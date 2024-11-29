<?php
include 'includes/db.php';
session_start();

$user_id = $_SESSION['user_id'];

$sql = "SELECT * FROM notifications WHERE user_id = ? ORDER BY sent_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Notifications</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto p-4">
        <h1 class="text-2xl font-bold mb-4">Notifications</h1>
        <div class="bg-white rounded-lg shadow p-4">
            <?php while($notification = $result->fetch_assoc()): ?>
                <div class="border-b p-4 <?php echo $notification['type'] === 'email' ? 'bg-blue-50' : 'bg-green-50'; ?>">
                    <p class="text-gray-600 text-sm"><?php echo date('M d, Y H:i', strtotime($notification['sent_at'])); ?></p>
                    <p class="mt-2"><?php echo $notification['message']; ?></p>
                    <span class="inline-block mt-2 px-2 py-1 text-xs rounded <?php echo $notification['type'] === 'email' ? 'bg-blue-200' : 'bg-green-200'; ?>">
                        <?php echo ucfirst($notification['type']); ?>
                    </span>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
</body>
</html>