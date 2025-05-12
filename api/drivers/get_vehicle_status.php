<?php
header('Content-Type: application/json');
require_once '../../functions/db.php';

$vehicleId = isset($_GET['vehicle_id']) ? intval($_GET['vehicle_id']) : 0;
$status = null;

if ($vehicleId > 0) {
    $conn = connectToCore1DB();
    $result = $conn->query("SELECT status FROM vehicles WHERE vehicle_id = $vehicleId LIMIT 1");
    if ($row = $result->fetch_assoc()) {
        $status = $row['status'];
    }
    $conn->close();
}

echo json_encode(['success' => true, 'status' => $status]); 