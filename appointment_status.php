<?php
include 'includes/db.php';
include 'notifications_manager.php';
include 'loyalty_program.php';
session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'technician'])) {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $appointment_id = $_POST['appointment_id'];
    $appointment_status = $_POST['status'];
    $user_id = $_POST['user_id'];
    $appointment_amount = $_POST['amount'];

    // Update appointment status
    $sql = "UPDATE appointments SET status = ? WHERE appointment_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $appointment_status, $appointment_id);

    if ($stmt->execute()) {
        if ($appointment_status === 'completed') {
            $notifier = new NotificationsManager($conn);
            $loyalty = new LoyaltyProgram($conn);

            // Award loyalty points
            $points_earned = $loyalty->awardPoints($user_id, $appointment_amount, 'service_completion');
            
            // Send completion notification
            $notifier->sendAppointmentCompletion($appointment_id, $points_earned);
        }
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit();
    }
}
?>