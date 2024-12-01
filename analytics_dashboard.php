<?php
include 'includes/db.php';
session_start();

if ($_SESSION['role'] != 'admin') {
    header('Location: login.php');
    exit();
}

// Get overall stats
$stats_sql = "SELECT 
    (SELECT COUNT(*) FROM appointments) as total_appointments,
    (SELECT COUNT(*) FROM appointments WHERE status = 'completed') as completed_appointments,
    (SELECT COUNT(*) FROM users WHERE role = 'customer') as total_customers,
    (SELECT SUM(amount) FROM payments WHERE status = 'paid') as total_revenue,
    (SELECT AVG(rating) FROM service_ratings) as avg_rating";
$stats = $conn->query($stats_sql)->fetch_assoc();

// Get monthly revenue data
$revenue_sql = "SELECT 
    DATE_FORMAT(p.payment_date, '%Y-%m') as month,
    COUNT(DISTINCT a.appointment_id) as total_appointments,
    SUM(p.amount) as revenue
    FROM payments p
    JOIN appointments a ON p.appointment_id = a.appointment_id
    WHERE p.status = 'paid'
    GROUP BY month
    ORDER BY month DESC
    LIMIT 12";
$revenue_data = $conn->query($revenue_sql);

// Get service performance
$service_sql = "SELECT 
    s.name,
    COUNT(a.appointment_id) as total_bookings,
    ROUND(AVG(r.rating), 2) as average_rating,
    SUM(p.amount) as revenue
    FROM services s
    LEFT JOIN appointments a ON s.service_id = a.service_id
    LEFT JOIN service_ratings r ON a.appointment_id = r.appointment_id
    LEFT JOIN payments p ON a.appointment_id = p.appointment_id
    GROUP BY s.service_id
    ORDER BY total_bookings DESC";
$service_data = $conn->query($service_sql);

// Get technician performance
$technician_sql = "SELECT 
    u.first_name,
    u.last_name,
    COUNT(a.appointment_id) as completed_services,
    ROUND(AVG(r.rating), 2) as average_rating
    FROM users u
    LEFT JOIN appointments a ON u.user_id = a.technician_id
    LEFT JOIN service_ratings r ON a.appointment_id = r.appointment_id
    WHERE u.role = 'technician'
    GROUP BY u.user_id
    ORDER BY completed_services DESC";
$technician_data = $conn->query($technician_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Analytics Dashboard - AutoBots</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 py-8">
        <h1 class="text-2xl font-bold mb-8">Analytics Dashboard</h1>

        <!-- Stats Overview -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 bg-blue-100 rounded-full">
                        <i class="fas fa-calendar-check text-blue-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-gray-500 text-sm">Total Appointments</p>
                        <h3 class="text-2xl font-bold"><?php echo $stats['total_appointments']; ?></h3>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 bg-green-100 rounded-full">
                        <i class="fas fa-users text-green-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-gray-500 text-sm">Total Customers</p>
                        <h3 class="text-2xl font-bold"><?php echo $stats['total_customers']; ?></h3>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 bg-yellow-100 rounded-full">
                        <i class="fas fa-dollar-sign text-yellow-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-gray-500 text-sm">Total Revenue</p>
                        <h3 class="text-2xl font-bold">$<?php echo number_format($stats['total_revenue'], 2); ?></h3>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 bg-purple-100 rounded-full">
                        <i class="fas fa-star text-purple-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-gray-500 text-sm">Average Rating</p>
                        <h3 class="text-2xl font-bold"><?php echo number_format($stats['avg_rating'], 1); ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Revenue Chart -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold mb-4">Revenue Overview</h2>
                <canvas id="revenueChart"></canvas>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold mb-4">Appointment Trends</h2>
                <canvas id="appointmentChart"></canvas>
            </div>
        </div>

        <!-- Service Performance -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-xl font-semibold mb-4">Service Performance</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Service</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Bookings</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Avg Rating</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Revenue</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php while($service = $service_data->fetch_assoc()): ?>
                            <tr>
                                <td class="px-6 py-4"><?php echo htmlspecialchars($service['name']); ?></td>
                                <td class="px-6 py-4"><?php echo $service['total_bookings']; ?></td>
                                <td class="px-6 py-4">
                                    <?php if($service['average_rating']): ?>
                                        <div class="flex items-center">
                                            <span class="text-yellow-400 mr-1"><i class="fas fa-star"></i></span>
                                            <?php echo $service['average_rating']; ?>
                                        </div>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">$<?php echo number_format($service['revenue'], 2); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Technician Performance -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold mb-4">Technician Performance</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Technician</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Completed Services</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Average Rating</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php while($technician = $technician_data->fetch_assoc()): ?>
                            <tr>
                                <td class="px-6 py-4">
                                    <?php echo htmlspecialchars($technician['first_name'] . ' ' . $technician['last_name']); ?>
                                </td>
                                <td class="px-6 py-4"><?php echo $technician['completed_services']; ?></td>
                                <td class="px-6 py-4">
                                    <?php if($technician['average_rating']): ?>
                                        <div class="flex items-center">
                                            <span class="text-yellow-400 mr-1"><i class="fas fa-star"></i></span>
                                            <?php echo $technician['average_rating']; ?>
                                        </div>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Revenue Chart
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: [<?php 
                    $revenue_data->data_seek(0);
                    while($row = $revenue_data->fetch_assoc()) {
                        echo "'" . $row['month'] . "',";
                    }
                ?>],
                datasets: [{
                    label: 'Monthly Revenue',
                    data: [<?php 
                        $revenue_data->data_seek(0);
                        while($row = $revenue_data->fetch_assoc()) {
                            echo $row['revenue'] . ",";
                        }
                    ?>],
                    borderColor: 'rgb(59, 130, 246)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value;
                            }
                        }
                    }
                }
            }
        });

        // Appointment Chart
        const appointmentCtx = document.getElementById('appointmentChart').getContext('2d');
        new Chart(appointmentCtx, {
            type: 'bar',
            data: {
                labels: [<?php 
                    $revenue_data->data_seek(0);
                    while($row = $revenue_data->fetch_assoc()) {
                        echo "'" . $row['month'] . "',";
                    }
                ?>],
                datasets: [{
                    label: 'Monthly Appointments',
                    data: [<?php 
                        $revenue_data->data_seek(0);
                        while($row = $revenue_data->fetch_assoc()) {
                            echo $row['total_appointments'] . ",";
                        }
                    ?>],
                    backgroundColor: 'rgba(59, 130, 246, 0.5)',
                    borderColor: 'rgb(59, 130, 246)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>