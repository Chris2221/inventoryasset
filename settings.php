<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include "config.php";

$createdBy = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['approvers_json'])) {
    $approversJson = $_POST['approvers_json'];
    $data = json_decode($approversJson, true);

    if (!is_array($data) || count($data) < 1) {
        echo '<div class="alert alert-danger">No approvers selected.</div>';
    } else {
        $settingJson = json_encode($data);

        $check = $conn->query("SELECT PK_Settings FROM Settings WHERE SettingType = 1");
        if ($check->num_rows > 0) {
            $row = $check->fetch_assoc();
            $stmt = $conn->prepare("UPDATE Settings SET SettingValue = ?, CreatedOn = NOW(), CreatedBy = ? WHERE PK_Settings = ?");
            $stmt->bind_param("sii", $settingJson, $createdBy, $row['PK_Settings']);
        } else {
            $stmt = $conn->prepare("INSERT INTO Settings (SettingType, SettingValue, CreatedBy) VALUES (1, ?, ?)");
            $stmt->bind_param("si", $settingJson, $createdBy);
        }

        if ($stmt->execute()) {
            header("Location: settings.php?success=1");
            exit;
        } else {
            header("Location: settings.php?error=1");
            exit;
        }

        $stmt->close();
    }
}


if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['saveDynamicApproversGen'])) {
    $approversJson = $_POST['approvers_jsongen'];
    $data = json_decode($approversJson, true);

    if (!is_array($data) || count($data) < 1) {
        echo '<div class="alert alert-danger">No approvers selected.</div>';
    } else {
        $settingJson = json_encode($data);

        $check = $conn->query("SELECT PK_Settings FROM Settings WHERE SettingType = 2");
        if ($check->num_rows > 0) {
            $row = $check->fetch_assoc();
            $stmt = $conn->prepare("UPDATE Settings SET SettingValue = ?, CreatedOn = NOW(), CreatedBy = ? WHERE PK_Settings = ?");
            $stmt->bind_param("sii", $settingJson, $createdBy, $row['PK_Settings']);
        } else {
            $stmt = $conn->prepare("INSERT INTO Settings (SettingType, SettingValue, CreatedBy) VALUES (2, ?, ?)");
            $stmt->bind_param("si", $settingJson, $createdBy);
        }

        if ($stmt->execute()) {
            header("Location: settings.php?success=1");
            exit;
        } else {
            header("Location: settings.php?error=1");
            exit;
        }

        $stmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['saveSameDayApprover'])) {
    $approversJson = $_POST['approvers_jsongen'];
    $data = json_decode($approversJson, true);

    if (!is_array($data) || count($data) < 1) {
        echo '<div class="alert alert-danger">No approvers selected.</div>';
    } else {
        $settingJson = json_encode($data);

        $check = $conn->query("SELECT PK_Settings FROM Settings WHERE SettingType = 3");
        if ($check->num_rows > 0) {
            $row = $check->fetch_assoc();
            $stmt = $conn->prepare("UPDATE Settings SET SettingValue = ?, CreatedOn = NOW(), CreatedBy = ? WHERE PK_Settings = ?");
            $stmt->bind_param("sii", $settingJson, $createdBy, $row['PK_Settings']);
        } else {
            $stmt = $conn->prepare("INSERT INTO Settings (SettingType, SettingValue, CreatedBy) VALUES (3, ?, ?)");
            $stmt->bind_param("si", $settingJson, $createdBy);
        }

        if ($stmt->execute()) {
            header("Location: settings.php?success=1");
            exit;
        } else {
            header("Location: settings.php?error=1");
            exit;
        }

        $stmt->close();
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['sameDayLimitsubmit'])) {
    $settingValue = (int) $_POST['sameDayLimit'];
    $settingType = 4;

    // Check if a setting with SettingType = 4 already exists
    $checkStmt = $conn->prepare("SELECT PK_Settings FROM Settings WHERE SettingType = ?");
    $checkStmt->bind_param("i", $settingType);
    $checkStmt->execute();
    $checkStmt->store_result();

    if ($checkStmt->num_rows > 0) {
        // Exists: Update
        $checkStmt->bind_result($pkSetting);
        $checkStmt->fetch();
        $checkStmt->close();

        $updateStmt = $conn->prepare("UPDATE Settings SET SettingValue = ?, CreatedOn = CURRENT_TIMESTAMP, CreatedBy = ? WHERE PK_Settings = ?");
        $updateStmt->bind_param("sii", $settingValue, $createdBy, $pkSetting);
        $updateStmt->execute();
        $updateStmt->close();
        header("Location: settings.php?success=1");
        exit;
    } else {
        // Doesn't exist: Insert
        $checkStmt->close();

        $insertStmt = $conn->prepare("INSERT INTO Settings (SettingType, SettingValue, CreatedBy) VALUES (?, ?, ?)");
        $insertStmt->bind_param("isi", $settingType, $settingValue, $createdBy);
        $insertStmt->execute();
        $insertStmt->close();
        header("Location: settings.php?success=1");
        exit;
    }
}


$currentApprovers = [];
$resApprovers = $conn->query("SELECT SettingValue FROM Settings WHERE SettingType = 1");

if ($resApprovers && $resApprovers->num_rows > 0) {
    $row = $resApprovers->fetch_assoc();
    $currentApprovers = json_decode($row['SettingValue'], true);
}

$generalAssetApprovers = [];
$resApproversGen = $conn->query("SELECT SettingValue FROM Settings WHERE SettingType = 2");

if ($resApproversGen && $resApproversGen->num_rows > 0) {
    $row = $resApproversGen->fetch_assoc();
    $generalAssetApprovers = json_decode($row['SettingValue'], true);
}

$sameDayApprovers = [];
$resApproversSameDay = $conn->query("SELECT SettingValue FROM Settings WHERE SettingType = 3");

if ($resApproversSameDay && $resApproversSameDay->num_rows > 0) {
    $row = $resApproversSameDay->fetch_assoc();
    $sameDayApprovers = json_decode($row['SettingValue'], true);
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings</title>
    <link rel="icon" href="inventory.png" type="image/x-icon">
    <?php include "tools/head-plugin.php" ?>

</head>

<body>
    <?php include 'header.php'; ?>
    <div class="container">
        <h2 class="mb-4">Settings</h2>

        <?php include 'modal.php'; ?>

        <div class="d-flex justify-content-start mb-3">

            <a href="transfer-assets.php" class="btn btn-dark"><i class="bi bi-arrow-left-circle me-2"></i>Back</a>

        </div> <br>

        <?php include "tools/alert-message.php"; ?>

        <div class="accordion" id="settingsAccordion">

            <!-- IT Asset Approvers -->
            <div class="accordion-item">
                <h2 class="accordion-header" id="headingApprovers">
                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseApprovers" aria-expanded="true" aria-controls="collapseApprovers">
                        IT Asset Approvers
                    </button>
                </h2>
                <div id="collapseApprovers" class="accordion-collapse collapse show" aria-labelledby="headingApprovers" data-bs-parent="#settingsAccordion">
                    <div class="accordion-body">
                        <!-- Dynamic Approvers Form Goes Here -->

                        <?php if (!empty($currentApprovers)): ?>
                            <div class="mb-4">
                                <h5>Current Approver Steps:</h5>
                                <ul class="list-group">
                                    <?php
                                    foreach ($currentApprovers as $step) {
                                        $approverId = intval($step['approver_id']);
                                        $stepNumber = intval($step['step']);
                                        $empRes = $conn->query("SELECT Name FROM Employees WHERE PK_Employees = $approverId");
                                        $name = $empRes && $empRes->num_rows > 0 ? $empRes->fetch_assoc()['Name'] : 'Unknown';
                                        echo "<li class='list-group-item'>Step {$stepNumber}: <strong>" . htmlspecialchars($name) . "</strong></li>";
                                    }
                                    ?>
                                </ul>


                            </div>

                            <button class="btn btn-outline-primary mb-3" type="button" data-bs-toggle="collapse" data-bs-target="#approverFormCollapse" aria-expanded="true" aria-controls="approverFormCollapse">
                                Change Approver Steps
                            </button>

                        <?php endif; ?>
                        <div class="collapse mt-4" id="approverFormCollapse">
                            <div class="card card-body">
                                <form method="post" id="approversForm">
                                    <input type="hidden" name="approvers_json" id="approvers_json">
                                    <div id="approverSteps">
                                        <!-- First Step Default -->
                                        <div class="approver-step mb-3">
                                            <label class="form-label">Step 1 Approver</label>
                                            <div class="input-group">
                                                <select class="form-select approver-select" required>
                                                    <option value="">-- Select Approver --</option>
                                                    <?php
                                                    $res = $conn->query("SELECT PK_Employees, Name FROM Employees WHERE Status = 'Active'");
                                                    $approverOptions = "";
                                                    while ($row = $res->fetch_assoc()) {
                                                        $approverOptions .= "<option value='{$row['PK_Employees']}'>" . htmlspecialchars($row['Name']) . "</option>";
                                                    }
                                                    echo $approverOptions;
                                                    ?>
                                                </select>
                                                <button type="button" class="btn btn-danger remove-step ms-2">Remove</button>
                                            </div>
                                        </div>
                                    </div>

                                    <button type="button" class="btn btn-secondary mb-3" id="addStep">Add Step</button>
                                    <br>
                                    <button type="submit" name="saveDynamicApprovers" class="btn btn-primary">Save Approvers</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Other Settings Example -->
            <div class="accordion-item">
                <h2 class="accordion-header" id="headingOther1">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOther1" aria-expanded="false" aria-controls="collapseOther1">
                        General Asset Approvers
                    </button>
                </h2>
                <div id="collapseOther1" class="accordion-collapse collapse" aria-labelledby="headingOther1" data-bs-parent="#settingsAccordion">
                    <div class="accordion-body">

                        <?php if (!empty($generalAssetApprovers)): ?>
                            <div class="mb-4">
                                <h5>Current Approver Steps:</h5>
                                <ul class="list-group">
                                    <?php
                                    foreach ($generalAssetApprovers as $step) {
                                        $approverId = intval($step['approver_id']);
                                        $stepNumber = intval($step['step']);
                                        $empRes = $conn->query("SELECT Name FROM Employees WHERE PK_Employees = $approverId");
                                        $name = $empRes && $empRes->num_rows > 0 ? $empRes->fetch_assoc()['Name'] : 'Unknown';
                                        echo "<li class='list-group-item'>Step {$stepNumber}: <strong>" . htmlspecialchars($name) . "</strong></li>";
                                    }
                                    ?>
                                </ul>


                            </div>

                            <button class="btn btn-outline-primary mb-3" type="button" data-bs-toggle="collapse" data-bs-target="#approverGeneralFormCollapse" aria-expanded="true" aria-controls="approverGeneralFormCollapse">
                                Change Approver Steps
                            </button>

                        <?php endif; ?>
                        <div class="collapse mt-4" id="approverGeneralFormCollapse">
                            <div class="card card-body">
                                <form method="post" id="approversFormGen">
                                    <input type="hidden" name="approvers_jsongen" id="approvers_jsongen">
                                    <div id="approverStepsGen">
                                        <!-- First Step Default -->
                                        <div class="approver-step-gen mb-3">
                                            <label class="form-label">Step 1 Approver</label>
                                            <div class="input-group">
                                                <select class="form-select approver-select-gen" required>
                                                    <option value="">-- Select Approver --</option>
                                                    <?php
                                                    $res = $conn->query("SELECT PK_Employees, Name FROM Employees WHERE Status = 'Active'");
                                                    $approverOptionsGen = "";
                                                    while ($row = $res->fetch_assoc()) {
                                                        $approverOptionsGen .= "<option value='{$row['PK_Employees']}'>" . htmlspecialchars($row['Name']) . "</option>";
                                                    }
                                                    echo $approverOptionsGen;
                                                    ?>
                                                </select>
                                                <button type="button" class="btn btn-danger remove-step-gen ms-2">Remove</button>
                                            </div>
                                        </div>
                                    </div>

                                    <button type="button" class="btn btn-secondary mb-3" id="addStepGen">Add Step</button>
                                    <br>
                                    <button type="submit" name="saveDynamicApproversGen" class="btn btn-primary">Save Approvers</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Other Settings Example -->
            <div class="accordion-item">
                <h2 class="accordion-header" id="headingOther1">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOther2" aria-expanded="false" aria-controls="collapseOther2">
                        Same-Day Departure Additional Approver(s)
                    </button>
                </h2>
                <div id="collapseOther2" class="accordion-collapse collapse" aria-labelledby="headingOther1" data-bs-parent="#settingsAccordion">
                    <div class="accordion-body">

                        <?php if (!empty($sameDayApprovers)): ?>
                            <div class="mb-4">
                                <h5>Current Approver Steps:</h5>
                                <ul class="list-group">
                                    <?php
                                    foreach ($sameDayApprovers as $step) {
                                        $approverId = intval($step['approver_id']);
                                        $stepNumber = intval($step['step']);
                                        $empRes = $conn->query("SELECT Name FROM Employees WHERE PK_Employees = $approverId");
                                        $name = $empRes && $empRes->num_rows > 0 ? $empRes->fetch_assoc()['Name'] : 'Unknown';
                                        echo "<li class='list-group-item'>Step {$stepNumber}: <strong>" . htmlspecialchars($name) . "</strong></li>";
                                    }
                                    ?>
                                </ul>
                            </div>

                            <button class="btn btn-outline-primary mb-3" type="button" data-bs-toggle="collapse" data-bs-target="#approverSameDayFormCollapse" aria-expanded="true" aria-controls="approverGeneralFormCollapse">
                                Change Approver Steps
                            </button>

                        <?php endif; ?>


                        <div class="collapse mt-4" id="approverSameDayFormCollapse">
                            <div class="card card-body">
                                <form method="post" id="approversFormSameDay">

                                    <input type="hidden" name="approvers_jsongen" id="approvers_jsonSameDay">
                                    <div id="approverSameDay">
                                        <!-- First Step Default -->
                                        <div class="approver-step-same-day mb-3">
                                            <label class="form-label">Step 1 Approver</label>
                                            <div class="input-group">
                                                <select class="form-select approver-select-same-day" required>
                                                    <option value="">-- Select Approver --</option>
                                                    <?php
                                                    $res = $conn->query("SELECT PK_Employees, Name FROM Employees WHERE Status = 'Active'");
                                                    $approverOptionsSameDay = "";
                                                    while ($row = $res->fetch_assoc()) {
                                                        $approverOptionsSameDay .= "<option value='{$row['PK_Employees']}'>" . htmlspecialchars($row['Name']) . "</option>";
                                                    }
                                                    echo $approverOptionsSameDay;
                                                    ?>
                                                </select>
                                                <button type="button" class="btn btn-danger remove-step-same-day ms-2">Remove</button>
                                            </div>
                                        </div>
                                    </div>

                                    <button type="button" class="btn btn-secondary mb-3" id="addStepSameDay">Add Step</button>
                                    <br>
                                    <button type="submit" name="saveSameDayApprover" class="btn btn-primary">Save Approvers</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>




            <!-- Other Settings Example -->
            <div class="accordion-item">
                <h2 class="accordion-header" id="headingOther2">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOther3" aria-expanded="false" aria-controls="collapseOther3">
                        No. of Days before Departure to Request IT Assets
                    </button>
                </h2>
                <div id="collapseOther3" class="accordion-collapse collapse" aria-labelledby="headingOther2" data-bs-parent="#settingsAccordion">
                    <div class="accordion-body">

                        <div class="collapse mt-4" id="noofdayFormCollapse">
                            <div class="card card-body">
                                <?php
                                $settingType = 4;
                                $stmt = $conn->prepare("SELECT SettingValue FROM Settings WHERE SettingType = ? LIMIT 1");
                                $stmt->bind_param("i", $settingType);
                                $stmt->execute();
                                $stmt->bind_result($existingValue);
                                $stmt->fetch();
                                $stmt->close();
                                ?>
                                <form method="post" id="approversFormSameDay">
                                    <div class="mb-3">
                                        <label for="sameDayLimit" class="form-label">Enter No. of Days</label>
                                        <input type="number" class="form-control" id="sameDayLimit" name="sameDayLimit" required  value="<?php echo htmlspecialchars($existingValue); ?>">
                                    </div>
                                    <button type="submit" name="sameDayLimitsubmit" class="btn btn-primary">Save</button>

                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="script/stopper.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const collapseEl = document.getElementById('noofdayFormCollapse');
            new bootstrap.Collapse(collapseEl, {
                show: true
            });
        });
    </script>

    <!-- Script for Same Day Aprovers -->
    <script>
        const approverOptionsSameDay = `<?= $approverOptionsSameDay ?>`;

        function renumberStepsSameDay() {
            const steps = document.querySelectorAll('.approver-step-same-day');
            steps.forEach((step, index) => {
                step.querySelector('label').textContent = `Step ${index + 1} Approver`;
            });
        }

        document.getElementById('addStepSameDay').addEventListener('click', () => {
            const stepDiv = document.createElement('div');
            stepDiv.classList.add('approver-step-same-day', 'mb-3');
            stepDiv.innerHTML = `
            <label class="form-label">Step Approver</label>
            <div class="input-group">
                <select class="form-select approver-select-same-day" required>
                    <option value="">-- Select Approver --</option>
                    ${approverOptionsSameDay}
                </select>
                <button type="button" class="btn btn-danger remove-step-same-day ms-2">Remove</button>
            </div>
        `;
            document.getElementById('approverSameDay').appendChild(stepDiv);
            renumberStepsSameDay();
        });

        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-step-same-day')) {
                e.target.closest('.approver-step-same-day').remove();
                renumberStepsSameDay();
            }
        });

        document.getElementById('approversFormSameDay').addEventListener('submit', function(e) {
            const selects = document.querySelectorAll('.approver-select-same-day');
            const data = [];
            const used = [];

            selects.forEach((sel, index) => {
                const value = sel.value;
                //if (value && !used.includes(value)) {
                if (value) {
                    data.push({
                        step: index + 1,
                        approver_id: parseInt(value)
                    });
                    used.push(value);
                }
            });

            document.getElementById('approvers_jsonSameDay').value = JSON.stringify(data);
            // Optional: uncomment below if you want to see result in console
            // console.log("Approvers JSON:", document.getElementById('approvers_json').value);
        });
    </script>

    <!-- Script for General Aprovers -->
    <script>
        const approverOptionsGen = `<?= $approverOptionsGen ?>`;

        function renumberStepsGen() {
            const steps = document.querySelectorAll('.approver-step-gen');
            steps.forEach((step, index) => {
                step.querySelector('label').textContent = `Step ${index + 1} Approver`;
            });
        }

        document.getElementById('addStepGen').addEventListener('click', () => {
            const stepDiv = document.createElement('div');
            stepDiv.classList.add('approver-step-gen', 'mb-3');
            stepDiv.innerHTML = `
            <label class="form-label">Step Approver</label>
            <div class="input-group">
                <select class="form-select approver-select-gen" required>
                    <option value="">-- Select Approver --</option>
                    ${approverOptionsGen}
                </select>
                <button type="button" class="btn btn-danger remove-step-gen ms-2">Remove</button>
            </div>
        `;
            document.getElementById('approverStepsGen').appendChild(stepDiv);
            renumberStepsGen();
        });

        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-step-gen')) {
                e.target.closest('.approver-step-gen').remove();
                renumberStepsGen();
            }
        });

        document.getElementById('approversFormGen').addEventListener('submit', function(e) {
            const selects = document.querySelectorAll('.approver-select-gen');
            const data = [];
            const used = [];

            selects.forEach((sel, index) => {
                const value = sel.value;
                //if (value && !used.includes(value)) {
                if (value) {
                    data.push({
                        step: index + 1,
                        approver_id: parseInt(value)
                    });
                    used.push(value);
                }
            });

            document.getElementById('approvers_jsongen').value = JSON.stringify(data);
            // Optional: uncomment below if you want to see result in console
            // console.log("Approvers JSON:", document.getElementById('approvers_json').value);
        });
    </script>

    <!-- Script for IT and General Aprovers -->
    <script>
        const approverOptions = `<?= $approverOptions ?>`;

        function renumberSteps() {
            const steps = document.querySelectorAll('.approver-step');
            steps.forEach((step, index) => {
                step.querySelector('label').textContent = `Step ${index + 1} Approver`;
            });
        }

        document.getElementById('addStep').addEventListener('click', () => {
            const stepDiv = document.createElement('div');
            stepDiv.classList.add('approver-step', 'mb-3');
            stepDiv.innerHTML = `
            <label class="form-label">Step Approver</label>
            <div class="input-group">
                <select class="form-select approver-select" required>
                    <option value="">-- Select Approver --</option>
                    ${approverOptions}
                </select>
                <button type="button" class="btn btn-danger remove-step ms-2">Remove</button>
            </div>
        `;
            document.getElementById('approverSteps').appendChild(stepDiv);
            renumberSteps();
        });

        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-step')) {
                e.target.closest('.approver-step').remove();
                renumberSteps();
            }
        });

        document.getElementById('approversForm').addEventListener('submit', function(e) {
            const selects = document.querySelectorAll('.approver-select');
            const data = [];
            const used = [];

            selects.forEach((sel, index) => {
                const value = sel.value;
                //if (value && !used.includes(value)) {
                if (value) {
                    data.push({
                        step: index + 1,
                        approver_id: parseInt(value)
                    });
                    used.push(value);
                }
            });

            document.getElementById('approvers_json').value = JSON.stringify(data);
            // Optional: uncomment below if you want to see result in console
            // console.log("Approvers JSON:", document.getElementById('approvers_json').value);
        });
    </script>

    <?php if (empty($currentApprovers)): ?>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const collapseEl = document.getElementById('approverFormCollapse');
                new bootstrap.Collapse(collapseEl, {
                    show: true
                });
            });
        </script>
    <?php endif; ?>

    <?php if (empty($generalAssetApprovers)): ?>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const collapseEl = document.getElementById('approverGeneralFormCollapse');
                new bootstrap.Collapse(collapseEl, {
                    show: true
                });
            });
        </script>
    <?php endif; ?>

    <?php if (empty($sameDayApprovers)): ?>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const collapseEl = document.getElementById('approverSameDayFormCollapse');
                new bootstrap.Collapse(collapseEl, {
                    show: true
                });
            });
        </script>
    <?php endif; ?>

</body>

<?php $conn->close(); ?>

</html>