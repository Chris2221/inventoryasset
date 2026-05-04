<?php
require '../config.php';

header('Content-Type: application/json');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id > 0) {
    $sql = "SELECT * FROM AssetRepairedHistory WHERE FK_AssetMaster = ? AND RepairedDate IS NULL ORDER BY PK_AssetRepairedHistory DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        echo json_encode([
            'success' => true,
            'record' => $row
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No record found']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
}
?>
