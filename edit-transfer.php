<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include "config.php";

$createdBy = $_SESSION['user_id'];

$hash = $_GET['status'];
$isApproved = false;
$status = "";

if ($hash === hash('sha256', 'Approved')) {
    $isApproved = true;
    $status = "Approved";
}

if ($hash === hash('sha256', 'Rejected')) {
    $isApproved = true;
    $status = "Rejected";
}
if ($hash === hash('sha256', 'Returned')) {
    $isApproved = true;
    $status = "Returned";
}

if ($hash === hash('sha256', 'Received')) {
    $isApproved = true;
    $status = "Received";
}

$type = $_GET['type'];
$inbound = false;
if ($type === hash('sha256', 'Inbound')) {
    $inbound  = true;
}

$outboundId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submitRequest'])) {
    if ($outboundId <= 0) {
        die("Invalid or missing OutboundAssets ID.");
    }

    $description = $_POST['Description'];
    $dateAcquired = $_POST['DateRequested'];
    $departureDate = $_POST['departureDate'];
    $receiveBy = $_POST['receiveBy'];
    $fkUser = $_POST['Employee'];
    $requestedBy = $_POST['RequestedBy'];
    $approvedBy = $_POST['ApprovedBy'];
    $itDept = $_POST['ITDepartment'];

    $approvals = json_encode([
        'requested' => (int)$requestedBy,
        'approved' => (int)$approvedBy,
        'itdept'   => (int)$itDept
    ]);

    $status = 'Pending';

    // Image Handling
    $imageSql = "";
    $imagePath = '';

    if (isset($_FILES['Image']) && $_FILES['Image']['error'] === UPLOAD_ERR_OK) {
        $imageTmp = $_FILES['Image']['tmp_name'];
        $imageName = basename($_FILES['Image']['name']);
        $imageDir = 'image/transfer_images/';
        $targetPath = $imageDir . uniqid() . '_' . $imageName;

        if (!is_dir($imageDir)) {
            mkdir($imageDir, 0755, true);
        }

        if (move_uploaded_file($imageTmp, $targetPath)) {
            $imagePath = $targetPath;
            $imageSql = ", Image = ?";
        }
    }

    // Update the OutboundAssets record
    $query = "UPDATE TransferAssets 
              SET Descriptions = ?, DateAcquired = ?, DepartureDate= ?, ExpectedReceiver = ?, FK_Users = ?, Approvals = ?, Status = ?, CreatedBy = ? $imageSql 
              WHERE PK_TransferAssets = ?";

    if ($imageSql) {
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssssissssi", $description, $dateAcquired, $departureDate, $receiveBy, $fkUser, $approvals, $status, $createdBy, $imagePath, $outboundId);
    } else {
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssssisssi", $description, $dateAcquired, $departureDate, $receiveBy, $fkUser, $approvals, $status, $createdBy, $outboundId);
    }

    if ($stmt->execute()) {
        // Delete previous entries in OutboundAssetsList
        $conn->query("DELETE FROM TransferAssetsList WHERE FK_TransferAssets = $outboundId");

        // Reinsert new entries
        $tableValue = $_POST['tableValue'] ?? '[]';
        $data = json_decode($tableValue, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            die("Invalid JSON data.");
        }

        if (!empty($data)) {
            $stmt = $conn->prepare("INSERT INTO TransferAssetsList (FK_TransferAssets, FK_AssetMaster, FK_GeneralAssetMaster, Quantity) VALUES (?, ?, ?, ?)");

            foreach ($data as $item) {
                $fkAssetMaster = $item['type'] === 'IT' ? (int)$item['id'] : 0;
                $fkGeneralAssetMaster = $item['type'] === 'GAM' ? (int)$item['id'] : 0;
                $quantity = (int)$item['quantity'];

                $stmt->bind_param("iiii", $outboundId, $fkAssetMaster, $fkGeneralAssetMaster, $quantity);

                if (!$stmt->execute()) {
                    echo "Insert failed for item with id {$item['id']}: " . $stmt->error;
                }
            }

            $stmt->close();
        }

        header("Location: transfer-assets.php?status=requestupdated");
        exit();
    } else {
        echo "Error updating request: " . $stmt->error;
    }
}


$query = "
    SELECT
        CASE
            WHEN ax.FK_AssetMaster = 0 THEN 'GAM'
            ELSE 'IT'
        END AS Type,
        COALESCE(AM.AssetTagNumber, GAM.Name) AS Name,
        CASE
            WHEN ax.FK_AssetMaster > 0 THEN ax.FK_AssetMaster
            ELSE ax.FK_GeneralAssetMaster
        END AS ID,
        CASE
            WHEN ax.FK_AssetMaster > 0 THEN
                CASE 
                    WHEN ax.Quantity > 0 THEN 0
                    ELSE 1
                END
            ELSE
                GAM.Quantity - IFNULL((
                    SELECT SUM(Quantity)
                    FROM TransferAssetsList
                    WHERE FK_GeneralAssetMaster = ax.FK_GeneralAssetMaster
                ), 0)
        END AS CurrentStock,
        ax.Quantity AS Quantity,
        COALESCE(AT_AM.AssetTypeName, AT_GAM.AssetTypeName) AS AssetType
    FROM TransferAssetsList ax
    LEFT JOIN AssetMaster AM ON ax.FK_AssetMaster = AM.PK_AssetMaster
    LEFT JOIN GeneralAssetMaster GAM ON ax.FK_GeneralAssetMaster = GAM.GeneralAssetMaster
    LEFT JOIN AssetType AT_AM ON AM.FK_AssetType = AT_AM.PK_AssetType
    LEFT JOIN AssetType AT_GAM ON GAM.FK_AssetType = AT_GAM.PK_AssetType
    WHERE ax.FK_TransferAssets = ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $outboundId);
$stmt->execute();
$result2 = $stmt->get_result();

$id = $outboundId;

// Fetch outbound asset
$stmt = $conn->prepare("SELECT * FROM TransferAssets WHERE PK_TransferAssets = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$outbound = $result->fetch_assoc();

$approvals = json_decode($outbound['Approvals'], true);

// Extract IDs
$requestedById = $approvals['requested'] ?? null;
$approvedById = $approvals['approved'] ?? null;
$itDeptId = $approvals['itdept'] ?? null;

// Function to get employee name by ID
function getEmployeeName($conn, $empId)
{
    if (!$empId) return '';
    $stmt = $conn->prepare("SELECT Name FROM Employees WHERE PK_Employees = ?");
    $stmt->bind_param("i", $empId);
    $stmt->execute();
    $result = $stmt->get_result();
    $emp = $result->fetch_assoc();
    return $emp['Name'] ?? '';
}

$fkUserId = $outbound['FK_Users'] ?? '';

$requestedByName = getEmployeeName($conn, $requestedById);
$approvedByName = getEmployeeName($conn, $approvedById);
$itDeptName = getEmployeeName($conn, $itDeptId);
$employeeName = getEmployeeName($conn, $fkUserId);

$description = $outbound['Descriptions'] ?? '';
$imagePath = $outbound['Image'] ?? '';
$dateAcquired = $outbound['DateAcquired'] ?? '';
$departureDate = $outbound['DepartureDate'] ?? '';
$expectedReceiver = $outbound['ExpectedReceiver'] ?? '';
$expectedReceiver = $outbound['ExpectedReceiver'] ?? '';

$imagePathReceived = $outbound['ReceivedImage'] ?? '';
$receivedBy = $outbound['ReceivedBy'] ?? '';

$receivedImage = $outbound['ReceivedImage'] ?? '';
$receivedRemarks = $outbound['ReceivedRemarks'] ?? '';
$receivedBy = $outbound['ReceivedBy'] ?? '';
$dateReceived = $outbound['DateReceived'] ?? '';


$status = $outbound['Status'] ?? '';
$Status = $status;
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Request</title>
    <link rel="icon" href="inventory.png" type="image/x-icon">
    <?php include "tools/head-plugin.php" ?>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet" />
    <style>
        .form-card {
            border-radius: 0.75rem;
            border: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .form-label {
            font-weight: 500;
            color: #495057;
        }

        .form-control:focus,
        .form-select:focus {
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.2);
            border-color: #86b7fe;
        }

        /* Custom File Input */
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

        /* Image Preview */
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
            position: relative;
        }

        .image-preview {
            max-width: 100%;
            max-height: 100%;
            object-fit: cover;
        }

        .image-placeholder {
            color: #6c757d;
            text-align: center;
            display: none;
        }

        .section-divider {
            margin-top: 1.5rem;
            margin-bottom: 1.5rem;
            border-top: 1px solid #dee2e6;
        }

        .btn-clear {
            border-left: 1px solid #dee2e6;
        }


        /* Selected Items Table */
        #selectedProductsTable thead th {
            background-color: #f8f9fa;
        }

        #selectedProductsTable .form-control {
            max-width: 80px;
        }

        #selectedProductsTable tbody tr:hover {
            background-color: #f1f3f5;
        }

        #no-items-row td {
            text-align: center;
            padding: 2rem;
            color: #6c757d;
        }



        .details-card {
            border-radius: 0.75rem;
            overflow: hidden;
            border: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .details-card .card-header {
            background-color: #d1e7dd;
            /* Light green for success/received */
            color: #0a3622;
            border-bottom: 1px solid #a3cfbb;
            font-weight: 600;
        }

        .view-label {
            font-weight: 500;
            color: #6c757d;
            font-size: 0.875rem;
            margin-bottom: 0.25rem;
        }

        .view-data {
            font-weight: 500;
            color: #343a40;
            background-color: #f8f9fa;
            padding: 0.5rem 0.75rem;
            border-radius: 0.375rem;
            min-height: 42px;
            display: flex;
            align-items: center;
            border: 1px solid #dee2e6;
            word-break: break-word;
        }

        .view-data.remarks {
            min-height: 80px;
            align-items: flex-start;
        }

        .image-display-container {
            width: 100%;
            height: auto;
            min-height: 200px;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f8f9fa;
            overflow: hidden;
        }

        .receipt-image {
            max-width: 100%;
            height: auto;
            display: block;
        }
    </style>

</head>

<body>
    <?php include 'header.php'; ?>
    <div class="container">
        <h2 class="mb-4">View Request</h2>

        <?php include 'modal.php'; ?>

        <div class="d-flex justify-content-start mb-3">
            <?php if (!$inbound) { ?>
                <a href="transfer-assets.php?Status=<?php echo $status ?>" class="btn btn-dark"><i class="bi bi-arrow-left-circle me-2"></i>Back</a>
            <?php } else { ?>
                <a href="transferred-assets.php?Status=<?php echo $status ?>" class="btn btn-dark"><i class="bi bi-arrow-left-circle me-2"></i>Back</a>
            <?php } ?>

        </div> <br>

        <?php include "tools/alert-message.php"; ?>

        <!-- NEWLY ADDED ITEM SELECTION SECTION -->
        <div class="card form-card">
            <div class="card-header bg-light border-bottom">
                <h5 class="card-title mb-0 fw-bold"><i class="bi bi-box-arrow-up me-2"></i>Transfer Request Form</h5>
            </div>
            <div class="card-body p-4">

                <h6 class="mt-4"><i class="bi bi-list-check me-2 text-primary"></i>Requested Items</h6>
                <hr class="mt-2">


                <div class="mb-3" <?php
                                    if ($isApproved) {
                                    ?>
                    style="display: none;"
                    <?php
                                    }
                    ?>>
                    <label for="selectedProduct" class="form-label">Click the button to add items to the list below.</label>
                    <div class="input-group">
                        <input type="text" class="form-control bg-light" id="selectedProduct" placeholder="No items selected..." readonly>
                        <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#productSelectModal">
                            <i class="bi bi-plus-circle-fill me-1"></i> Select Items
                        </button>
                    </div>
                </div>


                <table class="table mt-3" id="selectedProductsTable">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>ID</th>
                            <th>Name</th>

                            <?php if ($Status != "Returned" && $Status != "Rejected" && $Status != "Approved" && $Status == "Pending") { ?>
                                <th>Current Stock</th>
                            <?php } ?>
                            <th>Quantity</th>
                            <th>Asset Type</th>


                            <?php
                            if (!$isApproved) {
                            ?>
                                <th>Action</th>
                            <?php
                            }
                            ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result2 && $result2->num_rows > 0): ?>
                            <?php while ($row = $result2->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['Type']) ?></td>
                                    <td><?= htmlspecialchars($row['ID']) ?></td>
                                    <td><?= htmlspecialchars($row['Name']) ?></td>
                                    <?php if ($Status != "Returned" && $Status != "Rejected" && $Status != "Approved" && $Status == "Pending") { ?>
                                        <td><?= htmlspecialchars($row['CurrentStock']) ?></td>
                                    <?php } ?>
                                    <td>
                                        <input
                                            type="number"
                                            class="form-control quantity-input"
                                            value="<?= htmlspecialchars($row['Quantity']) ?>"
                                            style="width: 80px; margin-top: 5px;"
                                            disabled>

                                        </input>



                                    </td>
                                    <td><?= htmlspecialchars($row['AssetType']) ?></td>

                                    <?php
                                    if (!$isApproved) {
                                    ?>

                                        <td>
                                            <button type="button" class="btn btn-danger btn-sm remove-row-exist"
                                                data-type="<?= htmlspecialchars($row['Type']) ?>"
                                                data-id="<?= htmlspecialchars($row['ID']) ?>">
                                                Remove</button>
                                        </td>

                                    <?php
                                    }
                                    ?>
                                </tr>
                            <?php endwhile; ?>

                        <?php endif; ?>
                    </tbody>
                </table>
                <div class="section-divider"></div>

                <form method="POST" enctype="multipart/form-data" id="myForm" class="needs-validation" novalidate>

                    <input type="hidden" name="tableValue" id="tableValue" required>

                    <div class="row g-4">
                        <div class="col-lg-6">
                            <h6><i class="bi bi-info-circle-fill me-2 text-primary"></i>Request Details</h6>
                            <hr class="mt-2">

                            <div class="mb-3">
                                <label for="dateRequested" class="form-label">Date Requested</label>
                                <input type="date" name="DateRequested" id="dateRequested" class="form-control" value="<?php echo $dateAcquired; ?>" required
                                    <?php
                                    if ($isApproved) { ?>
                                    disabled
                                    <?php
                                    }
                                    ?>>
                                <div class="invalid-feedback">Please select a date.</div>
                            </div>

                            <?php
                            if ($isApproved) { ?>
                                <div class="mb-3">
                                    <label for="dateRequested" class="form-label">Departure Date</label>
                                    <input type="date" name="DateRequested" id="dateRequested" class="form-control" value="<?php echo $departureDate; ?>" required disabled>
                                    <div class="invalid-feedback">Please select a date.</div>
                                </div>
                            <?php
                            } else {
                            ?>

                                <div class="row g-3">
                                    <div class="col-md-8">
                                        <label for="departureDate" class="form-label">Departure Date</label>
                                        <input type="date" name="departureDate" id="departureDate" class="form-control" min="<?php echo $minDate; ?>" value="<?php echo $departureDate; ?>" required>
                                        <div class="invalid-feedback">Please select a departure date.</div>
                                    </div>
                                    <div class="col-md-4 d-flex align-items-end">
                                        <div class="form-check form-switch mb-1">
                                            <input class="form-check-input" type="checkbox" role="switch" id="sameDayCheckbox">
                                            <label class="form-check-label" for="sameDayCheckbox">Same Day</label>
                                        </div>
                                    </div>
                                </div>
                            <?php
                            }
                            ?>


                            <div class="mb-3">
                                <label for="receiveBy" class="form-label">Expected Recipient</label>
                                <input type="text" class="form-control" id="receiveBy" name="receiveBy" placeholder="Enter full name of the recipient" value="<?= htmlspecialchars($expectedReceiver) ?>" required
                                    <?php
                                    if ($isApproved) { ?>
                                    disabled
                                    <?php
                                    }
                                    ?>>
                                <div class="invalid-feedback">Please provide the recipient's name.</div>
                            </div>


                            <div class="mb-3">
                                <label for="description" class="form-label">Purpose / Description</label>
                                <textarea name="Description" id="description" class="form-control" rows="4" placeholder="Describe the purpose of this outbound request..." required
                                    <?php
                                    if ($isApproved) { ?>
                                    disabled
                                    <?php
                                    }
                                    ?>><?php echo $description ?>
                                </textarea>
                                <div class="invalid-feedback">Please provide a description.</div>
                            </div>


                        </div>

                        <!-- Right Column: Signatories & Image -->
                        <div class="col-lg-6">
                            <h6><i class="bi bi-pen-fill me-2 text-primary"></i>Signatories</h6>
                            <hr class="mt-2">


                            <div class="mb-3">
                                <label for="employeeName" class="form-label">Prepared By</label>
                                <div class="input-group">
                                    <input type="hidden" name="Employee" id="employeeId" value="<?= htmlspecialchars($fkUserId) ?>" required>
                                    <input type="text" class="form-control bg-light" id="employeeName" value="<?= htmlspecialchars($employeeName) ?>" readonly required placeholder="Select employee...">
                                    <?php
                                    if (!$isApproved) { ?>

                                        <button type="button" class="btn btn-outline-secondary select-employee-open-btn" data-bs-toggle="modal" data-bs-target="#employeeSelectModalOutbound" data-target-input="employee"><i class="bi bi-search"></i></button>
                                        <button type="button" class="btn btn-outline-secondary clear-employee-btn btn-clear" data-target-input="employee" title="Remove Selection"><i class="bi bi-x-lg"></i></button>
                                    <?php
                                    }
                                    ?>
                                </div>
                                <div class="invalid-feedback d-block" id="employeeName-feedback"></div>
                            </div>


                            <div class="mb-3">
                                <label for="requestedByName" class="form-label">Requested By</label>
                                <div class="input-group">
                                    <input type="hidden" name="RequestedBy" id="requestedById" value="<?= htmlspecialchars($requestedById) ?>" required>
                                    <input type="text" class="form-control bg-light" id="requestedByName" value="<?= htmlspecialchars($requestedByName) ?>" readonly required placeholder="Select employee...">
                                    <?php
                                    if (!$isApproved) { ?>
                                        <button type="button" class="btn btn-outline-secondary select-employee-open-btn" data-bs-toggle="modal" data-bs-target="#employeeSelectModalOutbound" data-target-input="requestedBy"><i class="bi bi-search"></i></button>
                                        <button type="button" class="btn btn-outline-secondary clear-employee-btn btn-clear" data-target-input="requestedBy" title="Remove Selection"><i class="bi bi-x-lg"></i></button>
                                    <?php
                                    }
                                    ?>
                                </div>
                                <div class="invalid-feedback d-block" id="requestedByName-feedback"></div>
                            </div>

                            <div class="mb-3">
                                <label for="approvedByName" class="form-label">Approved By (Supervisor/Manager)</label>
                                <div class="input-group">
                                    <input type="hidden" name="ApprovedBy" id="approvedById" value="<?= htmlspecialchars($approvedById) ?>" required>
                                    <input type="text" class="form-control bg-light" id="approvedByName" value="<?= htmlspecialchars($approvedByName) ?>" readonly required placeholder="Select employee...">
                                    <?php
                                    if (!$isApproved) { ?>
                                        <button type="button" class="btn btn-outline-secondary select-employee-open-btn" data-bs-toggle="modal" data-bs-target="#employeeSelectModalOutbound" data-target-input="approvedBy"><i class="bi bi-search"></i></button>
                                        <button type="button" class="btn btn-outline-secondary clear-employee-btn btn-clear" data-target-input="approvedBy" title="Remove Selection"><i class="bi bi-x-lg"></i></button>
                                    <?php
                                    }
                                    ?>
                                </div>
                                <div class="invalid-feedback d-block" id="approvedByName-feedback"></div>
                            </div>


                            <div class="mb-3">
                                <label for="itDeptName" class="form-label">I.T. Department (if needed)</label>
                                <div class="input-group">
                                    <input type="hidden" name="ITDepartment" id="itDeptId" value="<?= htmlspecialchars($itDeptId) ?>">
                                    <input type="text" class="form-control" id="itDeptName" value="<?= htmlspecialchars($itDeptName) ?>" readonly required placeholder="Select employee...">
                                    <?php
                                    if (!$isApproved) { ?>
                                        <button type="button" class="btn btn-outline-secondary select-employee-open-btn" data-bs-toggle="modal" data-bs-target="#employeeSelectModalOutbound" data-target-input="itDept"><i class="bi bi-search"></i></button>
                                        <button type="button" class="btn btn-outline-secondary clear-employee-btn btn-clear" data-target-input="itDept" title="Remove Selection"><i class="bi bi-x-lg"></i></button>
                                    <?php
                                    }
                                    ?>
                                </div>
                            </div>


                            <div class="mb-3">
                                <label for="image" class="form-label"><?php
                                                                        if (!$isApproved) { ?>Attach <?php
                                                                                                    }
                                                                                                        ?>Transfer Image</label>
                                <div id="imagePreviewContainer" class="image-preview-container">
                                    <img id="imagePreview" src="<?= htmlspecialchars($imagePath) ?>" alt="Image Preview" class="image-preview" />
                                    <div id="imagePlaceholder" class="image-placeholder">
                                        <i class="bi bi-file-earmark-image fs-1"></i>
                                        <p class="mb-0">Image Preview</p>
                                    </div>
                                </div>
                                <div class="file-upload-wrapper mt-2">
                                    <?php
                                    if (!$isApproved) { ?>
                                        <label class="file-upload-label" for="imageInput">
                                            <i class="bi bi-upload me-2"></i> <span>Choose Image</span>
                                        </label>
                                    <?php
                                    }
                                    ?>
                                    <input type="file" name="Image" id="imageInputUpload" class="form-control" accept="image/*">
                                </div>
                                <div class="invalid-feedback">Please attach an image.</div>
                            </div>
                        </div>

                        <div class="section-divider"></div>

                        <?php
                        if (!$isApproved) { ?>
                            <div class="text-end">
                                <button type="button" class="btn btn-secondary me-2" onclick="window.location.href='transfer-assets.php'">
                                    <i class="bi bi-x-circle me-1"></i>Cancel
                                </button>
                                <button type="submit" class="btn btn-primary" name="submitRequest">
                                    <i class="bi bi-check-circle-fill me-1"></i>Submit Request
                                </button>
                            </div>
                        <?php
                        }
                        ?>

                </form>

                <?php if ($status == "Received") { ?>

                    <div id="printSection">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-check-circle-fill me-2"></i>Transfer Completed</span>
                            <span class="badge bg-success rounded-pill">Received</span>
                        </div>
                        <div class="card-body p-4">
                            <div class="row g-4">
                                <!-- Left Column: Image -->
                                <div class="col-lg-5">
                                    <div class="mb-3">
                                        <label class="view-label">Proof of Receipt</label>
                                        <div class="image-display-container">
                                            <!-- Demonstration Image -->
                                            <img id="receiptImage" src="image/transferred_images/<?php echo $receivedImage ?>" alt="Proof of Receipt" class="receipt-image" />
                                        </div>
                                    </div>
                                </div>
                                <!-- Right Column: Details -->
                                <div class="col-lg-7">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="view-label">Transfer ID</label>
                                            <p id="displayTransferId" class="view-data">REQ-<?php echo $id ?></p>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="view-label">Date Received</label>
                                            <p id="displayReceivedDate" class="view-data"><?php echo $dateReceived ?></p>
                                        </div>

                                        <div class="col-12">
                                            <label class="view-label">Received By</label>
                                            <p id="displayReceivedBy" class="view-data"><?php echo $receivedBy ?></p>
                                        </div>
                                        <div class="col-12">
                                            <label class="view-label">Remarks</label>
                                            <p id="displayRemarks" class="view-data remarks"><?php echo $receivedRemarks ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                    </div>
                <?php } ?>

            </div>
        </div>
    </div>



    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="script/stopper.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

    <!-- Clear Employee -->
    <script>
        // --- Bootstrap Form Validation ---
        const form = document.getElementById('myForm');
        form.addEventListener('submit', function(event) {
            // Custom validation for readonly employee fields
            ['employeeName', 'requestedByName', 'approvedByName'].forEach(id => {
                const input = document.getElementById(id);
                const feedback = document.getElementById(`${id}-feedback`);
                if (!input.value) {
                    feedback.textContent = 'Please select an employee.';
                    event.preventDefault();
                    event.stopPropagation();
                } else {
                    feedback.textContent = '';
                }
            });

            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }

            form.classList.add('was-validated');
        }, false);

        // --- Clear Employee Buttons ---
        document.querySelectorAll('.clear-employee-btn').forEach(button => {
            button.addEventListener('click', function() {
                const target = this.dataset.targetInput;
                document.getElementById(`${target}Name`).value = '';
                document.getElementById(`${target}Id`).value = '';
                // Clear validation message if any
                const feedback = document.getElementById(`${target}Name-feedback`);
                if (feedback) feedback.textContent = '';
            });
        });
    </script>

    <script>
        (() => {
            'use strict'

            const forms = document.querySelectorAll('.needs-validation')

            Array.from(forms).forEach(form => {
                form.addEventListener('submit', event => {
                    // Get hidden inputs and their corresponding text inputs
                    const fields = [{
                            hidden: form.querySelector('input[name="Employee"]'),
                            text: form.querySelector('#employeeName')
                        },
                        {
                            hidden: form.querySelector('input[name="RequestedBy"]'),
                            text: form.querySelector('#requestedByName')
                        },
                        {
                            hidden: form.querySelector('input[name="ApprovedBy"]'),
                            text: form.querySelector('#approvedByName')
                        }
                    ];

                    let allValid = true;

                    fields.forEach(({
                        hidden,
                        text
                    }) => {
                        if (!hidden.value) {
                            allValid = false;
                            text.classList.add('is-invalid');
                        } else {
                            text.classList.remove('is-invalid');
                        }
                    });

                    if (!allValid) {
                        event.preventDefault();
                        event.stopPropagation();
                        // Optionally, focus on the first invalid input
                        const firstInvalid = fields.find(f => !f.hidden.value);
                        if (firstInvalid) firstInvalid.text.focus();
                    }

                    // Continue with Bootstrap validation
                    if (!form.checkValidity() || !allValid) {
                        event.preventDefault();
                        event.stopPropagation();
                    }

                    form.classList.add('was-validated');
                }, false);
            });
        })();
    </script>

    <!--- Select Employee -->
    <script>
        let currentTargetInput = null;

        document.querySelectorAll('.select-employee-open-btn').forEach(button => {
            button.addEventListener('click', () => {
                currentTargetInput = button.getAttribute('data-target-input');
            });
        });

        document.querySelectorAll('.select-employee-btn').forEach(button => {
            button.addEventListener('click', () => {
                if (!currentTargetInput) return;

                const employeeId = button.getAttribute('data-employee-id');
                const employeeName = button.getAttribute('data-employee-name');

                const hiddenInput = document.getElementById(currentTargetInput + 'Id');
                const textInput = document.getElementById(currentTargetInput + 'Name');

                if (hiddenInput && textInput) {
                    hiddenInput.value = employeeId;
                    textInput.value = employeeName;
                }


                currentTargetInput = null;
            });
        });
    </script>

    <!--- Select Product -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {

            // Delegate on modal body or document (if modal body is also dynamic)
            const modalBody = document.querySelector('#productSelectModal .modal-body');

            (modalBody || document).addEventListener('click', function(e) {
                const btn = e.target.closest('.select-product-btn');
                if (!btn) return; // Not a button click

                // Extract data attributes
                const type = btn.dataset.type;
                const id = btn.dataset.id;
                const assetname = btn.dataset.assetname;
                const name = btn.dataset.name;
                const currentStock = btn.dataset.qty;

                const uniqueKey = `${type}-${id}`;

                // Fill input box with selected item
                const selectedProductInput = document.getElementById('selectedProduct');
                if (selectedProductInput) {
                    selectedProductInput.value = `${uniqueKey} (${name})`;
                }

                // Insert into selected products table if not duplicate
                const displayTable = document.getElementById('selectedProductsTable');
                if (displayTable) {
                    let isDuplicate = false;
                    Array.from(displayTable.rows).forEach(row => {
                        const existingType = row.cells[0]?.textContent;
                        const existingId = row.cells[1]?.textContent;
                        if (`${existingType}-${existingId}` === uniqueKey) {
                            isDuplicate = true;
                        }
                    });

                    if (!isDuplicate) {
                        const row = displayTable.insertRow();
                        row.innerHTML = `
          <td>${type}</td>
          <td>${id}</td>
          <td>${name}</td>
          <td>${currentStock}</td>
          <td>
            <input 
              type="number" 
              class="form-control quantity-input" 
              min="1" 
              max="${currentStock}" 
              value="1" 
              style="width: 80px; margin-top: 5px;" 
              title="Enter quantity (max ${currentStock})"
            />
            <div class="invalid-feedback">Quantity cannot be below or exceed stock (${currentStock}).</div>
          </td>
          <td>${assetname}</td>
          <td><button type="button" class="btn btn-danger btn-sm remove-row">Remove</button></td>
        `;

                        const qtyInput = row.querySelector('.quantity-input');
                        qtyInput.addEventListener('input', () => {
                            let val = parseInt(qtyInput.value, 10);

                            if (isNaN(val) || val < 1) {
                                qtyInput.classList.add('is-invalid');
                            } else if (val > parseInt(currentStock, 10)) {
                                qtyInput.value = currentStock;
                                qtyInput.classList.remove('is-invalid');
                            } else {
                                qtyInput.classList.remove('is-invalid');
                            }
                        });
                    }
                }

                // Hide the modal after selection
                const modalEl = document.getElementById('productSelectModal');
                if (modalEl) {
                    const modal = bootstrap.Modal.getInstance(modalEl);
                    if (modal) modal.hide();
                }
            });

            // Remove button delegation
            const selectedProductsTable = document.getElementById('selectedProductsTable');
            if (selectedProductsTable) {
                selectedProductsTable.addEventListener('click', function(e) {
                    if (e.target.classList.contains('remove-row')) {
                        const row = e.target.closest('tr');
                        if (row) row.remove();
                    }
                });
            }

        });
    </script>

    <!--- Form Submission Validation -->
    <script>
        function populateTableValue() {
            const table = document.getElementById('selectedProductsTable');
            const rows = Array.from(table.rows).slice(1); // skip header

            const products = rows.map(row => {
                return {
                    type: row.cells[0].textContent.trim(),
                    id: row.cells[1].textContent.trim(),
                    name: row.cells[2].textContent.trim(),
                    currentStock: row.cells[3].textContent.trim(),
                    quantity: row.querySelector('input.quantity-input')?.value || '0',
                    assetname: row.cells[5].textContent.trim()
                };
            });

            document.getElementById('tableValue').value = JSON.stringify(products);
            return products;
        }

        document.getElementById('myForm').addEventListener('submit', (e) => {
            const products = populateTableValue();

            if (products.length === 0) {
                e.preventDefault();
                toastr.error('Please select at least one product.');
                return;
            }

            const hasValidQuantity = products.some(p => p.quantity !== '0' && p.quantity !== '');
            if (!hasValidQuantity) {
                e.preventDefault();
                toastr.error('Please enter a valid quantity for items.');
                return;
            }
        });

        // Optional: if you want to refresh tableValue when button is clicked (not just on submit)
        document.querySelector('button[name="submitRequest"]').addEventListener('click', populateTableValue);
    </script>

    <!--- Remove Row From Database-->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.remove-row-exist').forEach(button => {
                button.addEventListener('click', function() {
                    // Simply remove the row from the table
                    const row = this.closest('tr');
                    if (row) row.remove();
                });
            });
        });
    </script>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>



    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const imageInput = document.getElementById('imageInputUpload');
            const imagePreview = document.getElementById('imagePreview');
            const imagePlaceholder = document.getElementById('imagePlaceholder');

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
        });
    </script>

    <script>
        document.querySelector('[data-bs-target="#productSelectModal"]').addEventListener('click', function() {

            const table = document.getElementById('selectedProductsTable');
            const rows = table.querySelectorAll('tr'); // includes all rows (thead + inserted)
            const data = [];

            rows.forEach((row, index) => {
                if (index === 0) return; // Skip the <thead> row

                const cells = row.querySelectorAll('td');
                if (cells.length >= 2) {
                    const type = cells[0].textContent.trim();
                    const id = cells[1].textContent.trim();
                    data.push({
                        type,
                        id
                    });
                }
            });

            const json = JSON.stringify(data);

            const encodedJson = encodeURIComponent(json);

            fetch(`tools/load-items.php?data=${encodedJson}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok: ' + response.statusText);
                    }
                    return response.text();
                })
                .then(html => {
                    document.getElementById('productModalContent').innerHTML = html;

                    $(document).ready(function() {
                        $('#productTable').DataTable({
                            dom: 'lftip',
                            pagingType: 'simple',
                            language: {
                                lengthMenu: "Show _MENU_ entries",
                                search: "",
                                searchPlaceholder: "Search..."
                            }
                        });
                    });
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    document.getElementById('productModalContent').innerHTML = '<div class="alert alert-danger">Failed to load products. Please try again later.</div>';
                });
        });
    </script>


</body>

<?php $conn->close(); ?>

</html>