<?php
session_start();
session_unset();
session_destroy();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Logging out...</title>
    <script>
        // Clear remembered ID
        localStorage.removeItem('rememberedUserId');
        // Redirect to login page
        window.location.href = 'login.php';
    </script>
</head>
<body>
    <p>Logging out...</p>
</body>
</html>
