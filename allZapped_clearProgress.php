<?php
session_start();
$servername = "localhost";
$username = "root"; 
$password = "";
$dbname = "rawrit";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit();
}
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($_SESSION['account_number']) || !isset($data['attempt_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$attempt_id = $data['attempt_id'];

try {
    // Mark attempt as completed
    $stmt = $conn->prepare("UPDATE quiz_attempts SET completed = 1 WHERE attempt_id = ?");
    $stmt->bind_param("i", $attempt_id);
    $stmt->execute();
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}