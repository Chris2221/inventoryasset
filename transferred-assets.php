<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include "config.php";
include "tools/sender.php";

$createdBy = $_SESSION['user_id'];
date_default_timezone_set('Asia/Manila');

if (isset($_POST['MarkReceived'])) {
    $transferId = $_POST['transfer_id'];
    $remarks = htmlspecialchars(trim($_POST['remarks']));
    $receivedBy = $_POST['receivedBy'];
    $receivedDate = $_POST['received_date'];

    // Handle file upload
    $uploadDir = 'image/transferred_images/'; // make sure this folder exists and is writable
    $imageName = $_FILES['received_image']['name'];
    $imageTmpName = $_FILES['received_image']['tmp_name'];

    // Generate unique filename to avoid conflicts
    $uniqueName = time() . '_' . basename($imageName);
    $targetPath = $uploadDir . $uniqueName;

    // Validate and move uploaded file
    if (move_uploaded_file($imageTmpName, $targetPath)) {
        // Save path or filename (your choice — below is filename only)
        $receivedImage = $uniqueName;

        // Update DB
        $stmt = $conn->prepare("UPDATE TransferAssets SET ReceivedImage = ?, ReceivedRemarks = ?, ReceivedBy = ?, Status = 'Received', DateReceived = ? WHERE PK_TransferAssets = ?");
        $stmt->bind_param("ssssi", $receivedImage, $remarks, $receivedBy, $receivedDate, $transferId);

        if ($stmt->execute()) {
            TransferReceived($transferId, $conn);
            header("Location: transferred-assets.php?status=assetreceived");
        } else {
            header("Location: transferred-assets.php?status=error");
        }
        $stmt->close();
    } else {
        header("Location: transferred-assets.php?status=noimage");
    }
    exit;
}

// default to 'Pending' if not set
$statusFilter = $_GET['Status'] ?? 'Approved';

$sqlOutbounds = "SELECT 
            oa.PK_TransferAssets,
            oa.Descriptions,
            oa.DateAcquired,
            ua.name AS UserName,
            oa.Status,
            oa.CreatedOn,
            (
                Select sum(Quantity) from TransferAssetsList where FK_TransferAssets = oa.PK_TransferAssets
            ) as Quantity

        FROM TransferAssets oa
        LEFT JOIN Employees ua ON oa.FK_Users = ua.PK_Employees
        WHERE oa.Status = ?
        ORDER BY oa.CreatedOn DESC";

$stmt = $conn->prepare($sqlOutbounds);
$stmt->bind_param("s", $statusFilter);
$stmt->execute();
$resultOutbounds = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transferred Assets</title>
    <link rel="icon" href="inventory.png" type="image/x-icon">
    <?php include "tools/head-plugin.php" ?>
    <style>
        /* Modal Enhancements */
        .modal-content {
            border-radius: 0.75rem;
            overflow: hidden;
            border: none;
        }

        .modal-header {
            background-color: #d1e7dd;
            /* Light green for success/received */
            color: #0a3622;
            border-bottom: 1px solid #a3cfbb;
        }

        .modal-title {
            color: #0a3622;
        }

        /* Form Styling */
        .form-label {
            font-weight: 500;
            color: #495057;
        }

        .view-label {
            font-weight: 500;
            color: #6c757d;
            font-size: 0.875rem;
            margin-bottom: 0;
        }

        .view-data {
            font-weight: 500;
            color: #343a40;
            background-color: #e9ecef;
            padding: 0.375rem 0.75rem;
            border-radius: 0.25rem;
            min-height: 38px;
            display: flex;
            align-items: center;
        }

        .form-control:focus,
        .form-select:focus {
            box-shadow: 0 0 0 0.25rem rgba(25, 135, 84, 0.25);
            border-color: #84cbb2;
        }

        /* Custom File Input & Image Preview */
        .file-upload-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
            width: 100%;
        }

        .file-upload-wrapper input[type=file] {
            font-size: 100px;
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            cursor: pointer;
        }

        .file-upload-label {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem 1rem;
            border: 2px dashed #ced4da;
            border-radius: 0.375rem;
            cursor: pointer;
            transition: background-color 0.2s, border-color 0.2s;
            text-align: center;
        }

        .file-upload-label:hover {
            background-color: #e9ecef;
            border-color: #adb5bd;
        }

        .image-preview-container {
            width: 100%;
            height: 180px;
            border: 2px dashed #dee2e6;
            border-radius: 0.375rem;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f8f9fa;
            overflow: hidden;
        }

        .image-preview {
            display: none;
            max-width: 100%;
            max-height: 100%;
            object-fit: cover;
        }

        .image-placeholder {
            color: #6c757d;
            text-align: center;
        }

        /* Footer Buttons */
        .modal-footer {
            background-color: #f8f9fa;
        }



        /* Animation Container */
        .loading-animation-container {
            position: relative;
            height: 150px;
            width: 100%;
            overflow: hidden;
        }

        /* Conveyor Belt */
        .conveyor-belt {
            position: absolute;
            bottom: 30px;
            left: 0;
            width: 100%;
            height: 10px;
            background-color: #6c757d;
            border-radius: 5px;
        }

        .conveyor-belt::before {
            content: '';
            position: absolute;
            top: 2px;
            left: 0;
            width: 200%;
            height: 6px;
            background: repeating-linear-gradient(-45deg,
                    #868e96,
                    #868e96 10px,
                    #6c757d 10px,
                    #6c757d 20px);
            animation: move-belt 2s linear infinite;
        }

        /* Warehouse/Receiving Bay Icon */
        .receiving-bay {
            position: absolute;
            right: 10%;
            bottom: 35px;
            font-size: 4rem;
            color: #495057;
        }

        /* Animated Boxes */
        .box-icon {
            position: absolute;
            bottom: 35px;
            font-size: 2.5rem;
            color: #a4704b;
            /* Brown color for box */
            animation: move-box 4s linear infinite;
        }

        .box-1 {
            animation-delay: 0s;
        }

        .box-2 {
            animation-delay: 1.5s;
        }

        .box-3 {
            animation-delay: 3s;
        }

        /* Keyframe Animations */
        @keyframes move-belt {
            from {
                transform: translateX(0);
            }

            to {
                transform: translateX(-50%);
            }
        }

        @keyframes move-box {
            0% {
                left: -10%;
                transform: scale(0.8) rotate(0deg);
                opacity: 0;
            }

            10% {
                left: 10%;
                transform: scale(1) rotate(0deg);
                opacity: 1;
            }

            80% {
                left: 75%;
                transform: scale(1) rotate(0deg);
                opacity: 1;
            }

            90% {
                left: 80%;
                transform: scale(0.5) rotate(10deg);
                opacity: 0;
            }

            100% {
                left: 80%;
                opacity: 0;
            }
        }
    </style>
</head>

<body>
    <?php include 'header.php'; ?>
    <div class="container">
        <h2 class="mb-4">Transferred Assets</h2>

        <?php include 'modal.php'; ?>
        <a href="?Status=Approved" class="btn btn-outline-primary">
            <i class="bi bi-arrow-clockwise"></i> To Receive
        </a>

        <a href="?Status=Received" class="btn btn-outline-success">
            <i class="bi bi-arrow-return-left"></i> Received
        </a>
        <br><br>
        <?php
        if ($statusFilter === 'Approved') {
            $statusFilter = "To Receive";
        } else {
            $statusFilter = "Received";
        }
        ?>

        <h5 class="mt-3">
            Status: <span class="badge bg-light text-dark border border-secondary"><?php echo $statusFilter; ?></span>
        </h5>

        <br>

        <div class="table-responsive">
            <table id="myTable" class="table table-bordered table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Description</th>
                        <th>Date Requested</th>
                        <th>Prepared By</th>
                        <th>Total</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($resultOutbounds && $resultOutbounds->num_rows > 0): ?>
                        <?php while ($row = $resultOutbounds->fetch_assoc()): ?>
                            <tr>
                                <td>REQ-<?= $row['PK_TransferAssets'] ?></td>
                                <td><?= htmlspecialchars($row['Descriptions']) ?></td>
                                <td><?= htmlspecialchars($row['DateAcquired']) ?></td>
                                <td><?= htmlspecialchars($row['UserName'] ?? 'Unknown') ?></td>

                                <?php
                                $qtyTotal = htmlspecialchars($row['Quantity']);
                                $totalClass = 'bg-success';
                                ?>
                                <td>
                                    <span class="badge <?= $totalClass ?>">
                                        <?= $qtyTotal ?>
                                    </span>
                                </td>

                                <td>
                                    <a href="edit-transfer.php?id=<?= $row['PK_TransferAssets'] ?>&status=<?= hash('sha256', $row['Status']) ?>&type=<?= hash('sha256', 'Inbound') ?>" class="btn btn-sm btn-primary" title="View Request">
                                        <?php if (htmlspecialchars($row['Status']) == "Approved" || htmlspecialchars($row['Status']) == "Received") { ?>
                                            <i class="bi bi-eye"></i>
                                        <?php } ?>
                                    </a>

                                    <?php if (htmlspecialchars($row['Status']) != "Received") { ?>
                                        <button type="button" class="btn btn-sm btn-success mark-received-btn"
                                            data-id="<?= $row['PK_TransferAssets'] ?>"
                                            data-bs-toggle="modal"
                                            data-bs-target="#receivedModal"
                                            title="Mark as Received">
                                            <i class="bi bi-box-arrow-in-down"></i>
                                        </button>
                                    <?php } ?>

                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>

                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

    <!-- Receiving Loading Modal -->
    <div class="modal fade" id="receivingModal" tabindex="-1" aria-labelledby="receivingModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="receivingModalLabel">
                        Receiving Items...
                    </h5>
                </div>
                <div class="modal-body text-center p-4">
                    <div class="loading-animation-container mb-3">
                        <div class="conveyor-belt"></div>
                        <i class="bi bi-box-seam-fill box-icon box-1"></i>
                        <i class="bi bi-box-seam-fill box-icon box-2"></i>
                        <i class="bi bi-box-seam-fill box-icon box-3"></i>
                        <i class="bi bi-door-closed-fill receiving-bay"></i>
                    </div>
                    <p class="mb-0 text-muted">Please wait while the items are being processed.</p>
                    <div class="progress mt-3" role="progressbar" aria-label="Animated striped example" aria-valuenow="75" aria-valuemin="0" aria-valuemax="100" style="height: 5px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 100%"></div>
                    </div>
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
                        title: 'Transfer Assets',
                        exportOptions: {
                            columns: function(idx, data, node) {
                                return idx !== 7; // Exclude column indexes 6 and 7
                            }
                        }
                    },
                    {
                        extend: 'excelHtml5',
                        text: '<i class="bi bi-file-earmark-excel"></i>Excel',
                        className: 'btn btn-sm btn-success',
                        title: 'Transfer Assets',
                        exportOptions: {
                            columns: function(idx, data, node) {
                                return idx !== 7;
                            }
                        }
                    },
                    {
                        extend: 'pdfHtml5',
                        text: '<i class="bi bi-filetype-pdf"></i>PDF',
                        className: 'btn btn-sm btn-danger',
                        title: 'Transfer Assets',
                        exportOptions: {
                            columns: function(idx, data, node) {
                                return idx !== 7;
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
        document.querySelectorAll('.mark-received-btn').forEach(button => {
            button.addEventListener('click', () => {
                const transferId = button.getAttribute('data-id');
                document.getElementById('transferIdInput').value = transferId;
                document.getElementById('viewTransferId').textContent = "REQ-" + transferId;
            });
        });
    </script>


    <script>
        // --- Image Preview Logic ---
        const imageInput = document.getElementById('receivedImageInput');
        const imagePreview = document.getElementById('receivedImagePreview');
        const imagePlaceholder = document.getElementById('receivedImagePlaceholder');

        imageInput.addEventListener('change', function(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    imagePreview.src = e.target.result;
                    imagePreview.style.display = 'block';
                    imagePlaceholder.style.display = 'none';
                }
                reader.readAsDataURL(file);
            }
        });
    </script>


    <script>
        document.getElementById('receivedForm').addEventListener('submit', function(e) {
            const modal = bootstrap.Modal.getInstance(document.getElementById('receivedModal'));
            if (modal) {
                modal.hide();
            }

            const receivingModal = new bootstrap.Modal(document.getElementById('receivingModal'));
            receivingModal.show();

        });
    </script>

</body>

<?php $conn->close(); ?>

</html>