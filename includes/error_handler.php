<?php
class ErrorHandler {
    public static function logError($message, $type = 'ERROR') {
        $logFile = 'logs/error_log.txt';
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $type: $message\n";
        error_log($logMessage, 3, $logFile);
    }
}
?>