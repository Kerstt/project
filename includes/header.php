<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'AutoBots'; ?> - Professional Car Service</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        :root {
            --primary: #16a34a;
            --primary-dark: #15803d;
        }
        .animate-fade-in { animation: fadeIn 0.3s ease-out; }
        .animate-slide-up { animation: slideUp 0.4s ease-out; }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex flex-col" x-data="{ isOpen: false }">
    <nav class="bg-white shadow-lg sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <!-- Logo -->
                <div class="flex items-center">
                    <a href="index.php" class="flex items-center space-x-3">
                        <i class="fas fa-car text-green-600 text-2xl"></i>
                        <span class="text-xl font-bold text-gray-900">AutoBots</span>
                    </a>
                </div>

                <!-- Desktop Navigation -->
                <div class="hidden md:flex items-center space-x-8">
                    <a href="service_catalog.php" class="text-gray-600 hover:text-green-600 transition-colors">Services</a>
                    <a href="#" class="text-gray-600 hover:text-green-600 transition-colors">About</a>
                    <a href="#" class="text-gray-600 hover:text-green-600 transition-colors">Contact</a>
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <a href="dashboard.php" class="text-gray-600 hover:text-green-600 transition-colors">Dashboard</a>
                        <a href="logout.php" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors">Logout</a>
                    <?php else: ?>
                        <a href="login.php" class="text-gray-600 hover:text-green-600 transition-colors">Login</a>
                        <a href="register.php" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors">Register</a>
                    <?php endif; ?>
                </div>

                <!-- Mobile menu button -->
                <div class="md:hidden flex items-center">
                    <button @click="isOpen = !isOpen" class="text-gray-600">
                        <i class="fas" :class="isOpen ? 'fa-times' : 'fa-bars'"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile Navigation -->
        <div class="md:hidden" x-show="isOpen" x-transition>
            <div class="px-2 pt-2 pb-3 space-y-1 bg-white">
                <a href="service_catalog.php" class="block px-3 py-2 text-gray-600 hover:bg-green-50 hover:text-green-600 rounded-md">Services</a>
                <a href="#" class="block px-3 py-2 text-gray-600 hover:bg-green-50 hover:text-green-600 rounded-md">About</a>
                <a href="#" class="block px-3 py-2 text-gray-600 hover:bg-green-50 hover:text-green-600 rounded-md">Contact</a>
                <?php if(isset($_SESSION['user_id'])): ?>
                    <a href="dashboard.php" class="block px-3 py-2 text-gray-600 hover:bg-green-50 hover:text-green-600 rounded-md">Dashboard</a>
                    <a href="logout.php" class="block px-3 py-2 bg-green-600 text-white rounded-md">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="block px-3 py-2 text-gray-600 hover:bg-green-50 hover:text-green-600 rounded-md">Login</a>
                    <a href="register.php" class="block px-3 py-2 bg-green-600 text-white rounded-md">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    <main class="flex-grow animate-fade-in">