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

    $subject_id = $_POST['subject_id'];
    $quiz_title = $_POST['title'];
    $timer = $_POST['timer'];
    $quiz_type = $_POST['quiz_type'];
    $questions = isset($_POST['questions']) ? $_POST['questions'] : [];
    $answers = $_POST['answers'] ?? [];
    $correct = isset($_POST['correct']) ? $_POST['correct'] : [];
    $correct_answer = $_POST['correct_answer'] ?? [];
    $blanks_answers = isset($_POST['blanks_answers']) ? $_POST['blanks_answers'] : [];

    $success = false;
    $message = "";

    if (empty($quiz_type)) {
        $response["message"] = "Quiz type is empty or not passed.";
    } else {
        // Insert the quiz into the 'quizzes' table
        $stmt = $conn->prepare("INSERT INTO quizzes (subject_id, title, timer, quiz_type) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isis", $subject_id, $quiz_title, $timer, $quiz_type);

        if ($stmt->execute()) {
            $quiz_id = $stmt->insert_id;
            $stmt->close();

            $allQuestionsInserted = true; // Flag for successful question insertion

            foreach ($questions as $index => $question) {
                $stmt = $conn->prepare("INSERT INTO questions (quiz_id, question_text) VALUES (?, ?)");
                $stmt->bind_param("is", $quiz_id, $question);

                if ($stmt->execute()) {
                    $question_id = $stmt->insert_id;

                    // Handle quiz type answers
                    if ($quiz_type == 'True or False') {
                        $answers = ['True', 'False'];
                        foreach ($answers as $answer) {
                            $is_correct = ($correct[$index] == $answer) ? 1 : 0;
                            $stmt = $conn->prepare("INSERT INTO answers (question_id, answer_text, is_correct) VALUES (?, ?, ?)");
                            $stmt->bind_param("isi", $question_id, $answer, $is_correct);
                            if (!$stmt->execute()) {
                                $allQuestionsInserted = false;
                                error_log("Error inserting True/False answer: " . $stmt->error);
                            }
                        }
                    } elseif ($quiz_type == 'Fill in the Blanks') {
                        if (isset($blanks_answers[$index]) && is_array($blanks_answers[$index])) {
                            foreach ($blanks_answers[$index] as $blank_answer) {
                                $blank_answer = trim($blank_answer);
                                $stmt_answer = $conn->prepare("INSERT INTO answers (question_id, answer_text, is_correct) VALUES (?, ?, 1)");
                                $stmt_answer->bind_param("is", $question_id, $blank_answer);
                                
                                if (!$stmt_answer->execute()) {
                                    $allQuestionsInserted = false;
                                    error_log("Error inserting fill in the blanks answer: " . $stmt_answer->error);
                                }
                                $stmt_answer->close();
                            }
                        } else {
                            $allQuestionsInserted = false;
                            error_log("No answers provided for fill in the blanks question at index " . $index);
                        }
                    } elseif ($quiz_type == 'Enumeration') {
                        $correct_answer_list = explode(',', $correct_answer[$index]);
                        foreach ($correct_answer_list as $answer) {
                            $answer = trim($answer);
                            $stmt_answer = $conn->prepare("INSERT INTO answers (question_id, answer_text, is_correct) VALUES (?, ?, 1)");
                            $stmt_answer->bind_param("is", $question_id, $answer);
                            if (!$stmt_answer->execute()) {
                                $allQuestionsInserted = false;
                                error_log("Error inserting enumeration answer: " . $stmt_answer->error);
                            }
                            $stmt_answer->close();
                        }
                    } elseif ($quiz_type == 'Identification') {
                        $correct_answer_list = explode(',', $correct_answer[$index]);
                        foreach ($correct_answer_list as $answer) {
                            $answer = trim($answer);
                            $stmt_answer = $conn->prepare("INSERT INTO answers (question_id, answer_text, is_correct) VALUES (?, ?, 1)");
                            $stmt_answer->bind_param("is", $question_id, $answer);
                            if (!$stmt_answer->execute()) {
                                $allQuestionsInserted = false;
                                error_log("Error inserting identification answer: " . $stmt_answer->error);
                            }
                            $stmt_answer->close();
                        }
                    } elseif ($quiz_type == 'Matching Type') {
                        $lefts = array_map('trim', explode(',', $_POST['left_items'][$index] ?? ''));
                        $rights = array_map('trim', explode(',', $_POST['right_items'][$index] ?? ''));
                        $matching_config = $_POST['matching_config'][$index] ?? '[]';

                        $stmt_answer = $conn->prepare("INSERT INTO answers (question_id, answer_text, is_correct, left_items, right_items, matching_config) VALUES (?, ?, ?, ?, ?, ?)");
                        
                        foreach ($lefts as $i => $left) {
                            if (empty($left) || empty($rights[$i] ?? '')) continue;
                            $match_pair = "$left - {$rights[$i]}";
                            $is_correct = 1;
                            $stmt_answer->bind_param("isisss", $question_id, $match_pair, $is_correct, 
                                $_POST['left_items'][$index], 
                                $_POST['right_items'][$index], 
                                $matching_config);
                            
                            if(!$stmt_answer->execute()) {
                                $allQuestionsInserted = false;
                                error_log("Error inserting Matching Type answer: " . $stmt_answer->error);
                            }
                        }
                        
                        $stmt_answer->close();
                    }elseif ($quiz_type == 'Drag & Drop') {
                        if (isset($answers[$index]) && is_array($answers[$index])) {

                            //error_log("Processing question $index with correct answer: " . print_r($correct_answer[$index], true));

                            foreach ($answers[$index] as $answer_index => $answer_text) {

                                $is_correct = (isset($correct_answer[$index]) && $correct_answer[$index] == $answer_index) ? 1 : 0;

                                $stmt_answer = $conn->prepare("INSERT INTO answers (question_id, answer_text, is_correct) VALUES (?, ?, ?)");
                                $stmt_answer->bind_param("isi", $question_id, $answer_text, $is_correct);

                                if(!$stmt_answer->execute()) {
                                    $allQuestionsInserted = false;
                                    error_log("Error inserting Drag & Drop answer: " . $stmt_answer->error);
                                }
                            }
                        }                            
                    } 
                    //pang multiple choice na quiz
                    else {
                        foreach ($answers[$index] as $answer_index => $answer) {
                            $is_correct = ($correct[$index] == $answer_index) ? 1 : 0;
                            $stmt = $conn->prepare("INSERT INTO answers (question_id, answer_text, is_correct) VALUES (?, ?, ?)");
                            $stmt->bind_param("isi", $question_id, $answer, $is_correct);
                            if (!$stmt->execute()) {
                                $allQuestionsInserted = false;
                                error_log("Error inserting other type answer: " . $stmt->error);
                            }
                        }
                    }
                }  else {
                    $allQuestionsInserted = false;
                    error_log("Invalid or missing answers for Matching Type question at index " . $index);
                }
            }

            if ($allQuestionsInserted) {
                $response["success"] = true;
                $response["message"] = "Quiz and all questions created successfully.";
            } else {
                $response["message"] = "Quiz created but some questions or answers failed to insert.";
            }

            $response["subject_id"] = $subject_id;
        } else {
            error_log("Error inserting quiz: " . $stmt->error);
            $response["message"] = "Error creating quiz: " . $stmt->error;
        }
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