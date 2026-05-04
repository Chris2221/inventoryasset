<?php include '../config.php'; ?>
<?php 
$data = $_GET['data'] ?? '[]'; 
$decoded = json_decode($data, true);

$gam_ids = [];
$am_ids = [];

foreach ($decoded as $item) {
    if ($item['type'] === 'GAM') {
        $gam_ids[] = $item['id'];
    } elseif ($item['type'] === 'AM') {
        $am_ids[] = $item['id'];
    }
}

// Convert arrays to comma-separated strings
$gam = !empty($gam_ids) ? implode(',', $gam_ids) : '0';
$am = !empty($am_ids) ? implode(',', $am_ids) : '0';
?>

<table class="table table-bordered table-hover" id="productTable">
    <thead>
        <tr>
            <th>Type</th>
            <th>ID</th>
            <th>Name</th>
            <th>Quantity</th>
            <th>Asset Type</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $sql = "

            select * from(
              SELECT 
                    'IT' AS Type, 
                    AM.PK_AssetMaster AS ID, 
                    CASE 
                        WHEN EXISTS (
                            SELECT 1 
                            FROM OutboundAssetsList 
                            WHERE FK_AssetMaster = AM.PK_AssetMaster and IsReturned is null
                        ) THEN 0
                        ELSE 1
                    END AS Quantity, 
                    AT.AssetTypeName,
                    AM.AssetTagNumber as Name
                FROM AssetMaster AM
                JOIN AssetType `AT` ON AM.FK_AssetType = AT.PK_AssetType
                WHERE PK_AssetMaster not in($am)
                AND AM.Conditions not in (5,6)

                UNION

                SELECT 
                    'GAM' AS Type, 
                    GAM.GeneralAssetMaster AS ID, 
                    
                    
                    GAM.Quantity - IFNULL((
                        SELECT SUM(Quantity)
                        FROM OutboundAssetsList
                        WHERE FK_GeneralAssetMaster = GAM.GeneralAssetMaster
                        AND IsReturned is null
                    ), 0) AS Quantity, 
                    
                    
                    AT.AssetTypeName, 
                    GAM.Name
                FROM GeneralAssetMaster GAM
                JOIN AssetType `AT` ON GAM.FK_AssetType = AT.PK_AssetType
                WHERE GAM.Quantity > 0
                and GeneralAssetMaster not in($gam)
                
                ) as ax
                where ax.Quantity > 0 order by ax.Type desc
            ";
        $resultItems = mysqli_query($conn, $sql);
        while ($row = mysqli_fetch_assoc($resultItems)) {
            echo "<tr>
                      <td>{$row['Type']}</td>
                      <td>{$row['ID']}</td>
                      <td>{$row['Name']}</td>
                      <td>{$row['Quantity']}</td>
                      <td>{$row['AssetTypeName']}</td>
                      <td><button class='btn btn-sm btn-primary select-product-btn' 
                          data-type='{$row['Type']}' 
                          data-id='{$row['ID']}' 
                          data-name='{$row['Name']}' 
                          data-assetname='{$row['AssetTypeName']}' 
                          data-qty='{$row['Quantity']}'>Select</button></td>
                    </tr>";
        }
        ?>
    </tbody>
</table>


