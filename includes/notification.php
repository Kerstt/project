<?php
class NotificationSystem {
    public static function sendEmail($to, $subject, $message) {
        $headers = 'From: noreply@autobots.com' . "\r\n";
        mail($to, $subject, $message, $headers);
    }

    public static function createNotification($user_id, $message, $type = 'email') {
        global $conn;
        $sql = "INSERT INTO notifications (user_id, message, type) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iss", $user_id, $message, $type);
        return $stmt->execute();
    }
}
?>