<?php
require_once __DIR__ . '/auth_check_api.php';
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/constants.php';
require_once ROOT_PATH . '/config/db.php';

$data = json_decode(file_get_contents("php://input"), true);
$id = $data['id'] ?? null;
$status = $data['status'] ?? null;

if (!$id || !$status) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

$allowed_statuses = ['pending', 'confirmed', 'cancelled'];
if (!in_array($status, $allowed_statuses, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status value']);
    exit;
}

$conn = getDbConnection();

// Update status
$sql = "UPDATE bookings SET status = ? WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $status, $id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Update failed']);
}

$stmt->close();
$conn->close();
