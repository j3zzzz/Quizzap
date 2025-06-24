<?php
session_start();

// Check if the user has the correct account number prefix
if (strpos($_SESSION['account_number'], 'T') !== 0) {
    header("Location: login.php");
    exit();
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "rawrit";

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$response = ["success" => false, "message" => "", "subject_id" => ""];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $conn->begin_transaction();
    
    try {
        $subject_id = $_POST['subject_id'];
        $quiz_title = $_POST['title'];
        $timer = $_POST['timer'];
        $quiz_type = $_POST['quiz_type'];
        $questions = isset($_POST['questions']) ? $_POST['questions'] : [];
        $answers = $_POST['answers'] ?? [];
        $correct = isset($_POST['correct']) ? $_POST['correct'] : [];
        $correct_answer = $_POST['correct_answer'] ?? [];
        $blanks_answers = isset($_POST['blanks_answers']) ? $_POST['blanks_answers'] : [];

        if (empty($quiz_type)) {
            throw new Exception("Quiz type is empty or not passed.");
        }

        // Insert the quiz into the 'quizzes' table
        $stmt = $conn->prepare("INSERT INTO quizzes (subject_id, title, timer, quiz_type) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isis", $subject_id, $quiz_title, $timer, $quiz_type);

        if (!$stmt->execute()) {
            throw new Exception("Error creating quiz: " . $stmt->error);
        }

        $quiz_id = $stmt->insert_id;
        $stmt->close();

        foreach ($questions as $index => $question) {
            $question = trim($question);
            if (empty($question)) {
                throw new Exception("Question text cannot be empty for question " . ($index + 1));
            }

            // Insert question with question_type
            $stmt = $conn->prepare("INSERT INTO questions (quiz_id, question_text, question_type) VALUES (?, ?, ?)");
            $question_type = $quiz_type; // Assuming quiz_type matches question_type
            $stmt->bind_param("iss", $quiz_id, $question, $question_type);

            if (!$stmt->execute()) {
                throw new Exception("Error creating question: " . $stmt->error);
            }

            $question_id = $stmt->insert_id;

            // Handle quiz type answers
            if ($quiz_type == 'True or False') {
                $answers = ['True', 'False'];
                foreach ($answers as $answer) {
                    $is_correct = ($correct[$index] == $answer) ? 1 : 0;
                    $stmt = $conn->prepare("INSERT INTO answers (question_id, answer_text, is_correct) VALUES (?, ?, ?)");
                    $stmt->bind_param("isi", $question_id, $answer, $is_correct);
                    if (!$stmt->execute()) {
                        throw new Exception("Error inserting True/False answer: " . $stmt->error);
                    }
                }
            } elseif ($quiz_type == 'Fill in the Blanks') {
                if (isset($blanks_answers[$index]) && is_array($blanks_answers[$index])) {
                    foreach ($blanks_answers[$index] as $blank_answer) {
                        $blank_answer = trim($blank_answer);
                        $stmt_answer = $conn->prepare("INSERT INTO answers (question_id, answer_text, is_correct) VALUES (?, ?, 1)");
                        $stmt_answer->bind_param("is", $question_id, $blank_answer);
                        
                        if (!$stmt_answer->execute()) {
                            throw new Exception("Error inserting fill in the blanks answer: " . $stmt_answer->error);
                        }
                        $stmt_answer->close();
                    }
                } else {
                    throw new Exception("No answers provided for fill in the blanks question at index " . $index);
                }
            }  elseif ($quiz_type == 'Enumeration') {
                $correct_answer_list = explode(',', $correct_answer[$index]);
                // Trim each answer and filter out empty ones
                $correct_answer_list = array_filter(array_map('trim', $correct_answer_list));
                
                if (empty($correct_answer_list)) {
                    throw new Exception("No valid answers provided for enumeration question at index " . $index);
                }
                
                // Save all enumeration answers as a single comma-separated string
                $answer_text = implode(',', $correct_answer_list);
                $stmt_answer = $conn->prepare("INSERT INTO answers (question_id, answer_text, is_correct) VALUES (?, ?, 1)");
                $stmt_answer->bind_param("is", $question_id, $answer_text);
                if (!$stmt_answer->execute()) {
                    throw new Exception("Error inserting enumeration answer: " . $stmt_answer->error);
                }
                $stmt_answer->close();
            } elseif ($quiz_type == 'Identification') {
                $correct_answer_list = explode(',', $correct_answer[$index]);
                foreach ($correct_answer_list as $answer) {
                    $answer = trim($answer);
                    $stmt_answer = $conn->prepare("INSERT INTO answers (question_id, answer_text, is_correct) VALUES (?, ?, 1)");
                    $stmt_answer->bind_param("is", $question_id, $answer);
                    if (!$stmt_answer->execute()) {
                        throw new Exception("Error inserting identification answer: " . $stmt_answer->error);
                    }
                    $stmt_answer->close();
                }
            } elseif ($quiz_type == 'Matching Type') {
                if (empty($_POST['left_items'][$index])) {
                    throw new Exception("Missing left items for matching question " . ($index + 1));
                }
                
                if (empty($_POST['right_items'][$index])) {
                    throw new Exception("Missing right items for matching question " . ($index + 1));
                }
                
                $left_items = [];
                $right_items = [];
                
                foreach ($_POST['left_items'][$index] as $pair_index => $left_item) {
                    if (!isset($_POST['right_items'][$index][$pair_index])) continue;
                    
                    $left_item = trim($left_item);
                    $right_item = trim($_POST['right_items'][$index][$pair_index]);
                    
                    if (empty($left_item) || empty($right_item)) {
                        continue; // Skip empty pairs
                    }
                    
                    $left_items[] = $left_item;
                    $right_items[] = $right_item;
                    
                    // Changed from " - " to "|" to match allZapped_saveQuiz.php
                    $answer_text = "$left_item|$right_item";
                    
                    $stmt_answer = $conn->prepare("INSERT INTO answers (question_id, answer_text, is_correct) VALUES (?, ?, 1)");
                    $stmt_answer->bind_param("is", $question_id, $answer_text);
                    
                    if (!$stmt_answer->execute()) {
                        throw new Exception("Error saving matching pair: " . $stmt_answer->error);
                    }
                }
                
                // Update the question with left and right items as JSON
                $left_json = json_encode($left_items);
                $right_json = json_encode($right_items);
                
                $update_stmt = $conn->prepare("UPDATE questions SET left_items = ?, right_items = ? WHERE question_id = ?");
                $update_stmt->bind_param("ssi", $left_json, $right_json, $question_id);
                if (!$update_stmt->execute()) {
                    throw new Exception("Error updating matching question items: " . $update_stmt->error);
                }
            } elseif ($quiz_type == 'Drag & Drop') {
                if (isset($answers[$index]) && is_array($answers[$index])) {
                    foreach ($answers[$index] as $answer_index => $answer_text) {
                        $is_correct = (isset($correct_answer[$index]) && $correct_answer[$index] == $answer_index) ? 1 : 0;
                        $stmt_answer = $conn->prepare("INSERT INTO answers (question_id, answer_text, is_correct) VALUES (?, ?, ?)");
                        $stmt_answer->bind_param("isi", $question_id, $answer_text, $is_correct);
                        if (!$stmt_answer->execute()) {
                            throw new Exception("Error inserting Drag & Drop answer: " . $stmt_answer->error);
                        }
                    }
                }                            
            } else {
                // For multiple choice
                foreach ($answers[$index] as $answer_index => $answer) {
                    $is_correct = ($correct[$index] == $answer_index) ? 1 : 0;
                    $stmt = $conn->prepare("INSERT INTO answers (question_id, answer_text, is_correct) VALUES (?, ?, ?)");
                    $stmt->bind_param("isi", $question_id, $answer, $is_correct);
                    if (!$stmt->execute()) {
                        throw new Exception("Error inserting other type answer: " . $stmt->error);
                    }
                }
            }
        }

        $conn->commit();
        $response["success"] = true;
        $response["message"] = "Quiz and all questions created successfully.";
        $response["subject_id"] = $subject_id;
    } catch (Exception $e) {
        $conn->rollback();
        $response["message"] = $e->getMessage();
    }

    $conn->close();
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
} else {
    $subject_id = $_GET['subject_id'];
}

header("Location: t_quizDash.php?subject_id=$subject_id");
exit();
?>