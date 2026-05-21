<?php
require_once dirname(__DIR__, 2) . '/api/services/response_service.php';
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

ResponseService::json($tables);
$conn->close();
