<?php
session_start();
include 'includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $appointment_id = $_POST['appointment_id'];
    $action = $_POST['action'];
    
    // Verify appointment belongs to user
    $check_sql = "SELECT * FROM appointments WHERE appointment_id = ? AND user_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $appointment_id, $_SESSION['user_id']);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows === 0) {
        $_SESSION['error'] = "Invalid appointment";
        header('Location: customer_manage_appointments.php');
        exit();
    }
    
    if ($action === 'reschedule') {
        $new_date = $_POST['new_date'];
        
        $sql = "UPDATE appointments SET appointment_date = ? WHERE appointment_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $new_date, $appointment_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Appointment rescheduled successfully";
        } else {
            $_SESSION['error'] = "Error rescheduling appointment";
        }
    } 
    elseif ($action === 'cancel') {
        $sql = "UPDATE appointments SET status = 'cancelled' WHERE appointment_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $appointment_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Appointment cancelled successfully";
        } else {
            $_SESSION['error'] = "Error cancelling appointment";
        }
    }
}

header('Location: customer_manage_appointments.php');
exit();
?>