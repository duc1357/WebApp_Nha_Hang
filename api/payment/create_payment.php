<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__DIR__, 2) . '/logs/payment_error.log');

if (ob_get_level()) {
    ob_clean();
}

require_once __DIR__ . '/../../config/constants.php';
require_once ROOT_PATH . '/config/db.php';
require_once ROOT_PATH . '/api/services/response_service.php';
require_once ROOT_PATH . '/api/services/request_service.php';
require_once ROOT_PATH . '/api/services/validation_service.php';
require_once ROOT_PATH . '/api/services/payment_state_service.php';
require_once ROOT_PATH . '/api/services/csrf_service.php';
require_once ROOT_PATH . '/api/services/OrderService.php';
require_once ROOT_PATH . '/api/services/rate_limit_service.php';
require_once ROOT_PATH . '/api/services/auth_state_service.php';

try {
    CsrfService::validateRequest();

    $data = RequestService::json(true);
    if (!isset($data['items']) || !is_array($data['items'])) {
        ResponseService::error('Dữ liệu không hợp lệ.', 400);
    }

    $items = $data['items'];
    $paymentMethod = ValidationService::enum(
        ValidationService::requiredString($data, 'payment_method', 'Dữ liệu không hợp lệ.'),
        ['cash', 'bank_transfer'],
        'Phương thức thanh toán không hợp lệ.'
    );
    $address = ValidationService::optionalString($data, 'address');
    $tableInput = ValidationService::optionalString($data, 'table_id');
    $note = ValidationService::optionalString($data, 'note');
    $voucherCode = ValidationService::optionalString($data, 'voucher_code');
    $authUser = AuthStateService::requireSession();
    $userId = (int)$authUser['id'];

    if (!RateLimitService::check('create_payment_' . $userId, 10, 60)) {
        ResponseService::error('Thao tác quá nhanh. Vui lòng đợi 1 phút.', 429);
    }

    $conn = getDbConnection();
    $tableId = OrderService::getTableId($conn, $tableInput);
    if ($tableInput !== null && $tableId === null) {
        $conn->close();
        ResponseService::error('Tên bàn không hợp lệ hoặc không tồn tại.', 400);
    }

    $calcResult = OrderService::calculateOrderTotal($conn, $items);
    $totalCalculated = $calcResult['total_calculated'];
    $validItems = $calcResult['valid_items'];

    if (empty($validItems) || $totalCalculated <= 0) {
        $conn->close();
        ResponseService::error('Giỏ hàng trống hoặc món đặt không hợp lệ.', 400);
    }

    $voucherResult = OrderService::validateAndApplyVoucher($conn, $voucherCode, $totalCalculated);
    $discountAmount = $voucherResult['discount_amount'];
    $finalTotal = $voucherResult['final_total'];
    $appliedVoucher = $voucherResult['applied_voucher'];

    $conn->begin_transaction();
    try {
        $status = PaymentStateService::ORDER_PENDING;
        $sql = "INSERT INTO orders (user_id, total_amount, discount_amount, final_total, payment_method, status, address, table_id, note, voucher_code, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Prepare order failed: ' . $conn->error);
        }
        $stmt->bind_param("idddsssiss", $userId, $totalCalculated, $discountAmount, $finalTotal, $paymentMethod, $status, $address, $tableId, $note, $appliedVoucher);
        if (!$stmt->execute()) {
            throw new RuntimeException('Order insert failed: ' . $stmt->error);
        }
        $orderId = (int)$stmt->insert_id;
        $stmt->close();

        $stmtItem = $conn->prepare("INSERT INTO order_items (order_id, menu_item_id, quantity, unit_price) VALUES (?, ?, ?, ?)");
        if (!$stmtItem) {
            throw new RuntimeException('Prepare order item failed: ' . $conn->error);
        }
        foreach ($validItems as $vItem) {
            $stmtItem->bind_param("iiii", $orderId, $vItem['menu_item_id'], $vItem['quantity'], $vItem['unit_price']);
            if (!$stmtItem->execute()) {
                throw new RuntimeException('Order item insert failed: ' . $stmtItem->error);
            }
        }
        $stmtItem->close();
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        error_log('[Payment] Transaction failed: ' . $e->getMessage());
        $conn->close();
        ResponseService::error('Lỗi tạo đơn hàng. Vui lòng thử lại sau.', 500);
    }

    require_once ROOT_PATH . '/api/services/email_service.php';
    if ($paymentMethod === 'cash') {
        EmailService::sendOrderConfirmationEmail($conn, $orderId);
    }

    $response = [
        'order_id' => $orderId,
        'final_total' => $finalTotal,
    ];

    if ($paymentMethod === 'bank_transfer') {
        $paymentContent = "DH" . $orderId;
        $response['payUrl'] = "https://qr.sepay.vn/img"
            . "?acc=" . urlencode(defined('SEPAY_VA_ACCOUNT') ? SEPAY_VA_ACCOUNT : '')
            . "&bank=" . urlencode(defined('SEPAY_BANK_NAME') ? SEPAY_BANK_NAME : 'MBBank')
            . "&amount=" . urlencode($finalTotal)
            . "&des=" . urlencode($paymentContent);
        $response['message'] = 'Vui lòng quét mã QR để thanh toán.';
    } else {
        $response['message'] = 'Đặt đơn thành công! Thanh toán khi nhận món.';
    }

    $conn->close();
    ResponseService::success($response);
} catch (InvalidArgumentException $e) {
    ResponseService::error($e->getMessage(), $e->getCode() ?: 400);
} catch (Throwable $e) {
    error_log('[Payment] ' . $e->getMessage());
    ResponseService::error('Lỗi tạo đơn hàng. Vui lòng thử lại sau.', 500);
}
