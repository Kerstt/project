<?php
include 'includes/db.php';
session_start();

// Check if user is admin
if ($_SESSION['role'] != 'admin') {
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $conn->begin_transaction();
    try {
        switch($_POST['action']) {
            case 'add':
                $name = $_POST['name'];
                $description = $_POST['description'];
                $price = $_POST['price'];
                
                $sql = "INSERT INTO services (name, description, price) VALUES (?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssd", $name, $description, $price);
                $stmt->execute();
                $_SESSION['success_message'] = "Service added successfully";
                break;

            case 'edit':
                $service_id = $_POST['service_id'];
                $name = $_POST['name'];
                $description = $_POST['description'];
                $price = $_POST['price'];
                
                $sql = "UPDATE services SET name = ?, description = ?, price = ? WHERE service_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssdi", $name, $description, $price, $service_id);
                $stmt->execute();
                
                // Update related appointments with new price
                $sql = "UPDATE appointments a 
                       JOIN services s ON a.service_id = s.service_id 
                       SET a.updated_at = NOW() 
                       WHERE s.service_id = ? AND a.status = 'pending'";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $service_id);
                $stmt->execute();
                
                $_SESSION['success_message'] = "Service updated successfully";
                break;

            case 'delete':
                $service_id = $_POST['service_id'];
                
                // Check if service is used in any appointments
                $check_sql = "SELECT COUNT(*) as count FROM appointments WHERE service_id = ?";
                $stmt = $conn->prepare($check_sql);
                $stmt->bind_param("i", $service_id);
                $stmt->execute();
                $result = $stmt->get_result()->fetch_assoc();
                
                if ($result['count'] > 0) {
                    throw new Exception("Cannot delete service as it is used in appointments");
                }
                
                $sql = "DELETE FROM services WHERE service_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $service_id);
                $stmt->execute();
                $_SESSION['success_message'] = "Service deleted successfully";
                break;

            case 'add_package':
                $name = $_POST['name'];
                $description = $_POST['description'];
                $price = $_POST['price'];
                $duration = $_POST['duration'];
                $services = $_POST['included_services'];
                
                $sql = "INSERT INTO service_packages (name, description, price, duration_minutes, included_services, is_active) 
                        VALUES (?, ?, ?, ?, ?, 1)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssdis", $name, $description, $price, $duration, $services);
                $stmt->execute();
                $_SESSION['success_message'] = "Package added successfully";
                break;

            case 'edit_package':
                $package_id = $_POST['package_id'];
                $name = $_POST['name'];
                $description = $_POST['description'];
                $price = $_POST['price'];
                $duration = $_POST['duration'];
                $services = $_POST['included_services'];
                
                $sql = "UPDATE service_packages 
                        SET name = ?, description = ?, price = ?, duration_minutes = ?, included_services = ? 
                        WHERE package_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssdisi", $name, $description, $price, $duration, $services, $package_id);
                $stmt->execute();
                $_SESSION['success_message'] = "Package updated successfully";
                break;

            case 'delete_package':
                $package_id = $_POST['package_id'];
                
                $conn->begin_transaction();
                try {
                    // Check if package is used in any appointments
                    $check_sql = "SELECT COUNT(*) as count FROM appointments WHERE package_id = ?";
                    $stmt = $conn->prepare($check_sql);
                    $stmt->bind_param("i", $package_id);
                    $stmt->execute();
                    $result = $stmt->get_result()->fetch_assoc();
                    
                    if ($result['count'] > 0) {
                        throw new Exception("Cannot delete package as it is being used in appointments");
                    }
                    
                    // Soft delete the package
                    $sql = "UPDATE service_packages SET is_active = 0 WHERE package_id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("i", $package_id);
                    $stmt->execute();
                    
                    $conn->commit();
                    $_SESSION['success_message'] = "Package deleted successfully";
                } catch (Exception $e) {
                    $conn->rollback();
                    $_SESSION['error_message'] = $e->getMessage();
                }
                break;
        }
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = $e->getMessage();
    }
    header('Location: manage_services.php');
    exit();
}

$sql = "SELECT * FROM services";
$result = $conn->query($sql);

// Fetch service packages
$packages_sql = "SELECT * FROM service_packages WHERE is_active = 1";
$packages = $conn->query($packages_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Services - AutoBots</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto p-4">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">Manage Services</h1>
            <button onclick="openAddModal()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                <i class="fas fa-plus mr-2"></i>Add New Service
            </button>
        </div>

        <!-- Tab Navigation -->
        <div class="flex space-x-4 mb-6">
            <button onclick="switchTab('services')" id="servicesTab" 
                    class="px-4 py-2 font-medium border-b-2 border-blue-600">
                Services
            </button>
            <button onclick="switchTab('packages')" id="packagesTab" 
                    class="px-4 py-2 font-medium border-b-2 border-transparent">
                Service Packages
            </button>
        </div>

        <!-- Messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
                <?php 
                    echo $_SESSION['success_message'];
                    unset($_SESSION['success_message']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                <?php 
                    echo $_SESSION['error_message'];
                    unset($_SESSION['error_message']);
                ?>
            </div>
        <?php endif; ?>

        <!-- Services Content -->
        <div id="servicesContent" class="bg-white rounded-lg shadow-md overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Price</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php while($service = $result->fetch_assoc()): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($service['name']); ?></td>
                            <td class="px-6 py-4"><?php echo htmlspecialchars($service['description']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap">$<?php echo number_format($service['price'], 2); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($service)); ?>)"
                                        class="text-blue-600 hover:text-blue-900 mr-3">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button onclick="confirmDelete(<?php echo $service['service_id']; ?>)"
                                        class="text-red-600 hover:text-red-900">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- Packages Content -->
        <div id="packagesContent" class="hidden">
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Price</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Duration</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Services</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
    <?php while($package = $packages->fetch_assoc()): ?>
        <tr>
            <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($package['name']); ?></td>
            <td class="px-6 py-4"><?php echo htmlspecialchars($package['description']); ?></td>
            <td class="px-6 py-4 whitespace-nowrap">$<?php echo number_format($package['price'], 2); ?></td>
            <td class="px-6 py-4 whitespace-nowrap"><?php echo $package['duration_minutes']; ?> mins</td>
            <td class="px-6 py-4">
                <ul class="list-disc list-inside">
                    <?php 
                    $services = explode(',', $package['included_services']);
                    foreach($services as $service): ?>
                        <li class="text-sm text-gray-600"><?php echo htmlspecialchars(trim($service)); ?></li>
                    <?php endforeach; ?>
                </ul>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                <button onclick='openEditPackageModal(<?php echo json_encode($package); ?>)'
                        class="text-blue-600 hover:text-blue-900 mr-3">
                    <i class="fas fa-edit"></i> Edit
                </button>
                <button onclick="confirmDeletePackage(<?php echo $package['package_id']; ?>)"
                        class="text-red-600 hover:text-red-900">
                    <i class="fas fa-trash"></i> Delete
                </button>
            </td>
        </tr>
    <?php endwhile; ?>
</tbody>
                </table>
            </div>

            <!-- Add Package Button -->
            <div class="mt-4">
                <button onclick="openAddPackageModal()" 
                        class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                    <i class="fas fa-plus mr-2"></i>Add New Package
                </button>
            </div>
        </div>
    </div>

    <!-- Add/Edit Service Modal -->
    <div id="serviceModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4" id="modalTitle">Add New Service</h3>
                <form id="serviceForm" method="POST">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="service_id" id="serviceId">
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Service Name</label>
                        <input type="text" name="name" id="serviceName" required
                               class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Description</label>
                        <textarea name="description" id="serviceDescription" required
                                  class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"></textarea>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Price</label>
                        <input type="number" name="price" id="servicePrice" step="0.01" required
                               class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeModal()"
                                class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400">
                            Cancel
                        </button>
                        <button type="submit"
                                class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                            Save
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add/Edit Package Modal -->
    <div id="packageModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4" id="packageModalTitle">Add New Package</h3>
                <form id="packageForm" method="POST">
                    <input type="hidden" name="action" id="packageFormAction" value="add_package">
                    <input type="hidden" name="package_id" id="packageId">
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Package Name</label>
                        <input type="text" name="name" id="packageName" required
                               class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Description</label>
                        <textarea name="description" id="packageDescription" required
                                  class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700"></textarea>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Price</label>
                        <input type="number" name="price" id="packagePrice" step="0.01" required
                               class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Duration (minutes)</label>
                        <input type="number" name="duration" id="packageDuration" required
                               class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Included Services</label>
                        <textarea name="included_services" id="packageServices" required
                                  placeholder="Enter services separated by commas"
                                  class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700"></textarea>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closePackageModal()"
                                class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400">
                            Cancel
                        </button>
                        <button type="submit"
                                class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                            Save
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3 text-center">
                <h3 class="text-lg font-medium text-gray-900">Delete Service</h3>
                <div class="mt-2 px-7 py-3">
                    <p class="text-gray-500">Are you sure you want to delete this service?</p>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="service_id" id="deleteServiceId">
                    <div class="flex justify-center space-x-4 mt-4">
                        <button type="button" onclick="closeDeleteModal()"
                                class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400">
                            Cancel
                        </button>
                        <button type="submit"
                                class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">
                            Delete
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    function openAddModal() {
        document.getElementById('modalTitle').textContent = 'Add New Service';
        document.getElementById('formAction').value = 'add';
        document.getElementById('serviceId').value = '';
        document.getElementById('serviceName').value = '';
        document.getElementById('serviceDescription').value = '';
        document.getElementById('servicePrice').value = '';
        document.getElementById('serviceModal').classList.remove('hidden');
    }

    function openEditModal(service) {
        document.getElementById('modalTitle').textContent = 'Edit Service';
        document.getElementById('formAction').value = 'edit';
        document.getElementById('serviceId').value = service.service_id;
        document.getElementById('serviceName').value = service.name;
        document.getElementById('serviceDescription').value = service.description;
        document.getElementById('servicePrice').value = service.price;
        document.getElementById('serviceModal').classList.remove('hidden');
    }

    function closeModal() {
        document.getElementById('serviceModal').classList.add('hidden');
    }

    function confirmDelete(serviceId) {
        document.getElementById('deleteServiceId').value = serviceId;
        document.getElementById('deleteModal').classList.remove('hidden');
    }

    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.add('hidden');
    }

    // Close modals when clicking outside
    window.onclick = function(event) {
        const serviceModal = document.getElementById('serviceModal');
        const deleteModal = document.getElementById('deleteModal');
        if (event.target === serviceModal) {
            closeModal();
        }
        if (event.target === deleteModal) {
            closeDeleteModal();
        }
    }

    function switchTab(tabName) {
        // Update tab buttons
        document.getElementById('servicesTab').classList.remove('border-blue-600');
        document.getElementById('packagesTab').classList.remove('border-blue-600');
        document.getElementById(tabName + 'Tab').classList.add('border-blue-600');

        // Update content visibility
        document.getElementById('servicesContent').classList.add('hidden');
        document.getElementById('packagesContent').classList.add('hidden');
        document.getElementById(tabName + 'Content').classList.remove('hidden');
    }

    // Add these JavaScript functions
    function openAddPackageModal() {
        document.getElementById('packageModalTitle').textContent = 'Add New Package';
        document.getElementById('packageFormAction').value = 'add_package';
        document.getElementById('packageId').value = '';
        document.getElementById('packageName').value = '';
        document.getElementById('packageDescription').value = '';
        document.getElementById('packagePrice').value = '';
        document.getElementById('packageDuration').value = '';
        document.getElementById('packageServices').value = '';
        document.getElementById('packageModal').classList.remove('hidden');
    }

    function openEditPackageModal(package) {
        document.getElementById('packageModalTitle').textContent = 'Edit Package';
        document.getElementById('packageFormAction').value = 'edit_package';
        document.getElementById('packageId').value = package.package_id;
        document.getElementById('packageName').value = package.name;
        document.getElementById('packageDescription').value = package.description;
        document.getElementById('packagePrice').value = package.price;
        document.getElementById('packageDuration').value = package.duration_minutes;
        document.getElementById('packageServices').value = package.included_services;
        document.getElementById('packageModal').classList.remove('hidden');
    }

    function closePackageModal() {
        document.getElementById('packageModal').classList.add('hidden');
    }

    function confirmDeletePackage(packageId) {
        if(confirm('Are you sure you want to delete this package?')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete_package">
                <input type="hidden" name="package_id" value="${packageId}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }
    </script>
</body>
</html>