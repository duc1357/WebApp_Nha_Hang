<?php
require_once dirname(__DIR__) . '/config/constants.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: index.php");
    exit;
}

// Check if user is admin
if ($_SESSION['role'] !== 'admin') {
    // If logged in as customer, maybe redirect to home or show error
    // For now, redirect to index with error
    session_destroy();
    header("Location: index.php?error=unauthorized");
    exit;
}
?>
