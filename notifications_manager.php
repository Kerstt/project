<?php
include 'includes/db.php';
session_start();

class NotificationsManager {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    public function sendAppointmentReminder($appointment_id) {
        $sql = "SELECT a.*, u.email, u.phone_number, u.first_name, s.name as service_name 
                FROM appointments a
                JOIN users u ON a.user_id = u.user_id
                JOIN services s ON a.service_id = s.service_id
                WHERE a.appointment_id = ?";
                
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $appointment_id);
        $stmt->execute();
        $appointment = $stmt->get_result()->fetch_assoc();
        
        // Email notification
        $to = $appointment['email'];
        $subject = "Appointment Reminder - AutoBots";
        $message = "Hi {$appointment['first_name']},\n\n";
        $message .= "This is a reminder for your upcoming service appointment:\n";
        $message .= "Service: {$appointment['service_name']}\n";
        $message .= "Date: " . date('M d, Y h:i A', strtotime($appointment['appointment_date'])) . "\n\n";
        $message .= "Thank you for choosing AutoBots!";
        
        mail($to, $subject, $message);
        
        // Log notification
        $sql = "INSERT INTO notifications (user_id, appointment_id, message, type) 
                VALUES (?, ?, ?, 'email')";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("iis", $appointment['user_id'], $appointment_id, $message);
        $stmt->execute();
    }
    
    public function sendAppointmentCompletion($appointment_id, $points_earned) {
        try {
            // Get appointment and user details
            $sql = "SELECT a.*, u.email, u.first_name, s.name as service_name 
                    FROM appointments a
                    JOIN users u ON a.user_id = u.user_id
                    JOIN services s ON a.service_id = s.service_id
                    WHERE a.appointment_id = ?";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("i", $appointment_id);
            $stmt->execute();
            $data = $stmt->get_result()->fetch_assoc();
            
            if (!$data) {
                throw new Exception("Appointment not found");
            }
            
            // Prepare email content
            $to = $data['email'];
            $subject = "Service Completed - AutoBots";
            $message = "Dear {$data['first_name']},\n\n";
            $message .= "Your service appointment has been completed!\n\n";
            $message .= "Service: {$data['service_name']}\n";
            $message .= "Date: " . date('M d, Y', strtotime($data['appointment_date'])) . "\n";
            $message .= "Points Earned: {$points_earned}\n\n";
            $message .= "Thank you for choosing AutoBots!";
            
            // Send email
            mail($to, $subject, $message);
            
            // Log notification
            $sql = "INSERT INTO notifications (user_id, appointment_id, message, type) 
                    VALUES (?, ?, ?, 'email')";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("iis", $data['user_id'], $appointment_id, $message);
            $stmt->execute();
            
            return true;
        } catch (Exception $e) {
            error_log("Notification error: " . $e->getMessage());
            return false;
        }
    }
}
