<?php
// CODE-07: Guard ob_clean() với ob_get_level()
if (ob_get_level()) ob_clean();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../config/constants.php';
require_once ROOT_PATH . '/config/db.php';
require_once __DIR__ . '/../../api/services/csrf_service.php';
require_once __DIR__ . '/../../api/services/rate_limit_service.php';

// Validate CSRF
CsrfService::validateRequest();

$conn = getDbConnection();

// Auto-detect JSON or POST
$contentType = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';
if (strpos($contentType, 'application/json') !== false) {
    $data = json_decode(file_get_contents('php://input'), true);
} else {
    $data = $_POST;
}

$name   = $data['name']   ?? '';
$phone  = $data['phone']  ?? '';
$date   = $data['date']   ?? '';
$time   = $data['time']   ?? '';
$guests = $data['guests'] ?? '';
$floor  = $data['floor']  ?? '';
$table_id = $data['table_id'] ?? null;
$has_preorder = !empty($data['has_preorder']);
$items = $data['items'] ?? [];

$bookingDateTime = DateTime::createFromFormat('Y-m-d H:i', (string)$date . ' ' . (string)$time, new DateTimeZone('Asia/Ho_Chi_Minh'));

if (!$name || !$phone || !$date || !$time || !$guests) {
    echo json_encode(['success' => false, 'message' => 'Dữ liệu không đầy đủ.']);
    exit;
}

if (!preg_match('/^0[0-9]{9}$/', (string)$phone)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'So dien thoai khong hop le.']);
    exit;
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$date) || !preg_match('/^\d{2}:\d{2}$/', (string)$time)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Ngay hoac gio khong hop le.']);
    exit;
}

if (!$bookingDateTime || $bookingDateTime < new DateTime('now', new DateTimeZone('Asia/Ho_Chi_Minh'))) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Khong the dat ban trong qua khu.']);
    exit;
}

$hour = (int)$bookingDateTime->format('H');
if ($hour < 8 || $hour > 22) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Vui long chon gio trong khung 08:00 - 22:00.']);
    exit;
}

if (!$table_id) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng chọn bàn!']);
    exit;
}

$guestsInt = (int)$guests;
$table_number_int = (int)$table_id; 

if ($guestsInt < 1 || $guestsInt > 20) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'So khach khong hop le.']);
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Phiên đăng nhập hết hạn. Vui lòng đăng nhập lại.']);
    exit;
}

if (!RateLimitService::check('book_table_' . $user_id, 5, 60)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Thao tác quá nhanh. Vui lòng đợi 1 phút.']);
    exit;
}

// 1. Check duplicate & overlapping (+/- 2 hours)
$conn->begin_transaction();

$tableStmt = $conn->prepare("SELECT capacity FROM tables WHERE id = ? FOR UPDATE");
$tableStmt->bind_param("i", $table_id);
$tableStmt->execute();
$tableRes = $tableStmt->get_result();
$table = $tableRes->fetch_assoc();
$tableStmt->close();

if (!$table) {
    $conn->rollback();
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Ban khong ton tai.']);
    $conn->close();
    exit;
}

if ($guestsInt > (int)$table['capacity']) {
    $conn->rollback();
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'So khach vuot qua suc chua cua ban.']);
    $conn->close();
    exit;
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
$stmt->bind_param("iss", $table_id, $date, $time);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows > 0) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Bàn này đã được đặt trong khoảng 2 tiếng gần thời gian bạn chọn. Vui lòng chọn bàn/thời gian khác.']);
    $stmt->close();
    $conn->close();
    exit;
}
$stmt->close();

// 2. Tính toán tiền nếu có Preorder
$total_amount = 0;
$deposit_amount = 0;
$status = 'pending'; // Default

try {
    if ($has_preorder && !empty($items)) {
        // PERF-01: Bulk query thay vì N+1 query trong vòng lặp
        $ids = array_map('intval', array_column($items, 'menu_item_id'));
        $ids = array_filter($ids); // loại bỏ id = 0

        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $types        = str_repeat('i', count($ids));
            $priceStmt    = $conn->prepare("SELECT id, price FROM menu_items WHERE id IN ($placeholders) AND is_active = 1 AND deleted_at IS NULL");
            $priceStmt->bind_param($types, ...$ids);
            $priceStmt->execute();
            $priceResult = $priceStmt->get_result();

            // Build price map
            $priceMap = [];
            while ($row = $priceResult->fetch_assoc()) {
                $priceMap[$row['id']] = (int) $row['price'];
            }
            $priceStmt->close();

            // Apply real prices to items
            foreach ($items as &$item) {
                $menu_item_id = (int) $item['menu_item_id'];
                $qty          = max(0, (int) $item['quantity']);
                if ($qty <= 0 || !isset($priceMap[$menu_item_id])) continue;

                $item['unit_price'] = $priceMap[$menu_item_id];
                $total_amount      += $priceMap[$menu_item_id] * $qty;
            }
            unset($item);
        }
        
        // Cọc 30% cho tổng món ăn
        $deposit_amount = ceil($total_amount * 0.3); 
        $status = 'awaiting_payment';
    }

    // 3. Insert Booking
    $has_preorder_int = $has_preorder ? 1 : 0;
    $payment_status = 'pending';

    $sql = "INSERT INTO bookings (name, phone, date, time, guests, floor, table_number, table_id, user_id, status, has_preorder, total_amount, deposit_amount, payment_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmtIns = $conn->prepare($sql);
    $stmtIns->bind_param("ssssisiiisidds", $name, $phone, $date, $time, $guestsInt, $floor, $table_number_int, $table_id, $user_id, $status, $has_preorder_int, $total_amount, $deposit_amount, $payment_status);
    $stmtIns->execute();
    $booking_id = $stmtIns->insert_id;
    $stmtIns->close();

    // 4. Insert Booking Items
    if ($has_preorder && !empty($items)) {
        $itemStmt = $conn->prepare("INSERT INTO booking_items (booking_id, menu_item_id, quantity, unit_price) VALUES (?, ?, ?, ?)");
        foreach ($items as $item) {
            if (!isset($item['unit_price'])) continue;
            $qty = (int)$item['quantity'];
            $menu_item_id = (int)$item['menu_item_id'];
            $unit_price = (float)$item['unit_price'];
            $itemStmt->bind_param("iiid", $booking_id, $menu_item_id, $qty, $unit_price);
            $itemStmt->execute();
        }
        $itemStmt->close();
    }
    
    $conn->commit();

    // --- SEND EMAIL NOTIFICATION ---
    require_once __DIR__ . '/../../api/services/email_service.php';
    $uStmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
    $uStmt->bind_param("i", $user_id);
    $uStmt->execute();
    $uRes = $uStmt->get_result();
    $emailSent = false;
    if ($row = $uRes->fetch_assoc()) {
        $email = $row['email'];
        if ($email) {
            $subject = "[Dượng Bầu] Xác nhận yêu cầu đặt bàn";
            $preorderText = $has_preorder ? "Bạn đã đặt món trước. Vui lòng thanh toán tiền cọc để xác nhận." : "";
            $body = "<h2>Cảm ơn bạn đã yêu cầu đặt bàn!</h2>
                        <p>Xin chào <strong>$name</strong>,</p>
                        <p>Yêu cầu đặt bàn của bạn đã được ghi nhận:</p>
                        <ul>
                        <li><strong>Ngày:</strong> $date</li>
                        <li><strong>Giờ:</strong> $time</li>
                        <li><strong>Số khách:</strong> $guests</li>
                        <li><strong>Bàn:</strong> $table_number_int (Sảnh $floor)</li>
                        </ul>
                        <p>$preorderText</p>
                        <p>Trân trọng,<br>Nhà Hàng Cơm Quê Dượng Bầu</p>";
            if (EmailService::send($email, $subject, $body)) {
                $emailSent = true;
            }
        }
    }
    $uStmt->close();

    // Return response
    if ($has_preorder && $deposit_amount > 0) {
        // Tạo mã QR bằng SePay API chuẩn theo file config (Or generic VietQR code)
        // SePay free account often uses VietQR wrapper or their own dynamic QR
        $payment_content = "BKG" . $booking_id; 
        
        $bank_account = defined('SEPAY_VA_ACCOUNT') ? SEPAY_VA_ACCOUNT : '';
        $bank_id      = defined('SEPAY_BANK_NAME')  ? SEPAY_BANK_NAME  : 'MBBank';
        $payUrl = "https://qr.sepay.vn/img?acc={$bank_account}&bank={$bank_id}&amount={$deposit_amount}&des={$payment_content}";

        echo json_encode([
            'success' => true,
            'require_payment' => true,
            'booking_id' => $booking_id,
            'deposit_amount' => $deposit_amount,
            'payUrl' => $payUrl,
            'message' => 'Vui lòng thanh toán tiền cọc ' . number_format($deposit_amount) . 'đ để xác nhận giữ chỗ.'
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'require_payment' => false,
            'message' => 'Đặt bàn thành công! ' . ($emailSent ? 'Vui lòng kiểm tra email.' : '')
        ]);
    }

} catch (Exception $e) {
    $conn->rollback();
    error_log('[BookTable] Exception: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống. Vui lòng thử lại.']);
}

$conn->close();
?>
