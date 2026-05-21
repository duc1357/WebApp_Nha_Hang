<?php
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

require_once __DIR__ . '/../../config/constants.php';
require_once ROOT_PATH . '/config/db.php';

$action = $argv[1] ?? '';
$conn = getDbConnection();

function out(array $data): void {
    echo json_encode($data, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(0);
}

function scalarQuery(mysqli $conn, string $sql, string $types = '', mixed ...$params): mixed {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException($conn->error);
    }
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_row();
    $stmt->close();
    return $row[0] ?? null;
}

$prefix = 'SMOKE_WEBHOOK_';

try {
    if ($action === 'setup') {
        $userId = (int)scalarQuery($conn, "SELECT id FROM users WHERE email = 'customer.demo@example.test' LIMIT 1");
        if ($userId <= 0) {
            $userId = (int)scalarQuery($conn, "SELECT id FROM users WHERE role = 'customer' ORDER BY id LIMIT 1");
        }
        if ($userId <= 0) {
            throw new RuntimeException('No customer user exists for webhook smoke fixture.');
        }

        $menuId = (int)scalarQuery($conn, "SELECT id FROM menu_items WHERE is_active = 1 AND deleted_at IS NULL ORDER BY id LIMIT 1");
        if ($menuId <= 0) {
            throw new RuntimeException('No active menu item exists for webhook smoke fixture.');
        }

        $voucherCode = $prefix . bin2hex(random_bytes(4));
        $conn->begin_transaction();

        $stmt = $conn->prepare("INSERT INTO vouchers (code, description, discount_type, discount_value, min_order_value, expire_date, usage_limit, used_count, is_active) VALUES (?, 'Smoke webhook fixture', 'fixed', 1000, 0, DATE_ADD(NOW(), INTERVAL 1 DAY), 5, 0, 1)");
        $stmt->bind_param('s', $voucherCode);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("INSERT INTO orders (user_id, total_amount, discount_amount, final_total, payment_method, status, voucher_code, created_at) VALUES (?, 50000, 1000, 49000, 'bank_transfer', 'pending', ?, NOW())");
        $stmt->bind_param('is', $userId, $voucherCode);
        $stmt->execute();
        $orderId = (int)$stmt->insert_id;
        $stmt->close();

        $stmt = $conn->prepare("INSERT INTO order_items (order_id, menu_item_id, quantity, unit_price) VALUES (?, ?, 1, 50000)");
        $stmt->bind_param('ii', $orderId, $menuId);
        $stmt->execute();
        $stmt->close();

        $date = (new DateTimeImmutable('+45 days'))->format('Y-m-d');
        $stmt = $conn->prepare("INSERT INTO bookings (table_id, name, phone, date, time, guests, status, floor, table_number, user_id, has_preorder, total_amount, deposit_amount, payment_status) VALUES (1, 'Smoke Webhook', '0123456789', ?, '18:00:00', 2, 'awaiting_payment', 'Smoke', 1, ?, 1, 90000, 27000, 'pending')");
        $stmt->bind_param('si', $date, $userId);
        $stmt->execute();
        $bookingId = (int)$stmt->insert_id;
        $stmt->close();

        $conn->commit();
        out([
            'order_id' => $orderId,
            'booking_id' => $bookingId,
            'voucher_code' => $voucherCode,
            'order_amount' => 49000,
            'booking_amount' => 27000,
        ]);
    }

    if ($action === 'webhook-config') {
        if (SEPAY_WEBHOOK_TOKEN === '') {
            throw new RuntimeException('SEPAY_WEBHOOK_TOKEN is not configured.');
        }
        out([
            'token' => SEPAY_WEBHOOK_TOKEN,
            'bank' => SEPAY_BANK_NAME,
            'account' => SEPAY_VA_ACCOUNT,
        ]);
    }

    if ($action === 'status') {
        $orderId = (int)($argv[2] ?? 0);
        $bookingId = (int)($argv[3] ?? 0);
        $voucherCode = (string)($argv[4] ?? '');
        out([
            'order_status' => scalarQuery($conn, 'SELECT status FROM orders WHERE id = ?', 'i', $orderId),
            'booking_status' => scalarQuery($conn, 'SELECT status FROM bookings WHERE id = ?', 'i', $bookingId),
            'booking_payment_status' => scalarQuery($conn, 'SELECT payment_status FROM bookings WHERE id = ?', 'i', $bookingId),
            'voucher_used_count' => (int)scalarQuery($conn, 'SELECT used_count FROM vouchers WHERE code = ?', 's', $voucherCode),
        ]);
    }

    if ($action === 'cleanup') {
        $orderId = (int)($argv[2] ?? 0);
        $bookingId = (int)($argv[3] ?? 0);
        $voucherCode = (string)($argv[4] ?? '');
        $conn->begin_transaction();
        $stmt = $conn->prepare('DELETE FROM order_items WHERE order_id = ?');
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $stmt->close();
        $stmt = $conn->prepare('DELETE FROM orders WHERE id = ?');
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $stmt->close();
        $stmt = $conn->prepare('DELETE FROM booking_items WHERE booking_id = ?');
        $stmt->bind_param('i', $bookingId);
        $stmt->execute();
        $stmt->close();
        $stmt = $conn->prepare('DELETE FROM bookings WHERE id = ?');
        $stmt->bind_param('i', $bookingId);
        $stmt->execute();
        $stmt->close();
        $stmt = $conn->prepare('DELETE FROM vouchers WHERE code = ? AND code LIKE "SMOKE_WEBHOOK_%"');
        $stmt->bind_param('s', $voucherCode);
        $stmt->execute();
        $stmt->close();
        $conn->commit();
        out(['success' => true]);
    }

    throw new RuntimeException('Unknown action.');
} catch (Throwable $e) {
    if ($conn instanceof mysqli) {
        try {
            $conn->rollback();
        } catch (Throwable $ignored) {
        }
    }
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
} finally {
    if ($conn instanceof mysqli) {
        $conn->close();
    }
}
