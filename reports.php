<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include "config.php";

$createdBy = $_SESSION['user_id'];

$data = [];
$labels = [];
$query = "SELECT at.AssetTypeName, COUNT(am.PK_AssetMaster) AS TotalAssets
          FROM assetmaster am
          JOIN assettype at ON am.FK_AssetType = at.PK_AssetType
          where am.IsArchived = 0
          GROUP BY at.AssetTypeName
          ORDER BY TotalAssets DESC";
$result = mysqli_query($conn, $query);

while ($row = mysqli_fetch_assoc($result)) {
    $labels[] = $row['AssetTypeName'];
    $data[] = $row['TotalAssets'];
}


$deptLabels = [];
$deptData = [];

$query = "SELECT TRIM(e.Department) AS Department, COUNT(am.PK_AssetMaster) AS AssetCount
FROM Employees e
LEFT JOIN AssetMaster am ON am.AssignedTo = e.PK_Employees AND am.AssignedTo != 0
GROUP BY TRIM(e.Department)
ORDER BY AssetCount DESC;
";
$result = mysqli_query($conn, $query);

while ($row = mysqli_fetch_assoc($result)) {
    $deptLabels[] = $row['Department'];
    $deptData[] = $row['AssetCount'];
}


$repairDateFrom = $_GET['repair_from'] ?? '2025-01-01';
$repairDateTo = $_GET['repair_to'] ?? date('Y-m-d'); // Default to today

$repairChartLabels = [];
$repairChartData = [];

$queryRepairChart = "
    SELECT DATE_FORMAT(RepairedDate, '%Y-%m') AS RepairMonth, SUM(Cost) AS TotalCost
    FROM assetrepairedhistory
    WHERE DATE(RepairedDate) BETWEEN '$repairDateFrom' AND '$repairDateTo'
    GROUP BY RepairMonth
    ORDER BY RepairMonth
";

$resultRepairChart = mysqli_query($conn, $queryRepairChart);

while ($rowRepairChart = mysqli_fetch_assoc($resultRepairChart)) {
    $label = date('M Y', strtotime($rowRepairChart['RepairMonth'] . '-01')); // e.g., "Jan 2025"
    $repairChartLabels[] = $label;
    $repairChartData[] = $rowRepairChart['TotalCost'];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports</title>
    <link rel="icon" href="inventory.png" type="image/x-icon">
    <?php include "tools/head-plugin.php" ?>

</head>

<body>
    <?php include 'header.php'; ?>
    <div class="container">

        <?php include 'modal.php'; ?>

        <?php include "tools/alert-message.php"; ?>

<div class="mt-4 pt-3">
             <h4 class="mb-3">Visualizations</h4>
            <div class="row">
                <!-- Asset Type Bar Chart -->
                <div class="col-lg-6 mb-4">
                    <div class="card chart-card h-100">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title fw-bold"><i class="bi bi-bar-chart-line-fill me-2"></i>Assets by Category</h5>
                            <div class="flex-grow-1 mt-3" style="min-height: 300px;">
                                <canvas id="assetTypeChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Department Allocation Pie Chart -->
                <div class="col-lg-6 mb-4">
                    <div class="card chart-card h-100">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title fw-bold"><i class="bi bi-pie-chart-fill me-2"></i>Assets by Department</h5>
                             <div class="flex-grow-1 mt-3 d-flex align-items-center justify-content-center" style="min-height: 300px;">
                                <canvas id="deptAssetChart" style="max-height: 300px;"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- NEWLY ADDED REPAIR COSTS CHART -->
                <div class="col-lg-12 mb-4">
                     <div class="card chart-card">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title fw-bold"><i class="bi bi-wrench-adjustable-circle-fill me-2"></i>Repair Costs Over Time</h5>
                             <div class="flex-grow-1 mt-3" style="min-height: 300px;">
                                <canvas id="repairCostChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>



    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="script.js"></script>

    <script>
        const ctxRepairChart = document.getElementById('repairCostChart').getContext('2d');

        const repairLabels = <?php echo json_encode($repairChartLabels); ?>;
        const repairData = <?php echo json_encode($repairChartData); ?>;

        // Generate a different color for each bar
        const backgroundColors = repairData.map(() => {
            const r = Math.floor(Math.random() * 156 + 100);
            const g = Math.floor(Math.random() * 156 + 100);
            const b = Math.floor(Math.random() * 156 + 100);
            return `rgba(${r}, ${g}, ${b}, 0.6)`;
        });

        const borderColors2 = backgroundColors.map(color => color.replace('0.6', '1'));

        const repairCostChart = new Chart(ctxRepairChart, {
            type: 'bar',
            data: {
                labels: repairLabels,
                datasets: [{
                    label: 'Total Repair Cost (₱)',
                    data: repairData,
                    backgroundColor: backgroundColors,
                    borderColor: borderColors2,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    },
                    x: {
                        ticks: {
                            autoSkip: true,
                            maxTicksLimit: 12
                        }
                    }
                }
            }
        });
    </script>

    <script>
        const ctx = document.getElementById('assetTypeChart').getContext('2d');

        const colors = [
            'rgba(255, 99, 132, 0.6)', // Red
            'rgba(54, 162, 235, 0.6)', // Blue
            'rgba(255, 206, 86, 0.6)', // Yellow
            'rgba(75, 192, 192, 0.6)', // Teal
            'rgba(153, 102, 255, 0.6)', // Purple
            'rgba(255, 159, 64, 0.6)', // Orange
            'rgba(199, 199, 199, 0.6)', // Gray
            'rgba(255, 99, 255, 0.6)', // Pink
            'rgba(100, 255, 218, 0.6)' // Aqua
        ];

        const borderColors = colors.map(c => c.replace('0.6', '1'));

        const assetTypeChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($labels); ?>,
                datasets: [{
                    label: '', // No label shown in tooltip or legend
                    data: <?php echo json_encode($data); ?>,
                    backgroundColor: colors.slice(0, <?php echo count($labels); ?>),
                    borderColor: borderColors.slice(0, <?php echo count($labels); ?>),
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false // Completely hides the legend
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    </script>

    <script>
        const ctx2 = document.getElementById('deptAssetChart').getContext('2d');

        const colors2 = [
            'rgba(255, 99, 132, 0.6)',
            'rgba(54, 162, 235, 0.6)',
            'rgba(255, 206, 86, 0.6)',
            'rgba(75, 192, 192, 0.6)',
            'rgba(153, 102, 255, 0.6)',
            'rgba(255, 159, 64, 0.6)',
            'rgba(199, 199, 199, 0.6)'
        ];

        const deptAssetChart = new Chart(ctx2, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode($deptLabels); ?>,
                datasets: [{
                    data: <?php echo json_encode($deptData); ?>,
                    backgroundColor: colors2.slice(0, <?php echo count($deptLabels); ?>),
                    borderColor: '#fff',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'right'
                    },
                    title: {
                        display: true,
                        text: ''
                    }
                }
            }
        });
    </script>

</body>

<?php
$conn->close();
?>

</html>