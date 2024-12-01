<?php
include 'includes/db.php';
session_start();

if (!isset($_SESSION['payment_success']) || !isset($_SESSION['appointment_details'])) {
    header('Location: customer_dashboard.php');
    exit();
}

$appointment = $_SESSION['appointment_details'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Confirmation - AutoBots</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-900">
    <div class="min-h-screen flex items-center justify-center">
        <div class="max-w-md w-full mx-4">
            <div class="bg-gray-800 rounded-lg shadow-xl p-8 text-center">
                <div class="mb-6">
                    <div class="w-16 h-16 bg-green-500 rounded-full flex items-center justify-center mx-auto">
                        <i class="fas fa-check text-white text-3xl"></i>
                    </div>
                </div>
                
                <h2 class="text-2xl font-bold text-white mb-4">Payment Successful!</h2>
                <p class="text-gray-300 mb-6">Your payment has been processed successfully.</p>
                
                <div class="bg-gray-700 rounded-lg p-4 mb-6">
                    <div class="text-gray-300 mb-2">Payment Details</div>
                    <div class="text-white font-semibold">Amount: $<?php echo number_format($appointment['price'], 2); ?></div>
                    <div class="text-gray-400">Service: <?php echo $appointment['service_name']; ?></div>
                    <div class="text-gray-400">Reference: #<?php echo $appointment['appointment_id']; ?></div>
                </div>

                <div class="space-y-3">
                    <a href="customer_dashboard.php" 
                       class="block w-full bg-orange-500 hover:bg-orange-600 text-white font-bold py-3 px-4 rounded-lg transition">
                        Return to Dashboard
                    </a>
                    <a href="#" onclick="window.print()" 
                       class="block w-full bg-gray-600 hover:bg-gray-700 text-white font-bold py-3 px-4 rounded-lg transition">
                        Print Receipt
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>