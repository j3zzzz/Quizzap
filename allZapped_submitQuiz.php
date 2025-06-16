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

if (!$answers || !$quiz_id) {
    echo json_encode(["success" => false, "error" => "Answers or quiz ID is missing."]);
    exit;
}

$score = 0;
$total = count($answers);
$wrong_answers = [];

foreach ($answers as $question_id => $answer) {
    $is_correct = 0;

    // Get the question type for THIS specific question
    $sql = "SELECT question_type FROM questions WHERE question_id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(["success" => false, "error" => "Failed to prepare statement: " . $conn->error]);
        exit;
    }
    
    $stmt->bind_param("i", $question_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $question_type = $row['question_type'];
    } else {
        echo json_encode(["success" => false, "error" => "Question not found for question_id: " . $question_id]);
        exit;
    }
    $stmt->close();

    error_log("Submitting answers for quiz $quiz_id with questions: " . print_r(array_keys($answers), true));

    if ($question_type === 'multiple_choice') {
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
                 // Get the answer text for this wrong answer
                $sql_text = "SELECT answer_text FROM answers WHERE answer_id = ?";
                $stmt_text = $conn->prepare($sql_text);
                if (!$stmt_text) {
                    echo json_encode(["success" => false, "error" => "Failed to prepare statement: " . $conn->error]);
                    exit;
                }
                $stmt_text->bind_param("i", $answer);
                $stmt_text->execute();
                $result_text = $stmt_text->get_result();
                if ($result_text && $result_text->num_rows > 0) {
                    $row_text = $result_text->fetch_assoc();
                    $wrong_answers[$question_id] = [
                        'answer_id' => $answer,
                        'answer_text' => $row_text['answer_text']
                    ];
                } else {
                    $wrong_answers[$question_id] = $answer; // fallback
                }
                $stmt_text->close();
            }
        } else {
            echo json_encode(["success" => false, "error" => "Answer not found for answer_id: " . $answer]);
            exit;
        }
        $stmt->close();

        } elseif ($question_type === 'true_or_false') {
            $sql = "SELECT answer_id, is_correct FROM answers 
                    WHERE question_id = ? AND (answer_id = ? OR LOWER(TRIM(answer_text)) = LOWER(TRIM(?)))";
            $stmt = $conn->prepare($sql);
            
            // Convert answer to appropriate types
            $answer_id_param = is_numeric($answer) ? $answer : 0;
            $answer_text_param = is_numeric($answer) ? '' : $answer;
            
            $stmt->bind_param("iis", $question_id, $answer_id_param, $answer_text_param);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $is_correct = $row['is_correct'];
                
                if ($is_correct) {
                    $score++;
                } else {
                   // For true/false, we might have either an ID or text answer
                    if (is_numeric($answer)) {
                        // Get the answer text for this wrong answer
                        $sql_text = "SELECT answer_text FROM answers WHERE answer_id = ?";
                        $stmt_text = $conn->prepare($sql_text);
                        if (!$stmt_text) {
                            echo json_encode(["success" => false, "error" => "Failed to prepare statement: " . $conn->error]);
                            exit;
                        }
                        $stmt_text->bind_param("i", $answer);
                        $stmt_text->execute();
                        $result_text = $stmt_text->get_result();
                        if ($result_text && $result_text->num_rows > 0) {
                            $row_text = $result_text->fetch_assoc();
                            $wrong_answers[$question_id] = [
                                'answer_id' => $answer,
                                'answer_text' => $row_text['answer_text']
                            ];
                        } else {
                            $wrong_answers[$question_id] = $answer; // fallback
                        }
                        $stmt_text->close();
                    } else {
                        // It's already text (like "True" or "False")
                        $wrong_answers[$question_id] = [
                            'answer_text' => $answer
                        ];
                    }
                }
            } else {
                // Check if question exists but has no valid answers
                $checkQ = $conn->prepare("SELECT 1 FROM questions WHERE question_id = ?");
                $checkQ->bind_param("i", $question_id);
                $checkQ->execute();
                
                if ($checkQ->get_result()->num_rows > 0) {
                    // Question exists but answer not found - likely misconfigured
                    $wrong_answers[$question_id] = "Question misconfigured - no valid answers";
                    $is_correct = 0;
                } else {
                    // Question doesn't exist
                    throw new Exception("Question not found for question_id: $question_id");
                }
            }
            $stmt->close();
        } elseif ($question_type === 'drag_and_drop') {
            // For Drag and Drop - answer should be an array
            if (is_array($answer) && count($answer) > 0) {
                $answer_id = $answer[0]; // Get the first answer ID
                $sql = "SELECT is_correct, answer_text FROM answers WHERE answer_id = ?";
                $stmt = $conn->prepare($sql);
                if (!$stmt) {
                    echo json_encode(["success" => false, "error" => "Failed to prepare statement: " . $conn->error]);
                    exit;
                }
                $stmt->bind_param("i", $answer_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result && $result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    $is_correct = ($row['is_correct'] === 1) ? 1 : 0;
                    if ($row['is_correct'] == 1) {
                        $score++;
                    } else {
                        $wrong_answers[$question_id] = [
                            'answer_id' => $answer_id,
                            'answer_text' => $row['answer_text']
                        ];
                    }
                } else {
                    echo json_encode(["success" => false, "error" => "Answer not found for drag and drop answer_id: " . $answer_id]);
                    exit;
                }
                $stmt->close();
            } else {
                $wrong_answers[$question_id] = $answer;
            }

        } elseif ($question_type === 'fill_in_the_blanks' || $question_type === 'identification') {
            // For Fill in the blanks or Identification
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

                // Compare submitted answers with correct answers (case-insensitive)
                $correct_count = count(array_uintersect($correct_answers, $submitted_answers, 'strcasecmp'));
                $is_correct = ($correct_count == count($correct_answers)) ? 1 : 0;

                if ($is_correct) {
                    $score++;
                } else {
                    // Store wrong answer in consistent format
                    $wrong_answers[$question_id] = [
                        'answer_text' => $answer
                    ];
                }
            } else {
                echo json_encode(["success" => false, "error" => "Question not found for question_id: " . $question_id]);
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

                // Compare submitted answers with correct answers (case-insensitive)
                $correct_count = count(array_uintersect($correct_answers, $submitted_answers, 'strcasecmp'));
                
                // For enumeration, give partial credit
                $score += $correct_count;
                if ($correct_count != count($correct_answers)) {
                    // Store wrong answer in consistent format
                    $wrong_answers[$question_id] = [
                        'answer_text' => $answer
                    ];
                }
            } else {
                echo json_encode(["success" => false, "error" => "Question not found for question_id: " . $question_id]);
                exit;
            }
            $stmt->close();

        } elseif ($question_type === 'matching_type') {
            // For Matching type - answer should be an array of matching objects
            if (is_array($answer) && count($answer) > 0) {
                $matching_data = $answer[0]; // Get the first matching data
                
                // Get all correct matching pairs for this question
                $sql = "SELECT answer_id, matching_pair FROM answers WHERE question_id = ? AND side = 'left'";
                $stmt = $conn->prepare($sql);
                if (!$stmt) {
                    echo json_encode(["success" => false, "error" => "Failed to prepare statement: " . $conn->error]);
                    exit;
                }
                $stmt->bind_param("i", $question_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                $correct_matches = 0;
                $total_matches = 0;
                
                while ($row = $result->fetch_assoc()) {
                    $total_matches++;
                    if (isset($matching_data['left']) && isset($matching_data['right'])) {
                        if ($row['answer_id'] == $matching_data['left'] && $row['matching_pair'] == $matching_data['right']) {
                            $correct_matches++;
                        }
                    }
                }
                
                if ($correct_matches == $total_matches && $total_matches > 0) {
                    $score++;
                    $is_correct = 1;
                } else {
                    $wrong_answers[$question_id] = $answer;
                }
                
                $stmt->close();
            } else {
                $wrong_answers[$question_id] = $answer;
            }
        }

    // Convert answer to string for database storage
    $answer_string = is_array($answer) ? json_encode($answer) : $answer;

    // Insert the student's answer into the student_answers table for ALL quiz types
    $sql_insert = "INSERT INTO student_answers (student_id, quiz_id, question_id, answer, is_correct) 
                    VALUES (?, ?, ?, ?, ?)";
    $stmt_insert = $conn->prepare($sql_insert);
    if (!$stmt_insert) {
        echo json_encode(["success" => false, "error" => "Failed to prepare statement for inserting answers: " . $conn->error]);
        exit;
    }
    $stmt_insert->bind_param("iiisi", $student_id, $quiz_id, $question_id, $answer_string, $is_correct);
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