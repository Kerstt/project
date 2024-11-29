<?php
include 'includes/db.php';
session_start();

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $email = $_POST['email'];
    $phone_number = $_POST['phone_number'];
    
    $sql = "UPDATE users SET first_name = ?, last_name = ?, email = ?, phone_number = ? WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssi", $first_name, $last_name, $email, $phone_number, $user_id);
    $stmt->execute();
}

$sql = "SELECT * FROM users WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto p-4">
        <h1 class="text-2xl font-bold mb-4">My Profile</h1>
        <div class="bg-white rounded-lg shadow p-4">
            <form method="post" action="">
                <div class="grid grid-cols-2 gap-4">
                    <div class="mb-4">
                        <label class="block text-gray-700">First Name</label>
                        <input type="text" name="first_name" value="<?php echo $user['first_name']; ?>" 
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700">Last Name</label>
                        <input type="text" name="last_name" value="<?php echo $user['last_name']; ?>" 
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700">Email</label>
                        <input type="email" name="email" value="<?php echo $user['email']; ?>" 
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700">Phone Number</label>
                        <input type="text" name="phone_number" value="<?php echo $user['phone_number']; ?>" 
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
                    </div>
                </div>
                <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded">Update Profile</button>
            </form>
            
            <div class="mt-8">
                <h2 class="text-xl font-bold mb-4">Change Password</h2>
                <form method="post" action="change_password.php">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="mb-4">
                            <label class="block text-gray-700">Current Password</label>
                            <input type="password" name="current_password" 
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
                        </div>
                        <div class="mb-4">
                            <label class="block text-gray-700">New Password</label>
                            <input type="password" name="new_password" 
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
                        </div>
                    </div>
                    <button type="submit" class="bg-green-500 text-white px-4 py-2 rounded">Change Password</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>