<?php
include 'includes/db.php';
include 'includes/auth_middleware.php';
session_start();

// Check admin authentication
checkAdminAuth();

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_status':
                $appointment_id = $_POST['appointment_id'];
                $new_status = $_POST['status'];
                $notes = $_POST['notes'];
                
                // Begin transaction
                $conn->begin_transaction();
                try {
                    // Get current status
                    $stmt = $conn->prepare("SELECT status FROM appointments WHERE appointment_id = ?");
                    $stmt->bind_param("i", $appointment_id);
                    $stmt->execute();
                    $old_status = $stmt->get_result()->fetch_assoc()['status'];
                    
                    // Update appointment status
                    $stmt = $conn->prepare("UPDATE appointments SET status = ?, notes = CONCAT(IFNULL(notes,''), '\n', ?) WHERE appointment_id = ?");
                    $stmt->bind_param("ssi", $new_status, $notes, $appointment_id);
                    $stmt->execute();
                    
                    // Log the change
                    $stmt = $conn->prepare("INSERT INTO appointment_logs (appointment_id, status_from, status_to, notes, created_by) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("isssi", $appointment_id, $old_status, $new_status, $notes, $_SESSION['user_id']);
                    $stmt->execute();
                    
                    // If status is completed and payment is pending, create payment record
                    if ($new_status == 'completed') {
                        $stmt = $conn->prepare("
                            INSERT INTO payments (appointment_id, amount, status, payment_date)
                            SELECT a.appointment_id, s.price, 'pending', NOW()
                            FROM appointments a
                            JOIN services s ON a.service_id = s.service_id
                            WHERE a.appointment_id = ? AND NOT EXISTS (
                                SELECT 1 FROM payments WHERE appointment_id = ?
                            )
                        ");
                        $stmt->bind_param("ii", $appointment_id, $appointment_id);
                        $stmt->execute();
                    }
                    
                    $conn->commit();
                    $success_message = "Appointment updated successfully";
                } catch (Exception $e) {
                    $conn->rollback();
                    $error_message = "Error updating appointment: " . $e->getMessage();
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
        }
    }
}

// Fetch statistics
$stats_sql = "SELECT 
    (SELECT COUNT(*) FROM appointments WHERE status = 'pending') as pending_appointments,
    (SELECT COUNT(*) FROM appointments WHERE status = 'completed') as completed_appointments,
    (SELECT COUNT(*) FROM users WHERE role = 'customer') as total_customers,
    (SELECT SUM(amount) FROM payments WHERE status = 'paid') as total_revenue";
$stats = $conn->query($stats_sql)->fetch_assoc();

// Fetch recent appointments with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

$appointments_sql = "
    SELECT a.*, 
           u.first_name, u.last_name, 
           s.name as service_name, s.price,
           t.first_name as tech_first_name, t.last_name as tech_last_name,
           p.status as payment_status, p.payment_method
    FROM appointments a 
    JOIN users u ON a.user_id = u.user_id 
    JOIN services s ON a.service_id = s.service_id 
    LEFT JOIN users t ON a.technician_id = t.user_id
    LEFT JOIN payments p ON a.appointment_id = p.appointment_id
    ORDER BY a.appointment_date DESC
    LIMIT ? OFFSET ?";
    
$stmt = $conn->prepare($appointments_sql);
$stmt->bind_param("ii", $per_page, $offset);
$stmt->execute();
$appointments = $stmt->get_result();

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
                        <h3 class="text-2xl font-bold"><?php echo $stats['total_customers']; ?></h3>
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
                        <h3 class="text-2xl font-bold"><?php echo $stats['pending_appointments']; ?></h3>
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
                        <h3 class="text-2xl font-bold">$<?php echo number_format($stats['total_revenue'], 2); ?></h3>
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
                        <h3 class="text-2xl font-bold"><?php echo $stats['completed_appointments']; ?></h3>
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
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php while($appointment = $appointments->fetch_assoc()): ?>
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
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php echo !$appointment['payment_status'] ? 'bg-red-100 text-red-800' : 
                                            ($appointment['payment_status'] == 'paid' ? 'bg-green-100 text-green-800' : 
                                            'bg-yellow-100 text-yellow-800'); ?>">
                                        <?php echo $appointment['payment_status'] ? ucfirst($appointment['payment_status']) : 'Unpaid'; ?>
                                    </span>
                                    <?php if($appointment['payment_method']): ?>
                                        <span class="ml-2 text-xs text-gray-500">
                                            <i class="fas <?php echo $appointment['payment_method'] == 'credit_card' ? 'fa-credit-card' : 'fa-money-bill'; ?>"></i>
                                            <?php echo ucfirst($appointment['payment_method']); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <button onclick="openUpdateModal(<?php echo $appointment['appointment_id']; ?>)"
                                            class="text-blue-600 hover:text-blue-900 mr-2">
                                        <i class="fas fa-edit"></i> Update
                                    </button>
                                    <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to cancel this appointment?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="appointment_id" value="<?php echo $appointment['appointment_id']; ?>">
                                        <button type="submit" class="text-red-600 hover:text-red-900">
                                            <i class="fas fa-trash"></i> Cancel
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
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
                
                <div class="flex justify-end space-x-2">
                    <button type="button" onclick="closeModal('updateStatusModal')" 
                            class="bg-gray-500 text-white px-4 py-2 rounded">Cancel</button>
                    <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded">Update</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add this JavaScript for modal handling -->
    <script>
    function openUpdateModal(appointmentId) {
        document.getElementById('modal_appointment_id').value = appointmentId;
        document.getElementById('updateStatusModal').classList.remove('hidden');
    }

    function closeModal(modalId) {
        document.getElementById(modalId).classList.add('hidden');
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        if (event.target.classList.contains('modal-overlay')) {
            closeModal('updateStatusModal');
        }
    }
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
</body>
</html>