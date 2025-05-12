<?php
header('Content-Type: application/json');
require_once '../../functions/db.php';

$driverId = isset($_GET['driver_id']) ? intval($_GET['driver_id']) : 0;
$status = null;

if ($driverId > 0) {
    $conn = connectToCore1DB();
    $result = $conn->query("SELECT status FROM drivers WHERE driver_id = $driverId LIMIT 1");
    if ($row = $result->fetch_assoc()) {
        $status = $row['status'];
    }
    $conn->close();
}

echo json_encode(['success' => true, 'status' => $status]); 