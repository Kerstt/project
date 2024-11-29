<?php
include 'includes/db.php';

$sql = "SELECT * FROM services";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Service Catalog</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto p-4">
        <h1 class="text-2xl font-bold mb-4">Our Services</h1>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php while($service = $result->fetch_assoc()): ?>
                <div class="bg-white rounded-lg shadow p-4">
                    <h2 class="text-xl font-bold mb-2"><?php echo $service['name']; ?></h2>
                    <p class="text-gray-600 mb-4"><?php echo $service['description']; ?></p>
                    <p class="text-lg font-bold mb-2">$<?php echo $service['price']; ?></p>
                    <button class="bg-blue-500 text-white px-4 py-2 rounded">Book Now</button>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
</body>
</html>