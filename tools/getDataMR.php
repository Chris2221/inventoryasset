<?php
include '../config.php'; //
// SQL query
$employeeId = intval($_GET['id']);

$sql = "
    SELECT 
        *,
        ax.AssetTagNumber AS Description, 
        ax.BrandManufacturer,
        ax.SerialNumber,
        ax.PurchasePrice,
        ax.PurchasePrice AS TotalCost,
        bx.DateAcquired
    FROM assetmaster ax
    LEFT JOIN assetinventory bx
        ON ax.PK_AssetMaster = bx.FK_AssetMaster
    WHERE bx.AssignStatus = 1 
        AND ax.AssignedTo = ?
        AND bx.AssignedTo = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $employeeId, $employeeId); // 'ii' = two integers
$stmt->execute();
$result = $stmt->get_result();

$tableData = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $tableData[] = [
            "item" => $row['AssetTagNumber'] ?? '',
            "qty" => "1", // Assuming qty is 1 per row, modify if you have a quantity column
            "uom" => "pcs", // Or dynamically use $row['UOM'] if it exists
            "description" => $row['Description'] ?? '',
            "brand" => $row['BrandManufacturer'] ?? '',
            "serial" => $row['SerialNumber'] ?? '',
            "unitCost" => $row['PurchasePrice'] ?? '',
            "totalCost" => $row['TotalCost'] ?? '',
            "dateAcquired" => $row['DateAcquired'] ?? ''
        ];
    }
}

// Output JSON
header('Content-Type: application/json');
echo json_encode(["tableData" => $tableData], JSON_PRETTY_PRINT);

$conn->close();
?>
