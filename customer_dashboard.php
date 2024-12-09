<?php
// Session configuration - add at very top of file
ini_set('session.gc_maxlifetime', 86400); // 24 hours
ini_set('session.cookie_lifetime', 86400);
ini_set('session.gc_probability', 1);
ini_set('session.gc_divisor', 100);

// Set secure session params
$lifetime = 24 * 60 * 60; // 24 hours
$secure = isset($_SERVER['HTTPS']); // Set true if HTTPS
$httponly = true;
$samesite = 'Lax';
$path = '/';

session_set_cookie_params([
    'lifetime' => $lifetime,
    'path' => $path,
    'secure' => $secure,
    'httponly' => $httponly, 
    'samesite' => $samesite
]);

// Start session
session_start();
include 'includes/db.php';

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header('Location: login.php');
    exit();
}

// Session timeout check
$inactive = 86400; // 24 hours
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $inactive)) {
    // Last request was more than 24 hours ago
    session_unset();     // unset $_SESSION variable for the run-time
    session_destroy();   // destroy session data in storage
    header("Location: login.php"); 
    exit();
}

// Update last activity timestamp
$_SESSION['last_activity'] = time();

$user_id = $_SESSION['user_id'];

// Fetch user data
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
    SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed_services,
    SUM(CASE WHEN a.status = 'pending' THEN 1 ELSE 0 END) as pending_appointments
    FROM vehicles v
    LEFT JOIN appointments a ON v.user_id = a.user_id AND v.user_id = ?
    WHERE v.user_id = ?";

$stmt = $conn->prepare($stats_sql);
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Add null checks for stats display
$stats['total_vehicles'] = $stats['total_vehicles'] ?? 0;
$stats['completed_services'] = $stats['completed_services'] ?? 0;
$stats['pending_appointments'] = $stats['pending_appointments'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard - AutoBots</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .stat-card:hover { transform: translateY(-5px); }
        .card-zoom:hover { transform: scale(1.02); }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fadeIn { animation: fadeIn 0.5s ease-out; }
        [x-cloak] { 
            display: none !important; 
        }
    </style>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-900 text-gray-100" x-data="{ showLogoutModal: false, isOpen: false }">
    <!-- Add this right after opening body tag -->

    <!-- Add this navigation section after the opening body tag -->
    <nav class="bg-gray-800 border-b border-gray-700">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <!-- Logo and Brand -->
                <div class="flex items-center">
                    <a href="index.php" class="flex items-center">
                        <i class="fas fa-car text-orange-500 text-2xl"></i>
                        <span class="ml-2 text-xl font-bold text-white">AutoBots</span>
                    </a>
                    
                    <!-- Main Navigation -->
                    <div class="hidden md:block ml-10">
                        <div class="flex items-center space-x-4">
                            <a href="customer_dashboard.php" 
                               class="text-white px-3 py-2 rounded-md text-sm font-medium hover:bg-gray-700">
                                <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
                            </a>
                            <a href="book_appointment.php" 
                               class="text-gray-300 hover:bg-gray-700 hover:text-white px-3 py-2 rounded-md text-sm font-medium">
                                <i class="fas fa-calendar-plus mr-2"></i>Book Service
                            </a>
                            <a href="manage_vehicles.php" 
                               class="text-gray-300 hover:bg-gray-700 hover:text-white px-3 py-2 rounded-md text-sm font-medium">
                                <i class="fas fa-car mr-2"></i>My Vehicles
                            </a>
                            <a href="service_history.php" 
                               class="text-gray-300 hover:bg-gray-700 hover:text-white px-3 py-2 rounded-md text-sm font-medium">
                                <i class="fas fa-history mr-2"></i>Service History
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Right Side Menu -->
                <div class="hidden md:flex items-center" x-data="{ profileOpen: false }">
                    <div class="relative">
                        <button @click="profileOpen = !profileOpen" 
                                class="flex items-center space-x-3 text-gray-300 hover:text-white focus:outline-none"
                                type="button">
                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user_data['first_name'] . ' ' . $user_data['last_name']); ?>&background=F97316&color=fff" 
                                 class="h-8 w-8 rounded-full">
                            <span class="text-sm font-medium"><?php echo htmlspecialchars($user_data['first_name']); ?></span>
                            <svg class="w-4 h-4 ml-1" :class="{'rotate-180': profileOpen}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>

                        <div x-show="profileOpen"
                             x-cloak
                             @click.away="profileOpen = false"
                             x-transition:enter="transition ease-out duration-100"
                             x-transition:enter-start="transform opacity-0 scale-95"
                             x-transition:enter-end="transform opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-75"
                             x-transition:leave-start="transform opacity-100 scale-100"
                             x-transition:leave-end="transform opacity-0 scale-95"
                             class="absolute right-0 mt-2 w-48 rounded-md shadow-lg py-1 bg-white ring-1 ring-black ring-opacity-5 z-50">
                            
                            <div class="px-4 py-2 border-b">
                                <p class="text-sm text-gray-700 font-medium">
                                    <?php echo htmlspecialchars($user_data['first_name'] . ' ' . $user_data['last_name']); ?>
                                </p>
                                <p class="text-xs text-gray-500"><?php echo htmlspecialchars($user_data['email']); ?></p>
                            </div>
                            
                            <a href="profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-user-circle mr-2"></i>My Profile
                            </a>
                            <button @click="showLogoutModal = true; profileOpen = false" 
                                    class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-gray-100">
                                <i class="fas fa-sign-out-alt mr-2"></i>Logout
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Mobile menu button -->
                <div class="md:hidden flex items-center">
                    <button type="button" 
                            class="mobile-menu-button inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-white hover:bg-gray-700 focus:outline-none"
                            aria-controls="mobile-menu" 
                            aria-expanded="false">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile menu -->
        <div class="md:hidden hidden" id="mobile-menu">
            <div class="px-2 pt-2 pb-3 space-y-1">
                <a href="customer_dashboard.php" class="text-white block px-3 py-2 rounded-md text-base font-medium">
                    <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
                </a>
                <a href="book_appointment.php" class="text-gray-300 hover:bg-gray-700 hover:text-white block px-3 py-2 rounded-md text-base font-medium">
                    <i class="fas fa-calendar-plus mr-2"></i>Book Service
                </a>
                <a href="manage_vehicles.php" class="text-gray-300 hover:bg-gray-700 hover:text-white block px-3 py-2 rounded-md text-base font-medium">
                    <i class="fas fa-car mr-2"></i>My Vehicles
                </a>
                <a href="service_history.php" class="text-gray-300 hover:bg-gray-700 hover:text-white block px-3 py-2 rounded-md text-base font-medium">
                    <i class="fas fa-history mr-2"></i>Service History
                </a>
                <div class="border-t border-gray-700 pt-4 pb-3">
                    <div class="flex items-center px-5">
                        <div class="flex-shrink-0">
                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user_data['first_name'] . ' ' . $user_data['last_name']); ?>&background=orange&color=fff" 
                                 class="h-10 w-10 rounded-full">
                        </div>
                        <div class="ml-3">
                            <div class="text-base font-medium text-white"><?php echo htmlspecialchars($user_data['first_name'] . ' ' . $user_data['last_name']); ?></div>
                            <div class="text-sm font-medium text-gray-400"><?php echo htmlspecialchars($user_data['email']); ?></div>
                        </div>
                    </div>
                    <div class="mt-3 px-2 space-y-1">
                        <a href="profile.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-400 hover:text-white hover:bg-gray-700">
                            <i class="fas fa-user mr-2"></i>Profile
                        </a>
                        <button @click="showLogoutModal = true; isOpen = false" 
                                class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-gray-100">
                            <i class="fas fa-sign-out-alt mr-2"></i>Logout
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <script>
    // Toggle mobile menu
    document.querySelector('.mobile-menu-button').addEventListener('click', function() {
        document.getElementById('mobile-menu').classList.toggle('hidden');
    });
    </script>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Welcome Banner -->
        <div class="bg-gradient-to-r from-gray-800 to-gray-900 rounded-lg shadow-lg p-6 mb-8 animate-fadeIn">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-white">Welcome back, <?php echo htmlspecialchars($user_data['first_name']); ?>!</h1>
                    <p class="mt-1 text-gray-400">Manage your vehicles and appointments here</p>
                </div>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-gray-800 rounded-lg shadow-lg p-6 transition duration-300 stat-card">
                <div class="flex items-center">
                    <div class="p-3 bg-orange-500/10 rounded-full">
                        <i class="fas fa-car text-orange-500 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-gray-400">Your Vehicles</p>
                        <h3 class="text-2xl font-bold text-white"><?php echo $stats['total_vehicles']; ?></h3>
                    </div>
                </div>
            </div>

            <div class="bg-gray-800 rounded-lg shadow-lg p-6 transition duration-300 stat-card">
                <div class="flex items-center">
                    <div class="p-3 bg-blue-500/10 rounded-full">
                        <i class="fas fa-calendar-check text-blue-500 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-gray-400">Upcoming Services</p>
                        <h3 class="text-2xl font-bold text-white"><?php echo (int)$stats['pending_appointments']; ?></h3>
                    </div>
                </div>
            </div>

            <div class="bg-gray-800 rounded-lg shadow-lg p-6 transition duration-300 stat-card">
                <div class="flex items-center">
                    <div class="p-3 bg-green-500/10 rounded-full">
                        <i class="fas fa-check-circle text-green-500 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-gray-400">Completed Services</p>
                        <h3 class="text-2xl font-bold text-white"><?php echo $stats['completed_services']; ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Appointments Section -->
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-gray-800 rounded-lg shadow-lg p-6">
                    <h2 class="text-xl font-semibold text-white mb-4">Upcoming Appointments</h2>
                    <?php if ($result_appointments->num_rows > 0): ?>
                        <div class="space-y-4">
                            <?php while($appointment = $result_appointments->fetch_assoc()): ?>
                                <div class="bg-gray-700 rounded-lg p-4 hover:bg-gray-600 transition card-zoom">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <h3 class="font-semibold text-white"><?php echo htmlspecialchars($appointment['service_name']); ?></h3>
                                            <p class="text-gray-400"><?php echo date('F j, Y g:i A', strtotime($appointment['appointment_date'])); ?></p>
                                            <p class="text-sm text-gray-400 mt-2">
                                                Vehicle: <?php echo htmlspecialchars($appointment['make'] . ' ' . $appointment['model']); ?>
                                            </p>
                                        </div>
                                        <div class="text-right">
                                            <span class="px-3 py-1 rounded-full text-sm 
                                                <?php echo $appointment['status'] == 'pending' ? 'bg-yellow-100 text-yellow-800' : 
                                                    ($appointment['status'] == 'completed' ? 'bg-green-100 text-green-800' : 
                                                    'bg-blue-100 text-blue-800'); ?>">
                                                <?php echo ucfirst($appointment['status']); ?>
                                            </span>
                                            <p class="mt-2 font-semibold text-white">$<?php echo number_format($appointment['price'], 2); ?></p>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <img src="https://cdn-icons-png.flaticon.com/512/6598/6598519.png" 
                                 alt="No appointments" class="w-24 h-24 mx-auto mb-4 opacity-50">
                            <p class="text-gray-400">No upcoming appointments</p>
                            <a href="book_appointment.php" class="mt-4 inline-block text-orange-500 hover:text-orange-400">
                                Schedule your first service →
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Vehicles Section -->
                <div class="bg-gray-800 rounded-lg shadow-lg p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-semibold text-white">Your Vehicles</h2>
                        <a href="manage_vehicles.php" class="text-orange-500 hover:text-orange-400">
                            Manage Vehicles →
                        </a>
                    </div>
                    <?php if ($result_vehicles->num_rows > 0): ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <?php while($vehicle = $result_vehicles->fetch_assoc()): ?>
                                <div class="bg-gray-700 rounded-lg p-4 card-zoom">
                                    <div class="flex items-start space-x-4">
                                        <div class="p-3 bg-gray-600 rounded-lg">
                                            <i class="fas fa-car text-orange-500 text-xl"></i>
                                        </div>
                                        <div>
                                            <h3 class="font-semibold text-white"><?php echo htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model']); ?></h3>
                                            <p class="text-sm text-gray-400">Year: <?php echo htmlspecialchars($vehicle['year']); ?></p>
                                            <p class="text-sm text-gray-400">Plate: <?php echo htmlspecialchars($vehicle['license_plate']); ?></p>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <img src="https://cdn-icons-png.flaticon.com/512/2554/2554969.png" 
                                 alt="No vehicles" class="w-24 h-24 mx-auto mb-4 opacity-50">
                            <p class="text-gray-400">No vehicles registered</p>
                            <a href="manage_vehicles.php" class="mt-4 inline-block text-orange-500 hover:text-orange-400">
                                Register your first vehicle →
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions Sidebar -->
            <div class="space-y-6">
                <div class="bg-gray-800 rounded-lg shadow-lg p-6">
                    <h2 class="text-xl font-semibold text-white mb-4">Quick Actions</h2>
                    <div class="space-y-3">
                        <a href="book_appointment.php" class="flex items-center space-x-3 p-3 bg-gray-700 rounded-lg hover:bg-gray-600 transition">
                            <i class="fas fa-calendar-plus text-orange-500"></i>
                            <span>Book New Appointment</span>
                        </a>
                        <a href="manage_vehicles.php" class="flex items-center space-x-3 p-3 bg-gray-700 rounded-lg hover:bg-gray-600 transition">
                            <i class="fas fa-car text-orange-500"></i>
                            <span>Add New Vehicle</span>
                        </a>
                        <a href="service_history.php" class="flex items-center space-x-3 p-3 bg-gray-700 rounded-lg hover:bg-gray-600 transition">
                            <i class="fas fa-history text-orange-500"></i>
                            <span>View Service History</span>
                        </a>
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

    <!-- Add this modal before closing body tag -->
    <div id="logoutModal" 
         x-data="{ show: false }" 
         x-show="show" 
         @keydown.escape.window="show = false"
         class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 flex items-center justify-center"
         x-cloak>
        <div class="relative p-4 w-full max-w-md h-full md:h-auto">
            <div class="relative bg-white rounded-lg shadow">
                <div class="p-6 text-center">
                    <i class="fas fa-sign-out-alt text-red-500 text-5xl mb-4"></i>
                    <h3 class="mb-5 text-lg font-normal text-gray-800">
                        Are you sure you want to logout?
                    </h3>
                    <button @click="show = false; window.location.href='logout.php'" 
                            type="button" 
                            class="text-white bg-red-600 hover:bg-red-700 font-medium rounded-lg text-sm inline-flex px-5 py-2.5 text-center mr-2">
                        Yes, Logout
                    </button>
                    <button @click="show = false" 
                            type="button" 
                            class="text-gray-500 bg-white hover:bg-gray-100 rounded-lg border border-gray-200 text-sm font-medium px-5 py-2.5">
                        Cancel
                    </button>
                </div>
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

    <!-- Add this script before closing body tag -->
    <script>
    document.addEventListener('alpine:init', () => {
        window.addEventListener('open-logout', () => {
            Alpine.store('modal', { show: true });
        });
    });
    </script>


    <!-- Add this logout modal before closing body tag -->
<div x-show="showLogoutModal"
     x-cloak
     class="fixed inset-0 z-50 overflow-y-auto"
     aria-labelledby="modal-title" 
     role="dialog" 
     aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <!-- Background overlay -->
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" 
             @click="showLogoutModal = false"></div>

        <!-- Modal panel -->
        <div class="relative inline-block align-bottom bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <div class="bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                        <i class="fas fa-sign-out-alt text-red-600"></i>
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                        <h3 class="text-lg leading-6 font-medium text-white">Confirm Logout</h3>
                        <div class="mt-2">
                            <p class="text-sm text-gray-300">Are you sure you want to logout?</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-gray-800 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <a href="logout.php" 
                   class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                    Confirm Logout
                </a>
                <button type="button" 
                        @click="showLogoutModal = false"
                        class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-600 shadow-sm px-4 py-2 bg-gray-700 text-base font-medium text-gray-300 hover:bg-gray-600 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                    Cancel
                </button>
            </div>
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