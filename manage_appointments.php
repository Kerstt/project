<?php
include 'includes/db.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
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

if (!$user_data) {
    session_destroy();
    header('Location: login.php');
    exit();
}

// First, add status color helper function
function getStatusColor($status) {
    switch($status) {
        case 'pending':
            return 'bg-yellow-100 text-yellow-800';
        case 'confirmed':
            return 'bg-blue-100 text-blue-800';
        case 'in-progress':
            return 'bg-indigo-100 text-indigo-800';
        case 'completed':
            return 'bg-green-100 text-green-800';
        case 'cancelled':
            return 'bg-red-100 text-red-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

// First, add these functions to handle appointment operations at the top of manage_appointments.php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        $conn->begin_transaction();
        try {
            switch ($_POST['action']) {
                case 'edit':
                    $appointment_id = $_POST['appointment_id'];
                    $status = $_POST['status'];
                    $technician_id = !empty($_POST['technician_id']) ? $_POST['technician_id'] : null;
                    $notes = $_POST['notes'];
                    
                    // Update appointment
                    $stmt = $conn->prepare("
                        UPDATE appointments 
                        SET status = ?, 
                            technician_id = ?, 
                            notes = ?, 
                            updated_at = NOW() 
                        WHERE appointment_id = ?
                    ");
                    $stmt->bind_param("sisi", $status, $technician_id, $notes, $appointment_id);
                    $stmt->execute();
                    
                    // Create notification
                    $notify_sql = "INSERT INTO notifications (user_id, type, message, appointment_id) 
                                 SELECT user_id, 'appointment_update', 
                                 CONCAT('Your appointment status has been updated to: ', ?), ? 
                                 FROM appointments WHERE appointment_id = ?";
                    $stmt = $conn->prepare($notify_sql);
                    $stmt->bind_param("sii", $status, $appointment_id, $appointment_id);
                    $stmt->execute();
                    
                    $conn->commit();
                    $_SESSION['success'] = "Appointment updated successfully";
                    break;

                case 'delete':
                    $appointment_id = $_POST['appointment_id'];
                    
                    // Start transaction
                    $conn->begin_transaction();
                    
                    try {
                        // First delete related records from appointment_logs
                        $delete_logs = "DELETE FROM appointment_logs WHERE appointment_id = ?";
                        $stmt = $conn->prepare($delete_logs);
                        $stmt->bind_param("i", $appointment_id);
                        $stmt->execute();
                        
                        // Delete from appointment_status_history if exists
                        $delete_history = "DELETE FROM appointment_status_history WHERE appointment_id = ?";
                        $stmt = $conn->prepare($delete_history);
                        $stmt->bind_param("i", $appointment_id);
                        $stmt->execute();
                        
                        // Delete from notifications
                        $delete_notifications = "DELETE FROM notifications WHERE appointment_id = ?";
                        $stmt = $conn->prepare($delete_notifications);
                        $stmt->bind_param("i", $appointment_id);
                        $stmt->execute();
                        
                        // Finally delete the appointment
                        $delete_appointment = "DELETE FROM appointments WHERE appointment_id = ?";
                        $stmt = $conn->prepare($delete_appointment);
                        $stmt->bind_param("i", $appointment_id);
                        $stmt->execute();
                        
                        $conn->commit();
                        $_SESSION['success'] = "Appointment deleted successfully";
                        
                    } catch (Exception $e) {
                        $conn->rollback();
                        $_SESSION['error'] = "Error deleting appointment: " . $e->getMessage();
                    }
                    
                    header('Location: manage_appointments.php');
                    exit();
                    break;
            }
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error_message'] = $e->getMessage();
        }
        header('Location: manage_appointments.php');
        exit();
    }
}

// Update the SQL queries in manage_appointments.php
if (isset($_GET['search']) || isset($_GET['status'])) {
    $conditions = [];
    $params = [];
    $types = "";

    if (!empty($_GET['search'])) {
        $search = "%" . $_GET['search'] . "%";
        $conditions[] = "(CONCAT(u.first_name, ' ', u.last_name) LIKE ? 
                        OR v.make LIKE ? 
                        OR v.model LIKE ?
                        OR COALESCE(s.name, sp.name) LIKE ?)";
        $params = array_merge($params, [$search, $search, $search, $search]);
        $types .= "ssss";
    }

    if (!empty($_GET['status'])) {
        $conditions[] = "a.status = ?";
        $params[] = $_GET['status'];
        $types .= "s";
    }

    $sql = "SELECT a.*, 
            u.first_name, u.last_name,
            v.make, v.model, v.year,
            COALESCE(s.name, sp.name) as service_name,
            COALESCE(s.price, sp.price) as service_price,
            CASE 
                WHEN a.package_id IS NOT NULL THEN 'package'
                ELSE 'service'
            END as service_type
            FROM appointments a
            JOIN users u ON a.user_id = u.user_id
            JOIN vehicles v ON a.vehicle_id = v.vehicle_id
            LEFT JOIN services s ON a.service_id = s.service_id
            LEFT JOIN service_packages sp ON a.package_id = sp.package_id";

    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }
    
    $sql .= " ORDER BY a.appointment_date DESC";
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    // Default query without filters
    $sql = "SELECT a.*, 
            u.first_name, u.last_name,
            v.make, v.model, v.year,
            COALESCE(s.name, sp.name) as service_name,
            COALESCE(s.price, sp.price) as service_price,
            CASE 
                WHEN a.package_id IS NOT NULL THEN 'package'
                ELSE 'service'
            END as service_type
            FROM appointments a
            JOIN users u ON a.user_id = u.user_id
            JOIN vehicles v ON a.vehicle_id = v.vehicle_id
            LEFT JOIN services s ON a.service_id = s.service_id
            LEFT JOIN service_packages sp ON a.package_id = sp.package_id
            ORDER BY a.appointment_date DESC";
    $result = $conn->query($sql);
}

// Fetch technicians for assignment
$technicians_sql = "SELECT user_id, first_name, last_name FROM users WHERE role = 'technician'";
$technicians = $conn->query($technicians_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Appointments - AutoBots</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
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
                
                <!-- Navigation Links -->
                <div class="hidden md:flex items-center space-x-4">
                    <a href="admin_dashboard.php" class="nav-link px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:text-blue-600">
                        <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
                    </a>
                    <a href="manage_users.php" class="nav-link px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:text-blue-600">
                        <i class="fas fa-users mr-2"></i>Users
                    </a>
                    <a href="manage_appointments.php" class="nav-link active px-3 py-2 rounded-md text-sm font-medium text-blue-600">
                        <i class="fas fa-calendar-alt mr-2"></i>Appointments
                    </a>
                    <a href="manage_services.php" class="nav-link px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:text-blue-600">
                        <i class="fas fa-wrench mr-2"></i>Services
                    </a>
                    <a href="notifications.php" class="nav-link px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:text-blue-600">
                        <i class="fas fa-bell mr-2"></i>Notifications
                    </a>
                    
                    <!-- Profile Dropdown -->
                    <div class="relative" x-data="{ profileOpen: false }">
                        <button @click="profileOpen = !profileOpen" 
                                class="flex items-center space-x-3 text-gray-600 hover:text-blue-600 focus:outline-none">
                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user_data['first_name'] . ' ' . $user_data['last_name']); ?>&background=2563eb&color=fff" 
                                 class="h-8 w-8 rounded-full">
                            <span class="text-sm font-medium"><?php echo htmlspecialchars($user_data['first_name']); ?></span>
                            <svg class="w-4 h-4" :class="{'rotate-180': profileOpen}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>

                        <!-- Profile Dropdown Menu -->
                        <div x-show="profileOpen"
                             x-cloak
                             @click.away="profileOpen = false"
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

    <div class="container mx-auto p-4">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">Manage Appointments</h1>
            
            <!-- Search Form -->
            <form class="flex space-x-2">
                <input type="text" name="search" placeholder="Search appointments..." 
                       value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>"
                       class="px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                    <i class="fas fa-search"></i> Search
                </button>
            </form>
        </div>

        <!-- Replace the existing appointments table with this enhanced version -->
<div class="bg-white shadow-lg rounded-lg overflow-hidden">
    <!-- Table Header with Search and Filters -->
    <!-- Replace the existing search/filter section -->
<div class="p-5 border-b border-gray-200 flex justify-between items-center flex-wrap gap-4">
    <h2 class="text-xl font-semibold text-gray-800">
        <i class="fas fa-calendar-alt mr-2 text-blue-600"></i>
        Appointments List
    </h2>
    
    <form method="GET" class="flex gap-4" id="searchFilterForm">
        <div class="relative">
            <input type="text" 
                   name="search" 
                   id="searchInput"
                   value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>"
                   placeholder="Search appointments..."
                   class="pl-10 pr-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
        </div>
        
        <select name="status" 
                id="statusFilter" 
                class="border rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                onchange="this.form.submit()">
            <option value="">All Status</option>
            <option value="pending" <?php echo isset($_GET['status']) && $_GET['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
            <option value="confirmed" <?php echo isset($_GET['status']) && $_GET['status'] == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
            <option value="in-progress" <?php echo isset($_GET['status']) && $_GET['status'] == 'in-progress' ? 'selected' : ''; ?>>In Progress</option>
            <option value="completed" <?php echo isset($_GET['status']) && $_GET['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
            <option value="cancelled" <?php echo isset($_GET['status']) && $_GET['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
        </select>
    </form>
</div>

    <!-- Table -->
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Customer Info
                    </th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Vehicle Details
                    </th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Service
                    </th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Date & Time
                    </th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Status
                    </th>
                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Actions
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr class="hover:bg-gray-50 transition-colors duration-150">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-10 w-10">
                                    <img class="h-10 w-10 rounded-full" 
                                         src="https://ui-avatars.com/api/?name=<?php echo urlencode($row['first_name'] . ' ' . $row['last_name']); ?>&background=2563eb&color=fff" 
                                         alt="<?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>">
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        #<?php echo $row['appointment_id']; ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($row['make'] . ' ' . $row['model']); ?></div>
                            <div class="text-xs text-gray-500">Year: <?php echo htmlspecialchars($row['year'] ?? 'N/A'); ?></div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($row['service_name']); ?></div>
                            <div class="text-xs text-gray-500">
                                <?php echo $row['service_type'] === 'package' ? 'Package' : 'Single Service'; ?>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-sm text-gray-900">
                                <?php echo date('M d, Y', strtotime($row['appointment_date'])); ?>
                            </div>
                            <div class="text-xs text-gray-500">
                                <?php echo date('h:i A', strtotime($row['appointment_date'])); ?>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo getStatusColor($row['status']); ?>">
                                <?php echo ucfirst($row['status']); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <div class="flex space-x-3">
                                <button onclick="viewAppointment(<?php echo htmlspecialchars(json_encode($row)); ?>)"
                                        class="text-blue-600 hover:text-blue-900 transition-colors duration-150">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($row)); ?>)"
                                        class="bg-blue-100 text-blue-600 hover:bg-blue-200 px-3 py-1 rounded-md transition-colors duration-200">
                                    <i class="fas fa-edit mr-1"></i> Edit
                                </button>
                                <button onclick="openDeleteModal(<?php echo $row['appointment_id']; ?>)"
                                        class="bg-red-100 text-red-600 hover:bg-red-200 px-3 py-1 rounded-md transition-colors duration-200">
                                    <i class="fas fa-trash-alt mr-1"></i> Delete
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
        <div class="flex-1 flex justify-between sm:hidden">
            <a href="#" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                Previous
            </a>
            <a href="#" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                Next
            </a>
        </div>
        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
            <div>
                <p class="text-sm text-gray-700">
                    Showing <span class="font-medium">1</span> to <span class="font-medium">10</span> of <span class="font-medium">20</span> results
                </p>
            </div>
            <div>
                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                    <a href="#" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                        Previous
                    </a>
                    <a href="#" class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                        1
                    </a>
                    <a href="#" class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                        2
                    </a>
                    <a href="#" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                        Next
                    </a>
                </nav>
            </div>
        </div>
    </div>
</div>

    </div>

    <!-- Add these modals before the closing body tag -->

<!-- View Appointment Modal -->
<div id="viewModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900">Appointment Details</h3>
                <button onclick="closeModal('viewModal')" class="text-gray-400 hover:text-gray-500">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="appointmentDetails" class="space-y-4">
                <!-- Details will be populated by JavaScript -->
            </div>
        </div>
    </div>
</div>

<!-- Edit Appointment Modal -->
<div id="editModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-medium text-gray-900">Edit Appointment</h3>
            <button onclick="closeModal('editModal')" class="text-gray-400 hover:text-gray-500">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="editForm" method="POST" class="space-y-4">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="appointment_id" id="edit_appointment_id">
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select name="status" id="edit_status" 
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="pending">Pending</option>
                    <option value="confirmed">Confirmed</option>
                    <option value="in-progress">In Progress</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Assign Technician</label>
                <select name="technician_id" id="edit_technician" 
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="">Select Technician</option>
                    <?php 
                    $technicians->data_seek(0);
                    while($tech = $technicians->fetch_assoc()): ?>
                        <option value="<?php echo $tech['user_id']; ?>">
                            <?php echo htmlspecialchars($tech['first_name'] . ' ' . $tech['last_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                <textarea name="notes" id="edit_notes" rows="3" 
                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
            </div>
            
            <div class="flex justify-end space-x-3 pt-4">
                <button type="button" onclick="closeModal('editModal')"
                        class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition-colors">
                    Cancel
                </button>
                <button type="submit"
                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Replace the Delete Modal HTML -->
<div id="deleteModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3 text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
                <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
            </div>
            <h3 class="text-lg font-medium text-gray-900 mb-2">Delete Appointment</h3>
            <p class="text-sm text-gray-500 mb-6">Are you sure you want to delete this appointment? This action cannot be undone.</p>
            
            <form id="deleteForm" method="POST" class="mt-2">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="appointment_id" id="delete_appointment_id">
                
                <div class="flex justify-center space-x-4">
                    <button type="button" 
                            onclick="closeModal('deleteModal')"
                            class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition-colors">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors">
                        Delete
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add this right after the delete modal -->
<div id="viewModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900">Appointment Details</h3>
                <button onclick="closeModal('viewModal')" class="text-gray-400 hover:text-gray-500">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="appointmentDetails" class="space-y-4">
                <!-- Details will be populated by JavaScript -->
            </div>
            <div class="mt-6 flex justify-end">
                <button onclick="closeModal('viewModal')" 
                        class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Update the JavaScript viewAppointment function -->
<script>
function viewAppointment(appointment) {
    const details = document.getElementById('appointmentDetails');
    details.innerHTML = `
        <div class="space-y-3">
            <p class="flex justify-between">
                <span class="font-medium">Customer:</span>
                <span>${appointment.first_name} ${appointment.last_name}</span>
            </p>
            <p class="flex justify-between">
                <span class="font-medium">Service:</span>
                <span>${appointment.service_name}</span>
            </p>
            <p class="flex justify-between">
                <span class="font-medium">Type:</span>
                <span>${appointment.service_type === 'package' ? 'Package' : 'Individual Service'}</span>
            </p>
            <p class="flex justify-between">
                <span class="font-medium">Price:</span>
                <span>$${parseFloat(appointment.service_price).toFixed(2)}</span>
            </p>
            <p class="flex justify-between">
                <span class="font-medium">Vehicle:</span>
                <span>${appointment.make} ${appointment.model}</span>
            </p>
            <p class="flex justify-between">
                <span class="font-medium">Date:</span>
                <span>${new Date(appointment.appointment_date).toLocaleString()}</span>
            </p>
            <p class="flex justify-between">
                <span class="font-medium">Status:</span>
                <span class="px-2 rounded-full ${getStatusColorClass(appointment.status)}">
                    ${appointment.status}
                </span>
            </p>
            <p class="flex justify-between">
                <span class="font-medium">Technician:</span>
                <span>${appointment.tech_first_name ? 
                    appointment.tech_first_name + ' ' + appointment.tech_last_name : 
                    'Not assigned'}</span>
            </p>
            ${appointment.notes ? `
                <div class="border-t pt-3 mt-3">
                    <p class="font-medium mb-1">Notes:</p>
                    <p class="text-gray-600">${appointment.notes}</p>
                </div>
            ` : ''}
        </div>
    `;
    document.getElementById('viewModal').classList.remove('hidden');
}

function getStatusColorClass(status) {
    switch(status) {
        case 'pending': return 'bg-yellow-100 text-yellow-800';
        case 'confirmed': return 'bg-blue-100 text-blue-800';
        case 'in-progress': return 'bg-indigo-100 text-indigo-800';
        case 'completed': return 'bg-green-100 text-green-800';
        case 'cancelled': return 'bg-red-100 text-red-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modals = ['viewModal', 'editModal', 'deleteModal'];
    modals.forEach(modalId => {
        const modal = document.getElementById(modalId);
        if (event.target === modal) {
            closeModal(modalId);
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const statusFilter = document.getElementById('statusFilter');
    const form = document.getElementById('searchFilterForm');
    
    let timeoutId;

    searchInput.addEventListener('input', function() {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(() => {
            form.submit();
        }, 500);
    });

    // Clear search
    const clearSearch = () => {
        searchInput.value = '';
        statusFilter.value = '';
        form.submit();
    };
});

function openEditModal(appointment) {
    // Set form values
    document.getElementById('edit_appointment_id').value = appointment.appointment_id;
    document.getElementById('edit_status').value = appointment.status || 'pending';
    document.getElementById('edit_technician').value = appointment.technician_id || '';
    document.getElementById('edit_notes').value = appointment.notes || '';
    
    // Show modal
    document.getElementById('editModal').classList.remove('hidden');
}

// Update the edit form submission
document.getElementById('editForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Validate form
    const appointmentId = document.getElementById('edit_appointment_id').value;
    const status = document.getElementById('edit_status').value;
    
    if (!appointmentId || !status) {
        alert('Please fill in all required fields');
        return;
    }
    
    // Confirm before submitting
    if (confirm('Are you sure you want to update this appointment?')) {
        this.submit();
    }
});

function openDeleteModal(appointmentId) {
    document.getElementById('delete_appointment_id').value = appointmentId;
    document.getElementById('deleteModal').classList.remove('hidden');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
}

// Form submission handling
document.getElementById('editForm').addEventListener('submit', function(e) {
    e.preventDefault();
    if (confirm('Are you sure you want to update this appointment?')) {
        this.submit();
    }
});

document.getElementById('deleteForm').addEventListener('submit', function(e) {
    e.preventDefault();
    if (confirm('Are you sure you want to delete this appointment? This cannot be undone.')) {
        this.submit();
    }
});

// Close modals when clicking outside
window.onclick = function(event) {
    const modals = ['editModal', 'deleteModal', 'viewModal'];
    modals.forEach(modalId => {
        const modal = document.getElementById(modalId);
        if (event.target === modal) {
            closeModal(modalId);
        }
    });
}

// Close modals with escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modals = ['editModal', 'deleteModal', 'viewModal'];
        modals.forEach(modalId => closeModal(modalId));
    }
});

// Add error handling for form submission
<?php if (isset($_SESSION['error'])): ?>
    alert("<?php echo addslashes($_SESSION['error']); ?>");
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['success'])): ?>
    alert("<?php echo addslashes($_SESSION['success']); ?>");
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>
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