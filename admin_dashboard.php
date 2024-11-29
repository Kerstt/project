<?php
include 'includes/db.php';
session_start();

if ($_SESSION['role'] != 'admin') {
    header('Location: login.php');
    exit();
}

// Add this query with other dashboard stats queries
$services_sql = "SELECT COUNT(*) AS total_services FROM services";
$services_result = $conn->query($services_sql);
$total_services = $services_result->fetch_assoc()['total_services'];

// Rest of your existing queries remain the same
$total_users_sql = "SELECT COUNT(*) AS total_users FROM users";
$total_users_result = $conn->query($total_users_sql);
$total_users = $total_users_result->fetch_assoc()['total_users'];

$total_appointments_sql = "SELECT COUNT(*) AS total_appointments FROM appointments";
$total_appointments_result = $conn->query($total_appointments_sql);
$total_appointments = $total_appointments_result->fetch_assoc()['total_appointments'];

$revenue_sql = "SELECT SUM(amount) AS total_revenue FROM payments";
$revenue_result = $conn->query($revenue_sql);
$total_revenue = $revenue_result->fetch_assoc()['total_revenue'] ?? 0; // Added fallback for null revenue

// Updated query to use user_id instead of customer_id
$sql = "SELECT a.*, u.first_name, u.last_name, s.name as service_name 
        FROM appointments a 
        JOIN users u ON a.user_id = u.user_id 
        JOIN services s ON a.service_id = s.service_id 
        ORDER BY a.appointment_date DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - AutoBots</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .stat-card:hover { transform: translateY(-5px); }
        .nav-link.active { color: #2563eb; border-bottom: 2px solid #2563eb; }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="index.php" class="flex items-center space-x-2">
                        <i class="fas fa-car text-blue-600 text-2xl"></i>
                        <span class="text-xl font-bold">AutoBots Admin</span>
                    </a>
                </div>
                
                <div class="hidden md:flex items-center space-x-4">
                    <a href="admin_dashboard.php" class="nav-link active px-3 py-2 rounded-md text-sm font-medium transition-colors duration-200">
                        <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
                    </a>
                    <a href="manage_users.php" class="nav-link px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:text-blue-600 transition-colors duration-200">
                        <i class="fas fa-users mr-2"></i>Users
                    </a>
                    <a href="manage_appointments.php" class="nav-link px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:text-blue-600 transition-colors duration-200">
                        <i class="fas fa-calendar-alt mr-2"></i>Appointments
                    </a>
                    <a href="manage_services.php" class="nav-link px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:text-blue-600 transition-colors duration-200">
                        <i class="fas fa-wrench mr-2"></i>Services
                    </a>
                    <a href="notifications.php" class="nav-link px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:text-blue-600 transition-colors duration-200">
                        <i class="fas fa-bell mr-2"></i>Notifications
                    </a>
                    <div class="relative">
                        <button class="flex items-center space-x-2 text-gray-600 hover:text-blue-600">
                            <img src="https://ui-avatars.com/api/?name=Admin&background=2563eb&color=fff" class="h-8 w-8 rounded-full">
                            <span>Admin</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="stat-card bg-white rounded-lg shadow-md p-6 transition-all duration-300">
                <div class="flex items-center">
                    <div class="p-3 bg-blue-100 rounded-full">
                        <i class="fas fa-users text-blue-600"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-gray-500 text-sm">Total Users</p>
                        <h3 class="text-2xl font-bold"><?php echo $total_users; ?></h3>
                    </div>
                </div>
            </div>
            <div class="stat-card bg-white rounded-lg shadow-md p-6 transition-all duration-300">
                <div class="flex items-center">
                    <div class="p-3 bg-green-100 rounded-full">
                        <i class="fas fa-calendar-check text-green-600"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-gray-500 text-sm">Appointments</p>
                        <h3 class="text-2xl font-bold"><?php echo $total_appointments; ?></h3>
                    </div>
                </div>
            </div>
            <div class="stat-card bg-white rounded-lg shadow-md p-6 transition-all duration-300">
                <div class="flex items-center">
                    <div class="p-3 bg-yellow-100 rounded-full">
                        <i class="fas fa-dollar-sign text-yellow-600"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-gray-500 text-sm">Revenue</p>
                        <h3 class="text-2xl font-bold">$<?php echo number_format($total_revenue, 2); ?></h3>
                    </div>
                </div>
            </div>
            <div class="stat-card bg-white rounded-lg shadow-md p-6 transition-all duration-300">
                <div class="flex items-center">
                    <div class="p-3 bg-purple-100 rounded-full">
                        <i class="fas fa-wrench text-purple-600"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-gray-500 text-sm">Services</p>
                        <h3 class="text-2xl font-bold"><?php echo $total_services; ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Appointments -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-semibold">Recent Appointments</h2>
                <div class="flex space-x-2">
                    <input type="text" placeholder="Search appointments..." 
                           class="px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <button class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-search mr-2"></i>Search
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Service</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php while($appointment = $result->fetch_assoc()): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <img class="h-10 w-10 rounded-full" src="https://ui-avatars.com/api/?name=<?php echo urlencode($appointment['first_name'] . ' ' . $appointment['last_name']); ?>&background=random" alt="">
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo htmlspecialchars($appointment['service_name']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php echo $appointment['status'] == 'completed' ? 'bg-green-100 text-green-800' : 
                                            ($appointment['status'] == 'pending' ? 'bg-yellow-100 text-yellow-800' : 
                                            'bg-gray-100 text-gray-800'); ?>">
                                        <?php echo ucfirst($appointment['status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="#" class="text-blue-600 hover:text-blue-900 mr-3">View</a>
                                    <a href="#" class="text-green-600 hover:text-green-900 mr-3">Edit</a>
                                    <a href="#" class="text-red-600 hover:text-red-900">Delete</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>