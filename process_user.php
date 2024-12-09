<?php
include 'includes/db.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $conn->begin_transaction();
    
    try {
        switch ($_POST['action']) {
            case 'add':
                // Validate email uniqueness
                $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
                $stmt->bind_param("s", $_POST['email']);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    throw new Exception("Email already exists");
                }
                
                // Hash password
                $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                
                // Insert new user
                $stmt = $conn->prepare("
                    INSERT INTO users (first_name, last_name, email, phone_number, password, role) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("ssssss", 
                    $_POST['first_name'],
                    $_POST['last_name'],
                    $_POST['email'],
                    $_POST['phone_number'],
                    $hashed_password,
                    $_POST['role']
                );
                $stmt->execute();
                
                $_SESSION['success_message'] = "User added successfully";
                break;

            case 'edit':
                // Check if email exists for other users
                $stmt = $conn->prepare("
                    SELECT user_id FROM users 
                    WHERE email = ? AND user_id != ?
                ");
                $stmt->bind_param("si", $_POST['email'], $_POST['user_id']);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    throw new Exception("Email already exists");
                }
                
                // Update user
                $sql = "UPDATE users SET 
                        first_name = ?, 
                        last_name = ?, 
                        email = ?, 
                        phone_number = ?, 
                        role = ?";
                
                // Add password to update if provided
                $params = [$_POST['first_name'], 
                          $_POST['last_name'], 
                          $_POST['email'], 
                          $_POST['phone_number'], 
                          $_POST['role']];
                $types = "sssss";
                
                if (!empty($_POST['password'])) {
                    $sql .= ", password = ?";
                    $params[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $types .= "s";
                }
                
                $sql .= " WHERE user_id = ?";
                $params[] = $_POST['user_id'];
                $types .= "i";
                
                $stmt = $conn->prepare($sql);
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                
                $_SESSION['success_message'] = "User updated successfully";
                break;

            case 'delete':
                // Check if user has any appointments
                $stmt = $conn->prepare("
                    SELECT COUNT(*) as count 
                    FROM appointments 
                    WHERE user_id = ?
                ");
                $stmt->bind_param("i", $_POST['user_id']);
                $stmt->execute();
                $result = $stmt->get_result()->fetch_assoc();
                
                if ($result['count'] > 0) {
                    throw new Exception("Cannot delete user with existing appointments");
                }
                
                // Delete user
                $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
                $stmt->bind_param("i", $_POST['user_id']);
                $stmt->execute();
                
                $_SESSION['success_message'] = "User deleted successfully";
                break;
                
            default:
                throw new Exception("Invalid action");
        }
        
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = $e->getMessage();
    }
}

header('Location: manage_users.php');
exit();