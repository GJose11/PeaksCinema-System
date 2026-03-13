<?php
session_start();
$_SESSION['admin_logged_in'] = false;
unset($_SESSION['admin_logged_in']);
unset($_SESSION['admin_name']);
unset($_SESSION['admin_email']);
session_destroy();
header("Location: admin_login.php");
exit;
