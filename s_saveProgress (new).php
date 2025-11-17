<?php
session_start();
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

if (!$answers || !$quiz_id) {
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

// Get quiz type to handle answers appropriately
$quiz_stmt = $conn->prepare("SELECT quiz_type FROM quizzes WHERE quiz_id = ?");
$quiz_stmt->bind_param("i", $quiz_id);
$quiz_stmt->execute();
$quiz_result = $quiz_stmt->get_result();
$quiz_data = $quiz_result->fetch_assoc();
$quiz_type = $quiz_data['quiz_type'];
$quiz_stmt->close();

try {
    // Begin transaction
    $conn->begin_transaction();

    // Delete existing answers for this quiz attempt to avoid duplicates
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

    // Process each answer based on quiz type
    foreach ($answers as $question_id => $answer) {
        // Convert answer to appropriate format
        $answer_string = is_array($answer) ? json_encode($answer) : $answer;
        $is_correct = null; // Will be determined during final submission
        
        $insert_stmt->bind_param("iiisi", $student_id, $quiz_id, $question_id, $answer_string, $is_correct);
        if (!$insert_stmt->execute()) {
            throw new Exception('Failed to save answer for question ' . $question_id . ': ' . $insert_stmt->error);
        }

        // Do preliminary scoring for certain question types
        if ($quiz_type === 'True or False' || $quiz_type === 'Multiple Choice') {
            // For MC and T/F, check if the selected answer is correct
            $check_sql = "SELECT is_correct FROM answers 
                         WHERE question_id = ? AND answer_id = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("ii", $question_id, $answer);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $is_correct_prelim = $row['is_correct'];
                
                // Update with preliminary score
                $update_sql = "UPDATE student_answers SET is_correct = ? 
                             WHERE student_id = ? AND quiz_id = ? AND question_id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("iiii", $is_correct_prelim, $student_id, $quiz_id, $question_id);
                $update_stmt->execute();
                $update_stmt->close();
            }
            $check_stmt->close();
        }
        // For text-based answers (Identification, Enumeration, Fill in the Blanks)
        else if (in_array($quiz_type, ['Identification', 'Enumeration', 'Fill in the Blanks']) && is_string($answer)) {
            $check_sql = "SELECT answer_text FROM answers 
                         WHERE question_id = ? AND is_correct = 1";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("i", $question_id);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $correct_answer = strtolower(trim($row['answer_text']));
                $user_answer = strtolower(trim($answer));
                
                // Case-insensitive comparison
                $is_correct_prelim = ($correct_answer === $user_answer) ? 1 : 0;
                
                // Update with preliminary score
                $update_sql = "UPDATE student_answers SET is_correct = ? 
                             WHERE student_id = ? AND quiz_id = ? AND question_id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("iiii", $is_correct_prelim, $student_id, $quiz_id, $question_id);
                $update_stmt->execute();
                $update_stmt->close();
            }
            $check_stmt->close();
        }
        // For Drag & Drop
        else if ($quiz_type === 'Drag & Drop' && is_array($answer)) {
            if (count($answer) > 0) {
                $dragged_answer_id = $answer[0];
                
                $check_sql = "SELECT is_correct FROM answers 
                             WHERE question_id = ? AND answer_id = ?";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param("ii", $question_id, $dragged_answer_id);
                $check_stmt->execute();
                $result = $check_stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    $is_correct_prelim = $row['is_correct'];
                    
                    // Update with preliminary score
                    $update_sql = "UPDATE student_answers SET is_correct = ? 
                                 WHERE student_id = ? AND quiz_id = ? AND question_id = ?";
                    $update_stmt = $conn->prepare($update_sql);
                    $update_stmt->bind_param("iiii", $is_correct_prelim, $student_id, $quiz_id, $question_id);
                    $update_stmt->execute();
                    $update_stmt->close();
                }
                $check_stmt->close();
            }
        }
        // For Matching Type - stored as JSON array
        else if ($quiz_type === 'Matching Type' && is_array($answer)) {
            // Matching type will be scored during final submission
            // Just store the matches as JSON
        }
    }
    $insert_stmt->close();

    // Update attempt timestamp if attempt_id is provided
    if ($attempt_id) {
        $update_stmt = $conn->prepare("UPDATE quiz_attempts 
                                      SET last_saved = NOW()
                                      WHERE attempt_id = ?");
        if ($update_stmt) {
            $update_stmt->bind_param("i", $attempt_id);
            $update_stmt->execute();
            $update_stmt->close();
        }
    }

    if ($attempt_id && $time_remaining !== null) {
    // Parse the time string (format: "MM:SS")
    $time_parts = explode(':', $time_remaining);
    if (count($time_parts) === 2) {
        $minutes = intval($time_parts[0]);
        $seconds = intval($time_parts[1]);
        $total_seconds_remaining = ($minutes * 60) + $seconds;
        
        $time_stmt = $conn->prepare("UPDATE quiz_attempts 
                                    SET time_remaining = ?
                                    WHERE attempt_id = ?");
        if ($time_stmt) {
            $time_stmt->bind_param("ii", $total_seconds_remaining, $attempt_id);
            $time_stmt->execute();
            $time_stmt->close();
            
            error_log("Auto-save: Updated time remaining to " . $total_seconds_remaining . " seconds for attempt " . $attempt_id);
        }
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