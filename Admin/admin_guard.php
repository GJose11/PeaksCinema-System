<?php
/**
 * admin_guard.php
 * Include this at the TOP of every admin page to protect it.
 * Usage: require_once("admin_guard.php");
 *
 * Place this file in: D:\xampp\htdocs\PeaksCinema\Admin\
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit;
}
