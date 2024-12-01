<?php
include 'includes/db.php';
session_start();

if ($_SESSION['role'] != 'admin') {
    header('Location: login.php');
    exit();
}

// Handle inventory actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $sql = "INSERT INTO inventory (name, description, quantity, unit_price, reorder_level, category) 
                        VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssidis", $_POST['name'], $_POST['description'], $_POST['quantity'], 
                                $_POST['unit_price'], $_POST['reorder_level'], $_POST['category']);
                $stmt->execute();
                break;
            
            case 'update':
                $sql = "UPDATE inventory SET quantity = quantity + ? WHERE item_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ii", $_POST['quantity'], $_POST['item_id']);
                $stmt->execute();
                break;
        }
    }
}

// Fetch inventory items
$sql = "SELECT * FROM inventory ORDER BY category, name";
$result = $conn->query($sql);

// Get low stock alerts
$alerts_sql = "SELECT * FROM inventory WHERE quantity <= reorder_level";
$alerts_result = $conn->query($alerts_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Inventory Management - AutoBots</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Alerts Section -->
        <?php if ($alerts_result->num_rows > 0): ?>
            <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-8">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-red-500"></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-red-800 font-medium">Low Stock Alerts</h3>
                        <div class="mt-2 text-red-700">
                            <?php while($alert = $alerts_result->fetch_assoc()): ?>
                                <p><?php echo htmlspecialchars($alert['name']); ?> - Only <?php echo $alert['quantity']; ?> remaining</p>
                            <?php endwhile; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Inventory Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <?php while($item = $result->fetch_assoc()): ?>
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex justify-between items-start">
                        <div>
                            <h3 class="font-semibold text-lg"><?php echo htmlspecialchars($item['name']); ?></h3>
                            <p class="text-gray-500"><?php echo htmlspecialchars($item['category']); ?></p>
                        </div>
                        <span class="px-2 py-1 text-sm rounded-full <?php echo $item['quantity'] <= $item['reorder_level'] ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'; ?>">
                            Stock: <?php echo $item['quantity']; ?>
                        </span>
                    </div>
                    <p class="mt-2 text-gray-600"><?php echo htmlspecialchars($item['description']); ?></p>
                    <div class="mt-4">
                        <form method="POST" class="flex space-x-2">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                            <input type="number" name="quantity" class="w-20 border rounded px-2 py-1" placeholder="Qty">
                            <button type="submit" class="bg-blue-500 text-white px-4 py-1 rounded">Update</button>
                        </form>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
</body>
</html>