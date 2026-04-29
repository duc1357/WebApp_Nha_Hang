<?php
require_once __DIR__ . '/auth_check_api.php';
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/db.php';

$conn = getDbConnection();
$response = [];

// Create reviews table
$sql = "CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    user_id INT NOT NULL,
    rating TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_order_review (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if ($conn->query($sql)) {
    $response['success'] = true;
    $response['message'] = "Table 'reviews' created or already exists.";
} else {
    $response['success'] = false;
    $response['message'] = "Error creating table: " . $conn->error;
}

$conn->close();
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
