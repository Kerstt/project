<?php
include 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $phone_number = $_POST['phone_number'];

    $sql = "INSERT INTO users (role, first_name, last_name, email, password, phone_number)
            VALUES ('customer', '$first_name', '$last_name', '$email', '$password', '$phone_number')";

    if ($conn->query($sql) === TRUE) {
        echo "New user registered successfully";
    } else {
        echo "Error: " . $sql . "<br>" . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto p-4">
        <h1 class="text-2xl font-bold mb-4">Register</h1>
        <form method="post" action="">
            <div class="mb-4">
                <label for="first_name" class="block text-gray-700">First Name:</label>
                <input type="text" id="first_name" name="first_name" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
            </div>
            <div class="mb-4">
                <label for="last_name" class="block text-gray-700">Last Name:</label>
                <input type="text" id="last_name" name="last_name" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
            </div>
            <div class="mb-4">
                <label for="email" class="block text-gray-700">Email:</label>
                <input type="email" id="email" name="email" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
            </div>
            <div class="mb-4">
                <label for="password" class="block text-gray-700">Password:</label>
                <input type="password" id="password" name="password" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
            </div>
            <div class="mb-4">
                <label for="phone_number" class="block text-gray-700">Phone Number:</label>
                <input type="text" id="phone_number" name="phone_number" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
            </div>
            <div>
                <input type="submit" value="Register" class="bg-blue-500 text-white py-2 px-4 rounded">
            </div>
        </form>
    </div>
</body>
</html>