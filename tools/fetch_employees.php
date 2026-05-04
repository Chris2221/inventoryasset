<?php
require_once '../config.php';

$selectedEmpId = isset($_GET['selectedEmpId']) ? intval($_GET['selectedEmpId']) : 0;

$excludedArray = [];
$resultemp = mysqli_query($conn, "SELECT FK_Employees FROM Users WHERE FK_Employees IS NOT NULL AND FK_Employees != 0 AND Status = 1");

while ($row = mysqli_fetch_assoc($resultemp)) {
    $excludedArray[] = $row['FK_Employees'];
}

$placeholders = implode(',', array_fill(0, count($excludedArray), '?'));

$query = "
    SELECT PK_Employees, EmployeeID, Name
    FROM Employees
    WHERE (Status = 'Active' " .
    (count($excludedArray) ? "AND PK_Employees NOT IN ($placeholders)" : "") .
    ") OR PK_Employees = $selectedEmpId
    ORDER BY Name ASC";

$stmt = $conn->prepare($query);
if (count($excludedArray)) {
    $stmt->bind_param(str_repeat('i', count($excludedArray)), ...$excludedArray);
}

$stmt->execute();
$result = $stmt->get_result();

$employees = [];
while ($row = $result->fetch_assoc()) {
    $employees[] = $row;
}

header('Content-Type: application/json');
echo json_encode($employees);
?>
