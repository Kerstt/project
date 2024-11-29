<?php
include 'includes/db.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch upcoming appointments
$sql = "SELECT a.*, s.name as service_name, s.price 
        FROM appointments a 
        JOIN services s ON a.service_id = s.service_id 
        WHERE a.user_id = ? AND a.appointment_date >= CURDATE() 
        ORDER BY a.appointment_date ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result_appointments = $stmt->get_result();

// Fetch vehicles
$sql_vehicles = "SELECT * FROM vehicles WHERE user_id = ?";
$stmt = $conn->prepare($sql_vehicles);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result_vehicles = $stmt->get_result();

// Fetch service history
$sql_history = "SELECT a.*, s.name as service_name, s.price 
                FROM appointments a 
                JOIN services s ON a.service_id = s.service_id 
                WHERE a.user_id = ? AND a.appointment_date < CURDATE() 
                ORDER BY a.appointment_date DESC 
                LIMIT 5";
$stmt = $conn->prepare($sql_history);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result_history = $stmt->get_result();

// Count total appointments
$total_appointments = $result_appointments->num_rows;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Customer Dashboard - AutoBots</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="index.php" class="flex items-center space-x-2">
                        <i class="fas fa-car text-blue-600 text-2xl"></i>
                        <span class="text-xl font-bold">AutoBots</span>
                    </a>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-gray-600">Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></span>
                    <a href="logout.php" class="text-red-600 hover:text-red-700">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Quick Stats -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 bg-blue-100 rounded-full">
                        <i class="fas fa-calendar text-blue-600"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-gray-500 text-sm">Upcoming Appointments</h3>
                        <p class="text-2xl font-semibold"><?php echo $total_appointments; ?></p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 bg-green-100 rounded-full">
                        <i class="fas fa-car text-green-600"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-gray-500 text-sm">My Vehicles</h3>
                        <p class="text-2xl font-semibold"><?php echo $result_vehicles->num_rows; ?></p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6">
                <a href="book_appointment.php" class="flex items-center justify-center space-x-2 bg-blue-600 text-white py-3 rounded-lg hover:bg-blue-700 transition">
                    <i class="fas fa-plus"></i>
                    <span>Book New Appointment</span>
                </a>
            </div>
        </div>

        <!-- Upcoming Appointments -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-xl font-semibold mb-4">Upcoming Appointments</h2>
            <?php if ($result_appointments->num_rows > 0): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Service</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php while($appointment = $result_appointments->fetch_assoc()): ?>
                                <tr>
                                    <td class="px-6 py-4"><?php echo htmlspecialchars($appointment['service_name']); ?></td>
                                    <td class="px-6 py-4"><?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?></td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            <?php echo $appointment['status'] == 'pending' ? 'bg-yellow-100 text-yellow-800' : 
                                                    ($appointment['status'] == 'completed' ? 'bg-green-100 text-green-800' : 
                                                    'bg-gray-100 text-gray-800'); ?>">
                                            <?php echo ucfirst($appointment['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">$<?php echo number_format($appointment['price'], 2); ?></td>
                                    <td class="px-6 py-4">
                                        <a href="view_appointment.php?id=<?php echo $appointment['appointment_id']; ?>" 
                                           class="text-blue-600 hover:text-blue-900">View Details</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-gray-500">No upcoming appointments.</p>
            <?php endif; ?>
        </div>

        <!-- My Vehicles -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold">My Vehicles</h2>
                    <a href="manage_vehicles.php" class="text-blue-600 hover:text-blue-700">
                        <i class="fas fa-plus"></i> Add Vehicle
                    </a>
                </div>
                <?php if ($result_vehicles->num_rows > 0): ?>
                    <div class="space-y-4">
                        <?php while($vehicle = $result_vehicles->fetch_assoc()): ?>
                            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                                <div class="flex items-center space-x-4">
                                    <i class="fas fa-car text-gray-400 text-2xl"></i>
                                    <div>
                                        <h3 class="font-medium"><?php echo htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model']); ?></h3>
                                        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($vehicle['year']); ?> - <?php echo htmlspecialchars($vehicle['license_plate']); ?></p>
                                    </div>
                                </div>
                                <a href="vehicle_details.php?id=<?php echo $vehicle['vehicle_id']; ?>" 
                                   class="text-blue-600 hover:text-blue-700">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <p class="text-gray-500">No vehicles registered.</p>
                <?php endif; ?>
            </div>

            <!-- Service History -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold mb-4">Service History</h2>
                <?php if ($result_history->num_rows > 0): ?>
                    <div class="space-y-4">
                        <?php while($history = $result_history->fetch_assoc()): ?>
                            <div class="p-4 bg-gray-50 rounded-lg">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <h3 class="font-medium"><?php echo htmlspecialchars($history['service_name']); ?></h3>
                                        <p class="text-sm text-gray-500"><?php echo date('M d, Y', strtotime($history['appointment_date'])); ?></p>
                                    </div>
                                    <span class="text-green-600 font-medium">$<?php echo number_format($history['price'], 2); ?></span>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <p class="text-gray-500">No service history available.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-white border-t mt-8">
        <div class="max-w-7xl mx-auto px-4 py-6">
            <p class="text-center text-gray-500 text-sm">
                &copy; <?php echo date('Y'); ?> AutoBots. All rights reserved.
            </p>
        </div>
    </footer>
</body>
</html>