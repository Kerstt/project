<?php
include 'includes/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $email = $_POST['email'];
    $phone_number = $_POST['phone_number'];
    
    // Check if email exists for other users
    $check_sql = "SELECT user_id FROM users WHERE email = ? AND user_id != ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("si", $email, $user_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $error_message = "Email already exists";
    } else {
        $sql = "UPDATE users SET first_name = ?, last_name = ?, email = ?, phone_number = ? WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssi", $first_name, $last_name, $email, $phone_number, $user_id);
        
        if ($stmt->execute()) {
            $success_message = "Profile updated successfully";
            // Refresh user data
            $sql = "SELECT * FROM users WHERE user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
        } else {
            $error_message = "Error updating profile";
        }
    }
} else {
    // Initial user data fetch
    $sql = "SELECT * FROM users WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Profile - AutoBots</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        .animate-fade { animation: fadeIn 0.5s ease-out; }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="bg-gray-900 text-gray-100">
    <!-- Display Messages -->
    <?php if ($success_message): ?>
        <div id="successMessage" class="fixed top-4 right-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded animate-fade">
            <?php echo $success_message; ?>
        </div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
        <div id="errorMessage" class="fixed top-4 right-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded animate-fade">
            <?php echo $error_message; ?>
        </div>
    <?php endif; ?>

    <nav class="bg-gray-800 border-b border-gray-700">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center">
                    <a href="index.php" class="flex items-center space-x-3">
                        <i class="fas fa-car text-orange-500 text-2xl"></i>
                        <span class="text-xl font-bold">AutoBots</span>
                    </a>
                </div>
                <a href="customer_dashboard.php" class="text-gray-300 hover:text-white">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="max-w-4xl mx-auto px-4 py-8">
        <div class="bg-gray-800 rounded-lg shadow-xl p-6 animate-fade">
            <!-- Profile Header -->
            <div class="flex items-center space-x-6 mb-8 pb-8 border-b border-gray-700">
                <div class="relative">
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['first_name'] . ' ' . $user['last_name']); ?>&size=120&background=F97316&color=fff" 
                         class="rounded-full w-28 h-28">
                    <button class="absolute bottom-0 right-0 bg-orange-500 p-2 rounded-full text-white hover:bg-orange-600 transition">
                        <i class="fas fa-camera"></i>
                    </button>
                </div>
                <div>
                    <h1 class="text-2xl font-bold"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h1>
                    <p class="text-gray-400"><?php echo htmlspecialchars($user['email']); ?></p>
                    <p class="text-gray-400">Member since <?php echo date('F Y', strtotime($user['created_at'])); ?></p>
                </div>
            </div>

            <!-- Profile Form -->
            <form method="post" action="" x-data="{ loading: false }" @submit="loading = true">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <div>
                        <label class="block text-gray-400 mb-2">First Name</label>
                        <input type="text" name="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" 
                               class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-orange-500" required>
                    </div>
                    <div>
                        <label class="block text-gray-400 mb-2">Last Name</label>
                        <input type="text" name="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>" 
                               class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-orange-500" required>
                    </div>
                    <div>
                        <label class="block text-gray-400 mb-2">Email</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" 
                               class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-orange-500" required>
                    </div>
                    <div>
                        <label class="block text-gray-400 mb-2">Phone Number</label>
                        <input type="tel" name="phone_number" value="<?php echo htmlspecialchars($user['phone_number']); ?>" 
                               class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-orange-500" required>
                    </div>
                </div>
                <button type="submit" 
                        class="bg-orange-500 text-white px-6 py-2 rounded-lg hover:bg-orange-600 transition flex items-center"
                        :class="{ 'opacity-75 cursor-not-allowed': loading }">
                    <i class="fas fa-save mr-2"></i>
                    <span x-text="loading ? 'Saving...' : 'Save Changes'"></span>
                </button>
            </form>

            <!-- Change Password Section -->
            <div class="mt-12 pt-8 border-t border-gray-700">
                <h2 class="text-xl font-bold mb-6">Change Password</h2>
                <form method="post" action="change_password.php" x-data="{ loading: false }" @submit="loading = true">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label class="block text-gray-400 mb-2">Current Password</label>
                            <input type="password" name="current_password" 
                                   class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-orange-500" required>
                        </div>
                        <div>
                            <label class="block text-gray-400 mb-2">New Password</label>
                            <input type="password" name="new_password" 
                                   class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-orange-500" required>
                        </div>
                    </div>
                    <button type="submit" 
                            class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition flex items-center"
                            :class="{ 'opacity-75 cursor-not-allowed': loading }">
                        <i class="fas fa-key mr-2"></i>
                        <span x-text="loading ? 'Updating...' : 'Update Password'"></span>
                    </button>
                </form>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="fixed bottom-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg animate-fade">
                <i class="fas fa-check-circle mr-2"></i>
                <?php 
                    echo $_SESSION['success_message'];
                    unset($_SESSION['success_message']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="fixed bottom-4 right-4 bg-red-500 text-white px-6 py-3 rounded-lg shadow-lg animate-fade">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?php 
                    echo $_SESSION['error_message'];
                    unset($_SESSION['error_message']);
                ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
    // Only auto-hide notification messages
    document.addEventListener('DOMContentLoaded', function() {
        const messages = document.querySelectorAll('#successMessage, #errorMessage');
        messages.forEach(message => {
            setTimeout(() => {
                message.style.opacity = '0';
                setTimeout(() => message.remove(), 500);
            }, 3000);
        });
    });
    </script>
</body>
</html>