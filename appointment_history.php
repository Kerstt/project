<?php
include 'includes/db.php';
session_start();

// Get user's appointments
$user_id = $_SESSION['user_id'];
$sql = "SELECT a.*, u.first_name, u.last_name 
        FROM appointments a 
        LEFT JOIN users u ON a.technician_id = u.user_id 
        WHERE a.customer_id = $user_id 
        ORDER BY a.appointment_date DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Appointment History</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto p-4">
        <h1 class="text-2xl font-bold mb-4">Appointment History</h1>
        <div class="bg-white rounded-lg shadow p-4">
            <table class="min-w-full">
                <thead>
                    <tr>
                        <th class="px-4 py-2">Date</th>
                        <th class="px-4 py-2">Service</th>
                        <th class="px-4 py-2">Technician</th>
                        <th class="px-4 py-2">Status</th>
                        <th class="px-4 py-2">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td class="px-4 py-2"><?php echo $row['appointment_date']; ?></td>
                            <td class="px-4 py-2"><?php echo $row['service_type']; ?></td>
                            <td class="px-4 py-2"><?php echo $row['first_name'] . ' ' . $row['last_name']; ?></td>
                            <td class="px-4 py-2"><?php echo $row['status']; ?></td>
                            <td class="px-4 py-2">
                                <?php if($row['status'] == 'completed'): ?>
                                    <button class="bg-blue-500 text-white px-4 py-2 rounded">Leave Feedback</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>