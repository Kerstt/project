<?php
include 'includes/db.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user details
$user_sql = "SELECT * FROM users WHERE user_id = ?";
$stmt = $conn->prepare($user_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();

// Fetch upcoming appointments with more details
$sql = "SELECT a.*, 
        COALESCE(s.name, sp.name) as service_name,
        COALESCE(s.price, sp.price) as price,
        v.make, v.model, v.year,
        u.first_name as tech_first_name, u.last_name as tech_last_name
        FROM appointments a 
        LEFT JOIN services s ON a.service_id = s.service_id 
        LEFT JOIN service_packages sp ON a.package_id = sp.package_id
        JOIN vehicles v ON a.vehicle_id = v.vehicle_id
        LEFT JOIN users u ON a.technician_id = u.user_id
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

// Get statistics
$stats_sql = "SELECT 
    COUNT(DISTINCT v.vehicle_id) as total_vehicles,
    COUNT(DISTINCT a.appointment_id) as total_appointments,
    SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed_services
    FROM vehicles v
    LEFT JOIN appointments a ON v.user_id = a.user_id
    WHERE v.user_id = ?";
$stmt = $conn->prepare($stats_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard - AutoBots</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="index.php" class="flex items-center space-x-2">
                        <img src="https://cdn-icons-png.flaticon.com/512/1785/1785210.png" alt="AutoBots Logo" class="h-8 w-8">
                        <span class="text-xl font-bold text-gray-800">AutoBots</span>
                    </a>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="profile.php" class="flex items-center space-x-2">
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user_data['first_name'] . ' ' . $user_data['last_name']); ?>&background=3b82f6&color=ffffff" 
                             class="h-8 w-8 rounded-full">
                        <span class="text-gray-700"><?php echo htmlspecialchars($user_data['first_name']); ?></span>
                    </a>
                    <a href="logout.php" class="text-red-600 hover:text-red-700">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Welcome Banner -->
        <div class="bg-gradient-to-r from-blue-600 to-blue-800 rounded-lg shadow-lg p-6 mb-8">
            <div class="flex items-center justify-between">
                <div class="text-white">
                    <h1 class="text-2xl font-bold">Welcome back, <?php echo htmlspecialchars($user_data['first_name']); ?>!</h1>
                    <p class="mt-1 text-blue-100">Manage your vehicles and appointments here</p>
                </div>
                <a href="book_appointment.php" class="bg-white text-blue-600 px-6 py-2 rounded-lg hover:bg-blue-50 transition">
                    Book Appointment
                </a>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition">
                <div class="flex items-center">
                    <div class="p-3 bg-blue-100 rounded-full">
                        <i class="fas fa-car text-blue-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-gray-500 text-sm">Your Vehicles</p>
                        <h3 class="text-2xl font-bold"><?php echo $stats['total_vehicles']; ?></h3>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition">
                <div class="flex items-center">
                    <div class="p-3 bg-green-100 rounded-full">
                        <i class="fas fa-calendar-check text-green-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-gray-500 text-sm">Total Appointments</p>
                        <h3 class="text-2xl font-bold"><?php echo $stats['total_appointments']; ?></h3>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition">
                <div class="flex items-center">
                    <div class="p-3 bg-purple-100 rounded-full">
                        <i class="fas fa-check-circle text-purple-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-gray-500 text-sm">Completed Services</p>
                        <h3 class="text-2xl font-bold"><?php echo $stats['completed_services']; ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Upcoming Appointments -->
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-semibold mb-4">Upcoming Appointments</h2>
                    <?php if ($result_appointments->num_rows > 0): ?>
                        <div class="space-y-4">
                            <?php while($appointment = $result_appointments->fetch_assoc()): ?>
                                <div class="bg-white rounded-lg shadow-md p-4 mb-4">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <h3 class="text-lg font-semibold"><?php echo htmlspecialchars($appointment['service_name']); ?></h3>
                                            <p class="text-gray-600">
                                                <?php echo date('F j, Y g:i A', strtotime($appointment['appointment_date'])); ?>
                                            </p>
                                            <span class="inline-block mt-2 px-3 py-1 rounded-full text-sm
                                                <?php echo $appointment['status'] == 'pending' ? 'bg-yellow-100 text-yellow-800' : 
                                                    ($appointment['status'] == 'completed' ? 'bg-green-100 text-green-800' : 
                                                    'bg-blue-100 text-blue-800'); ?>">
                                                <?php echo ucfirst($appointment['status']); ?>
                                            </span>
                                        </div>
                                        
                                        <?php if ($appointment['status'] != 'completed' && $appointment['status'] != 'cancelled'): ?>
                                            <div class="space-x-2">
                                                <button onclick="openRescheduleModal(<?php echo $appointment['appointment_id']; ?>)"
                                                        class="text-blue-600 hover:text-blue-800">
                                                    <i class="fas fa-calendar-alt"></i> Reschedule
                                                </button>
                                                <button onclick="confirmCancel(<?php echo $appointment['appointment_id']; ?>)"
                                                        class="text-red-600 hover:text-red-800">
                                                    <i class="fas fa-times"></i> Cancel
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <img src="https://cdn-icons-png.flaticon.com/512/6598/6598519.png" 
                                 alt="No appointments" class="w-24 h-24 mx-auto mb-4 opacity-50">
                            <p class="text-gray-500">No upcoming appointments</p>
                            <a href="book_appointment.php" class="mt-4 inline-block text-blue-600 hover:text-blue-700">
                                Schedule your first appointment →
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Your Vehicles -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-semibold">Your Vehicles</h2>
                        <a href="manage_vehicles.php" class="text-blue-600 hover:text-blue-700">
                            <i class="fas fa-plus"></i> Add Vehicle
                        </a>
                    </div>
                    <?php if ($result_vehicles->num_rows > 0): ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <?php while($vehicle = $result_vehicles->fetch_assoc()): ?>
                                <div class="border rounded-lg p-4 hover:border-blue-500 transition">
                                    <div class="flex items-center space-x-4">
                                        <img src="https://cdn-icons-png.flaticon.com/512/3774/3774278.png" 
                                             alt="Vehicle" class="w-16 h-16">
                                        <div>
                                            <h3 class="font-semibold"><?php echo htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model']); ?></h3>
                                            <p class="text-sm text-gray-600">Year: <?php echo htmlspecialchars($vehicle['year']); ?></p>
                                            <p class="text-sm text-gray-600">Plate: <?php echo htmlspecialchars($vehicle['license_plate']); ?></p>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <img src="https://cdn-icons-png.flaticon.com/512/2554/2554969.png" 
                                 alt="No vehicles" class="w-24 h-24 mx-auto mb-4 opacity-50">
                            <p class="text-gray-500">No vehicles registered</p>
                            <a href="manage_vehicles.php" class="mt-4 inline-block text-blue-600 hover:text-blue-700">
                                Register your first vehicle →
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Quick Actions -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-semibold mb-4">Quick Actions</h2>
                    <div class="space-y-3">
                        <a href="book_appointment.php" class="flex items-center space-x-3 p-3 bg-blue-50 rounded-lg hover:bg-blue-100 transition">
                            <i class="fas fa-calendar-plus text-blue-600"></i>
                            <span>Book New Appointment</span>
                        </a>
                        <a href="manage_vehicles.php" class="flex items-center space-x-3 p-3 bg-green-50 rounded-lg hover:bg-green-100 transition">
                            <i class="fas fa-car text-green-600"></i>
                            <span>Manage Vehicles</span>
                        </a>
                        <a href="service_history.php" class="flex items-center space-x-3 p-3 bg-purple-50 rounded-lg hover:bg-purple-100 transition">
                            <i class="fas fa-history text-purple-600"></i>
                            <span>View Service History</span>
                        </a>
                    </div>
                </div>

                <!-- Latest Updates -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-semibold mb-4">Latest Updates</h2>
                    <div class="space-y-4">
                        <div class="flex items-start space-x-3">
                            <div class="p-2 bg-blue-100 rounded-lg">
                                <img src="https://cdn-icons-png.flaticon.com/512/4635/4635595.png" 
                                     alt="Service" class="w-12 h-12">
                            </div>
                            <div>
                                <h3 class="font-semibold">New Service Available</h3>
                                <p class="text-sm text-gray-600">We have added a new service to our offerings. Check it out now!</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3">
                            <div class="p-2 bg-green-100 rounded-lg">
                                <img src="https://cdn-icons-png.flaticon.com/512/3774/3774278.png" 
                                     alt="Vehicle" class="w-12 h-12">
                            </div>
                            <div>
                                <h3 class="font-semibold">Vehicle Maintenance Tips</h3>
                                <p class="text-sm text-gray-600">Read our latest blog post on how to keep your vehicle in top condition.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Reschedule Modal -->
    <div id="rescheduleModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3 text-center">
                <h3 class="text-lg leading-6 font-medium text-gray-900">Reschedule Appointment</h3>
                <form id="rescheduleForm" action="update_appointment.php" method="POST">
                    <input type="hidden" name="action" value="reschedule">
                    <input type="hidden" name="appointment_id" id="reschedule_appointment_id">
                    
                    <div class="mt-4">
                        <input type="datetime-local" 
                               name="new_date"
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                               required>
                    </div>
                    
                    <div class="mt-5 flex justify-center space-x-4">
                        <button type="button" 
                                onclick="document.getElementById('rescheduleModal').classList.add('hidden')"
                                class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600">
                            Confirm
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add JavaScript for modal handling -->
    <script>
    function openRescheduleModal(appointmentId) {
        document.getElementById('reschedule_appointment_id').value = appointmentId;
        document.getElementById('rescheduleModal').classList.remove('hidden');
        
        // Set minimum date to tomorrow
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        tomorrow.setMinutes(tomorrow.getMinutes() - tomorrow.getTimezoneOffset());
        document.querySelector('input[type="datetime-local"]').min = tomorrow.toISOString().slice(0, 16);
    }

    function closeModal(modalId) {
        document.getElementById(modalId).classList.add('hidden');
    }

    function confirmCancel(appointmentId) {
        if (confirm('Are you sure you want to cancel this appointment?')) {
            // Create and submit form
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'update_appointment.php';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'cancel';
            
            const appointmentInput = document.createElement('input');
            appointmentInput.type = 'hidden';
            appointmentInput.name = 'appointment_id';
            appointmentInput.value = appointmentId;
            
            form.appendChild(actionInput);
            form.appendChild(appointmentInput);
            document.body.appendChild(form);
            form.submit();
        }
    }

    // Add to all dashboard pages
    function checkAppointmentUpdates() {
        fetch('check_updates.php')
            .then(response => response.json())
            .then(data => {
                if (data.updates) {
                    location.reload();
                }
            });
    }

    // Check for updates every 30 seconds
    setInterval(checkAppointmentUpdates, 30000);
    </script>

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