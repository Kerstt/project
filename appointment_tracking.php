<?php
class AppointmentTracking {
    public static function updateStatus($appointment_id, $status, $notes = '') {
        global $conn;
        
        $sql = "UPDATE appointments SET 
                status = ?, 
                notes = CONCAT(notes, '\n', ?),
                updated_at = CURRENT_TIMESTAMP 
                WHERE appointment_id = ?";
        
        $stmt = $conn->prepare($sql);
        $status_note = date('Y-m-d H:i:s') . " - Status updated to: " . $status . "\n" . $notes;
        $stmt->bind_param("ssi", $status, $status_note, $appointment_id);
        
        return $stmt->execute();
    }

    public static function getTimeline($appointment_id) {
        global $conn;
        
        $sql = "SELECT * FROM appointment_status_history 
                WHERE appointment_id = ? 
                ORDER BY created_at DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $appointment_id);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}