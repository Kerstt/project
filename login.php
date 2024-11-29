<?php
include 'includes/db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];

    // Single query to get user details
    $sql = "SELECT * FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['name'] = $user['first_name'];

            switch ($user['role']) {
                case 'admin':
                    header('Location: admin_dashboard.php');
                    exit();
                case 'technician':
                    header('Location: technician_dashboard.php');
                    exit();
                case 'customer':
                    header('Location: customer_dashboard.php');
                    exit();
                default:
                    $_SESSION['error'] = "Invalid user role";
                    header('Location: login.php');
                    exit();
            }
        } else {
            $error = "Invalid password";
        }
    } else {
        $error = "No user found with that email";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - AutoBots</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .animate-fade-in { animation: fadeIn 0.5s ease-out; }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-blue-50 to-gray-100 flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <!-- Logo -->
        <div class="text-center mb-8 animate-fade-in">
            <a href="index.php" class="inline-flex items-center space-x-2">
                <i class="fas fa-car text-blue-600 text-4xl"></i>
                <span class="text-3xl font-bold text-gray-800">AutoBots</span>
            </a>
            <p class="mt-2 text-gray-600">Welcome back! Please login to your account.</p>
        </div>

        <!-- Login Card -->
        <div class="bg-white rounded-lg shadow-xl p-8 animate-fade-in">
            <?php if (isset($error)): ?>
                <div class="bg-red-50 text-red-500 p-4 rounded-lg mb-6 flex items-center">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="post" action="" class="space-y-6">
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                    <div class="relative">
                        <i class="fas fa-envelope absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        <input type="email" id="email" name="email" 
                               class="pl-10 w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                               required>
                    </div>
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                    <div class="relative">
                        <i class="fas fa-lock absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        <input type="password" id="password" name="password" 
                               class="pl-10 w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                               required>
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <input type="checkbox" id="remember" name="remember" class="h-4 w-4 text-blue-600 rounded border-gray-300">
                        <label for="remember" class="ml-2 text-sm text-gray-600">Remember me</label>
                    </div>
                    <a href="#" class="text-sm text-blue-600 hover:text-blue-700">Forgot password?</a>
                </div>

                <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 transition-colors flex items-center justify-center space-x-2">
                    <i class="fas fa-sign-in-alt"></i>
                    <span>Sign In</span>
                </button>
            </form>

            <div class="mt-6 text-center">
                <p class="text-sm text-gray-600">
                    Don't have an account? 
                    <a href="register.php" class="text-blue-600 hover:text-blue-700 font-medium">Sign up</a>
                </p>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center mt-8 text-sm text-gray-500">
            &copy; <?php echo date('Y'); ?> AutoBots. All rights reserved.
        </div>
    </div>

    <script>
        // Add loading state to form submission
        document.querySelector('form').addEventListener('submit', function(e) {
            const button = this.querySelector('button[type="submit"]');
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Signing in...';
        });
    </script>
</body>
</html>