<?php
include 'includes/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    exit(json_encode(['error' => 'Unauthorized']));
}

header('Content-Type: application/json');

$last_check = $_SESSION['last_check'] ?? date('Y-m-d H:i:s', strtotime('-1 minute'));
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Query based on role
switch ($role) {
    case 'admin':
        $sql = "SELECT COUNT(*) as count FROM appointments WHERE updated_at > ?";
        $params = ["s", $last_check];
        break;
    case 'technician':
        $sql = "SELECT COUNT(*) as count FROM appointments WHERE technician_id = ? AND updated_at > ?";
        $params = ["is", $user_id, $last_check];
        break;
    case 'customer':
        $sql = "SELECT COUNT(*) as count FROM appointments WHERE user_id = ? AND updated_at > ?";
        $params = ["is", $user_id, $last_check];
        break;
}

$stmt = $conn->prepare($sql);
$stmt->bind_param(...$params);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

$_SESSION['last_check'] = date('Y-m-d H:i:s');

echo json_encode([
    'updates' => $result['count'] > 0
]);
