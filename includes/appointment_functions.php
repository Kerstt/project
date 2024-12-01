<?php
function updateAppointmentStatus($conn, $appointment_id, $new_status, $notes = '') {
    $valid_transitions = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['in-progress', 'cancelled'],
        'in-progress' => ['completed', 'cancelled'],
        'completed' => [],
        'cancelled' => []
    ];
    
    // Get current status
    $stmt = $conn->prepare("SELECT status FROM appointments WHERE appointment_id = ?");
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $current_status = $stmt->get_result()->fetch_assoc()['status'];
    
    // Validate transition
    if (!in_array($new_status, $valid_transitions[$current_status])) {
        throw new Exception("Invalid status transition from $current_status to $new_status");
    }
    
    $conn->begin_transaction();
    try {
        // Update appointment status
        $stmt = $conn->prepare("UPDATE appointments SET status = ?, updated_at = NOW() WHERE appointment_id = ?");
        $stmt->bind_param("si", $new_status, $appointment_id);
        $stmt->execute();
        
        // Log status change
        $stmt = $conn->prepare("INSERT INTO appointment_status_history (appointment_id, status, notes) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $appointment_id, $new_status, $notes);
        $stmt->execute();
        
        // Handle status-specific actions
        if ($new_status == 'completed') {
            // Create payment record
            $stmt = $conn->prepare("
                INSERT INTO payments (appointment_id, amount, status, payment_date)
                SELECT a.appointment_id, s.price, 'pending', NOW()
                FROM appointments a
                JOIN services s ON a.service_id = s.service_id
                WHERE a.appointment_id = ?
            ");
            $stmt->bind_param("i", $appointment_id);
            $stmt->execute();
        }
        
        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}