<?php
ini_set('session.gc_maxlifetime', 86400); // 24 hours
ini_set('session.cookie_lifetime', 86400);
ini_set('session.gc_probability', 1);
ini_set('session.gc_divisor', 100);

// Set secure session params
$lifetime = 24 * 60 * 60; // 24 hours
$secure = isset($_SERVER['HTTPS']); 
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
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'technician') {
    header('Location: login.php');
    exit();
}

// Session timeout check
$inactive = 86400; // 24 hours
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $inactive)) {
    session_unset();
    session_destroy();   
    header('Location: login.php');
    exit();
}

// Update last activity time
$_SESSION['last_activity'] = time();

$user_id = $_SESSION['user_id'];

// Fetch technician data
$user_sql = "SELECT * FROM users WHERE user_id = ? AND role = 'technician'";
$stmt = $conn->prepare($user_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();

function getStatusColor($status) {
    $colorMap = [
        'pending' => 'bg-yellow-100 text-yellow-800',
        'confirmed' => 'bg-blue-100 text-blue-800',
        'in-progress' => 'bg-indigo-100 text-indigo-800',
        'completed' => 'bg-green-100 text-green-800',
        'cancelled' => 'bg-red-100 text-red-800'
    ];
    
    return $colorMap[strtolower($status)] ?? 'bg-gray-100 text-gray-800';
}

$user_id = $_SESSION['user_id'];

// Update the main query in technician_dashboard.php (around line 38)
$sql = "SELECT a.*, 
        COALESCE(s.name, sp.name) as service_name,
        COALESCE(s.price, sp.price) as service_price,
        u.first_name, u.last_name,
        v.make, v.model, v.year
        FROM appointments a
        LEFT JOIN services s ON a.service_id = s.service_id
        LEFT JOIN service_packages sp ON a.package_id = sp.package_id
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Technician Dashboard - AutoBots</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        .stat-card:hover { transform: translateY(-5px); }
        .nav-link.active { color: #2563eb; border-bottom: 2px solid #2563eb; }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-gray-50" x-data="{ showLogoutModal: false }">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg sticky top-0 z-50 border-b border-gray-100">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="index.php" class="flex items-center">
                        <i class="fas fa-car text-blue-600 text-2xl transform hover:scale-110 transition-transform"></i>
                        <span class="ml-2 text-xl font-bold bg-gradient-to-r from-blue-600 to-blue-800 bg-clip-text text-transparent">
                            AutoBots Tech
                        </span>
                    </a>
                </div>

                <!-- Profile and Navigation -->
                <div class="flex items-center space-x-6">
                    <a href="technician_dashboard.php" 
                       class="nav-link active px-3 py-2 rounded-md text-sm font-medium inline-flex items-center space-x-2 border-b-2 border-blue-600">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="technician_manage_appointments.php" 
                       class="nav-link px-3 py-2 rounded-md text-sm font-medium inline-flex items-center space-x-2 text-gray-600 hover:text-blue-600 transition-colors">
                        <i class="fas fa-calendar"></i>
                        <span>Appointments</span>
                    </a>
                    
                    <!-- Profile Dropdown -->
                    <div class="relative" x-data="{ profileOpen: false }">
                        <button @click="profileOpen = !profileOpen" 
                                class="flex items-center space-x-3 text-gray-600 hover:text-blue-600 focus:outline-none transition-colors">
                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user_data['first_name'] . ' ' . $user_data['last_name']); ?>&background=2563eb&color=fff" 
                                 class="h-8 w-8 rounded-full ring-2 ring-blue-600 ring-offset-2">
                            <span class="text-sm font-medium"><?php echo htmlspecialchars($user_data['first_name']); ?></span>
                            <svg class="w-4 h-4 transition-transform duration-200" :class="{'rotate-180': profileOpen}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>

                        <!-- Enhanced dropdown menu -->
                        <div x-show="profileOpen"
                             x-cloak
                             @click.away="profileOpen = false"
                             class="absolute right-0 mt-3 w-48 rounded-lg shadow-lg py-1 bg-white ring-1 ring-black ring-opacity-5 z-50 transform origin-top-right transition-all"
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-75"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95">
                            <!-- Profile section -->
                            <div class="px-4 py-3 border-b">
                                <p class="text-sm text-gray-700 font-medium">
                                    <?php echo htmlspecialchars($user_data['first_name'] . ' ' . $user_data['last_name']); ?>
                                </p>
                                <p class="text-xs text-gray-500"><?php echo htmlspecialchars($user_data['email']); ?></p>
                            </div>
                            
                            <!-- Menu items -->
                            <a href="profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                                <i class="fas fa-user-circle mr-2 text-blue-600"></i>My Profile
                            </a>
                            <button @click="showLogoutModal = true; profileOpen = false" 
                                    class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors">
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
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-md p-6 border border-gray-100 hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1">
                <div class="flex items-center">
                    <div class="p-3 bg-blue-100 rounded-xl">
                        <i class="fas fa-clipboard-list text-blue-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-gray-500 text-sm font-medium">Assigned Tasks</h3>
                        <p class="text-3xl font-bold text-gray-900 mt-1"><?php echo $appointments->num_rows; ?></p>
                    </div>
                </div>
                <div class="mt-4 flex items-center text-sm text-gray-600">
                    <i class="fas fa-clock mr-2"></i>
                    <span>Updated just now</span>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-6 transition-transform duration-200 stat-card">
                <div class="flex items-center">
                    <div class="p-3 bg-green-100 rounded-full">
                        <i class="fas fa-check-circle text-green-600"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-gray-500 text-sm">Completed Services</h3>
                        <p class="text-2xl font-semibold"><?php echo $completed_count; ?></p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-6 transition-transform duration-200 stat-card">
                <div class="flex items-center">
                    <div class="p-3 bg-indigo-100 rounded-full">
                        <i class="fas fa-calendar-check text-indigo-600"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-gray-500 text-sm">Today's Appointments</h3>
                        <p class="text-2xl font-semibold">
                            <?php 
                            $today_count = 0;
                            $appointments->data_seek(0);
                            while($row = $appointments->fetch_assoc()) {
                                if(date('Y-m-d', strtotime($row['appointment_date'])) == date('Y-m-d')) {
                                    $today_count++;
                                }
                            }
                            echo $today_count;
                            $appointments->data_seek(0);
                            ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Appointments Table -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden border border-gray-100">
            <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                <h2 class="text-xl font-semibold text-gray-800 flex items-center">
                    <i class="fas fa-calendar-check text-blue-600 mr-2"></i>
                    My Appointments
                </h2>
                <!-- Add filter/search if needed -->
            </div>
            <div class="overflow-x-auto">
                <!-- Your existing table code here, but remove the outer div -->
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
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <img class="h-10 w-10 rounded-full ring-2 ring-gray-200" 
                                                 src="https://ui-avatars.com/api/?name=<?php echo urlencode($appointment['first_name'] . ' ' . $appointment['last_name']); ?>&background=2563eb&color=fff" 
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
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <!-- Changed from text-right to text-left and adjusted button styling -->
                                    <button onclick="updateStatus(<?php echo $appointment['appointment_id']; ?>)"
                                            class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                        <i class="fas fa-edit mr-2"></i>
                                        Update Status
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Logout Modal -->
    <div x-show="showLogoutModal"
         x-cloak
         class="fixed inset-0 z-50 overflow-y-auto"
         aria-labelledby="modal-title" 
         role="dialog" 
         aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" 
                 @click="showLogoutModal = false"></div>

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

    <!-- Add Status Update Modal -->
    <div id="updateStatusModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900">Update Appointment Status</h3>
                <button onclick="closeStatusModal()" class="text-gray-400 hover:text-gray-500">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form id="updateStatusForm" onsubmit="submitStatusUpdate(event)">
                <input type="hidden" id="appointment_id" name="appointment_id">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select name="status" id="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="pending">Pending</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="in-progress">In Progress</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                    <textarea name="notes" id="notes" rows="3" 
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                              placeholder="Add service notes here..."></textarea>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeStatusModal()"
                            class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        Update Status
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize event listeners
        initializeModalHandlers();
    });

    function initializeModalHandlers() {
        const modal = document.getElementById('updateStatusModal');
        const buttons = document.querySelectorAll('[onclick^="updateStatus"]');
        
        console.log('Modal element:', modal);
        console.log('Update buttons found:', buttons.length);
        
        // Add click outside handler
        window.onclick = function(event) {
            if (event.target === modal) {
                closeStatusModal();
            }
        };
        
        // Add escape key handler
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeStatusModal();
            }
        });
    }

    function updateStatus(appointmentId) {
        console.log('Updating status for appointment:', appointmentId);
        
        // Clear previous values
        const statusSelect = document.getElementById('status');
        const notesField = document.getElementById('notes');
        const appointmentIdField = document.getElementById('appointment_id');
        const modal = document.getElementById('updateStatusModal');
        
        if (!statusSelect || !notesField || !appointmentIdField || !modal) {
            console.error('Required elements not found');
            return;
        }
        
        statusSelect.value = 'pending';
        notesField.value = '';
        appointmentIdField.value = appointmentId;
        
        // Show modal
        modal.classList.remove('hidden');
    }

    function closeStatusModal() {
        const modal = document.getElementById('updateStatusModal');
        if (modal) {
            modal.classList.add('hidden');
        }
    }

    function submitStatusUpdate(event) {
        event.preventDefault();
        
        const data = {
            appointment_id: document.getElementById('appointment_id').value,
            status: document.getElementById('status').value,
            notes: document.getElementById('notes').value
        };
        
        console.log('Submitting update:', data);
        
        fetch('update_appointment_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Status updated successfully');
                location.reload();
            } else {
                alert('Error: ' + (data.error || 'Unknown error occurred'));
            }
        })
        .catch(error => {
            console.error('Update error:', error);
            alert('Error: ' + error.message);
        })
        .finally(() => {
            closeStatusModal();
        });
    }

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
        // Clear previous values
        document.getElementById('status').value = 'pending';
        document.getElementById('notes').value = '';
        
        // Set appointment ID and show modal
        document.getElementById('appointment_id').value = appointmentId;
        document.getElementById('updateStatusModal').classList.remove('hidden');
    }

    // Add console logging for debugging
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('updateStatusModal');
        if (!modal) {
            console.error('Status modal not found!');
        }
        
        const buttons = document.querySelectorAll('[onclick^="updateStatus"]');
        console.log('Found update buttons:', buttons.length);
    });

    function closeStatusModal() {
        document.getElementById('updateStatusModal').classList.add('hidden');
    }

    function submitStatusUpdate(event) {
        event.preventDefault();
        
        const data = {
            appointment_id: document.getElementById('appointment_id').value,
            status: document.getElementById('status').value,
            notes: document.getElementById('notes').value
        };
        
        fetch('update_appointment_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Status updated successfully');
                location.reload();
            } else {
                alert('Error: ' + (data.error || 'Unknown error occurred'));
            }
        })
        .catch(error => {
            alert('Error: ' + error.message);
        })
        .finally(() => {
            closeStatusModal();
        });
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('updateStatusModal');
        if (event.target === modal) {
            closeStatusModal();
        }
    }

    // Add escape key listener
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeStatusModal();
        }
    });

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

<!-- Add these styles to your existing style section -->
<style>
.stat-card {
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
}

.nav-link.active {
    position: relative;
}

.nav-link.active:after {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 0;
    right: 0;
    height: 2px;
    background: linear-gradient(to right, #2563eb, #1d4ed8);
}

@keyframes slideIn {
    0% { transform: translateY(-10px); opacity: 0; }
    100% { transform: translateY(0); opacity: 1; }
}

.animate-slide-in {
    animation: slideIn 0.3s ease-out;
}

/* Custom scrollbar */
::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}
</style>