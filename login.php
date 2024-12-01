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
        .bg-image {
            background-image: linear-gradient(rgba(17, 24, 39, 0.8), rgba(17, 24, 39, 0.9)),
                            url('https://images.unsplash.com/photo-1492144534655-ae79c964c9d7?ixlib=rb-1.2.1&auto=format&fit=crop&w=1920&q=80');
            background-size: cover;
            background-position: center;
        }
    </style>
</head>
<body class="bg-gray-900 min-h-screen">
    <div class="flex min-h-screen">
        <!-- Left side - Image -->
        <div class="hidden lg:block lg:w-1/2 bg-image"></div>

        <!-- Right side - Login Form -->
        <div class="w-full lg:w-1/2 flex items-center justify-center p-8">
            <div class="max-w-md w-full space-y-8">
                <!-- Logo -->
                <div class="text-center">
                    <a href="index.php" class="flex items-center justify-center space-x-2">
                        <svg class="h-12 w-12" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                            <defs>
                                <linearGradient id="logoGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" style="stop-color:#F97316;stop-opacity:1" />
                                    <stop offset="100%" style="stop-color:#C2410C;stop-opacity:1" />
                                </linearGradient>
                            </defs>
                            <path fill="url(#logoGradient)" d="M50 5 L90 25 L90 75 L50 95 L10 75 L10 25 Z">
                                <animate attributeName="d" dur="10s" repeatCount="indefinite"
                                    values="M50 5 L90 25 L90 75 L50 95 L10 75 L10 25 Z;
                                           M50 10 L85 28 L85 72 L50 90 L15 72 L15 28 Z;
                                           M50 5 L90 25 L90 75 L50 95 L10 75 L10 25 Z" />
                            </path>
                        </svg>
                        <span class="text-2xl font-bold text-orange-500">AutoBots</span>
                    </a>
                    <h2 class="mt-6 text-3xl font-bold text-white">Welcome back</h2>
                    <p class="mt-2 text-gray-400">Sign in to access your account</p>
                </div>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="bg-red-900/50 border-l-4 border-red-500 p-4" role="alert">
                        <p class="text-red-400"><?php echo $_SESSION['error']; ?></p>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <form method="POST" class="mt-8 space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-300" for="email">Email</label>
                        <div class="mt-1 relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-envelope text-gray-500"></i>
                            </div>
                            <input type="email" id="email" name="email" required
                                class="block w-full pl-10 bg-gray-800 border border-gray-700 rounded-lg px-4 py-2 text-white focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                placeholder="Enter your email">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300" for="password">Password</label>
                        <div class="mt-1 relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-lock text-gray-500"></i>
                            </div>
                            <input type="password" id="password" name="password" required
                                class="block w-full pl-10 bg-gray-800 border border-gray-700 rounded-lg px-4 py-2 text-white focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                placeholder="Enter your password">
                        </div>
                    </div>

                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <input type="checkbox" id="remember" name="remember"
                                class="h-4 w-4 text-orange-500 focus:ring-orange-500 border-gray-700 rounded bg-gray-800">
                            <label for="remember" class="ml-2 block text-sm text-gray-300">Remember me</label>
                        </div>
                        <a href="#" class="text-sm text-orange-500 hover:text-orange-400">Forgot password?</a>
                    </div>

                    <button type="submit" 
                        class="w-full flex justify-center py-2 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-orange-500 hover:bg-orange-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500">
                        <i class="fas fa-sign-in-alt mr-2"></i> Sign in
                    </button>
                </form>

                <p class="text-center text-gray-400">
                    Don't have an account? 
                    <a href="register.php" class="text-orange-500 hover:text-orange-400">Create one</a>
                </p>
            </div>
        </div>
    </div>

    <script>
    // Add form validation if needed
    document.querySelector('form').addEventListener('submit', function(e) {
        const email = document.getElementById('email').value;
        const password = document.getElementById('password').value;
        
        if (!email || !password) {
            e.preventDefault();
            alert('Please fill in all fields');
        }
    });
    </script>
</body>
</html>