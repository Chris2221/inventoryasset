<?php

$host = 'localhost';
$db = 'inventory';
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
date_default_timezone_set('Asia/Manila'); // Set the timezone


function logActivity($conn, $userId, $action, $details)
{
    $ipAddress = !empty($_SERVER['HTTP_CLIENT_IP']) ? $_SERVER['HTTP_CLIENT_IP'] :
                 (!empty($_SERVER['HTTP_X_FORWARDED_FOR']) ? explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0] :
                 $_SERVER['REMOTE_ADDR']);

    $stmt = $conn->prepare("INSERT INTO ActivityLogs (FK_Users, Action, Details, IPAddress) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $userId, $action, $details, $ipAddress);
    $stmt->execute();
    $stmt->close();
}

?>