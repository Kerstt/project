<?php
include 'includes/db.php';
session_start();

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $make = $_POST['make'];
    $model = $_POST['model'];
    $year = $_POST['year'];
    $license_plate = $_POST['license_plate'];
    
    $sql = "INSERT INTO vehicles (user_id, make, model, year, license_plate) 
            VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issis", $user_id, $make, $model, $year, $license_plate);
    $stmt->execute();
}

$sql = "SELECT * FROM vehicles WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Vehicles</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto p-4">
        <h1 class="text-2xl font-bold mb-4">Manage Vehicles</h1>
        
        <!-- Add Vehicle Form -->
        <div class="bg-white rounded-lg shadow p-4 mb-4">
            <h2 class="text-xl font-bold mb-4">Add New Vehicle</h2>
            <form method="post" action="">
                <div class="grid grid-cols-2 gap-4">
                    <input type="text" name="make" placeholder="Make" class="border p-2 rounded" required>
                    <input type="text" name="model" placeholder="Model" class="border p-2 rounded" required>
                    <input type="number" name="year" placeholder="Year" class="border p-2 rounded" required>
                    <input type="text" name="license_plate" placeholder="License Plate" class="border p-2 rounded" required>
                    <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded col-span-2">Add Vehicle</button>
                </div>
            </form>
        </div>

        <!-- Vehicles List -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php while($vehicle = $result->fetch_assoc()): ?>
                <div class="bg-white rounded-lg shadow p-4">
                    <h3 class="text-lg font-bold mb-2"><?php echo $vehicle['make'] . ' ' . $vehicle['model']; ?></h3>
                    <p>Year: <?php echo $vehicle['year']; ?></p>
                    <p>License Plate: <?php echo $vehicle['license_plate']; ?></p>
                    <div class="mt-4">
                        <a href="vehicle_details.php?id=<?php echo $vehicle['vehicle_id']; ?>" 
                           class="bg-blue-500 text-white px-4 py-2 rounded mr-2">Details</a>
                        <button class="bg-red-500 text-white px-4 py-2 rounded">Delete</button>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
</body>
</html>