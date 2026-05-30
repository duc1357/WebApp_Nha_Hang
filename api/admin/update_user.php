<?php
require_once dirname(__DIR__, 2) . '/api/services/response_service.php';
// api/admin/update_user.php
require_once __DIR__ . '/auth_check_api.php';
requireAdminPost();
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/db.php';
require_once ROOT_PATH . '/api/services/auth_state_service.php';
require_once ROOT_PATH . '/api/services/password_policy.php';
$conn = getDbConnection();

$data = json_decode(file_get_contents("php://input"), true);

$id = isset($data['id']) ? (int)$data['id'] : 0;
if ($id <= 0) {
    ResponseService::json(['success' => false, 'message' => 'ID không hợp lệ'], 400);
    exit;
}

$name = trim($data['name'] ?? '');
$phone = trim($data['phone'] ?? '');
$email = trim($data['email'] ?? '');
$password = $data['password'] ?? null;
$role = trim($data['role'] ?? '');
if ($role === 'user') {
    $role = 'customer';
}

$allowedRoles = ['admin', 'customer'];
if (!in_array($role, $allowedRoles, true)) {
    ResponseService::json(['success' => false, 'message' => 'Role không hợp lệ'], 422);
    exit;
}
if ($id === (int)$_SESSION['user_id'] && $role !== 'admin') {
    ResponseService::json(['success' => false, 'message' => 'Không thể tự hạ quyền admin của chính mình'], 409);
    exit;
}
if ($name === '' || strlen($name) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^0[0-9]{9}$/', $phone)) {
    ResponseService::json(['success' => false, 'message' => 'Thông tin người dùng không hợp lệ'], 422);
    exit;
}
if (!empty($password) && !PasswordPolicy::isValid($password)) {
    ResponseService::json(['success' => false, 'message' => PasswordPolicy::MESSAGE], 422);
    exit;
}

$sql = "UPDATE users SET name=?, phone=?, email=?, role=?, token_version = token_version + 1";
$types = "ssss";
$params = [$name, $phone, $email, $role];

if (!empty($password)) {
    $sql .= ", password=?";
    $types .= "s";
    $params[] = password_hash($password, PASSWORD_BCRYPT);
}

$sql .= " WHERE id=?";
$types .= "i";
$params[] = $id;

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    if ($id === (int)$_SESSION['user_id']) {
        $versionStmt = $conn->prepare('SELECT token_version, role FROM users WHERE id = ? LIMIT 1');
        $versionStmt->bind_param('i', $id);
        $versionStmt->execute();
        $versionRow = $versionStmt->get_result()->fetch_assoc();
        $versionStmt->close();

        if ($versionRow) {
            AuthStateService::syncCurrentSessionVersion($id, (int)$versionRow['token_version'], (string)$versionRow['role']);
        }
    }

    ResponseService::json(['success' => true, 'message' => 'Cập nhật người dùng thành công']);
} else {
    error_log('[AdminUpdateUser] ' . $stmt->error);
    ResponseService::json(['success' => false, 'message' => 'Không thể cập nhật người dùng'], 500);
}
$conn->close();
