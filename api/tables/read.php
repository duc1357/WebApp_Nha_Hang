<?php
header("Content-Type: application/json; charset=utf-8");
require_once __DIR__ . '/../../config/constants.php';
require_once ROOT_PATH . '/config/db.php';

$conn = getDbConnection();

$sql = "SELECT id, name, floor, capacity, status FROM tables ORDER BY floor ASC, id ASC";
$result = $conn->query($sql);

$tables = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $tables[] = $row;
    }
}

echo json_encode($tables, JSON_UNESCAPED_UNICODE);
$conn->close();
