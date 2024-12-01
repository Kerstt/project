<?php
class ServiceRating {
    public static function addRating($appointment_id, $rating, $review = '') {
        global $conn;
        
        $sql = "INSERT INTO service_ratings 
                (appointment_id, rating, review, created_at) 
                VALUES (?, ?, ?, CURRENT_TIMESTAMP)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iis", $appointment_id, $rating, $review);
        
        return $stmt->execute();
    }

    public static function getAverageRating($service_id) {
        global $conn;
        
        $sql = "SELECT AVG(r.rating) as avg_rating 
                FROM service_ratings r 
                JOIN appointments a ON r.appointment_id = a.appointment_id 
                WHERE a.service_id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $service_id);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_assoc()['avg_rating'];
    }
}