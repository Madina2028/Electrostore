<?php
require_once __DIR__ . '/security.php';
secureSessionStart();

if (!isset($_SESSION['user_id'])) {
    header("Location: /Electrostore/auth/login.php");
    exit();
}
?>
