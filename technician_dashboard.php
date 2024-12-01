<?php
include 'includes/db.php';
session_start();

function getStatusColor($status) {
    $colorMap = [
        'pending' => 'bg-yellow-100 text-yellow-800',
        'confirmed' => 'bg-blue-100 text-blue-800',
        'in-progress' => 'bg-indigo-100 text-indigo-800',
        'completed' => 'bg-green-100 text-green-800',
        'cancelled' => 'bg-red-100 text-red-800'
    ];
    
    return $colorMap[$status] ?? 'bg-gray-100 text-gray-800';
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'technician') {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Update the main query in technician_dashboard.php (around line 38)
$sql = "SELECT a.*, 
        s.name as service_name, s.price as service_price,
        u.first_name, u.last_name,
        v.make, v.model, v.year
        FROM appointments a
        JOIN services s ON a.service_id = s.service_id
        JOIN users u ON a.user_id = u.user_id
        JOIN vehicles v ON a.vehicle_id = v.vehicle_id
        WHERE a.technician_id = ?
        ORDER BY a.appointment_date ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$appointments = $stmt->get_result();

// Fetch completed appointments count
$sql_completed = "SELECT COUNT(*) AS completed_count FROM appointments WHERE technician_id = $user_id AND status = 'completed'";
$result_completed = $conn->query($sql_completed);
$completed_count = $result_completed->fetch_assoc()['completed_count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Technician Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <nav class="bg-white shadow">
        <div class="container mx-auto px-4">
            <div class="flex justify-between">
                <div class="flex space-x-4">
                    <a href="index.php" class="py-5 px-3 text-gray-700">Home</a>
                    <a href="technician_dashboard.php" class="py-5 px-3 text-gray-700">Dashboard</a>
                </div>
                <div class="flex space-x-4">
                    <a href="profile.php" class="py-5 px-3 text-gray-700">Profile</a>
                    <a href="logout.php" class="py-5 px-3 text-red-600 hover:text-red-700">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>
    <div class="container mx-auto p-4">
    <h1 class="text-2xl font-bold mb-4">Technician Dashboard</h1>
    
    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 bg-blue-100 rounded-full">
                    <i class="fas fa-clipboard-list text-blue-600"></i>
                </div>
                <div class="ml-4">
                    <h3 class="text-gray-500 text-sm">Assigned Tasks</h3>
                    <p class="text-2xl font-semibold"><?php echo $appointments->num_rows; ?></p>
                </div>
            </div>
        </div>
        <!-- Add more stat cards as needed -->
    </div>

    <!-- Appointments Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Service</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vehicle</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php while($appointment = $appointments->fetch_assoc()): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-10 w-10">
                                    <img class="h-10 w-10 rounded-full" 
                                         src="https://ui-avatars.com/api/?name=<?php echo urlencode($appointment['first_name'] . ' ' . $appointment['last_name']); ?>" 
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
                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($appointment['service_name']); ?></div>
                            <div class="text-sm text-gray-500">$<?php echo number_format($appointment['service_price'], 2); ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo htmlspecialchars($appointment['make'] . ' ' . $appointment['model'] . ' (' . $appointment['year'] . ')'); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo date('M d, Y h:i A', strtotime($appointment['appointment_date'])); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                <?php 
                                switch($appointment['status']) {
                                    case 'pending':
                                        echo 'bg-yellow-100 text-yellow-800';
                                        break;
                                    case 'in-progress':
                                        echo 'bg-blue-100 text-blue-800';
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
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <button onclick="updateStatus(<?php echo $appointment['appointment_id']; ?>)"
                                    class="text-blue-600 hover:text-blue-900">
                                <i class="fas fa-edit"></i> Update Status
                            </button>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Status Update Modal -->
<div id="updateStatusModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
    <!-- Modal content -->
</div>

<script>
function getStatusColor(status) {
    switch(status) {
        case 'pending': return 'bg-yellow-100 text-yellow-800';
        case 'in-progress': return 'bg-blue-100 text-blue-800';
        case 'completed': return 'bg-green-100 text-green-800';
        case 'cancelled': return 'bg-red-100 text-red-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}

function updateStatus(appointmentId) {
    const modal = document.getElementById('updateStatusModal');
    document.getElementById('appointment_id').value = appointmentId;
    modal.classList.remove('hidden');
}

function checkUpdates() {
    fetch('check_appointments.php')
        .then(response => response.json())
        .then(data => {
            if (data.updates) {
                location.reload();
            }
        });
}

// Check for updates every 30 seconds
setInterval(checkUpdates, 30000);

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
</body>
</html>