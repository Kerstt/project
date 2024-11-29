<?php
include 'includes/db.php';
session_start();

$vehicle_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

$sql = "SELECT * FROM vehicles WHERE vehicle_id = ? AND user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $vehicle_id, $user_id);
$stmt->execute();
$vehicle = $stmt->get_result()->fetch_assoc();

if (!$vehicle) {
    header('Location: manage_vehicles.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Vehicle Details</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto p-4">
        <h1 class="text-2xl font-bold mb-4">Vehicle Details</h1>
        <div class="bg-white rounded-lg shadow p-4">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <h2 class="text-xl font-bold mb-2">Vehicle Information</h2>
                    <p><strong>Make:</strong> <?php echo $vehicle['make']; ?></p>
                    <p><strong>Model:</strong> <?php echo $vehicle['model']; ?></p>
                    <p><strong>Year:</strong> <?php echo $vehicle['year']; ?></p>
                    <p><strong>License Plate:</strong> <?php echo $vehicle['license_plate']; ?></p>
                </div>
                <div>
                    <h2 class="text-xl font-bold mb-2">Service History</h2>
                    <!-- Add service history here -->
                </div>
            </div>
            <div class="mt-4">
                <a href="manage_vehicles.php" class="bg-gray-500 text-white px-4 py-2 rounded mr-2">Back</a>
                <button class="bg-blue-500 text-white px-4 py-2 rounded mr-2">Edit</button>
                <button class="bg-red-500 text-white px-4 py-2 rounded">Delete</button>
            </div>
        </div>
    </div>
</body>
</html>