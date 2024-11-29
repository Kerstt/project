<?php
include 'includes/db.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: login.php');
    exit();
}

// Updated query with proper joins and column names
$sql = "SELECT a.*, 
        u.first_name, u.last_name,
        v.make, v.model,
        s.name as service_name,
        t.first_name as tech_first_name, t.last_name as tech_last_name
        FROM appointments a
        JOIN users u ON a.user_id = u.user_id
        JOIN vehicles v ON a.vehicle_id = v.vehicle_id
        JOIN services s ON a.service_id = s.service_id
        LEFT JOIN users t ON a.technician_id = t.user_id
        ORDER BY a.appointment_date DESC";
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
        <div class="bg-white rounded-lg shadow p-4">
            <table class="min-w-full">
                <thead>
                    <tr>
                        <th class="px-4 py-2">Customer</th>
                        <th class="px-4 py-2">Vehicle</th>
                        <th class="px-4 py-2">Service</th>
                        <th class="px-4 py-2">Date</th>
                        <th class="px-4 py-2">Technician</th>
                        <th class="px-4 py-2">Status</th>
                        <th class="px-4 py-2">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td class="px-4 py-2">
                                <?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>
                            </td>
                            <td class="px-4 py-2">
                                <?php echo htmlspecialchars($row['make'] . ' ' . $row['model']); ?>
                            </td>
                            <td class="px-4 py-2"><?php echo htmlspecialchars($row['service_name']); ?></td>
                            <td class="px-4 py-2"><?php echo date('M d, Y', strtotime($row['appointment_date'])); ?></td>
                            <td class="px-4 py-2">
                                <?php echo $row['tech_first_name'] ? htmlspecialchars($row['tech_first_name'] . ' ' . $row['tech_last_name']) : 'Not assigned'; ?>
                            </td>
                            <td class="px-4 py-2"><?php echo htmlspecialchars($row['status']); ?></td>
                            <td class="px-4 py-2">
                                <a href="edit_appointment.php?id=<?php echo $row['appointment_id']; ?>" 
                                   class="text-blue-600 hover:text-blue-800">Edit</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>