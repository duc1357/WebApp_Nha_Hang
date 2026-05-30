<?php
require_once dirname(__DIR__) . '/config/constants.php';
require_once ROOT_PATH . '/api/services/auth_state_service.php';

// Check if user is logged in
$authState = AuthStateService::validateSession('admin');
if (!$authState['ok'] && (int)$authState['status'] === 401) {
    header("Location: index.php");
    exit;
}

// Check if user is admin
if (!$authState['ok']) {
    // If logged in as customer, maybe redirect to home or show error
    // For now, redirect to index with error
    session_destroy();
    header("Location: index.php?error=unauthorized");
    exit;
}
?>
