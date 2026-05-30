<?php
require_once __DIR__ . '/../../config/mail_config.php';
require_once __DIR__ . '/../../lib/SimpleSMTP.php';

class EmailService {
    /**
     * Send an email
     * @param string $to Recipient email
     * @param string $subject Subject
     * @param string $body HTML Body
     * @return bool
     */
    public static function send($to, $subject, $body) {
        $smtp = new SimpleSMTP(MAIL_HOST, MAIL_USER, MAIL_PASS, MAIL_PORT);
        try {
            return $smtp->send($to, $subject, $body, MAIL_FROM_NAME);
        } catch (Exception $e) {
            error_log("Email Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Gửi email xác nhận đơn hàng chi tiết
     * @param mysqli $conn
     * @param int $orderId
     * @return bool
     */
    public static function sendOrderConfirmationEmail($conn, $orderId) {
        // 1. Lấy thông tin đơn hàng
        $stmt = $conn->prepare("SELECT user_id, total_amount, discount_amount, final_total, payment_method, address, table_id, note FROM orders WHERE id = ?");
        if (!$stmt) return false;
        $stmt->bind_param("i", $orderId);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$order) return false;

        $userId = (int)$order['user_id'];
        $totalAmount = (float)$order['total_amount'];
        $discountAmount = (float)$order['discount_amount'];
        $finalTotal = (float)$order['final_total'];
        $paymentMethod = $order['payment_method'];
        $address = $order['address'];
        $tableId = $order['table_id'];
        $note = $order['note'];

        // 2. Lấy thông tin user
        $uStmt = $conn->prepare("SELECT email, name FROM users WHERE id = ?");
        if (!$uStmt) return false;
        $uStmt->bind_param("i", $userId);
        $uStmt->execute();
        $user = $uStmt->get_result()->fetch_assoc();
        $uStmt->close();

        if (!$user || empty($user['email'])) return false;
        $uEmail = $user['email'];
        $uName = htmlspecialchars($user['name'] ?? 'Quý khách', ENT_QUOTES, 'UTF-8');

        // 3. Lấy chi tiết các món ăn
        $itemsStmt = $conn->prepare("
            SELECT oi.menu_item_id, oi.quantity, oi.unit_price, mi.name as menu_item_name
            FROM order_items oi
            LEFT JOIN menu_items mi ON oi.menu_item_id = mi.id
            WHERE oi.order_id = ?
        ");
        if (!$itemsStmt) return false;
        $itemsStmt->bind_param("i", $orderId);
        $itemsStmt->execute();
        $itemsRes = $itemsStmt->get_result();
        
        $itemListHtml = "<ul style='padding-left:20px; line-height: 1.6;'>";
        while ($itm = $itemsRes->fetch_assoc()) {
            $iQty = (int)$itm['quantity'];
            $iPrice = number_format((int)$itm['unit_price']);
            $itemName = htmlspecialchars($itm['menu_item_name'] ?? ('Món ăn #' . $itm['menu_item_id']), ENT_QUOTES, 'UTF-8');
            $itemListHtml .= "<li><strong>{$itemName}</strong> x {$iQty} ({$iPrice} đ)</li>";
        }
        $itemListHtml .= "</ul>";
        $itemsStmt->close();

        // 4. Biên soạn email
        $subject = "[Dượng Bầu] Xác nhận đơn hàng #" . $orderId;
        $payStr = ($paymentMethod === 'bank_transfer') ? 'Chuyển khoản (Đã thanh toán)' : 'Tiền mặt (Thanh toán khi nhận món)';
        $totalStr = number_format($finalTotal);
        $discountStr = ($discountAmount > 0) ? "<p style='color: #27ae60;'>Giảm giá: -" . number_format($discountAmount) . " đ</p>" : "";

        $deliverHtml = "";
        if (!empty($address)) {
            $deliverHtml = "<p>📍 <strong>Địa chỉ giao hàng:</strong> " . htmlspecialchars($address, ENT_QUOTES, 'UTF-8') . "</p>";
        } else if (!empty($tableId)) {
            // Lấy tên bàn
            $tStmt = $conn->prepare("SELECT name FROM tables WHERE id = ?");
            if ($tStmt) {
                $tStmt->bind_param("i", $tableId);
                $tStmt->execute();
                if ($tRow = $tStmt->get_result()->fetch_assoc()) {
                    $tableName = $tRow['name'];
                    $deliverHtml = "<p>🍽️ <strong>Đặt tại bàn:</strong> " . htmlspecialchars($tableName, ENT_QUOTES, 'UTF-8') . "</p>";
                }
                $tStmt->close();
            }
        }

        $noteHtml = !empty($note) ? "<p style='font-style: italic; color: #555;'>📝 <strong>Ghi chú:</strong> " . htmlspecialchars($note, ENT_QUOTES, 'UTF-8') . "</p>" : "";

        $body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e0e0e0; border-radius: 12px; background-color: #fcfbfa;'>
            <h2 style='color: #d4af37; border-bottom: 2px solid #d4af37; padding-bottom: 10px; margin-top: 0;'>Cảm ơn bạn đã đặt món tại Dượng Bầu!</h2>
            <p>Xin chào <strong>{$uName}</strong>,</p>
            <p>Đơn hàng <strong>#{$orderId}</strong> của bạn đã được xác nhận thành công.</p>
            
            <div style='background-color: #fdfaf2; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #d4af37;'>
                <h3 style='margin-top: 0; color: #8a6d3b;'>Chi tiết đơn hàng:</h3>
                {$itemListHtml}
                <hr style='border: 0; border-top: 1px dashed #d4af37; margin: 15px 0;'>
                {$discountStr}
                <p style='font-size: 1.1em; margin-bottom: 5px;'><strong>Tổng thanh toán: <span style='color: #e67e22;'>{$totalStr} đ</span></strong></p>
                <p style='margin-top: 0;'>💳 <strong>Phương thức:</strong> {$payStr}</p>
                {$deliverHtml}
                {$noteHtml}
            </div>
            
            <p>Nhà hàng đang chuẩn bị món ăn ngon nóng hổi để phục vụ bạn sớm nhất.</p>
            <p style='margin-top: 30px; font-size: 0.9em; color: #7f8c8d; border-top: 1px solid #e0e0e0; padding-top: 15px; text-align: center;'>
                🍲 <strong>Nhà Hàng Cơm Quê Dượng Bầu</strong><br>
                Chúc quý khách có bữa ăn ngon miệng và trọn vị!
            </p>
        </div>";

        return self::send($uEmail, $subject, $body);
    }

    /**
     * Gửi email xác nhận đặt bàn chi tiết
     * @param mysqli $conn
     * @param int $bookingId
     * @return bool
     */
    public static function sendBookingConfirmationEmail($conn, $bookingId) {
        // 1. Lấy thông tin booking
        $stmt = $conn->prepare("SELECT name, phone, date, time, guests, floor, table_number, table_id, user_id, has_preorder, total_amount, deposit_amount, payment_status FROM bookings WHERE id = ?");
        if (!$stmt) return false;
        $stmt->bind_param("i", $bookingId);
        $stmt->execute();
        $booking = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$booking) return false;

        $name = htmlspecialchars($booking['name'], ENT_QUOTES, 'UTF-8');
        $phone = htmlspecialchars($booking['phone'], ENT_QUOTES, 'UTF-8');
        $date = htmlspecialchars($booking['date'], ENT_QUOTES, 'UTF-8');
        $time = htmlspecialchars($booking['time'], ENT_QUOTES, 'UTF-8');
        $guests = (int)$booking['guests'];
        $floor = htmlspecialchars($booking['floor'] ?? '', ENT_QUOTES, 'UTF-8');
        $tableNumber = (int)$booking['table_number'];
        $tableId = (int)$booking['table_id'];
        $userId = (int)$booking['user_id'];
        $hasPreorder = (int)$booking['has_preorder'];
        $totalAmount = (float)$booking['total_amount'];
        $depositAmount = (float)$booking['deposit_amount'];
        $paymentStatus = $booking['payment_status'];

        // Lấy tên bàn chính xác
        $tableName = 'Bàn #' . $tableNumber;
        $tStmt = $conn->prepare("SELECT name FROM tables WHERE id = ?");
        if ($tStmt) {
            $tStmt->bind_param("i", $tableId);
            $tStmt->execute();
            if ($tRow = $tStmt->get_result()->fetch_assoc()) {
                $tableName = $tRow['name'];
            }
            $tStmt->close();
        }

        // 2. Lấy thông tin user email
        $uStmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
        if (!$uStmt) return false;
        $uStmt->bind_param("i", $userId);
        $uStmt->execute();
        $uRow = $uStmt->get_result()->fetch_assoc();
        $uStmt->close();

        if (!$uRow || empty($uRow['email'])) return false;
        $uEmail = $uRow['email'];

        // 3. Chi tiết thực đơn đặt trước (nếu có)
        $preorderHtml = "";
        if ($hasPreorder) {
            $itemsStmt = $conn->prepare("
                SELECT bi.menu_item_id, bi.quantity, bi.unit_price, mi.name as menu_item_name
                FROM booking_items bi
                LEFT JOIN menu_items mi ON bi.menu_item_id = mi.id
                WHERE bi.booking_id = ?
            ");
            if ($itemsStmt) {
                $itemsStmt->bind_param("i", $bookingId);
                $itemsStmt->execute();
                $itemsRes = $itemsStmt->get_result();
                
                $preorderList = "<ul style='padding-left:20px; line-height: 1.6;'>";
                while ($itm = $itemsRes->fetch_assoc()) {
                    $iQty = (int)$itm['quantity'];
                    $iPrice = number_format((int)$itm['unit_price']);
                    $itemName = htmlspecialchars($itm['menu_item_name'] ?? ('Món ăn #' . $itm['menu_item_id']), ENT_QUOTES, 'UTF-8');
                    $preorderList .= "<li><strong>{$itemName}</strong> x {$iQty} ({$iPrice} đ)</li>";
                }
                $preorderList .= "</ul>";
                $itemsStmt->close();

                $depositStr = number_format($depositAmount);
                $totalStr = number_format($totalAmount);
                
                $payStatusStr = ($paymentStatus === 'partial' || $paymentStatus === 'paid') ? "<span style='color:green;'>Đã đặt cọc 30% ({$depositStr} đ)</span>" : "<span style='color:red;'>Chờ chuyển khoản cọc ({$depositStr} đ)</span>";

                $preorderHtml = "
                <div style='background-color: #fdfaf2; padding: 15px; border-radius: 8px; margin: 15px 0; border: 1px dashed #d4af37;'>
                    <h3 style='margin-top: 0; color: #8a6d3b;'>Thực đơn đặt trước & Tiền cọc:</h3>
                    {$preorderList}
                    <hr style='border: 0; border-top: 1px dashed #d4af37; margin: 10px 0;'>
                    <p style='margin: 5px 0;'>💵 <strong>Tổng tiền món ăn:</strong> {$totalStr} đ</p>
                    <p style='margin: 5px 0;'>💳 <strong>Trạng thái cọc:</strong> {$payStatusStr}</p>
                </div>";
            }
        }

        // 4. Biên soạn email
        $subject = "[Dượng Bầu] Xác nhận yêu cầu đặt bàn thành công";
        
        $body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e0e0e0; border-radius: 12px; background-color: #fcfbfa;'>
            <h2 style='color: #d4af37; border-bottom: 2px solid #d4af37; padding-bottom: 10px; margin-top: 0;'>Xác Nhận Đặt Bàn Thành Công!</h2>
            <p>Xin chào <strong>{$name}</strong>,</p>
            <p>Yêu cầu đặt bàn của quý khách đã được ghi nhận và giữ chỗ thành công:</p>
            
            <div style='background-color: #f5f6fa; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #3498db;'>
                <h3 style='margin-top: 0; color: #2c3e50;'>Thông tin đặt bàn:</h3>
                <ul style='list-style-type: none; padding-left: 0; line-height: 1.8; margin-bottom: 0;'>
                    <li>📅 <strong>Ngày:</strong> {$date}</li>
                    <li>⏰ <strong>Giờ:</strong> {$time}</li>
                    <li>👥 <strong>Số khách:</strong> {$guests} người</li>
                    <li>📌 <strong>Vị trí:</strong> Khu vực {$floor} - <strong>{$tableName}</strong></li>
                    <li>📞 <strong>Số điện thoại:</strong> {$phone}</li>
                </ul>
            </div>
            
            {$preorderHtml}
            
            <p>Nhà hàng hân hạnh được phục vụ bạn tại Dượng Bầu. Xin vui lòng đến đúng giờ đã hẹn. Nếu có bất kỳ thay đổi nào, vui lòng liên hệ hotline nhà hàng.</p>
            <p style='margin-top: 30px; font-size: 0.9em; color: #7f8c8d; border-top: 1px solid #e0e0e0; padding-top: 15px; text-align: center;'>
                🍲 <strong>Nhà Hàng Cơm Quê Dượng Bầu</strong><br>
                Hương vị quê nhà, đậm đà tình thân!
            </p>
        </div>";

        return self::send($uEmail, $subject, $body);
    }
}
