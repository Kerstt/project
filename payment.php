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
    <title>Payment</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto p-4">
        <h1 class="text-2xl font-bold mb-4">Payment</h1>
        <div class="bg-white rounded-lg shadow p-4">
            <div class="mb-4">
                <h2 class="text-xl font-bold mb-2">Service Details</h2>
                <p><strong>Service:</strong> <?php echo $appointment['service_name']; ?></p>
                <p><strong>Amount:</strong> $<?php echo $appointment['price']; ?></p>
                <p><strong>Date:</strong> <?php echo $appointment['appointment_date']; ?></p>
            </div>
            
            <form method="post" action="process_payment.php">
                <input type="hidden" name="appointment_id" value="<?php echo $appointment_id; ?>">
                <div class="mb-4">
                    <label class="block text-gray-700">Card Number</label>
                    <input type="text" name="card_number" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
                </div>
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-gray-700">Expiry Date</label>
                        <input type="text" name="expiry" placeholder="MM/YY" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
                    </div>
                    <div>
                        <label class="block text-gray-700">CVV</label>
                        <input type="text" name="cvv" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
                    </div>
                </div>
                <button type="submit" class="w-full bg-blue-500 text-white px-4 py-2 rounded">Pay Now</button>
            </form>
        </div>
    </div>
</body>
</html>