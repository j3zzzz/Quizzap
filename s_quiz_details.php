<?php
session_start();

// Check if account_number is set in session
if (!isset($_SESSION['account_number'])) {
    echo json_encode(["error" => "Not logged in"]);
    exit();
}

$quiz_id = $_GET['quiz_id'] ?? 0;
$account_number = $_SESSION['account_number'];

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "rawrit";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die(json_encode(["error" => "Connection failed: " . $conn->connect_error]));
}

// Fetch quiz details - removed max_attempts from query
$sql = "SELECT title, quiz_type, timer, start_date, end_date, 
        (SELECT COUNT(*) FROM questions WHERE quiz_id = ?) AS num_of_questions 
        FROM quizzes WHERE quiz_id = ?";
$stmt = $conn->prepare($sql);

// Check if prepare was successful
if (!$stmt) {
    die(json_encode(["error" => "Prepare failed: " . $conn->error]));
}

$stmt->bind_param("ii", $quiz_id, $quiz_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $quiz = $result->fetch_assoc();
    
    // Check if student has already taken this quiz
    $attempt_sql = "SELECT attempt_id FROM quiz_attempts 
                   WHERE quiz_id = ? AND account_number = ? 
                   LIMIT 1";
    $attempt_stmt = $conn->prepare($attempt_sql);
    
    if ($attempt_stmt) {
        $attempt_stmt->bind_param("is", $quiz_id, $account_number);
        $attempt_stmt->execute();
        $attempt_result = $attempt_stmt->get_result();
        
        // Add flag indicating if quiz has been taken
        $quiz['already_taken'] = ($attempt_result->num_rows > 0);
        $attempt_stmt->close();
    }
    
    echo json_encode($quiz);
} else {
    echo json_encode(["error" => "Quiz not found."]);
}

$stmt->close();
$conn->close();
?>