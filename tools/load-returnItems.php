<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

include "../config.php";

$outboundId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$query = "
    SELECT
        CASE WHEN ax.FK_AssetMaster = 0 THEN 'GAM' ELSE 'AM' END AS Type,
        CASE WHEN ax.FK_AssetMaster = 0 THEN '' ELSE AM.SerialNumber END AS SerialNumber,
        COALESCE(AM.AssetTagNumber, GAM.Name) AS Name,
        CASE
            WHEN ax.FK_AssetMaster > 0 THEN ax.FK_AssetMaster
            ELSE ax.FK_GeneralAssetMaster
        END AS ID,
        ax.Quantity AS Quantity,
        COALESCE(AT_AM.AssetTypeName, AT_GAM.AssetTypeName) AS AssetType,
        ax.isReturned,
        ax.QuantityReceived,
        ax.PK_OutboundAssetsList,
        ax.ReturnedDate
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
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode($data);

$stmt->close();
$conn->close();

exit;
?>