<?php
require '../config.php'; // DB connection

$assetId = intval($_GET['asset_id'] ?? 0);
$data = [];

if ($assetId > 0) {
    $sql = "
        SELECT e.Name AS employee, h.Quantity, h.CreatedOn, f.Name
        FROM GeneralAssetHistory h
        JOIN Employees e ON e.PK_Employees = h.FK_Employees
        LEFT JOIN Users f ON f.PK_Users = h.FK_Users
        WHERE h.FK_GeneralAssetMaster = ?
        ORDER BY h.CreatedOn DESC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $assetId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'employee' => $row['employee'],
            'quantity' => $row['Quantity'],
            'date' => date('Y-m-d H:i', strtotime($row['CreatedOn'])),
            'name' => $row['Name'],
            
        ];
    }

    $stmt->close();
}

header('Content-Type: application/json');
echo json_encode($data);
