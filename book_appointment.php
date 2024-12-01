<?php
include 'includes/db.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch vehicles using prepared statement
$sql_vehicles = "SELECT * FROM vehicles WHERE user_id = ?";
$stmt = $conn->prepare($sql_vehicles);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result_vehicles = $stmt->get_result();

// Fetch services
$sql_services = "SELECT * FROM services";
$result_services = $conn->query($sql_services);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $vehicle_id = $_POST['vehicle_id'];
    $service_id = $_POST['service_id'];
    $appointment_date = $_POST['appointment_date'];
    $notes = $_POST['notes'];

    // Fixed query using user_id instead of customer_id and prepared statement
    $sql = "INSERT INTO appointments (user_id, vehicle_id, service_id, appointment_date, notes, status) 
            VALUES (?, ?, ?, ?, ?, 'pending')";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiiss", $user_id, $vehicle_id, $service_id, $appointment_date, $notes);
    
    if ($stmt->execute()) {
        $appointment_id = $conn->insert_id;
        // Redirect to payment page
        header('Location: payment.php?appointment_id=' . $appointment_id);
        exit();
    } else {
        $error = "Error booking appointment: " . $conn->error;
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
        .bg-dark-gray { background-color: #1F2937; }
        .bg-orange { background-color: #F97316; }
        .text-orange { color: #F97316; }
        .border-orange { border-color: #F97316; }
        .hover-orange:hover { background-color: #EA580C; }
    </style>
</head>
<body class="bg-gray-900 text-gray-100">
    <!-- Navigation -->
    <nav class="bg-dark-gray shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="index.php" class="flex items-center space-x-2">
                        <i class="fas fa-car text-orange text-2xl"></i>
                        <span class="text-xl font-bold text-white">AutoBots</span>
                    </a>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="customer_dashboard.php" class="text-gray-300 hover:text-orange transition">
                        <i class="fas fa-tachometer-alt mr-1"></i> Dashboard
                    </a>
                    <a href="profile.php" class="text-gray-300 hover:text-orange transition">
                        <i class="fas fa-user mr-1"></i> Profile
                    </a>
                    <a href="logout.php" class="text-gray-300 hover:text-orange transition">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
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
                        <label class="block text-gray-300 mb-2">Preferred Date & Time</label>
                        <input type="datetime-local" name="appointment_date" required
                               class="w-full bg-gray-800 border border-gray-600 text-white rounded-lg px-4 py-2.5 focus:border-orange focus:ring-orange transition">
                    </div>
                </div>

                <!-- Service Selection (Hidden) -->
                <input type="hidden" name="service_id" id="selected_service_id" required>

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
        // Service card selection
        const serviceCards = document.querySelectorAll('.service-card');
        const serviceInput = document.getElementById('selected_service_id');

        serviceCards.forEach(card => {
            card.addEventListener('click', function() {
                // Remove active class from all cards
                serviceCards.forEach(c => c.classList.remove('border-orange'));
                // Add active class to selected card
                card.classList.add('border-orange');
                // Update hidden input
                serviceInput.value = card.dataset.serviceId;
            });
        });

        // Form validation
        document.getElementById('bookingForm').addEventListener('submit', function(e) {
            if (!serviceInput.value) {
                e.preventDefault();
                alert('Please select a service');
            }
        });

        // Date validation
        
    });
    </script>
</body>
</html>