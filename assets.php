<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include "config.php";
include "tools/sender.php";

$createdBy = $_SESSION['user_id'];

$selectedAssetType = isset($_GET['AssetType']) ? $_GET['AssetType'] : 'All';
$selectedStatus = isset($_GET['Status']) ? $_GET['Status'] : 'All';
$selectedCondition = isset($_GET['Condition']) ? $_GET['Condition'] : 'All';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['Delete'])) {
    $id = $_POST['PK_AssetMaster'];
    $deleteRemarks = $_POST['DeleteRemarks'];

    $stmt = $conn->prepare("UPDATE AssetMaster SET IsArchived = 1, ArchivedRemarks = ? WHERE PK_AssetMaster = ?");
    $stmt->bind_param("si", $deleteRemarks, $id);

    if ($stmt->execute()) {
        $currentUser = $_SESSION['user_id'];
        $actionUser = "Archive Asset";
        $logDetails = "Archived asset ID: AST-$id. Remarks: \"$deleteRemarks\"";
        logActivity($conn, $currentUser, $actionUser, $logDetails);

        header("Location: assets.php?AssetType=" . urlencode($selectedAssetType) .
            "&Status=" . urlencode($selectedStatus) .
            "&Condition=" . urlencode($selectedCondition) .
            "&status=deleted");
        exit;
    } else {
        echo "Error: " . $stmt->error;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['Update'])) {
    $id = $_POST['PK_AssetMaster'];

    $condition = $_POST['condition'];
    $AssetTagNumber = $_POST['AssetTagNumber'];
    $FK_AssetType = $_POST['FK_AssetType'];
    $BrandManufacturer = $_POST['BrandManufacturer'];
    $Model = $_POST['Model'];
    $SerialNumber = $_POST['SerialNumber'];
    $WarrantyExpiryDate = $_POST['WarrantyExpiryDate'];
    $Description = $_POST['Description'];
    $PurchasePrice = $_POST['PurchasePrice'];
    $SupplierVendor = $_POST['SupplierVendor'];
    $PurchaseDate = $_POST['PurchaseDate'];
    $latitude = $_POST['latitude'];
    $longitude = $_POST['longitude'];


    $OldImage = $_POST['OldImage'] ?? null; // Get the old image name if it exists


    $targetDir = "image/assetimages/";
    $newFileName = $OldImage;


    if (isset($_FILES['AssetImage']) && $_FILES['AssetImage']['error'] == 0) {

        if (!empty($OldImage)) {
            $oldImagePath = $targetDir . basename($OldImage);

            if (file_exists($oldImagePath)) {
                unlink($oldImagePath); // Delete the old image file
            }
        }

        $fileTmpPath = $_FILES['AssetImage']['tmp_name'];
        $originalName = $_FILES['AssetImage']['name'];

        // Get file extension
        $fileExtension = pathinfo($originalName, PATHINFO_EXTENSION);

        // Generate new file name (timestamp + random)
        $newFileName = time() . '_' . bin2hex(random_bytes(5)) . '.' . $fileExtension;

        $dest_path = $targetDir . $newFileName;

        if (move_uploaded_file($fileTmpPath, $dest_path)) {
            // Upload successful, $newFileName contains the saved file name
            echo "Uploaded file name: " . $newFileName;

            // You can now save $newFileName to your database or use it elsewhere
        } else {
            echo "Error moving the uploaded file.";
        }
    }

    $select = $conn->prepare("SELECT * FROM AssetMaster WHERE PK_AssetMaster = ?");
    $select->bind_param("i", $id);
    $select->execute();
    $result = $select->get_result();
    $oldData = $result->fetch_assoc();
    $select->close();



    $changes = [];

    if ($oldData['AssetTagNumber'] !== $AssetTagNumber) {
        $changes[] = "AssetTagNumber: '{$oldData['AssetTagNumber']}' → '$AssetTagNumber'";
    }
    if ($oldData['FK_AssetType'] != $FK_AssetType) {
        $changes[] = "Asset Type: '{$oldData['FK_AssetType']}' → '$FK_AssetType'";
    }
    if ($oldData['BrandManufacturer'] !== $BrandManufacturer) {
        $changes[] = "BrandManufacturer: '{$oldData['BrandManufacturer']}' → '$BrandManufacturer'";
    }
    if ($oldData['Model'] !== $Model) {
        $changes[] = "Model: '{$oldData['Model']}' → '$Model'";
    }
    if ($oldData['SerialNumber'] !== $SerialNumber) {
        $changes[] = "SerialNumber: '{$oldData['SerialNumber']}' → '$SerialNumber'";
    }
    if ($oldData['WarrantyExpiryDate'] !== $WarrantyExpiryDate) {
        $changes[] = "WarrantyExpiryDate: '{$oldData['WarrantyExpiryDate']}' → '$WarrantyExpiryDate'";
    }
    if ($oldData['Descriptions'] !== $Description) {
        $changes[] = "Description: '{$oldData['Descriptions']}' → '$Description'";
    }
    if ($oldData['PurchasePrice'] !== $PurchasePrice) {
        $changes[] = "PurchasePrice: '{$oldData['PurchasePrice']}' → '$PurchasePrice'";
    }
    if ($oldData['SupplierVendor'] !== $SupplierVendor) {
        $changes[] = "SupplierVendor: '{$oldData['SupplierVendor']}' → '$SupplierVendor'";
    }

    $conditionLabels = [
        1 => 'New',
        2 => 'Good',
        3 => 'Used',
        4 => 'Repaired',
        5 => 'Damaged',
        6 => 'Under Repair',
        7 => 'Decommissioned'
    ];

    // Convert both old and new values to readable labels:
    $oldCondLabel = $conditionLabels[(int)$oldData['Conditions']] ?? $oldData['Conditions'];
    $newCondLabel = $conditionLabels[(int)$condition] ?? $condition;

    if ($oldCondLabel !== $newCondLabel) {
        $changes[] = "Condition: '$oldCondLabel' → '$newCondLabel'";
    }


    if ($oldData['PurchaseDate'] !== $PurchaseDate) {
        $changes[] = "PurchaseDate: '{$oldData['PurchaseDate']}' → '$PurchaseDate'";
    }


    $stmt = $conn->prepare("UPDATE AssetMaster SET 
        AssetTagNumber = ?, FK_AssetType = ?, BrandManufacturer = ?, Model = ?, SerialNumber = ?, 
        WarrantyExpiryDate = ?, Descriptions = ?, PurchasePrice = ?, SupplierVendor = ?, `Conditions` = ?, Image = ?, 
        PurchaseDate = ?,
        latitude = ?,
        longitude = ?
        WHERE PK_AssetMaster = ?");
    $stmt->bind_param(
        "sissssssssssssi",
        $AssetTagNumber,
        $FK_AssetType,
        $BrandManufacturer,
        $Model,
        $SerialNumber,
        $WarrantyExpiryDate,
        $Description,
        $PurchasePrice,
        $SupplierVendor,
        $condition,
        $newFileName,
        $PurchaseDate,
        $latitude,
        $longitude,
        $id
    );

    if ($stmt->execute()) {
        if (!empty($changes)) {
            $logDetails = "<strong>Updated asset ID: AST-$id</strong><br><table class='table table-bordered table-sm mt-2'><thead><tr><th>Old Value</th><th>New Value</th></tr></thead><tbody>";

            foreach ($changes as $field => $change) {
                list($oldValue, $newValue) = explode(" → ", $change);
                $logDetails .= "<tr><td>" . htmlspecialchars($oldValue) . "</td><td>" . htmlspecialchars($newValue) . "</td></tr>";
            }

            $logDetails .= "</tbody></table>";

            $currentUser = $_SESSION['user_id'];
            $actionUser = "Update Asset";

            logActivity($conn, $currentUser, $actionUser, $logDetails);
        }

        header("Location: assets.php?AssetType=" . urlencode($selectedAssetType) .
            "&Status=" . urlencode($selectedStatus) .
            "&Condition=" . urlencode($selectedCondition) .
            "&status=updated");
        exit;
    } else {
        echo "Error: " . $stmt->error;
    }
}



if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['importFromExcel'])) {
    $rows = json_decode($_POST['excelData'], true);
    if (!$rows) {
        die("Invalid JSON data");
    }

    // Assume first row = header, skip it
    foreach ($rows as $i => $row) {
        if ($i === 0) continue;

        // Adjust according to your Excel column order
        $assetTagNumber      = $row[0] ?? null;

        $assetTypeName = trim($row[1] ?? null);

        $fkAssetType   = null;

        if ($assetTypeName) {
            $stmtType = $conn->prepare("SELECT PK_AssetType FROM AssetType WHERE AssetTypeName = ? LIMIT 1");
            $stmtType->bind_param("s", $assetTypeName);
            $stmtType->execute();
            $stmtType->bind_result($fkAssetType);
            $stmtType->fetch();
            $stmtType->close();
        }

        if (!$fkAssetType) {
            continue; // move to next Excel row
        }

        $brandManufacturer   = $row[2] ?? null;
        $model               = $row[3] ?? null;
        $serialNumber        = $row[4] ?? null;
        $descriptions        = $row[5] ?? null;
        $warrantyExpiryDate  = !empty($row[6]) ? date("Y-m-d", strtotime($row[6])) : null;
        $purchasePrice       = $row[7] ?? null;
        $supplierVendor      = $row[8] ?? null;

        $conditions          = $row[9] ?? 1; // default new

        $conditionText = strtolower(trim($row[9] ?? 'new'));


        switch ($conditionText) {
            case 'new':
                $conditions = 1;
                break;
            case 'good':
                $conditions = 2;
                break;
            case 'used':
                $conditions = 3;
                break;
            case 'repaired':
                $conditions = 4;
                break;
            case 'damaged':
                $conditions = 5;
                break;
            case 'under repair':
            case 'under_repair':
            case 'repair':
                $conditions = 6;
                break;
            case 'decommissioned':
                $conditions = 7;
                break;
            default:
                $conditions = 1; // fallback to New if unknown
        }


        $purchaseDate        = !empty($row[10]) ? date("Y-m-d", strtotime($row[10])) : null;

        $createdBy           = $createdBy;

        $assignedTo          = 0;
        $image               = null;
        $isArchived          = 0;
        $archivedRemarks     = null;
        $reasonForRejection  = null;
        $latitude            = null;
        $longitude           = null;
        $receipt             = null;


        // Prepare insert
        $stmt = $conn->prepare("
                    INSERT INTO AssetMaster (
                        AssetTagNumber, FK_AssetType, BrandManufacturer, Model, SerialNumber,
                        Descriptions, WarrantyExpiryDate, PurchasePrice, PurchaseDate, SupplierVendor,
                        CreatedBy, Conditions, AssignedTo, Image, IsArchived,
                        ArchivedRemarks, ReasonForRejection, latitude, longitude, Receipt
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

        $stmt->bind_param(
            "sisssssdssiiiissssss",
            $assetTagNumber,
            $fkAssetType,
            $brandManufacturer,
            $model,
            $serialNumber,
            $descriptions,
            $warrantyExpiryDate,
            $purchasePrice,
            $purchaseDate,
            $supplierVendor,
            $createdBy,
            $conditions,
            $assignedTo,
            $image,
            $isArchived,
            $archivedRemarks,
            $reasonForRejection,
            $latitude,
            $longitude,
            $receipt
        );

        $stmt->execute();
        $stmt->close();
    }


    header("Location: assets.php?AssetType=" . urlencode($selectedAssetType) .
        "&Status=" . urlencode($selectedStatus) .
        "&Condition=" . urlencode($selectedCondition) .
        "&status=added");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
    $condition = mysqli_real_escape_string($conn, $_POST['condition']);
    $AssetTagNumber = mysqli_real_escape_string($conn, $_POST['AssetTagNumber']);
    $FK_AssetType = intval($_POST['FK_AssetType']);
    $BrandManufacturer = mysqli_real_escape_string($conn, $_POST['BrandManufacturer']);
    $Model = mysqli_real_escape_string($conn, $_POST['Model']);
    $SerialNumber = mysqli_real_escape_string($conn, $_POST['SerialNumber']);
    $WarrantyExpiryDate = !empty($_POST['WarrantyExpiryDate']) ? $_POST['WarrantyExpiryDate'] : null;
    $Description = mysqli_real_escape_string($conn, $_POST['Description']);
    $PurchasePrice = !empty($_POST['PurchasePrice']) ? floatval($_POST['PurchasePrice']) : null;
    $SupplierVendor = mysqli_real_escape_string($conn, $_POST['SupplierVendor']);
    $PurchaseDate = mysqli_real_escape_string($conn, $_POST['PurchaseDate']);
    $latitude = mysqli_real_escape_string($conn, $_POST['latitude']);
    $longitude = mysqli_real_escape_string($conn, $_POST['longitude']);

    $targetDir = "image/assetimages/";
    $newFileName = "";

    $targetDirReceipt = "image/assetreceipts/";
    $newFileNameReceipt = "";

    if (isset($_FILES['AssetImage']) && $_FILES['AssetImage']['error'] == 0) {
        $fileTmpPath = $_FILES['AssetImage']['tmp_name'];
        $originalName = $_FILES['AssetImage']['name'];

        // Get file extension
        $fileExtension = pathinfo($originalName, PATHINFO_EXTENSION);

        // Generate new file name (timestamp + random)
        $newFileName = time() . '_' . bin2hex(random_bytes(5)) . '.' . $fileExtension;

        $dest_path = $targetDir . $newFileName;

        if (move_uploaded_file($fileTmpPath, $dest_path)) {
            // Upload successful, $newFileName contains the saved file name
            echo "Uploaded file name: " . $newFileName;

            // You can now save $newFileName to your database or use it elsewhere
        } else {
            echo "Error moving the uploaded file.";
        }
    }

    if (isset($_FILES['AssetReceipt']) && $_FILES['AssetReceipt']['error'] == 0) {
        $fileTmpPath2 = $_FILES['AssetReceipt']['tmp_name'];
        $originalName2 = $_FILES['AssetReceipt']['name'];

        // Get file extension
        $fileExtension2 = pathinfo($originalName2, PATHINFO_EXTENSION);

        // Generate new file name (timestamp + random)
        $newFileNameReceipt = time() . '_' . bin2hex(random_bytes(5)) . '.' . $fileExtension2;

        $dest_path2 = $targetDirReceipt . $newFileNameReceipt;

        if (move_uploaded_file($fileTmpPath2, $dest_path2)) {
            // Upload successful, $newFileName contains the saved file name
            echo "Uploaded file name: " . $newFileNameReceipt;

            // You can now save $newFileName to your database or use it elsewhere
        } else {
            echo "Error moving the uploaded file.";
        }
    }


    $query = "INSERT INTO AssetMaster (
                AssetTagNumber, FK_AssetType, BrandManufacturer, Model, SerialNumber,
                Descriptions, WarrantyExpiryDate, PurchasePrice, SupplierVendor, CreatedBy, `Conditions`, Image, PurchaseDate, latitude, longitude, Receipt
              ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param(
        $stmt,
        "sisssssssissssss",
        $AssetTagNumber,
        $FK_AssetType,
        $BrandManufacturer,
        $Model,
        $SerialNumber,
        $Description,
        $WarrantyExpiryDate,
        $PurchasePrice,
        $SupplierVendor,
        $createdBy,
        $condition,
        $newFileName,
        $PurchaseDate,
        $latitude,
        $longitude,
        $newFileNameReceipt
    );

    if (mysqli_stmt_execute($stmt)) {
        $logDetails = "Added item with the asset tag number: $AssetTagNumber.";
        $currentUser = $_SESSION['user_id'];
        $actionUser = "Add Asset";

        logActivity($conn, $currentUser, $actionUser, $logDetails);

        header("Location: assets.php?AssetType=" . urlencode($selectedAssetType) .
            "&Status=" . urlencode($selectedStatus) .
            "&Condition=" . urlencode($selectedCondition) .
            "&status=added");
        exit;
    } else {
        echo "<script>alert('Error inserting asset.');</script>";
    }

    mysqli_stmt_close($stmt);
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['assign'])) {


    $FK_AssetMaster = intval($_POST['FK_AssetMaster']);
    $AssignedTo = intval($_POST['AssignedTo']);
    $Location = mysqli_real_escape_string($conn, $_POST['Location']);
    $DateAcquired = $_POST['DateAcquired'];
    $Conditions = mysqli_real_escape_string($conn, $_POST['Conditions']);
    $Remarks = mysqli_real_escape_string($conn, $_POST['Remarks']);
    $FK_Users = $_SESSION['user_id'];
    $Status = 'Assigned';


    $updatedApprovers = getUpdatedApproverJsonWithStatus($conn);

    //Check first if the item is already assigned
    $sql = "SELECT AssignedTo FROM AssetMaster WHERE PK_AssetMaster = ?";
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("i", $FK_AssetMaster);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            if ((int)$row['AssignedTo'] !== 0) {
                // Already assigned – redirect and exit
                $stmt->close();
                header("Location: assets.php");
                exit;
            }
        }
    }

    // Image upload handling
    $imageName = null;
    if (isset($_FILES['AssetImage']) && $_FILES['AssetImage']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['AssetImage']['name'], PATHINFO_EXTENSION);
        $imageName = 'asset_' . $FK_AssetMaster . '_' . time() . '.' . $ext;
        $uploadPath = 'image/assignimages/' . $imageName;
        move_uploaded_file($_FILES['AssetImage']['tmp_name'], $uploadPath);
    }

    $query = "INSERT INTO AssetInventory (FK_AssetMaster, Location, AssignedTo, DateAcquired, Conditions, Remarks, FK_Users, Image)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "isisssis", $FK_AssetMaster, $Location, $AssignedTo, $DateAcquired, $Conditions, $Remarks, $FK_Users, $imageName);

    if (mysqli_stmt_execute($stmt)) {

        $insertedId = mysqli_insert_id($conn);
        $approvalType = "Assigned";
        $otherReason = "";

        $stmtApproval = $conn->prepare("INSERT INTO AssignApprovals 
        (FK_AssetMaster, FK_Employees, ApprovalType, FK_Users, OtherReason, Reason, FK_AssetInventory, Approvers) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

        $stmtApproval->bind_param("iisissis", $FK_AssetMaster, $AssignedTo, $approvalType, $FK_Users, $otherReason, $Remarks, $insertedId, $updatedApprovers);
        $stmtApproval->execute();
        $lastApprovalId = $conn->insert_id;
        $stmtApproval->close();

        $updateQuery = "UPDATE AssetMaster SET AssignedTo = ? WHERE PK_AssetMaster = ?";
        $updateStmt = mysqli_prepare($conn, $updateQuery);
        mysqli_stmt_bind_param($updateStmt, "ii", $AssignedTo, $FK_AssetMaster);
        mysqli_stmt_execute($updateStmt);
        mysqli_stmt_close($updateStmt);

        AssignApproval($lastApprovalId, $conn);

        $sql = "SELECT ax.AssetTagNumber, bx.Name
        FROM assetmaster ax
        LEFT JOIN employees bx ON ax.AssignedTo = bx.PK_Employees
        WHERE ax.PK_AssetMaster = ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $FK_AssetMaster); // assuming FK_AssetMaster is an integer
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $assetTag = $row['AssetTagNumber'];
            $employeeName = $row['Name'];

            $logDetails = "Requested to assign asset $assetTag to $employeeName";
            $currentUser = $_SESSION['user_id'];
            $actionUser = "Assign Asset";

            logActivity($conn, $currentUser, $actionUser, $logDetails);
        }

        header("Location: assets.php?AssetType=" . urlencode($selectedAssetType) .
            "&Status=" . urlencode($selectedStatus) .
            "&Condition=" . urlencode($selectedCondition) .
            "&status=assigned");
        exit;
    } else {
        echo "Error assigning asset: " . mysqli_error($conn);
    }

    mysqli_stmt_close($stmt);
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unassign'])) {
    $assetId = intval($_POST['FK_AssetMaster']);
    $approvalType = "Unassigned";

    $reason =  isset($_POST['UnassignReason']) ? trim($_POST['UnassignReason']) : null;
    $otherReason = isset($_POST['OtherReason']) ? trim($_POST['OtherReason']) : null;
    $fkEmployees = intval($_POST['unassignToId']);
    $fkUsers = 0;
    $FK_AssetInventory = intval($_POST['FK_AssetInventory']);

    $updatedApprovers = getUpdatedApproverJsonWithStatus($conn);

    //Check first if the item has not assigned
    $sql = "SELECT AssignedTo FROM AssetMaster WHERE PK_AssetMaster = ?";
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("i", $assetId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            if ((int)$row['AssignedTo'] === 0) {
                // Already assigned – redirect and exit
                $stmt->close();
                header("Location: assets.php");
                exit;
            }
        }
    }
    //Check if there's already existing approval

    $sql = "SELECT 1 
            FROM assignapprovals 
            WHERE FK_AssetMaster = ? 
            AND FK_AssetInventory= ? 
            AND ApprovalType = 'Unassigned' 
            and IsApproved != 2
            order by PK_Approvals desc
            LIMIT 1";
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("ii", $assetId, $FK_AssetInventory);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // Row exists – Unassigned approval already pending
            $stmt->close();
            header("Location: assets.php");
            exit;
        }
    }

    $conn->begin_transaction();

    try {
        $stmtApproval = $conn->prepare("INSERT INTO AssignApprovals 
        (FK_AssetMaster, FK_Employees, ApprovalType, FK_Users, OtherReason, Reason, FK_AssetInventory, Approvers) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

        $stmtApproval->bind_param("iisissis", $assetId, $fkEmployees, $approvalType, $fkUsers, $otherReason, $reason, $FK_AssetInventory, $updatedApprovers);
        $stmtApproval->execute();
        $lastApprovalId = $conn->insert_id;
        $stmtApproval->close();

        $conn->commit();

        AssignApproval($lastApprovalId, $conn);

        $sql = "SELECT ax.AssetTagNumber, bx.Name
        FROM assetmaster ax
        LEFT JOIN employees bx ON ax.AssignedTo = bx.PK_Employees
        WHERE ax.PK_AssetMaster = ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $assetId); // assuming FK_AssetMaster is an integer
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $assetTag = $row['AssetTagNumber'];
            $employeeName = $row['Name'];

            $logDetails = "Requested to unassign asset $assetTag to $employeeName";
            $currentUser = $_SESSION['user_id'];
            $actionUser = "Unassign Asset";

            logActivity($conn, $currentUser, $actionUser, $logDetails);
        }

        header("Location: assets.php?AssetType=" . urlencode($selectedAssetType) .
            "&Status=" . urlencode($selectedStatus) .
            "&Condition=" . urlencode($selectedCondition) .
            "&status=unassigned");
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        // Handle error - maybe redirect with error message
        header("Location: assets.php?status=error&message=" . urlencode($e->getMessage()));
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['markRepaired'])) {
    $PK_AssetMaster = intval($_POST['AssetID']);
    $repairDate = $_POST['RepairDate'];
    $repairDetails = mysqli_real_escape_string($conn, $_POST['RepairDetails']);
    $repairedDate = $_POST['RepairedDate'];
    $repairedBy = $_POST['RepairedBy'];
    $repairValue = !empty($_POST['RepairValue']) ? floatval($_POST['RepairValue']) : null;
    $assignedTo = 0;
    $isRepaired = isset($_POST['isRepaired']) ? 1 : 0;

    $targetPath = null;
    if (isset($_FILES['ServiceOrder']) && $_FILES['ServiceOrder']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'image/serviceorder_image/';

        // Extract original file name & extension
        $originalName = basename($_FILES['ServiceOrder']['name']);
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);

        // Generate a unique filename
        $uniqueName = time() . '_' . uniqid() . '.' . $extension;
        $targetPath = $uploadDir . $uniqueName;

        // Move the file to the upload directory
        if (move_uploaded_file($_FILES['ServiceOrder']['tmp_name'], $targetPath)) {
            $serviceOrderImage = $uniqueName;
        }
    }


    $sql = "SELECT PK_AssetRepairedHistory FROM AssetRepairedHistory WHERE FK_AssetMaster = ? AND RepairedDate IS NULL";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $PK_AssetMaster);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {

        $row = $result->fetch_assoc();
        $repairHistoryId = $row['PK_AssetRepairedHistory'];

        if ($isRepaired  === 1) {
            $sql = "UPDATE AssetRepairedHistory SET RepairedDate = ?, RepairedBy = ?, Cost = ?, ServiceOrderImage = ? WHERE PK_AssetRepairedHistory = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssdsi", $repairedDate, $repairedBy, $repairValue, $targetPath, $repairHistoryId);
            $stmt->execute();

            $sql = "UPDATE AssetMaster SET `Conditions` = '4' WHERE PK_AssetMaster = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $PK_AssetMaster);
            $stmt->execute();
        }
    } else {

        $sql = "SELECT AssignedTo FROM AssetMaster WHERE PK_AssetMaster = ?";
        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("i", $PK_AssetMaster);
            $stmt->execute();

            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $assignedTo = $row['AssignedTo'];
            }
            $stmt->close();
        }

        if ($isRepaired  === 1) {
            // If device is repaired, insert all details
            $sql = "INSERT INTO AssetRepairedHistory 
                    (FK_AssetMaster, FK_Employees, RepairDate, RepairDetails, RepairedDate, RepairedBy, Cost, ServiceOrderImage)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iissssds", $PK_AssetMaster, $assignedTo, $repairDate, $repairDetails, $repairedDate, $repairedBy, $repairValue, $targetPath);
            $stmt->execute();

            $sql = "UPDATE AssetMaster SET `Conditions` = '4' WHERE PK_AssetMaster = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $PK_AssetMaster);
            $stmt->execute();
        } else {
            // Otherwise, insert partial repair info
            $sql = "INSERT INTO AssetRepairedHistory 
                    (FK_AssetMaster, FK_Employees, RepairDate, RepairDetails) 
                    VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iiss", $PK_AssetMaster, $assignedTo, $repairDate, $repairDetails);
            $stmt->execute();
        }
    }
    header("Location: assets.php?AssetType=" . urlencode($selectedAssetType) .
        "&Status=" . urlencode($selectedStatus) .
        "&Condition=" . urlencode($selectedCondition) .
        "&status=repaired");

    exit;
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
            ) as FK_AssetInventory,

            (
                SELECT
                    CASE
                        WHEN COUNT(*) = 0 THEN 0
                        ELSE 1
                    END
                FROM outboundassetslist ayy
                WHERE ayy.FK_AssetMaster = am.PK_AssetMaster AND ayy.isReturned is null
            ) AS isOutbound,

            (
                SELECT
                    CASE
                        WHEN COUNT(*) > 0 THEN 1
                        ELSE 0
                    END
                FROM transferassets abb
                LEFT JOIN transferassetslist acc
                    ON abb.PK_TransferAssets = acc.FK_TransferAssets
                WHERE acc.FK_AssetMaster = am.PK_AssetMaster
                AND abb.Status != 'Rejected'
            ) AS isTransferred


        FROM AssetMaster am
        LEFT JOIN AssetType e ON am.FK_AssetType = e.PK_AssetType

        WHERE am.IsArchived = 0 and am.Conditions != 7";

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
    <title>Assets</title>
    <link rel="icon" href="inventory.png" type="image/x-icon">
    <?php include "tools/head-plugin.php" ?>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="css/assets.css">

    <style>
        #map,
        #edit-map {
            width: 100%;
            height: 300px;
            border: 1px solid #ced4da;
            border-radius: 0.375rem;
            margin-top: 10px;
        }

        #suggestions,
        #edit-suggestions {
            position: absolute;
            z-index: 999;
            background: white;
            border-top: none;
            max-height: 150px;
            overflow-y: auto;
            width: 100%;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        #suggestions div,
        #edit-suggestions div {
            padding: 10px;
            cursor: pointer;
        }

        #suggestions div:hover,
        #edit-suggestions div:hover {
            background-color: #f8f9fa;
        }


        /* Animation Container */
        .loading-animation-container {
            position: relative;
            height: 120px;
            width: 100%;
            overflow: hidden;
        }

        /* Asset and Employee Icons */
        .asset-icon,
        .employee-icon {
            position: absolute;
            font-size: 4rem;
            bottom: 20px;
        }

        /* Employee Icon (Static) */
        .employee-icon {
            right: 15%;
            color: #495057;
            transition: color 0.3s ease;
        }

        .employee-icon.receiving {
            color: #198754;
            /* Green on receive */
            animation: pulse-icon 0.5s ease-in-out;
        }

        /* Asset Icon (Animated) */
        .asset-icon {
            color: #0d6efd;
            /* Blue for asset */
            animation: move-asset-to-employee 3s linear infinite;
        }

        /* Keyframe Animations */
        @keyframes move-asset-to-employee {
            0% {
                left: -15%;
                opacity: 0;
                transform: scale(0.8);
            }

            15% {
                left: 10%;
                opacity: 1;
                transform: scale(1);
            }

            70% {
                left: 60%;
                opacity: 1;
                transform: scale(1);
            }

            85% {
                left: 70%;
                opacity: 0;
                transform: scale(0.5);
            }

            100% {
                left: 70%;
                opacity: 0;
            }
        }

        @keyframes pulse-icon {
            0% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.2);
            }

            100% {
                transform: scale(1);
            }
        }


        /* Animation Container */
        .loading-animation-container {
            position: relative;
            height: 120px;
            width: 100%;
            overflow: hidden;
        }

        /* Icons */
        .asset-icon-unassign,
        .employee-icon-unassign,
        .storage-icon-unassign {
            position: absolute;
            font-size: 4rem;
            bottom: 20px;
        }

        /* Employee Icon (Static) */
        .employee-icon-unassign {
            left: 15%;
            color: #495057;
            transition: color 0.3s ease;
        }

        .employee-icon-unassign.sending {
            animation: pulse-send-icon 0.5s ease-in-out;
        }

        /* Storage Icon (Static) */
        .storage-icon-unassign {
            right: 15%;
            color: #495057;
        }

        /* Asset Icon (Animated) */
        .asset-icon-unassign {
            color: #dc3545;
            /* Red for unassign/return */
            animation: move-asset-from-employee 3s linear infinite;
        }

        /* Keyframe Animations */
        @keyframes move-asset-from-employee {
            0% {
                left: 25%;
                opacity: 0;
                transform: scale(0.5);
            }

            15% {
                left: 30%;
                opacity: 1;
                transform: scale(1);
            }

            85% {
                left: 65%;
                opacity: 1;
                transform: scale(1);
            }

            100% {
                left: 70%;
                opacity: 0;
                transform: scale(0.8);
            }
        }

        @keyframes pulse-send-icon {
            0% {
                transform: scale(1) translateX(0);
            }

            50% {
                transform: scale(1.1) translateX(-5px);
            }

            100% {
                transform: scale(1) translateX(0);
            }
        }
    </style>
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="container">
        <h2 class="mb-4">Asset Master</h2>
        <?php include 'modal.php'; ?>
        <!-- Top Right Action Buttons -->
        <div class="container mb-3">
            <div class="d-flex justify-content-end gap-2">

                <!-- Add Button -->
                <button type="button" class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#addAssetModal">
                    <i class="bi bi-plus-circle me-1"></i> Add
                </button>


                <!-- Import from Excel Button -->
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#importExcelModal">
                    <i class="bi bi-file-earmark-excel me-1"></i> Import Excel
                </button>


                <!-- Archive Button -->
                <a href="archive.php" class="btn btn-dark">
                    <i class="bi bi-archive me-1"></i> Archive
                </a>

            </div>
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

        <table id="myTable" class="table table-bordered table-hover table-striped table-responsive">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Asset Tag</th>
                    <th>Barcode</th>
                    <th>Serial No.</th>
                    <th>Asset Type</th>

                    <th>Condition</th>
                    <th>Image</th>
                    <th>Actions</th>

                </tr>
            </thead>
            <tbody>
                <?php
                $assetTags = []; // Initialize array to store asset tag numbers
                ?>

                <?php if (mysqli_num_rows($resultAsset) > 0): ?>
                    <?php while ($row = mysqli_fetch_assoc($resultAsset)): ?>
                        <?php $assetTags[] = $row['AssetTagNumber']; ?> <!-- Save tag number -->
                        <tr class="<?= ($row['isOutbound'] == 1 || $row['isTransferred'] == 1) ? 'table-danger' : '' ?>">
                            <td>AST-<?= htmlspecialchars($row['PK_AssetMaster']) ?>
                            </td>
                            <td><?= htmlspecialchars($row['AssetTagNumber']) ?></td>
                            <td>
                                <svg class="barcode"
                                    jsbarcode-format="CODE128"
                                    jsbarcode-value="<?= htmlspecialchars($row['AssetTagNumber']) ?>"
                                    jsbarcode-textmargin="0"
                                    jsbarcode-fontoptions="bold"
                                    jsbarcode-width="2"
                                    jsbarcode-height="50"
                                    onclick="saveBarcodeAsImage(this)">>
                                </svg>
                                <?php if ($row['isOutbound'] == 1): ?>
                                    <span class="badge bg-danger ms-2">Out Bounded</span>
                                <?php endif; ?>

                                <?php if ($row['isTransferred'] == 1): ?>
                                    <span class="badge bg-danger ms-2">Transferred</span>
                                <?php endif; ?>
                            </td>
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
                                <?php
                                if (htmlspecialchars($row['Conditions']) != '5' && htmlspecialchars($row['Conditions']) != '6') {
                                ?>
                                    <?php if (htmlspecialchars($row['IsApproved']) == 'na') {
                                    ?>
                                        <?php if ($row['AssignedTo'] == 0): ?>
                                            <a href="#"
                                                class="btn btn-sm btn-success me-1 btn-assign-asset"
                                                data-id="<?= $row['PK_AssetMaster'] ?>"
                                                data-assetTag="<?= $row['AssetTagNumber'] ?>"
                                                data-assetModel="<?= $row['Model'] ?>"
                                                data-bs-toggle="modal"
                                                data-bs-target="#assignAssetModal"
                                                data-asset='<?= json_encode($row) ?>'
                                                title="Assign">
                                                <i class="bi bi-person-plus-fill"></i>
                                            </a>
                                        <?php else: ?>
                                            <a href="#"
                                                class="btn btn-sm btn-danger me-1 btn-unassign-asset"
                                                data-id="<?= $row['PK_AssetMaster'] ?>"
                                                data-invID="<?= $row['FK_AssetInventory'] ?>"
                                                data-assetTag="<?= $row['AssetTagNumber'] ?>"
                                                data-assetModel="<?= $row['Model'] ?>"
                                                data-bs-toggle="modal"
                                                data-bs-target="#unassignAssetModal"
                                                data-asset='<?= json_encode($row) ?>'
                                                title="Unassign">
                                                <i class="bi bi-person-dash-fill"></i>
                                            </a>
                                        <?php endif; ?>

                                    <?php } else { ?>
                                        <?php if (htmlspecialchars($row['IsApproved']) == '0' && htmlspecialchars($row['ApprovalType']) == 'Unassigned') { ?>

                                            <!--  Pending Unassigned Approval Button -->
                                            <a href="#"
                                                class="btn btn-sm btn-warning me-1 btn-pending-status-unassign"
                                                data-id="<?= $row['PK_AssetMaster'] ?>"
                                                data-bs-toggle="modal"
                                                data-bs-target="#unassignApprovalAssetModal"
                                                data-asset='<?= json_encode($row) ?>'
                                                title="Pending : For Unassigned Approval">
                                                <i class="bi bi-hourglass-split"></i>
                                            </a>

                                        <?php } else ?>
                                        <?php if (htmlspecialchars($row['IsApproved']) == '0' && htmlspecialchars($row['ApprovalType']) == 'Assigned') { ?>

                                            <!--  Pending Assigned Approval Button -->
                                            <a href="#"
                                                class="btn btn-sm btn-warning me-1 btn-pending-status-assign"
                                                data-id="<?= $row['PK_AssetMaster'] ?>"
                                                data-bs-toggle="modal"
                                                data-bs-target="#assignApprovalAssetModal"
                                                data-asset='<?= json_encode($row) ?>'
                                                title="Pending : For Assigned Approval">
                                                <i class="bi bi-hourglass-split"></i>
                                            </a>
                                        <?php } else ?>

                                        <?php if (htmlspecialchars($row['IsApproved']) == '2' && htmlspecialchars($row['ApprovalType']) == 'Assigned') { ?>

                                            <a href="#"
                                                class="btn btn-sm btn-success me-1 btn-assign-asset"
                                                data-id="<?= $row['PK_AssetMaster'] ?>"
                                                data-assetTag="<?= $row['AssetTagNumber'] ?>"
                                                data-assetModel="<?= $row['Model'] ?>"
                                                data-bs-toggle="modal"
                                                data-bs-target="#assignAssetModal"
                                                data-asset='<?= json_encode($row) ?>'
                                                title="Assign">
                                                <i class="bi bi-person-plus-fill"></i>
                                            </a>

                                        <?php } else ?>

                                        <?php if (htmlspecialchars($row['IsApproved']) == '1' && htmlspecialchars($row['ApprovalType']) == 'Unassigned') { ?>

                                            <a href="#"
                                                class="btn btn-sm btn-success me-1 btn-assign-asset"
                                                data-id="<?= $row['PK_AssetMaster'] ?>"
                                                data-assetTag="<?= $row['AssetTagNumber'] ?>"
                                                data-assetModel="<?= $row['Model'] ?>"
                                                data-bs-toggle="modal"
                                                data-bs-target="#assignAssetModal"
                                                data-asset='<?= json_encode($row) ?>'
                                                title="Assign">
                                                <i class="bi bi-person-plus-fill"></i>
                                            </a>

                                        <?php } else ?>

                                        <?php if (htmlspecialchars($row['IsApproved']) == '1' && htmlspecialchars($row['ApprovalType']) == 'Assigned') { ?>

                                            <a href="#"
                                                class="btn btn-sm btn-danger me-1 btn-unassign-asset"
                                                data-id="<?= $row['PK_AssetMaster'] ?>"
                                                data-invID="<?= $row['FK_AssetInventory'] ?>"
                                                data-assetTag="<?= $row['AssetTagNumber'] ?>"
                                                data-assetModel="<?= $row['Model'] ?>"
                                                data-bs-toggle="modal"
                                                data-bs-target="#unassignAssetModal"
                                                data-asset='<?= json_encode($row) ?>'
                                                title="Unassign">
                                                <i class="bi bi-person-dash-fill"></i>
                                            </a>

                                        <?php } else ?>

                                        <?php if (htmlspecialchars($row['IsApproved']) == '2' && htmlspecialchars($row['ApprovalType']) == 'Unassigned') { ?>

                                            <a href="#"
                                                class="btn btn-sm btn-danger me-1 btn-unassign-asset"
                                                data-id="<?= $row['PK_AssetMaster'] ?>"
                                                data-invID="<?= $row['FK_AssetInventory'] ?>"
                                                data-assetTag="<?= $row['AssetTagNumber'] ?>"
                                                data-assetModel="<?= $row['Model'] ?>"
                                                data-bs-toggle="modal"
                                                data-bs-target="#unassignAssetModal"
                                                data-asset='<?= json_encode($row) ?>'
                                                title="Unassign">
                                                <i class="bi bi-person-dash-fill"></i>
                                            </a>


                                        <?php } else ?>

                                    <?php } ?>

                                <?php
                                } else {
                                ?>

                                    <a href="#"
                                        class="btn btn-sm btn-info me-1 btn-repaired-asset"
                                        data-bs-toggle="modal"
                                        data-bs-target="#repairedAssetModal"
                                        data-id="<?= $row['PK_AssetMaster'] ?>"
                                        data-assettag="<?= htmlspecialchars($row['AssetTagNumber']) ?>"
                                        title="Mark as Repaired">
                                        <i class="bi bi-wrench-adjustable-circle"></i>
                                    </a>


                                <?php } ?>


                                <a href="#"
                                    class="btn btn-sm btn-primary me-1 btn-view-asset"
                                    data-bs-toggle="modal"
                                    data-bs-target="#viewAssetModal"
                                    data-asset='<?= json_encode($row) ?>'
                                    title="View">
                                    <i class="bi bi-eye-fill"></i>
                                </a>

                                <a href="#"
                                    class="btn btn-sm btn-warning editAssetBtn"
                                    data-bs-toggle="modal"
                                    data-bs-target="#editAssetModal"
                                    data-asset='<?= json_encode($row) ?>'
                                    title="Edit">
                                    <i class="bi bi-pencil-fill"></i>
                                </a>

                                <?php if ($row['AssignedTo'] == 0): ?>
                                    <a href="#"
                                        class="btn btn-sm btn-danger btn-delete-asset"
                                        data-bs-toggle="modal"
                                        data-bs-target="#deleteAssetModal"
                                        data-id="<?= $row['PK_AssetMaster'] ?>"
                                        data-assetTag="<?= $row['AssetTagNumber'] ?>"
                                        data-assetModel="<?= $row['Model'] ?>"
                                        title="Move to Archived">
                                        <i class="bi bi-archive-fill"></i>
                                    </a>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-danger" disabled title="Cannot archive while assigned">
                                        <i class="bi bi-trash-fill"></i>
                                    </button>
                                <?php endif; ?>

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

    <!-- Assigning Asset Loading Modal -->
    <div class="modal fade" id="assignLoadingModal" tabindex="-1" aria-labelledby="assignLoadingModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="assignLoadingModalLabel">
                        Processing Assignment...
                    </h5>
                </div>
                <div class="modal-body text-center p-4">
                    <div class="loading-animation-container mb-3">
                        <i class="bi bi-box-seam-fill asset-icon"></i>
                        <i class="bi bi-person-fill employee-icon"></i>
                    </div>
                    <p class="mb-0 text-muted">Please wait while the asset is being assigned.</p>
                    <div class="progress mt-3" role="progressbar" aria-label="Animated striped example" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100" style="height: 5px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 100%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- Unassigning Asset Loading Modal -->
    <div class="modal fade" id="unassignLoadingModal" tabindex="-1" aria-labelledby="unassignLoadingModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="unassignLoadingModalLabel">
                        Processing Unassignment...
                    </h5>
                </div>
                <div class="modal-body text-center p-4">
                    <div class="loading-animation-container mb-3">
                        <i class="bi bi-person-fill employee-icon-unassign"></i>
                        <i class="bi bi-box-seam-fill asset-icon-unassign"></i>
                        <i class="bi bi-hdd-stack-fill storage-icon-unassign"></i>
                    </div>
                    <p class="mb-0 text-muted">Please wait while the asset is being returned to storage.</p>
                    <div class="progress mt-3" role="progressbar" aria-label="Animated striped example" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100" style="height: 5px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-danger" style="width: 100%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Import Excel Modal -->
    <div class="modal fade" id="importExcelModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <form method="POST" id="importExcelForm" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Import Excel</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- Upload -->
                    <div class="mb-3">
                        <label for="excelFile" class="form-label">Upload Excel File</label>
                        <input type="file" id="excelFile" class="form-control" accept=".xlsx,.xls">
                    </div>

                    <!-- Preview Area -->
                    <div id="previewArea" class="table-responsive">
                        <p class="text-muted">No file uploaded yet.</p>
                    </div>

                    <!-- Hidden JSON Input -->
                    <input type="hidden" name="excelData" id="excelData">
                </div>
                <div class="modal-footer">
                    <button type="submit" id="confirmImport" name="importFromExcel" class="btn btn-primary" disabled>Confirm Import</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </form>
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
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <?php include "tools/alert-message.php"; ?>
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>

    <script src="script.js"></script>

    <script>
        JsBarcode(".barcode").init();
    </script>

    <script>
        document.getElementById('excelFile').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = function(evt) {
                const data = new Uint8Array(evt.target.result);
                const workbook = XLSX.read(data, {
                    type: 'array'
                });

                // Get first sheet
                const sheet = workbook.Sheets[workbook.SheetNames[0]];
                const json = XLSX.utils.sheet_to_json(sheet, {
                    header: 1
                });

                // Build preview table
                let html = '<table class="table table-bordered">';
                json.forEach((row, i) => {
                    html += '<tr>';
                    row.forEach(cell => {
                        if (i === 0) {
                            html += `<th>${cell || ''}</th>`;
                        } else {
                            html += `<td>${cell || ''}</td>`;
                        }
                    });
                    html += '</tr>';
                });
                html += '</table>';

                document.getElementById('previewArea').innerHTML = html;
                document.getElementById('confirmImport').disabled = false;

                // Save parsed data to hidden input
                document.getElementById('excelData').value = JSON.stringify(json);
            };
            reader.readAsArrayBuffer(file);
        });
    </script>

    <!--------------- Check if Asset Tag number already exist ------------------------------>
    <script>
        const existingAssetTags = <?= json_encode($assetTags) ?>; // From PHP
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const input = document.getElementById('AddAssetTagNumber');

            input.addEventListener('input', function() {
                const value = input.value.trim().toUpperCase(); // normalize
                const isDuplicate = existingAssetTags.some(tag => tag.toUpperCase() === value);

                // Remove old message if any
                const oldMsg = document.getElementById('assetTagWarning');
                if (oldMsg) oldMsg.remove();

                if (isDuplicate) {
                    const warning = document.createElement('div');
                    warning.id = 'assetTagWarning';
                    warning.className = 'text-danger mt-1';
                    warning.innerText = '⚠️ This Asset Tag Number already exists.';
                    input.insertAdjacentElement('afterend', warning);
                }
            });
        });
    </script>
    <!--------------------------------------------------------------------------------------->


    <!-- Save Barcode as Image -->
    <script>
        function saveBarcodeAsImage(svgElement) {
            const serializer = new XMLSerializer();
            const svgString = serializer.serializeToString(svgElement);

            const canvas = document.createElement("canvas");
            const ctx = canvas.getContext("2d");

            const img = new Image();
            const svgBlob = new Blob([svgString], {
                type: "image/svg+xml;charset=utf-8"
            });
            const url = URL.createObjectURL(svgBlob);

            img.onload = function() {
                canvas.width = img.width;
                canvas.height = img.height;
                ctx.drawImage(img, 0, 0);
                URL.revokeObjectURL(url);

                // Trigger download
                const a = document.createElement("a");
                a.download = (svgElement.getAttribute("jsbarcode-value") || "barcode") + ".png";
                a.href = canvas.toDataURL("image/png");
                a.click();
            };

            img.src = url;
        }
    </script>

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
        document.getElementById('addAssetForm').addEventListener('submit', function(e) {
            showLoader();
        });

        document.getElementById('repairedAssetForm').addEventListener('submit', function(e) {
            showLoader();
        });

        document.getElementById('importExcelForm').addEventListener('submit', function(e) {
            showLoader();
        });
    </script>

    <!-- DataTable-->
    <script>
        let scannedBarcode = '';
        let scanTimeout;

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

            // Listen for fast input (scanner-like)
            $(document).on('keydown', function(e) {
                // Skip special keys
                if (e.key.length === 1) {
                    scannedBarcode += e.key;

                    clearTimeout(scanTimeout);

                    // Set timeout to detect end of scan
                    scanTimeout = setTimeout(() => {
                        if (scannedBarcode.length > 3) {
                            table.search('').draw(); // Clear old search
                            table.search(scannedBarcode).draw();
                        }
                        scannedBarcode = '';
                    }, 100); // 100ms delay: if user pauses, assume scan is done
                }
            });
        });
    </script>

    <!--Preview Image -->
    <script>
        // Preview image on file select
        // --- Image Preview Logic ---
        const imageInput = document.getElementById('assetImageInput');
        const imagePreview = document.getElementById('assetImagePreview');
        const imagePlaceholder = document.getElementById('assetImagePlaceholder');

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

        const editAssetModal = document.getElementById('editAssetModal');

        // --- Image Preview Logic for Edit Modal ---
        const editImageInput = document.getElementById('editAssetImageInput');
        const editImagePreview = document.getElementById('editAssetImagePreview');
        const editImagePlaceholder = document.getElementById('editAssetImagePlaceholder');

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


    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        const defaultLat = 14.5995,
            defaultLng = 120.9842;
        const map = L.map('map').setView([defaultLat, defaultLng], 13);
        const marker = L.marker([defaultLat, defaultLng], {
            draggable: true
        }).addTo(map);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        document.getElementById('latitude').value = defaultLat;
        document.getElementById('longitude').value = defaultLng;

        // Update lat/lng when marker dragged
        marker.on('dragend', function(e) {
            const latlng = marker.getLatLng();
            document.getElementById('latitude').value = latlng.lat;
            document.getElementById('longitude').value = latlng.lng;
        });

        // Address autocomplete
        const addressInput = document.getElementById("address");
        const suggestionsBox = document.getElementById("suggestions");

        addressInput.addEventListener("input", function() {
            const query = addressInput.value;
            if (query.length < 3) {
                suggestionsBox.innerHTML = "";
                return;
            }

            fetch(`https://photon.komoot.io/api/?q=${encodeURIComponent(query)}&limit=5`)
                .then(response => response.json())
                .then(data => {
                    suggestionsBox.innerHTML = "";
                    data.features.forEach((place) => {
                        const div = document.createElement("div");
                        div.className = "autocomplete-suggestion";
                        div.textContent = place.properties.name + ", " + (place.properties.city || '') + " " + (place.properties.country || '');
                        div.addEventListener("click", () => {
                            const lat = place.geometry.coordinates[1];
                            const lon = place.geometry.coordinates[0];
                            addressInput.value = div.textContent;
                            document.getElementById('latitude').value = lat;
                            document.getElementById('longitude').value = lon;
                            map.setView([lat, lon], 16);
                            marker.setLatLng([lat, lon]);
                            suggestionsBox.innerHTML = "";
                        });
                        suggestionsBox.appendChild(div);
                    });
                });
        });

        // Hide suggestions on outside click
        document.addEventListener("click", function(e) {
            if (!addressInput.contains(e.target) && !suggestionsBox.contains(e.target)) {
                suggestionsBox.innerHTML = "";
            }
        });
    </script>

    <script>
        // Fix for map not rendering properly inside Bootstrap modal
        const assetModal = document.getElementById('addAssetModal');
        assetModal.addEventListener('shown.bs.modal', function() {
            setTimeout(() => {
                map.invalidateSize(); // Recalculate dimensions after modal is visible
            }, 200); // Delay ensures rendering completes
        });
    </script>

    <script>
        let editMap, editMarker;
        let isEditMapInit = false;

        document.querySelectorAll('.editAssetBtn').forEach(btn => {
            btn.addEventListener('click', function() {
                const assetData = JSON.parse(this.getAttribute('data-asset'));

                // Fill the form fields with data
                document.getElementById('edit-address').value = assetData.address || '';
                document.getElementById('edit-latitude').value = assetData.latitude || 14.5995;
                document.getElementById('edit-longitude').value = assetData.longitude || 120.9842;
            });
        });

        document.getElementById('editAssetModal').addEventListener('shown.bs.modal', function() {
            const lat = parseFloat(document.getElementById('edit-latitude').value) || 14.5995;
            const lng = parseFloat(document.getElementById('edit-longitude').value) || 120.9842;

            if (!isEditMapInit) {
                editMap = L.map('edit-map').setView([lat, lng], 13);
                editMarker = L.marker([lat, lng], {
                    draggable: true
                }).addTo(editMap);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; OpenStreetMap contributors'
                }).addTo(editMap);

                editMarker.on('dragend', function() {
                    const pos = editMarker.getLatLng();
                    document.getElementById('edit-latitude').value = pos.lat;
                    document.getElementById('edit-longitude').value = pos.lng;
                });

                isEditMapInit = true;
            } else {
                editMarker.setLatLng([lat, lng]);
                editMap.setView([lat, lng], 13);
            }

            setTimeout(() => {
                editMap.invalidateSize();
            }, 200);
        });
    </script>


    <script>
        const editAddressInput = document.getElementById("edit-address");
        const editSuggestionsBox = document.getElementById("edit-suggestions");

        editAddressInput.addEventListener("input", function() {
            const query = editAddressInput.value.trim();

            if (query.length < 3) {
                editSuggestionsBox.innerHTML = "";
                return;
            }

            fetch(`https://photon.komoot.io/api/?q=${encodeURIComponent(query)}&limit=5`)
                .then(response => response.json())
                .then(data => {
                    editSuggestionsBox.innerHTML = "";

                    data.features.forEach(place => {
                        const div = document.createElement("div");
                        div.className = "autocomplete-suggestion";
                        div.textContent = `${place.properties.name}, ${place.properties.city || ''} ${place.properties.country || ''}`;

                        div.addEventListener("click", () => {
                            const lat = place.geometry.coordinates[1];
                            const lon = place.geometry.coordinates[0];

                            editAddressInput.value = div.textContent;
                            document.getElementById('edit-latitude').value = lat;
                            document.getElementById('edit-longitude').value = lon;

                            if (editMap && editMarker) {
                                editMap.setView([lat, lon], 16);
                                editMarker.setLatLng([lat, lon]);
                            }

                            editSuggestionsBox.innerHTML = "";
                        });

                        editSuggestionsBox.appendChild(div);
                    });
                });
        });

        // Hide suggestions on outside click
        document.addEventListener("click", function(e) {
            if (!editAddressInput.contains(e.target) && !editSuggestionsBox.contains(e.target)) {
                editSuggestionsBox.innerHTML = "";
            }
        });
    </script>

    <!-- Validate first if Assign has value-->
    <script>
        document.getElementById('assignForm').addEventListener('submit', function(e) {
            const assignToTextValidation = document.getElementById('assignToText');

            if (!assignToTextValidation.value.trim()) {
                assignToTextValidation.classList.add('is-invalid'); // Add highlight
                assignToTextValidation.focus();
                e.preventDefault(); // Prevent form submission
            } else {
                assignToTextValidation.classList.remove('is-invalid'); // Clean up if valid
            }
            const modal = bootstrap.Modal.getInstance(document.getElementById('assignAssetModal'));
            if (modal) {
                modal.hide();
            }


            const assignModal = new bootstrap.Modal(document.getElementById('assignLoadingModal'));
            const startBtn = document.getElementById('startAssignmentBtn');
            const employeeIcon = document.querySelector('.employee-icon');

            assignModal.show();


            const animationDuration = 3000; // Must match the CSS animation duration (3s)
            const pulsePoint = animationDuration * 0.8; // 80% through the animation

            function triggerPulse() {
                setTimeout(() => {
                    employeeIcon.classList.add('receiving');
                    setTimeout(() => {
                        employeeIcon.classList.remove('receiving');
                    }, 500); // Duration of the pulse animation
                }, pulsePoint);
            }

            // Set up an interval to match the CSS animation
            let pulseInterval;
            const modalElement = document.getElementById('assignLoadingModal');

            modalElement.addEventListener('shown.bs.modal', () => {
                triggerPulse(); // Trigger immediately on show
                pulseInterval = setInterval(triggerPulse, animationDuration);
            });

            modalElement.addEventListener('hidden.bs.modal', () => {
                clearInterval(pulseInterval); // Clean up the interval when the modal is hidden
            });

        });
    </script>

    <script>
        document.getElementById('UnassignForm').addEventListener('submit', function(e) {
            const modal = bootstrap.Modal.getInstance(document.getElementById('unassignAssetModal'));
            if (modal) {
                modal.hide();
            }

            const unassignModal = new bootstrap.Modal(document.getElementById('unassignLoadingModal'));
            const startBtn = document.getElementById('startUnassignmentBtn');
            const employeeIcon = document.querySelector('.employee-icon-unassign');

            unassignModal.show();

            // --- Logic for icon animation ---
            const animationDuration = 3000; // Must match the CSS animation duration (3s)
            const pulsePoint = animationDuration * 0.1; // 10% through the animation

            function triggerPulse() {
                setTimeout(() => {
                    employeeIcon.classList.add('sending');
                    setTimeout(() => {
                        employeeIcon.classList.remove('sending');
                    }, 500); // Duration of the pulse animation
                }, pulsePoint);
            }

            // Set up an interval to match the CSS animation
            let pulseInterval;
            const modalElement = document.getElementById('unassignLoadingModal');

            modalElement.addEventListener('shown.bs.modal', () => {
                triggerPulse(); // Trigger immediately on show
                pulseInterval = setInterval(triggerPulse, animationDuration);
            });

            modalElement.addEventListener('hidden.bs.modal', () => {
                clearInterval(pulseInterval); // Clean up the interval when the modal is hidden
            });
        });
    </script>
</body>

<?php
$conn->close();
?>

</html>