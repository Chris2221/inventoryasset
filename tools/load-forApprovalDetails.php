<?php
// Include database connection
include '../config.php'; // adjust this to your actual DB connection file

// Get the FK_AssetMaster from GET or POST
$assetId = isset($_GET['id']) ? intval($_GET['id']) : (isset($_POST['id']) ? intval($_POST['id']) : 0);
$invID = isset($_GET['invId']) ? intval($_GET['invId']) : (isset($_POST['invId']) ? intval($_POST['invId']) : 0);

if ($assetId <= 0) {
    echo json_encode(['error' => 'Invalid Asset ID']);
    exit;
}


// Prepare SQL
$sql = "
SELECT 
    bx.Name AS AssignedToName,
    ax.Location,
    ax.DateAcquired,
    ax.Conditions,
    ax.Remarks,
    ax.Image
FROM assetinventory ax
LEFT JOIN employees bx ON ax.AssignedTo = bx.PK_Employees
LEFT JOIN assignapprovals cx ON cx.FK_AssetMaster = ax.FK_AssetMaster
WHERE cx.PK_Approvals = ? AND ax.PK_AssetInventory = ?
";

// Execute query
$stmt = $conn->prepare($sql);
$stmt->bind_param('ii', $assetId, $invID);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode($row);
} else {
    echo json_encode(['error' => 'No data found']);
}

$stmt->close();
$conn->close();
?>
