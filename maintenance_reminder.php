<?php
class MaintenanceReminder {
    public static function createReminder($vehicle_id, $service_type, $due_date) {
        global $conn;
        
        $sql = "INSERT INTO maintenance_reminders 
                (vehicle_id, service_type, due_date, status) 
                VALUES (?, ?, ?, 'pending')";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iss", $vehicle_id, $service_type, $due_date);
        
        if ($stmt->execute()) {
            // Send notification to vehicle owner
            $vehicle_sql = "SELECT u.email FROM vehicles v 
                          JOIN users u ON v.user_id = u.user_id 
                          WHERE v.vehicle_id = ?";
            $stmt = $conn->prepare($vehicle_sql);
            $stmt->bind_param("i", $vehicle_id);
            $stmt->execute();
            $email = $stmt->get_result()->fetch_assoc()['email'];
            
            // Send email notification
            NotificationSystem::sendEmail(
                $email,
                "Maintenance Reminder",
                "Your vehicle is due for $service_type on $due_date"
            );
            
            return true;
        }
        return false;
    }
}