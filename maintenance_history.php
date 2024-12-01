<?php
include 'includes/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$vehicle_id = $_GET['id'];

// Fetch vehicle details
$sql_vehicle = "SELECT v.*, u.first_name, u.last_name 
                FROM vehicles v 
                JOIN users u ON v.user_id = u.user_id 
                WHERE v.vehicle_id = ?";
$stmt = $conn->prepare($sql_vehicle);
$stmt->bind_param("i", $vehicle_id);
$stmt->execute();
$vehicle = $stmt->get_result()->fetch_assoc();

// Fetch maintenance history
$sql_history = "SELECT m.*, s.name as service_name, 
                t.first_name as tech_first_name, t.last_name as tech_last_name
                FROM appointments m
                JOIN services s ON m.service_id = s.service_id
                LEFT JOIN users t ON m.technician_id = t.user_id
                WHERE m.vehicle_id = ?
                ORDER BY m.appointment_date DESC";
$stmt = $conn->prepare($sql_history);
$stmt->bind_param("i", $vehicle_id);
$stmt->execute();
$maintenance_history = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Maintenance History - <?php echo htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Vehicle Details -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h1 class="text-2xl font-bold mb-4">
                <?php echo htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model'] . ' (' . $vehicle['year'] . ')'); ?>
            </h1>
            <p class="text-gray-600">License Plate: <?php echo htmlspecialchars($vehicle['license_plate']); ?></p>
        </div>

        <!-- Maintenance Timeline -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold mb-6">Maintenance History</h2>
            <div class="space-y-6">
                <?php while($record = $maintenance_history->fetch_assoc()): ?>
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <div class="h-12 w-12 rounded-full bg-blue-100 flex items-center justify-center">
                                <i class="fas fa-wrench text-blue-600"></i>
                            </div>
                        </div>
                        <div class="ml-4 flex-grow">
                            <div class="bg-gray-50 rounded-lg p-4">
                                <h3 class="font-semibold"><?php echo htmlspecialchars($record['service_name']); ?></h3>
                                <p class="text-sm text-gray-600">
                                    Date: <?php echo date('M d, Y', strtotime($record['appointment_date'])); ?>
                                </p>
                                <?php if ($record['tech_first_name']): ?>
                                    <p class="text-sm text-gray-600">
                                        Technician: <?php echo htmlspecialchars($record['tech_first_name'] . ' ' . $record['tech_last_name']); ?>
                                    </p>
                                <?php endif; ?>
                                <?php if ($record['notes']): ?>
                                    <p class="text-sm text-gray-600 mt-2">
                                        Notes: <?php echo htmlspecialchars($record['notes']); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
</body>
</html>