<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "rawrit";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die(json_encode(["error" => "Connection failed: " . $conn->connect_error]));
}

$question_id = $_GET['question_id'];

error_log("=== s_get_answers.php called for question_id: $question_id ===");

// First, get the question to check its type
$question_sql = "SELECT q.question_id, q.quiz_id, q.question_type, q.left_items, q.right_items, qz.quiz_type 
                 FROM questions q 
                 JOIN quizzes qz ON q.quiz_id = qz.quiz_id 
                 WHERE q.question_id = ?";
$stmt = $conn->prepare($question_sql);
$stmt->bind_param("i", $question_id);
$stmt->execute();
$question_result = $stmt->get_result();
$question_data = $question_result->fetch_assoc();
$stmt->close();

error_log("Question data: " . print_r($question_data, true));

// Check if this is a Matching Type quiz
if ($question_data && $question_data['quiz_type'] === 'Matching Type') {
    error_log("Processing Matching Type question");
    error_log("Raw left_items: " . $question_data['left_items']);
    error_log("Raw right_items: " . $question_data['right_items']);
    
    // Parse JSON arrays
    $left_items = json_decode($question_data['left_items'], true);
    $right_items = json_decode($question_data['right_items'], true);
    
    error_log("Parsed left_items: " . print_r($left_items, true));
    error_log("Parsed right_items: " . print_r($right_items, true));
    
    $response = [
        'question_type' => 'Matching Type',
        'left_items' => $left_items ?: [],
        'right_items' => $right_items ?: []
    ];
    
    error_log("Final response: " . json_encode($response));
    
    // Return the matching type data structure
    echo json_encode($response);
} else {
    error_log("Processing non-Matching Type question");
    error_log("Quiz type: " . ($question_data ? $question_data['quiz_type'] : 'N/A'));
    
    // For other question types, fetch answers normally
    $sql = "SELECT * FROM answers WHERE question_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $question_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $answers = [];
    while ($row = $result->fetch_assoc()) {
        $answers[] = $row;
    }
    
    error_log("Answers returned: " . json_encode($answers));
    
    echo json_encode($answers);
    $stmt->close();
}

$conn->close();
?>