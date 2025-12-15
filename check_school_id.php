<?php
session_start();
header('Content-Type: application/json');

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "rawrit";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode(['valid' => false, 'message' => 'Database connection error']);
    exit();
}

if (isset($_POST['school_id']) && !empty($_POST['school_id'])) {
    $school_id = mysqli_real_escape_string($conn, $_POST['school_id']);
    
    // Check if school ID exists in teachers table
    $sql = "SELECT school_id FROM teachers WHERE school_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $school_id);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows > 0) {
        echo json_encode(['valid' => true]);
    } else {
        echo json_encode(['valid' => false, 'message' => 'Invalid School ID. This ID does not match any registered teacher.']);
    }
    
    $stmt->close();
} else {
    echo json_encode(['valid' => false, 'message' => 'Please enter a School ID']);
}

$conn->close();
?>