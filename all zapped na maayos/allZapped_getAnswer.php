<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "rawrit";
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die(json_encode([
        "success" => false, 
        "error" => "Connection failed: " . $conn->connect_error
    ]));
}

// Prepare statement to prevent SQL injection
$stmt = $conn->prepare("SELECT q.question_type, a.* FROM answers a 
                        JOIN questions q ON a.question_id = q.question_id 
                        WHERE a.question_id = ?");
$stmt->bind_param("i", $question_id);

$question_id = $_GET['question_id'];
$stmt->execute();
$result = $stmt->get_result();

$answers = [];
$question_type = null;

while ($row = $result->fetch_assoc()) {
    // Store question type
    $question_type = $row['question_type'];
    
    // Remove question_type from the answer data
    unset($row['question_type']);
    $answers[] = $row;
}

// For matching type, ensure side is added if not present
if ($question_type === 'matching_type') {
    // You might need to modify this based on your exact database structure
    foreach ($answers as &$answer) {
        $answer['side'] = ($answer['is_left'] == 1) ? 'left' : 'right';
    }
}

echo json_encode($answers);

$stmt->close();
$conn->close();
?>