<?php
include 'includes/db.php';
session_start();

if ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'technician') {
    exit(json_encode(['error' => 'Unauthorized']));
}

$last_check = $_SESSION['last_payment_check'] ?? date('Y-m-d H:i:s', strtotime('-1 minute'));

$sql = "SELECT COUNT(*) as count FROM payments 
        WHERE created_at > ? AND status = 'completed'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $last_check);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

$_SESSION['last_payment_check'] = date('Y-m-d H:i:s');

echo json_encode([
    'new_payments' => $result['count'] > 0
]);