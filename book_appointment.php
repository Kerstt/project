<?php
include 'includes/db.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch vehicles using prepared statement
$sql_vehicles = "SELECT * FROM vehicles WHERE user_id = ?";
$stmt = $conn->prepare($sql_vehicles);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result_vehicles = $stmt->get_result();

// Fetch services
$sql_services = "SELECT * FROM services";
$result_services = $conn->query($sql_services);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $vehicle_id = $_POST['vehicle_id'];
    $service_id = $_POST['service_id'];
    $appointment_date = $_POST['appointment_date'];
    $notes = $_POST['notes'];

    // Fixed query using user_id instead of customer_id and prepared statement
    $sql = "INSERT INTO appointments (user_id, vehicle_id, service_id, appointment_date, notes, status) 
            VALUES (?, ?, ?, ?, ?, 'pending')";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiiss", $user_id, $vehicle_id, $service_id, $appointment_date, $notes);
    
    if ($stmt->execute()) {
        header('Location: customer_dashboard.php?success=1');
        exit();
    } else {
        $error = "Error booking appointment: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Book Appointment</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <nav class="bg-white shadow">
        <div class="container mx-auto px-4">
            <div class="flex justify-between">
                <div class="flex space-x-4">
                    <a href="index.php" class="py-5 px-3 text-gray-700">Home</a>
                    <a href="customer_dashboard.php" class="py-5 px-3 text-gray-700">Dashboard</a>
                </div>
                <div class="flex space-x-4">
                    <a href="profile.php" class="py-5 px-3 text-gray-700">Profile</a>
                    <a href="logout.php" class="py-5 px-3 text-gray-700">Logout</a>
                </div>
            </div>
        </div>
    </nav>
    <div class="container mx-auto p-4">
        <h1 class="text-2xl font-bold mb-4">Book Appointment</h1>
        <form method="post" action="">
            <div class="mb-4">
                <label for="vehicle_id" class="block text-gray-700">Vehicle:</label>
                <select id="vehicle_id" name="vehicle_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
                    <?php
                    if ($result_vehicles->num_rows > 0) {
                        while($row = $result_vehicles->fetch_assoc()) {
                            echo "<option value='{$row['vehicle_id']}'>{$row['make']} {$row['model']} ({$row['year']})</option>";
                        }
                    } else {
                        echo "<option value=''>No vehicles found</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="mb-4">
                <label for="service_id" class="block text-gray-700">Service:</label>
                <select id="service_id" name="service_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
                    <?php
                    if ($result_services->num_rows > 0) {
                        while($row = $result_services->fetch_assoc()) {
                            echo "<option value='{$row['service_id']}'>{$row['name']} ({$row['price']})</option>";
                        }
                    } else {
                        echo "<option value=''>No services found</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="mb-4">
                <label for="appointment_date" class="block text-gray-700">Date and Time:</label>
                <input type="datetime-local" id="appointment_date" name="appointment_date" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
            </div>
            <div class="mb-4">
                <label for="notes" class="block text-gray-700">Notes:</label>
                <textarea id="notes" name="notes" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"></textarea>
            </div>
            <div>
                <input type="submit" value="Book Appointment" class="bg-blue-500 text-white py-2 px-4 rounded">
            </div>
        </form>
    </div>
</body>
</html>