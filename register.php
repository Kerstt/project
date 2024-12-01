<?php
include 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
    $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $phone_number = mysqli_real_escape_string($conn, $_POST['phone_number']);

    // Check if email exists
    $check_sql = "SELECT * FROM users WHERE email = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $error_message = "Email already registered";
    } else {
        $sql = "INSERT INTO users (role, first_name, last_name, email, password, phone_number) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $role = 'customer';
        $stmt->bind_param("ssssss", $role, $first_name, $last_name, $email, $password, $phone_number);
        
        if ($stmt->execute()) {
            $success_message = "Registration successful! Please login.";
            header("refresh:2;url=login.php");
        } else {
            $error_message = "Error: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account - AutoBots</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .bg-image {
            background-image: linear-gradient(rgba(17, 24, 39, 0.8), rgba(17, 24, 39, 0.9)), 
                            url('https://images.unsplash.com/photo-1625047509248-ec889cbff17f?ixlib=rb-1.2.1&auto=format&fit=crop&w=1920&q=80');
            background-size: cover;
            background-position: center;
        }
    </style>
</head>
<body class="bg-gray-900 min-h-screen">
    <div class="flex min-h-screen">
        <!-- Left side - Image -->
        <div class="hidden lg:block lg:w-1/2 bg-image"></div>

        <!-- Right side - Form -->
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
                            <path fill="url(#logoGradient)" d="M50 5 L90 25 L90 75 L50 95 L10 75 L10 25 Z"></path>
                        </svg>
                        <span class="text-2xl font-bold text-orange-500">AutoBots</span>
                    </a>
                    <h2 class="mt-6 text-3xl font-bold text-white">Create your account</h2>
                    <p class="mt-2 text-gray-400">Join AutoBots for premium car services</p>
                </div>

                <?php if (isset($error_message)): ?>
                    <div class="bg-red-900/50 border-l-4 border-red-500 p-4 mb-4" role="alert">
                        <p class="text-red-400"><?php echo $error_message; ?></p>
                    </div>
                <?php endif; ?>

                <?php if (isset($success_message)): ?>
                    <div class="bg-green-900/50 border-l-4 border-green-500 p-4 mb-4" role="alert">
                        <p class="text-green-400"><?php echo $success_message; ?></p>
                    </div>
                <?php endif; ?>

                <form method="POST" class="mt-8 space-y-6" onsubmit="return validateForm()">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-300" for="first_name">First Name</label>
                            <input type="text" id="first_name" name="first_name" required
                                class="mt-1 block w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2 text-white focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-300" for="last_name">Last Name</label>
                            <input type="text" id="last_name" name="last_name" required
                                class="mt-1 block w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2 text-white focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300" for="email">Email</label>
                        <input type="email" id="email" name="email" required
                            class="mt-1 block w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2 text-white focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300" for="phone_number">Phone Number</label>
                        <input type="tel" id="phone_number" name="phone_number" required
                            class="mt-1 block w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2 text-white focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300" for="password">Password</label>
                        <input type="password" id="password" name="password" required
                            class="mt-1 block w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2 text-white focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                    </div>

                    <div>
                        <button type="submit" 
                            class="w-full bg-orange-500 text-white py-2 px-4 rounded-lg hover:bg-orange-600 transition-colors">
                            Create Account
                        </button>
                    </div>
                </form>

                <p class="text-center text-gray-400">
                    Already have an account? 
                    <a href="login.php" class="text-orange-500 hover:text-orange-400">Sign in</a>
                </p>
            </div>
        </div>
    </div>

    <script>
    function validateForm() {
        const password = document.getElementById('password').value;
        if (password.length < 6) {
            alert('Password must be at least 6 characters long');
            return false;
        }
        return true;
    }
    </script>
</body>
</html>