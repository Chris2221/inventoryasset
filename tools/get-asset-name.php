<?php
require '../config.php';

$type = $_GET['type'] ?? '';
$id = intval($_GET['id'] ?? 0);
$name = '';

if ($type === 'AM') {
    $stmt = $conn->prepare("SELECT AssetTagNumber FROM AssetMaster WHERE PK_AssetMaster = ?");
} else {
    $stmt = $conn->prepare("SELECT Name FROM GeneralAssetMaster WHERE GeneralAssetMaster = ?");
}
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

if ($type === 'AM') {
    echo $data['AssetTagNumber'] ?? 'N/A';
} else {
    echo $data['Name'] ?? 'N/A';
}
