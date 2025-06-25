<?php
session_start();
$quiz_id = $_GET['quiz_id'];
$account_number = $_SESSION['account_number'];

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "rawrit";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch quiz details
$sql = "SELECT title, quiz_type, timer, max_attempts, start_date, end_date, 
        (SELECT COUNT(*) FROM questions WHERE quiz_id = ?) AS num_of_questions 
        FROM quizzes WHERE quiz_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $quiz_id, $quiz_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $quiz = $result->fetch_assoc();
    
    // Get number of attempts already used by this student
    $attempts_sql = "SELECT COUNT(*) AS attempts_used 
                     FROM quiz_attempts 
                     WHERE quiz_id = ? AND account_number = ?";
    $attempts_stmt = $conn->prepare($attempts_sql);
    $attempts_stmt->bind_param("is", $quiz_id, $account_number);
    $attempts_stmt->execute();
    $attempts_result = $attempts_stmt->get_result();
    $attempts_data = $attempts_result->fetch_assoc();
    
    $quiz['attempts_remaining'] = $quiz['max_attempts'] - $attempts_data['attempts_used'];
    $quiz['attempts_remaining'] = max(0, $quiz['attempts_remaining']); // Ensure it doesn't go negative
    
    echo json_encode($quiz);
} else {
    echo json_encode(["error" => "Quiz not found."]);
}

$stmt->close();
$conn->close();
?>