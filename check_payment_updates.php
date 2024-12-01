<?php
include 'includes/db.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    exit(json_encode(['error' => 'Unauthorized']));
}

$input = json_decode(file_get_contents('php://input'), true);
$appointment_ids = $input['appointment_ids'] ?? [];

if (empty($appointment_ids)) {
    exit(json_encode(['updates' => []]));
}

$ids = implode(',', array_map('intval', $appointment_ids));
$sql = "
    SELECT 
        a.appointment_id,
        a.status as appointment_status,
        a.payment_status,
        p.payment_method,
        p.payment_date
    FROM appointments a
    LEFT JOIN payments p ON a.appointment_id = p.appointment_id
    WHERE a.appointment_id IN ($ids)
";

$result = $conn->query($sql);
$updates = [];

while ($row = $result->fetch_assoc()) {
    $updates[] = [
        'appointment_id' => $row['appointment_id'],
        'appointment_status' => $row['appointment_status'],
        'payment_status' => $row['payment_status'] ?? 'pending',
        'payment_method' => $row['payment_method'],
        'payment_date' => $row['payment_date']
    ];
}

echo json_encode(['updates' => $updates]);