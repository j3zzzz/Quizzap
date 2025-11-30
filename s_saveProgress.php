<?php
session_start();
date_default_timezone_set('Asia/Manila');
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

$answers = $data['answers'] ?? null;
$quiz_id = $data['quiz_id'] ?? null;
$attempt_id = $data['attempt_id'] ?? null;
$time_remaining = $data['time_remaining'] ?? null;

if (!$answers || !$quiz_id || !$attempt_id) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
    exit();
}

// Get student_id from account_number
$account_number = $_SESSION['account_number'];
$student_id = null;

$student_stmt = $conn->prepare("SELECT student_id FROM students WHERE account_number = ?");
if (!$student_stmt) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Failed to prepare student query: ' . $conn->error]);
    exit();
}

$student_stmt->bind_param("s", $account_number);
$student_stmt->execute();
$student_result = $student_stmt->get_result();

if ($student_result->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Student not found']);
    exit();
}

$student_row = $student_result->fetch_assoc();
$student_id = $student_row['student_id'];
$student_stmt->close();

// Verify the attempt belongs to the current user
$verify_stmt = $conn->prepare("SELECT attempt_id FROM quiz_attempts 
                              WHERE attempt_id = ? AND account_number = ? AND completed = 0");
if (!$verify_stmt) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Failed to prepare verification query: ' . $conn->error]);
    exit();
}

$verify_stmt->bind_param("is", $attempt_id, $account_number);
$verify_stmt->execute();
$verify_result = $verify_stmt->get_result();

if ($verify_result->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid attempt or already completed']);
    exit();
}
$verify_stmt->close();

try {
    // Begin transaction
    $conn->begin_transaction();

    // Delete existing answers for this attempt to avoid duplicates
    $delete_stmt = $conn->prepare("DELETE FROM student_answers 
                                 WHERE student_id = ? AND quiz_id = ?");
    if (!$delete_stmt) {
        throw new Exception('Failed to prepare delete statement: ' . $conn->error);
    }
    $delete_stmt->bind_param("ii", $student_id, $quiz_id);
    $delete_stmt->execute();
    $delete_stmt->close();

    // Prepare insert statement
    $insert_stmt = $conn->prepare("INSERT INTO student_answers 
                                  (student_id, quiz_id, question_id, answer, is_correct) 
                                  VALUES (?, ?, ?, ?, ?)");
    if (!$insert_stmt) {
        throw new Exception('Failed to prepare insert statement: ' . $conn->error);
    }

    // Process each answer
    foreach ($answers as $question_id => $answer) {
        $answer_string = is_array($answer) ? json_encode($answer) : $answer;
        $is_correct = null; // Will be determined during final submission
        
        $insert_stmt->bind_param("iiisi", $student_id, $quiz_id, $question_id, $answer_string, $is_correct);
        if (!$insert_stmt->execute()) {
            throw new Exception('Failed to save answer for question ' . $question_id . ': ' . $insert_stmt->error);
        }

        // For enum/identification types, do preliminary scoring if possible
        if (is_string($answer)) {
            $check_sql = "SELECT COUNT(*) as is_correct FROM answers 
                        WHERE question_id = ? AND FIND_IN_SET(?, answer_text)";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("is", $question_id, $answer);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            $row = $result->fetch_assoc();
            $is_correct_prelim = $row['is_correct'] > 0 ? 1 : 0;
            
            // Update with preliminary score
            $update_sql = "UPDATE student_answers SET is_correct = ? 
                        WHERE student_id = ? AND quiz_id = ? AND question_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("iiii", $is_correct_prelim, $student_id, $quiz_id, $question_id);
            $update_stmt->execute();
            $update_stmt->close();
        }
    }
    $insert_stmt->close();

    if ($attempt_id) {
        $update_stmt = $conn->prepare("UPDATE quiz_attempts 
                                    SET last_saved = NOW(), time_remaining = ?
                                    WHERE attempt_id = ?");
        if ($update_stmt) {
            $update_stmt->bind_param("ii", $time_remaining, $attempt_id);
            $update_stmt->execute();
            $update_stmt->close();
            
            error_log("✅ Auto-save: Updated last_saved for attempt " . $attempt_id);
        }
    }

    // Commit transaction
    $conn->commit();

    header('Content-Type: application/json');
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
?>