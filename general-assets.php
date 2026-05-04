<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include "config.php";

$createdBy = $_SESSION['user_id'];

if (isset($_POST['add_general_asset'])) {
    $name = mysqli_real_escape_string($conn, $_POST['Name']);
    $location = mysqli_real_escape_string($conn, $_POST['Location']);
    $quantity = intval($_POST['Quantity']);
    $type = intval($_POST['FK_AssetType']);
    $description = mysqli_real_escape_string($conn, $_POST['Descriptions']);
    $price = floatval($_POST['PurchasePrice']);
    $createdBy = $_SESSION['user_id'];

    $imagePath = '';
    if (!empty($_FILES['Image']['name'])) {
        $targetDir = "image/generalassets/";
        $originalName = basename($_FILES['Image']['name']);
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $uniqueName = time() . '_' . uniqid() . '.' . $extension;
        $imagePath = $targetDir . $uniqueName;

        move_uploaded_file($_FILES['Image']['tmp_name'], $imagePath);
    }

    $sql = "INSERT INTO GeneralAssetMaster (Name, Location, Quantity, FK_AssetType, Descriptions, Image, PurchasePrice, CreatedBy)
            VALUES ('$name', '$location', $quantity, $type, '$description', '$imagePath', $price, $createdBy)";

    if (mysqli_query($conn, $sql)) {
        $logDetails = "Added item: $name .";
        $currentUser = $_SESSION['user_id'];
        $actionUser = "Add General Asset";

        logActivity($conn, $currentUser, $actionUser, $logDetails);
        header("Location: general-assets.php?status=generalassetadded");
        exit();
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}

if (isset($_POST['giveGenAsset'])) {
    $assetId = (int) $_POST['asset_id'];
    $employeeId = (int) $_POST['employee_id'];
    $quantityToGive = (int) $_POST['quantity_to_give'];

    echo  $assetId;
    echo  $employeeId;
    echo $quantityToGive;

    $stmt = $conn->prepare("UPDATE GeneralAssetMaster SET Quantity = Quantity - ? WHERE GeneralAssetMaster = ?");
    $stmt->bind_param("ii", $quantityToGive, $assetId);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("INSERT INTO GeneralAssetHistory (FK_GeneralAssetMaster, FK_Employees, Quantity, FK_Users) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiii", $assetId, $employeeId, $quantityToGive, $createdBy);
    $stmt->execute();
    $stmt->close();

    header("Location: general-assets.php?status=generalassetupdated");
    exit();
}

if (isset($_POST['EditGenAsset'])) {
    $id = intval($_POST['GeneralAssetMaster']);
    $name = mysqli_real_escape_string($conn, $_POST['Name']);
    $quantity = intval($_POST['Quantity']);
    $location = mysqli_real_escape_string($conn, $_POST['Location']);
    $type = intval($_POST['FK_AssetType']);
    $description = mysqli_real_escape_string($conn, $_POST['Descriptions']);
    $price = floatval($_POST['PurchasePrice']);

    // Handle image upload if provided
    $imagePath = '';
    if (isset($_FILES['Image']) && $_FILES['Image']['error'] === UPLOAD_ERR_OK) {
        $imageTmp = $_FILES['Image']['tmp_name'];
        $imageName = basename($_FILES['Image']['name']);
        $imageDir = 'image/generalassets/';
        $targetPath = $imageDir . uniqid() . '_' . $imageName;

        if (!is_dir($imageDir)) {
            mkdir($imageDir, 0755, true);
        }

        if (move_uploaded_file($imageTmp, $targetPath)) {
            $imagePath = $targetPath;
        }
    }

    $query = "SELECT * FROM GeneralAssetMaster WHERE GeneralAssetMaster = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $current = $result->fetch_assoc();

    // Compare old vs new
    $changes = [];
    if (trim($current['Name']) !== trim($name)) {
        $changes['Name'] = [$current['Name'], $name];
    }

    if ((int)$current['Quantity'] !== $quantity) {
        $changes['Quantity'] = [$current['Quantity'], $quantity];
    }

    if (trim($current['Location']) !== trim($location)) {
        $changes['Location'] = [$current['Location'], $location];
    }

    if ((int)$current['FK_AssetType'] !== $type) {
        $changes['Asset Type'] = [$current['FK_AssetType'], $type];
    }

    if (trim($current['Descriptions']) !== trim($description)) {
        $changes['Descriptions'] = [$current['Descriptions'], $description];
    }

    if ((float)$current['PurchasePrice'] !== (float)$price) {
        $changes['PurchasePrice'] = [$current['PurchasePrice'], $price];
    }


    // Prepare SQL update query
    $query = "UPDATE GeneralAssetMaster 
              SET Name = '$name', Quantity = $quantity, Location = '$location', 
                  FK_AssetType = $type, Descriptions = '$description', 
                  PurchasePrice = $price";

    // Only update image if a new one was uploaded
    if ($imagePath !== '') {
        $query .= ", Image = '$imagePath'";
    }

    $query .= " WHERE GeneralAssetMaster = $id";

    // Execute query
    if (mysqli_query($conn, $query)) {

        if (!empty($changes)) {
            $logDetails = "<strong>Updated item: $name</strong><br><table class='table table-bordered table-sm mt-2'><thead><tr><th>Field</th><th>Old</th><th>New</th></tr></thead><tbody>";

            foreach ($changes as $field => [$old, $new]) {
                $logDetails .= "<tr><td>" . htmlspecialchars($field) . "</td><td>" . htmlspecialchars($old) . "</td><td>" . htmlspecialchars($new) . "</td></tr>";
            }

            $logDetails .= "</tbody></table>";

            $currentUser = $_SESSION['user_id'];
            $actionUser = "Update General Asset";

            logActivity($conn, $currentUser, $actionUser, $logDetails);
        }

        header("Location: general-assets.php?status=generalassetupdated");
        exit();
    } else {
        echo "Error updating asset: " . mysqli_error($conn);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['DeleteGenAsset'])) {
    $assetId = intval($_POST['GeneralAssetMaster']);
    $DeleteGenAssetRemarks = mysqli_real_escape_string($conn, $_POST['DeleteGenAssetRemarks']);

    // Update instead of delete
    $archiveQuery = "UPDATE GeneralAssetMaster SET IsArchived = 1, ArchivedRemarks = '$DeleteGenAssetRemarks'  WHERE GeneralAssetMaster = $assetId";

    if (mysqli_query($conn, $archiveQuery)) {
        $logDetails = "General Asset ID: $assetId has been archived.";

        $currentUser = $_SESSION['user_id'];
        $actionUser = "Archive General Asset";

        logActivity($conn, $currentUser, $actionUser, $logDetails);

        header("Location: general-assets.php?status=generalassetarchived");
        exit();
    } else {
        echo "Error archiving asset: " . mysqli_error($conn);
    }
}

$sqlGenA = "SELECT g.*, 
               a.AssetTypeName, 
               u.Name AS CreatedByName
        FROM GeneralAssetMaster g
        LEFT JOIN AssetType a ON g.FK_AssetType = a.PK_AssetType
        LEFT JOIN Users u ON g.CreatedBy = u.PK_Users
        WHERE g.IsArchived = 0
        ORDER BY g.GeneralAssetMaster
        ";

$resultGeneralAssets = mysqli_query($conn, $sqlGenA);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>General Assets</title>
    <link rel="icon" href="inventory.png" type="image/x-icon">
    <?php include "tools/head-plugin.php" ?>
    <link rel="stylesheet" href="css/gen-assets.css">

</head>

<body>
    <?php include 'header.php'; ?>
    <div class="container">
        <h2 class="mb-4">General Assets</h2>

        <?php include 'modal.php'; ?>

        <div class="d-flex justify-content-end mb-3">
            <button type="button" class="btn btn-dark me-2" data-bs-toggle="modal" data-bs-target="#addGenAssetModal">
                <i class="bi bi-plus-circle me-1"></i> Add
            </button>

            <a href="archive-gen.php" class="btn btn-dark">
                <i class="bi bi-archive me-1"></i> Archive
            </a>
        </div>

        <div class="table-responsive">
            <table id="myTable" class="table table-bordered table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Item</th>
                        <th>Quantity</th>
                        <th>Price</th>
                        <th>Location</th>
                        <th>Asset Type</th>
                        <th>Image</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($resultGeneralAssets && $resultGeneralAssets->num_rows > 0): ?>
                        <?php while ($row = $resultGeneralAssets->fetch_assoc()): ?>
                            <tr>
                                <td>GEN-<?= $row['GeneralAssetMaster'] ?></td>
                                <td><?= htmlspecialchars($row['Name']) ?></td>
                                <td><?= $row['Quantity'] ?></td>
                                <td><?= $row['PurchasePrice'] ?></td>
                                <td><?= htmlspecialchars($row['Location']) ?></td>
                                <td><?= $row['AssetTypeName'] ?></td>


                                <td>
                                    <?php if (!empty($row['Image'])): ?>
                                        <img
                                            src="<?= htmlspecialchars($row['Image']) ?>"
                                            alt="Asset Image"
                                            style="max-height: 80px; cursor: pointer;"
                                            data-bs-toggle="modal"
                                            data-bs-target="#imageModal<?= $row['GeneralAssetMaster'] ?>">
                                    <?php else: ?>
                                        No Image
                                    <?php endif; ?>
                                </td>

                                <?php if (!empty($row['Image'])): ?>
                                    <div class="modal fade" id="imageModal<?= $row['GeneralAssetMaster'] ?>" tabindex="-1" aria-labelledby="imageModalLabel<?= $row['Name'] ?>" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-body p-0">
                                                    <img src="<?= htmlspecialchars($row['Image']) ?>" alt="Asset Image" style="width: 100%; height: auto;">
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <td>

                                    <!-- Give Button -->
                                    <button type="button" class="btn btn-sm btn-success giveGenAssetBtn"
                                        data-id="<?= $row['GeneralAssetMaster'] ?>"
                                        data-name="<?= htmlspecialchars($row['Name'], ENT_QUOTES) ?>"
                                        data-quantity="<?= $row['Quantity'] ?>"
                                        data-bs-toggle="modal" data-bs-target="#giveGeneralAssetModal"
                                        title="Give Asset">
                                        <i class="bi bi-hand-index-thumb"></i> <!-- Bootstrap Icon for "Give" -->
                                    </button>

                                    <button type="button" class="btn btn-sm btn-primary editGenAssetBtn"
                                        data-id="<?= $row['GeneralAssetMaster'] ?>"
                                        data-name="<?= htmlspecialchars($row['Name'], ENT_QUOTES) ?>"
                                        data-location="<?= htmlspecialchars($row['Location'], ENT_QUOTES) ?>"
                                        data-quantity="<?= $row['Quantity'] ?>"
                                        data-type="<?= $row['FK_AssetType'] ?>"
                                        data-description="<?= htmlspecialchars($row['Descriptions'], ENT_QUOTES) ?>"
                                        data-price="<?= $row['PurchasePrice'] ?>"
                                        data-image="<?= htmlspecialchars($row['Image'], ENT_QUOTES) ?>"
                                        data-bs-toggle="modal" data-bs-target="#editGeneralAssetModal"
                                        title="Edit Asset">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-danger deleteGenAssetBtn"
                                        data-id="<?= $row['GeneralAssetMaster'] ?>"
                                        data-name="<?= htmlspecialchars($row['Name'], ENT_QUOTES) ?>"
                                        data-bs-toggle="modal" data-bs-target="#deleteGeneralAssetModal"
                                        title="Delete Asset">
                                        <i class="bi bi-archive"></i>
                                    </button>

                                    <!-- History Button -->
                                    <button type="button" class="btn btn-sm btn-info historyGenAssetBtn"
                                        data-id="<?= $row['GeneralAssetMaster'] ?>"
                                        data-name="<?= htmlspecialchars($row['Name'], ENT_QUOTES) ?>"
                                        data-bs-toggle="modal" data-bs-target="#historyGeneralAssetModal"
                                        title="History">
                                        <i class="bi bi-clock-history"></i> <!-- Bootstrap Icon for History -->
                                    </button>


                                </td>

                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>

                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Give Asset Modal -->
    <div class="modal fade" id="giveGeneralAssetModal" tabindex="-1" aria-labelledby="giveGeneralAssetModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form id="giveAssetForm" method="POST">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="giveGeneralAssetModalLabel">Give Asset</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">
                        <input type="hidden" id="giveAssetId" name="asset_id">

                        <div class="mb-3">
                            <label class="form-label">Asset Name:</label>
                            <p id="giveAssetName" class="form-control-plaintext fw-bold"></p>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Current Quantity:</label>
                            <p id="giveAssetQuantity" class="form-control-plaintext text-primary fw-semibold"></p>
                        </div>

                        <div class="mb-3">
                            <label for="quantityToGive" class="form-label">Quantity to Give:</label>
                            <input type="number" class="form-control" id="quantityToGive" name="quantity_to_give" min="1" required>
                        </div>

                        <div class="mb-3">
                            <label for="employeeSelect" class="form-label">Select Employee:</label>
                            <select class="form-select" id="employeeSelect" name="employee_id" required>
                                <option value="" disabled selected>Select an employee</option>
                                <?php

                                $sqlemployee = "SELECT PK_Employees, Name FROM Employees WHERE Status = 'Active' ORDER BY Name ASC";
                                $resultEmp = $conn->query($sqlemployee);

                                while ($row = $resultEmp->fetch_assoc()) {
                                    $id = htmlspecialchars($row['PK_Employees'], ENT_QUOTES);
                                    $name = htmlspecialchars($row['Name'], ENT_QUOTES);
                                    echo "<option value=\"$id\">$name</option>";
                                }
                                ?>
                            </select>
                        </div>


                    </div>

                    <div class="modal-footer">
                        <button type="submit" name="giveGenAsset" class="btn btn-success">Confirm Give</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Asset History Modal -->
    <div class="modal fade" id="historyGeneralAssetModal" tabindex="-1" aria-labelledby="historyGeneralAssetModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Asset Give History - <span id="historyAssetName" class="text-primary fw-bold"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <table class="table table-bordered table-striped" id="historyTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Employee</th>
                                <th>Quantity</th>
                                <th>Date Given</th>
                                <th>Gave By</th>
                            </tr>
                        </thead>
                        <tbody id="historyTableBody">
                            <!-- Populated via JS -->
                        </tbody>
                    </table>
                </div>
            </div>
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
    <script src="script/stopper.js"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <?php include "tools/alert-message.php"; ?>

    <!--Loading Submission Function -->
    <script>
        function createLoader() {
            if (document.getElementById('loadingOverlay')) return; // Already exists

            const overlay = document.createElement('div');
            overlay.id = 'loadingOverlay';
            overlay.style.position = 'fixed';
            overlay.style.top = 0;
            overlay.style.left = 0;
            overlay.style.width = '100%';
            overlay.style.height = '100%';
            overlay.style.backgroundColor = 'rgba(255, 255, 255, 0.8)';
            overlay.style.display = 'flex';
            overlay.style.alignItems = 'center';
            overlay.style.justifyContent = 'center';
            overlay.style.zIndex = '9999';
            overlay.style.display = 'none';

            const spinner = document.createElement('div');
            spinner.style.border = '6px solid #f3f3f3';
            spinner.style.borderTop = '6px solid #3498db';
            spinner.style.borderRadius = '50%';
            spinner.style.width = '50px';
            spinner.style.height = '50px';
            spinner.style.animation = 'spin 1s linear infinite';

            overlay.appendChild(spinner);
            document.body.appendChild(overlay);

            // Inject keyframes if not already present
            const style = document.createElement('style');
            style.innerHTML = `
                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
                `;
            document.head.appendChild(style);
        }

        function showLoader() {
            createLoader(); // Ensure it's created
            document.getElementById('loadingOverlay').style.display = 'flex';
        }

        function hideLoader() {
            const loader = document.getElementById('loadingOverlay');
            if (loader) loader.style.display = 'none';
        }
    </script>
    <!-- Loading Submission -->
    <script>
        document.getElementById('addGeneralAssetForm').addEventListener('submit', function(e) {
            showLoader();
        });

        document.getElementById('repairedAssetForm').addEventListener('submit', function(e) {
            showLoader();
        });
    </script>


    <script>
        document.querySelectorAll('.historyGenAssetBtn').forEach(button => {
            button.addEventListener('click', async () => {
                const assetId = button.getAttribute('data-id');
                const assetName = button.getAttribute('data-name');
                document.getElementById('historyAssetName').textContent = assetName;

                const tbody = document.getElementById('historyTableBody');
                tbody.innerHTML = '<tr><td colspan="4">Loading...</td></tr>';

                const response = await fetch(`tools/fetch_asset_history.php?asset_id=${assetId}`);
                const data = await response.json();

                tbody.innerHTML = '';
                if (data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="5" class="text-center">No history found.</td></tr>';
                } else {
                    data.forEach((entry, index) => {
                        const row = `
                    <tr>
                        <td>${index + 1}</td>
                        <td>${entry.employee}</td>
                        <td>${entry.quantity}</td>
                        <td>${entry.date}</td>
                        <td>${entry.name}</td>
                    </tr>
                `;
                        tbody.innerHTML += row;
                    });
                }
            });
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const giveButtons = document.querySelectorAll('.giveGenAssetBtn');

            giveButtons.forEach(button => {
                button.addEventListener('click', () => {
                    const assetId = button.getAttribute('data-id');
                    const assetName = button.getAttribute('data-name');
                    const quantity = parseInt(button.getAttribute('data-quantity')) || 0;

                    document.getElementById('giveAssetId').value = assetId;
                    document.getElementById('giveAssetName').textContent = assetName;
                    document.getElementById('giveAssetQuantity').textContent = quantity;

                    const inputField = document.getElementById('quantityToGive');
                    inputField.value = '';
                    inputField.max = quantity;
                    inputField.placeholder = `Max: ${quantity}`;
                });
            });

        });
    </script>


    <script>
        // --- Image Preview Logic ---
        const editImageInput = document.getElementById('editGenImageInput');
        const editImagePreview = document.getElementById('currentImage');
        const editImagePlaceholder = document.getElementById('editGenImagePlaceholder');

        editImageInput.addEventListener('change', function(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    editImagePreview.src = e.target.result;
                    editImagePreview.style.display = 'block';
                    editImagePlaceholder.style.display = 'none';
                }
                reader.readAsDataURL(file);
            }
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {

            // --- Image Preview Logic ---
            const addImageInput = document.getElementById('addGenImageInput');
            const addImagePreview = document.getElementById('addGenImagePreview');
            const addImagePlaceholder = document.getElementById('addGenImagePlaceholder');

            addImageInput.addEventListener('change', function(event) {
                const file = event.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        addImagePreview.src = e.target.result;
                        addImagePreview.style.display = 'block';
                        addImagePlaceholder.style.display = 'none';
                    }
                    reader.readAsDataURL(file);
                }
            });
        });
    </script>
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

</body>

<?php $conn->close(); ?>

</html>