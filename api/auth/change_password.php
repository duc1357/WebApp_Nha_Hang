<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/constants.php';
require_once ROOT_PATH . '/config/db.php';

// Gọi config thay vì gọi thẳng session_start()
require_once ROOT_PATH . '/config/session_config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập để thực hiện chức năng này.']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['old_password']) || !isset($data['new_password'])) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng nhập đầy đủ thông tin.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$old_pass = $data['old_password'];
$new_pass = $data['new_password'];

if (strlen($new_pass) < 6) {
    echo json_encode(['success' => false, 'message' => 'Mật khẩu mới phải có ít nhất 6 ký tự.']);
    exit;
}

$conn = getDbConnection();

// 1. Get current password hash
$stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $current_hash = $row['password'];

    // 2. Verify old password
    if (password_verify($old_pass, $current_hash)) {
        // 3. Hash new password and update
        $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
        
        $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $updateStmt->bind_param("si", $new_hash, $user_id);
        
        if ($updateStmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Đổi mật khẩu thành công.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Lỗi cập nhật Database.']);
        }
        $updateStmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Mật khẩu cũ không đúng.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Tài khoản không tồn tại.']);
}

$stmt->close();
$conn->close();
