<?php
header('Content-Type: application/json');
include '../includes/db.php';

switch($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        $sql = "SELECT * FROM appointments WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $_GET['user_id']);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        echo json_encode($result);
        break;
        
    case 'POST':
        // Handle appointment creation
        break;
}
?>