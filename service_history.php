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
    
    $conn->begin_transaction();
    try {
        // First verify the service belongs to user and is completed
        $check_sql = "SELECT * FROM appointments WHERE appointment_id = ? AND user_id = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("ii", $service_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Service record not found");
        }
        
        // Delete the service record
        $delete_sql = "DELETE FROM appointments WHERE appointment_id = ? AND user_id = ?";
        $stmt = $conn->prepare($delete_sql);
        $stmt->bind_param("ii", $service_id, $user_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to delete service record");
        }
        
        $conn->commit();
        $_SESSION['success_message'] = "Service record deleted successfully";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = $e->getMessage();
    }
    
    header('Location: service_history.php');
    exit();
}

// Fetch service history with complete details
$sql = "SELECT a.*, 
        s.name as service_name, s.price,
        v.make, v.model, v.year,
        u.first_name as tech_first_name, u.last_name as tech_last_name
        FROM appointments a 
        JOIN services s ON a.service_id = s.service_id 
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
                    <a href="customer_dashboard.php" class="text-gray-600 hover:text-gray-900">
                        <i class="fas fa-tachometer-alt mr-1"></i> Dashboard
                    </a>
                    <a href="logout.php" class="text-red-600 hover:text-red-700">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 py-8">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Service History</h1>
            <p class="text-gray-600">View all your past services</p>
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
            <div class="bg-white shadow-md rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Service</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vehicle</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Technician</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php while($service = $services->fetch_assoc()): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($service['service_name']); ?></div>
                                    <div class="text-sm text-gray-500">$<?php echo number_format($service['price'], 2); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <?php echo htmlspecialchars($service['make'] . ' ' . $service['model']); ?>
                                    </div>
                                    <div class="text-sm text-gray-500">Year: <?php echo htmlspecialchars($service['year']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('M d, Y h:i A', strtotime($service['appointment_date'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php 
                                    echo $service['tech_first_name'] ? 
                                        htmlspecialchars($service['tech_first_name'] . ' ' . $service['tech_last_name']) : 
                                        'Not assigned';
                                    ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                        <?php echo $service['status'] == 'completed' ? 'bg-green-100 text-green-800' : 
                                            ($service['status'] == 'pending' ? 'bg-yellow-100 text-yellow-800' : 
                                            'bg-blue-100 text-blue-800'); ?>">
                                        <?php echo ucfirst($service['status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <button onclick="confirmDelete(<?php echo $service['appointment_id']; ?>)" 
                                            class="text-red-600 hover:text-red-900 focus:outline-none">
                                        <i class="fas fa-trash-alt mr-1"></i> Delete
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-8">
                <img src="https://cdn-icons-png.flaticon.com/512/6598/6598519.png" 
                     alt="No service history" class="w-24 h-24 mx-auto mb-4 opacity-50">
                <p class="text-gray-500">No service history found</p>
                <a href="book_appointment.php" class="mt-4 inline-block text-blue-600 hover:text-blue-700">
                    Book your first service →
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Add Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3 text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
                    <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-4">Delete Service Record</h3>
                <div class="mt-2 px-7 py-3">
                    <p class="text-sm text-gray-500">
                        Are you sure you want to delete this service record? This action cannot be undone.
                    </p>
                </div>
                <form action="service_history.php" method="POST">
                    <input type="hidden" name="delete_service" value="1">
                    <input type="hidden" name="service_id" id="delete_service_id">
                    <div class="flex justify-center space-x-4 mt-4">
                        <button type="button" 
                                onclick="closeDeleteModal()"
                                class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 bg-red-500 text-white rounded-md hover:bg-red-600 transition">
                            Delete
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="deleteConfirmModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3 text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
                    <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-4">Delete Service Record</h3>
                <div class="mt-2 px-7 py-3">
                    <p class="text-sm text-gray-500">Are you sure you want to delete this service record? This action cannot be undone.</p>
                </div>
                <div class="mt-4">
                    <form method="POST">
                        <input type="hidden" name="delete_service" value="1">
                        <input type="hidden" name="service_id" id="serviceIdToDelete">
                        <button type="button" onclick="closeDeleteModal()" 
                                class="inline-block bg-gray-200 text-gray-700 px-4 py-2 rounded-md mr-2 hover:bg-gray-300">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="inline-block bg-red-500 text-white px-4 py-2 rounded-md hover:bg-red-600">
                            Delete
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
    function showDeleteModal(serviceId) {
        document.getElementById('delete_service_id').value = serviceId;
        document.getElementById('deleteModal').classList.remove('hidden');
    }

    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.add('hidden');
    }

    function confirmDelete(serviceId) {
        document.getElementById('serviceIdToDelete').value = serviceId;
        document.getElementById('deleteConfirmModal').classList.remove('hidden');
    }

    // Close modal when clicking outside
    document.addEventListener('click', function(event) {
        const modal = document.getElementById('deleteModal');
        if (event.target === modal) {
            closeDeleteModal();
        }
    });

    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('deleteConfirmModal');
        if (event.target === modal) {
            closeDeleteModal();
        }
    }

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
                setTimeout(() => message.remove(), 500);
            }, 3000);
        });
    });
    </script>
</body>
</html>