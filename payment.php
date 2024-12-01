<?php
include 'includes/db.php';
session_start();

$appointment_id = $_GET['appointment_id'];

$sql = "SELECT a.*, s.name as service_name, s.price 
        FROM appointments a 
        JOIN services s ON a.service_id = s.service_id 
        WHERE a.appointment_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $appointment_id);
$stmt->execute();
$appointment = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment - AutoBots</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-900">
    <div class="min-h-screen flex items-center justify-center">
        <div class="max-w-md w-full mx-4">
            <div class="bg-gray-800 rounded-lg shadow-xl p-8">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold text-white">Payment Details</h2>
                    <div class="text-orange-500">
                        <i class="fas fa-lock text-xl"></i>
                    </div>
                </div>

                <!-- Service Summary -->
                <div class="mb-8 p-4 bg-gray-700 rounded-lg">
                    <h3 class="text-lg font-semibold text-white mb-2">Service Summary</h3>
                    <p class="text-gray-300"><?php echo $appointment['service_name']; ?></p>
                    <div class="text-2xl font-bold text-orange-500 mt-2">
                        $<?php echo number_format($appointment['price'], 2); ?>
                    </div>
                </div>

                <!-- Payment Form -->
                <form action="./process_payment.php" method="POST" class="space-y-6"> <!-- Remove leading slash -->
                    <input type="hidden" name="appointment_id" value="<?php echo $appointment_id; ?>">
                    
                    <!-- Payment Method Selection -->
                    <div class="grid grid-cols-2 gap-4">
                        <label class="relative">
                            <input type="radio" name="payment_method" value="credit_card" class="sr-only peer" checked>
                            <div class="peer-checked:border-orange-500 peer-checked:text-orange-500 border-2 rounded-lg p-4 cursor-pointer text-center text-gray-300 hover:border-orange-500 transition">
                                <i class="fas fa-credit-card mb-2"></i>
                                <p>Credit Card</p>
                            </div>
                        </label>
                        <label class="relative">
                            <input type="radio" name="payment_method" value="cash" class="sr-only peer">
                            <div class="peer-checked:border-orange-500 peer-checked:text-orange-500 border-2 rounded-lg p-4 cursor-pointer text-center text-gray-300 hover:border-orange-500 transition">
                                <i class="fas fa-money-bill mb-2"></i>
                                <p>Cash</p>
                            </div>
                        </label>
                    </div>

                    <!-- Credit Card Details -->
                    <div id="creditCardDetails" class="space-y-4">
                        <div>
                            <label class="block text-gray-300 mb-2">Card Number</label>
                            <input type="text" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white placeholder-gray-400" placeholder="1234 5678 9012 3456">
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-gray-300 mb-2">Expiry Date</label>
                                <input type="text" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white placeholder-gray-400" placeholder="MM/YY">
                            </div>
                            <div>
                                <label class="block text-gray-300 mb-2">CVV</label>
                                <input type="text" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white placeholder-gray-400" placeholder="123">
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="w-full bg-orange-500 hover:bg-orange-600 text-white font-bold py-3 px-4 rounded-lg transition flex items-center justify-center space-x-2">
                        <i class="fas fa-lock"></i>
                        <span>Pay Now</span>
                    </button>
                </form>

                <?php if (isset($_GET['error'])): ?>
                    <div class="mt-4 p-4 bg-red-500 text-white rounded-lg">
                        <?php echo htmlspecialchars($_GET['error']); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const creditCardDetails = document.getElementById('creditCardDetails');
        const paymentMethods = document.getElementsByName('payment_method');

        paymentMethods.forEach(method => {
            method.addEventListener('change', function() {
                creditCardDetails.style.display = this.value === 'credit_card' ? 'block' : 'none';
            });
        });
    });
    </script>
</body>
</html>