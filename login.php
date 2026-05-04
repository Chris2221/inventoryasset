<?php
session_start();
require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

include "tools/sender.php";

$error_message = '';
$rememberScript = '';

// Handle retrieveID form submission

if (isset($_POST['form_type']) && $_POST['form_type'] === 'retrieveID') {
    $remembered_id = $_POST['remembered_id'] ?? '';

    $sql = "SELECT PK_Users, Password, Name, Role FROM Users WHERE PK_Users = '$remembered_id' and Status = 1";
    $result = mysqli_query($conn, $sql);

    if ($result && mysqli_num_rows($result) === 1) {
        $user = mysqli_fetch_assoc($result);

        $_SESSION['user_id'] = $user['PK_Users'];
        $_SESSION['username'] = $username;
        $_SESSION['name'] = $user['Name'];
        $_SESSION['role'] = $user['Role'];


        $rememberScript = "localStorage.setItem('rememberedUserId', " . json_encode($user['PK_Users']) . ");
                console.log('User ID remembered: " . $user['PK_Users'] . "');
                window.location.href = 'dashboard.php';";

        $logDetails = "Logged in successfully.";
        $currentUser = $_SESSION['user_id'];
        $actionUser = "Login";

        logActivity($conn, $currentUser, $actionUser, $logDetails);
    } else {
        $rememberScript = "localStorage.removeItem('rememberedUserId');
                window.location.href = 'login.php';";
    }
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username']) && isset($_POST['password'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $username = mysqli_real_escape_string($conn, $username);

    $sql = "SELECT ax.PK_Users, ax.Password, ax.Name, ax.Role, bx.PK_Employees
            FROM Users ax
            left join Employees bx
            on ax.FK_Employees = bx.PK_Employees
            WHERE ax.Username = '$username' and ax.Status = 1";
    $result = mysqli_query($conn, $sql);

    if ($result && mysqli_num_rows($result) === 1) {
        $user = mysqli_fetch_assoc($result);

        if (password_verify($password, $user['Password'])) {
            $_SESSION['user_id'] = $user['PK_Users'];
            $_SESSION['emp_id'] = $user['PK_Employees'];
            $_SESSION['username'] = $username;
            $_SESSION['name'] = $user['Name'];

            if (in_array($user['Role'], ['Manager', 'Supervisor', 'Admin'])) {
                $roleGroup = 'Admin';
            } else {
                $roleGroup = 'User';
            }

            $_SESSION['role'] = $roleGroup;


            if (isset($_POST['remember'])) {
                $rememberScript = "localStorage.setItem('rememberedUserId', " . json_encode($user['PK_Users']) . ");
                console.log('User ID remembered: " . $user['PK_Users'] . "');
                window.location.href = 'dashboard.php';";
            } else {
                $rememberScript = "localStorage.removeItem('rememberedUserId');
                window.location.href = 'dashboard.php';";
            }

            $logDetails = "Logged in successfully.";
            $currentUser = $_SESSION['user_id'];
            $actionUser = "Login";

            logActivity($conn, $currentUser, $actionUser, $logDetails);
        }
    } else {
        $logDetails = "Failed login attempt for username: $username";
        $currentUser = 0;
        $actionUser = "Login";

        logActivity($conn, $currentUser, $actionUser, $logDetails);

        header("Location: login.php?error=1");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>InventoryPro | Login</title>
    <link rel="icon" href="inventory.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="css/others.css">
    <link rel="stylesheet" href="css/login.css">
</head>

<body>
    <!-- Decorative floating elements -->
    <div class="floating-box"></div>
    <div class="floating-box"></div>

    <div class="login-container">
        <div class="logo">
            <div class="logo-icon">
                <i class="fas fa-boxes"></i>
            </div>
            <h1>ASSET MANAGER</h1>
            <p>Manage your inventory with ease</p>
        </div>

        <?php if (isset($_GET['error']) && $_GET['error'] == 1): ?>
            <div class="error-message">Oops! That username or password didn’t match. Try again.</div>
        <?php endif; ?>

        <form class="login-form" id="loginForm" method="POST">
            <div class="form-group">
                <label for="username">Username</label>
                <div class="input-with-icon">
                    <i class="fas fa-user"></i>
                    <input type="text" name="username" id="username" placeholder="Enter your username" required>
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-with-icon">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" id="password" placeholder="Enter your password" required>
                </div>
            </div>

            <div class="remember-forgot">
                <div class="remember-me">
                    <input type="checkbox" id="remember" name="remember">
                    <label for="remember">Remember me</label>
                </div>
                <!--
                <div class="forgot-password">
                    <a href="#">Forgot password?</a>
                </div>
        -->
            </div>

            <button type="submit" class="login-btn">
                Login <i class="fas fa-arrow-right"></i>
            </button>
        </form>

        <form name="retrieveID" method="POST">
            <input type="hidden" name="remembered_id" id="rememberedUserInput">
            <input type="hidden" name="form_type" value="retrieveID">

        </form>
    </div>

    <?php if (!empty($rememberScript)): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                <?php echo $rememberScript; ?>
            });
        </script>
    <?php endif; ?>

    <script>
        window.onload = function() {
            const rememberedId = localStorage.getItem('rememberedUserId');
            if (rememberedId) {
                const input = document.getElementById('rememberedUserInput');
                if (input) input.value = rememberedId;

                const form = document.forms['retrieveID'];
                if (form) form.submit();
            }
        };
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const loginForm = document.getElementById('loginForm');
            loginForm.addEventListener('submit', function(e) {
                const loginBtn = this.querySelector('.login-btn');
                loginBtn.innerHTML = 'Logging in <i class="fas fa-spinner fa-spin"></i>';
                loginBtn.disabled = true;
            });

            const inputs = document.querySelectorAll('input');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.querySelector('i').style.transform = 'translateY(-50%) scale(1.2)';
                });
                input.addEventListener('blur', function() {
                    this.parentElement.querySelector('i').style.transform = 'translateY(-50%) scale(1)';
                });
            });
        });
    </script>
</body>

</html>