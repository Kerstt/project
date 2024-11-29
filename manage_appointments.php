<?php
include 'includes/db.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: login.php');
    exit();
}

// Fetch appointments
$sql = "SELECT * FROM appointments";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Appointments</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <nav class="bg-white shadow">
        <div class="container mx-auto px-4">
            <div class="flex justify-between">
                <div class="flex space-x-4">
                    <a href="index.php" class="py-5 px-3 text-gray-700">Home</a>
                    <a href="admin_dashboard.php" class="py-5 px-3 text-gray-700">Dashboard</a>
                </div>
                <div class="flex space-x-4">
                    <a href="profile.php" class="py-5 px-3 text-gray-700">Profile</a>
                    <a href="logout.php" class="py-5 px-3 text-gray-700">Logout</a>
                </div>
            </div>
        </div>
    </nav>
    <div class="container mx-auto p-4">
        <h1 class="text-2xl font-bold mb-4">Manage Appointments</h1>
        <table class="min-w-full bg-white">
            <thead>
                <tr>
                    <th class="py-2 px-4 border-b">ID</th>
                    <th class="py-2 px-4 border-b">Customer</th>
                    <th class="py-2 px-4 border-b">Vehicle</th>
                    <th class="py-2 px-4 border-b">Service</th>
                    <th class="py-2 px-4 border-b">Date</th>
                    <th class="py-2 px-4 border-b">Status</th>
                    <th class="py-2 px-4 border-b">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($result->num_rows > 0) {
                    while($row = $result->fetch_assoc()) {
                        echo "<tr>
                                <td class='py-2 px-4 border-b'>{$row['appointment_id']}</td>
                                <td class='py-2 px-4 border-b'>{$row['customer_id']}</td>
                                <td class='py-2 px-4 border-b'>{$row['vehicle_id']}</td>
                                <td class='py-2 px-4 border-b'>{$row['service_id']}</td>
                                <td class='py-2 px-4 border-b'>{$row['appointment_date']}</td>
                                <td class='py-2 px-4 border-b'>{$row['status']}</td>
                                <td class='py-2 px-4 border-b'>
                                    <a href='update_appointment_status.php?id={$row['appointment_id']}' class='bg-blue-500 text-white py-1 px-2 rounded'>Update Status</a>
                                    <a href='assign_technician.php?id={$row['appointment_id']}' class='bg-green-500 text-white py-1 px-2 rounded'>Assign Technician</a>
                                </td>
                              </tr>";
                    }
                } else {
                    echo "<tr><td colspan='7' class='py-2 px-4 border-b text-center'>No appointments found</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</body>
</html>