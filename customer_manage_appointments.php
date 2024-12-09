<?php
// Session configuration
session_start();
include 'includes/db.php';

// Display success/error messages
if (isset($_SESSION['success'])) {
    echo "<script>document.addEventListener('DOMContentLoaded', () => showMessage('" . 
         htmlspecialchars($_SESSION['success']) . "', 'success'));</script>";
    unset($_SESSION['success']);
}

if (isset($_SESSION['error'])) {
    echo "<script>document.addEventListener('DOMContentLoaded', () => showMessage('" . 
         htmlspecialchars($_SESSION['error']) . "', 'error'));</script>";
    unset($_SESSION['error']);
}

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user data
$user_sql = "SELECT * FROM users WHERE user_id = ?";
$stmt = $conn->prepare($user_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();

// Check if user data exists
if (!$user_data) {
    $_SESSION['error'] = "Unable to fetch user data";
    header('Location: logout.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Update SQL query
$sql = "SELECT a.*, 
        s.name as service_name, 
        s.price,
        v.make, v.model, v.year,
        u.first_name as tech_first_name, 
        u.last_name as tech_last_name,
        CASE 
            WHEN a.appointment_date < NOW() AND a.status = 'pending' THEN 'expired'
            ELSE a.status 
        END as display_status
        FROM appointments a
        LEFT JOIN services s ON a.service_id = s.service_id 
        JOIN vehicles v ON a.vehicle_id = v.vehicle_id
        LEFT JOIN users u ON a.technician_id = u.user_id
        WHERE a.user_id = ? 
        ORDER BY 
            CASE a.status 
                WHEN 'pending' THEN 1
                WHEN 'confirmed' THEN 2
                WHEN 'in-progress' THEN 3
                WHEN 'completed' THEN 4
                WHEN 'cancelled' THEN 5
            END,
            a.appointment_date DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$appointments = $stmt->get_result();

// Add this function in PHP section
function getStatusColor($status) {
    $colors = [
        'pending' => 'bg-yellow-100 text-yellow-800',
        'confirmed' => 'bg-blue-100 text-blue-800',
        'in-progress' => 'bg-indigo-100 text-indigo-800',
        'completed' => 'bg-green-100 text-green-800',
        'cancelled' => 'bg-red-100 text-red-800',
        'expired' => 'bg-gray-100 text-gray-800'
    ];
    return $colors[$status] ?? 'bg-gray-100 text-gray-800';
}
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
        @keyframes slideIn {
            from { transform: translateY(-100%); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .notification {
            animation: slideIn 0.3s ease-out;
        }
        
        .modal-overlay {
            backdrop-filter: blur(4px);
        }

        /* Add these to your existing styles */
        .table-fixed { table-layout: fixed; }
        .table-auto { table-layout: auto; }
        
        /* Custom scrollbar for webkit browsers */
        .overflow-x-auto::-webkit-scrollbar {
            height: 8px;
        }
        
        .overflow-x-auto::-webkit-scrollbar-track {
            background: #374151;
            border-radius: 4px;
        }
        
        .overflow-x-auto::-webkit-scrollbar-thumb {
            background: #4B5563;
            border-radius: 4px;
        }
        
        .overflow-x-auto::-webkit-scrollbar-thumb:hover {
            background: #6B7280;
        }
    </style>
</head>
<!-- Add this after opening <body> tag -->
<body class="bg-gray-900 text-gray-100">
    <div x-data="{ showLogoutModal: false, isOpen: false }">
        <!-- Navigation -->
        <nav class="bg-gray-800 border-b border-gray-700">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <!-- Logo and Brand -->
                <div class="flex items-center">
                    <a href="index.php" class="flex items-center">
                        <i class="fas fa-car text-orange-500 text-2xl"></i>
                        <span class="ml-2 text-xl font-bold text-white">AutoBots</span>
                    </a>
                    
                    <!-- Main Navigation -->
                    <div class="hidden md:block ml-10">
                        <div class="flex items-center space-x-4">
                            <a href="customer_dashboard.php" 
                               class="text-gray-300 hover:bg-gray-700 hover:text-white px-3 py-2 rounded-md text-sm font-medium">
                                <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
                            </a>
                            <a href="book_appointment.php" 
                               class="text-gray-300 hover:bg-gray-700 hover:text-white px-3 py-2 rounded-md text-sm font-medium">
                                <i class="fas fa-calendar-plus mr-2"></i>Book Service
                            </a>
                            <a href="manage_vehicles.php" 
                               class="text-gray-300 hover:bg-gray-700 hover:text-white px-3 py-2 rounded-md text-sm font-medium">
                                <i class="fas fa-car mr-2"></i>My Vehicles
                            </a>
                            <a href="service_history.php" 
                               class="text-gray-300 hover:bg-gray-700 hover:text-white px-3 py-2 rounded-md text-sm font-medium">
                                <i class="fas fa-history mr-2"></i>Service History
                            </a>
                            <a href="customer_manage_appointments.php" 
                               class="text-gray-300 hover:bg-gray-700 hover:text-white px-3 py-2 rounded-md text-sm font-medium"
                               x-data
                               @click.prevent="window.location.href='customer_manage_appointments.php'">
                                <i class="fas fa-calendar-alt mr-2"></i>Manage Appointments
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Right Side Menu -->
                <div class="hidden md:flex items-center" x-data="{ profileOpen: false }">
                    <div class="relative">
                        <button @click="profileOpen = !profileOpen" 
                                class="flex items-center space-x-3 text-gray-300 hover:text-white focus:outline-none"
                                type="button">
                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user_data['first_name'] . ' ' . $user_data['last_name']); ?>&background=F97316&color=fff" 
                                 class="h-8 w-8 rounded-full">
                            <span class="text-sm font-medium"><?php echo htmlspecialchars($user_data['first_name']); ?></span>
                            <svg class="w-4 h-4 ml-1" :class="{'rotate-180': profileOpen}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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

                <!-- Mobile menu button -->
                <div class="md:hidden flex items-center">
                    <button type="button" 
                            class="mobile-menu-button inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-white hover:bg-gray-700 focus:outline-none"
                            aria-controls="mobile-menu" 
                            aria-expanded="false">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile menu -->
        <div class="md:hidden hidden" id="mobile-menu">
            <div class="px-2 pt-2 pb-3 space-y-1">
                <a href="customer_dashboard.php" class="text-white block px-3 py-2 rounded-md text-base font-medium">
                    <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
                </a>
                <a href="book_appointment.php" class="text-gray-300 hover:bg-gray-700 hover:text-white block px-3 py-2 rounded-md text-base font-medium">
                    <i class="fas fa-calendar-plus mr-2"></i>Book Service
                </a>
                <a href="manage_vehicles.php" class="text-gray-300 hover:bg-gray-700 hover:text-white block px-3 py-2 rounded-md text-base font-medium">
                    <i class="fas fa-car mr-2"></i>My Vehicles
                </a>
                <a href="service_history.php" class="text-gray-300 hover:bg-gray-700 hover:text-white block px-3 py-2 rounded-md text-base font-medium">
                    <i class="fas fa-history mr-2"></i>Service History
                </a>
                <a href="customer_manage_appointments.php" class="text-gray-300 hover:bg-gray-700 hover:text-white block px-3 py-2 rounded-md text-base font-medium">
                    <i class="fas fa-calendar-alt mr-2"></i>Manage Appointments
                </a>
                <div class="border-t border-gray-700 pt-4 pb-3">
                    <div class="flex items-center px-5">
                        <div class="flex-shrink-0">
                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user_data['first_name'] . ' ' . $user_data['last_name']); ?>&background=orange&color=fff" 
                                 class="h-10 w-10 rounded-full">
                        </div>
                        <div class="ml-3">
                            <div class="text-base font-medium text-white"><?php echo htmlspecialchars($user_data['first_name'] . ' ' . $user_data['last_name']); ?></div>
                            <div class="text-sm font-medium text-gray-400"><?php echo htmlspecialchars($user_data['email']); ?></div>
                        </div>
                    </div>
                    <div class="mt-3 px-2 space-y-1">
                        <a href="profile.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-400 hover:text-white hover:bg-gray-700">
                            <i class="fas fa-user mr-2"></i>Profile
                        </a>
                        <button @click="showLogoutModal = true; isOpen = false" 
                                class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-gray-100">
                            <i class="fas fa-sign-out-alt mr-2"></i>Logout
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </nav>

        <!-- Logout Modal -->
        <div x-show="showLogoutModal" 
             class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center"
             x-cloak>
            <div class="bg-gray-800 rounded-lg p-6 max-w-sm mx-4">
                <h3 class="text-xl font-bold mb-4">Confirm Logout</h3>
                <p class="text-gray-300 mb-4">Are you sure you want to logout?</p>
                <div class="flex justify-end space-x-3">
                    <button @click="showLogoutModal = false" 
                            class="px-4 py-2 bg-gray-700 text-gray-300 rounded-md hover:bg-gray-600">
                        Cancel
                    </button>
                    <a href="logout.php" 
                       class="px-4 py-2 bg-red-500 text-white rounded-md hover:bg-red-600">
                        Logout
                    </a>
                </div>
            </div>
        </div>
        
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="bg-gray-800 rounded-lg shadow-lg p-6">
                <div class="flex justify-between items-center mb-6">
                    <h1 class="text-2xl font-bold">Manage Your Appointments</h1>
                    <a href="book_appointment.php" class="bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 rounded-md">
                        <i class="fas fa-plus mr-2"></i>New Appointment
                    </a>
                </div>

                <?php if ($appointments->num_rows > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-700">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">
                                        Service
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">
                                        Date & Time
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">
                                        Vehicle
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">
                                        Technician
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">
                                        Price
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-300 uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-700">
                                <?php while($appointment = $appointments->fetch_assoc()): ?>
                                    <tr class="hover:bg-gray-700 transition-colors">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-white">
                                                <?php echo htmlspecialchars($appointment['service_name']); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-300">
                                                <i class="fas fa-calendar mr-2"></i>
                                                <?php echo date('F j, Y g:i A', strtotime($appointment['appointment_date'])); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-300">
                                                <i class="fas fa-car mr-2"></i>
                                                <?php echo htmlspecialchars($appointment['make'] . ' ' . $appointment['model'] . ' (' . $appointment['year'] . ')'); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-300">
                                                <?php if ($appointment['tech_first_name']): ?>
                                                    <i class="fas fa-user-gear mr-2"></i>
                                                    <?php echo htmlspecialchars($appointment['tech_first_name'] . ' ' . $appointment['tech_last_name']); ?>
                                                <?php else: ?>
                                                    <span class="text-gray-500">Not assigned</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                <?php echo getStatusColor($appointment['display_status']); ?>">
                                                <?php echo ucfirst($appointment['display_status']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-white">
                                                $<?php echo number_format($appointment['price'], 2); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <?php if (in_array($appointment['status'], ['pending', 'confirmed']) && strtotime($appointment['appointment_date']) > time()): ?>
                                                <button onclick="openRescheduleModal(<?php echo $appointment['appointment_id']; ?>, '<?php echo $appointment['appointment_date']; ?>')"
                                                        class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded-md text-sm mr-2">
                                                    <i class="fas fa-clock mr-1"></i> Reschedule
                                                </button>
                                                <button onclick="confirmCancel(<?php echo $appointment['appointment_id']; ?>, '<?php echo $appointment['status']; ?>')"
                                                        class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded-md text-sm">
                                                    <i class="fas fa-times mr-1"></i> Cancel
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-12">
                        <i class="fas fa-calendar-alt text-5xl text-gray-600 mb-4"></i>
                        <p class="text-xl text-gray-400">No appointments found</p>
                        <a href="book_appointment.php" class="mt-4 inline-block text-orange-500 hover:text-orange-400">
                            Schedule your first appointment →
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Reschedule Modal -->
        <div id="rescheduleModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="bg-gray-800 rounded-lg shadow-xl p-6 w-full max-w-md">
                    <h3 class="text-xl font-bold mb-4">Reschedule Appointment</h3>
                    <form id="rescheduleForm" action="update_appointment.php" method="POST">
                        <input type="hidden" name="action" value="reschedule">
                        <input type="hidden" name="appointment_id" id="reschedule_appointment_id">
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium mb-2">New Appointment Date & Time</label>
                            <input type="datetime-local" 
                                   name="new_date"
                                   class="w-full bg-gray-700 border border-gray-600 rounded-md px-3 py-2 text-white"
                                   required>
                        </div>
                        
                        <div class="flex justify-end space-x-3">
                            <button type="button" 
                                    onclick="closeModal('rescheduleModal')"
                                    class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-500">
                                Cancel
                            </button>
                            <button type="submit"
                                    class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600">
                                Confirm
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
        // Add at the top of the script section
        let successTimeout;
        let errorTimeout;

        function showMessage(message, type = 'success') {
            clearTimeout(successTimeout);
            clearTimeout(errorTimeout);
            
            const alertDiv = document.createElement('div');
            alertDiv.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg ${
                type === 'success' ? 'bg-green-500' : 'bg-red-500'
            } text-white z-50 transition-opacity duration-500`;
            alertDiv.textContent = message;
            document.body.appendChild(alertDiv);
            
            const timeout = setTimeout(() => {
                alertDiv.style.opacity = '0';
                setTimeout(() => alertDiv.remove(), 500);
            }, 3000);
            
            if (type === 'success') {
                successTimeout = timeout;
            } else {
                errorTimeout = timeout;
            }
        }

        // Update JavaScript for handling modals
        function openRescheduleModal(appointmentId, currentDate) {
            const modal = document.getElementById('rescheduleModal');
            const dateInput = modal.querySelector('input[type="datetime-local"]');
            document.getElementById('reschedule_appointment_id').value = appointmentId;
            
            // Set minimum date to tomorrow
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            tomorrow.setMinutes(tomorrow.getMinutes() - tomorrow.getTimezoneOffset());
            dateInput.min = tomorrow.toISOString().slice(0, 16);
            
            // Set current appointment date
            const currentDateTime = new Date(currentDate);
            currentDateTime.setMinutes(currentDateTime.getMinutes() - currentDateTime.getTimezoneOffset());
            dateInput.value = currentDateTime.toISOString().slice(0, 16);
            
            modal.classList.remove('hidden');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
        }

        function confirmCancel(appointmentId, status) {
            const message = status === 'confirmed' 
                ? 'This appointment is already confirmed. Canceling may incur a fee. Do you still want to proceed?'
                : 'Are you sure you want to cancel this appointment? This action cannot be undone.';
                
            if (confirm(message)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'update_appointment.php';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'cancel';
                
                const appointmentInput = document.createElement('input');
                appointmentInput.type = 'hidden';
                appointmentInput.name = 'appointment_id';
                appointmentInput.value = appointmentId;
                
                form.appendChild(actionInput);
                form.appendChild(appointmentInput);
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Add error handling for form submission
        document.getElementById('rescheduleForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const newDate = new Date(this.elements.new_date.value);
            const now = new Date();
            
            if (newDate <= now) {
                showMessage('Please select a future date and time', 'error');
                return;
            }
            
            const thirtyDaysFromNow = new Date();
            thirtyDaysFromNow.setDate(thirtyDaysFromNow.getDate() + 30);
            
            if (newDate > thirtyDaysFromNow) {
                showMessage('Appointments can only be scheduled within the next 30 days', 'error');
                return;
            }
            
            this.submit();
        });

        // Status color helper function
        function getStatusColor(status) {
            const colors = {
                'pending': 'bg-yellow-100 text-yellow-800',
                'confirmed': 'bg-blue-100 text-blue-800',
                'in-progress': 'bg-indigo-100 text-indigo-800',
                'completed': 'bg-green-100 text-green-800',
                'cancelled': 'bg-red-100 text-red-800',
                'expired': 'bg-gray-100 text-gray-800'
            };
            return colors[status] || 'bg-gray-100 text-gray-800';
        }
        </script>
    </div>
</body>
</html>