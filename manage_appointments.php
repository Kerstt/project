<?php
include 'includes/db.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
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
                    $technician_id = $_POST['technician_id'];
                    $notes = $_POST['notes'];
                    
                    $stmt = $conn->prepare("
                        UPDATE appointments 
                        SET status = ?, technician_id = ?, notes = ?, updated_at = NOW() 
                        WHERE appointment_id = ?
                    ");
                    $stmt->bind_param("sisi", $status, $technician_id, $notes, $appointment_id);
                    $stmt->execute();
                    
                    // Create notification
                    $notify_sql = "INSERT INTO notifications (user_id, type, message, appointment_id) 
                                 SELECT user_id, 'appointment_update', 
                                 CONCAT('Your appointment has been updated'), ? 
                                 FROM appointments WHERE appointment_id = ?";
                    $stmt = $conn->prepare($notify_sql);
                    $stmt->bind_param("ii", $appointment_id, $appointment_id);
                    $stmt->execute();
                    
                    $conn->commit();
                    $_SESSION['success_message'] = "Appointment updated successfully";
                    break;

                case 'delete':
                    $appointment_id = $_POST['appointment_id'];
                    
                    // Check if appointment can be deleted
                    $check_sql = "SELECT status FROM appointments WHERE appointment_id = ?";
                    $stmt = $conn->prepare($check_sql);
                    $stmt->bind_param("i", $appointment_id);
                    $stmt->execute();
                    $result = $stmt->get_result()->fetch_assoc();
                    
                    if ($result['status'] == 'completed') {
                        throw new Exception("Cannot delete completed appointments");
                    }
                    
                    $stmt = $conn->prepare("DELETE FROM appointments WHERE appointment_id = ?");
                    $stmt->bind_param("i", $appointment_id);
                    $stmt->execute();
                    
                    $conn->commit();
                    $_SESSION['success_message'] = "Appointment deleted successfully";
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
if (isset($_GET['search'])) {
    $search = "%{$_GET['search']}%";
    $sql = "SELECT a.*, 
            u.first_name, u.last_name,
            v.make, v.model,
            COALESCE(s.name, sp.name) as service_name,
            COALESCE(s.price, sp.price) as service_price,
            t.first_name as tech_first_name, t.last_name as tech_last_name,
            CASE 
                WHEN a.package_id IS NOT NULL THEN 'package'
                ELSE 'service'
            END as service_type
            FROM appointments a
            JOIN users u ON a.user_id = u.user_id
            JOIN vehicles v ON a.vehicle_id = v.vehicle_id
            LEFT JOIN services s ON a.service_id = s.service_id
            LEFT JOIN service_packages sp ON a.package_id = sp.package_id
            LEFT JOIN users t ON a.technician_id = t.user_id
            WHERE CONCAT(u.first_name, ' ', u.last_name) LIKE ? 
            OR v.make LIKE ? 
            OR v.model LIKE ?
            OR COALESCE(s.name, sp.name) LIKE ?
            ORDER BY a.appointment_date DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $search, $search, $search, $search);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    // Default query without search
    $sql = "SELECT a.*, 
            u.first_name, u.last_name,
            v.make, v.model,
            COALESCE(s.name, sp.name) as service_name,
            COALESCE(s.price, sp.price) as service_price,
            t.first_name as tech_first_name, t.last_name as tech_last_name,
            CASE 
                WHEN a.package_id IS NOT NULL THEN 'package'
                ELSE 'service'
            END as service_type
            FROM appointments a
            JOIN users u ON a.user_id = u.user_id
            JOIN vehicles v ON a.vehicle_id = v.vehicle_id
            LEFT JOIN services s ON a.service_id = s.service_id
            LEFT JOIN service_packages sp ON a.package_id = sp.package_id
            LEFT JOIN users t ON a.technician_id = t.user_id
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
    <title>Manage Appointments</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <nav class="bg-white shadow">
        <div class="container mx-auto px-4">
            <div class="flex justify-between">
                <div class="flex space-x-4">
                    <a href="index.php" class="py-5 px-3 text-gray-700">Home</a>
                    <a href="admin_dashboard.php" class="py-5 px-3 text-gray-700">Dashboard</a>
                </div>
                <div class="flex space-x-4">
                    <a href="profile.php" class="py-5 px-3 text-gray-700">Profile</a>
                    <a href="logout.php" class="py-5 px-3 text-gray-700">Logout</a>
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

        <!-- Update the table structure -->
        <div class="overflow-x-auto bg-white rounded-lg shadow-md">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Service</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vehicle</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Technician</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php while($row = $result->fetch_assoc()): ?>
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
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">
                                    <?php echo htmlspecialchars($row['service_name']); ?>
                                    <?php if($row['service_type'] == 'package'): ?>
                                        <span class="ml-2 px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                            Package
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-sm text-gray-500">
                                    $<?php echo number_format($row['service_price'], 2); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">
                                    <?php echo htmlspecialchars($row['make'] . ' ' . $row['model']); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo date('M d, Y h:i A', strtotime($row['appointment_date'])); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo $row['tech_first_name'] ? 
                                    htmlspecialchars($row['tech_first_name'] . ' ' . $row['tech_last_name']) : 
                                    '<span class="text-yellow-600">Not assigned</span>'; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    <?php echo getStatusColor($row['status']); ?>">
                                    <?php echo ucfirst($row['status']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <button onclick='viewAppointment(<?php echo json_encode($row); ?>)'
                                        class="text-blue-600 hover:text-blue-900 mr-2">
                                    <i class="fas fa-eye"></i> View
                                </button>
                                <button onclick='openEditModal(<?php echo json_encode($row); ?>)'
                                        class="text-green-600 hover:text-green-900 mr-2">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <?php if($row['status'] != 'completed'): ?>
                                    <button onclick="confirmDelete(<?php echo $row['appointment_id']; ?>)"
                                            class="text-red-600 hover:text-red-900">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
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
<div id="editModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900">Edit Appointment</h3>
                <button onclick="closeModal('editModal')" class="text-gray-400 hover:text-gray-500">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="editForm" method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="appointment_id" id="edit_appointment_id">
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Status</label>
                        <select name="status" id="edit_status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            <option value="pending">Pending</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="in-progress">In Progress</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Technician</label>
                        <select name="technician_id" id="edit_technician" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
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
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Notes</label>
                        <textarea name="notes" id="edit_notes" rows="3" 
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"></textarea>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeModal('editModal')"
                                class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                            Save Changes
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3 text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
                <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
            </div>
            <h3 class="text-lg font-medium text-gray-900 mb-2">Delete Appointment</h3>
            <p class="text-sm text-gray-500 mb-6">Are you sure you want to delete this appointment? This action cannot be undone.</p>
            
            <form method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="appointment_id" id="delete_appointment_id">
                
                <div class="flex justify-center space-x-4">
                    <button type="button" onclick="closeModal('deleteModal')"
                            class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
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
</script>
</body>
</html>