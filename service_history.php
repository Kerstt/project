<?php
include 'includes/db.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle service deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_service'])) {
    $service_id = $_POST['service_id'];
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // First verify the service belongs to user
        $check_sql = "SELECT * FROM appointments WHERE appointment_id = ? AND user_id = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("ii", $service_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // 1. First delete appointment_logs
            $delete_logs_sql = "DELETE FROM appointment_logs WHERE appointment_id = ?";
            $stmt = $conn->prepare($delete_logs_sql);
            $stmt->bind_param("i", $service_id);
            $stmt->execute();
            
            // 2. Delete any status_history records if they exist
            $delete_history_sql = "DELETE FROM appointment_status_history WHERE appointment_id = ?";
            $stmt = $conn->prepare($delete_history_sql);
            $stmt->bind_param("i", $service_id);
            $stmt->execute();
            
            // 3. Delete any notifications
            $delete_notifications_sql = "DELETE FROM notifications WHERE appointment_id = ?";
            $stmt = $conn->prepare($delete_notifications_sql);
            $stmt->bind_param("i", $service_id);
            $stmt->execute();
            
            // 4. Finally delete the appointment
            $delete_appointment_sql = "DELETE FROM appointments WHERE appointment_id = ? AND user_id = ?";
            $stmt = $conn->prepare($delete_appointment_sql);
            $stmt->bind_param("ii", $service_id, $user_id);
            $stmt->execute();
            
            // Commit transaction
            $conn->commit();
            $_SESSION['success'] = "Service record deleted successfully";
        } else {
            $_SESSION['error'] = "Invalid service record";
        }
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        $_SESSION['error'] = "Error deleting service record: " . $e->getMessage();
    }
    
    // Redirect back to service history
    header('Location: service_history.php');
    exit();
}

// Fetch service history with complete details
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
        WHERE a.user_id = ? 
        ORDER BY a.appointment_date DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$services = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Service History - AutoBots</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
        .animate-fade { animation: fadeIn 0.5s ease-out; }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .table-row-hover:hover { background-color: rgba(31, 41, 55, 0.4); }
    </style>
</head>
<body class="bg-gray-900 text-gray-100">
    <!-- Navigation -->
    <nav class="bg-gray-800 border-b border-gray-700">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center">
                    <a href="customer_dashboard.php" class="flex items-center">
                        <i class="fas fa-car text-orange-500 text-2xl"></i>
                        <span class="ml-2 text-xl font-bold text-white">AutoBots</span>
                    </a>
                    
                    <!-- Main Navigation -->
                    <div class="hidden md:block ml-10">
                        <div class="flex items-center space-x-4">
                            <a href="customer_dashboard.php" class="text-gray-300 hover:bg-gray-700 hover:text-white px-3 py-2 rounded-md text-sm font-medium">
                                <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
                            </a>
                            <a href="book_appointment.php" class="text-gray-300 hover:bg-gray-700 hover:text-white px-3 py-2 rounded-md text-sm font-medium">
                                <i class="fas fa-calendar-plus mr-2"></i>Book Service
                            </a>
                            <a href="manage_vehicles.php" class="text-gray-300 hover:bg-gray-700 hover:text-white px-3 py-2 rounded-md text-sm font-medium">
                                <i class="fas fa-car mr-2"></i>My Vehicles
                            </a>
                            <a href="service_history.php" class="text-white bg-gray-700 px-3 py-2 rounded-md text-sm font-medium">
                                <i class="fas fa-history mr-2"></i>Service History
                            </a>
                            <a href="customer_manage_appointments.php" class="text-gray-300 hover:bg-gray-700 hover:text-white px-3 py-2 rounded-md text-sm font-medium">
                            <i class="fas fa-calendar-alt mr-2"></i>Manage Appointments
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Page Header -->
        <div class="bg-gradient-to-r from-gray-800 to-gray-900 rounded-lg shadow-lg p-6 mb-8 animate-fade">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-white">Service History</h1>
                    <p class="mt-1 text-gray-400">View and manage your service records</p>
                </div>
                <a href="book_appointment.php" class="bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 rounded-lg transition-colors">
                    <i class="fas fa-plus mr-2"></i>Book New Service
                </a>
            </div>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert-message bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <?php 
                echo $_SESSION['success_message'];
                unset($_SESSION['success_message']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert-message bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <?php 
                echo $_SESSION['error_message'];
                unset($_SESSION['error_message']);
                ?>
            </div>
        <?php endif; ?>

        <?php if ($services->num_rows > 0): ?>
            <div class="bg-gray-800 rounded-lg shadow-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-700">
                        <thead class="bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Service</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Vehicle</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Technician</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700">
                            <?php while($service = $services->fetch_assoc()): ?>
                                <tr class="table-row-hover transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-white"><?php echo htmlspecialchars($service['service_name']); ?></div>
                                        <div class="text-sm text-gray-400">$<?php echo number_format($service['price'], 2); ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-white">
                                            <?php echo htmlspecialchars($service['make'] . ' ' . $service['model']); ?>
                                        </div>
                                        <div class="text-sm text-gray-400">Year: <?php echo htmlspecialchars($service['year']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-400">
                                        <?php echo date('M d, Y h:i A', strtotime($service['appointment_date'])); ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-400">
                                        <?php 
                                        echo $service['tech_first_name'] ? 
                                            htmlspecialchars($service['tech_first_name'] . ' ' . $service['tech_last_name']) : 
                                            '<span class="text-gray-500">Not assigned</span>';
                                        ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full
                                            <?php echo $service['status'] == 'completed' ? 'bg-green-100 text-green-800' : 
                                                ($service['status'] == 'pending' ? 'bg-yellow-100 text-yellow-800' : 
                                                'bg-blue-100 text-blue-800'); ?>">
                                            <?php echo ucfirst($service['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm font-medium">
                                        <button onclick="confirmDelete(<?php echo $service['appointment_id']; ?>)"
                                                class="text-red-400 hover:text-red-500 transition-colors">
                                            <i class="fas fa-trash-alt mr-1"></i> Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-gray-800 rounded-lg shadow-lg p-8 text-center">
                <img src="https://cdn-icons-png.flaticon.com/512/6598/6598519.png" 
                     alt="No service history" class="w-24 h-24 mx-auto mb-4 opacity-50">
                <p class="text-gray-400 mb-4">No service history found</p>
                <a href="book_appointment.php" 
                   class="inline-flex items-center px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg transition-colors">
                    <i class="fas fa-calendar-plus mr-2"></i>
                    Book Your First Service
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Replace the Delete Modal HTML with this simplified version -->
    <div id="deleteModal" 
         class="fixed inset-0 z-50 overflow-y-auto hidden"
         style="background-color: rgba(0, 0, 0, 0.5);">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="relative inline-block align-bottom bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                            <i class="fas fa-exclamation-triangle text-red-600"></i>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                            <h3 class="text-lg leading-6 font-medium text-white">Delete Service Record</h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-400">Are you sure you want to delete this service record? This action cannot be undone.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-800 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <form method="POST" id="deleteForm">
                        <input type="hidden" name="delete_service" value="1">
                        <input type="hidden" name="service_id" id="delete_service_id">
                        <button type="submit"
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                            Delete
                        </button>
                        <button type="button" 
                                onclick="closeDeleteModal()"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-600 shadow-sm px-4 py-2 bg-gray-700 text-base font-medium text-gray-300 hover:bg-gray-600 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Replace the JavaScript with this updated version -->
    <script>
    function confirmDelete(serviceId) {
        document.getElementById('delete_service_id').value = serviceId;
        document.getElementById('deleteModal').classList.remove('hidden');
    }

    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.add('hidden');
    }

    // Close modal when clicking outside
    document.addEventListener('click', function(event) {
        const modal = document.getElementById('deleteModal');
        if (event.target === modal) {
            closeDeleteModal();
        }
    });

    // Add escape key listener
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeDeleteModal();
        }
    });

    // Show success/error messages and fade out
    document.addEventListener('DOMContentLoaded', function() {
        const messages = document.querySelectorAll('.alert-message');
        messages.forEach(message => {
            setTimeout(() => {
                message.style.opacity = '0';
                message.style.transition = 'opacity 0.5s';
                setTimeout(() => message.remove(), 500);
            }, 3000);
        });
    });
    </script>
</body>
</html>