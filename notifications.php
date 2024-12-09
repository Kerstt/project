<?php
include 'includes/db.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Add this function at the top of the file with other PHP code
function getStatusColor($status) {
    switch($status) {
        case 'pending':
            return 'bg-yellow-100 text-yellow-800';
        case 'confirmed':
            return 'bg-blue-100 text-blue-800';
        case 'in-progress':
            return 'bg-indigo-100 text-indigo-800';
        case 'completed':
            return 'bg-green-100 text-green-800';
        case 'cancelled':
            return 'bg-red-100 text-red-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

// Add pagination variables
$records_per_page = 5;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Fetch admin data
$user_sql = "SELECT * FROM users WHERE user_id = ? AND role = 'admin'";
$stmt = $conn->prepare($user_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();

// Add search and filter conditions to SQL query
$sql = "SELECT n.*, 
        a.appointment_date, 
        a.status as appointment_status,
        COALESCE(s.name, sp.name) as service_name,
        u.first_name as customer_first_name, 
        u.last_name as customer_last_name,
        u.email as customer_email
        FROM notifications n
        LEFT JOIN appointments a ON n.appointment_id = a.appointment_id
        LEFT JOIN users u ON a.user_id = u.user_id
        LEFT JOIN services s ON a.service_id = s.service_id
        LEFT JOIN service_packages sp ON a.package_id = sp.package_id
        WHERE 1=1";

$params = [];
$types = "";

// Add search condition
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = "%" . $_GET['search'] . "%";
    $sql .= " AND (n.message LIKE ? 
              OR CONCAT(u.first_name, ' ', u.last_name) LIKE ?
              OR COALESCE(s.name, sp.name) LIKE ?)";
    $params = array_merge($params, [$search, $search, $search]);
    $types .= "sss";
}

// Add type filter
if (isset($_GET['type']) && !empty($_GET['type'])) {
    $sql .= " AND n.type = ?";
    $params[] = $_GET['type'];
    $types .= "s";
}

// Add status filter
if (isset($_GET['status']) && !empty($_GET['status'])) {
    $sql .= " AND a.status = ?";
    $params[] = $_GET['status'];
    $types .= "s";
}

// Add pagination parameters
$sql .= " ORDER BY n.sent_at DESC LIMIT ? OFFSET ?";
$params[] = $records_per_page;
$params[] = $offset;
$types .= "ii";

// Update total count query with filters
$total_sql = "SELECT COUNT(*) as total 
              FROM notifications n
              LEFT JOIN appointments a ON n.appointment_id = a.appointment_id
              LEFT JOIN users u ON a.user_id = u.user_id
              LEFT JOIN services s ON a.service_id = s.service_id
              LEFT JOIN service_packages sp ON a.package_id = sp.package_id
              WHERE 1=1";

// Add the same conditions to total count query (without LIMIT and OFFSET)
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $total_sql .= " AND (n.message LIKE ? 
                   OR CONCAT(u.first_name, ' ', u.last_name) LIKE ?
                   OR COALESCE(s.name, sp.name) LIKE ?)";
}
if (isset($_GET['type']) && !empty($_GET['type'])) {
    $total_sql .= " AND n.type = ?";
}
if (isset($_GET['status']) && !empty($_GET['status'])) {
    $total_sql .= " AND a.status = ?";
}

// Prepare and execute the total count query
$stmt = $conn->prepare($total_sql);
if (!empty($params)) {
    // Remove the last two parameters (LIMIT and OFFSET)
    $temp_params = array_slice($params, 0, -2);
    $temp_types = substr($types, 0, -2);
    if (!empty($temp_params)) {
        $stmt->bind_param($temp_types, ...$temp_params);
    }
}
$stmt->execute();
$total_result = $stmt->get_result();
$total_notifications = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_notifications / $records_per_page);

// Prepare and execute the main query
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$notifications = $stmt->get_result();

// Update pagination links to include search and filter parameters
function buildPaginationUrl($page) {
    $params = $_GET;
    $params['page'] = $page;
    return '?' . http_build_query($params);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - AutoBots Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        .nav-link.active { color: #2563eb; border-bottom: 2px solid #2563eb; }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-gray-50" x-data="{ showLogoutModal: false }">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="index.php" class="flex items-center space-x-2">
                        <i class="fas fa-car text-blue-600 text-2xl"></i>
                        <span class="text-xl font-bold">AutoBots Admin</span>
                    </a>
                </div>
                
                <div class="hidden md:flex items-center space-x-4">
                    <a href="admin_dashboard.php" class="nav-link px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:text-blue-600">
                        <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
                    </a>
                    <a href="manage_users.php" class="nav-link px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:text-blue-600">
                        <i class="fas fa-users mr-2"></i>Users
                    </a>
                    <a href="manage_appointments.php" class="nav-link px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:text-blue-600">
                        <i class="fas fa-calendar-alt mr-2"></i>Appointments
                    </a>
                    <a href="manage_services.php" class="nav-link px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:text-blue-600">
                        <i class="fas fa-wrench mr-2"></i>Services
                    </a>
                    <a href="notifications.php" class="nav-link active px-3 py-2 rounded-md text-sm font-medium text-blue-600">
                        <i class="fas fa-bell mr-2"></i>Notifications
                    </a>
                    
                    <!-- Profile Dropdown -->
                    <div class="relative" x-data="{ profileOpen: false }">
                        <button @click="profileOpen = !profileOpen" 
                                class="flex items-center space-x-3 text-gray-600 hover:text-blue-600 focus:outline-none">
                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user_data['first_name'] . ' ' . $user_data['last_name']); ?>&background=2563eb&color=fff" 
                                 class="h-8 w-8 rounded-full">
                            <span class="text-sm font-medium"><?php echo htmlspecialchars($user_data['first_name']); ?></span>
                            <svg class="w-4 h-4" :class="{'rotate-180': profileOpen}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>

                        <div x-show="profileOpen"
                             x-cloak
                             @click.away="profileOpen = false"
                             class="absolute right-0 mt-2 w-48 rounded-md shadow-lg py-1 bg-white ring-1 ring-black ring-opacity-5 z-50">
                            <div class="px-4 py-2 border-b">
                                <p class="text-sm text-gray-700 font-medium">
                                    <?php echo htmlspecialchars($user_data['first_name'] . ' ' . $user_data['last_name']); ?>
                                </p>
                                <p class="text-xs text-gray-500"><?php echo htmlspecialchars($user_data['email']); ?></p>
                            </div>
                            
                            <a href="profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-user-circle mr-2"></i>My Profile
                            </a>
                            <button @click="showLogoutModal = true; profileOpen = false" 
                                    class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-gray-100">
                                <i class="fas fa-sign-out-alt mr-2"></i>Logout
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->

        <!-- Replace the existing table section with this -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <!-- Table Header with Search and Filters -->
            <div class="p-5 border-b border-gray-200 flex justify-between items-center flex-wrap gap-4">
                <h2 class="text-xl font-semibold text-gray-800">
                    <i class="fas fa-bell mr-2 text-blue-600"></i>
                    Notifications List
                </h2>
                
                <form method="GET" class="flex gap-4" id="searchFilterForm">
                    <div class="relative">
                        <input type="text" 
                               name="search" 
                               id="searchInput"
                               value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>"
                               placeholder="Search notifications..."
                               class="pl-10 pr-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    </div>
                    
                    <select name="type" 
                            id="typeFilter" 
                            class="border rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            onchange="this.form.submit()">
                        <option value="">All Types</option>
                        <option value="email" <?php echo isset($_GET['type']) && $_GET['type'] == 'email' ? 'selected' : ''; ?>>Email</option>
                        <option value="sms" <?php echo isset($_GET['type']) && $_GET['type'] == 'sms' ? 'selected' : ''; ?>>SMS</option>
                    </select>

                    <select name="status" 
                            id="statusFilter" 
                            class="border rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            onchange="this.form.submit()">
                        <option value="">All Statuses</option>
                        <option value="pending" <?php echo isset($_GET['status']) && $_GET['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="in-progress" <?php echo isset($_GET['status']) && $_GET['status'] == 'in-progress' ? 'selected' : ''; ?>>In Progress</option>
                        <option value="completed" <?php echo isset($_GET['status']) && $_GET['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo isset($_GET['status']) && $_GET['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </form>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Date/Time
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Type
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Message
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Customer
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Service
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Status
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if ($notifications->num_rows > 0): ?>
                            <?php while($notification = $notifications->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50 transition-colors duration-150">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900 font-medium">
                                            <?php echo date('M d, Y', strtotime($notification['sent_at'])); ?>
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            <?php echo date('h:i A', strtotime($notification['sent_at'])); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-3 py-1 inline-flex items-center text-xs leading-4 font-medium rounded-full 
                                            <?php echo $notification['type'] === 'email' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'; ?>">
                                            <i class="<?php echo $notification['type'] === 'email' ? 'fas fa-envelope' : 'fas fa-comment'; ?> mr-1"></i>
                                            <?php echo ucfirst($notification['type']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900 max-w-xs truncate" title="<?php echo htmlspecialchars($notification['message']); ?>">
                                            <?php echo htmlspecialchars($notification['message']); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($notification['customer_first_name']): ?>
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-8 w-8">
                                                    <img class="h-8 w-8 rounded-full" 
                                                         src="https://ui-avatars.com/api/?name=<?php echo urlencode($notification['customer_first_name'] . ' ' . $notification['customer_last_name']); ?>&background=2563eb&color=fff" 
                                                         alt="">
                                                </div>
                                                <div class="ml-3">
                                                    <div class="text-sm font-medium text-gray-900">
                                                        <?php echo htmlspecialchars($notification['customer_first_name'] . ' ' . $notification['customer_last_name']); ?>
                                                    </div>
                                                    <div class="text-xs text-gray-500">
                                                        <?php echo htmlspecialchars($notification['customer_email']); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            <?php echo htmlspecialchars($notification['service_name'] ?? 'N/A'); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($notification['appointment_status']): ?>
                                            <span class="px-3 py-1 inline-flex text-xs leading-5 font-medium rounded-full <?php echo getStatusColor($notification['appointment_status']); ?>">
                                                <?php echo ucfirst($notification['appointment_status']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <?php if ($notification['appointment_id']): ?>
                                            <a href="manage_appointments.php?id=<?php echo $notification['appointment_id']; ?>" 
                                               class="text-blue-600 hover:text-blue-900 transition-colors duration-150">
                                                <i class="fas fa-external-link-alt mr-1"></i>
                                                View
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                                    <div class="flex flex-col items-center py-8">
                                        <i class="fas fa-bell-slash text-4xl text-gray-400 mb-4"></i>
                                        <p class="text-lg">No notifications found</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Replace the existing pagination section -->
            <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                <div class="flex-1 flex justify-between sm:hidden">
                    <?php if($page > 1): ?>
                        <a href="<?php echo buildPaginationUrl($page-1); ?>" 
                           class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            Previous
                        </a>
                    <?php endif; ?>
                    <?php if($page < $total_pages): ?>
                        <a href="<?php echo buildPaginationUrl($page+1); ?>" 
                           class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            Next
                        </a>
                    <?php endif; ?>
                </div>
                <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-gray-700">
                            Showing 
                            <span class="font-medium"><?php echo ($offset + 1); ?></span> 
                            to 
                            <span class="font-medium">
                                <?php echo min($offset + $records_per_page, $total_notifications); ?>
                            </span> 
                            of 
                            <span class="font-medium"><?php echo $total_notifications; ?></span> 
                            notifications
                        </p>
                    </div>
                    <div>
                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                            <!-- Update pagination links -->
                            <?php if($page > 1): ?>
                                <a href="<?php echo buildPaginationUrl($page-1); ?>" 
                                   class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                    <span class="sr-only">Previous</span>
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            <?php endif; ?>

                            <?php for($i = 1; $i <= $total_pages; $i++): ?>
                                <a href="<?php echo buildPaginationUrl($i); ?>" 
                                   class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium 
                                          <?php echo $page === $i ? 'text-blue-600 bg-blue-50' : 'text-gray-700 hover:bg-gray-50'; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>

                            <?php if($page < $total_pages): ?>
                                <a href="<?php echo buildPaginationUrl($page+1); ?>" 
                                   class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                    <span class="sr-only">Next</span>
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Logout Modal -->
    <div x-show="showLogoutModal"
         x-cloak
         class="fixed inset-0 z-50 overflow-y-auto"
         aria-labelledby="modal-title" 
         role="dialog" 
         aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <!-- Background overlay -->
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" 
                 @click="showLogoutModal = false"></div>

            <!-- Modal panel -->
            <div class="relative inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                            <i class="fas fa-sign-out-alt text-red-600"></i>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                            <h3 class="text-lg leading-6 font-medium text-gray-900">Confirm Logout</h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500">Are you sure you want to logout?</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <a href="logout.php" 
                       class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                        Confirm Logout
                    </a>
                    <button type="button" 
                            @click="showLogoutModal = false"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add JavaScript for filtering -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('searchNotification');
        const typeFilter = document.getElementById('typeFilter');
        const statusFilter = document.getElementById('statusFilter');
        const notifications = document.querySelectorAll('.notification-item');

        function filterNotifications() {
            const searchTerm = searchInput.value.toLowerCase();
            const typeValue = typeFilter.value;
            const statusValue = statusFilter.value;

            notifications.forEach(notification => {
                const text = notification.textContent.toLowerCase();
                const type = notification.dataset.type;
                const status = notification.dataset.status;
                
                const matchesSearch = text.includes(searchTerm);
                const matchesType = !typeValue || type === typeValue;
                const matchesStatus = !statusValue || status === statusValue;
                
                notification.style.display = 
                    matchesSearch && matchesType && matchesStatus ? 'block' : 'none';
            });
        }

        searchInput.addEventListener('input', filterNotifications);
        typeFilter.addEventListener('change', filterNotifications);
        statusFilter.addEventListener('change', filterNotifications);
    });
    </script>
</body>
</html>