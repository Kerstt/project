</main>
    <footer class="bg-gray-800 text-white mt-12">
        <div class="max-w-7xl mx-auto px-4 py-12">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div>
                    <h3 class="text-xl font-bold mb-4">AutoBots</h3>
                    <p class="text-gray-400">Professional car service you can trust.</p>
                </div>
                <div>
                    <h4 class="font-semibold mb-4">Quick Links</h4>
                    <ul class="space-y-2 text-gray-400">
                        <li><a href="#" class="hover:text-white transition-colors">Services</a></li>
                        <li><a href="#" class="hover:text-white transition-colors">About Us</a></li>
                        <li><a href="#" class="hover:text-white transition-colors">Contact</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-semibold mb-4">Services</h4>
                    <ul class="space-y-2 text-gray-400">
                        <li><a href="#" class="hover:text-white transition-colors">Oil Change</a></li>
                        <li><a href="#" class="hover:text-white transition-colors">Brake Service</a></li>
                        <li><a href="#" class="hover:text-white transition-colors">Tire Service</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-semibold mb-4">Contact</h4>
                    <ul class="space-y-2 text-gray-400">
                        <li><i class="fas fa-map-marker-alt mr-2"></i>123 Service St.</li>
                        <li><i class="fas fa-phone mr-2"></i>(555) 123-4567</li>
                        <li><i class="fas fa-envelope mr-2"></i>info@autobots.com</li>
                    </ul>
                </div>
            </div>
            <div class="border-t border-gray-700 mt-8 pt-8 text-center text-gray-400">
                <p>&copy; <?php echo date('Y'); ?> AutoBots. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Toast Messages -->
    <div id="toast" class="fixed bottom-4 right-4 transform transition-all duration-300 opacity-0 translate-y-full">
        <div class="bg-green-600 text-white px-6 py-3 rounded-lg shadow-lg">
            <span id="toast-message"></span>
        </div>
    </div>

    <script>
        function showToast(message) {
            const toast = document.getElementById('toast');
            const toastMessage = document.getElementById('toast-message');
            toastMessage.textContent = message;
            toast.classList.remove('opacity-0', 'translate-y-full');
            setTimeout(() => {
                toast.classList.add('opacity-0', 'translate-y-full');
            }, 3000);
        }
    </script>
</body>
</html>