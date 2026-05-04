<?php
require '../config.php'; // your DB config

$assetId = intval($_GET['id'] ?? 0);

$sql = "
    SELECT 
        ax.Name AS EmployeeName,
        dx.AssetTagNumber,
        bx.Reason,
        bx.OtherReason,
        bx.CreatedOn
    FROM employees ax
    LEFT JOIN assignapprovals bx ON ax.PK_Employees = bx.FK_Employees
    LEFT JOIN assetinventory cx ON bx.FK_AssetMaster = cx.PK_AssetInventory
    LEFT JOIN assetmaster dx ON bx.FK_AssetMaster = dx.PK_AssetMaster
    WHERE bx.FK_AssetMaster = $assetId
    ORDER BY bx.CreatedOn DESC
    LIMIT 1
";

$result = $conn->query($sql);
$data = $result->fetch_assoc();

echo json_encode($data);
