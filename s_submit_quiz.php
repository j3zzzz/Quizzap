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

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
   echo json_encode(["success" => false, "error" => "Connection failed: " . $conn->connect_error]);
   exit;
}

$subject_id = null;

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
$is_final_submit = !($data['partial_submit'] ?? false);

if (!$quiz_id) {
    echo json_encode(["success" => false, "error" => "Quiz ID is missing."]);
    exit;
}

// Retrieve the quiz type from the database
$sql = "SELECT quiz_type, subject_id FROM quizzes WHERE quiz_id = ?";
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
    $quiz_type = $row['quiz_type'];
    $subject_id = $row['subject_id'];
} else {
    echo json_encode(["success" => false, "error" => "Quiz not found."]);
    exit;
}

$stmt->close();

$score = 0;
$total = count($answers);
$wrong_answers = [];

// First, clear any existing answers for this student and quiz
$delete_sql = "DELETE FROM student_answers WHERE student_id = ? AND quiz_id = ?";
$delete_stmt = $conn->prepare($delete_sql);
$delete_stmt->bind_param("ii", $student_id, $quiz_id);
$delete_stmt->execute();
$delete_stmt->close();

foreach ($answers as $question_id => $answer) {
    $is_correct = 0;
    $answer_text_to_store = is_array($answer) ? json_encode($answer) : $answer;

    // Debug logging
    error_log("Processing question $question_id, answer: " . print_r($answer, true) . ", quiz_type: $quiz_type");

    if ($quiz_type === 'True or False' || $quiz_type === 'Multiple Choice' || $quiz_type === 'Drag & Drop') {
        // For these types, answer should be an answer_id
        if (is_numeric($answer)) {
            $sql = "SELECT answer_text, is_correct FROM answers WHERE answer_id = ?";
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
                $answer_text_to_store = $row['answer_text'];

                if ($is_correct) {
                    $score++;
                } else {
                    $wrong_answers[$question_id] = [
                        'answer_id' => $answer,
                        'answer_text' => $row['answer_text']
                    ];
                }
            } else {
                // If answer_id not found, try to find by answer text
                $sql = "SELECT answer_id, answer_text, is_correct FROM answers WHERE question_id = ? AND answer_text = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("is", $question_id, $answer);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result && $result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    $is_correct = ($row['is_correct'] === 1) ? 1 : 0;
                    $answer_text_to_store = $row['answer_text'];

                    if ($is_correct) {
                        $score++;
                    } else {
                        $wrong_answers[$question_id] = [
                            'answer_id' => $row['answer_id'],
                            'answer_text' => $row['answer_text']
                        ];
                    }
                } else {
                    error_log("Answer not found for question_id: $question_id, answer: $answer");
                    $wrong_answers[$question_id] = [
                        'answer_text' => $answer,
                        'error' => 'Answer not found in database'
                    ];
                }
            }
            $stmt->close();
        } else {
            // Handle non-numeric answers for these types
            $sql = "SELECT answer_id, answer_text, is_correct FROM answers WHERE question_id = ? AND answer_text = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("is", $question_id, $answer);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $is_correct = ($row['is_correct'] === 1) ? 1 : 0;
                $answer_text_to_store = $row['answer_text'];

                if ($is_correct) {
                    $score++;
                } else {
                    $wrong_answers[$question_id] = [
                        'answer_id' => $row['answer_id'],
                        'answer_text' => $row['answer_text']
                    ];
                }
            } else {
                error_log("Answer not found for question_id: $question_id, answer: $answer");
                $wrong_answers[$question_id] = [
                    'answer_text' => $answer,
                    'error' => 'Answer not found in database'
                ];
            }
            $stmt->close();
        }

    } elseif ($quiz_type === 'Enumeration' || $quiz_type === 'Fill in the Blanks' || $quiz_type === 'Identification') {
        // For text-based answers
        $sql = "SELECT answer_text, is_correct FROM answers WHERE question_id = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            echo json_encode(["success" => false, "error" => "Failed to prepare statement: " . $conn->error]);
            exit;
        }
        $stmt->bind_param("i", $question_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $correct_answer_found = false;
            
            while ($row = $result->fetch_assoc()) {
                $correct_answer = trim($row['answer_text']);
                $submitted_answer = trim($answer);
                
                // Case-insensitive comparison
                if (strcasecmp($correct_answer, $submitted_answer) === 0) {
                    $is_correct = 1;
                    $score++;
                    $correct_answer_found = true;
                    break;
                }
                
                // For enumeration with multiple answers
                if ($quiz_type === 'Enumeration') {
                    $correct_answers = array_map('trim', explode(',', $correct_answer));
                    $submitted_answers = array_map('trim', explode(',', $submitted_answer));
                    
                    $correct_answers_lower = array_map('strtolower', $correct_answers);
                    $submitted_answers_lower = array_map('strtolower', $submitted_answers);
                    
                    sort($correct_answers_lower);
                    sort($submitted_answers_lower);
                    
                    if ($correct_answers_lower === $submitted_answers_lower) {
                        $is_correct = 1;
                        $score++;
                        $correct_answer_found = true;
                        break;
                    }
                }
            }
            
            if (!$correct_answer_found) {
                $wrong_answers[$question_id] = [
                    'answer_text' => $answer
                ];
            }
            
            $answer_text_to_store = $answer;
        } else {
            error_log("Question not found for question_id: " . $question_id);
            $wrong_answers[$question_id] = [
                'answer_text' => $answer,
                'error' => 'Question not found'
            ];
        }
        $stmt->close();

    } elseif ($quiz_type === 'Matching Type') {
        // Handle matching type questions
        try {
            $submitted_matches = [];
            
            // Parse the submitted answer
            if (is_string($answer)) {
                $submitted_matches = json_decode($answer, true);
            } elseif (is_array($answer)) {
                $submitted_matches = $answer;
            }
            
            if (!is_array($submitted_matches)) {
                $submitted_matches = [];
            }
            
            // Get correct matches
            $sql = "SELECT answer_text FROM answers WHERE question_id = ? AND is_correct = 1";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $question_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $correct_matches = [];
            while ($row = $result->fetch_assoc()) {
                $match_data = json_decode($row['answer_text'], true);
                if (is_array($match_data)) {
                    $correct_matches[] = $match_data;
                }
            }
            
            $correct_count = 0;
            $total_expected = count($correct_matches);
            
            // Compare matches
            foreach ($submitted_matches as $submitted_match) {
                foreach ($correct_matches as $correct_match) {
                    if (isset($submitted_match['left'], $submitted_match['right']) &&
                        isset($correct_match['left'], $correct_match['right'])) {
                        
                        $submitted_left = trim($submitted_match['left']);
                        $submitted_right = trim($submitted_match['right']);
                        $correct_left = trim($correct_match['left']);
                        $correct_right = trim($correct_match['right']);
                        
                        // Remove numbering for comparison
                        $submitted_left = preg_replace('/^\d+\.\s*/', '', $submitted_left);
                        $submitted_right = preg_replace('/^[A-Z]\.\s*/', '', $submitted_right);
                        $correct_left = preg_replace('/^\d+\.\s*/', '', $correct_left);
                        $correct_right = preg_replace('/^[A-Z]\.\s*/', '', $correct_right);
                        
                        if (strcasecmp($submitted_left, $correct_left) === 0 && 
                            strcasecmp($submitted_right, $correct_right) === 0) {
                            $correct_count++;
                            break;
                        }
                    }
                }
            }
            
            // All matches must be correct for full credit
            if ($correct_count === $total_expected && $total_expected > 0) {
                $is_correct = 1;
                $score++;
            } else {
                $wrong_answers[$question_id] = [
                    'submitted_matches' => $submitted_matches,
                    'correct_matches' => $correct_matches,
                    'correct_count' => $correct_count,
                    'total_expected' => $total_expected
                ];
            }
            
            $answer_text_to_store = json_encode($submitted_matches);
            $stmt->close();
            
        } catch (Exception $e) {
            error_log("Error processing matching type: " . $e->getMessage());
            $wrong_answers[$question_id] = [
                'answer_text' => $answer,
                'error' => 'Error processing matching type'
            ];
        }
    }

    // Insert the student's answer
    $sql_insert = "INSERT INTO student_answers (student_id, quiz_id, question_id, answer, is_correct) 
                    VALUES (?, ?, ?, ?, ?)";
    $stmt_insert = $conn->prepare($sql_insert);
    if (!$stmt_insert) {
        echo json_encode(["success" => false, "error" => "Failed to prepare statement for inserting answers: " . $conn->error]);
        exit;
    }
    $stmt_insert->bind_param("iiisi", $student_id, $quiz_id, $question_id, $answer_text_to_store, $is_correct);
    
    if (!$stmt_insert->execute()) {
        error_log("Failed to insert answer for question $question_id: " . $stmt_insert->error);
    }
    
    $stmt_insert->close();    
}

// Only create a new quiz attempt for final submissions
if ($is_final_submit) {
    // Update existing attempt or create new one
    $check_attempt_sql = "SELECT attempt_id FROM quiz_attempts WHERE quiz_id = ? AND account_number = ? AND completed = 0 ORDER BY attempt_id DESC LIMIT 1";
    $check_stmt = $conn->prepare($check_attempt_sql);
    $check_stmt->bind_param("is", $quiz_id, $account_number);
    $check_stmt->execute();
    $attempt_result = $check_stmt->get_result();
    
    if ($attempt_result->num_rows > 0) {
        // Update existing attempt
        $attempt_row = $attempt_result->fetch_assoc();
        $attempt_id = $attempt_row['attempt_id'];
        
        $update_sql = "UPDATE quiz_attempts SET score = ?, completed = 1, time_remaining = 0 WHERE attempt_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ii", $score, $attempt_id);
        $update_stmt->execute();
        $update_stmt->close();
    } else {
        // Create new attempt
        $insert_sql = "INSERT INTO quiz_attempts (quiz_id, account_number, score, completed) VALUES (?, ?, ?, 1)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("isi", $quiz_id, $account_number, $score);
        $insert_stmt->execute();
        $insert_stmt->close();
    }
    $check_stmt->close();
}

echo json_encode([
    "success" => true,
    "score" => $score,
    "total" => $total,
    "wrong_answers" => $wrong_answers,
    "is_final_submit" => $is_final_submit,
    "subject_id" => $subject_id
]);

$conn->close();
?>