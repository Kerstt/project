<?php
class PaymentProcessor {
    public static function processPayment($appointment_id, $amount, $payment_method, $user_id) {
        global $conn;
        
        try {
            $conn->begin_transaction();

            // Create payment record
            $stmt = $conn->prepare("INSERT INTO payments (appointment_id, amount, payment_method, status, payment_date) VALUES (?, ?, ?, 'paid', NOW())");
            $stmt->bind_param("ids", $appointment_id, $amount, $payment_method);
            $stmt->execute();

            // Update appointment status
            $stmt = $conn->prepare("UPDATE appointments SET payment_status = 'paid', status = 'confirmed', updated_at = NOW() WHERE appointment_id = ?");
            $stmt->bind_param("i", $appointment_id);
            $stmt->execute();

            // Create notifications for admin and assigned technician
            $stmt = $conn->prepare("
                INSERT INTO notifications (user_id, type, message, appointment_id) 
                SELECT user_id, 'payment_received', 
                CONCAT('New payment received for appointment #', ?), ?
                FROM users 
                WHERE role IN ('admin', 'technician') 
                AND (user_id IN (SELECT technician_id FROM appointments WHERE appointment_id = ?) 
                     OR role = 'admin')
            ");
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