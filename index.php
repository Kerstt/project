<?php
require_once 'includes/db.php';
?>
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
        :root {
            --color-dark: #1F2937;
            --color-orange: #F97316;
        }
        .bg-dark { background-color: var(--color-dark); }
        .text-orange { color: var(--color-orange); }
        .bg-orange { background-color: var(--color-orange); }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fadeIn { animation: fadeIn 0.5s ease-out; }
        .blur-bg { backdrop-filter: blur(8px); }
        .hero-section {
            background-image: linear-gradient(rgba(31, 41, 55, 0.8), rgba(31, 41, 55, 0.8)),
                            url('https://images.unsplash.com/photo-1625047509248-ec889cbff17f?ixlib=rb-1.2.1&auto=format&fit=crop&w=1920&q=80');
            background-size: cover;
            background-position: center;
        }
    </style>
</head>
<body class="bg-dark text-gray-100" x-data="{ isOpen: false }">
    <!-- Navigation -->
    <nav class="bg-dark/90 blur-bg shadow fixed w-full z-50 transition-all duration-300">
        <div class="container mx-auto px-4">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center">
                    <a href="index.php" class="flex items-center space-x-3">
                        <svg class="h-12 w-12" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                            <defs>
                                <linearGradient id="logoGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" style="stop-color:#F97316;stop-opacity:1" />
                                    <stop offset="100%" style="stop-color:#C2410C;stop-opacity:1" />
                                </linearGradient>
                            </defs>
                            <path fill="url(#logoGradient)" d="M50 5
                                L90 25 L90 75 L50 95 L10 75 L10 25 Z
                                M50 20 L70 30 L70 45 L50 55 L30 45 L30 30 Z
                                M20 60 L45 75 L45 85 L20 70 Z
                                M80 60 L55 75 L55 85 L80 70 Z">
                                <animate attributeName="d" dur="10s" repeatCount="indefinite"
                                    values="M50 5 L90 25 L90 75 L50 95 L10 75 L10 25 Z M50 20 L70 30 L70 45 L50 55 L30 45 L30 30 Z M20 60 L45 75 L45 85 L20 70 Z M80 60 L55 75 L55 85 L80 70 Z;
                                           M50 10 L85 28 L85 72 L50 90 L15 72 L15 28 Z M50 25 L65 33 L65 42 L50 50 L35 42 L35 33 Z M25 65 L42 77 L42 82 L25 70 Z M75 65 L58 77 L58 82 L75 70 Z;
                                           M50 5 L90 25 L90 75 L50 95 L10 75 L10 25 Z M50 20 L70 30 L70 45 L50 55 L30 45 L30 30 Z M20 60 L45 75 L45 85 L20 70 Z M80 60 L55 75 L55 85 L80 70 Z"
                                />
                            </path>
                        </svg>
                        <div class="flex flex-col">
                            <span class="text-2xl font-bold text-orange">AutoBots</span>
                            <span class="text-xs text-gray-400">Transform Your Ride</span>
                        </div>
                    </a>
                </div>
                
                <!-- Desktop Navigation -->
                <div class="hidden md:flex items-center space-x-8">
                    <a href="#services" class="text-gray-300 hover:text-orange transition-colors">Services</a>
                    <a href="#about" class="text-gray-300 hover:text-orange transition-colors">About</a>
                    <a href="#contact" class="text-gray-300 hover:text-orange transition-colors">Contact</a>
                    <a href="login.php" class="text-gray-300 hover:text-orange transition-colors">Login</a>
                    <a href="register.php" class="bg-orange text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition-colors">
                        Get Started
                    </a>
                </div>

                <!-- Mobile Menu Button -->
                <button @click="isOpen = !isOpen" class="md:hidden text-gray-300">
                    <i class="fas" :class="isOpen ? 'fa-times' : 'fa-bars'"></i>
                </button>
            </div>
        </div>
        
        <!-- Mobile Menu -->
        <div x-show="isOpen" class="md:hidden bg-dark border-t border-gray-800">
            <div class="container mx-auto px-4 py-4 space-y-4">
                <a href="#services" class="block text-gray-300 hover:text-orange">Services</a>
                <a href="#about" class="block text-gray-300 hover:text-orange">About</a>
                <a href="#contact" class="block text-gray-300 hover:text-orange">Contact</a>
                <a href="login.php" class="block text-gray-300 hover:text-orange">Login</a>
                <a href="register.php" class="block bg-orange text-white px-4 py-2 rounded-lg text-center">Get Started</a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section min-h-screen flex items-center justify-center">
        <div class="container mx-auto px-4 text-center">
            <div class="animate-fadeIn max-w-3xl mx-auto">
                <h1 class="text-5xl md:text-6xl font-bold mb-6">Transform Your Car Care Experience</h1>
                <p class="text-xl mb-8 text-gray-300">Professional auto service with the power of innovation and expertise</p>
                <div class="space-x-4">
                    <a href="register.php" class="bg-orange text-white px-8 py-3 rounded-lg font-semibold hover:bg-orange-600 transition-colors">
                        Book Appointment
                    </a>
                    <a href="#services" class="bg-gray-800 text-white px-8 py-3 rounded-lg font-semibold hover:bg-gray-700 transition-colors">
                        Our Services
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section id="services" class="py-20 bg-gray-900">
        <div class="container mx-auto px-4">
            <h2 class="text-3xl font-bold text-center mb-12">Our Services</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <?php
                $sql = "SELECT * FROM services LIMIT 3";
                $result = $conn->query($sql);
                while($service = $result->fetch_assoc()):
                ?>
                <div class="bg-gray-800 rounded-lg p-6 hover:transform hover:scale-105 transition-all duration-300">
                    <div class="text-orange text-4xl mb-4">
                        <i class="fas <?php echo getServiceIcon($service['name']); ?>"></i>
                    </div>
                    <h3 class="text-xl font-semibold mb-2"><?php echo htmlspecialchars($service['name']); ?></h3>
                    <p class="text-gray-400"><?php echo htmlspecialchars($service['description']); ?></p>
                    <p class="text-orange font-bold mt-4">$<?php echo number_format($service['price'], 2); ?></p>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </section>

    <!-- Service Packages -->
    <section class="py-20 bg-gray-800">
        <div class="container mx-auto px-4">
            <h2 class="text-3xl font-bold text-center mb-12">Service Packages</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <?php
                $sql = "SELECT * FROM service_packages WHERE is_active = 1";
                $packages = $conn->query($sql);
                while($package = $packages->fetch_assoc()):
                ?>
                <div class="bg-gray-900 rounded-lg p-6 hover:transform hover:scale-105 transition-all duration-300">
                    <h3 class="text-xl font-bold mb-4 text-orange"><?php echo htmlspecialchars($package['name']); ?></h3>
                    <p class="text-gray-400 mb-4"><?php echo htmlspecialchars($package['description']); ?></p>
                    <div class="border-t border-gray-700 pt-4">
                        <p class="text-2xl font-bold text-orange">$<?php echo number_format($package['price'], 2); ?></p>
                        <p class="text-sm text-gray-400"><?php echo $package['duration_minutes']; ?> minutes</p>
                    </div>
                    <a href="register.php" class="mt-4 block text-center bg-orange text-white py-2 rounded-lg hover:bg-orange-600 transition-colors">
                        Book Now
                    </a>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="py-20 bg-dark">
        <div class="container mx-auto px-4">
            <h2 class="text-3xl font-bold text-center mb-16">Why Choose AutoBots</h2>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div class="text-center group">
                    <div class="bg-gray-800 w-16 h-16 mx-auto rounded-full flex items-center justify-center mb-4 group-hover:bg-orange transition-colors">
                        <i class="fas fa-tools text-2xl text-orange group-hover:text-white"></i>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">Expert Technicians</h3>
                    <p class="text-gray-400">Certified professionals with years of experience</p>
                </div>
                <div class="text-center group">
                    <div class="bg-gray-800 w-16 h-16 mx-auto rounded-full flex items-center justify-center mb-4 group-hover:bg-orange transition-colors">
                        <i class="fas fa-clock text-2xl text-orange group-hover:text-white"></i>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">Quick Service</h3>
                    <p class="text-gray-400">Fast and efficient service delivery</p>
                </div>
                <div class="text-center group">
                    <div class="bg-gray-800 w-16 h-16 mx-auto rounded-full flex items-center justify-center mb-4 group-hover:bg-orange transition-colors">
                        <i class="fas fa-shield-alt text-2xl text-orange group-hover:text-white"></i>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">Quality Guarantee</h3>
                    <p class="text-gray-400">100% satisfaction guaranteed</p>
                </div>
                <div class="text-center group">
                    <div class="bg-gray-800 w-16 h-16 mx-auto rounded-full flex items-center justify-center mb-4 group-hover:bg-orange transition-colors">
                        <i class="fas fa-dollar-sign text-2xl text-orange group-hover:text-white"></i>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">Fair Pricing</h3>
                    <p class="text-gray-400">Competitive and transparent pricing</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="py-20 bg-gray-900">
        <div class="container mx-auto px-4">
            <h2 class="text-3xl font-bold text-center mb-16">What Our Customers Say</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="bg-gray-800 p-6 rounded-lg">
                    <div class="flex items-center mb-4">
                        <img src="https://i.pravatar.cc/150?img=1" alt="Customer" class="w-12 h-12 rounded-full mr-4">
                        <div>
                            <h4 class="font-semibold">John Doe</h4>
                            <div class="text-orange">
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                            </div>
                        </div>
                    </div>
                    <p class="text-gray-400">"Excellent service! The team was professional and got my car fixed quickly."</p>
                </div>
                <!-- Add more testimonials -->
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="py-20 bg-dark">
        <div class="container mx-auto px-4">
            <div class="max-w-4xl mx-auto">
                <h2 class="text-3xl font-bold text-center mb-12">Get In Touch</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-12">
                    <div>
                        <h3 class="text-xl font-semibold mb-4">Contact Information</h3>
                        <div class="space-y-4">
                            <p class="flex items-center text-gray-400">
                                <i class="fas fa-map-marker-alt w-6 text-orange"></i>
                                123 Service Street, City, State
                            </p>
                            <p class="flex items-center text-gray-400">
                                <i class="fas fa-phone w-6 text-orange"></i>
                                (555) 123-4567
                            </p>
                            <p class="flex items-center text-gray-400">
                                <i class="fas fa-envelope w-6 text-orange"></i>
                                info@autobots.com
                            </p>
                        </div>
                    </div>
                    <div>
                        <form class="space-y-4">
                            <input type="text" placeholder="Name" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2 text-gray-300">
                            <input type="email" placeholder="Email" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2 text-gray-300">
                            <textarea placeholder="Message" rows="4" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2 text-gray-300"></textarea>
                            <button type="submit" class="bg-orange text-white px-6 py-2 rounded-lg hover:bg-orange-600 transition-colors">
                                Send Message
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-900 text-gray-400 py-12">
        <div class="container mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div>
                    <img src="https://cdn-icons-png.flaticon.com/512/744/744465.png" alt="AutoBots Logo" class="h-12 w-12 mb-4">
                    <p>Professional auto service you can trust.</p>
                </div>
                <div>
                    <h4 class="text-white font-semibold mb-4">Quick Links</h4>
                    <ul class="space-y-2">
                        <li><a href="#services" class="hover:text-orange transition-colors">Services</a></li>
                        <li><a href="#about" class="hover:text-orange transition-colors">About Us</a></li>
                        <li><a href="#contact" class="hover:text-orange transition-colors">Contact</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-white font-semibold mb-4">Services</h4>
                    <ul class="space-y-2">
                        <li><a href="#" class="hover:text-orange transition-colors">Oil Change</a></li>
                        <li><a href="#" class="hover:text-orange transition-colors">Brake Service</a></li>
                        <li><a href="#" class="hover:text-orange transition-colors">Tire Service</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-white font-semibold mb-4">Follow Us</h4>
                    <div class="flex space-x-4">
                        <a href="#" class="hover:text-orange transition-colors"><i class="fab fa-facebook"></i></a>
                        <a href="#" class="hover:text-orange transition-colors"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="hover:text-orange transition-colors"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
            </div>
            <div class="border-t border-gray-800 mt-12 pt-8 text-center">
                <p>&copy; <?php echo date('Y'); ?> AutoBots. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <?php
    function getServiceIcon($serviceName) {
        $icons = [
            'Oil Change' => 'fa-oil-can',
            'Brake Service' => 'fa-brake-system',
            'Tire Change' => 'fa-tire',
            'Battery Service' => 'fa-car-battery',
            'default' => 'fa-wrench'
        ];
        return $icons[strtolower($serviceName)] ?? $icons['default'];
    }
    ?>
</body>
</html>
