<?php
require_once __DIR__ . '/../../config/constants.php';
require_once ROOT_PATH . '/config/db.php';
require_once ROOT_PATH . '/api/services/response_service.php';
require_once ROOT_PATH . '/api/services/request_service.php';
require_once ROOT_PATH . '/api/services/validation_service.php';
require_once ROOT_PATH . '/api/services/csrf_service.php';
require_once ROOT_PATH . '/api/services/password_policy.php';

try {
    CsrfService::validateRequest();

    if (!isset($_SESSION['user_id'])) {
        ResponseService::error('Vui long dang nhap de thuc hien chuc nang nay.', 401);
    }

    $data = RequestService::json(true);
    $oldPass = ValidationService::requiredString($data, 'old_password', 'Vui long nhap day du thong tin.');
    $newPass = ValidationService::requiredString($data, 'new_password', 'Vui long nhap day du thong tin.');

    if (!PasswordPolicy::isValid($newPass)) {
        ResponseService::error(PasswordPolicy::MESSAGE, 400);
    }

    $userId = (int)$_SESSION['user_id'];
    $conn = getDbConnection();

    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if (!$row) {
        $conn->close();
        ResponseService::error('Tai khoan khong ton tai.', 404);
    }

    if (!password_verify($oldPass, $row['password'])) {
        $conn->close();
        ResponseService::error('Mat khau cu khong dung.', 400);
    }

    $newHash = password_hash($newPass, PASSWORD_BCRYPT);
    $updateStmt = $conn->prepare("UPDATE users SET password = ?, token_version = token_version + 1 WHERE id = ?");
    $updateStmt->bind_param("si", $newHash, $userId);

    if (!$updateStmt->execute()) {
        error_log('[ChangePass] Update failed: ' . $conn->error);
        $updateStmt->close();
        $conn->close();
        ResponseService::error('Loi he thong. Vui long thu lai.', 500);
    }

    $updateStmt->close();
    $conn->close();

    ResponseService::success(['message' => 'Doi mat khau thanh cong.']);
} catch (InvalidArgumentException $e) {
    ResponseService::error($e->getMessage(), $e->getCode() ?: 400);
} catch (Throwable $e) {
    error_log('[ChangePass] ' . $e->getMessage());
    ResponseService::error('Loi he thong. Vui long thu lai.', 500);
}
