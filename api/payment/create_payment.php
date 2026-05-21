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

try {
    CsrfService::validateRequest();

    $data = RequestService::json(true);
    if (!isset($data['items']) || !is_array($data['items'])) {
        ResponseService::error('Du lieu khong hop le', 400);
    }

    $items = $data['items'];
    $paymentMethod = ValidationService::enum(
        ValidationService::requiredString($data, 'payment_method', 'Du lieu khong hop le'),
        ['cash', 'bank_transfer'],
        'Phuong thuc thanh toan khong hop le.'
    );
    $address = ValidationService::optionalString($data, 'address');
    $tableInput = ValidationService::optionalString($data, 'table_id');
    $note = ValidationService::optionalString($data, 'note');
    $voucherCode = ValidationService::optionalString($data, 'voucher_code');
    $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

    if (!$userId) {
        ResponseService::error('Ban can dang nhap de dat hang.', 401);
    }

    if (!RateLimitService::check('create_payment_' . $userId, 10, 60)) {
        ResponseService::error('Thao tac qua nhanh. Vui long doi 1 phut.', 429);
    }

    $conn = getDbConnection();
    $tableId = OrderService::getTableId($conn, $tableInput);
    if ($tableInput !== null && $tableId === null) {
        $conn->close();
        ResponseService::error('Ten ban khong hop le hoac khong ton tai.', 400);
    }

    $calcResult = OrderService::calculateOrderTotal($conn, $items);
    $totalCalculated = $calcResult['total_calculated'];
    $validItems = $calcResult['valid_items'];

    if (empty($validItems) || $totalCalculated <= 0) {
        $conn->close();
        ResponseService::error('Gio hang trong hoac mon khong hop le.', 400);
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
        ResponseService::error('Loi tao don hang. Vui long thu lai.', 500);
    }

    require_once ROOT_PATH . '/api/services/email_service.php';
    $emailStmt = $conn->prepare("SELECT email, name FROM users WHERE id = ?");
    $emailStmt->bind_param("i", $userId);
    $emailStmt->execute();
    $emailRes = $emailStmt->get_result();

    if ($uRow = $emailRes->fetch_assoc()) {
        $uEmail = $uRow['email'];
        $uName = htmlspecialchars($uRow['name'] ?? '', ENT_QUOTES, 'UTF-8');
        if ($uEmail) {
            $subject = "[Duong Bau] Xac nhan don hang #$orderId";
            $itemListHtml = "<ul>";
            foreach ($validItems as $itm) {
                $iQty = (int)$itm['quantity'];
                $iPrice = number_format((int)$itm['unit_price']);
                $itemListHtml .= "<li>Mon #" . (int)$itm['menu_item_id'] . " x $iQty ($iPrice d)</li>";
            }
            $itemListHtml .= "</ul>";

            $payStr = ($paymentMethod === 'bank_transfer') ? 'Chuyen khoan (QR)' : 'Tien mat';
            $totalStr = number_format($finalTotal);
            $discountStr = ($discountAmount > 0) ? "<p>Giam gia: -" . number_format($discountAmount) . " d</p>" : "";

            $body = "<h2>Cam on ban da dat mon!</h2>
                     <p>Xin chao <strong>$uName</strong>,</p>
                     <p>Don hang <strong>#$orderId</strong> cua ban da duoc ghi nhan.</p>
                     <h3>Chi tiet don hang:</h3>
                     $itemListHtml
                     <hr>
                     $discountStr
                     <p><strong>Tong cong: $totalStr d</strong></p>
                     <p>Phuong thuc: $payStr</p>";

            EmailService::send($uEmail, $subject, $body);
        }
    }
    $emailStmt->close();

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
        $response['message'] = 'Vui long quet ma QR de thanh toan.';
    } else {
        $response['message'] = 'Dat don thanh cong. Thanh toan khi nhan mon.';
    }

    $conn->close();
    ResponseService::success($response);
} catch (InvalidArgumentException $e) {
    ResponseService::error($e->getMessage(), $e->getCode() ?: 400);
} catch (Throwable $e) {
    error_log('[Payment] ' . $e->getMessage());
    ResponseService::error('Loi tao don hang. Vui long thu lai.', 500);
}
