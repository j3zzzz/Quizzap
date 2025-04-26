<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "rawrit";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$question_id = $_GET['question_id'];
$sql = "SELECT * FROM answers WHERE question_id = $question_id";
$result = $conn->query($sql);

$answers = [];
while ($row = $result->fetch_assoc()) {
    $answers[] = $row;
}

echo json_encode($answers);

$conn->close();
?>
