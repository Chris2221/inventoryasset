<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include "config.php";

$createdBy = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['Approve'])) {
    $id = $_POST['PK_OutboundAssets'];

    $stmt = $conn->prepare("UPDATE AssetMaster SET `Conditions` = 7  WHERE PK_AssetMaster = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        header("Location: archive.php?status=approvedDecommissioned");
    } else {
        echo "Error: " . $stmt->error;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['Disapprove'])) {
    $id = $_POST['PK_OutboundAssets'];
    $reasonForRejection = $_POST['RejectionReason'];

    $stmt = $conn->prepare("UPDATE AssetMaster SET IsArchived = 0, ReasonForRejection = ?  WHERE PK_AssetMaster = ?");
    $stmt->bind_param("si", $reasonForRejection, $id);

    if ($stmt->execute()) {
        header("Location: archive.php?status=restored");
    } else {
        echo "Error: " . $stmt->error;
    }
}

$status = isset($_GET['Status']) && !empty($_GET['Status']) ? $_GET['Status'] : 'Pending';
$sqlFilter = '';

if ($status == 'Approved') {
    $sqlFilter = 'and `Conditions` = 7';
} else {
    $sqlFilter = 'and `Conditions` != 7';
}

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
            e.AssetTypeName
        FROM AssetMaster am
        LEFT JOIN AssetType e ON am.FK_AssetType = e.PK_AssetType 
        where IsArchived = 1 $sqlFilter";

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
    <title>Archived</title>
    <link rel="icon" href="inventory.png" type="image/x-icon">
    <?php include "tools/head-plugin.php" ?>

</head>

<body>
    <?php include 'header.php'; ?>
    <div class="container">
        <h2 class="mb-4">Archived</h2>
        <?php include 'modal.php'; ?>
        <div class="d-flex justify-content-end mb-3">
            <a href="assets.php" class="btn btn-dark">
                <i class="bi bi-arrow-left me-1"></i> Back
            </a>
        </div>


        <a href="?Status=Pending" class="btn btn-outline-primary">
            <i class="bi bi-hourglass-split"></i> For Approval
        </a>

        <a href="?Status=Approved" class="btn btn-outline-danger">
            <i class="bi bi-check-circle"></i> Decommissioned
        </a><br><br>


        <?php if($status == 'Approved'){
            $status = 'Decommissioned';
        }
        ?>
        <h5 class="mt-3">
            Status: <span class="badge bg-light text-dark border border-secondary"><?php echo $status ; ?></span>
        </h5>
        <br>


        <table id="myTable" class="table table-bordered table-hover">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Asset Tag</th>
                    <th>Serial No.</th>
                    <th>Asset Type</th>
                    <th>Brand</th>
                    <th>Condition</th>
                    <th>Reason</th>
                    <th>Image</th>
                    <th>Actions</th>

                </tr>
            </thead>
            <tbody>
                <?php if (mysqli_num_rows($resultAsset) > 0): ?>
                    <?php while ($row = mysqli_fetch_assoc($resultAsset)): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['PK_AssetMaster']) ?></td>
                            <td><?= htmlspecialchars($row['AssetTagNumber']) ?></td>
                            <td><?= htmlspecialchars($row['SerialNumber']) ?></td>
                            <td><?= htmlspecialchars($row['AssetTypeName']) ?></td>
                            <td><?= htmlspecialchars($row['BrandManufacturer']) ?></td>

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
                                } elseif ($row['Conditions'] == 7) {
                                    echo "Decommissioned";
                                } else {
                                    echo "Unknown";
                                }
                                ?>
                            </td>
                            <td><?= htmlspecialchars($row['ArchivedRemarks']) ?></td>
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
                                <div class="modal fade" id="imageModal<?= $row['PK_AssetMaster'] ?>" tabindex="-1" aria-labelledby="imageModalLabel<?= $row['id'] ?>" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-body p-0">
                                                <img src="assetimages/<?= htmlspecialchars($row['Image']) ?>" alt="Asset Image" style="width: 100%; height: auto;">
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

                                <?php if ($status == 'Pending') { ?>

                                    <?php if ($_SESSION['role'] == 'Supervisor' || $_SESSION['role'] == 'Manager' || $_SESSION['role'] == 'Admin') { ?>

                                        <button class="btn btn-sm btn-outline-success" onclick="showApproveModal(<?= $row['PK_AssetMaster'] ?>)">
                                            <i class="bi bi-check-circle"></i>
                                        </button>

                                        <button class="btn btn-sm btn-outline-danger" onclick="showDisapproveModal(<?= $row['PK_AssetMaster'] ?>)">
                                            <i class="bi bi-x-circle"></i>
                                        </button>

                                    <?php } ?>
                                <?php } ?>

                            </td>

                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>

                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

    <!-- Buttons extension -->
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css">
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="script.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <?php include "tools/alert-message.php"; ?>

    <script>
        $(document).ready(function() {
            $('#myTable').DataTable({
                dom: '<"justify-content-between align-items-center mb-2"lfB>tip',
                pagingType: 'simple',
                buttons: [{
                        extend: 'csvHtml5',
                        text: '<i class="bi bi-filetype-csv"></i>CSV',
                        className: 'btn btn-sm btn-primary',
                        title: 'Archived Assets',
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
                        title: 'Archived Assets',
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
                        title: 'Archived Assets',
                        exportOptions: {
                            columns: function(idx, data, node) {
                                return idx !== 6 && idx !== 7;
                            }
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

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const restoreButtons = document.querySelectorAll('.btn-restore-asset');
            const restoreAssetId = document.getElementById('restoreAssetId');
            const restoreAssetTagText = document.getElementById('restoreAssetTagText');

            restoreButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const assetId = this.getAttribute('data-id');
                    const assetTag = this.getAttribute('data-assettag'); // Optional: include in <a> tag if needed

                    restoreAssetId.value = assetId;
                    if (assetTag) {
                        restoreAssetTagText.textContent = "Asset Tag: " + assetTag;
                    }
                });
            });
        });
    </script>


</body>

<?php
$conn->close();
?>

</html>