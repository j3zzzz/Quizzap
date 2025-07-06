<?php
session_start();
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "rawrit";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$admin_id = $_POST['admin_id'];
$password = $_POST['password'];

// Validate admin ID format
if (strpos($admin_id, 'A') !== 0) {
    echo "<script>
    alert('Invalid Admin ID format. It must start with A.');
    window.location.href='admin_login.php';
    </script>";
    exit();
}

// Use prepared statement to prevent SQL injection
$stmt = $conn->prepare("SELECT * FROM admins WHERE account_number = ?");
$stmt->bind_param("s", $admin_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $admin = $result->fetch_assoc();
    if (password_verify($password, $admin['password'])) {
        // Set session variables
        $_SESSION['account_type'] = 'admin';
        $_SESSION['account_number'] = $admin_id;
        $_SESSION['fname'] = $admin['fname'];
        
        // Redirect to admin dashboard
        header("Location: a_Home.php");
        exit();
    } else {
        echo "<script>
        alert('Invalid password.');
        window.location.href='admin_login.php';
        </script>";
    }
} else {
    echo "<script>
    alert('Admin account not found.');
    window.location.href='admin_login.php';
    </script>";
}

$stmt->close();
$conn->close();
?>