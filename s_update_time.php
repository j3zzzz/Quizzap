<?php
session_start();
if (strpos($_SESSION['account_number'], 'S') !== 0) {
    header("Location: login.php");
    exit();
}

$servername = "localhost";
$username = "root"; 
$password = "";
$dbname = "rawrit";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit();
}

// Get JSON input data
$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid input data']);
    exit();
}

$attempt_id = $data['attempt_id'] ?? null;
$time_remaining = $data['time_remaining'] ?? null;

if (!$attempt_id || $time_remaining === null) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
    exit();
}

// Verify the attempt belongs to the current user
$account_number = $_SESSION['account_number'];
$verify_stmt = $conn->prepare("SELECT attempt_id FROM quiz_attempts 
                              WHERE attempt_id = ? AND account_number = ? AND completed = 0");
$verify_stmt->bind_param("is", $attempt_id, $account_number);
$verify_stmt->execute();
$verify_result = $verify_stmt->get_result();

if ($verify_result->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid attempt or already completed']);
    exit();
}
$verify_stmt->close();

// Update time remaining
$update_stmt = $conn->prepare("UPDATE quiz_attempts SET time_remaining = ? WHERE attempt_id = ?");
$update_stmt->bind_param("ii", $time_remaining, $attempt_id);

if ($update_stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to update time remaining']);
}

$update_stmt->close();
$conn->close();
?>