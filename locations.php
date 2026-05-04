<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include "config.php";

$createdBy = $_SESSION['user_id'];

$result = $conn->query("SELECT * FROM AssetMaster WHERE latitude IS NOT NULL AND longitude IS NOT NULL AND IsArchived = 0");

if (!$result) {
    die("Query error: " . $conn->error);
}

$items = [];
while ($row = $result->fetch_assoc()) {
    $items[] = $row;
}

$assetTypeQuery = "SELECT PK_AssetType, AssetTypeName FROM AssetType where Category = 0 ORDER BY AssetTypeName ASC";
$assetTypeResult = mysqli_query($conn, $assetTypeQuery);

$assetTypes = [];
$assetTypeMap = [];
if ($assetTypeResult && mysqli_num_rows($assetTypeResult) > 0) {
    while ($row = mysqli_fetch_assoc($assetTypeResult)) {
        $assetTypes[] = $row;
        $assetTypeMap[$row['PK_AssetType']] = $row['AssetTypeName'];
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asset Locations</title>
    <link rel="icon" href="inventory.png" type="image/x-icon">
    <?php include "tools/head-plugin.php" ?>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster/dist/MarkerCluster.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster/dist/MarkerCluster.Default.css">
    <link rel="stylesheet" href="css/location.css">
</head>

<body>
    <?php include 'header.php'; ?>
    <div id="mainContainer">
        <div id="sidebar">

            <select id="filter"></select>


            <input type="text" id="searchInput" placeholder="Search Asset Tag...">
            <div id="itemList">
                <?php foreach ($items as $item): ?>
                    <div class="item" data-id="<?= $item['PK_AssetMaster'] ?>" data-type="<?= $item['FK_AssetType'] ?>">
                        <?= htmlspecialchars($item['AssetTagNumber']) ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <div id="footer">
                This software is proprietary and licensed for internal use only.
            </div>

        </div>

        <div id="map"></div>

        <div id="detailPanel">
            <button id="backBtn" class="btn btn-dark"> <i class="bi bi-arrow-left me-1"></i> Back</button>
            <h3 id="dpTitle"></h3>
            <p><strong>Type:</strong> <span id="dpType"></span></p>
            <p><strong>Brand:</strong> <span id="dpBrand"></span></p>
            <p><strong>Model:</strong> <span id="dpModel"></span></p>
            <p><strong>Serial No:</strong> <span id="dpSerial"></span></p>
            <p><strong>Description:</strong> <span id="dpDesc"></span></p>
            <p><strong>Purchased On:</strong> <span id="dpPurchaseDate"></span></p>
            <p><strong>Supplier:</strong> <span id="dpSupplier"></span></p>
            <p><strong>Price:</strong> ₱<span id="dpPrice"></span></p>
            <img id="dpImage" src="" alt="Asset Image">
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <script src="script/stopper.js"></script>



    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet.markercluster/dist/leaflet.markercluster.js"></script>

    <script>
        const assetTypes = <?php echo json_encode($assetTypes); ?>;

        const filterSelect = document.getElementById('filter');

        // Clear existing options (optional if you want to rebuild it completely)
        filterSelect.innerHTML = '';

        // Add "All Types" option
        const allOption = document.createElement('option');
        allOption.value = 0;
        allOption.textContent = 'All Types';
        filterSelect.appendChild(allOption);

        // Add asset types from JSON
        assetTypes.forEach(type => {
            const option = document.createElement('option');
            option.value = type.PK_AssetType;
            option.textContent = type.AssetTypeName;
            filterSelect.appendChild(option);
        });
    </script>

    <script>
        const assetTypeMap = <?php echo json_encode($assetTypeMap); ?>;

        const assets = <?= json_encode($items) ?>;
        const map = L.map('map').setView([14.5995, 120.9842], 12);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        const markerCluster = L.markerClusterGroup();
        const markers = {};

        assets.forEach(asset => {
            const marker = L.marker([parseFloat(asset.latitude), parseFloat(asset.longitude)])
                .bindPopup(`<strong>${asset.AssetTagNumber}</strong>`);
            markers[asset.PK_AssetMaster] = {
                marker,
                asset
            };
            marker.on('click', () => showDetails(asset));
            markerCluster.addLayer(marker);
        });

        map.addLayer(markerCluster);

        // Show asset detail with animation
        function showDetails(asset) {
            document.getElementById('dpTitle').innerText = asset.AssetTagNumber;
            document.getElementById('dpType').innerText = assetTypeMap[asset.FK_AssetType] || "Unknown";
            document.getElementById('dpBrand').innerText = asset.BrandManufacturer;
            document.getElementById('dpModel').innerText = asset.Model;
            document.getElementById('dpSerial').innerText = asset.SerialNumber;
            document.getElementById('dpDesc').innerText = asset.Descriptions;
            document.getElementById('dpPurchaseDate').innerText = asset.PurchaseDate;
            document.getElementById('dpSupplier').innerText = asset.SupplierVendor;
            document.getElementById('dpPrice').innerText = parseFloat(asset.PurchasePrice).toFixed(2);
            document.getElementById('dpImage').src = 'image/assetimages/' + asset.Image;

            const detailPanel = document.getElementById('detailPanel');
            detailPanel.style.display = 'block';
            detailPanel.style.transform = 'translateX(0)'; // Slide in effect
        }

        // Asset click from list
        document.querySelectorAll('.item').forEach(el => {
            el.addEventListener('click', () => {
                const id = el.dataset.id;
                const data = markers[id];
                if (data) {
                    map.setView(data.marker.getLatLng(), 16);
                    data.marker.openPopup();
                    showDetails(data.asset);
                }
            });
        });

        // Filter asset type
        document.getElementById('filter').addEventListener('change', e => {
            const val = e.target.value;
            markerCluster.clearLayers();

            document.querySelectorAll('.item').forEach(el => {
                const show = (val == 0 || el.dataset.type === val);
                el.style.display = show ? 'block' : 'none';

                const id = el.dataset.id;
                if (show && markers[id]) {
                    markerCluster.addLayer(markers[id].marker);
                }
            });
        });

        // Search asset tag
        document.getElementById('searchInput').addEventListener('input', e => {
            const keyword = e.target.value.toLowerCase();
            document.querySelectorAll('.item').forEach(el => {
                const text = el.textContent.toLowerCase();
                el.style.display = text.includes(keyword) ? 'block' : 'none';
            });
        });

        // Back button with animation
        document.getElementById('backBtn').addEventListener('click', () => {
            const detailPanel = document.getElementById('detailPanel');
            detailPanel.style.transform = 'translateX(100%)'; // Slide out effect
            setTimeout(() => {
                detailPanel.style.display = 'none';
            }, 300); // Match the duration of the transition
        });

        // edit the openmap attribution namely the copyright
        const attribution = document.querySelector('.leaflet-control-attribution');
        if (attribution) {
            attribution.innerHTML = '&copy; 2025 Developed by Kyle &amp; JC. All rights reserved.';
        }

        //maximum zoom level
        map.setMaxZoom(18);
    </script>


</body>

<?php $conn->close(); ?>

</html>