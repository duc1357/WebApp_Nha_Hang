<?php
require_once __DIR__ . '/../../config/constants.php';
require_once ROOT_PATH . '/config/db.php';
require_once ROOT_PATH . '/api/services/response_service.php';
require_once ROOT_PATH . '/api/services/request_service.php';
require_once ROOT_PATH . '/api/services/validation_service.php';
require_once ROOT_PATH . '/api/services/csrf_service.php';
require_once ROOT_PATH . '/api/services/password_policy.php';
require_once ROOT_PATH . '/api/services/auth_state_service.php';

try {
    CsrfService::validateRequest();

    $authUser = AuthStateService::requireSession();

    $data = RequestService::json(true);
    $oldPass = ValidationService::requiredString($data, 'old_password', 'Vui lòng nhập đầy đủ thông tin.');
    $newPass = ValidationService::requiredString($data, 'new_password', 'Vui lòng nhập đầy đủ thông tin.');

    if (!PasswordPolicy::isValid($newPass)) {
        ResponseService::error(PasswordPolicy::MESSAGE, 400);
    }

    $userId = (int)$authUser['id'];
    $conn = getDbConnection();

    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if (!$row) {
        $conn->close();
        ResponseService::error('Tài khoản không tồn tại.', 404);
    }

    if (!password_verify($oldPass, $row['password'])) {
        $conn->close();
        ResponseService::error('Mật khẩu cũ không đúng.', 400);
    }

    $newHash = password_hash($newPass, PASSWORD_BCRYPT);
    $updateStmt = $conn->prepare("UPDATE users SET password = ?, token_version = token_version + 1 WHERE id = ?");
    $updateStmt->bind_param("si", $newHash, $userId);

    if (!$updateStmt->execute()) {
        error_log('[ChangePass] Update failed: ' . $conn->error);
        $updateStmt->close();
        $conn->close();
        ResponseService::error('Lỗi hệ thống. Vui lòng thử lại sau.', 500);
    }

    $updateStmt->close();

    $versionStmt = $conn->prepare("SELECT token_version, role FROM users WHERE id = ? LIMIT 1");
    $versionStmt->bind_param("i", $userId);
    $versionStmt->execute();
    $versionRow = $versionStmt->get_result()->fetch_assoc();
    $versionStmt->close();

    if ($versionRow) {
        AuthStateService::syncCurrentSessionVersion($userId, (int)$versionRow['token_version'], (string)$versionRow['role']);
    }

    $conn->close();

    ResponseService::success(['message' => 'Đổi mật khẩu thành công!']);
} catch (InvalidArgumentException $e) {
    ResponseService::error($e->getMessage(), $e->getCode() ?: 400);
} catch (Throwable $e) {
    error_log('[ChangePass] ' . $e->getMessage());
    ResponseService::error('Lỗi hệ thống. Vui lòng thử lại sau.', 500);
}
