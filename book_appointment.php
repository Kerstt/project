<?php
include 'includes/db.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

$user_sql = "SELECT * FROM users WHERE user_id = ?";
$stmt = $conn->prepare($user_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();

// Fetch vehicles using prepared statement
$sql_vehicles = "SELECT * FROM vehicles WHERE user_id = ?";
$stmt = $conn->prepare($sql_vehicles);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result_vehicles = $stmt->get_result();

// Fetch services
$sql_services = "SELECT * FROM services";
$result_services = $conn->query($sql_services);

// Fetch service packages
$sql_packages = "SELECT * FROM service_packages WHERE is_active = 1";
$result_packages = $conn->query($sql_packages);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $vehicle_id = $_POST['vehicle_id'];
    $appointment_date = $_POST['appointment_date'];
    $notes = $_POST['notes'];
    
    $conn->begin_transaction();
    try {
        if (isset($_POST['package_id']) && !empty($_POST['package_id'])) {
            // Package booking
            $package_id = $_POST['package_id'];
            
            // Verify package exists and is active
            $stmt = $conn->prepare("SELECT package_id FROM service_packages WHERE package_id = ? AND is_active = 1");
            $stmt->bind_param("i", $package_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                throw new Exception("Selected package is not available");
            }
            
            // Create appointment for package
            $sql = "INSERT INTO appointments (user_id, vehicle_id, package_id, service_id, appointment_date, notes, status) 
                    VALUES (?, ?, ?, NULL, ?, ?, 'pending')";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iiiss", $user_id, $vehicle_id, $package_id, $appointment_date, $notes);
            
        } elseif (isset($_POST['service_id']) && !empty($_POST['service_id'])) {
            // Single service booking
            $service_id = $_POST['service_id'];
            
            // Verify service exists
            $stmt = $conn->prepare("SELECT service_id FROM services WHERE service_id = ?");
            $stmt->bind_param("i", $service_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows === 0) {
                throw new Exception("Selected service is not available");
            }
            
            // Create appointment for service
            $sql = "INSERT INTO appointments (user_id, vehicle_id, service_id, package_id, appointment_date, notes, status) 
                    VALUES (?, ?, ?, NULL, ?, ?, 'pending')";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iiiss", $user_id, $vehicle_id, $service_id, $appointment_date, $notes);
            
        } else {
            throw new Exception("Please select a service or package");
        }
        
        if (!$stmt->execute()) {
            throw new Exception("Database error: " . $stmt->error);
        }
        
        $appointment_id = $conn->insert_id;
        
        // Create notification for admin
        $notify_sql = "INSERT INTO notifications (user_id, type, message, appointment_id) 
                      SELECT user_id, 'new_appointment', 
                      CONCAT('New appointment booking #', ?), ?
                      FROM users WHERE role = 'admin'";
        $notify_stmt = $conn->prepare($notify_sql);
        $notify_stmt->bind_param("ii", $appointment_id, $appointment_id);
        $notify_stmt->execute();
        
        $conn->commit();
        $_SESSION['success_message'] = "Appointment booked successfully!";
        header('Location: customer_dashboard.php');
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Error booking appointment: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Book Appointment - AutoBots</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        [x-cloak] { display: none !important; }
        .bg-dark-gray { background-color: #1F2937; }
        .bg-orange { background-color: #F97316; }
        .text-orange { color: #F97316; }
        .border-orange { border-color: #F97316; }
        .hover-orange:hover { background-color: #EA580C; }
    </style>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-900 text-gray-100">
    <div x-data="{ showLogoutModal: false, isOpen: false }">
        <!-- Navigation -->
        <nav class="bg-gray-800 border-b border-gray-700">
    <div class="max-w-7xl mx-auto px-4">
        <div class="flex items-center justify-between h-16">
            <!-- Logo and Brand -->
            <div class="flex items-center">
                <a href="customer_dashboard.php" class="flex items-center">
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
                           class="text-gray-300 hover:bg-gray-700 hover:text-white px-3 py-2 rounded-md text-sm font-medium">
                            <i class="fas fa-calendar-alt mr-2"></i>Manage Appointments
                        </a>
                    </div>
                </div>
            </div>

            <!-- Right Side Menu -->
            <div class="hidden md:flex items-center">
                <div x-data="{ isOpen: false }" @click.away="isOpen = false" class="relative">
                    <button @click="isOpen = !isOpen" 
                            class="flex items-center space-x-3 text-gray-300 hover:text-white focus:outline-none"
                            type="button">
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user_data['first_name'] . ' ' . $user_data['last_name']); ?>&background=F97316&color=fff" 
                             class="h-8 w-8 rounded-full">
                        <span class="text-sm font-medium"><?php echo htmlspecialchars($user_data['first_name']); ?></span>
                        <svg class="w-4 h-4 ml-1" :class="{'rotate-180': isOpen}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>

                    <div x-show="isOpen"
                         x-cloak
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
                        <button @click="$parent.showLogoutModal = true; isOpen = false" 
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
            <a href="customer_dashboard.php" class="text-gray-300 hover:bg-gray-700 hover:text-white block px-3 py-2 rounded-md text-base font-medium">
                <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
            </a>
            <a href="manage_vehicles.php" class="text-gray-300 hover:bg-gray-700 hover:text-white block px-3 py-2 rounded-md text-base font-medium">
                <i class="fas fa-car mr-2"></i>My Vehicles
            </a>
            <a href="service_history.php" class="text-gray-300 hover:bg-gray-700 hover:text-white block px-3 py-2 rounded-md text-base font-medium">
                <i class="fas fa-history mr-2"></i>Service History
            </a>
        </div>
    </div>
</nav>

    <div class="max-w-7xl mx-auto p-6">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-white mb-2">Book Your Service</h1>
            <p class="text-gray-400">Schedule your vehicle maintenance with our expert technicians</p>
        </div>

        <!-- Service Selection Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <?php while($service = $result_services->fetch_assoc()): ?>
                <div class="bg-dark-gray rounded-lg p-6 shadow-lg border border-gray-700 hover:border-orange transition cursor-pointer service-card" 
                     data-service-id="<?php echo $service['service_id']; ?>">
                    <div class="flex justify-between items-start">
                        <div>
                            <h3 class="text-xl font-semibold text-white mb-2"><?php echo htmlspecialchars($service['name']); ?></h3>
                            <p class="text-gray-400 mb-4"><?php echo htmlspecialchars($service['description']); ?></p>
                            <span class="text-orange text-2xl font-bold">$<?php echo number_format($service['price'], 2); ?></span>
                        </div>
                        <i class="fas fa-wrench text-orange text-2xl"></i>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>

        <!-- Service Packages -->
        <div class="mb-8">
            <h2 class="text-xl font-semibold text-white mb-4">Service Packages</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <?php while($package = $result_packages->fetch_assoc()): ?>
                    <div class="bg-dark-gray rounded-lg p-6 shadow-lg border border-gray-700 hover:border-orange transition cursor-pointer package-card" 
                         data-package-id="<?php echo $package['package_id']; ?>">
                        <div class="flex justify-between items-start">
                            <div>
                                <h3 class="text-xl font-semibold text-white mb-2"><?php echo htmlspecialchars($package['name']); ?></h3>
                                <p class="text-gray-400 mb-4"><?php echo htmlspecialchars($package['description']); ?></p>
                                <div class="mb-3">
                                    <h4 class="text-sm font-medium text-gray-300">Included Services:</h4>
                                    <ul class="list-disc list-inside text-gray-400 text-sm">
                                        <?php foreach(explode(',', $package['included_services']) as $service): ?>
                                            <li><?php echo htmlspecialchars($service); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                                <span class="text-orange text-2xl font-bold">$<?php echo number_format($package['price'], 2); ?></span>
                            </div>
                            <i class="fas fa-box text-orange text-2xl"></i>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>

        <!-- Booking Form -->
        <div class="bg-dark-gray rounded-lg p-8 shadow-lg border border-gray-700">
            <form method="post" action="" class="space-y-6" id="bookingForm">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Vehicle Selection -->
                    <div>
                        <label class="block text-gray-300 mb-2">Select Vehicle</label>
                        <select name="vehicle_id" required
                                class="w-full bg-gray-800 border border-gray-600 text-white rounded-lg px-4 py-2.5 focus:border-orange focus:ring-orange transition">
                            <?php
                            $result_vehicles->data_seek(0);
                            while($vehicle = $result_vehicles->fetch_assoc()): ?>
                                <option value="<?php echo $vehicle['vehicle_id']; ?>">
                                    <?php echo htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model'] . ' (' . $vehicle['year'] . ')'); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <!-- Date Time Selection -->
                    <div>
                        <label class="block text-gray-300 mb-2">Appointment Date & Time</label>
                        <input type="datetime-local" 
                               id="appointment_datetime"
                               name="appointment_date"
                               class="w-full bg-gray-800 border border-gray-600 text-white rounded-lg px-4 py-2.5 focus:border-orange focus:ring-orange transition"
                               required>
                        <?php if(isset($error)): ?>
                            <p class="text-red-500 text-sm mt-1"><?php echo $error; ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Service Selection (Hidden) -->
                <input type="hidden" name="service_id" id="selected_service_id" required>
                <input type="hidden" name="package_id" id="selected_package_id">

                <!-- Notes -->
                <div>
                    <label class="block text-gray-300 mb-2">Additional Notes</label>
                    <textarea name="notes" rows="4"
                            class="w-full bg-gray-800 border border-gray-600 text-white rounded-lg px-4 py-2.5 focus:border-orange focus:ring-orange transition"
                            placeholder="Any special requests or concerns?"></textarea>
                </div>

                <!-- Submit Button -->
                <div class="flex justify-end">
                    <button type="submit" 
                            class="bg-orange hover:bg-orange-600 text-white px-8 py-3 rounded-lg font-semibold transition flex items-center space-x-2">
                        <i class="fas fa-calendar-check"></i>
                        <span>Confirm Booking</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Success/Error Messages -->
    <?php if (isset($error)): ?>
        <div class="fixed bottom-4 right-4 bg-red-500 text-white px-6 py-3 rounded-lg shadow-lg">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const serviceCards = document.querySelectorAll('.service-card');
        const packageCards = document.querySelectorAll('.package-card');
        const serviceInput = document.getElementById('selected_service_id');
        const packageInput = document.getElementById('selected_package_id');

        // Service selection
        serviceCards.forEach(card => {
            card.addEventListener('click', function() {
                // Reset all cards
                serviceCards.forEach(c => c.classList.remove('border-orange'));
                packageCards.forEach(c => c.classList.remove('border-orange'));
                
                // Select this card
                card.classList.add('border-orange');
                
                // Update inputs
                serviceInput.value = card.dataset.serviceId;
                packageInput.value = '';
            });
        });

        // Package selection
        packageCards.forEach(card => {
            card.addEventListener('click', function() {
                // Reset all cards
                serviceCards.forEach(c => c.classList.remove('border-orange'));
                packageCards.forEach(c => c.classList.remove('border-orange'));
                
                // Select this card
                card.classList.add('border-orange');
                
                // Update inputs
                packageInput.value = card.dataset.packageId;
                serviceInput.value = '';
            });
        });

        // Form validation
        document.getElementById('bookingForm').addEventListener('submit', function(e) {
            if (!serviceInput.value && !packageInput.value) {
                e.preventDefault();
                alert('Please select a service or package');
            }
        });
    });
    </script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Get datetime input
        const datetimeInput = document.getElementById('appointment_datetime');
        
        // Set minimum datetime to current time
        const now = new Date();
        now.setMinutes(now.getMinutes() + 1); // Add 1 minute to current time
        const formattedNow = now.toISOString().slice(0, 16);
        datetimeInput.min = formattedNow;
        
        // Form validation
        document.getElementById('bookingForm').addEventListener('submit', function(e) {
            const selectedDate = new Date(datetimeInput.value);
            const currentDate = new Date();
            
            if (selectedDate <= currentDate) {
                e.preventDefault();
                alert('Please select a future date and time');
            }
        });
    });
    </script>
    <div x-show="showLogoutModal"
         x-cloak
         @keydown.escape.window="showLogoutModal = false"
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
                            <h3 class="text-lg font-medium text-gray-900">Confirm Logout</h3>
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