<?php
header('Content-Type: application/json');
require_once '../../functions/db.php';

$userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$status = null;

if ($userId > 0) {
    $conn = connectToCore2DB(); // Or connectToCore1Movers2DB() if that's your function
    $result = $conn->query("SELECT status FROM users WHERE user_id = $userId LIMIT 1");
    if ($row = $result->fetch_assoc()) {
        $status = $row['status'];
    }
    $conn->close();
}

echo json_encode(['success' => true, 'status' => $status]); 