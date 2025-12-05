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
    echo json_encode(["success" => false, "error" => "Connection failed: " . $conn->connect_error]);
    exit;
}

if (!isset($_GET['quiz_id'])) {
    echo json_encode(["success" => false, "error" => "Quiz ID is not specified."]);
    exit;
}

$quiz_id = $_GET['quiz_id'];

$stmt = $conn->prepare("SELECT * FROM quizzes WHERE quiz_id = ?");
$stmt->bind_param("i", $quiz_id);
$stmt->execute();
$result = $stmt->get_result();
$quiz = $result->fetch_assoc();
$stmt->close();

if(!$quiz) {
    echo json_encode(["success" => false, "error" => "Quiz not found."]);
    exit();
}

$quiz_type = $quiz['quiz_type'];
$subject_id = $quiz['subject_id'];

$student_id = $_SESSION['account_number'];
$studentQuery = "SELECT student_id FROM students WHERE account_number = ?";
$stmt = $conn->prepare($studentQuery);
$stmt->bind_param("s", $_SESSION['account_number']);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();
$stmt->close();

$attempt_id = null;
$remaining_time = null;
$prefilledAnswers = [];

$showModal = false;
$modalTitle = "";
$modalMessage = "";
$modalRedirect = "";

if ($student) {
    $attemptQuery = "SELECT * FROM quiz_attempts 
                    WHERE quiz_id = ? AND account_number = ? 
                    ORDER BY attempt_id DESC LIMIT 1";
    $stmt = $conn->prepare($attemptQuery);
    $stmt->bind_param("is", $quiz_id, $student_id);
    $stmt->execute();
    $existingAttempt = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existingAttempt && !$existingAttempt['completed']) {
        $attempt_id = $existingAttempt['attempt_id'];
        
        $start_time = strtotime($existingAttempt['attempt_time']);
        $current_time = time();
        $elapsed = $current_time - $start_time;
        
        $quiz_duration_seconds = $quiz['timer'] * 60;
        $remaining_time = max(0, $quiz_duration_seconds - $elapsed);
        
        if ($remaining_time <= 0) {
            $completeStmt = $conn->prepare("UPDATE quiz_attempts SET completed = 1, score = 0 WHERE attempt_id = ?");
            $completeStmt->bind_param("i", $attempt_id);
            $completeStmt->execute();
            $completeStmt->close();
            exit();
        }
        
        $updateTimeQuery = "UPDATE quiz_attempts SET time_remaining = ? WHERE attempt_id = ?";
        $stmt = $conn->prepare($updateTimeQuery);
        $stmt->bind_param("ii", $remaining_time, $attempt_id);
        $stmt->execute();
        $stmt->close();
        
        $answersQuery = "SELECT question_id, answer FROM student_answers 
                        WHERE quiz_id = ? AND student_id = ?";
        $stmt = $conn->prepare($answersQuery);
        $stmt->bind_param("ii", $quiz_id, $student['student_id']);
        $stmt->execute();
        $savedAnswers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        foreach ($savedAnswers as $answer) {
            $decoded = json_decode($answer['answer'], true);
            $prefilledAnswers[$answer['question_id']] = ($decoded !== null) ? $decoded : $answer['answer'];
        }
        $stmt->close();
        
    } else {
        $quiz_duration_seconds = $quiz['timer'] * 60;
        
        $insertAttempt = "INSERT INTO quiz_attempts (quiz_id, account_number, attempt_time, time_remaining) 
                        VALUES (?, ?, NOW(), ?)";
        $stmt = $conn->prepare($insertAttempt);
        $stmt->bind_param("isi", $quiz_id, $student_id, $quiz_duration_seconds);
        $stmt->execute();
        $attempt_id = $conn->insert_id;
        $remaining_time = $quiz_duration_seconds;
        $prefilledAnswers = [];
        $stmt->close();
    }
}

if (!isset($remaining_time)) {
    $remaining_time = $quiz['timer'] * 60;
}

// Check availability
$currentDate = date('Y-m-d H:i:s');
if ($quiz['start_date'] && $currentDate < $quiz['start_date']) {
    $showModal = true;
    $modalTitle = "Quiz Not Available";
    $modalMessage = "This quiz is not available yet. It will be available starting " . date('M j, Y g:i A', strtotime($quiz['start_date']));
    $modalRedirect = "select_quiz.php?subject_id=" . $subject_id;
} 
else if ($quiz['end_date'] && $currentDate > $quiz['end_date']) {
    $showModal = true;
    $modalTitle = "Quiz Not Available";
    $modalMessage = "This quiz is no longer available. It ended on " . date('M j, Y g:i A', strtotime($quiz['end_date']));
    $modalRedirect = "select_quiz.php?subject_id=" . $subject_id;
}

$sql = "SELECT * FROM questions WHERE quiz_id = $quiz_id";
$result = $conn->query($sql);

$questions = [];
while ($row = $result->fetch_assoc()) {
    $questions[] = $row;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link rel="stylesheet" type="text/css" href="other resources/fontawesome-free-6.5.2-web/css/all.min.css">
    <title>Take Quiz</title>
    
    <style type="text/css">
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, Helvetica, sans-serif;
            background-color: #ffffff;
            transition: background-color 0.3s, color 0.3s;
        }

        body.dark-mode {
            background-color: #1a1a1a;
            color: #e0e0e0;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            background-color: white;
            position: relative;
        }

        body.dark-mode header {
            background-color: #1a1a1a;
        }

        header .actions {
            display: flex;
            align-items: center;
            margin-left: auto;
        }

        header .profile {
            display: flex;
            align-items: center;
            margin-left: auto;
        }

        header .profile img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #f8b500;
        }

        .quiz-container {
            background-color: #FFFFFF;
            font-family: Fredoka;
            color: #f8b500;
            margin: 2% auto 5%;
            padding: 40px 50px;
            width: 90%;
            border: 2px solid #f8b500;
            border-radius: 10px;
            box-shadow: 4px 4px 0 0 #BC8900;
            transition: background-color 0.3s, border-color 0.3s;
        }

        body.dark-mode .quiz-container {
            background-color: #2d2d2d;
            border-color: #f8b500;
            color: #e0e0e0;
        }

        .quiz-header {
            text-align: center;
            margin-bottom: 20px;
        }

        .timer {
            background-color: white;
            font-family: Fredoka;
            font-size: 20px;
            color: #707070;
            display: flex;
            padding: 10px;
            width: 7%;
            margin-top: -4%;
            float: right;
            border-radius: 5px;
            text-align: center;
            vertical-align: middle;
            align-content: center;
            border: 2px solid #f8b500;
            transition: background-color 0.3s;
        }

        body.dark-mode .timer {
            background-color: #2d2d2d;
            color: #e0e0e0;
        }

        #tts {
            margin-top: 1%;
            position: absolute;
            font-size: 25px;
            cursor: pointer;
            padding: 4px 5px;
            background-color: transparent;
            transition: 0.3s;
        }

        #tts:hover {
            background-color: #f8b500;
            color: white;
            border-radius: 5px;
        }

        .speaker .speaker-tooltip {
            visibility: hidden;
            width: 120px;
            background-color: #f8b500;
            text-align: center;
            border-radius: 6px;
            padding: 5px 0;
            position: absolute;
            z-index: 1;
            top: 37%;
            left: 12%;
            color: white;
        }

        .speaker .speaker-tooltip::after {
            content: "";
            position: absolute;
            top: 50%;
            right: 100%;
            margin-top: -5%;
            border-width: 5px;
            border-style: solid;
            border-color: transparent #f8b500 transparent transparent;
        }

        .speaker:hover .speaker-tooltip {
            visibility: visible;
        }

        .question-text {
            color: black;
            font-size: 23px;
            font-weight: 600;
            margin-bottom: 15px;
            position: relative;
            padding-right: 40px;
        }

        body.dark-mode .question-text {
            color: #e0e0e0;
        }

        .question {
            background-color: #fff5e1;
            border: 1px solid #f0c808;
            border-radius: 8px;
            padding: 30px;
            margin-bottom: 30px;
            transition: background-color 0.3s, border-color 0.3s;
        }

        body.dark-mode .question {
            background-color: #333;
            border-color: #f8b500;
        }

        .answers {
            display: grid;
            gap: 10px;
        }

        .answer-button {
            display: block;
            width: 100%;
            padding: 10px;
            background-color: #f9f9f9;
            border: 1px solid #f8b500;
            border-radius: 5px;
            cursor: pointer;
            color: black;
            text-align: left;
            font-family: Fredoka;
            font-size: 17px;
            margin-bottom: 4px;
            transition: all 0.3s;
        }

        body.dark-mode .answer-button {
            background-color: #2d2d2d;
            color: #e0e0e0;
        }

        .answer-button:hover {
            background-color: #f8b500;
            color: white;
        }

        .answer-button.selected {
            background-color: #f8b500;
            color: white;
        }

        input[type="text"] {
            width: 100%;
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #f8b500;
            font-size: 17px;
            font-family: Fredoka;
            margin-bottom: 10px;
            transition: background-color 0.3s, color 0.3s, border-color 0.3s;
        }

        body.dark-mode input[type="text"] {
            background-color: #2d2d2d;
            color: #e0e0e0;
            border-color: #f8b500;
        }

        .drag-item {
            background-color: #fff5e1;
            border: 1px solid #f8b500;
            padding: 10px;
            margin: 5px 0;
            cursor: move;
            border-radius: 5px;
            color: black;
            transition: background-color 0.3s;
        }

        body.dark-mode .drag-item {
            background-color: #333;
            color: #e0e0e0;
        }

        .drop-zone {
            border: 2px dashed #f8b500;
            border-radius: 10px;
            padding: 15px;
            min-height: 50px;
            margin-bottom: 15px;
            transition: background-color 0.3s;
        }

        body.dark-mode .drop-zone {
            background-color: rgba(45, 45, 45, 0.5);
        }

        .drop-zone h4 {
            font-weight: lighter;
            margin-bottom: 10px;
            color: #333;
        }

        body.dark-mode .drop-zone h4 {
            color: #e0e0e0;
        }

        .match-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .left-items, .right-items {
            border: 1px solid #f8b500;
            border-radius: 8px;
            padding: 15px;
            background-color: #fff5e1;
            color: black;
            font-weight: 500;
            transition: background-color 0.3s;
        }

        body.dark-mode .left-items,
        body.dark-mode .right-items {
            background-color: #333;
            color: #e0e0e0;
        }

        .match-item {
            padding: 10px;
            margin: 5px 0;
            cursor: pointer;
            border-radius: 5px;
            background-color: #f9f9f9;
            border: 1px solid #ddd;
            color: black;
            transition: all 0.3s;
        }

        body.dark-mode .match-item {
            background-color: #2d2d2d;
            border-color: #f8b500;
            color: #e0e0e0;
        }

        .match-item:hover {
            background-color: #f8b500;
            color: white;
        }

        .match-item.selected {
            background-color: #f8b500;
            color: white;
        }

        .match-item.matched {
            background-color: #FCEF91;
            border-color: #f8b500;
            color: black;
            cursor: default;
        }

        body.dark-mode .match-item.matched {
            background-color: #f8b500;
            color: white;
        }

        .pairs-display {
            margin-top: 20px;
            padding: 15px;
            background-color: #f9f9f9;
            border-radius: 8px;
            border: 1px solid #ddd;
            transition: background-color 0.3s;
        }

        body.dark-mode .pairs-display {
            background-color: #333;
            border-color: #f8b500;
        }

        .clear-matches-btn {
            margin-top: 10px;
            padding: 8px 15px;
            background-color: #dc3545;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .match-pair {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 12px;
            background-color: #FCEF91;
            border: 1px solid #F8B500;
            border-radius: 5px;
            margin-bottom: 5px;
        }

        body.dark-mode .match-pair {
            background-color: rgba(248, 181, 0, 0.2);
        }

        .remove-match-btn {
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 3px;
            padding: 2px 6px;
            cursor: pointer;
            font-size: 12px;
            margin-left: 10px;
        }

        .remove-match-btn:hover {
            background: #c82333;
        }

        .submit-btn {
            display: block;
            width: 100%;
            padding: 15px;
            background-color: #f8b500;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 18px;
            font-family: Fredoka;
            cursor: pointer;
            margin-top: 20px;
            box-shadow: 0 5px 0 0 #BC8900;
            transition: background-color 0.3s;
        }

        .submit-btn:hover {
            background-color: #e6a500;
        }

        /* Loading message styles */
        .loading-message {
            position: fixed;
            top: 10%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: rgba(255, 255, 255, 0.9);
            padding: 20px 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 10px;
            font-family: Fredoka;
            color: #333;
            border: 1px solid #f8b500;
        }

        body.dark-mode .loading-message {
            background-color: rgba(45, 45, 45, 0.9);
            color: #e0e0e0;
        }

        .loading-spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #f8b500;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            animation: spin 1s linear infinite;
        }

        body.dark-mode .loading-spinner {
            border: 3px solid #333;
            border-top: 3px solid #f8b500;
        }

        .success-message {
            color: #28a745;
        }

        .fade-out {
            animation: fadeOut 1.5s ease-out forwards;
        }

        @keyframes fadeOut {
            0% { opacity: 1; }
            100% { opacity: 0; visibility: hidden; }
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Check icon styles */
        .check-icon {
            width: 24px;
            height: 24px;
            margin-right: 10px;
        }

        .check-icon-svg {
            width: 100%;
            height: 100%;
        }

        .check-icon-path {
            stroke-dasharray: 24;
            stroke-dashoffset: 24;
            animation: drawCheck 0.5s ease-in-out forwards;
        }

        @keyframes drawCheck {
            to {
                stroke-dashoffset: 0;
            }
        }

        .auto-save-success .check-icon-path {
            stroke: #28a745;
        }

        .auto-save-error .check-icon-path {
            stroke: #dc3545;
        }

        .instruction-label {
            font-weight: 500;
            color: black;
            margin-bottom: 3px;
        }

        body.dark-mode .instruction-label {
            color: #e0e0e0;
        }

        .instruction-text {
            color: #555;
            font-size: 15px;
            margin-bottom: 15px;
            padding: 8px;
            border-radius: 0 4px 4px 0;
        }

        body.dark-mode .instruction-text {
            color: #b0b0b0;
        }

        /* Modal Styles */
        .modal {
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            font-family: Fredoka;
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            width: 80%;
            max-width: 400px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            animation: fadeIn 0.3s ease-out;
            position: relative;
            z-index: 1001;
            transition: background-color 0.3s, color 0.3s;
        }

        body.dark-mode .modal-content {
            background-color: #2d2d2d;
            color: #e0e0e0;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .modal-close-btn {
            background-color: #f8b500;
            color: white;
            border: none;
            padding: 10px 20px;
            margin-top: 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-family: Fredoka;
        }

        .modal-close-btn:hover {
            background-color: #e6a500;
        }

        #reloadConfirmModal {
            z-index: 9999 !important; 
            background-color: rgba(0,0,0,0.8) !important;
            backdrop-filter: blur(2px);
        }

        #reloadConfirmModal .modal-content {
            z-index: 10000 !important;
            border: 2px solid #f8b500;
        }

        .modal-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 20px;
        }

        .modal-confirm-btn {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-family: Fredoka;
        }

        .modal-confirm-btn:hover {
            background-color: #c82333;
        }

        .modal-cancel-btn {
            background-color: #6c757d;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-family: Fredoka;
        }

        .modal-cancel-btn:hover {
            background-color: #5a6268;
        }

        #timeUpModal {
            z-index: 10001 !important;
            background-color: rgba(0,0,0,0.85) !important;
            backdrop-filter: blur(3px);
        }

        #timeUpModal .modal-content {
            z-index: 10002 !important;
            border: 3px solid #f8b500;
            animation: modalPulse 0.5s ease-out;
        }

        #timeUpModal h2 {
            font-family: Fredoka;
            color: #f8b500;
        }

        #timeUpModal p {
            font-family: Fredoka;
            color: #333;
        }

        body.dark-mode #timeUpModal p {
            color: #e0e0e0;
        }

        #timeUpModal .modal-confirm-btn {
            background-color: #f8b500;
            font-weight: bold;
            padding: 12px 20px;
            font-size: 18px;
            box-shadow: 0 4px 0 0 #BC8900;
            transition: all 0.2s;
        }

        #timeUpModal .modal-confirm-btn:hover {
            background-color: #e0a500;
            transform: translateY(-2px);
            box-shadow: 0 6px 0 0 #BC8900;
        }

        #timeUpModal .modal-confirm-btn:active {
            transform: translateY(2px);
            box-shadow: 0 2px 0 0 #BC8900;
        }

        @keyframes modalPulse {
            0% {
                transform: scale(0.8);
                opacity: 0;
            }
            50% {
                transform: scale(1.05);
            }
            100% {
                transform: scale(1);
                opacity: 1;
            }
        }

        /* Question navigation */
        .question-navigation {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin: 30px 0;
            flex-wrap: wrap;
        }

        .question-nav-btn {
            width: 40px;
            height: 40px;
            border-radius: 5px;
            border: 1px solid #f8b500;
            background-color: white;
            color: #f8b500;
            font-family: Fredoka;
            font-size: 16px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }

        body.dark-mode .question-nav-btn {
            background-color: #2d2d2d;
            color: #f8b500;
        }

        .question-nav-btn:hover {
            background-color: #f8b500;
            color: white;
        }

        .question-nav-btn.answered {
            background-color: #f8b500;
            color: white;
        }

        .question-nav-btn.current {
            background-color: #333;
            color: white;
            border-color: #333;
        }

        body.dark-mode .question-nav-btn.current {
            background-color: #f8b500;
            border-color: #f8b500;
        }

        .nav-controls {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
        }

        .nav-btn {
            padding: 10px 30px;
            background-color: #f8b500;
            color: white;
            border: none;
            border-radius: 5px;
            font-family: Fredoka;
            font-size: 16px;
            cursor: pointer;
            box-shadow: 0 3px 0 0 #BC8900;
            transition: background-color 0.3s;
        }

        .nav-btn:hover {
            background-color: #e6a500;
        }

        .nav-btn:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
            box-shadow: 0 3px 0 0 #999;
        }

        body.dark-mode .nav-btn:disabled {
            background-color: #555;
        }

        /* TTS Button in Question */
        .tts-button {
            position: absolute;
            right: 10px;
            top: 0;
            cursor: pointer;
            color: #f8b500;
            font-size: 20px;
            padding: 5px;
            transition: all 0.3s;
        }

        .tts-button:hover {
            background-color: #f8b500;
            color: white;
            border-radius: 5px;
        }

        /* Dark Mode Toggle */
        .dark-mode-toggle {
            position: fixed;
            bottom: 20px;
            right: 20px;
            top: auto;
            z-index: 100;
            background-color: #f8b500;
            color: white;
            border: none;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            
            transition: all 0.3s;
        }

        body.dark-mode .dark-mode-toggle {
            background-color: #333;
            color: #f8b500;
        }

        .dark-mode-toggle:hover {
            background-color: #e6a500;
            transform: scale(1.1);
        }

        /* Base responsive adjustments */
        @media screen and (max-width: 1200px) {
            .quiz-container {
                width: 95%;
                padding: 30px 40px;
            }
        }

        /* Tablet (768px) */
        @media screen and (max-width: 768px) {
            * {
                font-size: 15px;
            }
            
            header {
                padding: 15px;
            }
            
            header .profile img {
                width: 45px;
                height: 45px;
            }
            
            .dark-mode-toggle {
                bottom: 15px;
                right: 15px;
                width: 45px;
                height: 45px;
                font-size: 18px;
            }
            
            header .logo {
                margin-left: 15px;
                margin-top: 0;
            }
            
            header .logo img {
                width: 150px;
                height: 60px;
            }
            
            .quiz-container {
                width: 96%;
                padding: 25px 30px;
                margin: 3% auto 8%;
            }
            
            .quiz-header h1 {
                font-size: 24px;
                margin-bottom: 15px;
            }
            
            .timer {
                width: auto;
                min-width: 80px;
                margin-top: -3%;
                font-size: 18px;
                padding: 8px 15px;
                position: relative;
                float: none;
                display: inline-block;
            }
            
            #tts {
                font-size: 20px;
                margin-top: 0.5%;
            }
            
            .question {
                padding: 20px;
                margin-bottom: 25px;
            }
            
            .question-text {
                font-size: 20px;
                padding-right: 35px;
            }
            
            .answer-button {
                font-size: 16px;
                padding: 12px 15px;
            }
            
            .match-container {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .left-items, .right-items {
                padding: 12px;
            }
            
            .question-navigation {
                gap: 8px;
                margin: 20px 0;
            }
            
            .question-nav-btn {
                width: 35px;
                height: 35px;
                font-size: 14px;
            }
            
            .nav-btn {
                padding: 12px 25px;
                font-size: 15px;
            }
            
            .submit-btn {
                padding: 12px;
                font-size: 16px;
            }
            
            .instruction-text {
                font-size: 14px;
            }
            
            .modal-content {
                width: 90%;
                max-width: 350px;
                padding: 25px;
            }
            
            .speaker .speaker-tooltip {
                left: 10%;
                top: 45%;
            }
            
            /* Drag & Drop responsiveness */
            .drop-zone {
                padding: 12px;
            }
            
            .drag-item {
                padding: 8px;
                font-size: 15px;
            }
        }

        /* Small tablets and large phones (576px) */
        @media screen and (max-width: 576px) {
            header {
                padding: 12px;
                flex-direction: row; /* Keep it as row */
                text-align: left; /* Align to left */
            }
            
            header .logo {
                margin: 0;
            }
            
            header .actions {
                width: auto;
                display: flex;
                justify-content: flex-end;
                margin-left: auto;
            }
            
            header .profile img {
                width: 40px;
                height: 40px;
            }
            
            .dark-mode-toggle {
                bottom: 12px;
                right: 12px;
                width: 40px;
                height: 40px;
                font-size: 16px;
            }
            
            .quiz-container {
                width: 97%;
                padding: 20px;
                margin: 4% auto 10%;
                border-width: 1.5px;
            }
            
            .quiz-header {
                text-align: center;
                margin-bottom: 15px;
            }
            
            #quiz-header {
                display: flex;
                flex-direction: column;
                align-items: center;
            }
            
            .timer {
                margin-top: 10px;
                order: 2;
                align-self: center;
            }
            
            .question-text {
                font-size: 18px;
                line-height: 1.4;
            }
            
            .tts-button {
                font-size: 18px;
                top: -2px;
                right: 5px;
            }
            
            .answer-button {
                font-size: 15px;
                padding: 10px 12px;
                margin-bottom: 8px;
            }
            
            input[type="text"] {
                font-size: 15px;
                padding: 10px;
            }
            
            .match-item {
                padding: 8px 10px;
                font-size: 14px;
            }
            
            .question-navigation {
                gap: 6px;
                margin: 15px 0;
            }
            
            .question-nav-btn {
                width: 30px;
                height: 30px;
                font-size: 13px;
            }
            
            .nav-controls {
                flex-direction: column;
                gap: 10px;
                margin-top: 20px;
            }
            
            .nav-btn {
                width: 100%;
            }
            
            .submit-btn {
                font-size: 15px;
                padding: 12px;
            }
            
            .loading-message {
                width: 90%;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                padding: 15px 20px;
            }
            
            .modal-content {
                width: 95%;
                max-width: 320px;
                padding: 20px;
            }
            
            .modal-buttons {
                flex-direction: column;
                gap: 8px;
            }
            
            .modal-confirm-btn, 
            .modal-cancel-btn, 
            .modal-close-btn {
                width: 100%;
            }
            
            /* Drag & Drop adjustments */
            .answers {
                display: flex;
                flex-direction: column;
                gap: 15px;
            }
            
            .drop-zone {
                min-height: 40px;
            }
            
            /* Matching type adjustments */
            .pairs-display {
                padding: 12px;
            }
            
            .match-pair {
                flex-direction: column;
                text-align: center;
                gap: 5px;
                padding: 10px;
            }
            
            .match-pair span {
                width: 100%;
                text-align: center;
            }
            
            .clear-matches-btn {
                width: 100%;
                padding: 10px;
            }
        }

        /* Mobile (425px) */
        @media screen and (max-width: 425px) {
            header .profile img {
                width: 35px;
                height: 35px;
            }
            
            .dark-mode-toggle {
                bottom: 10px;
                right: 10px;
                width: 35px;
                height: 35px;
                font-size: 14px;
            }

            .quiz-container {
                padding: 15px;
                margin: 5% auto 12%;
            }
            
            .quiz-header h1 {
                font-size: 20px;
                margin-bottom: 10px;
            }
            
            .timer {
                font-size: 16px;
                padding: 6px 12px;
                min-width: 70px;
            }
            
            .question {
                padding: 15px;
                margin-bottom: 20px;
            }
            
            .question-text {
                font-size: 16px;
                padding-right: 30px;
            }
            
            .tts-button {
                font-size: 16px;
                padding: 4px;
            }
            
            #tts {
                font-size: 18px;
            }
            
            .answer-button {
                font-size: 14px;
                padding: 8px 10px;
            }
            
            .instruction-label {
                font-size: 14px;
            }
            
            .instruction-text {
                font-size: 13px;
                padding: 6px;
            }
            
            .match-container {
                gap: 10px;
            }
            
            .left-items, .right-items {
                padding: 10px;
            }
            
            .left-items h4, .right-items h4 {
                font-size: 14px;
            }
            
            .match-item {
                font-size: 13px;
                padding: 6px 8px;
            }
            
            .question-navigation {
                gap: 5px;
                margin: 10px 0;
            }
            
            .question-nav-btn {
                width: 28px;
                height: 28px;
                font-size: 12px;
            }
            
            .submit-btn {
                font-size: 14px;
                padding: 10px;
            }
            
            .modal-content h2 {
                font-size: 18px;
            }
            
            .modal-content p {
                font-size: 14px;
            }
            
            /* Drag items text size */
            .drag-item {
                font-size: 14px;
                padding: 6px;
            }
            
            .drop-zone h4 {
                font-size: 14px;
            }
        }

        /* Small mobile (375px) */
        @media screen and (max-width: 375px) {
            header .logo img {
                width: 120px;
                height: 50px;
            }
            
            header .profile img {
                width: 32px;
                height: 32px;
            }
            
            .dark-mode-toggle {
                bottom: 8px;
                right: 8px;
                width: 32px;
                height: 32px;
                font-size: 12px;
            }
            
            .quiz-container {
                padding: 12px;
                margin: 6% auto 15%;
                border-width: 1px;
            }
            
            .quiz-header h1 {
                font-size: 18px;
            }
            
            .timer {
                font-size: 14px;
                padding: 5px 10px;
                min-width: 60px;
            }
            
            .question {
                padding: 12px;
            }
            
            .question-text {
                font-size: 15px;
            }
            
            .answer-button {
                font-size: 13px;
                padding: 7px 8px;
            }
            
            input[type="text"] {
                font-size: 14px;
                padding: 8px;
            }
            
            .match-item {
                font-size: 12px;
            }
            
            .question-navigation {
                gap: 4px;
            }
            
            .question-nav-btn {
                width: 25px;
                height: 25px;
                font-size: 11px;
            }
            
            .nav-btn {
                padding: 10px 20px;
                font-size: 14px;
            }
            
            .submit-btn {
                font-size: 13px;
                padding: 10px;
            }
            
            .loading-message {
                padding: 12px 15px;
                font-size: 14px;
            }
            
            .loading-spinner {
                width: 16px;
                height: 16px;
            }
            
            .check-icon {
                width: 20px;
                height: 20px;
            }
            
            .modal-content {
                padding: 15px;
            }
            
            /* Adjust match pair display for very small screens */
            .match-pair {
                font-size: 12px;
                padding: 8px;
            }
            
            .remove-match-btn {
                padding: 1px 4px;
                font-size: 10px;
            }
        }

        /* Very small mobile (320px) */
        @media screen and (max-width: 320px) {
            .quiz-container {
                width: 98%;
                padding: 10px;
            }
            
            .question-text {
                font-size: 14px;
            }
            
            .answer-button {
                font-size: 12px;
            }
            
            .question-nav-btn {
                width: 23px;
                height: 23px;
                font-size: 10px;
            }
            
            header .profile img {
                width: 30px;
                height: 30px;
            }
            
            .dark-mode-toggle {
                width: 30px;
                height: 30px;
                font-size: 11px;
                bottom: 5px;
                right: 5px;
            }
        }

        /* Orientation adjustments */
        @media screen and (max-height: 600px) and (orientation: landscape) {
            .quiz-container {
                margin: 2% auto 5%;
                padding: 15px 20px;
            }
            
            .question {
                padding: 15px;
                margin-bottom: 15px;
            }
            
            .question-navigation {
                margin: 15px 0;
            }
            
            .nav-controls {
                margin-top: 15px;
            }
            
            .submit-btn {
                margin-top: 15px;
                padding: 10px;
            }
        }

        /* High DPI screens */
        @media (-webkit-min-device-pixel-ratio: 2), (min-resolution: 192dpi) {
            .quiz-container {
                border-width: 1.5px;
                box-shadow: 2px 2px 0 0 #BC8900;
            }
            
            .submit-btn {
                box-shadow: 0 3px 0 0 #BC8900;
            }
            
            .answer-button {
                border-width: 1.5px;
            }
        }

        /* Touch device optimizations */
        @media (hover: none) and (pointer: coarse) {
            .answer-button,
            .match-item,
            .nav-btn,
            .question-nav-btn,
            .submit-btn,
            .modal-close-btn,
            .modal-confirm-btn,
            .modal-cancel-btn {
                min-height: 44px; /* Minimum touch target size */
            }
            
            .question-nav-btn {
                min-width: 44px;
                min-height: 44px;
            }
            
            .tts-button {
                padding: 10px;
                min-width: 44px;
                min-height: 44px;
            }
            
            .drag-item {
                padding: 12px;
                min-height: 44px;
            }
        }

        /* Prevent text size adjustment on mobile */
        @media screen and (max-width: 768px) {
            html {
                -webkit-text-size-adjust: 100%;
                text-size-adjust: 100%;
            }
            
            body {
                -webkit-font-smoothing: antialiased;
                -moz-osx-font-smoothing: grayscale;
            }
        }

        /* Ensure images are responsive */
        img {
            max-width: 100%;
            height: auto;
        }

        /* Improve scroll behavior on mobile */
        @media screen and (max-width: 768px) {
            html {
                scroll-behavior: smooth;
            }
            
            body {
                overflow-x: hidden;
            }
        }

        @viewport {
            width: device-width;
            zoom: 1.0;
        }

        /* Fix for iOS Safari 100vh issue */
        @media screen and (max-width: 768px) {
            body {
                min-height: -webkit-fill-available;
            }
            
            .quiz-container {
                min-height: -webkit-fill-available;
            }
        }
    </style>

    <script>
        function showAlertModal(title, message, redirectUrl = null) {
            document.getElementById("modalTitle").textContent = title;
            document.getElementById("modalMessage").textContent = message;
            document.getElementById("quizAlertModal").style.display = "flex";
            
            if (redirectUrl) {
                document.getElementById("quizAlertModal").dataset.redirect = redirectUrl;
            }
        }

        function handleModalClose() {
            const modal = document.getElementById("quizAlertModal");
            const redirectUrl = modal.dataset.redirect;
            modal.style.display = "none";
            
            if (redirectUrl) {
                window.location.href = redirectUrl;
            }
        }

        let isReloadConfirmed = false;
        let isModalShowing = false;
        let isSubmitting = false;

        function confirmReload() {
            isReloadConfirmed = true;
            isModalShowing = false;
            document.getElementById("reloadConfirmModal").style.display = "none";
            
            window.onbeforeunload = null;
            
            if (Object.keys(userAnswers).length > 0) {
                submitQuiz(true);
            } else {
                setTimeout(() => {
                    window.location.reload();
                }, 100);
            }
        }

        function cancelReload() {
            isModalShowing = false;
            document.getElementById("reloadConfirmModal").style.display = "none";
        }

        function showReloadConfirmModal() {
            if (isModalShowing || isReloadConfirmed || isSubmitting) {
                return false;
            }
            
            isModalShowing = true;
            document.getElementById("reloadConfirmModal").style.display = "flex";
            return true;
        }

        window.addEventListener("popstate", function(e) {
            if (!isReloadConfirmed && !isSubmitting && !isModalShowing) {
                e.preventDefault();
                showReloadConfirmModal();
                history.pushState(null, null, window.location.href);
            }
        });

        history.pushState(null, null, window.location.href);

        // Dark Mode Functions
        function toggleDarkMode() {
            const body = document.body;
            body.classList.toggle('dark-mode');
            
            // Save preference to localStorage
            const isDarkMode = body.classList.contains('dark-mode');
            localStorage.setItem('darkMode', isDarkMode);
            
            // Update button icon
            const darkModeBtn = document.getElementById('darkModeToggle');
            if (darkModeBtn) {
                darkModeBtn.innerHTML = isDarkMode ? 
                    '<i class="fas fa-sun"></i>' : 
                    '<i class="fas fa-moon"></i>';
            }
        }

        // Initialize dark mode from localStorage
        function initDarkMode() {
            const isDarkMode = localStorage.getItem('darkMode') === 'true';
            if (isDarkMode) {
                document.body.classList.add('dark-mode');
                const darkModeBtn = document.getElementById('darkModeToggle');
                if (darkModeBtn) {
                    darkModeBtn.innerHTML = '<i class="fas fa-sun"></i>';
                }
            }
        }
    </script>

</head>
<body>

<!-- Quiz Alert Modal -->
<div id="quizAlertModal" class="modal" style="display: none;">
    <div class="modal-content">
        <h2 id="modalTitle">Alert</h2>
        <p id="modalMessage"></p>
        <button onclick="handleModalClose()" class="modal-close-btn">OK</button>
    </div>
</div>

<div id="loadingMessage" class="loading-message" style="display: none;">
    <div class="loading-spinner"></div>
    <span id="loadingText">Loading your saved answers...</span>
</div>

<div id="reloadConfirmModal" class="modal" style="display: none;">
    <div class="modal-content">
        <h2>Confirm Page Navigation</h2>
        <p>Clicking the back/forward button is prohibited. Your current quiz attempt will be submitted automatically.</p>
        <div class="modal-buttons">
            <button onclick="confirmReload()" class="modal-confirm-btn">Yes, Submit Quiz</button>
            <button onclick="cancelReload()" class="modal-cancel-btn">Cancel</button>
        </div>
    </div>
</div>

<!-- Time Up Modal -->
<div id="timeUpModal" class="modal" style="display: none;">
    <div class="modal-content">
        <h2 style="color: #f8b500; margin-bottom: 15px;">
            <i class="fa-solid fa-clock" style="margin-right: 10px;"></i>Time's Up!
        </h2>
        <p style="font-size: 18px; margin-bottom: 20px;">
            Your time has expired. Your quiz will be submitted automatically.
        </p>
        <div class="modal-buttons">
            <button onclick="confirmTimeUp()" class="modal-confirm-btn" 
                    style="background-color: #f8b500; width: 100%;">
                View Results
            </button>
        </div>
    </div>
</div>

<!-- Auto-save message -->
<div id="autoSaveMessage" class="loading-message" style="display: none;">
    <div class="check-icon">
        <svg viewBox="0 0 24 24" class="check-icon-svg">
            <path class="check-icon-path" fill="none" stroke="#28a745" stroke-width="2" d="M3 12.5L8.5 18L21 5"/>
        </svg>
    </div>
    <span id="autoSaveText">Auto-saving your progress...</span>
</div>

<!-- Dark Mode Toggle Button -->
<button class="dark-mode-toggle" id="darkModeToggle" onclick="toggleDarkMode()">
    <i class="fas fa-moon"></i>
</button>

<?php if (!$showModal): ?>
<header>
    <div class="logo"><img src="img/logo1.png" onclick="window.location.href='s_Home.php';" style="cursor: pointer;" width="200px" height="80px"></div>
    <div class="actions">
        <div class="profile"><img src="uploads/profiles/default-profile.jpg" width="50px" height="50px"></div>
    </div>
</header>

<div class="quiz-container">
    <div id="quiz-header">
        <h1><?php echo htmlspecialchars($quiz['title']); ?></h1> 
        <div id="timer" class="timer"></div>
    </div><br><br>

    <div id="quiz-questions">
        <!-- Questions will be dynamically inserted here -->
    </div>

    <div class="question-navigation" id="question-navigation">
        <!-- Question buttons will be inserted here -->
    </div>

    <div class="nav-controls">
        <button onclick="previousQuestion()" class="nav-btn" id="prev-btn">Previous</button>
        <button onclick="nextQuestion()" class="nav-btn" id="next-btn">Next</button>
    </div>

    <div class="submit-cont">
        <button onclick="submitQuiz()" class="submit-btn">Submit Quiz</button>
    </div>
</div>
<?php endif; ?>

<script>
    const questions = <?php echo json_encode($questions); ?>;
    const quizType = <?php echo json_encode($quiz_type); ?>;
    const userAnswers = {};
    let remainingTimeSeconds = <?php echo $remaining_time; ?>;
    
    const AUTO_SAVE_INTERVAL = 10000;
    let autoSaveInterval;
    let timerInterval = null;
    let currentQuestionIndex = 0;

    // TTS variables
    let synth = window.speechSynthesis;
    let voices = [];
    let defaultVoice = "Microsoft David - English (United States)";
    let isSpeaking = false;

    function populateVoices() {
        voices = synth.getVoices();
        if (voices.length > 0) {
            const preferredVoice = voices.find(voice => voice.name === defaultVoice);
            if (preferredVoice) {
                defaultVoice = preferredVoice.name;
            }
        }
    }

    populateVoices();
    if (speechSynthesis.onvoiceschanged !== undefined) {
        speechSynthesis.onvoiceschanged = populateVoices;
    }

    function speakText(text, questionNumber = null) {
        // Cancel any ongoing speech
        synth.cancel();
        isSpeaking = false;
        
        if (questionNumber !== null) {
            const questionNumSpeech = new SpeechSynthesisUtterance(`Question Number ${questionNumber}`);
            const questionTextSpeech = new SpeechSynthesisUtterance(text);
            
            voices.forEach((voice) => {
                if (voice.name === defaultVoice) {
                    questionNumSpeech.voice = voice;
                    questionTextSpeech.voice = voice;
                }
            });
            
            synth.speak(questionNumSpeech);
            synth.speak(questionTextSpeech);
            isSpeaking = true;
            
            // Set flag when done
            questionTextSpeech.onend = function() {
                isSpeaking = false;
            };
        } else {
            const utterance = new SpeechSynthesisUtterance(text);
            
            voices.forEach((voice) => {
                if (voice.name === defaultVoice) {
                    utterance.voice = voice;
                }
            });
            
            synth.speak(utterance);
            isSpeaking = true;
            
            utterance.onend = function() {
                isSpeaking = false;
            };
        }
    }

    function speakQuestionWithAnswers(questionId, questionText, questionNumber) {
        // Cancel any ongoing speech
        synth.cancel();
        
        // Speak the question number and text
        const questionNumSpeech = new SpeechSynthesisUtterance(`Question Number ${questionNumber}`);
        const questionTextSpeech = new SpeechSynthesisUtterance(questionText);
        
        voices.forEach((voice) => {
            if (voice.name === defaultVoice) {
                questionNumSpeech.voice = voice;
                questionTextSpeech.voice = voice;
            }
        });
        
        // Queue the question speech
        synth.speak(questionNumSpeech);
        synth.speak(questionTextSpeech);
        
        // Get answers for this question
        const answersDiv = document.getElementById(`answers-${questionId}`);
        if (answersDiv) {
            // Speak options after question
            questionTextSpeech.onend = function() {
                const optionsSpeech = new SpeechSynthesisUtterance("Options are:");
                voices.forEach((voice) => {
                    if (voice.name === defaultVoice) {
                        optionsSpeech.voice = voice;
                    }
                });
                synth.speak(optionsSpeech);
                
                // Speak each answer option with delay
                setTimeout(() => {
                    if (quizType === 'Multiple Choice' || quizType === 'True or False') {
                        const answerButtons = answersDiv.querySelectorAll('.answer-button');
                        answerButtons.forEach((button, index) => {
                            setTimeout(() => {
                                const optionText = button.textContent.trim();
                                const optionSpeech = new SpeechSynthesisUtterance(optionText);
                                voices.forEach((voice) => {
                                    if (voice.name === defaultVoice) {
                                        optionSpeech.voice = voice;
                                    }
                                });
                                synth.speak(optionSpeech);
                            }, index * 1500);
                        });
                    } else if (quizType === 'Drag & Drop') {
                        const draggables = answersDiv.querySelectorAll('.drag-item');
                        if (draggables.length > 0) {
                            setTimeout(() => {
                                const dragSpeech = new SpeechSynthesisUtterance("Drag and drop options:");
                                voices.forEach((voice) => {
                                    if (voice.name === defaultVoice) {
                                        dragSpeech.voice = voice;
                                    }
                                });
                                synth.speak(dragSpeech);
                                
                                draggables.forEach((item, index) => {
                                    setTimeout(() => {
                                        const itemSpeech = new SpeechSynthesisUtterance(item.textContent.trim());
                                        voices.forEach((voice) => {
                                            if (voice.name === defaultVoice) {
                                                itemSpeech.voice = voice;
                                            }
                                        });
                                        synth.speak(itemSpeech);
                                    }, (index + 1) * 1500);
                                });
                            }, 1000);
                        }
                    } else if (quizType === 'Matching Type') {
                        setTimeout(() => {
                            const matchSpeech = new SpeechSynthesisUtterance("Matching type question. Select items from left and right columns to match them.");
                            voices.forEach((voice) => {
                                if (voice.name === defaultVoice) {
                                    matchSpeech.voice = voice;
                                }
                            });
                            synth.speak(matchSpeech);
                        }, 1000);
                    }
                }, 1500);
            };
        }
    }

    function createTTSButton(questionId, questionText, questionNumber) {
        const ttsButton = document.createElement('i');
        ttsButton.className = 'fas fa-volume-up tts-button';
        ttsButton.id = `tts-${questionId}`;
        ttsButton.title = 'Text to Speech';
        ttsButton.addEventListener('click', (e) => {
            e.stopPropagation();
            speakQuestionWithAnswers(questionId, questionText, questionNumber);
        });
        
        return ttsButton;
    }

    function autoSaveProgress() {
        if (Object.keys(userAnswers).length === 0 || isSubmitting || remainingTimeSeconds <= 0) {
            return;
        }

        const timerDisplay = document.getElementById('timer').textContent;
        const [minutes, seconds] = timerDisplay.split(':').map(Number);
        const currentRemainingTime = minutes * 60 + seconds;
        remainingTimeSeconds = currentRemainingTime;

        fetch('s_saveProgress.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                attempt_id: <?php echo $attempt_id; ?>,
                answers: userAnswers,
                quiz_id: <?php echo $quiz_id; ?>,
                time_remaining: currentRemainingTime
            })
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                console.error('Auto-save failed:', data.error);
            }
        })
        .catch(error => {
            console.error('Auto-save error:', error);
        });
    }

    function restoreSavedAnswers() {
        const loadingMessage = document.getElementById('loadingMessage');
        const loadingText = document.getElementById('loadingText');
        const spinner = loadingMessage.querySelector('.loading-spinner');
        
        loadingMessage.style.display = 'flex';
        loadingText.textContent = 'Loading your saved answers...';
        loadingMessage.classList.remove('success-message', 'fade-out');

        const serverAnswers = <?php echo json_encode($prefilledAnswers); ?>;
        
        Object.entries(serverAnswers).forEach(([questionId, answer]) => {
            try {
                userAnswers[questionId] = typeof answer === 'string' ? 
                    JSON.parse(answer) : answer;
            } catch (e) {
                userAnswers[questionId] = answer;
            }
        });

        spinner.style.display = 'none';
        loadingText.textContent = 'Answers loaded successfully!';
        loadingMessage.classList.add('success-message');
        
        setTimeout(() => {
            loadingMessage.classList.add('fade-out');
            setTimeout(() => {
                loadingMessage.style.display = 'none';
                spinner.style.display = 'block';
            }, 1500);
        }, 1500);
    }

    function renderQuestions() {
        const quizQuestionsDiv = document.getElementById('quiz-questions');
        const questionNavDiv = document.getElementById('question-navigation');
        
        questions.forEach((question, index) => {
            // Create question navigation button
            const navButton = document.createElement('button');
            navButton.className = 'question-nav-btn';
            navButton.id = `nav-btn-${index}`;
            navButton.textContent = index + 1;
            navButton.onclick = () => goToQuestion(index);
            questionNavDiv.appendChild(navButton);

            // Create question container
            const questionDiv = document.createElement('div');
            questionDiv.className = 'question';
            questionDiv.dataset.questionId = question.question_id;
            questionDiv.style.display = index === 0 ? 'block' : 'none';
            questionDiv.id = `question-${index}`;

            const questionContent = document.createElement('div');
            questionContent.style.position = 'relative';

            // Add instructions if they exist
            if (question.instructions && question.instructions.trim() !== '') {
                const instructionContainer = document.createElement('div');
                instructionContainer.className = 'instruction-container';
                
                const instructionLabel = document.createElement('div');
                instructionLabel.className = 'instruction-label';
                instructionLabel.textContent = 'Instruction:';
                
                const instructionText = document.createElement('div');
                instructionText.className = 'instruction-text';
                instructionText.textContent = question.instructions;
                
                instructionContainer.appendChild(instructionLabel);
                instructionContainer.appendChild(instructionText);
                questionContent.appendChild(instructionContainer);
            }

            // Question number and text
            const questionNumberText = document.createElement('p');
            questionNumberText.innerText = `${index + 1}. ${question.question_text}`;     
            questionNumberText.className = 'question-text';
            questionNumberText.style.position = 'relative';

            // Add TTS button
            const ttsButton = createTTSButton(question.question_id, question.question_text, index + 1);
            questionNumberText.appendChild(ttsButton);
            questionContent.appendChild(questionNumberText);
            questionDiv.appendChild(questionContent);
            
            // Answers container
            const answersDiv = document.createElement('div');
            answersDiv.className = 'answers';
            answersDiv.id = `answers-${question.question_id}`;
            
            fetch(`s_get_answers.php?question_id=${question.question_id}`)
                .then(response => response.json())
                .then(data => {
                    renderAnswers(data, question, answersDiv, quizType);
                })
                .catch(error => {
                    console.error('Error fetching answers:', error);
                    answersDiv.innerHTML = `<p>Error loading answers: ${error.message}</p>`;
                });
            
            questionDiv.appendChild(answersDiv);
            quizQuestionsDiv.appendChild(questionDiv);
        });
    }

    function renderAnswers(data, question, answersDiv, questionType) {
        answersDiv.innerHTML = '';

        switch(questionType) {
            case 'True or False':
                ['True', 'False'].forEach((answerText, i) => {
                    const answerButton = document.createElement('button');
                    answerButton.innerText = answerText;
                    answerButton.className = 'answer-button';
                    answerButton.dataset.answerId = data[i]?.answer_id || i;
                    answerButton.onclick = function() {
                        saveAnswer(question.question_id, data[i]?.answer_id || answerText);
                        answersDiv.querySelectorAll('.answer-button').forEach(btn => btn.classList.remove('selected'));
                        answerButton.classList.add('selected');
                        updateQuestionNavigation();
                    };
                    answersDiv.appendChild(answerButton);
                });
                break;

            case 'Identification':
            case 'Enumeration':
            case 'Fill in the Blanks':
                const answerInput = document.createElement('input');
                answerInput.type = 'text';
                answerInput.placeholder = `Enter your ${questionType.toLowerCase()} answer`;
                answerInput.className = 'form-control';
                answerInput.oninput = function() {
                    saveAnswer(question.question_id, answerInput.value.trim());
                    updateQuestionNavigation();
                };
                answersDiv.appendChild(answerInput);
                break;

            case 'Multiple Choice':
                const labels = ['A', 'B', 'C', 'D'];
                data.forEach((answer, i) => {
                    if (!answer) return;
                    
                    const answerButton = document.createElement('button');
                    answerButton.innerText = `${labels[i]}. ${answer.answer_text || 'No text'}`;
                    answerButton.className = 'answer-button';
                    answerButton.dataset.answerId = answer.answer_id;
                    answerButton.onclick = function() {
                        saveAnswer(question.question_id, answer.answer_id);
                        answersDiv.querySelectorAll('.answer-button').forEach(btn => btn.classList.remove('selected'));
                        answerButton.classList.add('selected');
                        updateQuestionNavigation();
                    };
                    answersDiv.appendChild(answerButton);
                });
                break;

            case 'Drag & Drop':
                const sourceContainer = document.createElement('div');
                const targetContainer = document.createElement('div');
                sourceContainer.className = 'drop-zone source-zone';
                targetContainer.className = 'drop-zone target-zone';
                
                sourceContainer.innerHTML = '<h4>Drag Items</h4>';
                targetContainer.innerHTML = '<h4>Drop Item Here</h4>';
                
                const shuffledData = data.sort(() => Math.random() - 0.5);
                
                shuffledData.forEach((item, index) => {
                    const dragItem = document.createElement('div');
                    dragItem.className = 'drag-item';
                    dragItem.draggable = true;
                    dragItem.dataset.answerId = item.answer_id;
                    dragItem.innerText = item.answer_text;
                    
                    dragItem.addEventListener('dragstart', (e) => {
                        e.dataTransfer.setData('text/plain', e.target.dataset.answerId);
                        e.target.classList.add('dragging');
                    });
                    
                    dragItem.addEventListener('dragend', (e) => {
                        e.target.classList.remove('dragging');
                    });
                    
                    sourceContainer.appendChild(dragItem);
                });
                
                targetContainer.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    e.target.classList.add('drag-over');
                });
                
                targetContainer.addEventListener('dragleave', (e) => {
                    e.target.classList.remove('drag-over');
                });
                
                targetContainer.addEventListener('drop', (e) => {
                    e.preventDefault();
                    const answerId = e.dataTransfer.getData('text/plain');
                    const droppedItem = document.querySelector(`.drag-item[data-answer-id="${answerId}"]`);
                    
                    if (droppedItem) {
                        const existingItem = targetContainer.querySelector('.drag-item');
                        if (existingItem) {
                            sourceContainer.appendChild(existingItem);
                        }
                        
                        targetContainer.appendChild(droppedItem);
                        saveAnswer(question.question_id, [answerId]);
                        updateQuestionNavigation();
                    }
                    
                    e.target.classList.remove('drag-over');
                });
                
                answersDiv.style.display = 'grid';
                answersDiv.style.gridTemplateColumns = '1fr 1fr';
                answersDiv.style.gap = '20px';
                answersDiv.appendChild(sourceContainer);
                answersDiv.appendChild(targetContainer);
                break;

            case 'Matching Type':
                try {
                    const leftItems = JSON.parse(question.left_items || '[]');
                    const rightItems = JSON.parse(question.right_items || '[]');

                    const matchContainer = document.createElement('div');
                    matchContainer.className = 'match-container';
                    
                    const leftColumn = document.createElement('div');
                    leftColumn.className = 'left-items';
                    leftColumn.innerHTML = '<h4>Items to Match</h4>';
                    
                    leftItems.forEach((item, index) => {
                        const matchItem = document.createElement('div');
                        matchItem.className = 'match-item';
                        matchItem.textContent = `${index + 1}. ${item}`;
                        matchItem.dataset.answerId = `left_${index}`;
                        matchItem.dataset.side = 'left';
                        matchItem.dataset.itemIndex = index;
                        matchItem.addEventListener('click', function() {
                            selectMatchItem(this, question.question_id);
                        });
                        leftColumn.appendChild(matchItem);
                    });

                    const rightColumn = document.createElement('div');
                    rightColumn.className = 'right-items';
                    rightColumn.innerHTML = '<h4>Match With</h4>';
                    
                    const letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];
                    rightItems.forEach((item, index) => {
                        const matchItem = document.createElement('div');
                        matchItem.className = 'match-item';
                        matchItem.textContent = `${letters[index]}. ${item}`;
                        matchItem.dataset.answerId = `right_${index}`;
                        matchItem.dataset.side = 'right';
                        matchItem.dataset.itemIndex = index;
                        matchItem.addEventListener('click', function() {
                            selectMatchItem(this, question.question_id);
                        });
                        rightColumn.appendChild(matchItem);
                    });

                    matchContainer.appendChild(leftColumn);
                    matchContainer.appendChild(rightColumn);
                    answersDiv.appendChild(matchContainer);

                    const pairsDisplay = document.createElement('div');
                    pairsDisplay.className = 'pairs-display';
                    pairsDisplay.innerHTML = '<h4>Your Matches:</h4><div id="pairs-list-' + question.question_id + '"></div>';
                    answersDiv.appendChild(pairsDisplay);

                    const clearBtn = document.createElement('button');
                    clearBtn.textContent = 'Clear All Matches';
                    clearBtn.className = 'clear-matches-btn';
                    clearBtn.addEventListener('click', function() {
                        clearAllMatches(question.question_id);
                    });
                    answersDiv.appendChild(clearBtn);

                    if (!window.matchingData) {
                        window.matchingData = {};
                    }
                    window.matchingData[question.question_id] = {
                        selectedLeft: null,
                        selectedRight: null,
                        matches: []
                    };

                } catch (e) {
                    console.error('Error parsing matching items:', e);
                    answersDiv.innerHTML = '<p>Error loading matching items</p>';
                }
                break;
        }
    }

    function selectMatchItem(item, questionId) {
        const side = item.dataset.side;
        const matchData = window.matchingData[questionId];
        
        document.querySelectorAll(`[data-side="${side}"]`).forEach(el => {
            if (el.closest('.question').querySelector('.question-text').textContent.includes(questionId) || 
                el.getAttribute('data-question-id') === questionId.toString()) {
                el.classList.remove('selected');
            }
        });

        item.classList.add('selected');

        if (side === 'left') {
            matchData.selectedLeft = item;
        } else {
            matchData.selectedRight = item;
        }

        if (matchData.selectedLeft && matchData.selectedRight) {
            createMatch(questionId);
        }
    }

    function createMatch(questionId) {
        const matchData = window.matchingData[questionId];
        const leftItem = matchData.selectedLeft;
        const rightItem = matchData.selectedRight;
        
        const leftId = leftItem.dataset.answerId;
        const rightId = rightItem.dataset.answerId;
        
        const existingMatch = matchData.matches.find(m => m.left === leftId || m.right === rightId);
        if (existingMatch) {
            alert('One of these items is already matched. Clear existing matches first.');
            return;
        }

        const match = {
            left: leftId,
            leftText: leftItem.textContent,
            right: rightId,
            rightText: rightItem.textContent
        };
        
        matchData.matches.push(match);
        updateMatchesDisplay(questionId);
        saveAnswer(questionId, matchData.matches);
        updateQuestionNavigation();
        
        leftItem.classList.remove('selected');
        rightItem.classList.remove('selected');
        leftItem.classList.add('matched');
        rightItem.classList.add('matched');
        
        matchData.selectedLeft = null;
        matchData.selectedRight = null;
    }

    function updateMatchesDisplay(questionId) {
        if (!window.matchingData || !window.matchingData[questionId]) return;
        
        const matchData = window.matchingData[questionId];
        const pairsList = document.getElementById(`pairs-list-${questionId}`);
        
        if (!pairsList) return;
        
        pairsList.innerHTML = '';
        
        if (matchData.matches.length === 0) {
            pairsList.innerHTML = '<p style="color: #666; font-style: italic;">No matches yet</p>';
            return;
        }
        
        matchData.matches.forEach((match, index) => {
            const pairElement = document.createElement('div');
            pairElement.className = 'match-pair';
            
            const cleanLeft = (match.leftText || '').replace(/^\d+\.\s*/, '').trim();
            const cleanRight = (match.rightText || '').replace(/^[A-Z]\.\s*/, '').trim();
            
            pairElement.innerHTML = `
                <span style="flex: 1; font-weight: 500; color: black;">${cleanLeft}</span>
                <span style="margin: 0 10px; font-weight: 500; color: #28a745;">↔</span>
                <span style="flex: 1; font-weight: 500; color: black;">${cleanRight}</span>
                <button onclick="removeMatch(${questionId}, ${index})" class="remove-match-btn">×</button>
            `;
            
            pairsList.appendChild(pairElement);
        });
    }

    function removeMatch(questionId, matchIndex) {
        const matchData = window.matchingData[questionId];
        const removedMatch = matchData.matches[matchIndex];
        
        matchData.matches.splice(matchIndex, 1);
        
        document.querySelectorAll('.match-item').forEach(item => {
            if (item.dataset.answerId === removedMatch.left || item.dataset.answerId === removedMatch.right) {
                item.classList.remove('matched');
            }
        });
        
        updateMatchesDisplay(questionId);
        saveAnswer(questionId, matchData.matches);
        updateQuestionNavigation();
    }

    function clearAllMatches(questionId) {
        const matchData = window.matchingData[questionId];
        
        matchData.matches = [];
        matchData.selectedLeft = null;
        matchData.selectedRight = null;
        
        document.querySelectorAll('.match-item').forEach(item => {
            item.classList.remove('selected', 'matched');
        });
        
        updateMatchesDisplay(questionId);
        delete userAnswers[questionId];
        updateQuestionNavigation();
    }

    function saveAnswer(questionId, answer) {
        userAnswers[questionId] = answer;
    }

    function goToQuestion(index) {
        if (index >= 0 && index < questions.length) {
            document.querySelectorAll('.question').forEach(q => q.style.display = 'none');
            document.getElementById(`question-${index}`).style.display = 'block';
            
            document.querySelectorAll('.question-nav-btn').forEach(btn => btn.classList.remove('current'));
            document.getElementById(`nav-btn-${index}`).classList.add('current');
            
            currentQuestionIndex = index;
            updateNavigationButtons();
        }
    }

    function previousQuestion() {
        if (currentQuestionIndex > 0) {
            goToQuestion(currentQuestionIndex - 1);
        }
    }

    function nextQuestion() {
        if (currentQuestionIndex < questions.length - 1) {
            goToQuestion(currentQuestionIndex + 1);
        }
    }

    function updateNavigationButtons() {
        const prevBtn = document.getElementById('prev-btn');
        const nextBtn = document.getElementById('next-btn');
        
        prevBtn.disabled = currentQuestionIndex === 0;
        nextBtn.disabled = currentQuestionIndex === questions.length - 1;
    }

    function updateQuestionNavigation() {
        questions.forEach((question, index) => {
            const navBtn = document.getElementById(`nav-btn-${index}`);
            if (userAnswers[question.question_id]) {
                navBtn.classList.add('answered');
            } else {
                navBtn.classList.remove('answered');
            }
        });
    }

    function startTimer(duration) {
        let timer = duration;
        const timerElement = document.getElementById('timer');
        
        if (window.timerInterval) {
            clearInterval(window.timerInterval);
        }

        window.timerInterval = setInterval(function () {
            let minutes = parseInt(timer / 60, 10);
            let seconds = parseInt(timer % 60, 10);

            minutes = minutes < 10 ? "0" + minutes : minutes;
            seconds = seconds < 10 ? "0" + seconds : seconds;

            timerElement.textContent = `${minutes}:${seconds}`;

            if (timer <= 0) {
                clearInterval(window.timerInterval);
                clearInterval(autoSaveInterval);
                
                isSubmitting = true;
                showTimeUpModal();
                return;
            }
            
            timer--;
        }, 1000);
    }

    function submitQuiz(isForced = false) {
        console.log("User answers before submission:", userAnswers);

        const submissionData = {
            answers: Object.keys(userAnswers).length > 0 ? userAnswers : {},
            quiz_id: <?php echo $quiz_id; ?>,
            subject_id: <?php echo $subject_id; ?>
        };

        isSubmitting = true;
        clearInterval(autoSaveInterval);
        
        if (window.timerInterval) {
            clearInterval(window.timerInterval);
        }

        window.onbeforeunload = null;
        
        fetch('s_submit_quiz.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(submissionData)
        })
        .then(response => response.json())    
        .then(data => {
            if (data.success) {
                sessionStorage.setItem('quizResult', JSON.stringify({
                    score: data.score,
                    total: data.total,
                    quiz_id: <?php echo $quiz_id; ?>,
                    wrong_answers: data.wrong_answers,
                    subject_id: <?php echo $subject_id; ?>
                }));
                
                window.location.href = 'quiz_result.php';
            } else {
                alert('Error submitting quiz: ' + (data.error || 'Unknown error'));
                window.location.href = 'select_quiz.php?subject_id=<?php echo $subject_id; ?>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('There was an error submitting your quiz. Please try again.');
            window.location.href = 'select_quiz.php?subject_id=<?php echo $subject_id; ?>';
        });   
    }

    function showTimeUpModal() {
        const modal = document.getElementById('timeUpModal');
        modal.style.display = 'flex';
    }

    function confirmTimeUp() {
        document.getElementById('timeUpModal').style.display = 'none';
        submitQuiz(true);
    }

    window.onload = function() {
        <?php if ($showModal): ?>
            showAlertModal(
                <?php echo json_encode($modalTitle); ?>,
                <?php echo json_encode($modalMessage); ?>,
                <?php echo json_encode($modalRedirect); ?>
            );
            return;
        <?php endif; ?>

        console.log('Page loaded, initializing quiz...');
        
        // Initialize dark mode
        initDarkMode();
        
        window.matchingData = {};
        
        renderQuestions();
        restoreSavedAnswers();
        
        setTimeout(() => {
            goToQuestion(0);
            updateNavigationButtons();
            updateQuestionNavigation();
            
            // Restore saved answers to UI
            questions.forEach((question, index) => {
                if (userAnswers[question.question_id]) {
                    const navBtn = document.getElementById(`nav-btn-${index}`);
                    navBtn.classList.add('answered');
                }
            });
        }, 500);
        
        console.log('Starting timer with:', remainingTimeSeconds, 'seconds');
        startTimer(remainingTimeSeconds);
        
        autoSaveInterval = setInterval(autoSaveProgress, AUTO_SAVE_INTERVAL);

        console.log('Quiz initialization complete');
    };

    // Add to window.onload or as a separate function
        window.addEventListener('orientationchange', function() {
            setTimeout(function() {
                window.dispatchEvent(new Event('resize'));
            }, 100);
        });

        // Prevent zoom on double-tap for iOS
        document.addEventListener('touchstart', function(event) {
            if (event.touches.length > 1) {
                event.preventDefault();
            }
        }, { passive: false });

        let lastTouchEnd = 0;
        document.addEventListener('touchend', function(event) {
            const now = (new Date()).getTime();
            if (now - lastTouchEnd <= 300) {
                event.preventDefault();
            }
            lastTouchEnd = now;
        }, false);
</script>

</body>
</html>