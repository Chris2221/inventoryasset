<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include "config.php";
include "tools/sender.php";

$createdBy = $_SESSION['user_id'];

function getUpdatedApproverJsonWithStatus($allGAM, $isCurrentDate, $conn)
{
    $settingType = (int)($allGAM ? 2 : 1);

    $sql = "SELECT SettingValue FROM Settings WHERE SettingType = $settingType  LIMIT 1";
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

        // If current date is the departure date
        if ($isCurrentDate) {

            // 2. Load from DB
            $sql = "SELECT SettingValue FROM Settings WHERE SettingType = 3 LIMIT 1";
            $result = $conn->query($sql);
            $extraSteps = [];

            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $extraSteps = json_decode($row['SettingValue'], true);

                // Add status = 0 to each
                foreach ($extraSteps as &$step) {
                    $step['status'] = 0;
                }
            }

            // 3. Merge all steps
            $allSteps = array_merge($approverSteps, $extraSteps);

            // 4. Reassign step numbers
            foreach ($allSteps as $index => &$step) {
                $step['step'] = $index + 1;
            }

            // 5. Output final JSON
            return json_encode($allSteps, JSON_PRETTY_PRINT);
        } else {
            return json_encode($approverSteps);
        }
    }

    return null; // No setting found
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submitRequest'])) {
    $description = $_POST['Description'];
    $dateAcquired = $_POST['DateRequested'];
    $expectedReturn = $_POST['expectedReturn'];
    $departureDate = $_POST['departureDate'];
    $receiveBy = $_POST['receiveBy'];

    $fkUser = $_POST['Employee']; // FK_Users
    $requestedBy = $_POST['RequestedBy'];
    $approvedBy = $_POST['ApprovedBy'];
    $itDept = $_POST['ITDepartment'];

    $approvals = json_encode([
        'requested' => (int)$requestedBy,
        'approved' => (int)$approvedBy,
        'itdept'   => (int)$itDept
    ]);

    $status = 'Pending';

    $isCurrentDate = false;
    $currentDate = date('Y-m-d');

    if ($currentDate === date('Y-m-d', strtotime($departureDate))) {
        $isCurrentDate = true;
    }

    $tableValueData = $_POST['tableValue'] ?? '[]';
    $valuedata = json_decode($tableValueData, true);

    $allGAM = true;

    foreach ($valuedata as $item) {
        if ($item['type'] !== 'GAM') {
            $allGAM = false;
            break;
        }
    }

    $updatedApprovers = getUpdatedApproverJsonWithStatus($allGAM, $isCurrentDate, $conn);

    // Handle Image Upload
    $imagePath = '';
    if (isset($_FILES['Image']) && $_FILES['Image']['error'] === UPLOAD_ERR_OK) {
        $imageTmp = $_FILES['Image']['tmp_name'];
        $imageName = basename($_FILES['Image']['name']);
        $imageDir = 'image/outbound_images/';
        $targetPath = $imageDir . uniqid() . '_' . $imageName;

        if (!is_dir($imageDir)) {
            mkdir($imageDir, 0755, true);
        }

        if (move_uploaded_file($imageTmp, $targetPath)) {
            $imagePath = $targetPath;
        }
    }

    // Insert into DB
    $stmt = $conn->prepare("INSERT INTO OutboundAssets (Descriptions, Image, DateAcquired, FK_Users, Approvals, Status, CreatedBy, ExpectedReturnDate, DepartureDate, ExpectedReceiver, Approvers) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssississss", $description, $imagePath, $dateAcquired, $fkUser, $approvals, $status, $createdB, $expectedReturn, $departureDate, $receiveBy, $updatedApprovers);

    if ($stmt->execute()) {
        $lastId = $conn->insert_id;
        $tableValue = $_POST['tableValue'] ?? '[]';
        $data = json_decode($tableValue, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            die("Invalid JSON data.");
        }

        if (!empty($data)) {
            // Prepare the insert statement once
            $stmt = $conn->prepare("INSERT INTO OutboundAssetsList (FK_OutboundAssets, FK_AssetMaster, FK_GeneralAssetMaster, Quantity) VALUES (?, ?, ?, ?)");

            if (!$stmt) {
                die("Prepare failed: " . $conn->error);
            }

            foreach ($data as $item) {
                $fkAssetMaster = null;
                $fkGeneralAssetMaster = null;
                $quantity = (int)$item['quantity'];

                if ($item['type'] === 'IT') {
                    $fkAssetMaster = (int)$item['id'];
                } elseif ($item['type'] === 'GAM') {
                    $fkGeneralAssetMaster = (int)$item['id'];
                }

                $fkAssetMasterParam = $fkAssetMaster ?: null;
                $fkGeneralAssetMasterParam = $fkGeneralAssetMaster ?: null;

                $fkAssetMasterParam = $fkAssetMasterParam ?? 0;
                $fkGeneralAssetMasterParam = $fkGeneralAssetMasterParam ?? 0;

                $stmt->bind_param("iiii", $lastId, $fkAssetMasterParam, $fkGeneralAssetMasterParam, $quantity);

                if (!$stmt->execute()) {
                    echo "Insert failed for item with id {$item['id']}: " . $stmt->error;
                }
            }

            $stmt->close();
        }

        OutboundApproval($lastId, $conn);

        $logDetails = "Created Outbound Request ID: $lastId";

        $currentUser = $_SESSION['user_id'];
        $actionUser = "Outbound Request";

        logActivity($conn, $currentUser, $actionUser, $logDetails);

        header("Location: outbound-assets.php?status=requestadded");
        exit();
    } else {
        echo "Error: " . $stmt->error;
    }
}

$settingValue = 3; // default
$settingType = 4;

$stmt = $conn->prepare("SELECT SettingValue FROM Settings WHERE SettingType = ? LIMIT 1");
$stmt->bind_param("i", $settingType);
$stmt->execute();
$stmt->bind_result($settingValue);
$stmt->fetch();
$stmt->close();

// Calculate min selectable date
$minDate = date('Y-m-d', strtotime("+$settingValue days"));
$today = date('Y-m-d');
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Request</title>
    <link rel="icon" href="inventory.png" type="image/x-icon">
    <?php include "tools/head-plugin.php" ?>
    <link rel="stylesheet" href="css/loading.css">
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
            display: none;
            max-width: 100%;
            max-height: 100%;
            object-fit: cover;
        }

        .image-placeholder {
            color: #6c757d;
            text-align: center;
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
    </style>
</head>

<body>
    <?php include 'header.php'; ?>
    <div class="container">
        <h2 class="mb-4">Create Request</h2>

        <?php include 'modal.php'; ?>

        <div class="d-flex justify-content-start mb-3">
            <a href="outbound-assets.php" class="btn btn-dark add-btn">
                <i class="bi bi-arrow-left-circle me-2"></i> Back
            </a>
        </div>
        <br>

        <?php include "tools/alert-message.php"; ?>

        <div class="card form-card">
            <div class="card-header bg-light border-bottom">
                <h5 class="card-title mb-0 fw-bold"><i class="bi bi-box-arrow-up me-2"></i>Outbound Request Form</h5>
            </div>
            <div class="card-body p-4">

                <!-- NEWLY ADDED ITEM SELECTION SECTION -->
                <h6 class="mt-4"><i class="bi bi-list-check me-2 text-primary"></i>Requested Items</h6>
                <hr class="mt-2">
                <div class="mb-3">
                    <label for="selectedProduct" class="form-label">Click the button to add items to the list below.</label>
                    <div class="input-group">
                        <input type="text" class="form-control bg-light" id="selectedProduct" placeholder="No items selected..." readonly>
                        <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#productSelectModal">
                            <i class="bi bi-plus-circle-fill me-1"></i> Select Items
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle" id="selectedProductsTable">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Type</th>
                                <th scope="col">Asset/Item ID</th>
                                <th scope="col">Name</th>
                                <th scope="col" class="text-center">Stock</th>
                                <th scope="col" style="width: 120px;">Quantity</th>
                                <th scope="col">Type</th>
                                <th scope="col" class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>

                        </tbody>
                    </table>
                </div>
                <!-- END OF ITEM SELECTION SECTION -->


                <div class="section-divider"></div>

                <form method="POST" enctype="multipart/form-data" id="myForm" class="needs-validation" novalidate>
                    <input type="hidden" name="tableValue" id="tableValue" required>
                    <div class="row g-4">
                        <!-- Left Column: Request Details -->
                        <div class="col-lg-6">
                            <h6><i class="bi bi-info-circle-fill me-2 text-primary"></i>Request Details</h6>
                            <hr class="mt-2">

                            <div class="mb-3">
                                <label for="dateRequested" class="form-label">Date Requested</label>
                                <input type="date" name="DateRequested" id="dateRequested" class="form-control" required>
                                <div class="invalid-feedback">Please select a date.</div>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label for="departureDate" class="form-label">Departure Date</label>
                                    <input type="date" name="departureDate" id="departureDate" class="form-control" min="<?php echo $minDate; ?>" required>
                                    <div class="invalid-feedback">Please select a departure date.</div>
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <div class="form-check form-switch mb-1">
                                        <input class="form-check-input" type="checkbox" role="switch" id="sameDayCheckbox">
                                        <label class="form-check-label" for="sameDayCheckbox">Same Day</label>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3 mt-3">
                                <label for="expectedReturn" class="form-label">Expected Return Date</label>
                                <input type="date" name="expectedReturn" id="expectedReturn" class="form-control" required>
                                <div class="invalid-feedback">Please select an expected return date.</div>
                            </div>

                            <div class="mb-3">
                                <label for="receiveBy" class="form-label">Expected Recipient</label>
                                <input type="text" class="form-control" id="receiveBy" name="receiveBy" placeholder="Enter full name of the recipient" required>
                                <div class="invalid-feedback">Please provide the recipient's name.</div>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Purpose / Description</label>
                                <textarea name="Description" id="description" class="form-control" rows="4" placeholder="Describe the purpose of this outbound request..." required></textarea>
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
                                    <input type="hidden" name="Employee" id="employeeId" required>
                                    <input type="text" class="form-control bg-light" id="employeeName" readonly required placeholder="Select employee...">
                                    <button type="button" class="btn btn-outline-secondary select-employee-open-btn" data-bs-toggle="modal" data-bs-target="#employeeSelectModalOutbound" data-target-input="employee"><i class="bi bi-search"></i></button>
                                    <button type="button" class="btn btn-outline-secondary clear-employee-btn btn-clear" data-target-input="employee" title="Remove Selection"><i class="bi bi-x-lg"></i></button>
                                </div>
                                <div class="invalid-feedback d-block" id="employeeName-feedback"></div>
                            </div>

                            <div class="mb-3">
                                <label for="requestedByName" class="form-label">Requested By</label>
                                <div class="input-group">
                                    <input type="hidden" name="RequestedBy" id="requestedById" required>
                                    <input type="text" class="form-control bg-light" id="requestedByName" readonly required placeholder="Select employee...">
                                    <button type="button" class="btn btn-outline-secondary select-employee-open-btn" data-bs-toggle="modal" data-bs-target="#employeeSelectModalOutbound" data-target-input="requestedBy"><i class="bi bi-search"></i></button>
                                    <button type="button" class="btn btn-outline-secondary clear-employee-btn btn-clear" data-target-input="requestedBy" title="Remove Selection"><i class="bi bi-x-lg"></i></button>
                                </div>
                                <div class="invalid-feedback d-block" id="requestedByName-feedback"></div>
                            </div>
                            <div class="mb-3">
                                <label for="approvedByName" class="form-label">Approved By (Supervisor/Manager)</label>
                                <div class="input-group">
                                    <input type="hidden" name="ApprovedBy" id="approvedById" required>
                                    <input type="text" class="form-control bg-light" id="approvedByName" readonly required placeholder="Select employee...">
                                    <button type="button" class="btn btn-outline-secondary select-employee-open-btn" data-bs-toggle="modal" data-bs-target="#employeeSelectModalOutbound" data-target-input="approvedBy"><i class="bi bi-search"></i></button>
                                    <button type="button" class="btn btn-outline-secondary clear-employee-btn btn-clear" data-target-input="approvedBy" title="Remove Selection"><i class="bi bi-x-lg"></i></button>
                                </div>
                                <div class="invalid-feedback d-block" id="approvedByName-feedback"></div>
                            </div>


                            <div class="mb-3">
                                <label for="itDeptName" class="form-label">I.T. Department (if needed)</label>
                                <div class="input-group">
                                    <input type="hidden" name="ITDepartment" id="itDeptId">
                                    <input type="text" class="form-control" id="itDeptName" readonly required placeholder="Select employee...">
                                    <button type="button" class="btn btn-outline-secondary select-employee-open-btn" data-bs-toggle="modal" data-bs-target="#employeeSelectModalOutbound" data-target-input="itDept"><i class="bi bi-search"></i></button>
                                    <button type="button" class="btn btn-outline-secondary clear-employee-btn btn-clear" data-target-input="itDept" title="Remove Selection"><i class="bi bi-x-lg"></i></button>
                                </div>
                            </div>


                            <div class="mb-3">
                                <label for="image" class="form-label">Attach Outbound Image</label>
                                <div id="imagePreviewContainer" class="image-preview-container">
                                    <img id="imagePreview" src="#" alt="Image Preview" class="image-preview" />
                                    <div id="imagePlaceholder" class="image-placeholder">
                                        <i class="bi bi-file-earmark-image fs-1"></i>
                                        <p class="mb-0">Image Preview</p>
                                    </div>
                                </div>
                                <div class="file-upload-wrapper mt-2">
                                    <label class="file-upload-label" for="imageInput">
                                        <i class="bi bi-upload me-2"></i> <span>Choose Image</span>
                                    </label>
                                    <input type="file" name="Image" id="imageInputUpload" class="form-control" accept="image/*" required>
                                </div>
                                <div class="invalid-feedback">Please attach an image.</div>
                            </div>
                        </div>
                    </div>

                    <div class="section-divider"></div>

                    <div class="text-end">
                        <button type="button" class="btn btn-secondary me-2" onclick="window.location.href='outbound-assets.php'">
                            <i class="bi bi-x-circle me-1"></i>Cancel
                        </button>
                        <button type="submit" class="btn btn-primary" name="submitRequest">
                            <i class="bi bi-check-circle-fill me-1"></i>Submit Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="loadingModal" class="loading-modal">
        <div class="loading-modal-content">
            <div class="modal-body-loading">
                <div class="left-0 animate-pulse-start">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M22 21H2V19H3V11L12 3L21 11V19H22V21ZM19 19V12.2L12 5.8L5 12.2V19H19Z"></path>
                        <path d="M15 13H17V15H15zM15 16H17V18H15zM11 13H13V15H11zM11 16H13V18H11zM7 13H9V15H7zM7 16H9V18H7z"></path>
                    </svg>
                </div>

                <div class="road"></div>

                <div class="animate-drive">
                    <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M21.5,12.5H20v-3A1.5,1.5,0,0,0,18.5,8H13V6.5A1.5,1.5,0,0,0,11.5,5h-8A1.5,1.5,0,0,0,2,6.5v9A1.5,1.5,0,0,0,3.5,17H4a2,2,0,0,0,4,0h8a2,2,0,0,0,4,0h.5a1.5,1.5,0,0,0,1.5-1.5v-2A1.5,1.5,0,0,0,21.5,12.5ZM6,17a1,1,0,1,1-1-1A1,1,0,0,1,6,17Zm12,0a1,1,0,1,1-1-1A1,1,0,0,1,18,17ZM12,15H3.5a.5.5,0,0,1-.5-.5v-9A.5.5,0,0,1,3.5,5h8a.5.5,0,0,1,.5.5V15Zm8-2.5a.5.5,0,0,1-.5.5H14V9h4.5a.5.5,0,0,1,.5.5Z" />
                    </svg>
                </div>

                <div class="right-0 animate-pulse-end">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12,2A10,10,0,0,0,2,12a10,10,0,0,0,10,10,10,10,0,0,0,10-10A10,10,0,0,0,12,2Zm0,18a8,8,0,1,1,8-8A8,8,0,0,1,12,20Z" />
                        <path d="M12,6a1,1,0,0,0-1,1v5.59l-3.7,3.7,1.41,1.41,4.29-4.3V7A1,1,0,0,0,12,6Z" />
                    </svg>
                </div>
            </div>

            <h5 class="text-xl font-semibold text-gray-800 mt-8">Requesting Asset Outbound</h5>
            <p class="text-gray-500 mt-2">Please wait while we process your request...</p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>


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
        const sameDayCheckbox = document.getElementById('sameDayCheckbox');
        const departureDateInput = document.getElementById('departureDate');

        const originalMinDate = "<?php echo $minDate; ?>";
        const today = "<?php echo $today; ?>";

        sameDayCheckbox.addEventListener('change', function() {
            if (this.checked) {
                departureDateInput.min = today;
                departureDateInput.max = today;
                departureDateInput.value = today;
            } else {
                departureDateInput.min = originalMinDate;
                departureDateInput.removeAttribute('max');
                departureDateInput.value = '';
            }
        });
    </script>


    <script>
        (() => {
            'use strict'
            const forms = document.querySelectorAll('.needs-validation');

            Array.from(forms).forEach(form => {
                form.addEventListener('submit', event => {
                    let isValid = form.checkValidity();

                    // Custom validation for required hidden fields
                    const requiredHiddenFields = [{
                            id: 'employeeId',
                            name: 'Prepared By'
                        },
                        {
                            id: 'requestedById',
                            name: 'Requested By'
                        },
                        {
                            id: 'approvedById',
                            name: 'Approved By'
                        }
                    ];

                    requiredHiddenFields.forEach(field => {
                        const input = document.getElementById(field.id);
                        if (input && input.hasAttribute('required') && !input.value.trim()) {
                            isValid = false;
                            input.classList.add('is-invalid');
                            const feedback = input.parentElement.querySelector('.invalid-feedback');
                            if (feedback) {
                                feedback.textContent = `Please select ${field.name.toLowerCase()}.`;
                            }
                        } else if (input) {
                            input.classList.remove('is-invalid');
                        }
                    });

                    if (!isValid) {
                        event.preventDefault();
                        event.stopPropagation();
                    }

                    form.classList.add('was-validated');
                }, false);
            });
        })();
    </script>

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

                // Close the modal programmatically
                const modal = bootstrap.Modal.getInstance(document.getElementById('employeeSelectModal'));
                modal.hide();

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

                const noItemsRow = document.getElementById('no-items-row');
                if (noItemsRow) {
                    noItemsRow.remove();
                }



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

    <script>
        document.getElementById('myForm').addEventListener('submit', (e) => {
            const table = document.getElementById('selectedProductsTable');
            // Skip header row
            const rows = Array.from(table.rows).slice(1);

            if (rows.length === 0) {
                e.preventDefault();
                toastr.error('Please select at least one product.');
                return;
            }

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

            // Check if all quantities are zero or invalid (optional)
            const hasValidQuantity = products.some(p => p.quantity !== '0' && p.quantity !== '');

            if (!hasValidQuantity) {
                e.preventDefault();
                toastr.error('Please enter a valid quantity for at least one product.');
                return;
            }

            const imageInputUploadValidation = document.getElementById('imageInputUpload');

            if (!imageInputUploadValidation || imageInputUploadValidation.files.length === 0) {
                e.preventDefault();
                toastr.error('Please upload an image');
                return;
            }


            document.getElementById('tableValue').value = JSON.stringify(products);

            const form = document.getElementById('myForm');
            const loadingModal = document.getElementById('loadingModal');
            if (!form.checkValidity()) {
                // This will highlight missing fields automatically
                form.reportValidity(); // show validation messages (browser default)
                return; // prevent further execution
            }

            loadingModal.style.display = 'flex'; // Show modal

        });
    </script>

    <!--Load Products when Select Items Clicked -->
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
                    if (!response.ok) throw new Error('Network response was not ok');
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
                    document.getElementById('productModalContent').innerHTML =
                        '<div class="alert alert-danger">Failed to load products. Please try again later.</div>';
                });
        });
    </script>

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

    <!--Datatable -->
    <script>
        $(document).ready(function() {
            $('#employeeTables').DataTable({
                // Optional: add options here
                "pageLength": 10
            });
        });
    </script>

    <script>
        const todayDate = new Date().toISOString().split('T')[0];
        document.getElementById('dateRequested').setAttribute('max', todayDate);
        document.getElementById('expectedReturn').setAttribute('min', todayDate);
    </script>

    <script src="script.js"></script>
    <script src="script/stopper.js"></script>

</body>

<?php $conn->close(); ?>

</html>