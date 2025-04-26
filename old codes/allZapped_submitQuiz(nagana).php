<?php
require_once 'quiz_scoring.php';

session_start();
if (strpos($_SESSION['account_number'], 'S') !== 0) {
    header("Location: login.php");
    exit();
}

$servername = "localhost";
$username = "root"; 
$password = "";
$dbname = "rawrit";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
   echo json_encode(["success" => false, "error" => "Connection failed: " . $conn->connect_error]);
   exit;
}

// Check if student is logged in and fetch account_number
if (!isset($_SESSION['account_number'])) {
    echo json_encode(["success" => false, "error" => "User not logged in or account number missing from session."]);
    exit;
}
$account_number = $_SESSION['account_number'];

// Fetch student_id from the database using account_number
$sql = "SELECT student_id FROM students WHERE account_number = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(["success" => false, "error" => "Failed to prepare statement: " . $conn->error]);
    exit;
}
$stmt->bind_param("s", $account_number);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $student_id = $row['student_id'];
} else {
    echo json_encode(["success" => false, "error" => "Student not found."]);
    exit;
}
$stmt->close();

// Get JSON input data
$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    echo json_encode(["success" => false, "error" => "Invalid input data."]);
    exit;
}

$answers = $data['answers'];
$quiz_id = $data['quiz_id'];

//function para sa enumeration


if (!$answers || !$quiz_id) {
    echo json_encode(["success" => false, "error" => "Answers or quiz ID is missing."]);
    exit;
}

// Retrieve the quiz type from the database
$sql = "SELECT question_type FROM questions WHERE quiz_id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(["success" => false, "error" => "Failed to prepare statement: " . $conn->error]);
    exit;
}

$stmt->bind_param("i", $quiz_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $question_type = $row['question_type'];
} else {
    echo json_encode(["success" => false, "error" => "Quiz not found."]);
    exit;
}

$stmt->close();

$score = 0;
$total = count($answers);
$wrong_answers = [];

foreach ($answers as $question_id => $answer) {
    $is_correct = 0;

    if ($question_type === 'true_or_false' || $question_type === 'multiple_choice' || $question_type === 'drag_and_drop') {
        // For True/False or Multiple Choice
        $sql = "SELECT is_correct FROM answers WHERE answer_id = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            echo json_encode(["success" => false, "error" => "Failed to prepare statement: " . $conn->error]);
            exit;
        }
        $stmt->bind_param("i", $answer);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $is_correct = ($row['is_correct'] === 1) ? 1 : 0;
            if ($row['is_correct'] == 1) {
                $score++;
            } else {
                $wrong_answers[$question_id] = $answer;
            }
        } else {
            echo json_encode(["success" => false, "error" => "Answer not found."]);
            exit;
        }
        $stmt->close();

    } elseif ($question_type === 'fill_in_the_blanks' || $question_type === 'identification') {
        // For Enumeration type
        $sql = "SELECT answer_text FROM answers WHERE question_id = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            echo json_encode(["success" => false, "error" => "Failed to prepare statement: " . $conn->error]);
            exit;
        }
        $stmt->bind_param("i", $question_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $correct_answers = explode(',', $row['answer_text']);
            $submitted_answers = explode(',', $answer);

            //Trim ang both arrays into lowercase for case-insensitive comparison
            $correct_answers = array_map('strtolower', array_map('trim', $correct_answers));
            $submitted_answers = array_map('strtolower', array_map('trim', $submitted_answers));

            //i-compare ang mga submitted answers with correct answers (case-insensitive)
            $correct_count = count(array_uintersect($correct_answers, $submitted_answers, 'strcasecmp'));
            $is_correct = ($correct_count == count($correct_answers)) ? 1 : 0;

            $score += $correct_count;
            if ($correct_count != count($correct_answers)) {
                $wrong_answers[$question_id] = $answer;
            }
        } else {
            echo json_encode(["success" => false, "error" => "Question not found."]);
            exit;
        }
        $stmt->close();

    } elseif ($question_type === 'enumeration') {
        // For Enumeration type
        $sql = "SELECT answer_text FROM answers WHERE question_id = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            echo json_encode(["success" => false, "error" => "Failed to prepare statement: " . $conn->error]);
            exit;
        }
        $stmt->bind_param("i", $question_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $correct_answers = explode(',', $row['answer_text']);
            $submitted_answers = explode(',', $answer);

            // Trim and make both arrays lowercase for case-insensitive comparison
            $correct_answers = array_map('strtolower', array_map('trim', $correct_answers));
            $submitted_answers = array_map('strtolower', array_map('trim', $submitted_answers));

            $submitted_answers = array_unique($submitted_answers);

            // Compare submitted answers with correct answers (case-insensitive)
            $correct_count = count(array_intersect($correct_answers, $submitted_answers));

            $points_to_add =   $correct_count;
            $score += $points_to_add;

            $is_correct = ($correct_count == count($correct_answers)) ? 1 : 0;

            if ($is_correct === 0) {
                $wrong_answers[$question_id] = $answer;
            }
        } else {
            echo json_encode(["success" => false, "error" => "Question not found."]);
            exit;
        }
        $stmt->close();
    } elseif ($question_type === 'matching_type') {
        // For Matching type
        $sql = "SELECT matching_config FROM questions WHERE question_id = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            echo json_encode(["success" => false, "error" => "Failed to prepare statement: " . $conn->error]);
            exit;
        }
        $stmt->bind_param("i", $question_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            // Assuming matching_config is stored as a JSON string
            $correct_mappings = json_decode($row['matching_config'], true);

            // Check if the submitted answer matches the correct mapping
            if (isset($correct_mappings[$answer])) {
                $is_correct = 1;
                $score++;
            } else {
                $wrong_answers[$question_id] = $answer;
            }
        } else {
            echo json_encode(["success" => false, "error" => "Question not found."]);
            exit;
        }
        $stmt->close();
    }

    // Insert the student's answer into the student_answers table for ALL quiz types
    $sql_insert = "INSERT INTO student_answers (student_id, quiz_id, question_id, answer, is_correct) 
                    VALUES (?, ?, ?, ?, ?)";
    $stmt_insert = $conn->prepare($sql_insert);
    if (!$stmt_insert) {
        echo json_encode(["success" => false, "error" => "Failed to prepare statement for inserting answers: " . $conn->error]);
        exit;
    }
    $stmt_insert->bind_param("iiisi", $student_id, $quiz_id, $question_id, $answer, $is_correct);
    $stmt_insert->execute();
    $stmt_insert->close();    
}

$sql = "INSERT INTO quiz_attempts (quiz_id, account_number, score) VALUES (?, ?, ?)";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(["success" => false, "error" => "Failed to prepare statement: " . $conn->error]);
    exit;
}
$stmt->bind_param("isi", $quiz_id, $account_number, $score);
if (!$stmt->execute()) {
    echo json_encode(["success" => false, "error" => "Failed to execute statement: " . $stmt->error]);
    exit;
}

echo json_encode([
    "success" => true,
    "score" => $score,
    "total" => $total,
    "wrong_answers" => $wrong_answers
]);

$conn->close();
?>