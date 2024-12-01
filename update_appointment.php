<?php
include 'includes/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $appointment_id = $_POST['appointment_id'];
    
    // Verify appointment belongs to user
    $stmt = $conn->prepare("SELECT a.*, s.name as service_name 
                           FROM appointments a 
                           JOIN services s ON a.service_id = s.service_id 
                           WHERE a.appointment_id = ? AND a.user_id = ?");
    $stmt->bind_param("ii", $appointment_id, $_SESSION['user_id']);
    $stmt->execute();
    $appointment = $stmt->get_result()->fetch_assoc();
    
    if (!$appointment) {
        $_SESSION['error_message'] = "Invalid appointment";
        header('Location: customer_dashboard.php');
        exit();
    }

    // Check if appointment is not completed
    if ($appointment['status'] === 'completed') {
        $_SESSION['error_message'] = "Cannot modify completed appointments";
        header('Location: customer_dashboard.php');
        exit();
    }
    
    $conn->begin_transaction();
    try {
        switch ($_POST['action']) {
            case 'reschedule':
                $new_date = $_POST['new_date'];
                
                // Validate new date
                $new_date_obj = new DateTime($new_date);
                $now = new DateTime();
                
                if ($new_date_obj <= $now) {
                    throw new Exception("Please select a future date");
                }
                
                // Update appointment
                $stmt = $conn->prepare("UPDATE appointments 
                                      SET appointment_date = ?, 
                                          status = 'pending',
                                          updated_at = NOW() 
                                      WHERE appointment_id = ?");
                $stmt->bind_param("si", $new_date, $appointment_id);
                $stmt->execute();
                
                // Notify admin
                $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, message, appointment_id) 
                                      SELECT user_id, 'appointment_update', 
                                      CONCAT('Appointment #', ?, ' has been rescheduled'), ?
                                      FROM users WHERE role = 'admin'");
                $stmt->bind_param("ii", $appointment_id, $appointment_id);
                $stmt->execute();
                
                // Notify technician if assigned
                if ($appointment['technician_id']) {
                    $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, message, appointment_id)
                                          VALUES (?, 'appointment_update', ?, ?)");
                    $message = "Appointment #" . $appointment_id . " has been rescheduled";
                    $stmt->bind_param("isi", $appointment['technician_id'], $message, $appointment_id);
                    $stmt->execute();
                }
                
                $_SESSION['success_message'] = "Appointment rescheduled successfully";
                break;
                
            case 'cancel':
                // Update appointment status
                $stmt = $conn->prepare("UPDATE appointments 
                                      SET status = 'cancelled', 
                                          updated_at = NOW() 
                                      WHERE appointment_id = ?");
                $stmt->bind_param("i", $appointment_id);
                $stmt->execute();
                
                // Notify admin and technician
                $message = "Appointment #" . $appointment_id . " (" . $appointment['service_name'] . ") has been cancelled";
                
                // Notify admin
                $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, message, appointment_id) 
                                      SELECT user_id, 'appointment_update', ?, ?
                                      FROM users WHERE role = 'admin'");
                $stmt->bind_param("si", $message, $appointment_id);
                $stmt->execute();
                
                // Notify technician if assigned
                if ($appointment['technician_id']) {
                    $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, message, appointment_id)
                                          VALUES (?, 'appointment_update', ?, ?)");
                    $stmt->bind_param("isi", $appointment['technician_id'], $message, $appointment_id);
                    $stmt->execute();
                }
                
                $_SESSION['success_message'] = "Appointment cancelled successfully";
                break;
        }
        
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = "Error updating appointment: " . $e->getMessage();
    }
    
    header('Location: customer_dashboard.php');
    exit();
}