<?php
include 'includes/db.php';
session_start();

class LoyaltyProgram {
    private $conn;
    const POINTS_PER_DOLLAR = 10;
    const POINTS_FOR_SIGNUP = 100;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    public function awardPoints($user_id, $amount, $source) {
        $points = floor($amount * self::POINTS_PER_DOLLAR);
        
        $sql = "INSERT INTO loyalty_points (user_id, points, source, description) 
                VALUES (?, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        $description = "Points earned from $source";
        $stmt->bind_param("iiss", $user_id, $points, $source, $description);
        $stmt->execute();
        
        // Update total points
        $this->updateTotalPoints($user_id);
        
        return $points;
    }
    
    public function redeemPoints($user_id, $points, $reward) {
        $total_points = $this->getTotalPoints($user_id);
        
        if ($total_points >= $points) {
            $sql = "INSERT INTO loyalty_points (user_id, points, source, description) 
                    VALUES (?, ?, 'redemption', ?)";
            $stmt = $this->conn->prepare($sql);
            $negative_points = -$points;
            $description = "Points redeemed for $reward";
            $stmt->bind_param("iis", $user_id, $negative_points, $description);
            
            if ($stmt->execute()) {
                $this->updateTotalPoints($user_id);
                return true;
            }
        }
        return false;
    }
    
    private function updateTotalPoints($user_id) {
        $sql = "SELECT SUM(points) as total FROM loyalty_points WHERE user_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $total = $stmt->get_result()->fetch_assoc()['total'];
        
        $sql = "UPDATE users SET loyalty_points = ? WHERE user_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ii", $total, $user_id);
        $stmt->execute();
    }
    
    public function getTotalPoints($user_id) {
        $sql = "SELECT loyalty_points FROM users WHERE user_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc()['loyalty_points'];
    }
    
    public function getPointsHistory($user_id) {
        $sql = "SELECT * FROM loyalty_points WHERE user_id = ? ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        return $stmt->get_result();
    }
}


