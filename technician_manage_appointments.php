<?php
ini_set('session.gc_maxlifetime', 86400); // 24 hours
ini_set('session.cookie_lifetime', 86400);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'includes/db.php';

// Check technician authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'technician') {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

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

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        $conn->begin_transaction();
        try {
            switch ($_POST['action']) {
                case 'update_status':
                    $appointment_id = $_POST['appointment_id'];
                    $new_status = $_POST['status'];
                    $notes = $_POST['notes'];
                    
                    // Verify the appointment belongs to this technician
                    $check_sql = "SELECT * FROM appointments WHERE appointment_id = ? AND technician_id = ?";
                    $stmt = $conn->prepare($check_sql);
                    $stmt->bind_param("ii", $appointment_id, $user_id);
                    $stmt->execute();
                    if ($stmt->get_result()->num_rows === 0) {
                        throw new Exception("Unauthorized access to appointment");
                    }
                    
                    // Update appointment
                    $stmt = $conn->prepare("
                        UPDATE appointments 
                        SET status = ?,
                            notes = CONCAT(IFNULL(notes,''), '\n', ?),
                            updated_at = NOW()
                        WHERE appointment_id = ? AND technician_id = ?
                    ");
                    $stmt->bind_param("ssii", $new_status, $notes, $appointment_id, $user_id);
                    $stmt->execute();
                    
                    // Create notification
                    $notify_sql = "INSERT INTO notifications (user_id, type, message, appointment_id) 
                                 SELECT user_id, 'status_update', 
                                 CONCAT('Your appointment status has been updated to: ', ?), ? 
                                 FROM appointments WHERE appointment_id = ?";
                    $stmt = $conn->prepare($notify_sql);
                    $stmt->bind_param("sii", $new_status, $appointment_id, $appointment_id);
                    $stmt->execute();
                    
                    $conn->commit();
                    $_SESSION['success_message'] = "Appointment status updated successfully";
                    break;
            }
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error_message'] = $e->getMessage();
        }
        header('Location: technician_manage_appointments.php');
        exit();
    }
}

// Update the SQL queries for appointments
if (isset($_GET['search'])) {
    $search = "%{$_GET['search']}%";
    $sql = "SELECT a.*, 
            u.first_name, u.last_name,
            v.make, v.model,
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
            WHERE a.technician_id = ? AND
                  (CONCAT(u.first_name, ' ', u.last_name) LIKE ? 
                   OR v.make LIKE ? 
                   OR v.model LIKE ?
                   OR COALESCE(s.name, sp.name) LIKE ?)
            ORDER BY a.appointment_date DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issss", $user_id, $search, $search, $search, $search);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    // Default query without search
    $sql = "SELECT a.*, 
            u.first_name, u.last_name,
            v.make, v.model,
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
            WHERE a.technician_id = ?
            ORDER BY a.appointment_date DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
}

// Fetch technician data
$user_sql = "SELECT * FROM users WHERE user_id = ? AND role = 'technician'";
$stmt = $conn->prepare($user_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Appointments - Technician Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-gray-50" x-data="{ showViewModal: false, showEditModal: false, selectedAppointment: null, showLogoutModal: false }">
    <!-- Replace the existing navigation section with this -->
    <nav class="bg-white shadow-lg sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="index.php" class="flex items-center space-x-2">
                        <i class="fas fa-car text-blue-600 text-2xl"></i>
                        <span class="text-xl font-bold">AutoBots Tech</span>
                    </a>
                </div>

                <!-- Navigation Links & Profile Dropdown -->
                <div class="flex items-center space-x-4">
                    <a href="technician_dashboard.php" class="nav-link px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:text-blue-600">
                        <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
                    </a>
                    <a href="technician_manage_appointments.php" class="nav-link active px-3 py-2 rounded-md text-sm font-medium text-blue-600">
                        <i class="fas fa-calendar mr-2"></i>Appointments
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

    <!-- Add Logout Modal -->
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

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Manage Appointments</h1>
            
            <!-- Search Form -->
            <form action="" method="GET" class="flex gap-4">
                <input type="text" name="search" placeholder="Search appointments..."
                       value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>"
                       class="px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                    <i class="fas fa-search mr-2"></i>Search
                </button>
            </form>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <?php 
                echo $_SESSION['success_message'];
                unset($_SESSION['success_message']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <?php 
                echo $_SESSION['error_message'];
                unset($_SESSION['error_message']);
                ?>
            </div>
        <?php endif; ?>

        <!-- Appointments Table -->
        <div class="bg-white shadow overflow-hidden rounded-lg">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vehicle</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Service</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10">
                                        <img class="h-10 w-10 rounded-full" 
                                             src="https://ui-avatars.com/api/?name=<?php echo urlencode($row['first_name'] . ' ' . $row['last_name']); ?>&background=random" 
                                             alt="">
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo htmlspecialchars($row['make'] . ' ' . $row['model']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo htmlspecialchars($row['service_name']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo date('M d, Y h:i A', strtotime($row['appointment_date'])); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo getStatusColor($row['status']); ?>">
                                    <?php echo ucfirst($row['status']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <button @click="selectedAppointment = <?php echo htmlspecialchars(json_encode($row)); ?>; showViewModal = true"
                                        class="text-blue-600 hover:text-blue-900 mr-3">
                                    <i class="fas fa-eye"></i> View
                                </button>
                                <button @click="selectedAppointment = <?php echo htmlspecialchars(json_encode($row)); ?>; showEditModal = true"
                                        class="text-green-600 hover:text-green-900">
                                    <i class="fas fa-edit"></i> Update
                                </button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- View Modal -->
    <div x-show="showViewModal" x-cloak class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900">Appointment Details</h3>
                <button @click="showViewModal = false" class="text-gray-400 hover:text-gray-500">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="space-y-4">
                <template x-if="selectedAppointment">
                    <div class="space-y-3">
                        <p><span class="font-medium">Customer: </span>
                           <span x-text="selectedAppointment.first_name + ' ' + selectedAppointment.last_name"></span></p>
                        <p><span class="font-medium">Vehicle: </span>
                           <span x-text="selectedAppointment.make + ' ' + selectedAppointment.model"></span></p>
                        <p><span class="font-medium">Service: </span>
                           <span x-text="selectedAppointment.service_name"></span></p>
                        <p><span class="font-medium">Date: </span>
                           <span x-text="new Date(selectedAppointment.appointment_date).toLocaleString()"></span></p>
                        <p><span class="font-medium">Status: </span>
                           <span x-text="selectedAppointment.status.charAt(0).toUpperCase() + selectedAppointment.status.slice(1)"></span></p>
                        <p><span class="font-medium">Notes: </span>
                           <span x-text="selectedAppointment.notes || 'No notes'"></span></p>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div x-show="showEditModal" x-cloak class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900">Update Appointment Status</h3>
                <button @click="showEditModal = false" class="text-gray-400 hover:text-gray-500">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form action="" method="POST" x-ref="updateForm">
                <template x-if="selectedAppointment">
                    <div class="space-y-4">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="appointment_id" x-model="selectedAppointment.appointment_id">
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Status</label>
                            <select name="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="pending">Pending</option>
                                <option value="in-progress">In Progress</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Notes</label>
                            <textarea name="notes" rows="3" 
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    placeholder="Add service notes..."></textarea>
                        </div>
                        
                        <div class="flex justify-end space-x-3">
                            <button type="button" @click="showEditModal = false"
                                    class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                                Cancel
                            </button>
                            <button type="submit"
                                    class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                Update Status
                            </button>
                        </div>
                    </div>
                </template>
            </form>
        </div>
    </div>
</body>
</html>