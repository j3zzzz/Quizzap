<?php
// get_rankings.php
session_start();
header('Content-Type: application/json');

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "rawrit";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die(json_encode(['error' => "Connection failed: " . $conn->connect_error]));
}

$quiz_id = isset($_GET['quiz_id']) ? (int)$_GET['quiz_id'] : 0;

if (!$quiz_id) {
    die(json_encode(['error' => 'Invalid quiz ID']));
}

$stmt = $conn->prepare("
    SELECT 
        CONCAT(u.fname, ' ', u.lname) AS Name,
        qa.score,
        qa.attempt_time
    FROM quiz_attempts qa
    JOIN students u ON qa.account_number = u.account_number
    JOIN (
        SELECT account_number, MAX(score) as max_score 
        FROM quiz_attempts 
        WHERE quiz_id = ?
        GROUP BY account_number
    ) max_scores ON qa.account_number = max_scores.account_number
        AND qa.score = max_scores.max_score
    WHERE qa.quiz_id = ?
    GROUP BY qa.account_number
    ORDER BY qa.score DESC, qa.attempt_time DESC");

$stmt->bind_param("ii", $quiz_id, $quiz_id);
$stmt->execute();
$result = $stmt->get_result();

$rankings = [];
while ($row = $result->fetch_assoc()) {
    $rankings[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode($rankings);
?>