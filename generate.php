<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include "config.php";

$createdBy = $_SESSION['user_id'];

$outboundId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$query = "
    SELECT
        CASE
            WHEN ax.FK_AssetMaster = 0 THEN 'GAM'
            ELSE 'AM'
        END AS Type,

        CASE
            WHEN ax.FK_AssetMaster = 0 THEN ''
            ELSE AM.SerialNumber
        END AS SerialNumber,

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
                    FROM OutboundAssetsList
                    WHERE FK_GeneralAssetMaster = ax.FK_GeneralAssetMaster
                      AND IsReturned = 0
                ), 0)
        END AS CurrentStock,
        ax.Quantity AS Quantity,
        COALESCE(AT_AM.AssetTypeName, AT_GAM.AssetTypeName) AS AssetType
    FROM OutboundAssetsList ax
    LEFT JOIN AssetMaster AM ON ax.FK_AssetMaster = AM.PK_AssetMaster
    LEFT JOIN GeneralAssetMaster GAM ON ax.FK_GeneralAssetMaster = GAM.GeneralAssetMaster
    LEFT JOIN AssetType AT_AM ON AM.FK_AssetType = AT_AM.PK_AssetType
    LEFT JOIN AssetType AT_GAM ON GAM.FK_AssetType = AT_GAM.PK_AssetType
    WHERE ax.FK_OutboundAssets = ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $outboundId);
$stmt->execute();
$result2 = $stmt->get_result();

$id = $outboundId;

// Fetch outbound asset
$stmt = $conn->prepare("SELECT * FROM OutboundAssets WHERE PK_OutboundAssets = ?");
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

function getEmployeeDepartment($conn, $empId)
{
    if (!$empId) return '';
    $stmt = $conn->prepare("SELECT Department FROM Employees WHERE PK_Employees = ?");
    $stmt->bind_param("i", $empId);
    $stmt->execute();
    $result = $stmt->get_result();
    $emp = $result->fetch_assoc();
    return $emp['Department'] ?? '';
}

function getEmployeeContact($conn, $empId)
{
    if (!$empId) return '';
    $stmt = $conn->prepare("SELECT PhoneNumber FROM Employees WHERE PK_Employees = ?");
    $stmt->bind_param("i", $empId);
    $stmt->execute();
    $result = $stmt->get_result();
    $emp = $result->fetch_assoc();
    return $emp['PhoneNumber'] ?? '';
}


$fkUserId = $outbound['FK_Users'] ?? '';

$department = getEmployeeDepartment($conn, $fkUserId);
$contact = getEmployeeContact($conn, $fkUserId);

$requestedByName = getEmployeeName($conn, $requestedById);
$approvedByName = getEmployeeName($conn, $approvedById);
$itDeptName = getEmployeeName($conn, $itDeptId);
$employeeName = getEmployeeName($conn, $fkUserId);

$description = $outbound['Descriptions'] ?? '';
$imagePath = $outbound['Image'] ?? '';
$dateAcquired = $outbound['DateAcquired'] ?? '';
$purpose = $outbound['Descriptions'] ?? '';


$status = $outbound['Status'] ?? '';
$conn->close();

$data = [];

while ($row = $result2->fetch_assoc()) {
    $data[] = [
        'description' => $row['Name'],
        'serial' => $row['SerialNumber'],
        'quantity' => $row['Quantity']
    ];
}

header('Content-Type: application/json');
echo json_encode([
    'data' => $data,
    'date' => $dateAcquired,
    'name' => $employeeName,
    'department' => $department,
    'contact' => $contact,
    'purpose' => $purpose,
]);
exit;
?>
