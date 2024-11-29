<?php
include 'includes/db.php';
session_start();

// Check if admin
if ($_SESSION['role'] != 'admin') {
    header('Location: index.php');
    exit();
}

$sql = "SELECT p.*, u.first_name, u.last_name 
        FROM payments p 
        JOIN users u ON p.user_id = u.user_id 
        ORDER BY p.payment_date DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Logs</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto p-4">
        <h1 class="text-2xl font-bold mb-4">Payment Logs</h1>
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="min-w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php while($payment = $result->fetch_assoc()): ?>
                        <tr>
                            <td class="px-6 py-4"><?php echo $payment['payment_id']; ?></td>
                            <td class="px-6 py-4"><?php echo $payment['first_name'] . ' ' . $payment['last_name']; ?></td>
                            <td class="px-6 py-4">$<?php echo $payment['amount']; ?></td>
                            <td class="px-6 py-4">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    <?php echo $payment['status'] === 'completed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                    <?php echo ucfirst($payment['status']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4"><?php echo date('M d, Y H:i', strtotime($payment['payment_date'])); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>