<?php
require_once dirname(__DIR__, 2) . '/api/services/response_service.php';
require_once __DIR__ . '/../../config/db.php';

$conn = getDbConnection();

// Lấy toàn bộ món trong menu_items (bao gồm đang bán & ngưng)
$sql = "SELECT id, name, description, price, image_url AS photo, is_active, created_at
        FROM menu_items
        WHERE deleted_at IS NULL
        ORDER BY id DESC";

$result = $conn->query($sql);

$menu = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $menu[] = [
            "id"        => (int)$row["id"],
            "name"      => $row["name"],
            "description" => $row["description"],
            "price"     => (int)$row["price"],
            "photo"     => $row["photo"],
            "is_active" => (int)$row["is_active"],
            "created_at"=> $row["created_at"]
        ];
    }
}

ResponseService::json([
    "success" => true,
    "data" => $menu
]);

$conn->close();
