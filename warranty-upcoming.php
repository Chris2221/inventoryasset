<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include "config.php";
include "tools/sender.php";

$createdBy = $_SESSION['user_id'];

function getUpdatedApproverJsonWithStatus($conn)
{
    $sql = "SELECT SettingValue FROM Settings WHERE SettingType = 1 LIMIT 1";
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        $settingRow = $result->fetch_assoc();
        $rawJson = $settingRow['SettingValue'];

        $approverSteps = json_decode($rawJson, true);
        if (!is_array($approverSteps)) {
            return null; // Invalid JSON
        }

        // Add status: 0 to each step
        foreach ($approverSteps as &$step) {
            $step['status'] = 0;
        }

        return json_encode($approverSteps);
    }

    return null; // No setting found
}


$selectedAssetType = isset($_GET['AssetType']) ? $_GET['AssetType'] : 'All';
$selectedStatus = isset($_GET['Status']) ? $_GET['Status'] : 'All';
$selectedCondition = isset($_GET['Condition']) ? $_GET['Condition'] : 'All';

$sql = "SELECT 
            ifnull((
                Select IsApproved from AssignApprovals
                where FK_AssetMaster = am.PK_AssetMaster 
                Order by PK_Approvals DESC
                LIMIT 1
            ),'na') as IsApproved,

            ifnull((
                Select ApprovalType from AssignApprovals
                where FK_AssetMaster = am.PK_AssetMaster 
                Order by PK_Approvals DESC
                LIMIT 1
            ),'na') as ApprovalType,
            am.*, 
            e.AssetTypeName,
            
            (
                Select FK_AssetInventory 
                from AssignApprovals axx 
                where 
                    axx.FK_AssetMaster = am.PK_AssetMaster 
                    and axx.FK_Employees = am.AssignedTo
                    and axx.ApprovalType = 'Assigned'
                    and axx.IsApproved = 1
                Order by PK_Approvals desc
                LIMIT 1
            ) as FK_AssetInventory

        FROM AssetMaster am
        LEFT JOIN AssetType e ON am.FK_AssetType = e.PK_AssetType

        WHERE am.IsArchived = 0 and am.Conditions != 7
        and
        WarrantyExpiryDate IS NOT NULL AND
        WarrantyExpiryDate BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";


// Apply Asset Type filter if selected
if ($selectedAssetType != 'All') {
    $sql .= " AND am.FK_AssetType = " . intval($selectedAssetType);  // Safely add the asset type filter
}

// Apply Status filter if selected (Assigned or Not Assigned)
if ($selectedStatus != 'All') {
    $statusCondition = $selectedStatus == 'Assigned' ? " AND am.AssignedTo != 0" : " AND am.AssignedTo = 0";
    $sql .= $statusCondition;  // Add the status condition
}

// Filter by Condition
if ($selectedCondition != 'All') {
    $sql .= " AND am.Conditions = '" . $conn->real_escape_string($selectedCondition) . "'";
}


// Default order by PK_AssetMaster
$sql .= " ORDER BY am.AssetTagNumber";

// Execute the query
$resultAsset = mysqli_query($conn, $sql);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upcoming Warranty Expiration</title>
    <link rel="icon" href="inventory.png" type="image/x-icon">
    <?php include "tools/head-plugin.php" ?>

</head>

<body>
    <?php include 'header.php'; ?>

    <div class="container">
        <h2 class="mb-4">Upcoming Warranty Expiration</h2>
        <?php include 'modal.php'; ?>
        <div class="d-flex justify-content-end mb-3">
            <a href="assets.php" class="btn btn-dark">
                <i class="bi bi-arrow-left me-1"></i> Back
            </a>
        </div>

        <div class="row">
            <form method="GET" id="filterForm" class="row">
                <!-- Asset Type -->
                <div class="mb-3 col-md-4">
                    <label for="AssetType" class="form-label">Asset Type</label>
                    <select name="AssetType" id="AssetType" class="form-select" required onchange="document.getElementById('filterForm').submit();">
                        <option value="All" <?= $selectedAssetType == 'All' ? 'selected' : '' ?>>All</option>
                        <?php
                        $assetTypeQuery = $conn->query("SELECT PK_AssetType, AssetTypeName FROM AssetType where Category = 0 ORDER BY AssetTypeName ASC");
                        while ($type = $assetTypeQuery->fetch_assoc()):
                        ?>
                            <option value="<?= $type['PK_AssetType'] ?>" <?= $selectedAssetType == $type['PK_AssetType'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($type['AssetTypeName']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <!-- Status -->
                <div class="mb-3 col-md-4">
                    <label for="Status" class="form-label">Status</label>
                    <select name="Status" id="Status" class="form-select" required onchange="document.getElementById('filterForm').submit();">
                        <option value="All" <?= $selectedStatus == 'All' ? 'selected' : '' ?>>All</option>
                        <option value="Assigned" <?= $selectedStatus == 'Assigned' ? 'selected' : '' ?>>Assigned</option>
                        <option value="Not Assigned" <?= $selectedStatus == 'Not Assigned' ? 'selected' : '' ?>>Not Assigned</option>
                    </select>
                </div>

                <!-- Condition Filter -->
                <div class="mb-3 col-md-4">
                    <label for="Condition" class="form-label">Condition</label>
                    <select name="Condition" id="Condition" class="form-select" onchange="document.getElementById('filterForm').submit();">
                        <option value="All" <?= $selectedCondition == 'All' ? 'selected' : '' ?>>All</option>
                        <option value="1" <?= $selectedCondition == '1' ? 'selected' : '' ?>>New</option>
                        <option value="4" <?= $selectedCondition == '4' ? 'selected' : '' ?>>Repaired</option>
                        <option value="5" <?= $selectedCondition == '5' ? 'selected' : '' ?>>Damaged</option>
                        <option value="6" <?= $selectedCondition == '6' ? 'selected' : '' ?>>Under Repair</option>
                    </select>
                </div>
            </form>

        </div>

        <?php include "tools/alert-message.php"; ?>

        <table id="myTable" class="table table-bordered table-hover table-striped table-responsive">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Asset Tag</th>
                    <th>Serial No.</th>
                    <th>Asset Type</th>
                    <th>Condition</th>
                    <th>Expiry</th>
                    <th>Image</th>
                    <th>Actions</th>

                </tr>
            </thead>
            <tbody>
                <?php if (mysqli_num_rows($resultAsset) > 0): ?>
                    <?php while ($row = mysqli_fetch_assoc($resultAsset)): ?>
                        <tr>
                            <td>AST-<?= htmlspecialchars($row['PK_AssetMaster']) ?></td>
                            <td><?= htmlspecialchars($row['AssetTagNumber']) ?></td>
                            <td><?= htmlspecialchars($row['SerialNumber']) ?></td>
                            <td><?= htmlspecialchars($row['AssetTypeName']) ?></td>

                            <td>
                                <?php
                                if ($row['Conditions'] == 1) {
                                    echo "New";
                                } elseif ($row['Conditions'] == 2) {
                                    echo "Good";
                                } elseif ($row['Conditions'] == 3) {
                                    echo "Used";
                                } elseif ($row['Conditions'] == 4) {
                                    echo "Repaired";
                                } elseif ($row['Conditions'] == 5) {
                                    echo "Damaged";
                                } elseif ($row['Conditions'] == 6) {
                                    echo "Under Repair";
                                } else {
                                    echo "Unknown";
                                }
                                ?>
                            </td>

                            <td>
                                <?php
                                $expiryDate = $row['WarrantyExpiryDate'];
                                $today = new DateTime(); // current date
                                $expiry = new DateTime($expiryDate);
                                $diff = $today->diff($expiry);

                                // Calculate days left
                                $daysLeft = (int)$diff->format('%r%a'); // %r includes sign (+/-)
                                if ($daysLeft < 0) {
                                    echo '<span class="badge rounded-pill bg-danger">Expired</span>';
                                } elseif ($daysLeft === 0) {
                                    echo '<span class="badge rounded-pill bg-warning text-dark">Expires today</span>';
                                } else {
                                    echo '<span class="badge rounded-pill bg-success">' . $daysLeft . ' day(s) left</span>';
                                }
                                ?>
                            </td>

                            <td>
                                <?php if (!empty($row['Image'])): ?>
                                    <img
                                        src="image/assetimages/<?= htmlspecialchars($row['Image']) ?>"
                                        alt="Asset Image"
                                        style="max-height: 80px; cursor: pointer;"
                                        data-bs-toggle="modal"
                                        data-bs-target="#imageModal<?= $row['PK_AssetMaster'] ?>">
                                <?php else: ?>
                                    No Image
                                <?php endif; ?>
                            </td>

                            <?php if (!empty($row['Image'])): ?>
                                <div class="modal fade" id="imageModal<?= $row['PK_AssetMaster'] ?>" tabindex="-1" aria-labelledby="imageModalLabel<?= $row['AssetTagNumber'] ?>" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-body p-0">
                                                <img src="image/assetimages/<?= htmlspecialchars($row['Image']) ?>" alt="Asset Image" style="width: 100%; height: auto;">
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <td>
                                <a href="#"
                                    class="btn btn-sm btn-primary me-1 btn-view-asset"
                                    data-bs-toggle="modal"
                                    data-bs-target="#viewAssetModal"
                                    data-asset='<?= json_encode($row) ?>'
                                    title="View">
                                    <i class="bi bi-eye-fill"></i>
                                </a>


                                <a href="#"
                                    class="btn btn-sm btn-secondary btn-history-asset"
                                    data-bs-toggle="modal"
                                    data-bs-target="#historyAssetModal"
                                    data-id="<?= $row['PK_AssetMaster'] ?>"
                                    title="View History">
                                    <i class="bi bi-clock-history"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>

                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <script src="libs/jquery-3.6.0.min.js"></script>
    <script src="libs/bootstrap.bundle.min.js"></script>
    <script src="libs/jspdf.umd.min.js"></script>
    <script src="libs/jspdf.plugin.autotable.min.js"></script>
    <script src="libs/jquery.dataTables.min.js"></script>

    <!-- Buttons extension -->
    <link rel="stylesheet" href="css/buttons.bootstrap5.min.css">
    <script src="libs/dataTables.buttons.min.js"></script>
    <script src="libs/buttons.bootstrap5.min.js"></script>
    <script src="libs/jszip.min.js"></script>
    <script src="libs/pdfmake.min.js"></script>
    <script src="libs/vfs_fonts.js"></script>
    <script src="libs/buttons.html5.min.js"></script>

    <script src="script.js"></script>

    <!-- DataTable-->
    <script>
        $(document).ready(function() {
            $('#myTable').DataTable({
                dom: '<"justify-content-between align-items-center mb-2"lfB>tip',
                pagingType: 'simple',
                buttons: [{
                        extend: 'csvHtml5',
                        text: '<i class="bi bi-filetype-csv"></i>CSV',
                        className: 'btn btn-sm btn-primary',
                        title: 'Inventory Assets',
                        exportOptions: {
                            columns: function(idx, data, node) {
                                return idx !== 6 && idx !== 7; // Exclude column indexes 6 and 7
                            }
                        }
                    },
                    {
                        extend: 'excelHtml5',
                        text: '<i class="bi bi-file-earmark-excel"></i>Excel',
                        className: 'btn btn-sm btn-success',
                        title: 'Inventory Assets',
                        exportOptions: {
                            columns: function(idx, data, node) {
                                return idx !== 6 && idx !== 7;
                            }
                        }
                    },
                    {
                        extend: 'pdfHtml5',
                        text: '<i class="bi bi-filetype-pdf"></i>PDF',
                        className: 'btn btn-sm btn-danger',
                        title: 'Inventory Assets',
                        exportOptions: {
                            columns: function(idx, data, node) {
                                return idx !== 6 && idx !== 7;
                            }
                        },
                        customize: function(doc) {
                            // Auto-adjust column widths evenly
                            var tableBody = doc.content[1].table.body;
                            var colCount = tableBody[0].length;

                            // Set even column widths
                            doc.content[1].table.widths = Array(colCount).fill('*');

                            // Optional: Set page margins or styles
                            doc.pageMargins = [20, 20, 20, 20]; // [left, top, right, bottom]
                        }
                    }
                ],

                language: {
                    lengthMenu: "Show _MENU_ entries",
                    search: "",
                    searchPlaceholder: "Search..."
                }
            });
        });
    </script>

    <!--Preview Image -->
    <script>
        // Preview image on file select
        document.getElementById('assetImageInput').addEventListener('change', function(event) {
            const preview = document.getElementById('assetImagePreview');
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                reader.readAsDataURL(file);
            } else {
                preview.src = '#';
                preview.style.display = 'none';
            }
        });

        document.getElementById('assetImageInput2').addEventListener('change', function(event) {
            const preview = document.getElementById('assetImagePreview2');
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                reader.readAsDataURL(file);
            } else {
                preview.src = '#';
                preview.style.display = 'none';
            }
        });
    </script>

    <!--Repaired Visibility-->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggle = document.getElementById('isRepairedSwitch');
            const repairedFields = document.getElementById('repairedFields');

            const repairedDate = document.getElementById('RepairedDate');
            const repairedBy = document.getElementById('RepairedBy');
            const repairValue = document.getElementById('RepairValue');

            toggle.addEventListener('change', function() {
                const isChecked = this.checked;
                repairedFields.style.display = isChecked ? 'block' : 'none';

                // Toggle required attributes
                repairedDate.required = isChecked;
                repairedBy.required = isChecked;
                repairValue.required = isChecked;
            });
        });
    </script>

    <!--Fetch Repair info-->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('repairedAssetModal');

            const repairDate = document.getElementById('RepairDate');
            const repairDetails = document.getElementById('RepairDetails');


            document.querySelectorAll('.btn-repaired-asset').forEach(button => {
                button.addEventListener('click', function() {
                    const assetId = this.dataset.id;

                    repairDate.value = '';
                    repairDetails.value = '';

                    // Fetch repair history data
                    fetch('tools/load-assetRepair.php?id=' + encodeURIComponent(assetId))
                        .then(response => response.json())
                        .then(data => {
                            if (data && data.success) {
                                const record = data.record;
                                if (record.RepairDate) repairDate.value = record.RepairDate;
                                if (record.RepairDetails) repairDetails.value = record.RepairDetails;
                            }
                        })
                        .catch(error => {
                            console.error('Error fetching data:', error);
                        });
                });

            });
        });
    </script>

    <script>
        const today = new Date();
        const fiveDaysAgo = new Date();
        fiveDaysAgo.setDate(today.getDate() - 5);

        const formatDate = (date) => date.toISOString().split('T')[0];

        const repairedDateInput = document.getElementById('RepairedDate');
        repairedDateInput.min = formatDate(fiveDaysAgo);
        repairedDateInput.max = formatDate(today);

        const today2 = new Date().toISOString().split('T')[0];
        document.getElementById('RepairDate').max = today2;
    </script>

    <script>
        document.addEventListener('click', function(e) {
            if (e.target.closest('.view-image-btn')) {
                const btn = e.target.closest('.view-image-btn');
                const imageUrl = btn.getAttribute('data-image');
                document.getElementById('previewImage').src = imageUrl;
                const modal = new bootstrap.Modal(document.getElementById('imagePreviewModal'));
                modal.show();
            }
        });
    </script>


</body>

<?php
$conn->close();
?>

</html>