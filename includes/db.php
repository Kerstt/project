<?php
$host = 'localhost';
$username = 'root';
$password = ''; // Your MySQL password
$database = 'car_service_management';

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>