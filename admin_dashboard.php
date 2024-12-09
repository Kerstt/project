<?php
// At the very top of admin_dashboard.php
// Start session handling
ini_set('session.gc_maxlifetime', 86400); // 24 hours
ini_set('session.cookie_lifetime', 86400);

session_start();
include 'includes/db.php';
include 'includes/auth_middleware.php';

// Check admin authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: login.php');
    exit();
}

// Refresh session timestamp
$_SESSION['last_activity'] = time();

// Session timeout check - 24 hours
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 86400)) {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch admin data
$user_sql = "SELECT * FROM users WHERE user_id = ? AND role = 'admin'";
$stmt = $conn->prepare($user_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();

// Rest of your existing code...

// Add search functionality
if (isset($_GET['search'])) {
    $search = "%" . $_GET['search'] . "%";
    $appointments_sql = "
        SELECT a.*, 
               u.first_name, u.last_name,
               COALESCE(s.name, sp.name) as service_name,
               COALESCE(s.price, sp.price) as service_price,
               t.first_name as tech_first_name, t.last_name as tech_last_name
        FROM appointments a 
        JOIN users u ON a.user_id = u.user_id 
        LEFT JOIN services s ON a.service_id = s.service_id 
        LEFT JOIN service_packages sp ON a.package_id = sp.package_id
        LEFT JOIN users t ON a.technician_id = t.user_id
        WHERE CONCAT(u.first_name, ' ', u.last_name) LIKE ?
           OR COALESCE(s.name, sp.name) LIKE ?
           OR a.status LIKE ?
        ORDER BY a.appointment_date DESC";
    
    $stmt = $conn->prepare($appointments_sql);
    $stmt->bind_param("sss", $search, $search, $search);
    $stmt->execute();
    $appointments = $stmt->get_result();
} else {
    // Default query for appointments
    $appointments_sql = "
        SELECT a.*, 
               u.first_name, u.last_name,
               COALESCE(s.name, sp.name) as service_name,
               COALESCE(s.price, sp.price) as service_price,
               t.first_name as tech_first_name, t.last_name as tech_last_name,
               CASE 
                   WHEN a.package_id IS NOT NULL THEN 'package'
                   ELSE 'service'
               END as service_type
        FROM appointments a 
        JOIN users u ON a.user_id = u.user_id 
        LEFT JOIN services s ON a.service_id = s.service_id 
        LEFT JOIN service_packages sp ON a.package_id = sp.package_id
        LEFT JOIN users t ON a.technician_id = t.user_id
        ORDER BY a.appointment_date DESC";
    $appointments = $conn->query($appointments_sql);
}

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_status':
                $appointment_id = $_POST['appointment_id'];
                $new_status = $_POST['status'];
                $technician_id = $_POST['technician_id'] ?? null;
                $notes = $_POST['notes'];
                
                $conn->begin_transaction();
                try {
                    // Get current appointment details including package info
                    $stmt = $conn->prepare("
                        SELECT a.*, 
                               COALESCE(s.name, sp.name) as service_name,
                               CASE 
                                   WHEN a.package_id IS NOT NULL THEN 'package'
                                   ELSE 'service'
                               END as booking_type
                        FROM appointments a
                        LEFT JOIN services s ON a.service_id = s.service_id
                        LEFT JOIN service_packages sp ON a.package_id = sp.package_id
                        WHERE a.appointment_id = ?
                    ");
                    $stmt->bind_param("i", $appointment_id);
                    $stmt->execute();
                    $current = $stmt->get_result()->fetch_assoc();
                    
                    // Update appointment
                    $stmt = $conn->prepare("
                        UPDATE appointments 
                        SET status = ?,
                            technician_id = ?,
                            notes = CONCAT(IFNULL(notes,''), '\n', ?),
                            updated_at = NOW()
                        WHERE appointment_id = ?
                    ");
                    $stmt->bind_param("sisi", $new_status, $technician_id, $notes, $appointment_id);
                    $stmt->execute();
                    
                    // Create notification message
                    $service_type = $current['booking_type'] == 'package' ? 'service package' : 'service';
                    $message = "Your {$service_type} appointment for {$current['service_name']} has been updated to: " . $new_status;
                    
                    // Notify customer
                    $stmt = $conn->prepare("
                        INSERT INTO notifications (user_id, type, message, appointment_id) 
                        VALUES (?, 'status_update', ?, ?)
                    ");
                    $stmt->bind_param("isi", $current['user_id'], $message, $appointment_id);
                    $stmt->execute();
                    
                    // Notify technician if assigned
                    if ($technician_id && $technician_id != $current['technician_id']) {
                        $tech_message = "You have been assigned to appointment #" . $appointment_id;
                        $stmt = $conn->prepare("
                            INSERT INTO notifications (user_id, type, message, appointment_id)
                            VALUES (?, 'assignment', ?, ?)
                        ");
                        $stmt->bind_param("isi", $technician_id, $tech_message, $appointment_id);
                        $stmt->execute();
                    }
                    
                    // Log status change
                    $stmt = $conn->prepare("
                        INSERT INTO appointment_logs 
                        (appointment_id, status_from, status_to, notes, created_by) 
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->bind_param("isssi", $appointment_id, $current['status'], $new_status, $notes, $_SESSION['user_id']);
                    $stmt->execute();
                    
                    $conn->commit();
                    $_SESSION['success_message'] = "Appointment updated successfully";
                    
                } catch (Exception $e) {
                    $conn->rollback();
                    $_SESSION['error_message'] = "Error updating appointment: " . $e->getMessage();
                }
                break;
                
            case 'delete':
                $appointment_id = $_POST['appointment_id'];
                $stmt = $conn->prepare("UPDATE appointments SET status = 'cancelled' WHERE appointment_id = ?");
                $stmt->bind_param("i", $appointment_id);
                if ($stmt->execute()) {
                    $success_message = "Appointment cancelled successfully";
                } else {
                    $error_message = "Error cancelling appointment";
                }
                break;

            case 'cancel':
                $appointment_id = $_POST['appointment_id'];
                
                $conn->begin_transaction();
                try {
                    // Get current appointment details
                    $stmt = $conn->prepare("SELECT status, user_id, technician_id FROM appointments WHERE appointment_id = ?");
                    $stmt->bind_param("i", $appointment_id);
                    $stmt->execute();
                    $current = $stmt->get_result()->fetch_assoc();
                    
                    // Update appointment status
                    $stmt = $conn->prepare("UPDATE appointments SET status = 'cancelled', updated_at = NOW() WHERE appointment_id = ?");
                    $stmt->bind_param("i", $appointment_id);
                    $stmt->execute();
                    
                    // Notify customer
                    $notify_sql = "INSERT INTO notifications (user_id, type, message, appointment_id) VALUES (?, 'status_update', ?, ?)";
                    $message = "Your appointment has been cancelled by admin";
                    $stmt = $conn->prepare($notify_sql);
                    $stmt->bind_param("isi", $current['user_id'], $message, $appointment_id);
                    $stmt->execute();
                    
                    // Notify technician if assigned
                    if ($current['technician_id']) {
                        $tech_message = "Appointment #$appointment_id has been cancelled";
                        $stmt->bind_param("isi", $current['technician_id'], $tech_message, $appointment_id);
                        $stmt->execute();
                    }
                    
                    $conn->commit();
                    $_SESSION['success_message'] = "Appointment cancelled successfully";
                } catch (Exception $e) {
                    $conn->rollback();
                    $_SESSION['error_message'] = "Error cancelling appointment: " . $e->getMessage();
                }
                break;
        }
    }
}

// Fetch statistics
$stats_sql = "SELECT 
    (SELECT COUNT(*) FROM appointments WHERE status = 'pending') as pending_appointments,
    (SELECT COUNT(*) FROM appointments WHERE status = 'completed') as completed_appointments,
    (SELECT COUNT(*) FROM users WHERE role = 'customer') as total_customers";
$stats = $conn->query($stats_sql)->fetch_assoc();

// Fetch recent appointments
if (!isset($_GET['search'])) {
    $appointments_sql = "
        SELECT a.*, 
               u.first_name, u.last_name,
               COALESCE(s.name, sp.name) as service_name,
               COALESCE(s.price, sp.price) as service_price,
               t.first_name as tech_first_name, t.last_name as tech_last_name
        FROM appointments a 
        JOIN users u ON a.user_id = u.user_id 
        LEFT JOIN services s ON a.service_id = s.service_id 
        LEFT JOIN service_packages sp ON a.package_id = sp.package_id
        LEFT JOIN users t ON a.technician_id = t.user_id
        ORDER BY a.appointment_date DESC
        LIMIT 10";
}

// Execute query and store result
try {
    $appointments = $conn->query($appointments_sql);
    if (!$appointments) {
        throw new Exception($conn->error);
    }
} catch (Exception $e) {
    $_SESSION['error_message'] = "Error fetching appointments: " . $e->getMessage();
    $appointments = null;
}

// Fetch available technicians
$technicians_sql = "SELECT user_id, first_name, last_name FROM users WHERE role = 'technician'";
$technicians = $conn->query($technicians_sql);
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
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        .stat-card:hover { transform: translateY(-5px); }
        .nav-link.active { color: #2563eb; border-bottom: 2px solid #2563eb; }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-gray-50" x-data="{ showLogoutModal: false }">
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
                
                <!-- Replace the existing navigation menu buttons with this -->
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
                    
                    <!-- Profile Dropdown -->
                    <div class="relative" x-data="{ profileOpen: false }">
                        <button @click="profileOpen = !profileOpen" 
                                class="flex items-center space-x-3 text-gray-600 hover:text-blue-600 focus:outline-none"
                                type="button">
                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user_data['first_name'] . ' ' . $user_data['last_name']); ?>&background=2563eb&color=fff" 
                                 class="h-8 w-8 rounded-full">
                            <span class="text-sm font-medium"><?php echo htmlspecialchars($user_data['first_name']); ?></span>
                            <svg class="w-4 h-4" :class="{'rotate-180': profileOpen}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div class="bg-white rounded-lg shadow p-6 transition-transform duration-200 stat-card">
                <div class="flex items-center">
                    <div class="p-3 bg-blue-100 rounded-full">
                        <i class="fas fa-clock text-blue-600"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-gray-500 text-sm">Pending Appointments</h3>
                        <p class="text-2xl font-semibold"><?php echo $stats['pending_appointments']; ?></p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-6 transition-transform duration-200 stat-card">
                <div class="flex items-center">
                    <div class="p-3 bg-green-100 rounded-full">
                        <i class="fas fa-check-circle text-green-600"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-gray-500 text-sm">Completed Services</h3>
                        <p class="text-2xl font-semibold"><?php echo $stats['completed_appointments']; ?></p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-6 transition-transform duration-200 stat-card">
                <div class="flex items-center">
                    <div class="p-3 bg-purple-100 rounded-full">
                        <i class="fas fa-users text-purple-600"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-gray-500 text-sm">Total Customers</h3>
                        <p class="text-2xl font-semibold"><?php echo $stats['total_customers']; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Appointments -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-semibold">Recent Appointments</h2>
                <form method="GET" class="flex space-x-2">
                    <input type="text" 
                           name="search" 
                           placeholder="Search by customer name, service..." 
                           value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>"
                           class="px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <button type="submit" 
                            class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-search mr-2"></i>Search
                    </button>
                    <?php if(isset($_GET['search'])): ?>
                        <a href="admin_dashboard.php" 
                           class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition-colors">
                            Clear
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Service</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if ($appointments): ?>
                            <?php while($appointment = $appointments->fetch_assoc()): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10">
                                                <img class="h-10 w-10 rounded-full" 
                                                     src="https://ui-avatars.com/api/?name=<?php echo urlencode($appointment['first_name'] . ' ' . $appointment['last_name']); ?>&background=random" 
                                                     alt="">
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            <?php echo htmlspecialchars($appointment['service_name']); ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            $<?php echo number_format($appointment['service_price'], 2); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?>
                                    </td>
                                    <!-- Update the status cell in the appointments table -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            <?php 
                                            switch($appointment['status']) {
                                                case 'pending':
                                                    echo 'bg-yellow-100 text-yellow-800';
                                                    break;
                                                case 'confirmed':
                                                    echo 'bg-blue-100 text-blue-800';
                                                    break;
                                                case 'in-progress':
                                                    echo 'bg-indigo-100 text-indigo-800';
                                                    break;
                                                case 'completed':
                                                    echo 'bg-green-100 text-green-800';
                                                    break;
                                                case 'cancelled':
                                                    echo 'bg-red-100 text-red-800';
                                                    break;
                                                default:
                                                    echo 'bg-gray-100 text-gray-800';
                                            }
                                            ?>">
                                            <?php echo ucfirst($appointment['status']); ?>
                                        </span>
                                    </td>
                                    <!-- Update the status update button in the table -->
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <button onclick="openUpdateModal('<?php echo $appointment['appointment_id']; ?>', 
                                                                       '<?php echo $appointment['status']; ?>', 
                                                                       '<?php echo $appointment['technician_id']; ?>')"
                                                class="text-blue-600 hover:text-blue-900 mr-2">
                                            <i class="fas fa-edit"></i> Update
                                        </button>
                                        <button onclick="confirmCancel(<?php echo $appointment['appointment_id']; ?>)"
                                                class="text-red-600 hover:text-red-900">
                                            <i class="fas fa-times"></i> Cancel
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                    No appointments found or error loading appointments
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add this modal for updating appointment status -->
    <div id="updateStatusModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <form id="updateStatusForm" method="POST">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="appointment_id" id="modal_appointment_id">
                
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Status</label>
                    <select name="status" class="shadow border rounded w-full py-2 px-3">
                        <option value="pending">Pending</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="in-progress">In Progress</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Notes</label>
                    <textarea name="notes" class="shadow border rounded w-full py-2 px-3"></textarea>
                </div>
                
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Assign Technician</label>
                    <select name="technician_id" class="shadow border rounded w-full py-2 px-3">
                        <option value="">Select Technician</option>
                        <?php while($tech = $technicians->fetch_assoc()): ?>
                            <option value="<?php echo $tech['user_id']; ?>">
                                <?php echo htmlspecialchars($tech['first_name'] . ' ' . $tech['last_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="flex justify-end space-x-2">
                    <button type="button" onclick="closeModal('updateStatusModal')" 
                            class="bg-gray-500 text-white px-4 py-2 rounded">Cancel</button>
                    <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded">Update</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Update the status modal HTML -->
    <div id="updateStatusModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900">Update Appointment Status</h3>
                <button onclick="closeModal('updateStatusModal')" class="text-gray-400 hover:text-gray-500">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form id="updateStatusForm" method="POST">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="appointment_id" id="modal_appointment_id">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select name="status" id="modal_status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="pending">Pending</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="in-progress">In Progress</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Assign Technician</label>
                    <select name="technician_id" id="modal_technician" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Select Technician</option>
                        <?php 
                        $technicians->data_seek(0);
                        while($tech = $technicians->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $tech['user_id']; ?>">
                                <?php echo htmlspecialchars($tech['first_name'] . ' ' . $tech['last_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                    <textarea name="notes" id="modal_notes" rows="3" 
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('updateStatusModal')"
                            class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        Update
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Cancel Confirmation Modal -->
    <div id="cancelModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3 text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
                    <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                </div>
                <h3 class="text-lg font-medium text-gray-900">Cancel Appointment</h3>
                <div class="mt-2 px-7 py-3">
                    <p class="text-sm text-gray-500">Are you sure you want to cancel this appointment?</p>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="cancel">
                    <input type="hidden" name="appointment_id" id="cancel_appointment_id">
                    <div class="flex justify-center space-x-4 mt-4">
                        <button type="button" onclick="closeModal('cancelModal')"
                                class="px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400">
                            No, Keep it
                        </button>
                        <button type="submit"
                                class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">
                            Yes, Cancel it
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add this JavaScript for modal handling -->
    <script>
    // Update modal handling functions
    function openUpdateModal(appointmentId, currentStatus, technicianId) {
        // Set values in the modal
        document.getElementById('modal_appointment_id').value = appointmentId;
        document.getElementById('modal_status').value = currentStatus;
        if (technicianId) {
            document.getElementById('modal_technician').value = technicianId;
        }
        
        // Show the modal
        document.getElementById('updateStatusModal').classList.remove('hidden');
    }

    function confirmCancel(appointmentId) {
        if(confirm('Are you sure you want to cancel this appointment?')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="cancel">
                <input type="hidden" name="appointment_id" value="${appointmentId}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }

    function closeModal(modalId) {
        document.getElementById(modalId).classList.add('hidden');
    }

    // Close modals when clicking outside
    window.onclick = function(event) {
        const modals = ['updateStatusModal', 'cancelModal'];
        modals.forEach(modalId => {
            const modal = document.getElementById(modalId);
            if (event.target === modal) {
                closeModal(modalId);
            }
        });
    }

    // Add search functionality
    document.querySelector('input[name="search"]').addEventListener('keyup', function(e) {
        if (e.key === 'Enter') {
            this.closest('form').submit();
        }
    });
    </script>

    <!-- Add to admin_dashboard.php before </body> -->
    <script>
    function checkNewPayments() {
        fetch('check_new_payments.php')
            .then(response => response.json())
            .then(data => {
                if (data.new_payments) {
                    updatePaymentStatus();
                }
            });
    }

    function updatePaymentStatus() {
        location.reload(); // For simplicity, reload the page
    }

    // Check for new payments every 30 seconds
    setInterval(checkNewPayments, 30000);
    </script>

    <!-- Add to admin_dashboard.php and technician_dashboard.php before </body> -->
    <script>
    // Real-time payment status updates
    function checkPaymentUpdates() {
        const appointmentElements = document.querySelectorAll('[data-appointment-id]');
        const appointmentIds = Array.from(appointmentElements).map(el => el.dataset.appointmentId);
        
        fetch('check_payment_updates.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ appointment_ids: appointmentIds })
        })
        .then(response => response.json())
        .then(data => {
            if (data.updates) {
                data.updates.forEach(update => {
                    const element = document.querySelector(`[data-appointment-id="${update.appointment_id}"]`);
                    if (element) {
                        element.querySelector('.payment-status').className = 
                            `payment-status px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${
                                update.payment_status === 'paid' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'
                            }`;
                        element.querySelector('.payment-status').textContent = update.payment_status;
                    }
                });
            }
        });
    }

    // Check for updates every 30 seconds
    setInterval(checkPaymentUpdates, 30000);
    </script>

    <!-- Add this JavaScript for real-time search (optional) -->
    <script>
    document.querySelector('input[name="search"]').addEventListener('keyup', function(e) {
        if (e.key === 'Enter') {
            this.closest('form').submit();
        }
    });
    </script>

    <!-- Logout Modal -->
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
            <div class="relative inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                            <i class="fas fa-sign-out-alt text-red-600"></i>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                            <h3 class="text-lg leading-6 font-medium text-gray-900">Confirm Logout</h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500">Are you sure you want to logout?</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <a href="logout.php" 
                       class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                        Confirm Logout
                    </a>
                    <button type="button" 
                            @click="showLogoutModal = false"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>