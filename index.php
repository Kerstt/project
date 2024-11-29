<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AutoBots - Professional Car Service</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fadeIn { animation: fadeIn 0.5s ease-out; }
        .blur-bg { backdrop-filter: blur(8px); }
    </style>
</head>
<body class="bg-gray-50" x-data="{ isOpen: false }">
    <!-- Navigation -->
    <nav class="bg-white/90 blur-bg shadow fixed w-full z-50 transition-all duration-300">
        <div class="container mx-auto px-4">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center">
                    <a href="index.php" class="flex items-center space-x-2">
                        <i class="fas fa-car text-blue-600 text-2xl"></i>
                        <span class="text-2xl font-bold text-blue-600 hover:text-blue-700 transition">AutoBots</span>
                    </a>
                </div>

                <!-- Desktop Menu -->
                <div class="hidden md:flex space-x-8">
                    <a href="#" class="text-gray-700 hover:text-blue-600 transition-colors duration-200 flex items-center space-x-1">
                        <i class="fas fa-home text-sm"></i>
                        <span>Home</span>
                    </a>
                    <a href="#" class="text-gray-700 hover:text-blue-600 transition-colors duration-200 flex items-center space-x-1">
                        <i class="fas fa-wrench text-sm"></i>
                        <span>Services</span>
                    </a>
                    <a href="#" class="text-gray-700 hover:text-blue-600 transition-colors duration-200 flex items-center space-x-1">
                        <i class="fas fa-info-circle text-sm"></i>
                        <span>About</span>
                    </a>
                    <a href="#" class="text-gray-700 hover:text-blue-600 transition-colors duration-200 flex items-center space-x-1">
                        <i class="fas fa-envelope text-sm"></i>
                        <span>Contact</span>
                    </a>
                </div>

                <!-- Auth Buttons -->
                <div class="hidden md:flex items-center space-x-4">
                    <a href="login.php" class="text-blue-600 hover:text-blue-700 transition-colors duration-200 flex items-center space-x-1">
                        <i class="fas fa-sign-in-alt"></i>
                        <span>Login</span>
                    </a>
                    <a href="register.php" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors duration-200 flex items-center space-x-2 shadow-lg hover:shadow-xl">
                        <i class="fas fa-user-plus"></i>
                        <span>Register</span>
                    </a>
                </div>

                <!-- Mobile Menu Button -->
                <div class="md:hidden">
                    <button @click="isOpen = !isOpen" class="text-gray-700 hover:text-blue-600 transition-colors duration-200">
                        <i class="fas" :class="isOpen ? 'fa-times' : 'fa-bars'"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile Menu -->
        <div class="md:hidden" x-show="isOpen" x-transition:enter="transition ease-out duration-200" 
             x-transition:enter-start="opacity-0 transform -translate-y-2" 
             x-transition:enter-end="opacity-100 transform translate-y-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 transform translate-y-0"
             x-transition:leave-end="opacity-0 transform -translate-y-2">
            <div class="px-2 pt-2 pb-3 space-y-1 bg-white/90 blur-bg">
                <a href="#" class="block px-3 py-2 rounded-md text-gray-700 hover:text-blue-600 hover:bg-blue-50 transition-colors duration-200">
                    <i class="fas fa-home mr-2"></i> Home
                </a>
                <a href="#" class="block px-3 py-2 rounded-md text-gray-700 hover:text-blue-600 hover:bg-blue-50 transition-colors duration-200">
                    <i class="fas fa-wrench mr-2"></i> Services
                </a>
                <a href="#" class="block px-3 py-2 rounded-md text-gray-700 hover:text-blue-600 hover:bg-blue-50 transition-colors duration-200">
                    <i class="fas fa-info-circle mr-2"></i> About
                </a>
                <a href="#" class="block px-3 py-2 rounded-md text-gray-700 hover:text-blue-600 hover:bg-blue-50 transition-colors duration-200">
                    <i class="fas fa-envelope mr-2"></i> Contact
                </a>
                <div class="pt-4 border-t border-gray-200">
                    <a href="login.php" class="block px-3 py-2 rounded-md text-blue-600 hover:bg-blue-50 transition-colors duration-200">
                        <i class="fas fa-sign-in-alt mr-2"></i> Login
                    </a>
                    <a href="register.php" class="block px-3 py-2 mt-1 rounded-md bg-blue-600 text-white hover:bg-blue-700 transition-colors duration-200">
                        <i class="fas fa-user-plus mr-2"></i> Register
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="pt-20">
        <div class="relative bg-gradient-to-r from-blue-600 to-blue-800 h-[500px]">
            <div class="container mx-auto px-4 h-full flex items-center">
                <div class="text-white max-w-2xl">
                    <h1 class="text-5xl font-bold mb-6">Professional Car Service Made Simple</h1>
                    <p class="text-xl mb-8">Book your car service appointment in minutes. Expert technicians, transparent pricing, and guaranteed satisfaction.</p>
                    <a href="register.php" class="bg-white text-blue-600 px-8 py-3 rounded-lg font-semibold hover:bg-gray-100 transition">Book Appointment</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Services Section -->
    <section class="py-20 bg-white">
        <div class="container mx-auto px-4">
            <h2 class="text-3xl font-bold text-center mb-12">Our Services</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="text-center p-6">
                    <i class="fas fa-oil-can text-4xl text-blue-600 mb-4"></i>
                    <h3 class="text-xl font-semibold mb-2">Oil Change</h3>
                    <p class="text-gray-600">Professional oil change service to keep your engine running smoothly.</p>
                </div>
                <div class="text-center p-6">
                    <i class="fas fa-car-battery text-4xl text-blue-600 mb-4"></i>
                    <h3 class="text-xl font-semibold mb-2">Battery Service</h3>
                    <p class="text-gray-600">Battery testing, replacement, and maintenance services.</p>
                </div>
                <div class="text-center p-6">
                    <i class="fas fa-brake-system text-4xl text-blue-600 mb-4"></i>
                    <h3 class="text-xl font-semibold mb-2">Brake Service</h3>
                    <p class="text-gray-600">Complete brake inspection and repair services.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Why Choose Us -->
    <section class="py-20 bg-gray-50">
        <div class="container mx-auto px-4">
            <h2 class="text-3xl font-bold text-center mb-12">Why Choose AutoBots?</h2>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div class="text-center">
                    <i class="fas fa-clock text-3xl text-blue-600 mb-4"></i>
                    <h3 class="font-semibold mb-2">Quick Service</h3>
                    <p class="text-gray-600">Same-day service available</p>
                </div>
                <div class="text-center">
                    <i class="fas fa-tools text-3xl text-blue-600 mb-4"></i>
                    <h3 class="font-semibold mb-2">Expert Mechanics</h3>
                    <p class="text-gray-600">Certified professionals</p>
                </div>
                <div class="text-center">
                    <i class="fas fa-dollar-sign text-3xl text-blue-600 mb-4"></i>
                    <h3 class="font-semibold mb-2">Fair Pricing</h3>
                    <p class="text-gray-600">Transparent, competitive rates</p>
                </div>
                <div class="text-center">
                    <i class="fas fa-shield-alt text-3xl text-blue-600 mb-4"></i>
                    <h3 class="font-semibold mb-2">Guaranteed Work</h3>
                    <p class="text-gray-600">100% satisfaction guarantee</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials -->
    <section class="py-20 bg-white">
        <div class="container mx-auto px-4">
            <h2 class="text-3xl font-bold text-center mb-12">What Our Customers Say</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="bg-gray-50 p-6 rounded-lg">
                    <p class="text-gray-600 mb-4">"Excellent service! Very professional and timely."</p>
                    <p class="font-semibold">- John Doe</p>
                </div>
                <div class="bg-gray-50 p-6 rounded-lg">
                    <p class="text-gray-600 mb-4">"Best car service I've ever experienced. Highly recommended!"</p>
                    <p class="font-semibold">- Jane Smith</p>
                </div>
                <div class="bg-gray-50 p-6 rounded-lg">
                    <p class="text-gray-600 mb-4">"Fair prices and great customer service."</p>
                    <p class="font-semibold">- Mike Johnson</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-20 bg-blue-600">
        <div class="container mx-auto px-4 text-center">
            <h2 class="text-3xl font-bold text-white mb-8">Ready to Book Your Service?</h2>
            <a href="register.php" class="bg-white text-blue-600 px-8 py-3 rounded-lg font-semibold hover:bg-gray-100 inline-block">Book Now</a>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-12">
        <div class="container mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div>
                    <h3 class="text-xl font-bold mb-4">AutoBots</h3>
                    <p class="text-gray-400">Professional car service you can trust.</p>
                </div>
                <div>
                    <h4 class="font-semibold mb-4">Quick Links</h4>
                    <ul class="space-y-2 text-gray-400">
                        <li><a href="#" class="hover:text-white">About Us</a></li>
                        <li><a href="#" class="hover:text-white">Services</a></li>
                        <li><a href="#" class="hover:text-white">Contact</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-semibold mb-4">Services</h4>
                    <ul class="space-y-2 text-gray-400">
                        <li><a href="#" class="hover:text-white">Oil Change</a></li>
                        <li><a href="#" class="hover:text-white">Brake Service</li>
                        <li><a href="#" class="hover:text-white">Battery Service</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-semibold mb-4">Contact</h4>
                    <ul class="space-y-2 text-gray-400">
                        <li>123 Service St.</li>
                        <li>Phone: (555) 123-4567</li>
                        <li>Email: info@autobots.com</li>
                    </ul>
                </div>
            </div>
            <div class="border-t border-gray-700 mt-8 pt-8 text-center text-gray-400">
                <p>&copy; 2024 AutoBots. All rights reserved.</p>
            </div>
        </div>
    </footer>
</body>
</html>