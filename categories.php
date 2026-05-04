<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include "config.php";

$createdBy = $_SESSION['user_id'];

if (isset($_POST['update_asset_type'])) {
    $id = intval($_POST['PK_AssetType']);
    $name = mysqli_real_escape_string($conn, $_POST['AssetTypeName']);
    $category =  mysqli_real_escape_string($conn, $_POST['AssetTypeCategory']);

    $sql = "UPDATE AssetType 
            SET AssetTypeName = '$name', FK_Users = $createdBy, Category = $category
            WHERE PK_AssetType = $id";

    if (mysqli_query($conn, $sql)) {
        header("Location: categories.php?status=categoryupdated");
        exit();
    } else {
        echo "Error updating: " . mysqli_error($conn);
    }
}

if (isset($_POST['add_asset_type'])) {
    $name = mysqli_real_escape_string($conn, $_POST['AssetTypeName']);
    $category =  mysqli_real_escape_string($conn, $_POST['AssetTypeCategory']);


    $sql = "INSERT INTO AssetType (AssetTypeName, FK_Users, Category) VALUES ('$name', $createdBy, $category)";
    if (mysqli_query($conn, $sql)) {
        header("Location: categories.php?status=categoryadded");
        exit();
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}

if (isset($_POST['delete_asset_type'])) {
    $deleteId = intval($_POST['delete_id']);
    $sql = "DELETE FROM AssetType WHERE PK_AssetType = $deleteId";

    if (mysqli_query($conn, $sql)) {
        header("Location: categories.php?status=categorydeleted");
        exit();
    } else {
        echo "Error deleting asset type: " . mysqli_error($conn);
    }
}

$sqlCat = "SELECT 
                    a.*, 
                    b.name,
                    ifnull(
                        (
                            Select 1 from AssetMaster 
                            where FK_AssetType = a.PK_AssetType
                            limit 1
                        ),0
                    ) as hasItem,
                    ifnull(
                        (
                            Select count(PK_AssetMaster) from AssetMaster 
                            where FK_AssetType = a.PK_AssetType
                            and IsArchived = 0
                        ),0
                    ) as NonArchivedQty,
                     ifnull(
                        (
                            Select count(PK_AssetMaster) from AssetMaster 
                            where FK_AssetType = a.PK_AssetType
                            and IsArchived = 1
                        ),0
                    ) as ArchivedQty,

                    ifnull(
                        (
                            Select sum(Quantity) from GeneralAssetMaster 
                            where FK_AssetType = a.PK_AssetType
                            and IsArchived = 1
                        ),0
                    ) as GenArchivedQty,
                    ifnull(
                        (
                            Select sum(Quantity) from GeneralAssetMaster 
                            where FK_AssetType = a.PK_AssetType
                            and IsArchived = 0
                        ),0
                    ) as GenUnArchivedQty

            FROM AssetType a 
            LEFT JOIN Users b 
            ON a.FK_Users = b.PK_Users";

$resultCat = mysqli_query($conn, $sqlCat);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories</title>
    <link rel="icon" href="inventory.png" type="image/x-icon">
    <?php include "tools/head-plugin.php" ?>
</head>

<body>
    <?php include 'header.php'; ?>
    <div class="container">
        <h2 class="mb-4">Asset Types</h2>
        <?php include 'modal.php'; ?>
        <div class="d-flex justify-content-end mb-3">

            <button type="button" class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                <i class="bi bi-plus-circle me-1"></i>Add Category
            </button>

        </div>


        <div class="table-responsive">
            <table id="myTable" class="table table-bordered table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Asset Type Name</th>
                        <th>Items Quantity</th>
                        <th>Archived</th>
                        <th>Created On</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($resultCat && mysqli_num_rows($resultCat) > 0):
                        $count = 0;
                        while ($row = mysqli_fetch_assoc($resultCat)):
                            $count++;
                    ?>
                            <tr>
                                <td><?= $count ?></td>
                                <td><?= htmlspecialchars($row['AssetTypeName']) ?></td>
                                <td>
                                    <?php
                                    $nonArchivedTotal = $row['NonArchivedQty'] + $row['GenUnArchivedQty'];
                                    $badgeClass = $nonArchivedTotal > 0 ? 'bg-success' : 'bg-danger';
                                    ?>
                                    <span class="badge <?= $badgeClass ?>">
                                        <?= htmlspecialchars($nonArchivedTotal) ?>
                                    </span>
                                </td>

                                <td>
                                    <?php
                                    $archivedTotal = $row['ArchivedQty'] + $row['GenArchivedQty'];
                                    $badgeClass = $archivedTotal > 0 ? 'bg-success' : 'bg-danger';
                                    ?>
                                    <span class="badge <?= $badgeClass ?>">
                                        <?= htmlspecialchars($archivedTotal) ?>
                                    </span>
                                </td>

                                <td><?= htmlspecialchars($row['created_on']) ?></td>
                                <td>
                                    <button type="button"
                                        class="btn btn-sm btn-primary"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editAssetTypeModal"
                                        data-id="<?= $row['PK_AssetType'] ?>"
                                        data-name="<?= htmlspecialchars($row['AssetTypeName']) ?>"
                                        data-category="<?= htmlspecialchars($row['Category']) ?>"
                                        title="Edit Category">
                                        <i class="bi bi-pencil"></i>
                                    </button>

                                    <?php if (
                                        $row['hasItem'] == 0 &&
                                        ($row['NonArchivedQty'] + $row['GenUnArchivedQty']) == 0 &&
                                        ($row['ArchivedQty'] + $row['GenArchivedQty']) == 0
                                    ) { ?>
                                        <button type="button"
                                            class="btn btn-sm btn-danger"
                                            data-bs-toggle="modal"
                                            data-bs-target="#deleteAssetTypeModal"
                                            data-id="<?= $row['PK_AssetType'] ?>"
                                            data-name="<?= htmlspecialchars($row['AssetTypeName']) ?>"
                                            title="Delete Category">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    <?php } else { ?>
                                        <button type="button" class="btn btn-sm btn-danger" disabled
                                            title="Delete Category">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php
                        endwhile;
                    else:
                        ?>
                    <?php endif; ?>
                </tbody>
            </table>

        </div>
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
                        title: 'Asset Types',
                        exportOptions: {
                            columns: function(idx, data, node) {
                                return idx !== 5; // Exclude column indexes 6 and 7
                            }
                        }
                    },
                    {
                        extend: 'excelHtml5',
                        text: '<i class="bi bi-file-earmark-excel"></i>Excel',
                        className: 'btn btn-sm btn-success',
                        title: 'Asset Types',
                        exportOptions: {
                            columns: function(idx, data, node) {
                                return idx !== 5;
                            }
                        }
                    },
                    {
                        extend: 'pdfHtml5',
                        text: '<i class="bi bi-filetype-pdf"></i>PDF',
                        className: 'btn btn-sm btn-danger',
                        title: 'Asset Types',
                        exportOptions: {
                            columns: function(idx, data, node) {
                                return idx !== 5;
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
                },
                order: [
                    [2, 'desc'], // Sort by column index 2 (3rd column), ascending
                    [3, 'desc'] // Then by column index 3 (4th column), ascending
                ],
                columnDefs: [{
                        orderable: false,
                        targets: 0
                    } // disable sorting on index column
                ],
                drawCallback: function(settings) {
                    const api = this.api();
                    api.column(0, {
                            search: 'applied',
                            order: 'applied',
                            page: 'current'
                        })
                        .nodes()
                        .each(function(cell, i) {
                            cell.innerHTML = i + 1;
                        });
                }
            });
        });
    </script>



</body>

<?php
$conn->close();
?>

</html>