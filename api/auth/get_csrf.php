<?php
require_once dirname(__DIR__, 2) . '/api/services/response_service.php';
require_once __DIR__ . '/../../api/services/csrf_service.php';

// Ensure session is started (handled by service)
ResponseService::json([
    'success' => true,
    'csrf_token' => CsrfService::generateToken()
]);
