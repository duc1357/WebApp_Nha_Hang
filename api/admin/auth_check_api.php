<?php
// api/admin/auth_check_api.php

// Ensure session is started properly via constants
require_once __DIR__ . '/../../config/constants.php';
require_once ROOT_PATH . '/api/services/response_service.php';
require_once ROOT_PATH . '/api/services/auth_state_service.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$authState = AuthStateService::validateSession('admin');
if (!$authState['ok']) {
    // Clear buffer if any
    while (ob_get_level()) ob_end_clean();

    ResponseService::error('Unauthorized Access', (int)$authState['status']);
}

function requireAdminPost(): void {
    require_once ROOT_PATH . '/api/services/csrf_service.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        ResponseService::error('Method Not Allowed', 405);
    }

    CsrfService::validateRequest();
}
