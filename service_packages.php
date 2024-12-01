<?php
include 'includes/db.php';
session_start();

if ($_SESSION['role'] != 'admin') {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $description = $_POST['description'];
    $price = $_POST['price'];
    $duration = $_POST['duration'];
    $services = implode(',', $_POST['services']);
    
    $sql = "INSERT INTO service_packages (name, description, price, duration_minutes, included_services) 
            VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssdis", $name, $description, $price, $duration, $services);
    $stmt->execute();
}

// Fetch all packages
$sql = "SELECT * FROM service_packages ORDER BY created_at DESC";
$packages = $conn->query($sql);

// Fetch available services for package creation
$sql_services = "SELECT * FROM services";
$services = $conn->query($sql_services);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Service Packages - AutoBots</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-2xl font-bold">Service Packages</h1>
            <button onclick="document.getElementById('newPackageModal').classList.remove('hidden')"
                    class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                Create Package
            </button>
        </div>

        <!-- Packages Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <?php while($package = $packages->fetch_assoc()): ?>
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-xl font-semibold mb-2"><?php echo htmlspecialchars($package['name']); ?></h3>
                    <p class="text-gray-600 mb-4"><?php echo htmlspecialchars($package['description']); ?></p>
                    <div class="flex justify-between items-center">
                        <span class="text-2xl font-bold">$<?php echo number_format($package['price'], 2); ?></span>
                        <span class="text-sm text-gray-500"><?php echo $package['duration_minutes']; ?> min</span>
                    </div>
                    <div class="mt-4 pt-4 border-t">
                        <h4 class="font-medium mb-2">Included Services:</h4>
                        <ul class="list-disc list-inside text-sm text-gray-600">
                            <?php foreach(explode(',', $package['included_services']) as $service): ?>
                                <li><?php echo htmlspecialchars($service); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>

        <!-- New Package Modal -->
        <div id="newPackageModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center">
            <div class="bg-white rounded-lg p-6 w-full max-w-md">
                <h2 class="text-xl font-semibold mb-4">Create New Package</h2>
                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Package Name</label>
                        <input type="text" name="name" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Description</label>
                        <textarea name="description" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"></textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Price</label>
                            <input type="number" name="price" step="0.01" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Duration (minutes)</label>
                            <input type="number" name="duration" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Included Services</label>
                        <div class="mt-2 space-y-2">
                            <?php while($service = $services->fetch_assoc()): ?>
                                <label class="inline-flex items-center">
                                    <input type="checkbox" name="services[]" value="<?php echo htmlspecialchars($service['name']); ?>"
                                           class="rounded border-gray-300 text-blue-600">
                                    <span class="ml-2"><?php echo htmlspecialchars($service['name']); ?></span>
                                </label>
                            <?php endwhile; ?>
                        </div>
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="document.getElementById('newPackageModal').classList.add('hidden')"
                                class="px-4 py-2 border rounded-md text-gray-600 hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                            Create Package
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>