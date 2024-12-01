<?php
class ServiceMetrics {
    public static function getServiceStats() {
        global $conn;
        return $conn->query("
            SELECT 
                s.name,
                COUNT(a.appointment_id) as total_appointments,
                AVG(p.amount) as average_revenue
            FROM services s
            LEFT JOIN appointments a ON s.service_id = a.service_id
            LEFT JOIN payments p ON a.appointment_id = p.appointment_id
            GROUP BY s.service_id
        ")->fetch_all(MYSQLI_ASSOC);
    }
}
?>