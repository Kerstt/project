<?php
include 'includes/db.php';
session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'technician'])) {
    header('Location: login.php');
    exit();
}

// Handle schedule updates
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $technician_id = $_POST['technician_id'];
    $date = $_POST['date'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $status = $_POST['status'];

    $sql = "INSERT INTO technician_schedules (technician_id, date, start_time, end_time, status)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE status = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isssss", $technician_id, $date, $start_time, $end_time, $status, $status);
    $stmt->execute();
}

// Fetch technicians
$sql = "SELECT user_id, first_name, last_name FROM users WHERE role = 'technician'";
$technicians = $conn->query($sql);

// Fetch schedule for current week
$start_date = date('Y-m-d');
$end_date = date('Y-m-d', strtotime('+7 days'));

$schedule_sql = "SELECT ts.*, u.first_name, u.last_name 
                 FROM technician_schedules ts
                 JOIN users u ON ts.technician_id = u.user_id
                 WHERE ts.date BETWEEN ? AND ?
                 ORDER BY ts.date, ts.start_time";

$stmt = $conn->prepare($schedule_sql);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$schedules = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Technician Schedule - AutoBots</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 py-8">
        <h1 class="text-2xl font-bold mb-8">Technician Schedule</h1>

        <!-- Schedule Grid -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="grid grid-cols-8 gap-px bg-gray-200">
                <div class="bg-gray-50 p-4 font-medium">Technician</div>
                <?php
                for ($i = 0; $i < 7; $i++) {
                    $date = date('M d', strtotime("+$i days"));
                    echo "<div class='bg-gray-50 p-4 font-medium'>$date</div>";
                }
                ?>
            </div>

            <?php while($technician = $technicians->fetch_assoc()): ?>
                <div class="grid grid-cols-8 gap-px bg-gray-200">
                    <div class="bg-white p-4">
                        <?php echo htmlspecialchars($technician['first_name'] . ' ' . $technician['last_name']); ?>
                    </div>
                    <?php
                    for ($i = 0; $i < 7; $i++) {
                        $date = date('Y-m-d', strtotime("+$i days"));
                        $schedule_found = false;
                        
                        $schedules->data_seek(0);
                        while ($schedule = $schedules->fetch_assoc()) {
                            if ($schedule['technician_id'] == $technician['user_id'] && $schedule['date'] == $date) {
                                $schedule_found = true;
                                $status_color = $schedule['status'] == 'available' ? 'green' : ($schedule['status'] == 'booked' ? 'blue' : 'gray');
                                echo "<div class='bg-white p-4'>
                                        <span class='px-2 py-1 rounded-full text-xs bg-{$status_color}-100 text-{$status_color}-800'>
                                            {$schedule['status']}
                                        </span>
                                        <div class='text-sm mt-1'>
                                            {$schedule['start_time']} - {$schedule['end_time']}
                                        </div>
                                    </div>";
                                break;
                            }
                        }
                        
                        if (!$schedule_found) {
                            echo "<div class='bg-white p-4'></div>";
                        }
                    }
                    ?>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
</body>
</html>