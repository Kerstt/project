<?php
include 'includes/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
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

// Fetch vehicles
$vehicles_sql = "SELECT * FROM vehicles WHERE user_id = ?";
$stmt = $conn->prepare($vehicles_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$vehicles = $stmt->get_result();

// Update the stats section to use proper error handling
$stats = [
    'total_vehicles' => 0,
    'total_services' => 0
];

$stats_sql = "SELECT 
    COUNT(*) as total_vehicles,
    SUM(CASE WHEN EXISTS (
        SELECT 1 FROM appointments WHERE vehicle_id = v.vehicle_id
    ) THEN 1 ELSE 0 END) as total_services
    FROM vehicles v 
    WHERE user_id = ?";

$stmt = $conn->prepare($stats_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stats_result = $stmt->get_result()->fetch_assoc();

if ($stats_result) {
    $stats = $stats_result;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'edit':
                $vehicle_id = $_POST['vehicle_id'];
                $make = $_POST['make'];
                $model = $_POST['model'];
                $year = $_POST['year'];
                $license_plate = $_POST['license_plate'];
                
                $sql = "UPDATE vehicles 
                        SET make = ?, model = ?, year = ?, license_plate = ? 
                        WHERE vehicle_id = ? AND user_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssissi", $make, $model, $year, $license_plate, $vehicle_id, $user_id);
                
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = "Vehicle updated successfully";
                } else {
                    $_SESSION['error_message'] = "Error updating vehicle";
                }
                break;
                
            case 'delete':
                $vehicle_id = $_POST['vehicle_id'];
                
                // Check if vehicle has appointments
                $check_sql = "SELECT COUNT(*) as count FROM appointments WHERE vehicle_id = ?";
                $stmt = $conn->prepare($check_sql);
                $stmt->bind_param("i", $vehicle_id);
                $stmt->execute();
                $result = $stmt->get_result()->fetch_assoc();
                
                if ($result['count'] > 0) {
                    $_SESSION['error_message'] = "Cannot delete vehicle as it has associated appointments";
                } else {
                    $sql = "DELETE FROM vehicles WHERE vehicle_id = ? AND user_id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ii", $vehicle_id, $user_id);
                    
                    if ($stmt->execute()) {
                        $_SESSION['success_message'] = "Vehicle deleted successfully";
                    } else {
                        $_SESSION['error_message'] = "Error deleting vehicle";
                    }
                }
                break;
                
            default:
                // Handle adding new vehicle (existing code)
                $make = $_POST['make'];
                $model = $_POST['model'];
                $year = $_POST['year'];
                $license_plate = $_POST['license_plate'];
                
                $sql = "INSERT INTO vehicles (user_id, make, model, year, license_plate) 
                        VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("issis", $user_id, $make, $model, $year, $license_plate);
                
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = "Vehicle added successfully";
                } else {
                    $_SESSION['error_message'] = "Error adding vehicle";
                }
        }
    } else {
        // This handles adding new vehicle
        $make = $_POST['make'];
        $model = $_POST['model'];
        $year = $_POST['year'];
        $license_plate = $_POST['license_plate'];
        
        $sql = "INSERT INTO vehicles (user_id, make, model, year, license_plate) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issis", $user_id, $make, $model, $year, $license_plate);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Vehicle added successfully";
        } else {
            $_SESSION['error_message'] = "Error adding vehicle";
        }
        
        header('Location: manage_vehicles.php');
        exit();
    }
}

// Fetch vehicles with stats
$sql = "SELECT v.*, 
        (SELECT COUNT(*) FROM appointments WHERE vehicle_id = v.vehicle_id) as service_count,
        (SELECT MAX(appointment_date) FROM appointments WHERE vehicle_id = v.vehicle_id) as last_service
        FROM vehicles v 
        WHERE v.user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

// Get vehicle statistics
$stats_sql = "SELECT 
    COUNT(*) as total_vehicles,
    SUM((SELECT COUNT(*) FROM appointments WHERE vehicle_id = v.vehicle_id)) as total_services
    FROM vehicles v WHERE user_id = ?";
$stmt = $conn->prepare($stats_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Vehicles - AutoBots</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .vehicle-card:hover { transform: translateY(-5px); }
        .animate-fade { animation: fadeIn 0.5s ease-out; }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .modal-transition { transition: all 0.3s ease-out; }
        [x-cloak] { display: none !important; }
    </style>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-900 text-gray-100">
    <!-- Replace the existing navigation in manage_vehicles.php -->
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
                               class="text-white px-3 py-2 rounded-md text-sm font-medium bg-gray-700">
                                <i class="fas fa-car mr-2"></i>My Vehicles
                            </a>
                            <a href="service_history.php" 
                               class="text-gray-300 hover:bg-gray-700 hover:text-white px-3 py-2 rounded-md text-sm font-medium">
                                <i class="fas fa-history mr-2"></i>Service History
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Right Side Menu -->
                <div class="hidden md:flex items-center">
                    <div x-data="{ profileOpen: false }" @click.away="profileOpen = false" class="relative">
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
                            <button @click="$root.showLogoutModal = true; profileOpen = false" 
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
                <a href="book_appointment.php" class="text-gray-300 hover:bg-gray-700 hover:text-white block px-3 py-2 rounded-md text-base font-medium">
                    <i class="fas fa-calendar-plus mr-2"></i>Book Service
                </a>
                <a href="manage_vehicles.php" class="bg-gray-700 text-white block px-3 py-2 rounded-md text-base font-medium">
                    <i class="fas fa-car mr-2"></i>My Vehicles
                </a>
                <a href="service_history.php" class="text-gray-300 hover:bg-gray-700 hover:text-white block px-3 py-2 rounded-md text-base font-medium">
                    <i class="fas fa-history mr-2"></i>Service History
                </a>
            </div>
        </div>
    </nav>

    <!-- Add mobile menu script -->
    <script>
    // Toggle mobile menu
    document.querySelector('.mobile-menu-button').addEventListener('click', function() {
        document.getElementById('mobile-menu').classList.toggle('hidden');
    });
    </script>

    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Stats Section -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-gray-800 rounded-lg p-6 shadow-lg transition-transform duration-200 hover:transform hover:scale-105">
                <div class="flex items-center">
                    <div class="p-3 bg-orange-500/10 rounded-full">
                        <i class="fas fa-car text-orange-500 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-gray-400">Total Vehicles</p>
                        <h3 class="text-2xl font-bold"><?php echo $vehicles ? $vehicles->num_rows : 0; ?></h3>
                    </div>
                </div>
            </div>
            <!-- Add more stat cards as needed -->
        </div>

        <!-- Vehicles Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php while($vehicle = $result->fetch_assoc()): ?>
                <div class="bg-gray-800 rounded-lg p-6 shadow-lg vehicle-card transition-all duration-300 animate-fade">
                    <div class="relative h-40 mb-4 rounded-lg overflow-hidden bg-gray-700">
                        <img src="https://cdn.imagin.studio/getimage/?customer=img&make=<?php echo urlencode($vehicle['make']); ?>&modelYear=<?php echo $vehicle['year']; ?>" 
                             class="w-full h-full object-cover" alt="Vehicle Image">
                        <div class="absolute top-3 right-3">
                            <span class="bg-gray-900/70 text-white px-3 py-1 rounded-full text-sm backdrop-blur-sm">
                                <?php echo htmlspecialchars($vehicle['license_plate']); ?>
                            </span>
                        </div>
                    </div>
                    <div class="flex justify-between items-start">
                        <div>
                            <h3 class="text-xl font-semibold">
                                <?php echo htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model']); ?>
                            </h3>
                            <p class="text-gray-400"><?php echo htmlspecialchars($vehicle['year']); ?></p>
                        </div>
                        <div class="flex space-x-2">
                            <button onclick="editVehicle(<?php echo htmlspecialchars(json_encode($vehicle)); ?>)"
                                    class="text-blue-500 hover:text-blue-400 transition-colors">
                                <i class="fas fa-edit text-lg"></i>
                            </button>
                            <button onclick="deleteVehicle(<?php echo $vehicle['vehicle_id']; ?>)"
                                    class="text-red-500 hover:text-red-400 transition-colors">
                                <i class="fas fa-trash text-lg"></i>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>

    <!-- Add Vehicle Modal -->
    <div id="addVehicleModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50">
        <div class="min-h-screen flex items-center justify-center p-4">
            <div class="bg-gray-800 rounded-lg shadow-xl max-w-md w-full p-6 modal-transition">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-semibold">Add New Vehicle</h3>
                    <button onclick="closeModal('addVehicleModal')" class="text-gray-400 hover:text-gray-300">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="add">
                    
                    <div>
                        <label class="block text-gray-300 mb-2">Make</label>
                        <input type="text" name="make" required 
                               class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white">
                    </div>
                    
                    <div>
                        <label class="block text-gray-300 mb-2">Model</label>
                        <input type="text" name="model" required 
                               class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white">
                    </div>
                    
                    <div>
                        <label class="block text-gray-300 mb-2">Year</label>
                        <input type="number" name="year" required min="1900" max="<?php echo date('Y') + 1; ?>"
                               class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white">
                    </div>
                    
                    <div>
                        <label class="block text-gray-300 mb-2">License Plate</label>
                        <input type="text" name="license_plate" required 
                               class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white">
                    </div>
                    
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" onclick="closeModal('addVehicleModal')"
                                class="px-4 py-2 bg-gray-700 text-gray-300 rounded-lg hover:bg-gray-600">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600">
                            Add Vehicle
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Vehicle Modal -->
    <div id="editVehicleModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50">
        <div class="min-h-screen flex items-center justify-center p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-xl font-semibold">Edit Vehicle</h3>
                        <button onclick="closeModal('editVehicleModal')"
                                class="text-gray-400 hover:text-gray-500">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <form method="POST" id="editVehicleForm" class="space-y-4">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="vehicle_id" id="edit_vehicle_id">
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Make</label>
                            <input type="text" name="make" id="edit_make" required 
                                   class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Model</label>
                            <input type="text" name="model" id="edit_model" required 
                                   class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Year</label>
                            <input type="number" name="year" id="edit_year" required 
                                   min="1900" max="<?php echo date('Y') + 1; ?>"
                                   class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">License Plate</label>
                            <input type="text" name="license_plate" id="edit_license_plate" required 
                                   class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm">
                        </div>
                        <div class="flex justify-end space-x-3 mt-6">
                            <button type="button" onclick="closeModal('editVehicleModal')"
                                    class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                                Cancel
                            </button>
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteVehicleModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50">
        <div class="min-h-screen flex items-center justify-center p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
                <div class="p-6">
                    <div class="text-center">
                        <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
                            <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Delete Vehicle</h3>
                        <p class="text-sm text-gray-500 mb-6">Are you sure you want to delete this vehicle? This action cannot be undone.</p>
                        
                        <form method="POST" id="deleteVehicleForm">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="vehicle_id" id="delete_vehicle_id">
                            
                            <div class="flex justify-center space-x-4">
                                <button type="button" onclick="closeModal('deleteVehicleModal')"
                                        class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                                    Cancel
                                </button>
                                <button type="submit"
                                        class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                                    Delete Vehicle
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function deleteVehicle(id) {
            if (confirm('Are you sure you want to delete this vehicle?')) {
                window.location.href = 'delete_vehicle.php?id=' + id;
            }
        }

        function editVehicle(vehicle) {
            // Populate the edit form
            document.getElementById('edit_vehicle_id').value = vehicle.vehicle_id;
            document.getElementById('edit_make').value = vehicle.make;
            document.getElementById('edit_model').value = vehicle.model;
            document.getElementById('edit_year').value = vehicle.year;
            document.getElementById('edit_license_plate').value = vehicle.license_plate;
            
            // Show the modal
            document.getElementById('editVehicleModal').classList.remove('hidden');
        }

        function deleteVehicle(vehicleId) {
            document.getElementById('delete_vehicle_id').value = vehicleId;
            document.getElementById('deleteVehicleModal').classList.remove('hidden');
        }

        // Update the modal functions
        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
        }

        // Update window click handler
        window.onclick = function(event) {
            const modals = ['addVehicleModal', 'editVehicleModal', 'deleteVehicleModal'];
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