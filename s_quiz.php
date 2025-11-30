<?php
session_start();
date_default_timezone_set('Asia/Manila');

if (strpos($_SESSION['account_number'], 'S') !== 0) {
    header("Location: login.php");
    exit();
}

// Check if the back button was clicked
$partialSubmit = isset($_SERVER['HTTP_REFERER']) && 
                (strpos($_SERVER['HTTP_REFERER'], $_SERVER['HTTP_HOST']) === false ||
                isset($_SERVER['HTTP_CACHE_CONTROL']) && $_SERVER['HTTP_CACHE_CONTROL'] === 'max-age=0');

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "rawrit";

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
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

error_log("Student query result: " . ($student ? "Found student ID: " . $student['student_id'] : "No student found"));

if ($student) {
    // Get the latest attempt (completed or not)
    $attemptQuery = "SELECT * FROM quiz_attempts 
                    WHERE quiz_id = ? AND account_number = ? 
                    ORDER BY attempt_id DESC LIMIT 1";
    $stmt = $conn->prepare($attemptQuery);
    $stmt->bind_param("is", $quiz_id, $student_id);
    $stmt->execute();
    $existingAttempt = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // DEBUG: Check quiz timer value
    error_log("🔍 DEBUG - Quiz timer value: " . $quiz['timer'] . " minutes");
    error_log("🔍 DEBUG - Quiz timer in seconds: " . ($quiz['timer'] * 60));
    
    /*
    if ($existingAttempt && $existingAttempt['completed']) {
        $error = "You have already taken this quiz. Only one attempt is allowed.";
        echo '<script>
            showAlertModal("Quiz Attempt", "'.addslashes($error).'", "select_quiz.php?subject_id='.$subject_id.'");
        </script>';    
        exit();
    }
    */
    if ($existingAttempt && !$existingAttempt['completed']) {
        // EXISTING ATTEMPT - ALWAYS calculate from original start time
        $attempt_id = $existingAttempt['attempt_id'];
        
        // Calculate elapsed time from the original attempt_time
        $start_time = strtotime($existingAttempt['attempt_time']);
        $current_time = time();

        // DEBUG: Log raw values
        error_log("🔍 DEBUG - Attempt Time (DB): " . $existingAttempt['attempt_time']);
        error_log("🔍 DEBUG - Start timestamp: " . $start_time);
        error_log("🔍 DEBUG - Current timestamp: " . $current_time);

        $elapsed = $current_time - $start_time;
        error_log("🔍 DEBUG - Elapsed seconds: " . $elapsed);
        
        $quiz_duration_seconds = $quiz['timer'] * 60;  // Convert minutes to seconds
        error_log("🔍 DEBUG - Calculated remaining (before max): " . $remaining_time);
        
        // Calculate remaining time (cannot be negative)
        $remaining_time = max(0, $quiz_duration_seconds - $elapsed);
        error_log("🔍 DEBUG - Calculated remaining (before max): " . $remaining_time);

        error_log("ANTI-CHEAT Timer Calculation: " . 
                 "Quiz Duration: {$quiz['timer']} min ({$quiz_duration_seconds}s), " .
                 "Start Time: " . date('Y-m-d H:i:s', $start_time) . ", " .
                 "Current Time: " . date('Y-m-d H:i:s', $current_time) . ", " .
                 "Elapsed: {$elapsed}s, " .
                 "Remaining: {$remaining_time}s (" . floor($remaining_time/60) . ":" . ($remaining_time%60) . ")");
        
        // If time has expired, block access and mark as completed
        if ($remaining_time <= 0) {
            error_log("ANTI-CHEAT: Time expired - auto-completing quiz");
            
            // Mark the quiz as completed with 0 score
            $completeStmt = $conn->prepare("UPDATE quiz_attempts SET completed = 1, score = 0 WHERE attempt_id = ?");
            $completeStmt->bind_param("i", $attempt_id);
            $completeStmt->execute();
            $completeStmt->close();
    
            exit();
        }
        
        // Update time_remaining in database
        $updateTimeQuery = "UPDATE quiz_attempts SET time_remaining = ? WHERE attempt_id = ?";
        $stmt = $conn->prepare($updateTimeQuery);
        $stmt->bind_param("ii", $remaining_time, $attempt_id);
        $stmt->execute();
        $stmt->close();
        error_log("✅ Updated time_remaining in DB: " . $remaining_time);
        
        
        // Load saved answers
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
        // NEW ATTEMPT - Create with full time
        $quiz_duration_seconds = $quiz['timer'] * 60;  // Convert minutes to seconds
        
        $insertAttempt = "INSERT INTO quiz_attempts (quiz_id, account_number, attempt_time, time_remaining) 
                        VALUES (?, ?, NOW(), ?)";
        $stmt = $conn->prepare($insertAttempt);
        $stmt->bind_param("isi", $quiz_id, $student_id, $quiz_duration_seconds);
        $stmt->execute();
        $attempt_id = $conn->insert_id;
        $remaining_time = $quiz_duration_seconds;
        $prefilledAnswers = [];
        
        error_log("New attempt created - ID: {$attempt_id}, Full Duration: {$quiz['timer']} min ({$quiz_duration_seconds}s)");
        $stmt->close();
    }
}

// Final fallback - should not normally reach here
if (!isset($remaining_time)) {
    $remaining_time = $quiz['timer'] * 60;
    error_log("WARNING: Fallback remaining_time used: " . $remaining_time);
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
        }

        body.dark-mode header {
            background-color: #1a1a1a;
        }

        header .logo {
            font-size: 24px;
            font-weight: bold;
            margin-left: 30px;
            margin-top: 3px;
        }

        header .actions .profile img {
            width: 40px;
            height: 40px;
            background-color: #ffffff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #f5a623;
            font-size: 1.5rem;
        }

        nav p{
            font-family: Fredoka;
            color: white;
            font-size: 30px;
            margin-right: 30px;
        }

        body.dark-mode nav p {
            color: #e0e0e0;
        }

        p{
            font-size: 30px;
            font-family: Fredoka;
            color: white;
        }

        body.dark-mode p {
            color: #e0e0e0;
        }

        h1 {
            font-family: Fredoka;
            width: fit-content;
            letter-spacing: 2px;
        }

        body.dark-mode h1 {
            color: #e0e0e0;
        }

        .quiz-cont {
            background-color: #FFFFFF;
            font-family: Fredoka;
            color: #f8b500;
            margin-top: 2%;
            margin-bottom: 5%;
            margin-left: auto;
            margin-right: auto;
            padding: 50px 50px;
            width: 90%;
            height: 100%;
            border: 2px solid #f8b500;
            border-radius: 10px;
            box-shadow: 4px 4px 0 0 #BC8900;
        }

        body.dark-mode .quiz-cont {
            background-color: #2d2d2d;
            color: #e0e0e0;
        }

        .question-info {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 5%;
            margin-bottom: 15px;
            margin-left: 40px;
        }

        #question-text {
            font-family: Fredoka;
            letter-spacing: 2px;
            width: none;
            flex-grow: 1;
            color: black;
        }

        body.dark-mode #question-text {
            color: #e0e0e0;
        }

        #question-number {
            font-family: Fredoka;
            font-size: 28px;
            color: black;
        }

        body.dark-mode #question-number {
            color: #e0e0e0;
        }

        /* Instructions styling */
        .instructions {
            font-family: Fredoka;
            color: #666;
            font-size: 16px;
            margin: 10px 0;
            padding: 10px;
            background-color: #f8f9fa;
            border-left: 4px solid #f8b500;
            border-radius: 4px;
        }

        body.dark-mode .instructions {
            color: #b0b0b0;
            background-color: #333;
        }

        .instructions strong {
            color: #f8b500;
        }

        .timer {
            background-color: white;
            font-family: Fredoka;
            font-size: 20px;
            color: #707070;
            padding: 10px;
            width: 8%;
            margin-top: -5%;
            float: right;
            border-radius: 5px;
            text-align: center;
            vertical-align: middle;
            align-content: center;
            border: 2px solid #f8b500;
        }

        body.dark-mode .timer {
            background-color: #2d2d2d;
            color: #e0e0e0;
        }

        #answers {
            width: 95%;
            margin-left: 6%;
        }

        .answer-button {
            font-family: Fredoka;
            background-color: white;
            border-radius: 10px;
            display: inline-block;
            padding: 5px 20px;
            margin: 15px 15px 15px 25px;
            border: 2px solid #f8b500;
            border-radius: 20px;
            cursor: pointer;
            width: 42%;
            height: 50px;
            color: #f8b500;
            font-weight: bolder;
            font-size: 23px;
            letter-spacing: 1px;
            text-align: center;
            box-sizing: border-box;
            box-shadow: 0 5px 0 0 #BC8900;
        }

        body.dark-mode .answer-button {
            background-color: #2d2d2d;
            color: #f8b500;
        }

        .answer-button:hover {
              background-color: #f8b500;
              color: #ffffff;
        }

        body.dark-mode .answer-button:hover {
            background-color: #f8b500;
            color: white;
        }

        .answer-button.selected {
             background-color: #f8b500;
            color: white;
        }

        body.dark-mode .answer-button.selected {
            background-color: #f8b500 !important;
            color: white !important;
        }

        .answer-input {
            width: 100%;
            padding: 10px;
            border-radius: 15px;
            font-family: Fredoka;
            font-size: 18px;
            border: 2px solid #B9B6B6;
        }

        body.dark-mode .answer-input {
            background-color: #2d2d2d;
            color: #e0e0e0;
            border: 2px solid #f8b500;
        }

        .fa-circle-arrow-right {
            float: right;
            font-size: 30px;
        }

        .fa-circle-arrow-left {
            float: left;
            font-size: 30px;
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

        .question-btn {
            font-family: Fredoka;
            font-size: 18px;
            margin: 0 5px;
            padding: 5px 10px;
            border: 2px solid #f8b500;
            border-radius: 50%;
            width: 35px;
            text-align: center;
            background-color: white;
            color: #f8b500;
        }

        body.dark-mode .question-btn {
            background-color: #2d2d2d;
            color: #f8b500;
        }
          
        .question-btn-container {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: 10px;
        }

        .answered {
            background-color: #f8b500;
            color: white;
        }

        .question-drag-container {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 20px;
        }

        .drop-zone {
            width: 200px;
            height: 100px;
            border: 2px dashed #f8b500;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #fff;
            transition: all 0.3s ease;
            margin-left: 20px;
        }

        body.dark-mode .drop-zone {
            background-color: #2d2d2d;
        }

        .drop-zone.dragover {
            background-color: rgba(248, 181, 0, 0.1);
            border-style: solid;
        }

        .drop-zone.dropped {
            border-style: solid;
            background-color: #fff;
            color: #000;
        }

        body.dark-mode .drop-zone.dropped {
            background-color: #2d2d2d;
            color: #e0e0e0;
        }

        .drop-zone-prompt {
            color: #999;
            font-size: 16px;
            font-family: Fredoka;
        }

        body.dark-mode .drop-zone-prompt {
            color: #b0b0b0;
        }

        .choices-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 20px;
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 10px;
            border: 1px solid #a4a4a4ed;
            margin-right: 5%;
        }

        body.dark-mode .choices-container {
            background-color: #333;
            border: 1px solid #f8b500;
        }

        .draggable {
            padding: 10px 20px;
            background-color: white;
            border: 2px solid #f8b500;
            border-radius: 8px;
            cursor: move;
            font-family: Fredoka;
            color: #f8b500;
            transition: all 0.3s ease;
            box-shadow: 0 2px 0 0 #BC8900;
        }

        body.dark-mode .draggable {
            background-color: #2d2d2d;
            color: #f8b500;
        }

        .draggable:hover {
            background-color: #f8b500;
            color: white;
        }

        .draggable.dragging {
            opacity: 0.5;
            transform: scale(0.95);
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
        }

        body.dark-mode .match-item {
            background-color: #2d2d2d;
            border: 1px solid #f8b500;
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
        }

        body.dark-mode .pairs-display {
            background-color: #333;
            border: 1px solid #f8b500;
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

        .submit-btn {
            background-color: #f8b500;
            color: white;
            width: 100%;
            border-radius: 10px;
            border:none;
            padding: 10px;
            font-size: 18px;
            font-family: Fredoka;
            margin-top: 2%;
            width: 60%;
            box-shadow: 0 6px 0 0 #BC8900;
        }

        .submit-btn:hover {
            -ms-transform: scale(1.5); /* IE 9 */
            -webkit-transform: scale(1.5); /* Safari 3-8 */
            transform: scale(1.1); 
            transition: transform .2s;
            box-shadow: 0 4px 0 0 #BC8900;
        }

        .submit-btn:active {
            background-color: #A34404;  
            transform: translateY(4px);
        }

        .submit-btn:active {
            background-color: #A34404;
            box-shadow: 5px 6px 0 0 rgba(0, 0, 0, 0.3);
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

        /* width */
        ::-webkit-scrollbar {
          width: 10px;
          height: 10px;
        }

        /* Track */
        ::-webkit-scrollbar-track {
          box-shadow: inset 0 0 5px grey; 
          border-radius: 10px;
        }
         
        /* Handle */
        ::-webkit-scrollbar-thumb {
          background: #CF5300; 
          border-radius: 10px;
        }

        /* Handle on hover */
        ::-webkit-scrollbar-thumb:hover {
          background: #A34404; 
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
        }

        body.dark-mode .modal-content {
            background-color: #2d2d2d;
            color: #e0e0e0;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
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

        /* Pulse animation for attention */
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

        /* Quiz Alert Modal - matches allZapped style */
        #quizAlertModal {
            position: fixed;
            z-index: 10001;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.6);
            display: flex;
            justify-content: center;
            align-items: center;
        }

        #quizAlertModal .modal-content {
            font-family: Fredoka;
            background-color: white;
            padding: 40px;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            animation: fadeIn 0.3s ease-out;
        }

        body.dark-mode #quizAlertModal .modal-content {
            background-color: #2d2d2d;
            color: #e0e0e0;
        }

        #quizAlertModal h2 {
            font-family: Fredoka;
            color: #333;
            margin-bottom: 20px;
            font-size: 28px;
        }

        body.dark-mode #quizAlertModal h2 {
            color: #e0e0e0;
        }

        #quizAlertModal p {
            font-family: Fredoka;
            color: #666;
            font-size: 18px;
            line-height: 1.6;
            margin-bottom: 25px;
        }

        body.dark-mode #quizAlertModal p {
            color: #b0b0b0;
        }

        .modal-close-btn {
            background-color: #f8b500;
            color: white;
                border: none;
                padding: 12px 30px;
                border-radius: 8px;
                cursor: pointer;
                font-size: 18px;
                font-family: Fredoka;
                font-weight: bold;
                box-shadow: 0 4px 0 0 #BC8900;
                transition: all 0.2s;
            }

            .modal-close-btn:hover {
                background-color: #e6a500;
                transform: translateY(-2px);
                box-shadow: 0 6px 0 0 #BC8900;
            }

            .modal-close-btn:active {
                transform: translateY(2px);
                box-shadow: 0 2px 0 0 #BC8900;
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

        // Reload confirmation functions
        let isReloadConfirmed = false;
        let isModalShowing = false;

        function confirmReload() {
            isReloadConfirmed = true;
            isModalShowing = false;
            document.getElementById("reloadConfirmModal").style.display = "none";
            
            // Temporarily disable beforeunload handler
            window.onbeforeunload = null;
            
            if (Object.keys(userAnswers).length > 0) {
                submitQuiz(true); // Submit with answers
            } else {
                // For no answers, we need to force a reload after a small delay
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

        // Handle browser navigation buttons (back/forward)
        window.addEventListener("popstate", function(e) {
            if (!isReloadConfirmed && !isSubmitting && !isModalShowing) {
                e.preventDefault();
                showReloadConfirmModal();
                // Push the state back to prevent navigation
                history.pushState(null, null, window.location.href);
            }
        });

        // Push initial state to handle back button
        history.pushState(null, null, window.location.href);
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

<header>
    <div class="logo"><img src="img/logo1.png" onclick="window.location.href='s_Home.php';" style="cursor: pointer;" width="200px" height="80px"></div>
    <div class="actions">
        <div class="profile"><img src="uploads/profiles/default-profile.jpg" width="50px" height="50px"></div>
    </div>
</header>

<div class="quiz-cont">
    <div id="quiz-header">
        <h1><?php echo htmlspecialchars($quiz['title']); ?></h1> 
        <div class="speaker">
            <span><i class="fa-solid fa-volume-high"  id="tts"></i></span>
            <span class="speaker-tooltip">Read Aloud</span>
        </div>
        <div id="timer" class="timer"></div>
    </div><br>

    <div id="question">
        <div class="question-info"> 
            <p id="question-number"></p>
            <p id="question-text"></p>
        </div>
        <div id="answers"></div>
    </div><br>

    <div class="question-btn-container">
        <i class="fa-solid fa-circle-arrow-left" onclick="previousQuestion()"></i>
        <div id="question-buttons"></div>
        <i class="fa-solid fa-circle-arrow-right" onclick="nextQuestion()"></i>
    </div>
    <center>
    <button onclick="submitQuiz()" class="submit-btn">Submit Quiz</button>
    </center>
</div>

<script>
    // Dark Mode Functionality - Auto apply based on localStorage
    // Check for saved dark mode preference
    const isDarkMode = localStorage.getItem('darkMode') === 'true';

    // Apply dark mode on page load if enabled
    if (isDarkMode) {
        document.body.classList.add('dark-mode');
    }

    let currentQuestion = 0;

    const questions = <?php echo json_encode($questions); ?>;
    const quizType = <?php echo json_encode($quiz_type); ?>; 
    const userAnswers = {};
    const partialSubmit = <?php echo json_encode($partialSubmit); ?>;

    let remainingTimeSeconds = <?php echo $remaining_time; ?>;

    // Auto-save variables
    const AUTO_SAVE_INTERVAL = 1000;
    let autoSaveInterval;
    let isSubmitting = false;
    let timerInterval = null;

    // Auto-save function
    function autoSaveProgress() {
        // Don't save if submitting or time has expired
        if (Object.keys(userAnswers).length === 0 || isSubmitting || remainingTimeSeconds <= 0) {
            console.log("Skipping auto-save: no answers, submitting, or time expired");
            return;
        }

        // Calculate current remaining time from the timer display
        const timerDisplay = document.getElementById('timer').textContent;
        const [minutes, seconds] = timerDisplay.split(':').map(Number);
        const currentRemainingTime = minutes * 60 + seconds;

        // Update the global variable
        remainingTimeSeconds = currentRemainingTime;

        console.log("Auto-saving progress with time_remaining:", currentRemainingTime);

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
            } else {
                console.log("✅ Auto-save successful, time_remaining updated");
            }
        })
        .catch(error => {
            console.error('❌ Auto-save error:', error);
        });
    }

    // Function to restore saved answers
    function restoreSavedAnswers() {
        const loadingMessage = document.getElementById('loadingMessage');
        const loadingText = document.getElementById('loadingText');
        const spinner = loadingMessage.querySelector('.loading-spinner');
        
        loadingMessage.style.display = 'flex';
        loadingText.textContent = 'Loading your saved answers...';
        loadingMessage.classList.remove('success-message', 'fade-out');

        const serverAnswers = <?php echo json_encode($prefilledAnswers); ?>;

        console.log("Server answers received:", serverAnswers);
        
        Object.entries(serverAnswers).forEach(([questionId, answer]) => {
            try {
                userAnswers[questionId] = typeof answer === 'string' ? 
                    JSON.parse(answer) : answer;
            } catch (e) {
                userAnswers[questionId] = answer;
            }
        });

        console.log("Restored answers:", userAnswers);
        
        spinner.style.display = 'none';
        loadingText.textContent = 'Answers loaded successfully!';
        loadingMessage.classList.add('success-message');
        
        restoreAnswerSelections();
        
        setTimeout(() => {
            loadingMessage.classList.add('fade-out');
            setTimeout(() => {
                loadingMessage.style.display = 'none';
                spinner.style.display = 'block';
            }, 1500);
        }, 1500);
    }

    function restoreAnswerSelections() {
        console.log("🔄 Restoring answer selections to UI...");
        console.log("📊 Total saved answers:", Object.keys(userAnswers).length);
        
        // Wait for questions to be fully rendered
        const questionCheckInterval = setInterval(() => {
            const renderedElements = document.querySelectorAll('.answer-button, input[type="text"], .drop-zone, .match-item').length;
            
            console.log(`📊 Rendered elements: ${renderedElements}`);
            
            if (renderedElements > 0) {
                clearInterval(questionCheckInterval);
                console.log(`✅ UI elements ready, restoring ${Object.keys(userAnswers).length} answers`);
                
                setTimeout(() => {
                    Object.entries(userAnswers).forEach(([questionId, answer]) => {
                        const questionIndex = questions.findIndex(q => q.question_id == questionId);
                        
                        if (questionIndex === -1) {
                            console.warn(`❌ Question ${questionId} not found in questions array`);
                            return;
                        }
                        
                        console.log(`🔄 Processing Q${questionId} (index ${questionIndex}):`, answer);
                        
                        // Mark question button as answered
                        const questionBtn = document.getElementById(`question-btn-${questionIndex}`);
                        if (questionBtn) {
                            questionBtn.classList.add('answered');
                            console.log(`✅ Marked question button ${questionIndex} as answered`);
                        }
                        
                        // For current question, restore the UI
                        if (questionIndex === currentQuestion) {
                            console.log(`🎯 Current question - restoring UI`);
                            restoreCurrentQuestionAnswer(questionId, answer);
                        }
                    });
                }, 300);
            }
        }, 100);
        
        // Safety timeout - stop checking after 5 seconds
        setTimeout(() => {
            clearInterval(questionCheckInterval);
            console.log('⏱️ Question check timeout reached');
        }, 5000);
    }

    function restoreCurrentQuestionAnswer(questionId, answer) {
        console.log(`🔄 Restoring current question ${questionId} answer:`, answer);
        
        if (quizType === 'Multiple Choice' || quizType === 'True or False') {
            // Wait for buttons to be fully rendered
            setTimeout(() => {
                const answersDiv = document.getElementById('answers');
                if (!answersDiv) {
                    console.error('❌ Answers div not found');
                    return;
                }
                
                const buttons = answersDiv.querySelectorAll('.answer-button');
                console.log(`📊 Found ${buttons.length} answer buttons for question ${questionId}`);
                
                if (buttons.length === 0) {
                    console.warn('⚠️ No buttons found - retrying...');
                    // Retry once after additional delay
                    setTimeout(() => restoreCurrentQuestionAnswer(questionId, answer), 300);
                    return;
                }
                
                let buttonSelected = false;
                const answerStr = String(answer);
                
                buttons.forEach((btn, index) => {
                    const btnAnswerId = btn.dataset.answerId;
                    const btnAnswerText = btn.dataset.answerText;
                    
                    console.log(`🔍 Button ${index}:`, {
                        answerId: btnAnswerId,
                        answerText: btnAnswerText,
                        savedAnswer: answerStr
                    });
                    
                    // Check if answer ID matches OR answer text matches (case-insensitive)
                    if (String(btnAnswerId) === answerStr || 
                        (btnAnswerText && btnAnswerText.toLowerCase() === answerStr.toLowerCase())) {
                        btn.classList.add('selected');
                        buttonSelected = true;
                        console.log(`✅ Selected button ${index}: ${btn.textContent}`);
                    }
                });
                
                if (!buttonSelected) {
                    console.warn(`⚠️ No button matched answer: "${answerStr}"`);
                    console.log('Available button IDs:', Array.from(buttons).map(b => b.dataset.answerId));
                    console.log('Available button texts:', Array.from(buttons).map(b => b.dataset.answerText));
                }
            }, 600); // Increased delay to 600ms
            
        } else if (quizType === 'Identification' || quizType === 'Enumeration' || quizType === 'Fill in the Blanks') {
            setTimeout(() => {
                const input = document.querySelector('input[type="text"]');
                if (input && answer) {
                    input.value = answer;
                    console.log('✅ Set input value:', answer);
                } else {
                    console.warn('⚠️ Input field not found or no answer');
                }
            }, 300);
            
        } else if (quizType === 'Drag & Drop') {
            setTimeout(() => {
                if (answer) {
                    const dropZone = document.querySelector('.drop-zone');
                    const answerId = Array.isArray(answer) ? answer[0] : answer;
                    
                    console.log('🔄 Restoring drag & drop, answer ID:', answerId);
                    
                    fetch('s_get_answers.php?question_id=' + questionId)
                        .then(response => response.json())
                        .then(data => {
                            const answerData = data.find(a => a.answer_id == answerId);
                            if (answerData && dropZone) {
                                dropZone.innerHTML = answerData.answer_text;
                                dropZone.classList.add('dropped');
                                console.log('✅ Restored drag and drop:', answerData.answer_text);
                            } else {
                                console.warn('⚠️ Drop zone or answer data not found');
                            }
                        })
                        .catch(err => console.error('❌ Error fetching drag & drop answer:', err));
                }
            }, 300);
            
        } else if (quizType === 'Matching Type') {
            setTimeout(() => {
                try {
                    if (Array.isArray(answer)) {
                        const parsedMatches = typeof answer === 'string' ? JSON.parse(answer) : answer;
                        
                        console.log('🔄 Restoring matching type, matches:', parsedMatches);
                        
                        if (!window.matchingData) window.matchingData = {};
                        if (!window.matchingData[questionId]) {
                            window.matchingData[questionId] = {
                                selectedLeft: null,
                                selectedRight: null,
                                matches: []
                            };
                        }
                        
                        window.matchingData[questionId].matches = parsedMatches;
                        updateMatchesDisplay(questionId);
                        
                        parsedMatches.forEach(match => {
                            const leftItem = document.querySelector(`[data-answer-id="${match.left}"]`);
                            const rightItem = document.querySelector(`[data-answer-id="${match.right}"]`);
                            if (leftItem) leftItem.classList.add('matched');
                            if (rightItem) rightItem.classList.add('matched');
                        });
                        
                        console.log('✅ Restored matching type');
                    } else {
                        console.warn('⚠️ Matching answer is not an array');
                    }
                } catch (error) {
                    console.error('❌ Error restoring matching:', error);
                }
            }, 300);
        }
    }

    var tts = document.querySelector('#tts');
    var synth = window.speechSynthesis;
    var voices = [];
    var defaultVoice = "Microsoft David - English (United States)"; // Set the default voice name

    PopulateVoices();
    if(speechSynthesis !== undefined){
        speechSynthesis.onvoiceschanged = PopulateVoices;
    }

    tts.addEventListener('click', ()=> {
        var questionText = document.getElementById('question-text').innerText;
        var questionNumber = document.getElementById('question-number').innerText;

        var questionNumSpeech = new SpeechSynthesisUtterance(`Question Number ${questionNumber}`);

        voices.forEach((voice)=> {
            if (voice.name === defaultVoice) {
                questionNumSpeech.voice = voice;
            }
        });

        synth.speak(questionNumSpeech);

        var questionTextSpeech = new SpeechSynthesisUtterance(questionText);
        // Set the default voice if available
        voices.forEach((voice)=>{
            if(voice.name === defaultVoice){
                questionTextSpeech.voice = voice;
            }
        });
        synth.speak(questionTextSpeech);

        var answers = document.querySelectorAll('#answers');
        answers.forEach((answers) => {
            var answerText = answers.innerText;
            var toSpeakAnswer = new SpeechSynthesisUtterance(answerText);
            voices.forEach((voice) => {
                if (voice.name === defaultVoice) {
                    toSpeakAnswer.voice = voice;
                }
            });
            synth.speak(toSpeakAnswer);
        });
    });

    function PopulateVoices(){
        voices = synth.getVoices();
    }

    //para macheck yung mga type written na answers regardless sa sagot
    function compareAnswersCaseInsensitive(userAnswer, correctAnswer) {
        // Trim whitespace
        const trimmedUserAnswer = userAnswer.trim();
        const trimmedCorrectAnswer = correctAnswer.trim();

        // If the lengths are different, it's not a match
        if (trimmedUserAnswer.length !== trimmedCorrectAnswer.length) {
            return false;
        }

        // Compare character by character (case-insensitive)
        return trimmedUserAnswer.toLowerCase() === trimmedCorrectAnswer.toLowerCase();
    }

    // When checking answers during quiz submission or grading
    function checkAnswer(userAnswer, correctAnswer, quizType) {
        if (quizType === 'Enumeration' || quizType === 'Identification') {
            // Split multiple answers if needed (for Enumeration type)
            if (quizType === 'Enumeration') {
                const userAnswers = userAnswer.split(',').map(answer => answer.trim());
                const correctAnswers = correctAnswer.split(',').map(answer => answer.trim());
                
                // Check if all user answers match correct answers (case-insensitive)
                return userAnswers.length === correctAnswers.length && 
                    userAnswers.every((answer, index) => 
                        compareAnswersCaseInsensitive(answer, correctAnswers[index])
                    );
            }
            
            // For Identification type, simple case-insensitive comparison
            return compareAnswersCaseInsensitive(userAnswer, correctAnswer);
        }
        
        // Handle other quiz types as needed
        return false;
    }

    function showQuestion(index) {
        if (index >= 0 && index < questions.length) {
            currentQuestion = index;
            document.getElementById('question-number').innerText = `${index + 1}.  `;
            document.getElementById('question-text').innerText = questions[index].question_text;

            const instructionsDiv = document.getElementById('instructions');
                if (!instructionsDiv) {
                    const newInstructionsDiv = document.createElement('div');
                    newInstructionsDiv.id = 'instructions';
                    newInstructionsDiv.style.cssText = 'font-family: Fredoka; color: #666; font-size: 16px; margin: 10px 0; padding: 10px; background-color: #f8f9fa; border-left: 4px solid #f8b500; border-radius: 4px;';
                    document.getElementById('question').insertBefore(newInstructionsDiv, document.getElementById('answers'));
                }
                
                const instructionsElement = document.getElementById('instructions');
                if (questions[index].instructions && questions[index].instructions.trim() !== '') {
                    instructionsElement.innerHTML = `<strong><i class="fas fa-info-circle" style="color: #f8b500;"></i> Instructions:</strong> ${questions[index].instructions}`;
                    instructionsElement.style.display = 'block';
                } else {
                    instructionsElement.style.display = 'none';
                }

            fetch('s_get_answers.php?question_id=' + questions[index].question_id)
            .then(response => response.json())
            .then(data => {  
                const answersDiv = document.getElementById('answers');
                answersDiv.innerHTML = '';

                if (quizType === 'True or False') {
                    ['True', 'False'].forEach((answerText, i) => {
                        const answerButton = document.createElement('button');
                        answerButton.innerText = answerText;
                        answerButton.className = 'answer-button';

                        answerButton.dataset.answerId = data[i].answer_id;
                        answerButton.dataset.answerText = answerText;

                        answerButton.onclick = function() {
                            const answerId = data[i].answer_id;
                            saveAnswer(questions[index].question_id, answerId);
                            document.querySelectorAll('.answer-button').forEach(btn => btn.classList.remove('selected'));
                            answerButton.classList.add('selected');
                            document.getElementById(`question-btn-${index}`).classList.add('answered');
                        };
                        answersDiv.appendChild(answerButton);
                    });    
                } else if (quizType === 'Drag & Drop') {
                    const questionContainer = document.createElement('div');
                    questionContainer.className = 'question-drag-container';

                    const dropZone = document.createElement('div');
                    dropZone.className = 'drop-zone';
                    dropZone.innerHTML = '<span class = "drop-zone-prompt">Drop answer here!</span>';
                    dropZone.setAttribute('data-question-id', questions[index].question_id);

                    const choicesContainer = document.createElement('div');
                    choicesContainer.className = 'choices-container';

                    data.forEach((answer, i ) => {
                        const draggable = document.createElement('div');
                        draggable.className = 'draggable';
                        draggable.setAttribute('draggable', 'true');
                        draggable.setAttribute('data-answer-id', answer.answer_id);
                        draggable.textContent = answer.answer_text;
                        // Add drag event listeners
                        draggable.addEventListener('dragstart', handleDragStart);
                        draggable.addEventListener('dragend', handleDragEnd);
                        choicesContainer.appendChild(draggable);
                    });

                    dropZone.addEventListener('dragover', handleDragOver);
                    dropZone.addEventListener('drop', handleDrop);
                    dropZone.addEventListener('dragenter', handleDragEnter);
                    dropZone.addEventListener('dragleave', handleDragLeave);
                    
                    // Append elements
                    questionContainer.appendChild(dropZone);
                    answersDiv.appendChild(questionContainer);
                    answersDiv.appendChild(choicesContainer);
                    
                    // If there's a saved answer, show it in the drop zone
                    if (userAnswers[questions[index].question_id]) {
                        const savedAnswer = data.find(a => a.answer_id === userAnswers[questions[index].question_id]);
                        if (savedAnswer) {
                            dropZone.innerHTML = savedAnswer.answer_text;
                            dropZone.classList.add('dropped');
                        }
                    }
                } else if (quizType === 'Enumeration') {
                    // Render input field for enumeration type
                    const answerInput = document.createElement('input');
                    answerInput.type = 'text';
                    answerInput.className = 'answer-input';
                    answerInput.placeholder = 'Enter your answers separated by commas';
                    answerInput.oninput = function() {
                        saveAnswer(questions[index].question_id, answerInput.value.trim());
                        document.getElementById(`question-btn-${index}`).classList.add('answered');
                    };
                    answersDiv.appendChild(answerInput);

                    // If there is a saved answer, show it in the input field
                    if (userAnswers[questions[index].question_id]) {
                        answerInput.value = userAnswers[questions[index].question_id];
                    }
                } else if (quizType === 'Matching Type') {
                    // Parse the left_items and right_items from the current question
                    const leftItems = JSON.parse(questions[index].left_items || '[]');
                    const rightItems = JSON.parse(questions[index].right_items || '[]');
                    
                    // Create match container
                    const matchContainer = document.createElement('div');
                    matchContainer.className = 'match-container';
                    
                    // Left items column
                    const leftColumn = document.createElement('div');
                    leftColumn.className = 'left-items';
                    leftColumn.innerHTML = '<h4>Items to Match</h4>';
                    
                    // Add left items (numbered)
                    leftItems.forEach((item, idx) => {
                        const matchItem = document.createElement('div');
                        matchItem.className = 'match-item';
                        matchItem.textContent = `${idx + 1}. ${item}`;
                        matchItem.dataset.answerId = `left_${idx}`;
                        matchItem.dataset.side = 'left';
                        matchItem.dataset.itemIndex = idx;
                        matchItem.addEventListener('click', function() {
                            selectMatchItem(this, questions[currentQuestion].question_id);
                        });
                        leftColumn.appendChild(matchItem);
                    });

                    // Right items column
                    const rightColumn = document.createElement('div');
                    rightColumn.className = 'right-items';
                    rightColumn.innerHTML = '<h4>Match With</h4>';
                    
                    // Add right items (lettered)
                    const letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];
                    rightItems.forEach((item, idx) => {
                        const matchItem = document.createElement('div');
                        matchItem.className = 'match-item';
                        matchItem.textContent = `${letters[idx]}. ${item}`;
                        matchItem.dataset.answerId = `right_${idx}`;
                        matchItem.dataset.side = 'right';
                        matchItem.dataset.itemIndex = idx;
                        matchItem.addEventListener('click', function() {
                            selectMatchItem(this, questions[currentQuestion].question_id);
                        });
                        rightColumn.appendChild(matchItem);
                    });

                    // Add columns to container
                    matchContainer.appendChild(leftColumn);
                    matchContainer.appendChild(rightColumn);
                    answersDiv.appendChild(matchContainer);

                    // Pairs display area
                    const pairsDisplay = document.createElement('div');
                    pairsDisplay.className = 'pairs-display';
                    pairsDisplay.innerHTML = '<h4>Your Matches:</h4><div id="pairs-list-' + questions[currentQuestion].question_id + '"></div>';
                    answersDiv.appendChild(pairsDisplay);

                    // Clear button
                    const clearBtn = document.createElement('button');
                    clearBtn.textContent = 'Clear All Matches';
                    clearBtn.className = 'clear-matches-btn';
                    clearBtn.addEventListener('click', function() {
                        clearAllMatches(questions[currentQuestion].question_id);
                    });
                    answersDiv.appendChild(clearBtn);

                    // Initialize matching data for this question
                    if (!window.matchingData) {
                        window.matchingData = {};
                    }
                    window.matchingData[questions[currentQuestion].question_id] = {
                        selectedLeft: null,
                        selectedRight: null,
                        matches: []
                    };

                    // Load any saved matches
                    if (userAnswers[questions[currentQuestion].question_id]) {
                        try {
                            const savedMatches = JSON.parse(userAnswers[questions[currentQuestion].question_id]);
                            if (Array.isArray(savedMatches)) {
                                window.matchingData[questions[currentQuestion].question_id].matches = savedMatches;
                                updateMatchesDisplay(questions[currentQuestion].question_id);
                                
                                // Mark matched items
                                savedMatches.forEach(match => {
                                    const leftItem = document.querySelector(`[data-answer-id="${match.left}"]`);
                                    const rightItem = document.querySelector(`[data-answer-id="${match.right}"]`);
                                    if (leftItem) leftItem.classList.add('matched');
                                    if (rightItem) rightItem.classList.add('matched');
                                });
                            }
                        } catch (e) {
                            console.error('Error parsing saved matches:', e);
                        }
                    }
        
                    // Mark question as answered if there are matches
                    if (window.matchingData[questions[currentQuestion].question_id].matches.length > 0) {
                        document.getElementById(`question-btn-${currentQuestion}`).classList.add('answered');
                    }
                } else if (quizType === 'Identification') {
                    // Render input field for identification type
                    const answerInput = document.createElement('input');
                    answerInput.type = 'text';
                    answerInput.className = 'answer-input';
                    answerInput.placeholder = 'Enter your answers';
                    answerInput.oninput = function() {
                        saveAnswer(questions[index].question_id, answerInput.value.trim());
                        document.getElementById(`question-btn-${index}`).classList.add('answered');
                    };
                    answersDiv.appendChild(answerInput);

                    // If there is a saved answer, show it in the input field
                    if (userAnswers[questions[index].question_id]) {
                        answerInput.value = userAnswers[questions[index].question_id];
                    }
                } else if (quizType === 'Fill in the Blanks') {
                    // Handle fill-in-the-blank type
                    const fillInput = document.createElement('input');
                    fillInput.type = 'text';
                    fillInput.className = 'answer-input';
                    fillInput.placeholder = 'Enter your answer here';
                    fillInput.oninput = function() {
                        saveAnswer(questions[index].question_id, fillInput.value.trim());
                        document.getElementById(`question-btn-${index}`).classList.add('answered');
                    };
                    answersDiv.appendChild(fillInput);

                    if (userAnswers[questions[index].question_id]) {
                        fillInput.value = userAnswers[questions[index].question_id];
                    }
                } else {
                    const labels = ['A', 'B', 'C', 'D'];
                    data.forEach((answer , i) => {
                        const answerButton = document.createElement('button');
                        answerButton.innerText = `${labels[i]}. ${answer.answer_text}`;
                        answerButton.className = 'answer-button';

                        answerButton.dataset.answerId = answer.answer_id;
                        answerButton.dataset.answerText = answer.answer_text;

                        answerButton.onclick = function() {
                            saveAnswer(questions[index].question_id, answer.answer_id);
                            document.querySelectorAll('.answer-button').forEach(btn => btn.classList.remove('selected'));
                            answerButton.classList.add('selected');
                            document.getElementById(`question-btn-${index}`).classList.add('answered');
                        };
                        answersDiv.appendChild(answerButton);
                    });
                    
                }

            })
            .then (() => {
                setTimeout(() => {
                    const questionId = questions[index].question_id;
                    if (userAnswers[questionId]) {
                        console.log(`🔄 Attempting to restore Q${questionId}:`, userAnswers[questionId]);
                        restoreCurrentQuestionAnswer(questionId, userAnswers[questionId]);
                    } else {
                        console.log(`No saved answers for Q${questionId}`);
                    }
                }, 500);
            })
            .catch(error => {
                console.error('Error in showQuestion:', error);
            });
        }
    }

    function saveAnswer(questionId, answerId) {
        userAnswers[questionId] = answerId;
    }

    let draggingElement = null;

    function handleDragStart(e) {
        this.classList.add('dragging');
        draggingElement = this;
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', this.getAttribute('data-answer-id'));
    }

    function handleDragEnd(e) {
        this.classList.remove('dragging');
        draggingElement = null;
    }

    function handleDragOver(e) {
        if (e.preventDefault) {
            e.preventDefault();
        }
        e.dataTransfer.dropEffect = 'move';
        return false;
    }

    function handleDragEnter(e) {
        this.classList.add('dragover');
    }

    function handleDragLeave(e) {
        this.classList.remove('dragover');
    }

    function handleDrop(e) {
        e.preventDefault();
        this.classList.remove('dragover');
        
        if (draggingElement) {
            const answerId = draggingElement.getAttribute('data-answer-id');
            const questionId = this.getAttribute('data-question-id');
            
            // Save the answer
            saveAnswer(questionId, answerId);
            
            // Update drop zone appearance
            this.innerHTML = draggingElement.textContent;
            this.classList.add('dropped');
            
            // Mark question as answered
            document.getElementById(`question-btn-${currentQuestion}`).classList.add('answered');
        }
        
        return false;
    }

    function selectMatchItem(item, questionId) {
        const side = item.dataset.side;
        const matchData = window.matchingData[questionId];
        
        // Remove previous selection from the same side
        document.querySelectorAll(`.match-item[data-side="${side}"]`).forEach(el => {
            if (el.closest('.quiz-cont') && !el.classList.contains('matched')) {
                el.classList.remove('selected');
            }
        });

        // Don't allow selection of already matched items
        if (item.classList.contains('matched')) {
            return;
        }

        // Select current item
        item.classList.add('selected');

        if (side === 'left') {
            matchData.selectedLeft = item;
        } else {
            matchData.selectedRight = item;
        }

        // If both sides have selections, create a match
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
        
        // Check if either item is already matched
        const existingMatch = matchData.matches.find(m => m.left === leftId || m.right === rightId);
        if (existingMatch) {
            alert('One of these items is already matched. Clear existing matches first.');
            return;
        }

        // Create the match
        const match = {
            left: leftId,
            leftText: leftItem.textContent,
            right: rightId,
            rightText: rightItem.textContent
        };
        
        matchData.matches.push(match);
        
        // Update display
        updateMatchesDisplay(questionId);
        
        // Save to answers (stringify the matches array)
        saveAnswer(questionId, JSON.stringify(matchData.matches));
        
        // Clear selections
        leftItem.classList.remove('selected');
        rightItem.classList.remove('selected');
        leftItem.classList.add('matched');
        rightItem.classList.add('matched');
        
        matchData.selectedLeft = null;
        matchData.selectedRight = null;
        
        // Mark question as answered
        document.getElementById(`question-btn-${currentQuestion}`).classList.add('answered');
    }


    function updateMatchesDisplay(questionId) {
        const matchData = window.matchingData[questionId];
        const pairsList = document.getElementById('pairs-list-' + questionId);
        
        pairsList.innerHTML = '';
        
        matchData.matches.forEach((match, index) => {
            const pairElement = document.createElement('div');
            pairElement.className = 'match-pair';
            pairElement.style.display = 'flex';
            pairElement.style.justifyContent = 'space-between';
            pairElement.style.alignItems = 'center';
            pairElement.style.padding = '8px 12px';
            pairElement.style.backgroundColor = '#FCEF91';
            pairElement.style.border = '1px solid #F8B500';
            pairElement.style.borderRadius = '5px';
            pairElement.style.marginBottom = '5px';
            
            pairElement.innerHTML = `
                <span style="flex: 1; font-weight: 500; color: black;">${match.leftText}</span>
                <span style="margin: 0 10px; font-weight: 500; color: #28a745;">↔</span>
                <span style="flex: 1; font-weight: 500; color: black;">${match.rightText}</span>
                <button onclick="removeMatch(${questionId}, ${index})" style="
                    background: #dc3545; 
                    color: white; 
                    border: none; 
                    border-radius: 3px; 
                    padding: 2px 6px; 
                    cursor: pointer; 
                    font-size: 12px;
                    margin-left: 10px;
                ">×</button>
            `;
            
            pairsList.appendChild(pairElement);
        });
        
        if (matchData.matches.length === 0) {
            pairsList.innerHTML = '<p style="color: #666; font-style: italic;">No matches yet</p>';
        }
    }

    function removeMatch(questionId, matchIndex) {
        const matchData = window.matchingData[questionId];
        const removedMatch = matchData.matches[matchIndex];
        
        // Remove the match
        matchData.matches.splice(matchIndex, 1);
        
        // Remove 'matched' class from items
        document.querySelectorAll('.match-item').forEach(item => {
            if (item.dataset.answerId === removedMatch.left || item.dataset.answerId === removedMatch.right) {
                item.classList.remove('matched');
            }
        });
        
        // Update display and save
        updateMatchesDisplay(questionId);
        saveAnswer(questionId, matchData.matches);
        
        // Unmark question as answered if no matches left
        if (matchData.matches.length === 0) {
            document.getElementById(`question-btn-${currentQuestion}`).classList.remove('answered');
        }
    }

    function clearAllMatches(questionId) {
        const matchData = window.matchingData[questionId];
        
        // Clear all matches
        matchData.matches = [];
        matchData.selectedLeft = null;
        matchData.selectedRight = null;
        
        // Remove all visual indicators
        document.querySelectorAll('.match-item').forEach(item => {
            item.classList.remove('selected', 'matched');
        });
        
        // Update display and save
        updateMatchesDisplay(questionId);
        delete userAnswers[questionId];
        
        // Unmark question as answered
        document.getElementById(`question-btn-${currentQuestion}`).classList.remove('answered');
    }

    function saveAnswer(questionId, answerId) {
        userAnswers[questionId] = answerId;
    }

    function nextQuestion() {
        if (currentQuestion < questions.length - 1) {
        showQuestion(currentQuestion + 1);
        }
    }

    function previousQuestion() {
        if (currentQuestion > 0) {
        showQuestion(currentQuestion - 1);
        }
    }

    // Enhanced back button detection and automatic submission
    function setupBackButtonDetection() {

        let isSubmitting = false;

        // Detect back button or page navigation
        window.addEventListener('popstate', function(event) {
            if (!window.isSubmitting && Object.keys(userAnswers).length > 0 && !window.isIntentionalSubmit) {
                event.preventDefault();
                handleUnloadWithAnswers();
            }
        });

        // Detect browser page hide/show events
        window.addEventListener('pageshow', function(event) {
            if ((event.persisted ||
                (window.performance && 
                window.performance.navigation.type === window.performance.navigation.TYPE_BACK_FORWARD)) && !window.isSubmitting &&Object.keys(userAnswers).length > 0 && !window.isIntentionalSubmit) {
                    
                handleUnloadWithAnswers();
            }
        });

        // Handle page visibility changes
        document.addEventListener('visibilitychange', function() {
            if (document.visibilityState === 'hidden' && 
                !window.isSubmitting &&
                Object.keys(userAnswers).length > 0 && !window.isIntentionalSubmit) {
                
                handleUnloadWithAnswers();
            }
        });

        // Add beforeunload event to catch navigation attempts
        window.addEventListener('beforeunload', function(event) {
            if (!window.isSubmitting && Object.keys(userAnswers).length > 0 && !window.isIntentionalSubmit) {
                
                console.log('Page about to unload with answers');
                submitQuiz(true);

                event.preventDefault();
                event.returnValue = '';
                return 'You have unsaved answers. Are you sure you want to leave?';
            }
        });
    }    

    function handleUnloadWithAnswers() {
        // Prevent multiple submissions
        if (window.isSubmitting) return;

        // Confirm with user before submitting
        const confirmSubmit = confirm('You have unsaved answers. Do you want to submit the quiz?');
        
        if (confirmSubmit) {
            window.isSubmitting = true;
            submitQuiz(true);
        }
    }

    function submitQuiz(isPartialSubmit = false) {
        // If no answers and not explicitly marked for partial submit, skip submission
        if (Object.keys(userAnswers).length === 0 && !isPartialSubmit) {
            return;
        }

        // Prevent multiple submissions
        if (window.isSubmitting) {
            return;
        }

        window.isIntentionalSubmit = !isPartialSubmit;
        window.isSubmitting = true;

        if (window.timerInterval) {
            clearInterval(window.timerInterval);
        }

        if (autoSaveInterval) {
            clearInterval(autoSaveInterval);
        }

        // Prepare the data to send
        const submitData = {
            answers: userAnswers,
            quiz_id: <?php echo $quiz_id; ?>,
            partial_submit: isPartialSubmit
        };

        fetch('s_submit_quiz.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(submitData)
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })    
        .then(data => {
            if (data.success) {
                // Store the result in session before redirecting
                const resultData = {
                    quiz_id: <?php echo $quiz_id; ?>,
                    score: data.score,
                    total: data.total,
                    wrong_answers: data.wrong_answers,
                    subject_id: <?php echo $subject_id; ?> 
                };

                // Store the result in session via PHP
                fetch('store_quiz_result.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(resultData)
                })
                .then(() => {
                    window.location.href = 'quiz_result.php';
                })
                .catch(error => {
                    console.error('Error storing result:', error);
                    window.location.href = 'select_quiz.php?subject_id=<?php echo $subject_id; ?>';
                });
            } else {
                // Show the actual error message from server
                const errorMsg = data.error || 'Unknown error occurred';
                alert('Error submitting quiz: ' + errorMsg);
                window.isSubmitting = false;

                if (remainingTimeSeconds > 0) {
                    startTimer(remainingTimeSeconds);
                    autoSaveInterval = setInterval(autoSaveProgress, AUTO_SAVE_INTERVAL);
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('There was an error submitting your quiz. Please try again.');
            window.isSubmitting = false;

            if (remainingTimeSeconds > 0) {
                startTimer(remainingTimeSeconds);
                autoSaveInterval = setInterval(autoSaveProgress, AUTO_SAVE_INTERVAL);
            }
        });   
    }

    function startTimer(duration) {
        let timer = duration;
        const timerElement = document.getElementById('timer');
        
        console.log(`🕐 Timer started with ${timer} seconds (${Math.floor(timer/60)}:${timer%60})`);

        // Clear any existing timer first
        if (window.timerInterval) {
            clearInterval(window.timerInterval);
        }

        window.timerInterval = setInterval(function () {
            let minutes = parseInt(timer / 60, 10);
            let seconds = parseInt(timer % 60, 10);

            minutes = minutes < 10 ? "0" + minutes : minutes;
            seconds = seconds < 10 ? "0" + seconds : seconds;

            timerElement.textContent = `${minutes}:${seconds}`;

            // Check if time has expired
            if (timer <= 0) {
                clearInterval(window.timerInterval);
                clearInterval(autoSaveInterval); // STOP AUTO-SAVING
                
                console.log('⏰ Timer expired - showing time up modal');
                
                // Set flags to prevent multiple submissions
                window.isSubmitting = true;
                window.isIntentionalSubmit = true;
                
                // Show modal to user
                showTimeUpModal();
                
                return; // Exit the interval
            }
            
            timer--; // Decrement timer
        }, 1000);
    }

    function goToQuestion(index) {
        showQuestion(index);
    }

    window.onload = function() {
        // Check if we need to show modal
        <?php if ($showModal): ?>
            showAlertModal(
                <?php echo json_encode($modalTitle); ?>,
                <?php echo json_encode($modalMessage); ?>,
                <?php echo json_encode($modalRedirect); ?>
            );
            return; // Stop further initialization
        <?php endif; ?>

        console.log('🚀 Page loaded, initializing quiz...');
        
        window.matchingData = {};
        
        console.log('📥 Loading saved answers...');
        console.log('📊 Prefilled answers from server:', <?php echo json_encode($prefilledAnswers); ?>);
        restoreSavedAnswers();
        
        setTimeout(() => {
            console.log('📝 Showing first question...');
            showQuestion(0);
        }, 100);
        
        // FIX: Start timer with the correct remaining time from PHP
        console.log('🕐 Starting timer with:', remainingTimeSeconds, 'seconds');
        startTimer(remainingTimeSeconds);
        
        const questionButtonsDiv = document.getElementById('question-buttons');
        questions.forEach((question, index) => {
            const questionButton = document.createElement('button');
            questionButton.innerText = index + 1;
            questionButton.id = `question-btn-${index}`;
            questionButton.className = 'question-btn';
            
            if (userAnswers[question.question_id]) {
                questionButton.classList.add('answered');
            }
            
            questionButton.onclick = function() {
                goToQuestion(index);
            };
            questionButtonsDiv.appendChild(questionButton);
        });
        
        window.isSubmitting = false;
        window.isIntentionalSubmit = false;

        console.log('💾 Starting auto-save interval...');
        autoSaveInterval = setInterval(autoSaveProgress, AUTO_SAVE_INTERVAL);

        setupBackButtonDetection();

        console.log('✅ Quiz initialization complete');
    };
    
    function showTimeUpModal() {
        const modal = document.getElementById('timeUpModal');
        modal.style.display = 'flex';
    }

    function confirmTimeUp() {
        document.getElementById('timeUpModal').style.display = 'none';
        
        // Submit quiz and redirect to results
        const submitData = {
            answers: userAnswers,
            quiz_id: <?php echo $quiz_id; ?>,
            partial_submit: true
        };

        fetch('s_submit_quiz.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(submitData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const resultData = {
                    quiz_id: <?php echo $quiz_id; ?>,
                    score: data.score,
                    total: data.total,
                    wrong_answers: data.wrong_answers,
                    subject_id: <?php echo $subject_id; ?>
                };

                fetch('store_quiz_result.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(resultData)
                })
                .then(() => {
                    window.location.href = 'quiz_result.php';
                })
                .catch(error => {
                    console.error('Error storing result:', error);
                    window.location.href = 'quiz_result.php';
                });
            } else {
                alert('Error submitting quiz: ' + (data.error || 'Unknown error'));
                window.location.href = 'select_quiz.php?subject_id=<?php echo $subject_id; ?>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error submitting quiz. Redirecting...');
            window.location.href = 'select_quiz.php?subject_id=<?php echo $subject_id; ?>';
        });
    }

</script>

</body>
</html>