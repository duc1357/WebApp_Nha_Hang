<?php
// api/services/OrderService.php

class OrderService {
    private const MAX_ITEM_QUANTITY = 99;

    /**
     * Tìm ID bàn bằng tên bàn hoặc ID.
     * Khớp chính xác (exact match) thay vì dùng LIKE để tránh sai sót.
     *
     * @param mysqli $conn
     * @param string|int|null $table_input
     * @return int|null
     */
    public static function getTableId($conn, $table_input) {
        if ($table_input === null || trim((string)$table_input) === '') {
            return null;
        }

        $table_input = trim((string)$table_input);

        // Nếu là số, thử tìm theo ID trước
        if (is_numeric($table_input)) {
            $stmt = $conn->prepare("SELECT id FROM tables WHERE id = ? LIMIT 1");
            $stmt->bind_param("i", $table_input);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $stmt->close();
                return (int)$row['id'];
            }
            $stmt->close();
        }

        // Nếu không tìm thấy theo ID, hoặc đầu vào là chuỗi (VD: "Bàn R2"), tìm theo tên chính xác
        $stmt = $conn->prepare("SELECT id FROM tables WHERE name = ? LIMIT 1");
        $stmt->bind_param("s", $table_input);
        $stmt->execute();
        $res = $stmt->get_result();
        $table_id = null;
        if ($row = $res->fetch_assoc()) {
            $table_id = (int)$row['id'];
        }
        $stmt->close();

        // Nếu vẫn không thấy, tự động thêm chữ "Bàn " vào trước để hỗ trợ (ví dụ user gõ "R2" -> tìm "Bàn R2")
        if ($table_id === null) {
            $table_input_with_prefix = "Bàn " . ltrim($table_input, "Bànban ");
            $stmt = $conn->prepare("SELECT id FROM tables WHERE name = ? LIMIT 1");
            $stmt->bind_param("s", $table_input_with_prefix);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $table_id = (int)$row['id'];
            }
            $stmt->close();
        }

        return $table_id;
    }

    /**
     * Tính toán tổng tiền của đơn hàng từ Database để tránh Client truyền dữ liệu ảo.
     * Tránh lỗi N+1 Query bằng cách dùng WHERE id IN (...)
     *
     * @param mysqli $conn
     * @param array $items
     * @return array Bảng tổng hợp ['total_calculated' => int, 'valid_items' => array]
     */
    public static function calculateOrderTotal($conn, $items) {
        if (empty($items)) {
            return ['total_calculated' => 0, 'valid_items' => []];
        }

        // Tạo mảng nhóm số lượng theo ID
        $itemQtyMap = [];
        foreach ($items as $item) {
            $id = isset($item['id']) ? (int)$item['id'] : (int)($item['menu_item_id'] ?? 0);
            $qty = isset($item['quantity']) ? (int)$item['quantity'] : 0;
            if ($id > 0 && $qty > 0) {
                $qty = min($qty, self::MAX_ITEM_QUANTITY);
                if (!isset($itemQtyMap[$id])) {
                    $itemQtyMap[$id] = 0;
                }
                $itemQtyMap[$id] += $qty;
            }
        }

        if (empty($itemQtyMap)) {
            return ['total_calculated' => 0, 'valid_items' => []];
        }

        $ids = array_keys($itemQtyMap);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));

        $stmt = $conn->prepare("SELECT id, price FROM menu_items WHERE id IN ($placeholders) AND is_active = 1 AND deleted_at IS NULL");
        $stmt->bind_param($types, ...$ids);
        $stmt->execute();
        $res = $stmt->get_result();

        $total_calculated = 0;
        $valid_items = [];

        while ($row = $res->fetch_assoc()) {
            $id = (int)$row['id'];
            $price = (int)$row['price'];
            $qty = $itemQtyMap[$id];

            $total_calculated += ($price * $qty);
            $valid_items[] = [
                'menu_item_id' => $id,
                'quantity' => $qty,
                'unit_price' => $price
            ];
        }

        $stmt->close();

        return [
            'total_calculated' => $total_calculated,
            'valid_items' => $valid_items
        ];
    }

    /**
     * Kiểm tra và áp dụng Voucher
     *
     * @param mysqli $conn
     * @param string $voucher_code
     * @param int|float $total_calculated
     * @return array ['discount_amount' => float, 'final_total' => float, 'applied_voucher' => string|null]
     */
    public static function validateAndApplyVoucher($conn, $voucher_code, $total_calculated) {
        $discount_amount = 0;
        $final_total = $total_calculated;
        $applied_voucher = null;

        if (empty($voucher_code)) {
            return [
                'discount_amount' => 0,
                'final_total' => $total_calculated,
                'applied_voucher' => null
            ];
        }

        $voucher_code = strtoupper(trim((string)$voucher_code));

        $vSql = "SELECT code, discount_type, discount_value, min_order_value, expire_date, usage_limit, used_count
                 FROM vouchers
                 WHERE code = ? AND is_active = 1";
        $vStmt = $conn->prepare($vSql);
        $vStmt->bind_param("s", $voucher_code);
        $vStmt->execute();
        $vResult = $vStmt->get_result();

        if ($vRow = $vResult->fetch_assoc()) {
            $now = new DateTime();
            $expire = new DateTime($vRow['expire_date']);

            if ($now <= $expire &&
                $vRow['used_count'] < $vRow['usage_limit'] &&
                $total_calculated >= $vRow['min_order_value']) {

                // Tính toán giảm giá
                if ($vRow['discount_type'] === 'percent') {
                    $percent = max(0, min(100, (float)$vRow['discount_value']));
                    $discount_amount = ($total_calculated * $percent) / 100;
                } else {
                    $discount_amount = max(0, (float)$vRow['discount_value']);
                }

                // Capping giảm giá tối đa bằng với tổng đơn
                if ($discount_amount > $total_calculated) {
                    $discount_amount = $total_calculated;
                }

                $final_total = max(0, $total_calculated - $discount_amount);
                $applied_voucher = $voucher_code;

                // Increment usage only after a confirmed payment/webhook.
                // This prevents unpaid orders from exhausting voucher quota.
            }
        }
        $vStmt->close();

        return [
            'discount_amount' => $discount_amount,
            'final_total' => $final_total,
            'applied_voucher' => $applied_voucher
        ];
    }

    public static function markOrderPaid($conn, int $orderId, string $paymentMethod = 'bank_transfer'): array {
        $stmt = $conn->prepare("SELECT id, total_amount, final_total, status, voucher_code FROM orders WHERE id = ? FOR UPDATE");
        if (!$stmt) {
            throw new RuntimeException('Prepare order lookup failed: ' . $conn->error);
        }

        $stmt->bind_param("i", $orderId);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$order) {
            return ['success' => false, 'message' => 'Order not found', 'already_paid' => false];
        }

        if ($order['status'] === 'paid') {
            return ['success' => true, 'message' => 'Order already paid', 'already_paid' => true, 'order' => $order];
        }

        if (!empty($order['voucher_code'])) {
            $voucherStmt = $conn->prepare(
                "UPDATE vouchers
                 SET used_count = used_count + 1
                 WHERE code = ?
                   AND is_active = 1
                   AND expire_date >= NOW()
                   AND used_count < usage_limit"
            );
            if (!$voucherStmt) {
                throw new RuntimeException('Prepare voucher update failed: ' . $conn->error);
            }

            $voucherStmt->bind_param("s", $order['voucher_code']);
            $voucherStmt->execute();
            $voucherUpdated = $voucherStmt->affected_rows > 0;
            $voucherStmt->close();

            if (!$voucherUpdated) {
                return ['success' => false, 'message' => 'Voucher is no longer valid', 'already_paid' => false, 'order' => $order];
            }
        }

        $updateStmt = $conn->prepare("UPDATE orders SET status = 'paid', payment_method = ? WHERE id = ? AND status != 'paid'");
        if (!$updateStmt) {
            throw new RuntimeException('Prepare order update failed: ' . $conn->error);
        }

        $updateStmt->bind_param("si", $paymentMethod, $orderId);
        $updateStmt->execute();
        $updated = $updateStmt->affected_rows > 0;
        $updateStmt->close();

        return ['success' => $updated, 'message' => $updated ? 'Order updated' : 'Order was not updated', 'already_paid' => false, 'order' => $order];
    }
}
