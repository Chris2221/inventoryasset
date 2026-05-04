<style>
  .notification-wrapper {
    position: relative;
    display: inline-block;
  }

  .notification-box {
    position: absolute;
    top: 120%;
    left: 50%;
    transform: translateX(20%);
    margin-left: -83px;
    /* centers the box more rightward */
    width: 250px;
    background-color: #fff;
    color: #000;
    border-radius: 0.5rem;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
    display: none;
    z-index: 1000;
  }

  .notification-box::before {
    content: "";
    position: absolute;
    top: -10px;
    left: 20px;
    border-width: 5px;
    border-style: solid;
    border-color: transparent transparent #fff transparent;
  }

  /* 👇 Mobile: Flip to the left */
  @media (max-width: 576px) {
    .notification-box {
      left: auto;
      right: 0;
      transform: translateX(-20%);
      margin-left: 1500;
    }

    .notification-box::before {
      left: auto;
      right: 20px;
      /* arrow moves to match the new position */
    }
  }
</style>

<header class="navbar navbar-expand-lg navbar-dark bg-dark px-4 py-3">
  <div class="container-fluid">
    <a class="navbar-brand d-flex align-items-center" href="dashboard.php">
      <i class="bi bi-hdd-stack-fill me-2"></i>
      Asset Manager
    </a>

    <?php
    // Count records due in 1, 2, or 3 days
    $countQuery = "
    SELECT COUNT(*) as dueSoon
    FROM outboundassets
    WHERE DATEDIFF(ExpectedReturnDate, CURDATE()) IN (1, 2, 3)
    AND Status = 'Approved'
";
    $countResult = mysqli_query($conn, $countQuery);
    $countRow = mysqli_fetch_assoc($countResult);
    $dueSoonCount = $countRow['dueSoon'];
    ?>

    <div class="notification-wrapper">
      <span class="navbar-text text-white me-3 position-relative" id="notificationIcon" style="cursor: pointer;">
        <i class="bi bi-bell-fill fs-5"></i>
        <?php if ($dueSoonCount > 0): ?>
          <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
            <?= $dueSoonCount ?>
          </span>
        <?php endif; ?>
      </span>

      <!-- Notification Box -->
      <div class="notification-box" id="notificationBox">
        <div class="p-3 border-bottom fw-bold">Notifications</div>
        <div class="p-3">
          <?php
          // Fetch records due in 1–3 days
          $query = "
          SELECT 
              PK_OutboundAssets as RequestID,
              DateAcquired,
              ExpectedReturnDate
          FROM outboundassets
          WHERE DATEDIFF(ExpectedReturnDate, CURDATE()) IN (1, 2, 3)
          AND Status = 'Approved'
          ORDER BY ExpectedReturnDate ASC
      ";

          $result = mysqli_query($conn, $query);

          if (mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
              echo '<div class="mb-2">';
              echo '<i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>';
              echo 'Request ID REQ-<strong>' . $row['RequestID'] . '</strong> is due for return on ';
              echo '<strong>' . htmlspecialchars($row['ExpectedReturnDate']) . '</strong>';
              echo '</div>';
            }
          } else {
            echo '<div><i class="bi bi-info-circle text-muted me-2"></i> No upcoming returns.</div>';
          }
          ?>
        </div>
      </div>
    </div>



    <span class="navbar-text text-white ms-auto me-4">
      <i class="bi bi-person-circle me-1"></i> User: <?= htmlspecialchars($_SESSION['name']) ?>
    </span>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar">
      <span class="navbar-toggler-icon"></span>
    </button>

    <nav class="collapse navbar-collapse" id="mainNavbar">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item">
          <a class="nav-link" href="dashboard.php"><i class="bi bi-speedometer2 me-1"></i> Dashboard</a>
        </li>

        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" id="assetsDropdown" role="button" data-bs-toggle="dropdown">
            <i class="bi bi-box-seam me-1"></i> Assets
          </a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="assets.php"><i class="bi bi-box-seam me-2"></i>Inventory</a></li>
            <li><a class="dropdown-item" href="general-assets.php"><i class="bi bi-stack me-2"></i>General</a></li>
            <li><a class="dropdown-item" href="outbound-assets.php"><i class="bi bi-arrow-up-right-circle me-2"></i>Outbound</a></li>
            <li><a class="dropdown-item" href="inbound-assets.php"><i class="bi bi-arrow-down-left-circle me-2"></i>Inbound</a></li>
            <li><a class="dropdown-item" href="transfer-assets.php"><i class="bi bi-arrow-left-right me-2"></i>Transfer</a></li>
            <li><a class="dropdown-item" href="transferred-assets.php"><i class="bi bi-check2-circle me-2"></i>Transferred</a></li>
          </ul>
        </li>

        <li class="nav-item">
          <a class="nav-link" href="categories.php"><i class="bi bi-tags me-1"></i> Categories</a>
        </li>

        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" id="accountsDropdown" role="button" data-bs-toggle="dropdown">
            <i class="bi bi-people-fill me-1"></i> Accounts
          </a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="users.php"><i class="bi bi-people me-2"></i>Users</a></li>
            <li><a class="dropdown-item" href="employees.php"><i class="bi bi-person-badge me-2"></i>Employees</a></li>
          </ul>
        </li>

        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" id="accountsDropdown" role="button" data-bs-toggle="dropdown">
            <i class="bi bi-three-dots me-1"></i> More
          </a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="locations.php"><i class="bi bi-geo-alt me-2"></i>Asset Locations</a></li>
            <li><a class="dropdown-item" href="reports.php"><i class="bi bi-file-earmark-bar-graph me-2"></i>Reports</a></li>
            <li><a class="dropdown-item" href="settings.php"><i class="bi bi-gear me-2"></i>Settings</a></li>
            <li><a class="dropdown-item" href="logs.php"><i class="bi bi-file-earmark-text me-2"></i>Logs</a></li>
          </ul>
        </li>

        <li class="nav-item">
          <a class="nav-link text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-1"></i> Logout</a>
        </li>
      </ul>
    </nav>
  </div>
</header>
<br>


<div id="preloader">
  <!-- Digimax Preloader -->
  <div id="digimax-preloader" class="digimax-preloader">
    <!-- Preloader Animation -->
    <div class="preloader-animation">
      <!-- Spinner -->
      <div class="spinner"></div>
      <!-- Loader -->
      <div class="loader">
        <span data-text-preloader="I" class="animated-letters">I</span>
        <span data-text-preloader="C" class="animated-letters">C</span>
        <span data-text-preloader="A" class="animated-letters">A</span>
        <span data-text-preloader="R" class="animated-letters">R</span>
        <span data-text-preloader="U" class="animated-letters">U</span>
        <span data-text-preloader="S" class="animated-letters">S</span>
      </div>
      <p class="fw-5 text-center text-uppercase">Loading</p>
    </div>
    <!-- Loader Animation -->

  </div>
</div>

<script>
  const iconNotif = document.getElementById("notificationIcon");
  const boxNotif = document.getElementById("notificationBox");

  iconNotif.addEventListener("click", () => {
    boxNotif.style.display = boxNotif.style.display === "block" ? "none" : "block";
  });

  // Optional: Close the box if clicked outside
  document.addEventListener("click", function(e) {
    if (!iconNotif.contains(e.target) && !boxNotif.contains(e.target)) {
      boxNotif.style.display = "none";
    }
  });
</script>