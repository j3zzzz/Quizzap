<?php
header('Content-Type: application/json');
ob_start();

session_start();
if (strpos($_SESSION['account_number'], 'T') !== 0) {
    echo json_encode(["success" => false, "message" => "Unauthorized access"]);
    exit();
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "rawrit";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "Database connection failed"]);
    exit();
}

$response = ["success" => false, "message" => "", "subject_id" => ""];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn->begin_transaction();
    
    try {
        // Validate required fields
        if (empty($_POST['title'])) {
            throw new Exception("Quiz title is required");
        }
        
        if (empty($_POST['subject_id'])) {
            throw new Exception("Subject ID is required");
        }
        
        if (empty($_POST['timer'])) {
            throw new Exception("Timer value is required");
        }
        
        if (empty($_POST['questions'])) {
            throw new Exception("At least one question is required");
        }

        // Sanitize inputs
        $title = trim($conn->real_escape_string($_POST['title']));
        $subject_id = intval($_POST['subject_id']);
        $timer = intval($_POST['timer']);
        $quiz_type = $conn->real_escape_string($_POST['quiz_type']);

        // Insert quiz
        $stmt = $conn->prepare("INSERT INTO quizzes (title, subject_id, timer, quiz_type) VALUES (?, ?, ?, ?)");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("siis", $title, $subject_id, $timer, $quiz_type);
        
        if (!$stmt->execute()) {
            throw new Exception("Error creating quiz: " . $stmt->error);
        }
        
        $quiz_id = $conn->insert_id;
        
        // Process each question
        foreach ($_POST['questions'] as $i => $question_text) {
            if (empty(trim($question_text))) {
                throw new Exception("Question text cannot be empty for question " . ($i + 1));
            }
            
            $question_type = $_POST['question_type'][$i];
            $question_text = trim($conn->real_escape_string($question_text));
            
            // Insert question
            $stmt = $conn->prepare("INSERT INTO questions (quiz_id, question_type, question_text) VALUES (?, ?, ?)");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            $stmt->bind_param("iss", $quiz_id, $question_type, $question_text);
            
            if (!$stmt->execute()) {
                throw new Exception("Error creating question: " . $stmt->error);
            }
            
            $question_id = $conn->insert_id;
            
            // Handle question options based on type
            switch ($question_type) {
                case 'multiple_choice':
                    if (empty($_POST['answers'][$i]) || !is_array($_POST['answers'][$i])) {
                        throw new Exception("Missing answers for multiple choice question " . ($i + 1));
                    }
                    
                    $correct_index = isset($_POST['correct'][$i]) ? intval($_POST['correct'][$i]) : -1;
                    if ($correct_index === -1) {
                        throw new Exception("No correct answer selected for multiple choice question " . ($i + 1));
                    }
                    
                    foreach ($_POST['answers'][$i] as $answer_index => $answer_text) {
                        if (empty(trim($answer_text))) continue;
                        
                        $is_correct = ($answer_index == $correct_index) ? 1 : 0;
                        $answer_text = trim($conn->real_escape_string($answer_text));
                        
                        $stmt = $conn->prepare("INSERT INTO answers (question_id, answer_text, is_correct) VALUES (?, ?, ?)");
                        $stmt->bind_param("isi", $question_id, $answer_text, $is_correct);
                        
                        if (!$stmt->execute()) {
                            throw new Exception("Error saving answer: " . $stmt->error);
                        }
                    }
                    break;
                    
                case 'true_or_false':
                    if (empty($_POST['correct_option'][$i])) {
                        throw new Exception("Missing correct answer for true/false question " . ($i + 1));
                    }
                    
                    $correct_answer = $_POST['correct_option'][$i] === 'True' ? 1 : 0;
                    
                    // Insert True option
                    $stmt = $conn->prepare("INSERT INTO answers (question_id, answer_text, is_correct) VALUES (?, 'True', ?)");
                    $stmt->bind_param("ii", $question_id, $correct_answer);
                    $stmt->execute();
                    
                    // Insert False option
                    $stmt = $conn->prepare("INSERT INTO answers (question_id, answer_text, is_correct) VALUES (?, 'False', ?)");
                    $correct_answer = 1 - $correct_answer; // Flip for false
                    $stmt->bind_param("ii", $question_id, $correct_answer);
                    $stmt->execute();
                    break;
                    
                case 'fill_in_the_blanks':
                case 'identification':
                    if (empty($_POST['correct_option'][$i])) {
                        throw new Exception("Missing correct answer for " . $question_type . " question " . ($i + 1));
                    }
                    
                    $answer_text = trim($conn->real_escape_string($_POST['correct_option'][$i]));
                    $stmt = $conn->prepare("INSERT INTO answers (question_id, answer_text, is_correct) VALUES (?, ?, 1)");
                    $stmt->bind_param("is", $question_id, $answer_text);
                    
                    if (!$stmt->execute()) {
                        throw new Exception("Error saving answer: " . $stmt->error);
                    }
                    break;
                    
                case 'enumeration':
                    if (empty($_POST['correct_option'][$i])) {
                        throw new Exception("Missing correct answers for enumeration question " . ($i + 1));
                    }
                    
                    $answers = explode(',', $_POST['correct_option'][$i]);
                    foreach ($answers as $answer) {
                        $answer = trim($conn->real_escape_string($answer));
                        if (!empty($answer)) {
                            $stmt = $conn->prepare("INSERT INTO answers (question_id, answer_text, is_correct) VALUES (?, ?, 1)");
                            $stmt->bind_param("is", $question_id, $answer);
                            
                            if (!$stmt->execute()) {
                                throw new Exception("Error saving enumeration answer: " . $stmt->error);
                            }
                        }
                    }
                    break;
                    
                case 'drag_and_drop':
                    if (empty($_POST['answers'][$i]) || !is_array($_POST['answers'][$i])) {
                        throw new Exception("Missing answers for drag and drop question " . ($i + 1));
                    }
                    
                    $correct_index = isset($_POST['correct_answer'][$i]) ? intval($_POST['correct_answer'][$i]) : -1;
                    if ($correct_index === -1) {
                        throw new Exception("No correct answer selected for drag and drop question " . ($i + 1));
                    }
                    
                    foreach ($_POST['answers'][$i] as $answer_index => $answer_text) {
                        if (empty(trim($answer_text))) continue;
                        
                        $is_correct = ($answer_index == $correct_index) ? 1 : 0;
                        $answer_text = trim($conn->real_escape_string($answer_text));
                        
                        $stmt = $conn->prepare("INSERT INTO answers (question_id, answer_text, is_correct) VALUES (?, ?, ?)");
                        $stmt->bind_param("isi", $question_id, $answer_text, $is_correct);
                        
                        if (!$stmt->execute()) {
                            throw new Exception("Error saving answer: " . $stmt->error);
                        }
                    }
                    break;
                    
                    case 'matching_type':
                        if (empty($_POST['left_items'][$i]) || empty($_POST['right_items'][$i]) || 
                            !is_array($_POST['left_items'][$i]) || !is_array($_POST['right_items'][$i])) {
                            throw new Exception("Missing left or right items for matching question " . ($i + 1));
                        }
                        
                        if (count($_POST['left_items'][$i]) !== count($_POST['right_items'][$i])) {
                            throw new Exception("Number of left and right items don't match for question " . ($i + 1));
                        }
                        
                        // Prepare arrays for left and right items
                        $left_items = [];
                        $right_items = [];
                        
                        foreach ($_POST['left_items'][$i] as $pair_index => $left_item) {
                            if (!isset($_POST['right_items'][$i][$pair_index])) continue;
                            
                            $left_item = trim($conn->real_escape_string($left_item));
                            $right_item = trim($conn->real_escape_string($_POST['right_items'][$i][$pair_index]));
                            
                            if (empty($left_item) || empty($right_item)) {
                                continue; // Skip empty pairs
                            }
                            
                            $left_items[] = $left_item;
                            $right_items[] = $right_item;
                            
                            // Changed from " - " to "|"
                            $answer_text = "$left_item|$right_item";
                            
                            $stmt = $conn->prepare("INSERT INTO answers (question_id, answer_text, is_correct) VALUES (?, ?, 1)");
                            $stmt->bind_param("is", $question_id, $answer_text);
                            
                            if (!$stmt->execute()) {
                                throw new Exception("Error saving matching pair: " . $stmt->error);
                            }
                        }
                        
                        // Update the question with left and right items as JSON
                        $left_json = json_encode($left_items);
                        $right_json = json_encode($right_items);
                        
                        $update_stmt = $conn->prepare("UPDATE questions SET left_items = ?, right_items = ? WHERE question_id = ?");
                        $update_stmt->bind_param("ssi", $left_json, $right_json, $question_id);
                        $update_stmt->execute();
                        break;
            }
        }
        
        $conn->commit();
        echo json_encode([
            'success' => true,
            'message' => 'Quiz created successfully!',
            'subject_id' => $subject_id,
            'quiz_id' => $quiz_id
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}

$conn->close();
exit;
?>