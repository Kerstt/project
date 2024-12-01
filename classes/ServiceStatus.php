<?php
class ServiceStatus {
    const PENDING = 'pending';
    const CONFIRMED = 'confirmed';
    const IN_PROGRESS = 'in-progress';
    const COMPLETED = 'completed';
    const CANCELLED = 'cancelled';
    
    public static function updateStatus($appointment_id, $status) {
        global $conn;
        $sql = "UPDATE appointments SET status = ?, updated_at = NOW() WHERE appointment_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $status, $appointment_id);
        return $stmt->execute();
    }
}
?>