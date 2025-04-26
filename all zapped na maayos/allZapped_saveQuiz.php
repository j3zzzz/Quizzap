<?php
session_start();
if (strpos($_SESSION['account_number'], 'T') !== 0) {
    header("Location: login.php");
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
    die("Connection failed: " . $conn->connect_error);
}

$response = ["success" => false, "message" => "", "subject_id" => ""];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn->begin_transaction();
    
    try {
        // Get quiz details
        $title = $_POST['title'];
        $subject_id = intval($_POST['subject_id']);
        $timer = $_POST['timer'];
        $quiz_type = $_POST['quiz_type'];

        // Insert quiz
        $stmt = $conn->prepare("INSERT INTO quizzes (title, subject_id, timer, quiz_type) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("siis", $title, $subject_id, $timer, $quiz_type);
        
        if (!$stmt->execute()) {
            throw new Exception("Error creating quiz: " . $stmt->error);
        }
        
        $quiz_id = $conn->insert_id;
        
        // Process each question
        for ($i = 0; $i < count($_POST['questions']); $i++) {
            $question_type = $_POST['question_type'][$i];
            $question_text = $_POST['questions'][$i];
            
            // Insert question
            $stmt = $conn->prepare("INSERT INTO questions (quiz_id, question_type, question_text) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $quiz_id, $question_type, $question_text);
            
            if (!$stmt->execute()) {
                throw new Exception("Error creating question: " . $stmt->error);
            }
            
            $question_id = $conn->insert_id;
            
            // Handle question options based on type
            switch ($question_type) {
                case 'multiple_choice':
                    if (isset($_POST['answers'][$i]) && is_array($_POST['answers'][$i])) {
                        $answers = $_POST['answers'][$i];
                        $correct_answer_index = isset($_POST['correct'][$i]) ? 
                            intval($_POST['correct'][$i]) : 0;

                        foreach ($answers as $answer_index => $answer_text) {
                            $is_correct = ($answer_index === $correct_answer_index) ? 1 : 0;
                            
                            $stmt = $conn->prepare("INSERT INTO answers (question_id, answer_text, is_correct) VALUES (?, ?, ?)");
                            $stmt->bind_param("isi", $question_id, $answer_text, $is_correct);
                            
                            if (!$stmt->execute()) {
                                throw new Exception("Error creating multiple choice answer: " . $stmt->error);
                            }
                        }
                    }
                    break;
                case 'true_or_false':
                case 'fill_in_the_blanks':
                case 'identification':
                case 'enumeration':
            
                    // Store correct answer
                    $answer_text = $_POST['correct_option'][$i];
                        // For other question types, save as before
                        $stmt = $conn->prepare("INSERT INTO answers (question_id, answer_text, is_correct) VALUES (?, ?, 1)");
                        $stmt->bind_param("is", $question_id, $answer_text);
                        
                        if (!$stmt->execute()) {
                            throw new Exception("Error saving answer: " . $stmt->error);
                        }
    
                    break;

                case 'drag_and_drop':
                    if (isset($_POST['answers'][$i]) && is_array($_POST['answers'][$i])) {
                        $answers = $_POST['answers'][$i];
                        $correct_answer_index = isset($_POST['correct_answer'][$i]) ? 
                            intval($_POST['correct_answer'][$i]) : 0;

                        foreach ($answers as $answer_index => $answer_text) {
                            if (!empty($answer_text)) {
                                $is_correct = (intval($answer_index) === $correct_answer_index) ? 1 : 0;

                                $stmt = $conn->prepare("INSERT INTO answers (question_id, answer_text, is_correct) VALUES (?, ?, ?)");
                                $stmt->bind_param("isi", $question_id, $answer_text, $is_correct);
                                
                                if (!$stmt->execute()) {
                                    throw new Exception("Error creating drag and drop answer: " . $stmt->error);
                                }
                            }
                        }
                    }
                    break;

                case "matching_type":
                    // Process left images
                    $left_images = [];
                    if (isset($_FILES['left_images']['tmp_name'][$i])) {
                        foreach ($_FILES['left_images']['tmp_name'][$i] as $key => $tmp_name) {
                            if (!empty($tmp_name)) {
                                $original_name = $_FILES['left_images']['name'][$i][$key];
                                $file_name = uniqid() . '_' . $original_name;
                                $upload_path = 'uploads/left_images/' . $file_name;
                                
                                if (!is_dir('uploads/left_images')) {
                                    mkdir('uploads/left_images', 0777, true);
                                }
                                
                                if (move_uploaded_file($tmp_name, $upload_path)) {
                                    $left_images[] = $file_name;
                                } else {
                                    throw new Exception("Failed to upload left image: " . $original_name);
                                }
                            }
                        }
                    }

                    // Process right items
                    $right_items = [];
                    if (isset($_POST['right_items'][$i])) {
                        foreach ($_POST['right_items'][$i] as $right_item) {
                            if (!empty($right_item)) {
                                $right_items[] = $right_item;
                            }
                        }
                    }

                    // Process matching configuration
                    $matching_config = [];
                    if (isset($_POST['matching_config'][$i])) {
                        foreach ($_POST['matching_config'][$i] as $key => $match_index) {
                            // Ensure the match index is valid
                            if (isset($right_items[$match_index])) {
                                $matching_config[$key] = $right_items[$match_index];
                            }
                        }
                    }

                    // Encode paths and configurations
                    $left_items_json = json_encode($left_images);
                    $right_items_json = json_encode($right_items);
                    $matching_config_json = json_encode($matching_config);

                    // Update question with matching details
                    $stmt = $conn->prepare("UPDATE questions SET 
                        left_items = ?, 
                        right_items = ?, 
                        matching_config = ? 
                        WHERE question_id = ?");
                    $stmt->bind_param("sssi", 
                        $left_items_json, 
                        $right_items_json, 
                        $matching_config_json, 
                        $question_id
                    );
                    if (!$stmt->execute()) {
                        throw new Exception("Error saving matching question details: " . $stmt->error);
                    }

                    // Store answer for verification
                    $answer_text = $matching_config_json;
                    $stmt = $conn->prepare("INSERT INTO answers (question_id, answer_text, is_correct) VALUES (?, ?, 1)");
                    $stmt->bind_param("is", $question_id, $answer_text);
                    $stmt->execute();
                    break;
            }
        }
        
        $conn->commit();
        echo json_encode([
            'success' => true,
            'message' => 'Quiz created successfully!',
            'subject_id' => $subject_id
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    
    $conn->close();
    exit;
}
?>