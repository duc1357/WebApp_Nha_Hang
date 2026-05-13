<?php
// api/admin/auth_check_api.php

// Ensure session is started properly via constants
require_once __DIR__ . '/../../config/constants.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    // Clear buffer if any
    while (ob_get_level()) ob_end_clean();
    
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(401); // Unauthorized
    echo json_encode(['success' => false, 'message' => 'Unauthorized Access']);
    exit;
}

function requireAdminPost(): void {
    require_once ROOT_PATH . '/api/services/csrf_service.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method Not Allowed'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    CsrfService::validateRequest();
}
