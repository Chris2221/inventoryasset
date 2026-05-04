<?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['username']) || !isset($_SESSION['name']) || !isset($_SESSION['role'])
) {
    // One or more session variables are missing or invalid
    header("Location: logout.php");
    exit;
}

include "config.php";

$totalAssets = 0;
$archivedAssets = 0;

$queryTotal = "SELECT COUNT(PK_AssetMaster) AS count FROM assetmaster";
$queryArchived = "SELECT COUNT(PK_AssetMaster) AS count FROM assetmaster WHERE Isarchived = 1";

$resultTotal = mysqli_query($conn, $queryTotal);
$resultArchived = mysqli_query($conn, $queryArchived);

if ($rowTotal = mysqli_fetch_assoc($resultTotal)) {
  $totalAssets = $rowTotal['count'];
}
if ($rowArchived = mysqli_fetch_assoc($resultArchived)) {
  $archivedAssets = $rowArchived['count'];
}

$categoryCount = 0;
$categoryExamples = '';

$queryCount = "SELECT COUNT(PK_AssetType) AS count FROM assettype";
$queryNames = "SELECT AssetTypeName FROM assettype limit 2";

$resultCount = mysqli_query($conn, $queryCount);
$resultNames = mysqli_query($conn, $queryNames);

if ($rowCount = mysqli_fetch_assoc($resultCount)) {
  $categoryCount = $rowCount['count'];
}

$namesArray = [];
while ($rowName = mysqli_fetch_assoc($resultNames)) {
  $namesArray[] = $rowName['AssetTypeName'];
}

// Format names like: Laptop, Desktop, Monitor
$categoryExamples = implode(', ', $namesArray);



$adminCount = 0;
$staffCount = 0;
$supervisorCount = 0;
$managerCount = 0;

$queryAdmin = "SELECT COUNT(PK_Users) AS count FROM users WHERE Role = 'Admin'";
$queryStaff = "SELECT COUNT(PK_Users) AS count FROM users WHERE Role = 'User'";
$querySupervisor = "SELECT COUNT(PK_Users) AS count FROM users WHERE Role = 'Supervisor'";
$queryManager = "SELECT COUNT(PK_Users) AS count FROM users WHERE Role = 'Manager'";

$resultAdmin = mysqli_query($conn, $queryAdmin);
$resultStaff = mysqli_query($conn, $queryStaff);
$resultSupervisor = mysqli_query($conn, $querySupervisor);
$resultManager = mysqli_query($conn, $queryManager);

if ($row = mysqli_fetch_assoc($resultAdmin)) $adminCount = $row['count'];
if ($row = mysqli_fetch_assoc($resultStaff)) $staffCount = $row['count'];
if ($row = mysqli_fetch_assoc($resultSupervisor)) $supervisorCount = $row['count'];
if ($row = mysqli_fetch_assoc($resultManager)) $managerCount = $row['count'];



// Assuming you already have a DB connection as $conn
$assignCount = 0;
$unassignCount = 0;

$query1 = "SELECT COUNT(PK_Approvals) AS count FROM assignapprovals WHERE IsApproved = 0 AND ApprovalType = 'Assigned'";
$query2 = "SELECT COUNT(PK_Approvals) AS count FROM assignapprovals WHERE IsApproved = 0 AND ApprovalType = 'Unassigned'";

$result1 = mysqli_query($conn, $query1);
$result2 = mysqli_query($conn, $query2);

if ($row1 = mysqli_fetch_assoc($result1)) {
  $assignCount = $row1['count'];
}
if ($row2 = mysqli_fetch_assoc($result2)) {
  $unassignCount = $row2['count'];
}


$outboundCount = 0;
$query3 = "SELECT COUNT(PK_OutboundAssets) AS count FROM OutboundAssets WHERE status = 'Pending'";
$result3 = mysqli_query($conn, $query3);
if ($row3 = mysqli_fetch_assoc($result3)) {
  $outboundCount = $row3['count'];
}

$forReturnQty = 0;
$queryReturn = "SELECT (COALESCE(SUM(ax.Quantity), 0) - COALESCE(SUM(ax.QuantityReceived), 0)) AS total 
FROM outboundassetslist ax
left join outboundassets bx
on ax.FK_OutboundAssets =  bx.PK_OutboundAssets
WHERE ax.isReturned = 0 and bx.Status = 'Approved'
;
";
$resultReturn = mysqli_query($conn, $queryReturn);
if ($rowReturn = mysqli_fetch_assoc($resultReturn)) {
  $forReturnQty = $rowReturn['total'] ?? 0;
}

$underRepairCount = 0;
$queryRepair = "SELECT COUNT(PK_AssetMaster) AS count FROM assetmaster WHERE `Conditions` = 'Under Repair'";
$resultRepair = mysqli_query($conn, $queryRepair);

if ($rowRepair = mysqli_fetch_assoc($resultRepair)) {
  $underRepairCount = $rowRepair['count'];
}

$inUseCount = 0;
$queryInUse = "SELECT COUNT(PK_AssetMaster) AS count FROM assetmaster WHERE AssignedTo != 0";
$resultInUse = mysqli_query($conn, $queryInUse);

if ($rowInUse = mysqli_fetch_assoc($resultInUse)) {
  $inUseCount = $rowInUse['count'];
}

$activeEmployees = 0;
$queryEmployees = "SELECT COUNT(PK_Employees) AS count FROM Employees WHERE Status = 'Active'";
$resultEmployees = mysqli_query($conn, $queryEmployees);

if ($rowEmployees = mysqli_fetch_assoc($resultEmployees)) {
  $activeEmployees = $rowEmployees['count'];
}

$warrantyExpirationQuery = "
    SELECT COUNT(*) AS upcomingWarrantyCount
    FROM AssetMaster
    WHERE 
        IsArchived = 0 AND
        WarrantyExpiryDate IS NOT NULL AND
        WarrantyExpiryDate BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
";

$warrantyExpirationResult = $conn->query($warrantyExpirationQuery);
$totalUpcomingWarranties = 0;

if ($warrantyExpirationResult && $warrantyData = $warrantyExpirationResult->fetch_assoc()) {
  $totalUpcomingWarranties = $warrantyData['upcomingWarrantyCount'];
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard</title>
  <link rel="icon" href="inventory.png" type="image/x-icon">
  <?php include "tools/head-plugin.php" ?>
  <style>
    .dashboard-header {
            background: linear-gradient(90deg, #4e54c8, #8f94fb);
            color: white;
            padding: 2rem;
            border-radius: 0.75rem;
            margin-bottom: 2rem;
        }
  </style>
</head>

<body>
  <?php include 'header.php'; ?>


  <main class="container my-4">

    <header class="dashboard-header text-center text-lg-start d-lg-flex justify-content-between align-items-center">
      <div>
        <h1 class="h2 fw-bold">Welcome Back, <?= htmlspecialchars($_SESSION['name']) ?>!</h1>
        <p class="lead mb-0">Here's a summary of your asset management system.</p>
      </div>
      <div class="mt-3 mt-lg-0 text-lg-end">
        <h5 class="mb-0" id="live-date"></h5>
        <p class="mb-0" id="live-time"></p>
      </div>
    </header>
    <div class="row g-4">

      <!-- Total Assets -->
      <div class="col-md-6 col-xl-4">
        <div class="card shadow-sm h-100 border-0" style="cursor: pointer;" onclick="window.location.href='assets.php'">
          <div class="card-body">
            <h5 class="card-title"><i class="bi bi-box-seam me-2"></i>Total Assets</h5>
            <p class="card-text"><?= $totalAssets ?> assets registered</p>
            <p class="text-muted"><?= $archivedAssets ?> archived</p>
          </div>
        </div>
      </div>

      <!-- Assets in Use -->
      <div class="col-md-6 col-xl-4">
        <div class="card shadow-sm h-100 border-0" style="cursor: pointer;" onclick="window.location.href='assets.php?AssetType=All&Status=Assigned&Condition=All'">
          <div class="card-body">
            <h5 class="card-title"><i class="bi bi-hdd-network me-2"></i>Assets in Use</h5>
            <p class="card-text"><?= $inUseCount ?> asset(s) are currently assigned</p>
          </div>
        </div>
      </div>

      <!-- Under Maintenance -->
      <div class="col-md-6 col-xl-4">
        <div class="card shadow-sm h-100 border-0" style="cursor: pointer;" onclick="window.location.href='assets.php?AssetType=All&Status=All&Condition=Under+Repair'">
          <div class="card-body">
            <h5 class="card-title"><i class="bi bi-tools me-2"></i>Under Maintenance</h5>
            <p class="card-text"><?= $underRepairCount ?> asset(s) currently under maintenance</p>
          </div>
        </div>
      </div>

      <!-- Categories -->
      <div class="col-md-6 col-xl-4">
        <div class="card shadow-sm h-100 border-0" style="cursor: pointer;" onclick="window.location.href='categories.php'">
          <div class="card-body">
            <h5 class="card-title"><i class="bi bi-grid-1x2-fill me-2"></i>Asset Categories</h5>
            <p class="card-text"><?= $categoryCount ?> Categories</p>
            <p class="text-muted">e.g. <?= $categoryExamples ?></p>
          </div>
        </div>
      </div>

      <!-- Users -->
      <div class="col-md-6 col-xl-4">
        <div class="card shadow-sm h-100 border-0" style="cursor: pointer;" onclick="window.location.href='users.php'">
          <div class="card-body">
            <h5 class="card-title"><i class="bi bi-people me-2"></i>Users</h5>
            <p class="card-text">
              <?= $adminCount ?> Admins |
              <?= $staffCount ?> Staff |
              <?= $supervisorCount ?> Supervisors |
              <?= $managerCount ?> Managers
            </p>
          </div>
        </div>
      </div>

      <!-- Employees -->
      <div class="col-md-6 col-xl-4">
        <div class="card shadow-sm h-100 border-0" style="cursor: pointer;" onclick="window.location.href='employees.php'">
          <div class="card-body">
            <h5 class="card-title"><i class="bi bi-person-badge-fill me-2"></i>Employees</h5>
            <p class="card-text"><?= $activeEmployees ?> active employee(s)</p>
          </div>
        </div>
      </div>

      <!-- Warranty Expirations -->
      <div class="col-md-6 col-xl-4">
        <div class="card shadow-sm h-100 border-0" style="cursor: pointer;" onclick="window.location.href='warranty-upcoming.php'">
          <div class="card-body">
            <h5 class="card-title"><i class="bi bi-calendar-x-fill me-2"></i>Upcoming Warranty Expirations</h5>
            <p class="card-text"><?= $totalUpcomingWarranties ?> item(s) expiring within 30 days</p>
          </div>
        </div>
      </div>

      <!-- Reports -->
      <div class="col-md-6 col-xl-4">
        <div class="card shadow-sm h-100 border-0" style="cursor: pointer;" onclick="window.location.href='reports.php'">
          <div class="card-body">
            <h5 class="card-title"><i class="bi bi-bar-chart-line me-2"></i>Reports</h5>
            <p class="card-text">Generate usage and depreciation reports</p>
          </div>
        </div>
      </div>
    </div>

  </main>

  <?php if ($_SESSION['role'] == 'Supervisor' || $_SESSION['role'] == 'Manager' || $_SESSION['role'] == 'Admin') { ?>
    <!-- Device Assignment Approvals -->
    <div class="container mt-4">
      <div class="card shadow-sm border-0">
        <div class="card-body">
          <h5 class="card-title">
            <i class="bi bi-check2-square me-2"></i> Device Assignment Approvals
          </h5>
          <p class="card-text text-muted">
            Manage pending device assignment actions. Approve or reject pending requests below.
          </p>
          <div class="d-flex flex-wrap gap-2">
            <a href="assign-approval.php" class="btn btn-outline-dark">
              <i class="bi bi-person-plus-fill me-1"></i> Approve Assignment
              <span class="badge bg-dark text-white ms-2"><?= $assignCount ?></span>
            </a>
            <a href="unassign-approval.php" class="btn btn-outline-dark">
              <i class="bi bi-person-dash-fill me-1"></i> Approve Unassignment
              <span class="badge bg-dark text-white ms-2"><?= $unassignCount ?></span>
            </a>
          </div>
        </div>
      </div>
    </div>


    <!-- Additional Approvals Section -->
    <div class="container mt-4">
      <div class="row g-4">

        <!-- Outbound Asset Approvals -->
        <div class="col-md-6">
          <div class="card shadow-sm border-0 h-100">
            <div class="card-body d-flex flex-column justify-content-between">
              <div>
                <h5 class="card-title">
                  <i class="bi bi-box-seam me-2"></i> Outbound Asset Approvals
                </h5>
                <p class="card-text text-muted">Review and approve pending outbound asset requests.</p>
              </div>
              <br>
              <a href="outbound-assets.php?Status=Pending" class="btn btn-outline-dark mt-auto">
                <i class="bi bi-eye-fill me-1"></i>Pending Outbound Assets
                <span class="badge bg-dark text-white ms-2"><?= $outboundCount ?></span>
              </a>
            </div>
          </div>
        </div>

        <!-- Assets Pending Return -->
        <div class="col-md-6">
          <div class="card shadow-sm border-0 h-100">
            <div class="card-body d-flex flex-column justify-content-between">
              <div>
                <h5 class="card-title">
                  <i class="bi bi-arrow-clockwise me-2"></i> Assets Pending Return
                </h5>
                <p class="card-text text-muted">Items that have been issued and are awaiting return.</p>
              </div>
              <a href="inbound-assets.php" class="btn btn-outline-dark mt-auto">
                <i class="bi bi-arrow-return-left me-1"></i>Pending Returns
                <span class="badge bg-dark text-white ms-2"><?= $forReturnQty ?></span>
              </a>
            </div>
          </div>
        </div>

      </div>
    </div>

  <?php } ?>

  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="script/stopper.js"></script>

  <script>
        function updateTime() {
            const dateElement = document.getElementById('live-date');
            const timeElement = document.getElementById('live-time');
            
            const now = new Date();
            
            const dateOptions = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            const timeOptions = { hour: 'numeric', minute: '2-digit', second: '2-digit', hour12: true };
            
            dateElement.textContent = now.toLocaleDateString('en-US', dateOptions);
            timeElement.textContent = now.toLocaleTimeString('en-US', timeOptions);
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            updateTime();
            setInterval(updateTime, 1000);
        });
    </script>
</body>

</html>