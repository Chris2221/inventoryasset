<?php
header('Content-Type: application/json');
require '../config.php'; // your DB connection file

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing asset ID']);
    exit;
}

$assetId = intval($_GET['id']);
$invId = intval($_GET['invId']);

$sql = "SELECT ai.*, u.Name AS AssignedToName, ai.PK_AssetInventory
        FROM AssetInventory ai
        LEFT JOIN Employees u ON ai.AssignedTo = u.PK_Employees
        WHERE ai.FK_AssetMaster = ? and ai.PK_AssetInventory = ?";  // Get the latest assignment record

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $assetId, $invId);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode(['success' => true, 'asset' => $row]);
} else {
    // No inventory record found for asset, return defaults or error
    echo json_encode(['success' => false, 'message' => 'No assignment record found for this asset']);
}
