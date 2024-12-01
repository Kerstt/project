<?php
include 'includes/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Vehicles - AutoBots</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .vehicle-card { transition: all 0.3s ease; }
        .vehicle-card:hover { transform: translateY(-5px); }
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }
        .glass-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        .vehicle-image {
            transition: transform 0.3s ease;
        }
        .glass-card:hover .vehicle-image {
            transform: scale(1.05);
        }
        .status-badge {
            transition: all 0.3s ease;
        }
        .glass-card:hover .status-badge {
            transform: translateX(5px);
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Hero Section -->
    <div class="bg-gradient-to-r from-blue-600 to-blue-800 text-white">
        <div class="max-w-7xl mx-auto px-4 py-12">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold mb-2">My Vehicles</h1>
                    <p class="text-blue-100">Manage and track your vehicle fleet</p>
                </div>
                <button onclick="document.getElementById('addVehicleModal').classList.remove('hidden')"
                        class="bg-white text-blue-600 px-6 py-2 rounded-lg hover:bg-blue-50 transition">
                    <i class="fas fa-plus mr-2"></i>Add Vehicle
                </button>
            </div>
            
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-8">
                <div class="bg-white/10 backdrop-blur rounded-lg p-6">
                    <div class="flex items-center">
                        <i class="fas fa-car text-3xl mr-4"></i>
                        <div>
                            <p class="text-sm text-blue-100">Total Vehicles</p>
                            <h3 class="text-2xl font-bold"><?php echo $stats['total_vehicles']; ?></h3>
                        </div>
                    </div>
                </div>
                <div class="bg-white/10 backdrop-blur rounded-lg p-6">
                    <div class="flex items-center">
                        <i class="fas fa-wrench text-3xl mr-4"></i>
                        <div>
                            <p class="text-sm text-blue-100">Total Services</p>
                            <h3 class="text-2xl font-bold"><?php echo $stats['total_services']; ?></h3>
                        </div>
                    </div>
                </div>
                <div class="bg-white/10 backdrop-blur rounded-lg p-6">
                    <a href="book_appointment.php" class="flex items-center text-white hover:text-blue-100">
                        <i class="fas fa-calendar-plus text-3xl mr-4"></i>
                        <div>
                            <p class="text-sm text-blue-100">Quick Action</p>
                            <h3 class="text-lg font-bold">Book Service</h3>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Add this right after the hero section -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="max-w-7xl mx-auto px-4 mt-4">
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                <?php 
                    echo $_SESSION['success_message'];
                    unset($_SESSION['success_message']);
                ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="max-w-7xl mx-auto px-4 mt-4">
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                <?php 
                    echo $_SESSION['error_message'];
                    unset($_SESSION['error_message']);
                ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Vehicles Grid -->
    <div class="max-w-7xl mx-auto px-4 py-12">
        <?php if ($result->num_rows > 0): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php while($vehicle = $result->fetch_assoc()): ?>
                    <div class="glass-card rounded-xl overflow-hidden">
                        <div class="relative h-56 overflow-hidden bg-gradient-to-br from-blue-50 to-gray-50">
                            <img src="https://cdn.imagin.studio/getimage/?customer=img&make=<?php echo urlencode($vehicle['make']); ?>&modelYear=<?php echo $vehicle['year']; ?>&angle=23" 
                                 alt="<?php echo htmlspecialchars($vehicle['make']); ?>"
                                 class="vehicle-image w-full h-full object-cover">
                            <div class="absolute top-4 right-4">
                                <span class="status-badge bg-white/90 backdrop-blur px-4 py-1 rounded-full text-sm font-medium text-gray-700 shadow-sm">
                                    <?php echo htmlspecialchars($vehicle['license_plate']); ?>
                                </span>
                            </div>
                        </div>
                        <div class="p-6">
                            <div class="flex justify-between items-start mb-4">
                                <div>
                                    <h3 class="text-xl font-semibold text-gray-900">
                                        <?php echo htmlspecialchars($vehicle['make'] . ' ' . $vehicle['model']); ?>
                                    </h3>
                                    <p class="text-gray-500 mt-1"><?php echo htmlspecialchars($vehicle['year']); ?></p>
                                </div>
                                <div class="flex space-x-2">
                                    <?php if ($vehicle['service_count'] > 0): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            <?php echo $vehicle['service_count']; ?> Services
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="flex items-center text-sm text-gray-500 mb-6">
                                <i class="fas fa-calendar-alt mr-2"></i>
                                <?php echo $vehicle['last_service'] ? 'Last service: ' . date('M d, Y', strtotime($vehicle['last_service'])) : 'No service history'; ?>
                            </div>

                            <div class="flex items-center space-x-3">
                                <a href="vehicle_details.php?id=<?php echo $vehicle['vehicle_id']; ?>" 
                                   class="flex-1 bg-gradient-to-r from-blue-600 to-blue-700 text-white px-4 py-2 rounded-lg hover:from-blue-700 hover:to-blue-800 transition-all duration-200 text-center">
                                    View Details
                                </a>
                                <button class="p-2 text-gray-500 hover:text-blue-600 transition-colors"
                                        onclick='editVehicle(<?php echo json_encode($vehicle); ?>)'>
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="p-2 text-gray-500 hover:text-red-600 transition-colors"
                                        onclick="deleteVehicle(<?php echo $vehicle['vehicle_id']; ?>)">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Add Vehicle Modal -->
    <div id="addVehicleModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50">
        <div class="min-h-screen flex items-center justify-center p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-xl font-semibold">Add New Vehicle</h3>
                        <button onclick="closeModal('addVehicleModal')"
                                class="text-gray-400 hover:text-gray-500">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <form method="POST" action="manage_vehicles.php" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Make</label>
                            <input type="text" name="make" required 
                                   class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Model</label>
                            <input type="text" name="model" required 
                                   class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Year</label>
                            <input type="number" name="year" required 
                                   min="1900" max="<?php echo date('Y') + 1; ?>" 
                                   class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">License Plate</label>
                            <input type="text" name="license_plate" required 
                                   class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div class="flex justify-end space-x-3 mt-6">
                            <button type="button" onclick="closeModal('addVehicleModal')"
                                    class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                                Cancel
                            </button>
                            <button type="submit"
                                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                Add Vehicle
                            </button>
                        </div>
                    </form>
                </div>
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