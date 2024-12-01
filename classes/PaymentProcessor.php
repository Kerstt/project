<?php
class PaymentProcessor {
    public static function processPayment($appointment_id, $amount, $payment_method, $user_id) {
        global $conn;
        
        $conn->begin_transaction();
        try {
            // Create payment record
            $sql = "INSERT INTO payments (appointment_id, amount, payment_method, status, user_id) 
                    VALUES (?, ?, ?, 'completed', ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("idsi", $appointment_id, $amount, $payment_method, $user_id);
            $stmt->execute();
            
            // Update appointment status
            $sql = "UPDATE appointments 
                   SET payment_status = 'paid', status = 'confirmed', updated_at = NOW() 
                   WHERE appointment_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $appointment_id);
            $stmt->execute();
            
            // Create notification for admin and technician
            $notification_sql = "INSERT INTO notifications (user_id, type, message, appointment_id) 
                               SELECT user_id, 'payment_received', 
                               CONCAT('Payment received for appointment #', ?), ?
                               FROM users 
                               WHERE role IN ('admin', 'technician') 
                               AND user_id IN (
                                   SELECT technician_id FROM appointments WHERE appointment_id = ?
                               )";
            $stmt = $conn->prepare($notification_sql);
            $stmt->bind_param("iii", $appointment_id, $appointment_id, $appointment_id);
            $stmt->execute();
            
            $conn->commit();
            return true;
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
    }
}
?>