<?php
include 'includes/db.php';
session_start();

// Check if user is admin
if ($_SESSION['role'] != 'admin') {
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $description = $_POST['description'];
    $price = $_POST['price'];
    
    $sql = "INSERT INTO services (name, description, price) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssd", $name, $description, $price);
    $stmt->execute();
}

$sql = "SELECT * FROM services";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Services</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto p-4">
        <h1 class="text-2xl font-bold mb-4">Manage Services</h1>
        
        <!-- Add Service Form -->
        <div class="bg-white rounded-lg shadow p-4 mb-4">
            <h2 class="text-xl font-bold mb-4">Add New Service</h2>
            <form method="post" action="">
                <div class="grid grid-cols-1 gap-4">
                    <input type="text" name="name" placeholder="Service Name" class="border p-2 rounded" required>
                    <textarea name="description" placeholder="Description" class="border p-2 rounded" required></textarea>
                    <input type="number" name="price" placeholder="Price" class="border p-2 rounded" required>
                    <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded">Add Service</button>
                </div>
            </form>
        </div>

        <!-- Services List -->
        <div class="bg-white rounded-lg shadow p-4">
            <h2 class="text-xl font-bold mb-4">Current Services</h2>
            <table class="min-w-full">
                <thead>
                    <tr>
                        <th class="px-4 py-2">Name</th>
                        <th class="px-4 py-2">Description</th>
                        <th class="px-4 py-2">Price</th>
                        <th class="px-4 py-2">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($service = $result->fetch_assoc()): ?>
                        <tr>
                            <td class="px-4 py-2"><?php echo $service['name']; ?></td>
                            <td class="px-4 py-2"><?php echo $service['description']; ?></td>
                            <td class="px-4 py-2">$<?php echo $service['price']; ?></td>
                            <td class="px-4 py-2">
                                <button class="bg-yellow-500 text-white px-4 py-2 rounded mr-2">Edit</button>
                                <button class="bg-red-500 text-white px-4 py-2 rounded">Delete</button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>