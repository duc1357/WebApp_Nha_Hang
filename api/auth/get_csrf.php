<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../api/services/csrf_service.php';

// Ensure session is started (handled by service)
echo json_encode([
    'success' => true, 
    'csrf_token' => CsrfService::generateToken()
]);
