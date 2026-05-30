<?php
if (ob_get_level()) {
    ob_clean();
}

require_once __DIR__ . '/../../config/constants.php';
require_once ROOT_PATH . '/config/db.php';
require_once ROOT_PATH . '/api/services/response_service.php';
require_once ROOT_PATH . '/api/services/OrderService.php';
require_once ROOT_PATH . '/api/services/payment_state_service.php';
require_once ROOT_PATH . '/api/services/logger_service.php';

function logWebhookPayment(string $message, array $context = [], string $level = Logger::INFO): void {
    Logger::payment($message, $context, $level);
}

function webhookAuthHeader(): string {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if ($authHeader === '' && function_exists('apache_request_headers')) {
        $reqHeaders = apache_request_headers();
        $authHeader = $reqHeaders['Authorization'] ?? '';
    }
    return $authHeader;
}

if (empty(SEPAY_WEBHOOK_TOKEN)) {
    logWebhookPayment('SePay webhook token is not configured', [], Logger::CRITICAL);
    ResponseService::error('Webhook not configured', 503);
}

if (webhookAuthHeader() !== 'Apikey ' . SEPAY_WEBHOOK_TOKEN) {
    Logger::security('Unauthorized SePay webhook attempt', [], Logger::WARNING);
    ResponseService::error('Unauthorized Webhook!', 401);
}

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!is_array($data)) {
    logWebhookPayment('SePay webhook rejected - invalid JSON', [], Logger::WARNING);
    ResponseService::error('Invalid JSON', 400);
}

$content = (string)($data['content'] ?? '');
$amount = (int)($data['transferAmount'] ?? 0);
$transferType = strtolower(trim((string)($data['transferType'] ?? '')));
$accountNumber = trim((string)($data['accountNumber'] ?? ''));
$subAccount = trim((string)($data['subAccount'] ?? ''));
$gateway = trim((string)($data['gateway'] ?? ''));

logWebhookPayment('SePay webhook received', [
    'gateway' => $gateway !== '' ? $gateway : 'unknown',
    'accountNumber' => $accountNumber,
    'subAccount' => $subAccount,
    'transferAmount' => $amount,
    'content' => $content,
]);

if ($transferType !== 'in') {
    logWebhookPayment('SePay webhook rejected - unsupported transfer type', [
        'transferType' => $transferType,
        'content' => $content,
    ], Logger::WARNING);
    ResponseService::error('Unsupported transfer type', 400);
}

if (defined('SEPAY_VA_ACCOUNT') && SEPAY_VA_ACCOUNT !== '') {
    $normalize = static fn(string $value): string => preg_replace('/[^A-Z0-9]/', '', strtoupper($value));
    $expectedAccount = $normalize(SEPAY_VA_ACCOUNT);
    $expectedWithoutPrefix = str_starts_with($expectedAccount, 'VQR') ? substr($expectedAccount, 3) : $expectedAccount;
    $receivedAccountText = $normalize($accountNumber . ' ' . $subAccount . ' ' . $content);

    $accountMatches = $expectedAccount !== '' && str_contains($receivedAccountText, $expectedAccount);
    if (!$accountMatches && $expectedWithoutPrefix !== '' && $expectedWithoutPrefix !== $expectedAccount) {
        $accountMatches = str_contains($receivedAccountText, $expectedWithoutPrefix);
    }

    if (!$accountMatches) {
        logWebhookPayment('SePay webhook rejected - unexpected account', [
            'accountNumber' => $accountNumber,
            'subAccount' => $subAccount,
            'content' => $content,
        ], Logger::WARNING);
        ResponseService::error('Unexpected account number', 400);
    }
}

if (defined('SEPAY_BANK_NAME') && SEPAY_BANK_NAME !== '' && $gateway !== '' && strcasecmp($gateway, SEPAY_BANK_NAME) !== 0) {
    logWebhookPayment('SePay webhook rejected - unexpected gateway', [
        'gateway' => $gateway,
        'expected_gateway' => SEPAY_BANK_NAME,
        'content' => $content,
    ], Logger::WARNING);
    ResponseService::error('Unexpected gateway', 400);
}

if (preg_match('/DH(\d+)/i', $content, $matches)) {
    $orderId = (int)$matches[1];
    $conn = getDbConnection();

    $stmt = $conn->prepare("SELECT id, total_amount, final_total, status, voucher_code FROM orders WHERE id = ?");
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();

    if (!$order) {
        $stmt->close();
        $conn->close();
        logWebhookPayment('SePay order payment ignored - order not found', ['order_id' => $orderId], Logger::WARNING);
        ResponseService::error('Order not found', 200);
    }

    $expectedAmount = isset($order['final_total']) ? (int)$order['final_total'] : (int)$order['total_amount'];
    if ($amount < $expectedAmount) {
        $stmt->close();
        $conn->close();
        logWebhookPayment('SePay order payment rejected - insufficient amount', [
            'order_id' => $orderId,
            'amount' => $amount,
            'expected_amount' => $expectedAmount,
        ], Logger::WARNING);
        ResponseService::error('Insufficient payment amount', 400);
    }

    if (PaymentStateService::isPaidOrderStatus((string)$order['status'])) {
        $stmt->close();
        $conn->close();
        logWebhookPayment('SePay order payment ignored - already paid', ['order_id' => $orderId]);
        ResponseService::success(['message' => 'Order already paid']);
    }

    $conn->begin_transaction();
    $markResult = OrderService::markOrderPaid($conn, $orderId, 'bank_transfer');

    if ($markResult['success']) {
        $conn->commit();
        $stmt->close();
        
        // Gửi email xác nhận sau khi thanh toán thành công
        require_once ROOT_PATH . '/api/services/email_service.php';
        EmailService::sendOrderConfirmationEmail($conn, $orderId);

        $conn->close();
        logWebhookPayment('SePay order payment accepted', [
            'order_id' => $orderId,
            'amount' => $amount,
        ]);
        ResponseService::success(['message' => $markResult['message']]);
    }

    $conn->rollback();
    $stmt->close();
    $conn->close();
    logWebhookPayment('SePay order payment failed while marking paid', [
        'order_id' => $orderId,
        'reason' => $markResult['message'],
    ], Logger::ERROR);
    ResponseService::error($markResult['message'], $markResult['message'] === 'Voucher is no longer valid' ? 409 : 500);
}

if (preg_match('/BKG(\d+)/i', $content, $matches)) {
    $bookingId = (int)$matches[1];
    $conn = getDbConnection();

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("SELECT id, deposit_amount, payment_status FROM bookings WHERE id = ? FOR UPDATE");
        $stmt->bind_param("i", $bookingId);
        $stmt->execute();
        $booking = $stmt->get_result()->fetch_assoc();

        if (!$booking) {
            $conn->rollback();
            $stmt->close();
            $conn->close();
            logWebhookPayment('SePay booking deposit ignored - booking not found', ['booking_id' => $bookingId], Logger::WARNING);
            ResponseService::error('Booking not found', 200);
        }

        $expectedAmount = (int)$booking['deposit_amount'];
        if ($amount < $expectedAmount) {
            $conn->rollback();
            $stmt->close();
            $conn->close();
            logWebhookPayment('SePay booking deposit rejected - insufficient amount', [
                'booking_id' => $bookingId,
                'amount' => $amount,
                'expected_amount' => $expectedAmount,
            ], Logger::WARNING);
            ResponseService::error('Insufficient deposit amount', 400);
        }

        if (PaymentStateService::isPaidBookingPaymentStatus((string)$booking['payment_status'])) {
            $conn->commit();
            $stmt->close();
            $conn->close();
            logWebhookPayment('SePay booking deposit ignored - already paid', ['booking_id' => $bookingId]);
            ResponseService::success(['message' => 'Booking deposit already paid']);
        }

        $updateStmt = $conn->prepare("UPDATE bookings SET payment_status = 'partial', status = 'confirmed' WHERE id = ? AND payment_status = 'pending'");
        $updateStmt->bind_param("i", $bookingId);

        if ($updateStmt->execute() && $updateStmt->affected_rows > 0) {
            $conn->commit();
            $updateStmt->close();
            $stmt->close();

            // Gửi email xác nhận đặt bàn sau khi thanh toán cọc thành công
            require_once ROOT_PATH . '/api/services/email_service.php';
            EmailService::sendBookingConfirmationEmail($conn, $bookingId);

            $conn->close();
            logWebhookPayment('SePay booking deposit accepted', [
                'booking_id' => $bookingId,
                'amount' => $amount,
            ]);
            ResponseService::success(['message' => 'Booking updated']);
        }

        $conn->rollback();
        $updateStmt->close();
        $stmt->close();
        $conn->close();
        logWebhookPayment('SePay booking deposit update skipped - state changed before update', [
            'booking_id' => $bookingId,
        ]);
        ResponseService::success(['message' => 'Booking deposit already paid']);
    } catch (Throwable $e) {
        $conn->rollback();
        if (isset($updateStmt) && $updateStmt instanceof mysqli_stmt) {
            $updateStmt->close();
        }
        if (isset($stmt) && $stmt instanceof mysqli_stmt) {
            $stmt->close();
        }
        $error = $e->getMessage();
        $conn->close();
        logWebhookPayment('SePay booking deposit update failed', [
            'booking_id' => $bookingId,
            'error' => $error,
        ], Logger::ERROR);
        ResponseService::error('Update failed', 500);
    }
}

logWebhookPayment('SePay webhook ignored - no matching payment code', [
    'content' => $content,
    'amount' => $amount,
], Logger::WARNING);
ResponseService::error('No matching Code found in content', 200);
