<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include "config.php";

$createdBy = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['RestoreGenAsset'])) {
    $assetId = intval($_POST['GeneralAssetMaster']);

    $restoreQuery = "UPDATE GeneralAssetMaster SET IsArchived = 0 WHERE GeneralAssetMaster = $assetId";

    if (mysqli_query($conn, $restoreQuery)) {
        $logDetails = "General Asset ID: $assetId has been restored.";

        $currentUser = $_SESSION['user_id'];
        $actionUser = "Restore General Asset";

        logActivity($conn, $currentUser, $actionUser, $logDetails);
        header("Location: archive-gen.php?status=generalassetrestored");
        exit();
    } else {
        echo "Error restoring asset: " . mysqli_error($conn);
    }
}

$sqlGenA = "SELECT g.*, 
               a.AssetTypeName, 
               u.Name AS CreatedByName
        FROM GeneralAssetMaster g
        LEFT JOIN AssetType a ON g.FK_AssetType = a.PK_AssetType
        LEFT JOIN Users u ON g.CreatedBy = u.PK_Users
        WHERE g.IsArchived = 1
        ORDER BY g.GeneralAssetMaster";

$resultGeneralAssets = mysqli_query($conn, $sqlGenA);


?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>General Items Archived</title>
    <link rel="icon" href="inventory.png" type="image/x-icon">
    <?php include "tools/head-plugin.php" ?>

</head>

<body>
    <?php include 'header.php'; ?>
    <div class="container">
        <h2 class="mb-4">Archived - General Assets</h2>

        <?php include 'modal.php'; ?>

        <div class="d-flex justify-content-end mb-3">
            <a href="general-assets.php" class="btn btn-dark">
                <i class="bi bi-arrow-left me-1"></i> Back
            </a>
        </div>

        <div class="table-responsive">
            <table id="myTable" class="table table-bordered table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Item</th>
                        <th>Quantity</th>
                        <th>Price</th>
                        <th>Location</th>
                        <th>Asset Type</th>
                        <th>Reason</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($resultGeneralAssets && $resultGeneralAssets->num_rows > 0): ?>
                        <?php while ($row = $resultGeneralAssets->fetch_assoc()): ?>
                            <tr>
                                <td><?= $row['GeneralAssetMaster'] ?></td>
                                <td><?= htmlspecialchars($row['Name']) ?></td>
                                <td><?= $row['Quantity'] ?></td>
                                <td><?= $row['PurchasePrice'] ?></td>
                                <td><?= htmlspecialchars($row['Location']) ?></td>
                                <td><?= $row['AssetTypeName'] ?></td>
                                <td><?= $row['ArchivedRemarks'] ?></td>

                                <td>
                                    <button type="button"
                                        class="btn btn-sm btn-success restoreGenAssetBtn"
                                        data-id="<?= $row['GeneralAssetMaster'] ?>"
                                        data-name="<?= htmlspecialchars($row['Name'], ENT_QUOTES) ?>"
                                        data-bs-toggle="modal"
                                        data-bs-target="#restoreGeneralAssetModal"
                                        title="Restore Item">
                                        <i class="bi bi-arrow-counterclockwise"></i>
                                    </button>
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
                            title: 'General Assets',
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
                            title: 'General Assets',
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
                            title: 'General Assets',
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


        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const restoreButtons = document.querySelectorAll('.restoreGenAssetBtn');
                const restoreAssetName = document.getElementById('restoreAssetName');
                const restoreAssetId = document.getElementById('restoreAssetId2');

                restoreButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        const assetId = this.getAttribute('data-id');
                        const assetName = this.getAttribute('data-name');

                        restoreAssetId.value = assetId;
                        restoreAssetName.textContent = assetName;
                    });
                });
            });
        </script>


</body>

<?php $conn->close(); ?>

</html>