<?php
$quiz_id = $_GET['quiz_id'];

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

// Fetch quiz details
$sql = "SELECT title, quiz_type, timer, (SELECT COUNT(*) FROM questions WHERE quiz_id = ?) AS num_of_questions FROM quizzes WHERE quiz_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $quiz_id, $quiz_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $quiz = $result->fetch_assoc();
    echo json_encode($quiz);
} else {
    echo json_encode(["error" => "Quiz not found."]);
}

$stmt->close();
$conn->close();
?>
