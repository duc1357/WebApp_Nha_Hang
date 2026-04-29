<?php
ob_clean();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

ini_set('log_errors', 1);
// [SECURITY] Log ra thư mục /logs/ được bảo vệ bởi .htaccess (không trong web root api/)
ini_set('error_log', dirname(__DIR__, 2) . '/logs/payment_error.log');
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../config/constants.php';
require_once ROOT_PATH . '/config/db.php';
require_once __DIR__ . '/../../api/services/csrf_service.php';

// Validate CSRF
CsrfService::validateRequest();

$conn = getDbConnection();

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['items']) || !isset($data['total']) || !isset($data['payment_method'])) {
    echo json_encode(['success' => false, 'message' => '❌ Dữ liệu không hợp lệ']);
    exit;
}

$items          = $data['items'];
// Validate Total to prevent frontend manipulation (ideal world recalculate from DB, but for now stick to basic refactor)
$total          = (int)$data['total'];
$payment_method = $data['payment_method'];

// NEW FIELDS
$address  = isset($data['address']) ? trim($data['address']) : null;
$table_id = isset($data['table_id']) ? trim($data['table_id']) : null; // Changed to string
$note     = isset($data['note']) ? trim($data['note']) : null;
if ($table_id === '') $table_id = null;

// FIX: Prioritize Session User ID to prevent spoofing
$user_id = $_SESSION['user_id'] ?? null; 

// If table_id is provided but is a string like "R2", look up its actual ID
if ($table_id !== null && !is_numeric($table_id)) {
    $search = '%' . trim($table_id) . '%';
    $stmtTable = $conn->prepare("SELECT id FROM tables WHERE name LIKE ? LIMIT 1");
    if ($stmtTable) {
        $stmtTable->bind_param("s", $search);
        $stmtTable->execute();
        $resultTable = $stmtTable->get_result();
        if ($row = $resultTable->fetch_assoc()) {
            $table_id = (int)$row['id'];
        } else {
            echo json_encode(['success' => false, 'message' => '❌ Tên bàn không hợp lệ hoặc không tồn tại. Thử lại "R2" hoặc "Bàn R2"']);
            exit;
        }
        $stmtTable->close();
    }
}

// 1) Tạo đơn hàng (orders)
// Sửa lỗi logic: đảm bảo dùng created_at và status
$sql = "INSERT INTO orders (user_id, total_amount, payment_method, status, address, table_id, note, created_at)
        VALUES (?, ?, ?, 'pending', ?, ?, ?, NOW())";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => '❌ Lỗi chuẩn bị câu lệnh: ' . $conn->error]);
    exit;
}
$stmt->bind_param("iissss", $user_id, $total, $payment_method, $address, $table_id, $note);

if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => '❌ Lỗi tạo đơn hàng: ' . $stmt->error]);
    exit;
}

$order_id = $stmt->insert_id;
$stmt->close();

// 2) Lưu chi tiết order_items
$sql_item = "INSERT INTO order_items (order_id, menu_item_id, quantity, unit_price)
             VALUES (?, ?, ?, ?)";
$stmt_item = $conn->prepare($sql_item);
if (!$stmt_item) {
    echo json_encode(['success' => false, 'message' => '❌ Lỗi chuẩn bị câu lệnh chi tiết: ' . $conn->error]);
    exit;
}

$total_calculated = 0;

foreach ($items as $item) {
    // FIX: Ensure valid menu_item_id
    $menu_item_id = isset($item['id']) ? (int)$item['id'] : 0;
    if ($menu_item_id <= 0) continue; // Skip invalid items

    $qty = (int)$item['quantity'];
    if ($qty <= 0) continue;

    // SECURITY FIX: Fetch price from Database
    $priceStmt = $conn->prepare("SELECT price FROM menu_items WHERE id = ?");
    $priceStmt->bind_param("i", $menu_item_id);
    $priceStmt->execute();
    $resPrice = $priceStmt->get_result();
    
    if ($rowPrice = $resPrice->fetch_assoc()) {
        $real_price = (int)$rowPrice['price'];
        
        // Add to total
        $total_calculated += ($real_price * $qty);

        // Insert Item
        $stmt_item->bind_param("iiii", $order_id, $menu_item_id, $qty, $real_price);
        if (!$stmt_item->execute()) {
             error_log("Failed to insert item $menu_item_id for order $order_id: " . $stmt_item->error);
        }
    } else {
        // Item not found (manipulated ID?)
        error_log("Invalid menu_item_id $menu_item_id in order $order_id");
    }
    $priceStmt->close();
}
$stmt_item->close();

// --- VOUCHER HANDLING ---
$voucher_code = isset($data['voucher_code']) ? strtoupper(trim($data['voucher_code'])) : '';
$discount_amount = 0;
$final_total = $total_calculated;

if ($voucher_code) {
    // Check voucher validity
    $vSql = "SELECT * FROM vouchers WHERE code = ? AND is_active = 1";
    $vStmt = $conn->prepare($vSql);
    $vStmt->bind_param("s", $voucher_code);
    $vStmt->execute();
    $vResult = $vStmt->get_result();

    if ($vRow = $vResult->fetch_assoc()) {
        $now = new DateTime();
        $expire = new DateTime($vRow['expire_date']);

        // Check conditions
        if ($now <= $expire && 
            $vRow['used_count'] < $vRow['usage_limit'] && 
            $total_calculated >= $vRow['min_order_value']) {
            
            // Calculate Discount
            if ($vRow['discount_type'] === 'percent') {
                $discount_amount = ($total_calculated * $vRow['discount_value']) / 100;
            } else {
                $discount_amount = $vRow['discount_value'];
            }

            // Max discount limit check (optional, here we cap at total)
            if ($discount_amount > $total_calculated) $discount_amount = $total_calculated;

            $final_total = $total_calculated - $discount_amount;

            // Increment usage count (prepared statement – tránh SQL injection)
            $upV = $conn->prepare("UPDATE vouchers SET used_count = used_count + 1 WHERE id = ?");
            $upV->bind_param("i", $vRow['id']);
            $upV->execute();
            $upV->close();
        } else {
            // Invalid voucher (expired or limit reached or min value)
            // We verify silently or could return warning, but for security, if backend calc differs significantly, just ignore or log.
            // Here we just ignore invalid voucher to avoid blocking payment, but frontend should have checked.
            $voucher_code = null;
        }
    } else {
        $voucher_code = null;
    }
    $vStmt->close();
}

// Update Orders table with Real Total, Discount, and Voucher
// (Override whatever frontend sent as 'total')
$updateTotalKey = $conn->prepare("UPDATE orders SET total_amount = ?, discount_amount = ?, final_total = ?, voucher_code = ? WHERE id = ?");
$updateTotalKey->bind_param("dddsi", $total_calculated, $discount_amount, $final_total, $voucher_code, $order_id);
$updateTotalKey->execute();
$updateTotalKey->close();

// --- EMAIL NOTIFICATION ---
require_once __DIR__ . '/../../api/services/email_service.php';
$emailStmt = $conn->prepare("SELECT email, name FROM users WHERE id = ?");
$emailStmt->bind_param("i", $user_id);
$emailStmt->execute();
$emailRes = $emailStmt->get_result();

if ($uRow = $emailRes->fetch_assoc()) {
    $uEmail = $uRow['email'];
    $uName = $uRow['name'];
    if ($uEmail) {
        $subject = "[Dượng Bầu] Xác nhận đơn hàng #$order_id";
        
        // Build Item List
        $itemListHtml = "<ul>";
        foreach ($items as $itm) {
            $iName = htmlspecialchars($itm['name']);
            $iQty = $itm['quantity'];
            $iPrice = number_format($itm['price']);
            $itemListHtml .= "<li>$iName x $iQty ($iPrice đ)</li>";
        }
        $itemListHtml .= "</ul>";
        
        $payStr = ($payment_method === 'bank_transfer') ? 'Chuyển khoản (QR)' : 'Tiền mặt';
        $totalStr = number_format($final_total);
        $discountStr = ($discount_amount > 0) ? "<p>Giảm giá: -" . number_format($discount_amount) . " đ</p>" : "";

        $body = "<h2>Cảm ơn bạn đã đặt món!</h2>
                 <p>Xin chào <strong>$uName</strong>,</p>
                 <p>Đơn hàng <strong>#$order_id</strong> của bạn đã được ghi nhận.</p>
                 <h3>Chi tiết đơn hàng:</h3>
                 $itemListHtml
                 <hr>
                 $discountStr
                 <p><strong>Tổng cộng: $totalStr đ</strong></p>
                 <p>Phương thức: $payStr</p>
                 <p>Chúng tôi sẽ sớm giao món/phục vụ bạn!</p>";
                 
        EmailService::send($uEmail, $subject, $body);
    }
}
$emailStmt->close();
// --------------------------

// Update SePay Amount if needed
if ($payment_method === 'bank_transfer') {
    $total = $final_total; // Update variable for QR Code generation
}

// 3) Xử lý phản hồi theo phương thức thanh toán
$response = [
    'success'  => true,
    'order_id' => $order_id
];

if ($payment_method === 'bank_transfer') {
    // Thông tin SePay (lấy từ config/constants đã load từ .env)
    $sepay_va_account_id = defined('SEPAY_VA_ACCOUNT') ? SEPAY_VA_ACCOUNT : '';
    $sepay_bank_name     = defined('SEPAY_BANK_NAME')  ? SEPAY_BANK_NAME  : 'MBBank';

    $payment_content = "DH" . $order_id;
    $amount          = $total;

    $qrUrl = "https://qr.sepay.vn/img"
           . "?acc=" . urlencode($sepay_va_account_id)
           . "&bank=" . urlencode($sepay_bank_name)
           . "&amount=" . urlencode($amount)
           . "&des=" . urlencode($payment_content);

    $response['payUrl']  = $qrUrl;
    $response['message'] = "Vui lòng quét mã QR để thanh toán.";
} else {
    $response['message'] = "Đặt đơn thành công. Thanh toán khi nhận món.";
}

$conn->close();
echo json_encode($response);