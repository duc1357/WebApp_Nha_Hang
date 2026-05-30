<?php
if (ob_get_level()) {
    ob_clean();
}

require_once __DIR__ . '/../../config/constants.php';
require_once ROOT_PATH . '/config/db.php';
require_once ROOT_PATH . '/api/services/response_service.php';
require_once ROOT_PATH . '/api/services/request_service.php';
require_once ROOT_PATH . '/api/services/validation_service.php';
require_once ROOT_PATH . '/api/services/csrf_service.php';
require_once ROOT_PATH . '/api/services/rate_limit_service.php';
require_once ROOT_PATH . '/api/services/auth_state_service.php';

try {
    CsrfService::validateRequest();

    $data = RequestService::input(false);
    $name = ValidationService::requiredString($data, 'name', 'Dữ liệu không đầy đủ.');
    $phone = ValidationService::phone(
        ValidationService::requiredString($data, 'phone', 'Dữ liệu không đầy đủ.'),
        'Số điện thoại không hợp lệ.'
    );
    $date = ValidationService::date(
        ValidationService::requiredString($data, 'date', 'Dữ liệu không đầy đủ.'),
        'Ngày hoặc giờ không hợp lệ.'
    );
    $time = ValidationService::time(
        ValidationService::requiredString($data, 'time', 'Dữ liệu không đầy đủ.'),
        'Ngày hoặc giờ không hợp lệ.'
    );
    $guestsInt = ValidationService::intRange($data['guests'] ?? null, 1, 20, 'Số lượng khách không hợp lệ.');
    $floor = trim((string)($data['floor'] ?? ''));
    $tableId = ValidationService::intRange($data['table_id'] ?? null, 1, PHP_INT_MAX, 'Vui lòng chọn bàn!');
    $hasPreorder = !empty($data['has_preorder']);
    $items = isset($data['items']) && is_array($data['items']) ? $data['items'] : [];

    $bookingDateTime = DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . $time, new DateTimeZone('Asia/Ho_Chi_Minh'));
    if (!$bookingDateTime || $bookingDateTime < new DateTime('now', new DateTimeZone('Asia/Ho_Chi_Minh'))) {
        ResponseService::error('Không thể đặt bàn trong quá khứ.', 422);
    }

    $hour = (int)$bookingDateTime->format('H');
    if ($hour < 8 || $hour > 22) {
        ResponseService::error('Vui lòng chọn giờ trong khung 08:00 - 22:00.', 422);
    }

    $authUser = AuthStateService::requireSession();
    $userId = (int)$authUser['id'];

    if (!RateLimitService::check('book_table_' . $userId, 5, 60)) {
        ResponseService::error('Thao tác quá nhanh. Vui lòng đợi 1 phút.', 429);
    }

    $conn = getDbConnection();
    $conn->begin_transaction();

    $tableStmt = $conn->prepare("SELECT capacity FROM tables WHERE id = ? FOR UPDATE");
    $tableStmt->bind_param("i", $tableId);
    $tableStmt->execute();
    $tableRes = $tableStmt->get_result();
    $table = $tableRes->fetch_assoc();
    $tableStmt->close();

    if (!$table) {
        $conn->rollback();
        $conn->close();
        ResponseService::error('Bàn đặt không tồn tại.', 404);
    }

    if ($guestsInt > (int)$table['capacity']) {
        $conn->rollback();
        $conn->close();
        ResponseService::error('Số khách vượt quá sức chứa của bàn.', 422);
    }

    $checkSql = "
        SELECT id, time
        FROM bookings
        WHERE table_id = ?
          AND date = ?
          AND status != 'cancelled'
          AND ABS(TIMESTAMPDIFF(MINUTE, STR_TO_DATE(time, '%H:%i'), STR_TO_DATE(?, '%H:%i'))) <= 120
        FOR UPDATE
    ";
    $stmt = $conn->prepare($checkSql);
    $stmt->bind_param("iss", $tableId, $date, $time);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        $stmt->close();
        $conn->rollback();
        $conn->close();
        ResponseService::error('Bàn này đã được đặt trong khoảng 2 tiếng gần thời gian bạn chọn. Vui lòng chọn bàn hoặc thời gian khác.', 409);
    }
    $stmt->close();

    $totalAmount = 0;
    $depositAmount = 0;
    $status = 'pending';

    if ($hasPreorder && empty($items)) {
        $conn->rollback();
        $conn->close();
        ResponseService::error('Món đặt trước không hợp lệ.', 422);
    }

    if ($hasPreorder && !empty($items)) {
        $ids = array_map('intval', array_column($items, 'menu_item_id'));
        $ids = array_values(array_filter($ids));

        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $types = str_repeat('i', count($ids));
            $priceStmt = $conn->prepare("SELECT id, price FROM menu_items WHERE id IN ($placeholders) AND is_active = 1 AND deleted_at IS NULL");
            $priceStmt->bind_param($types, ...$ids);
            $priceStmt->execute();
            $priceResult = $priceStmt->get_result();

            $priceMap = [];
            while ($row = $priceResult->fetch_assoc()) {
                $priceMap[(int)$row['id']] = (int)$row['price'];
            }
            $priceStmt->close();

            foreach ($items as &$item) {
                $menuItemId = (int)($item['menu_item_id'] ?? 0);
                $qty = max(0, (int)($item['quantity'] ?? 0));
                if ($qty <= 0 || !isset($priceMap[$menuItemId])) {
                    continue;
                }

                $item['unit_price'] = $priceMap[$menuItemId];
                $totalAmount += $priceMap[$menuItemId] * $qty;
            }
            unset($item);
        }

        if ($totalAmount <= 0) {
            $conn->rollback();
            $conn->close();
            ResponseService::error('Món đặt trước không hợp lệ.', 422);
        }

        $depositAmount = ceil($totalAmount * 0.3);
        $status = 'awaiting_payment';
    }

    $hasPreorderInt = $hasPreorder ? 1 : 0;
    $paymentStatus = 'pending';
    $tableNumberInt = $tableId;

    $sql = "INSERT INTO bookings (name, phone, date, time, guests, floor, table_number, table_id, user_id, status, has_preorder, total_amount, deposit_amount, payment_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmtIns = $conn->prepare($sql);
    $stmtIns->bind_param("ssssisiiisidds", $name, $phone, $date, $time, $guestsInt, $floor, $tableNumberInt, $tableId, $userId, $status, $hasPreorderInt, $totalAmount, $depositAmount, $paymentStatus);
    $stmtIns->execute();
    $bookingId = (int)$stmtIns->insert_id;
    $stmtIns->close();

    if ($hasPreorder && !empty($items)) {
        $itemStmt = $conn->prepare("INSERT INTO booking_items (booking_id, menu_item_id, quantity, unit_price) VALUES (?, ?, ?, ?)");
        foreach ($items as $item) {
            if (!isset($item['unit_price'])) {
                continue;
            }
            $qty = (int)$item['quantity'];
            $menuItemId = (int)$item['menu_item_id'];
            $unitPrice = (float)$item['unit_price'];
            $itemStmt->bind_param("iiid", $bookingId, $menuItemId, $qty, $unitPrice);
            $itemStmt->execute();
        }
        $itemStmt->close();
    }

    $conn->commit();

    require_once ROOT_PATH . '/api/services/email_service.php';
    $emailSent = false;
    if (!$hasPreorder) {
        $emailSent = EmailService::sendBookingConfirmationEmail($conn, $bookingId);
    }

    if ($hasPreorder && $depositAmount > 0) {
        $paymentContent = "BKG" . $bookingId;
        $bankAccount = defined('SEPAY_VA_ACCOUNT') ? SEPAY_VA_ACCOUNT : '';
        $bankId = defined('SEPAY_BANK_NAME') ? SEPAY_BANK_NAME : 'MBBank';
        $payUrl = "https://qr.sepay.vn/img?acc={$bankAccount}&bank={$bankId}&amount={$depositAmount}&des={$paymentContent}";

        $conn->close();
        ResponseService::success([
            'require_payment' => true,
            'booking_id' => $bookingId,
            'deposit_amount' => $depositAmount,
            'payUrl' => $payUrl,
            'message' => 'Vui lòng thanh toán tiền cọc ' . number_format($depositAmount) . 'đ để xác nhận giữ chỗ.',
        ]);
    }

    $conn->close();
    ResponseService::success([
        'require_payment' => false,
        'message' => 'Đặt bàn thành công! ' . ($emailSent ? 'Vui lòng kiểm tra email xác nhận.' : ''),
    ]);
} catch (InvalidArgumentException $e) {
    ResponseService::error($e->getMessage(), $e->getCode() ?: 400);
} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        try {
            $conn->rollback();
            $conn->close();
        } catch (Throwable $ignored) {
        }
    }
    error_log('[BookTable] Exception: ' . $e->getMessage());
    ResponseService::error('Lỗi hệ thống. Vui lòng thử lại sau.', 500);
}
