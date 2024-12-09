<?php
include 'includes/db.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'technician') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false];
    
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $appointment_id = $data['appointment_id'];
        $status = $data['status'];
        $notes = $data['notes'];
        $technician_id = $_SESSION['user_id'];
        
        $conn->begin_transaction();
        
        // Verify appointment is assigned to this technician
        $check_sql = "SELECT a.*, u.email, u.first_name, COALESCE(s.name, sp.name) as service_name 
                      FROM appointments a 
                      LEFT JOIN services s ON a.service_id = s.service_id
                      LEFT JOIN service_packages sp ON a.package_id = sp.package_id
                      JOIN users u ON a.user_id = u.user_id
                      WHERE a.appointment_id = ? AND a.technician_id = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("ii", $appointment_id, $technician_id);
        $stmt->execute();
        $appointment = $stmt->get_result()->fetch_assoc();
        
        if (!$appointment) {
            throw new Exception("Appointment not found or not assigned to you");
        }
        
        // Update appointment status
        $update_sql = "UPDATE appointments 
                      SET status = ?, 
                          notes = CONCAT(IFNULL(notes,''), '\n', ?),
                          updated_at = NOW() 
                      WHERE appointment_id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("ssi", $status, $notes, $appointment_id);
        $stmt->execute();
        
        // Create notification for customer
        $customer_message = "Your appointment for {$appointment['service_name']} has been updated to: " . ucfirst($status);
        if ($notes) {
            $customer_message .= "\nTechnician notes: " . $notes;
        }
        
        $notify_sql = "INSERT INTO notifications (user_id, type, message, appointment_id) 
                      VALUES (?, 'status_update', ?, ?)";
        $stmt = $conn->prepare($notify_sql);
        $stmt->bind_param("isi", $appointment['user_id'], $customer_message, $appointment_id);
        $stmt->execute();
        
        // Notify admin
        $admin_sql = "INSERT INTO notifications (user_id, type, message, appointment_id)
                     SELECT user_id, 'status_update', ?, ?
                     FROM users WHERE role = 'admin'";
        $admin_message = "Appointment #{$appointment_id} status updated to: " . ucfirst($status);
        $stmt = $conn->prepare($admin_sql);
        $stmt->bind_param("si", $admin_message, $appointment_id);
        $stmt->execute();
        
        $conn->commit();
        $response['success'] = true;
        $response['message'] = "Status updated successfully";
        
    } catch (Exception $e) {
        $conn->rollback();
        $response['error'] = $e->getMessage();
    }
    
    echo json_encode($response);
    exit();
}