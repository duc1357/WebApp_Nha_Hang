<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/constants.php';
require_once ROOT_PATH . '/config/db.php';
require_once ROOT_PATH . '/api/services/csrf_service.php';

// SEC-02: Validate CSRF cho mutating request
CsrfService::validateRequest();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập để thực hiện chức năng này.']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['old_password']) || !isset($data['new_password'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Vui lòng nhập đầy đủ thông tin.']);
    exit;
}

$user_id  = (int) $_SESSION['user_id'];
$old_pass = $data['old_password'];
$new_pass = $data['new_password'];

if (strlen($new_pass) < 6) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Mật khẩu mới phải có ít nhất 6 ký tự.']);
    exit;
}

$conn = getDbConnection();

// 1. Get current password hash
$stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($row = $result->fetch_assoc()) {
    $current_hash = $row['password'];

    // 2. Verify old password
    if (password_verify($old_pass, $current_hash)) {
        // 3. Hash new password and update
        $new_hash = password_hash($new_pass, PASSWORD_BCRYPT);

        $updateStmt = $conn->prepare("UPDATE users SET password = ?, token_version = token_version + 1 WHERE id = ?");
        $updateStmt->bind_param("si", $new_hash, $user_id);

        if ($updateStmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Đổi mật khẩu thành công.']);
        } else {
            // CODE-06: Không lộ DB error
            error_log('[ChangePass] Update failed: ' . $conn->error);
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống. Vui lòng thử lại.']);
        }
        $updateStmt->close();
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Mật khẩu cũ không đúng.']);
    }
} else {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Tài khoản không tồn tại.']);
}

$conn->close();
