<?php
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    
    session_start();
    // Check if account_number exists in session and starts with 'S'
    if (!isset($_SESSION['account_number'])) {
        header("Location: login.php");
        exit();
    }

    if (strpos($_SESSION['account_number'], 'S') !== 0) {
        header("Location: login.php");
        exit();
    }

    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Take a Quiz</title>
    <style>
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
        
    </style>
    </head>
    <body>
        <div id="quizAlertModal" class="modal" style="display: none;">
            <div class="modal-content">
                <h2 id="modalTitle">Alert</h2>
                <p id="modalMessage"></p>
                <button onclick="handleModalClose()" class="modal-close-btn">OK</button>
            </div>
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
        let isSubmitting = false;
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

        // Only prevent default behavior during submission
        window.addEventListener("beforeunload", function(e) {
            if (isSubmitting) {
                e.preventDefault();
                e.returnValue = "";
                return "";
            }
        });

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
    </script>';

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

    $student_id = $_SESSION['account_number']; // Assuming account_number is the student ID

    // First get the proper student_id from students table
    $studentQuery = "SELECT student_id FROM students WHERE account_number = ?";
    $stmt = $conn->prepare($studentQuery);

    // Check if prepare was successful
    if (!$stmt) {
        die("Prepare failed: (" . $conn->errno . ") " . $conn->error);
    }

    $stmt->bind_param("s", $_SESSION['account_number']);
    if (!$stmt->execute()) {
        die("Execute failed: (" . $stmt->errno . ") " . $stmt->error);
    }

    $result = $stmt->get_result();
    $student = $result->fetch_assoc();
    $stmt->close();

    if ($student) {
        // Now query student_answers with the proper student_id
        $answersQuery = "SELECT question_id, answer FROM student_answers 
                        WHERE quiz_id = ? AND student_id = ?";
        $stmt = $conn->prepare($answersQuery);
        
        if (!$stmt) {
            die("Prepare failed: (" . $conn->errno . ") " . $conn->error);
        }
        
        $stmt->bind_param("ii", $quiz_id, $student['student_id']);
        if (!$stmt->execute()) {
            die("Execute failed: (" . $stmt->errno . ") " . $stmt->error);
        }
        
        $savedAnswers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        // Convert answers to proper format
        $prefilledAnswers = [];
        foreach ($savedAnswers as $answer) {
            // Try to decode JSON answers, fall back to raw value
            $decoded = json_decode($answer['answer'], true);
            $prefilledAnswers[$answer['question_id']] = ($decoded !== null) ? $decoded : $answer['answer'];
        }
    } else {
        $prefilledAnswers = []; // No student record found
    }

    date_default_timezone_set('Asia/Manila');

    // Use this for your date comparison
    $currentDate = new DateTime('now', new DateTimeZone('Asia/Manila'));
    // Check quiz availability and attempts
    $currentDate = date('Y-m-d H:i:s');
    $quizQuery = "SELECT q.* FROM quizzes q WHERE q.quiz_id = ?";
    $stmt = $conn->prepare($quizQuery);
    if (!$stmt) {
        // Add error handling to see what's wrong with the query
        die("Prepare failed: (" . $conn->errno . ") " . $conn->error);
    }
    $stmt->bind_param("i", $quiz_id);
    if (!$stmt->execute()) {
        die("Execute failed: (" . $stmt->errno . ") " . $stmt->error);
    }
    $quiz = $stmt->get_result()->fetch_assoc();

    if(!$quiz) {
        echo json_encode(["success" => false, "error" => "Quiz not found."]);
        exit;
    }

    $subject_id = $quiz['subject_id'];

    $error = null;

    // Check availability
    if ($quiz['start_date'] && $currentDate < $quiz['start_date']) {
        $error = "This quiz is not available yet. It will be available starting " . date('M j, Y g:i A', strtotime($quiz['start_date']));
    } 
    else if ($quiz['end_date'] && $currentDate > $quiz['end_date']) {
        $error = "This quiz is no longer available. It ended on " . date('M j, Y g:i A', strtotime($quiz['end_date']));
    } 

    if ($error) {
        // Instead of die(), we'll pass the error to JavaScript
        echo '<script>showAlertModal("Quiz Not Available", "' . addslashes($error) . '", "select_quiz.php?subject_id=' . $subject_id . '");</script>';
        // Still need to exit to prevent rendering the quiz
        exit();
    }    

    // Limit to 10 questions
    $sql = "SELECT question_id, question_text, question_type, left_items, right_items, instructions FROM questions WHERE quiz_id = $quiz_id";
    $result = $conn->query($sql);

    $questions = [];
    while ($row = $result->fetch_assoc()) {
        $questions[] = $row;
    }

    // First check for existing attempts before creating a new one
    $attemptQuery = "SELECT * FROM quiz_attempts 
                    WHERE quiz_id = ? AND account_number = ?";
    $stmt = $conn->prepare($attemptQuery);
    if (!$stmt) {
        $error = "Database error: " . $conn->error;
    } else {
        $stmt->bind_param("is", $quiz_id, $student_id);
        if (!$stmt->execute()) {
            $error = "Database error: " . $stmt->error;
        } else {
            $existingAttempt = $stmt->get_result()->fetch_assoc();
            
            // Only show error if attempt is completed (add a 'completed' field to your table)
            if ($existingAttempt && $existingAttempt['completed']) {
                $error = "You have already taken this quiz. Only one attempt is allowed.";
                echo '<script>
                    showAlertModal("Quiz Attempt", "'.addslashes($error).'", "select_quiz.php?subject_id='.$subject_id.'");
                </script>';    
            }
        }
        $stmt->close();
    }

    // Only create new attempt if none exists
    if (!$existingAttempt) {
        $insertAttempt = "INSERT INTO quiz_attempts (quiz_id, account_number, attempt_time) 
                        VALUES (?, ?, NOW())";
        $stmt = $conn->prepare($insertAttempt);
        $stmt->bind_param("is", $quiz_id, $student_id);
        $stmt->execute();
        $attempt_id = $conn->insert_id;
        $stmt->close();
    } else {
        $attempt_id = $existingAttempt['attempt_id'];
        
        // Calculate remaining time
        $start_time = strtotime($existingAttempt['attempt_time']);
        $elapsed = time() - $start_time;
        $remaining_time = max(0, ($quiz['timer'] * 60) - $elapsed);
        
        // Load saved answers
        $answersQuery = "SELECT question_id, answer FROM student_answers WHERE quiz_id = ? AND student_id = ?";
        $stmt = $conn->prepare($answersQuery);
        $student_id_int = (int) str_replace('S', '', $_SESSION['account_number']);
        $stmt->bind_param("ii", $quiz_id, $student_id_int);
        $stmt->execute();
        $savedAnswers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Convert to format expected by frontend
        $prefilledAnswers = [];
        foreach ($savedAnswers as $answer) {
            $prefilledAnswers[$answer['question_id']] = json_decode($answer['answer_data'], true);
        }
    }

    $attempt_id = null;
    $remaining_time = $quiz['timer'] * 60; // Default to full time

    if ($existingAttempt) {
        $attempt_id = $existingAttempt['attempt_id'];
        
        // Calculate remaining time
        $start_time = strtotime($existingAttempt['attempt_time']);
        $elapsed = time() - $start_time;
        $remaining_time = max(0, ($quiz['timer'] * 60) - $elapsed);
        
        // Load saved answers
        $answersQuery = "SELECT question_id, answer FROM student_answers WHERE quiz_id = ? AND student_id = ?";
        $stmt = $conn->prepare($answersQuery);
        $student_id_int = (int) str_replace('S', '', $_SESSION['account_number']);
        $stmt->bind_param("ii", $quiz_id, $student_id_int);
        $stmt->execute();
        $savedAnswers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Convert to format expected by frontend
        $prefilledAnswers = [];
        foreach ($savedAnswers as $answer) {
            $prefilledAnswers[$answer['question_id']] = json_decode($answer['answer_data'], true);
        }
    } else {
        // Create new attempt
        $insertAttempt = "INSERT INTO quiz_attempts (quiz_id, account_number, attempt_time) 
                        VALUES (?, ?, NOW())";
        $stmt = $conn->prepare($insertAttempt);
        $stmt->bind_param("is", $quiz_id, $student_id);
        $stmt->execute();
        $attempt_id = $conn->insert_id;
        $stmt->close();

        $prefilledAnswers = [];
    }

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
            }

            header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 20px;
                background-color: white;
            }

            header .logo {
                font-size: 24px;
                font-weight: bold;
                margin-left: 30px;
                margin-top: 3px;
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

            /* Add this to your question-text class to position the TTS button */
            .question-text {
                color: black;
                font-size: 23px;
                font-weight: 600;
                margin-bottom: 15px;
                position: relative; /* Add this */
                padding-right: 40px; /* Add space for TTS button */
            }

            .question {
                background-color: #fff5e1;
                border: 1px solid #f0c808;
                border-radius: 8px;
                padding: 30px;
                margin-bottom: 30px;
            }

            .question-text {
                color: black;
                font-size: 23px;
                font-weight: 600;
                margin-bottom: 15px;
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
            }

            .drag-item {
                background-color: #fff5e1;
                border: 1px solid #f8b500;
                padding: 10px;
                margin: 5px 0;
                cursor: move;
                border-radius: 5px;
                color: black;
            }

            .drop-zone {
                border: 2px dashed #f8b500;
                border-radius: 10px;
                padding: 15px;
                min-height: 50px;
                margin-bottom: 15px;
            }

            .drop-zone h4 {
                font-weight: lighter;
                margin-bottom: 10px;
                color: #333;
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

            .match-item {
                padding: 10px;
                margin: 5px 0;
                cursor: pointer;
                border-radius: 5px;
                background-color: #f9f9f9;
                border: 1px solid #ddd;
                color: black;
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

            .pairs-display {
                margin-top: 20px;
                padding: 15px;
                background-color: #f9f9f9;
                border-radius: 8px;
                border: 1px solid #ddd;
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

            .loading-spinner {
                border: 3px solid #f3f3f3;
                border-top: 3px solid #f8b500;
                border-radius: 50%;
                width: 20px;
                height: 20px;
                animation: spin 1s linear infinite;
            }

            .success-message {
                color: #28a745; /* Green color for success */
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

            /* Success state for auto-save */
            .auto-save-success .check-icon-path {
                stroke: #28a745;
            }

            /* Error state for auto-save */
            .auto-save-error .check-icon-path {
                stroke: #dc3545;
            }

            .instruction-label {
                font-weight: 500;
                color: black;
                margin-bottom: 3px;
            }

            .instruction-text {
                color: #555;
                font-size: 15px;
                margin-bottom: 15px;
                padding: 8px;
                border-radius: 0 4px 4px 0;
            }
            
        </style>
    </head>
    <body>

    <div id="loadingMessage" class="loading-message" style="display: none;">
        <div class="loading-spinner"></div>
        <span id="loadingText">Loading your saved answers...</span>
    </div>

    <!-- Auto-save message styles -->
    <div id="autoSaveMessage" class="loading-message" style="display: none;">
        <div class="check-icon">
            <svg viewBox="0 0 24 24" class="check-icon-svg">
                <path class="check-icon-path" fill="none" stroke="#28a745" stroke-width="2" d="M3 12.5L8.5 18L21 5"/>
            </svg>
        </div>
        <span id="autoSaveText">Auto-saving your progress...</span>
    </div>
    
    <?php if (!$error):?>
    <header>
        <div class="logo"><img src="img/logo1.png" onclick="window.location.href='s_Home.php';" style="cursor: pointer;" width="200px" height="80px"></div>
        <div class="actions">
            <div class="profile"><img src="img/default.png" width="50px" height="50px"></div>
        </div>
    </header>

    <div class="quiz-container">
        <div id="quiz-header">
            <h1><?php echo htmlspecialchars($quiz['title']); ?></h1> 
            <div id="timer" class="timer"><?php echo $quiz['timer']; ?></div>
        </div><br><br>


        <div id="quiz-questions">
            <!-- Questions will be dynamically inserted here -->
        </div>

        <div class="submit-cont">
            <button onclick="submitQuiz()" class="submit-btn">Submit Quiz</button>
        </div>
    </div>

    <script>
        
        const questions = <?php echo json_encode($questions); ?>;
        const userAnswers = {};
        const timerDuration = <?php echo $quiz['timer'] * 60; ?>;

        // Auto-save interval (every 10 seconds)
        const AUTO_SAVE_INTERVAL = 10000;
        let autoSaveInterval;

        // TTS variables
        let synth = window.speechSynthesis;
        let voices = [];
        let defaultVoice = "Microsoft David - English (United States)"; // Set the default voice name

        function populateVoices() {
            voices = synth.getVoices();
            if (voices.length > 0) {
                // Try to find our preferred voice
                const preferredVoice = voices.find(voice => voice.name === defaultVoice);
                if (preferredVoice) {
                    defaultVoice = preferredVoice.name;
                }
            }
        }

        // Initialize voices when they become available
        populateVoices();
        if (speechSynthesis.onvoiceschanged !== undefined) {
            speechSynthesis.onvoiceschanged = populateVoices;
        }

        // Function to speak text
        function speakText(text, questionNumber = null) {
            // Cancel any ongoing speech
            synth.cancel();
            
            // If we have a question number, speak that first
            if (questionNumber !== null) {
                const questionNumSpeech = new SpeechSynthesisUtterance(`Question Number ${questionNumber}`);
                const questionTextSpeech = new SpeechSynthesisUtterance(text);
                
                // Set the voice for both utterances
                voices.forEach((voice) => {
                    if (voice.name === defaultVoice) {
                        questionNumSpeech.voice = voice;
                        questionTextSpeech.voice = voice;
                    }
                });
                
                // Queue both to speak in order
                synth.speak(questionNumSpeech);
                synth.speak(questionTextSpeech);
            } else {
                const utterance = new SpeechSynthesisUtterance(text);
                
                // Set the voice
                voices.forEach((voice) => {
                    if (voice.name === defaultVoice) {
                        utterance.voice = voice;
                    }
                });
                
                synth.speak(utterance);
            }
        }

        // Function to create TTS button for a question
        function createTTSButton(questionId, questionText, questionNumber) {
            const ttsButton = document.createElement('i');
            ttsButton.className = 'fa-solid fa-volume-high';
            ttsButton.id = `tts-${questionId}`;
            ttsButton.style.position = 'absolute';
            ttsButton.style.right = '10px';
            ttsButton.style.top = '0';
            ttsButton.style.cursor = 'pointer';
            ttsButton.style.color = '#f8b500';
            ttsButton.addEventListener('click', () => {
                // Speak the question text
                speakText(questionText, questionNumber);
                
                // After a short delay, speak the answer options if they exist
                setTimeout(() => {
                    const answersDiv = document.getElementById(`answers-${questionId}`);
                    if (answersDiv) {
                        const answerTexts = [];
                        
                        // Handle different question types
                        const questionType = document.querySelector(`[data-question-id="${questionId}"]`).dataset.questionType;
                        
                        if (questionType === 'multiple_choice' || questionType === 'true_or_false') {
                            // For multiple choice or true/false, read all options
                            const answerButtons = answersDiv.querySelectorAll('.answer-button');
                            answerButtons.forEach(button => {
                                answerTexts.push(button.textContent.trim());
                            });
                        } else if (questionType === 'drag_and_drop') {
                            // For drag and drop, read the available options
                            const draggables = answersDiv.querySelectorAll('.drag-item');
                            if (draggables.length > 0) {
                                answerTexts.push('Drag and drop options:');
                                draggables.forEach(item => {
                                    answerTexts.push(item.textContent.trim());
                                });
                            }
                        } else if (questionType === 'matching_type') {
                            // For matching type, explain the interface
                            answerTexts.push('Matching type question. Select items from left and right columns to match them.');
                        }
                        
                        // Speak all answer texts with a small delay between them
                        answerTexts.forEach((text, index) => {
                            setTimeout(() => {
                                speakText(text);
                            }, index * 1500);
                        });
                    }
                }, 2000);
            });
            
            return ttsButton;
        }

        // Function to save progress to server
        function autoSaveProgress() {
            if (Object.keys(userAnswers).length === 0) {
                console.log("No answers to save");
                return;
            }

            console.log("Auto-saving progress:", userAnswers);

            // Show auto-save message
            const autoSaveMessage = document.getElementById('autoSaveMessage');
            const checkIcon = autoSaveMessage.querySelector('.check-icon-svg');
            autoSaveMessage.style.display = 'flex';
            autoSaveMessage.className = 'loading-message'; // Reset classes
            document.getElementById('autoSaveText').textContent = 'Auto-saving your progress...';
            
            // Reset the check icon animation
            checkIcon.querySelector('.check-icon-path').style.strokeDashoffset = '24';
            
            fetch('allZapped_saveProgress.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    attempt_id: <?php echo $attempt_id; ?>,
                    answers: userAnswers,
                    time_remaining: document.getElementById('timer').textContent,
                    quiz_id: <?php echo $quiz_id; ?>
                })
            })
            .then(response => {
                // First check if the response is JSON
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    return response.text().then(text => {
                        throw new Error(`Invalid response: ${text}`);
                    });
                }
                return response.json();
            })
            .then(data => {
                if (!data.success) {
                    console.error('Auto-save failed:', data.error);
                    autoSaveMessage.classList.add('auto-save-error');
                    document.getElementById('autoSaveText').textContent = 'Auto-save failed - using local backup';localStorage
                    localStorage.setItem(`quizProgress_${<?php echo $quiz_id; ?>}`, 
                        JSON.stringify({
                            answers: userAnswers,
                            timer: document.getElementById('timer').textContent,
                            timestamp: new Date().getTime()
                        }));
                } else {
                    console.log("Auto-save successful");
                    autoSaveMessage.classList.add('auto-save-success');
                    document.getElementById('autoSaveText').textContent = 'Progress saved successfully!';
                }

                // Animate the check mark
                setTimeout(() => {
                    checkIcon.querySelector('.check-icon-path').style.animation = 'drawCheck 0.5s ease-in-out forwards';
                    
                    // Fade out after 1.5 seconds
                    setTimeout(() => {
                        autoSaveMessage.classList.add('fade-out');
                        setTimeout(() => {
                            autoSaveMessage.style.display = 'none';
                        }, 1500);
                    }, 1000);
                }, 100);
            })
            .catch(error => {
                console.error('Auto-save error:', error);
                autoSaveMessage.classList.add('auto-save-error');
                document.getElementById('autoSaveText').textContent = 'Auto-save error - using local backup';
                // Fallback to localStorage
                localStorage.setItem(`quizProgress_${<?php echo $quiz_id; ?>}`, 
                    JSON.stringify({
                        answers: userAnswers,
                        timer: document.getElementById('timer').textContent,
                        timestamp: new Date().getTime()
                    }));

                // Animate the check mark (as error)
                setTimeout(() => {
                    checkIcon.querySelector('.check-icon-path').style.animation = 'drawCheck 0.5s ease-in-out forwards';
                    
                    // Fade out after showing error
                    setTimeout(() => {
                        autoSaveMessage.classList.add('fade-out');
                        setTimeout(() => {
                            autoSaveMessage.style.display = 'none';
                        }, 1500);
                    }, 1000);
                }, 100);
            });
        }

        // Function to restore saved answers
        function restoreSavedAnswers() {
            const loadingMessage = document.getElementById('loadingMessage');
            const loadingText = document.getElementById('loadingText');
            const spinner = loadingMessage.querySelector('.loading-spinner');
            
            // Show loading message
            loadingMessage.style.display = 'flex';
            loadingText.textContent = 'Loading your saved answers...';
            loadingMessage.classList.remove('success-message', 'fade-out');

            // 1. Get server-side saved answers from PHP
            const serverAnswers = <?php 
                // Query fresh answers from database
                $savedAnswers = [];
                if (isset($attempt_id)) {
                    $sql = "SELECT question_id, answer FROM student_answers 
                            WHERE student_id = ? AND quiz_id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ii", $student['student_id'], $quiz_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    while ($row = $result->fetch_assoc()) {
                        $savedAnswers[$row['question_id']] = $row['answer'];
                    }
                }
                echo json_encode($savedAnswers); 
            ?>;
            
            // 2. Populate userAnswers with decoded values
            Object.entries(serverAnswers).forEach(([questionId, answer]) => {
                try {
                    // Try to parse JSON answers first
                    userAnswers[questionId] = typeof answer === 'string' ? 
                        JSON.parse(answer) : answer;
                } catch (e) {
                    // Fallback to raw value if not JSON
                    userAnswers[questionId] = answer;
                }
            });
            
            console.log("Restored answers from server:", userAnswers);
            
            // 3. Fallback to localStorage if no server answers
            if (Object.keys(userAnswers).length === 0) {
                const savedProgress = localStorage.getItem(`quizProgress_${<?php echo $quiz_id; ?>}`);
                if (savedProgress) {
                    const progress = JSON.parse(savedProgress);
                    Object.assign(userAnswers, progress.answers);
                    console.log("Restored answers from localStorage:", userAnswers);
                }
            }
            
            // Show success message
            spinner.style.display = 'none';
            loadingText.textContent = 'Answers loaded successfully!';
            loadingMessage.classList.add('success-message');
            
            // Apply answers to UI
            restoreAnswerSelections();
            
            // Fade out after 1.5 seconds
            setTimeout(() => {
                loadingMessage.classList.add('fade-out');
                
                // Remove element after fade out completes
                setTimeout(() => {
                    loadingMessage.style.display = 'none';
                    spinner.style.display = 'block'; // Reset spinner for next time
                }, 1500);
            }, 1500);

            // 4. Apply to UI after short delay
            setTimeout(restoreAnswerSelections, 300);
        }

        function restoreAnswerSelections() {
            console.log("Attempting to restore answers to UI...");
            
            // Wait for all questions to be fully rendered
            const questionCheckInterval = setInterval(() => {
                const renderedQuestions = document.querySelectorAll('.question').length;
                if (renderedQuestions === questions.length) {
                    clearInterval(questionCheckInterval);
                    console.log(`All ${questions.length} questions rendered`);
                    
                    // Add small delay to ensure all answer elements are ready
                    setTimeout(() => {
                        Object.entries(userAnswers).forEach(([questionId, answer]) => {
                            const questionDiv = document.querySelector(`div[data-question-id="${questionId}"]`);
                            if (!questionDiv) {
                                console.warn(`Question ${questionId} div not found`);
                                return;
                            }
                            
                            const questionType = questionDiv.dataset.questionType;
                            console.log(`Restoring ${questionType} answer for Q${questionId}:`, answer);
                            
                            switch(questionType) {
                                case 'multiple_choice':
                                case 'true_or_false':
                                    console.log('True/False answer to restore:', answer);
                                    console.log('Available buttons:', document.querySelectorAll(`[data-question-id="${questionId}"] .answer-button`));
                                    // Convert answer to string for comparison
                                    const answerStr = answer.toString();
                                    
                                    // First try exact match by answer ID
                                    const answerById = document.querySelector(
                                        `[data-question-id="${questionId}"] .answer-button[data-answer-id="${answerStr}"]`
                                    );
                                    
                                    if (answerById) {
                                        answerById.classList.add('selected');
                                        console.log(`Matched by exact ID for Q${questionId}`);
                                        break;
                                    }
                                    
                                    // If no ID match, try matching by text content
                                    document.querySelectorAll(`[data-question-id="${questionId}"] .answer-button`)
                                        .forEach(btn => {
                                            if (btn.textContent.trim().toLowerCase() === answerStr.toLowerCase()) {
                                                btn.classList.add('selected');
                                                console.log(`Matched by text for Q${questionId}:`, btn.textContent);
                                            }
                                        });
                                    break;
                                    
                                case 'identification':
                                case 'enumeration':
                                case 'fill_in_the_blanks':
                                    const input = questionDiv.querySelector('input');
                                    if (input) {
                                        input.value = answer;
                                        console.log(`Set input value for Q${questionId}`);
                                    }
                                    break;
                                    
                                case 'drag_and_drop':
                                    if (Array.isArray(answer) && answer.length > 0) {
                                        const answerId = answer[0]; // Get first answer ID
                                        const dragItem = document.querySelector(`[data-answer-id="${answerId}"]`);
                                        const dropZone = questionDiv.querySelector('.target-zone');
                                        
                                        if (dragItem && dropZone) {
                                            // Clear existing drop zone content
                                            dropZone.innerHTML = '<h4>Drop Item Here</h4>';
                                            // Move the item to drop zone
                                            dropZone.appendChild(dragItem);
                                            console.log(`Restored drag and drop for Q${questionId}`);
                                        }
                                    }
                                    break;
                                    
                                case 'matching_type':
                                    if (Array.isArray(answer)) {
                                        // Initialize matchingData for this question if not exists
                                        if (!window.matchingData) window.matchingData = {};
                                        if (!window.matchingData[questionId]) {
                                            window.matchingData[questionId] = {
                                                selectedLeft: null,
                                                selectedRight: null,
                                                matches: []
                                            };
                                        }
                                        
                                        // Restore matches
                                        window.matchingData[questionId].matches = answer.map(match => ({
                                            left: match.left,
                                            right: match.right,
                                            leftText: document.querySelector(`[data-question-id="${questionId}"] [data-answer-id="${match.left}"]`)?.textContent || '',
                                            rightText: document.querySelector(`[data-question-id="${questionId}"] [data-answer-id="${match.right}"]`)?.textContent || ''
                                        }));
                                        
                                        // Mark items as matched in UI
                                        answer.forEach(match => {
                                            const leftItem = document.querySelector(`[data-question-id="${questionId}"] [data-answer-id="${match.left}"]`);
                                            const rightItem = document.querySelector(`[data-question-id="${questionId}"] [data-answer-id="${match.right}"]`);
                                            if (leftItem) leftItem.classList.add('matched');
                                            if (rightItem) rightItem.classList.add('matched');
                                        });
                                        
                                        // Update matches display
                                        updateMatchesDisplay(questionId);
                                    }
                                    break;

                            }
                        });
                    }, 300); // Increased delay to ensure UI readiness
                }
            }, 100);
        }

        function renderAnswers(data, question, answersDiv, questionType) {
            // Clear any existing answers
            answersDiv.innerHTML = '';

            // Debug logging to understand the data and question type
            console.log('Question Type:', questionType);
            console.log('Answers Data:', data);

            // Render answers based on the specific question type
            switch(questionType) {
                case 'true_or_false':
                    // Create True and False buttons with proper IDs
                    const trueFalseOptions = [
                        { text: 'True', id: 'true_answer' },
                        { text: 'False', id: 'false_answer' }
                    ];
                    
                    trueFalseOptions.forEach((option) => {
                        const answerButton = document.createElement('button');
                        answerButton.innerText = option.text;
                        answerButton.className = 'answer-button';
                        answerButton.dataset.answerId = option.id;
                        answerButton.onclick = function() {
                            // Save the answer text (True/False)
                            saveAnswer(question.question_id, option.text);
                            
                            // Update UI
                            answersDiv.querySelectorAll('.answer-button').forEach(btn => {
                                btn.classList.remove('selected');
                            });
                            this.classList.add('selected');
                        };
                        answersDiv.appendChild(answerButton);
                    });
                    break;

                case 'identification':
                case 'enumeration':
                case 'fill_in_the_blanks':
                    const answerInput = document.createElement('input');
                    answerInput.type = 'text';
                    answerInput.placeholder = `Enter your ${questionType.replace(/_/g, ' ').toLowerCase()} answer`;
                    answerInput.className = 'form-control'; // Added for better styling
                    answerInput.oninput = function() {
                        saveAnswer(question.question_id, answerInput.value.trim());
                    };
                    answersDiv.appendChild(answerInput);
                    break;

                case 'multiple_choice':
                    if (!data || data.length === 0) {
                        console.error('No answers found for Multiple Choice question');
                        return;
                    }
                    
                    const labels = ['A', 'B', 'C', 'D'];
                    data.forEach((answer, i) => {
                        if (!answer) return; // Skip if answer is undefined
                        
                        const answerButton = document.createElement('button');
                        answerButton.innerText = `${labels[i]}. ${answer.answer_text || 'No text'}`;
                        answerButton.className = 'answer-button';
                        answerButton.dataset.answerId = answer.answer_id;
                        answerButton.onclick = function() {
                            saveAnswer(question.question_id, answer.answer_id);
                            answersDiv.querySelectorAll('.answer-button').forEach(btn => btn.classList.remove('selected'));
                            answerButton.classList.add('selected');
                        };
                        answersDiv.appendChild(answerButton);
                    });
                    break;

                case 'drag_and_drop':
                    if (!data || data.length === 0) {
                        console.error('No answers found for Drag and Drop question');
                        return;
                    }
                    // Create source and target containers
                    const sourceContainer = document.createElement('div');
                    const targetContainer = document.createElement('div');
                    sourceContainer.className = 'drop-zone source-zone';
                    targetContainer.className = 'drop-zone target-zone';
                    
                    sourceContainer.innerHTML = '<h4>Drag Items</h4>';
                    targetContainer.innerHTML = '<h4>Drop Item Here</h4>';
                    
                    // Shuffle the items to make it more challenging
                    const shuffledData = data.sort(() => Math.random() - 0.5);
                    
                    // Create draggable items for source container
                    shuffledData.forEach((item, index) => {
                        const dragItem = document.createElement('div');
                        dragItem.className = 'drag-item';
                        dragItem.draggable = true;
                        dragItem.dataset.answerId = item.answer_id;
                        dragItem.innerText = item.answer_text;
                        
                        // Drag event listeners
                        dragItem.addEventListener('dragstart', (e) => {
                            e.dataTransfer.setData('text/plain', e.target.dataset.answerId);
                            e.target.classList.add('dragging');
                        });
                        
                        dragItem.addEventListener('dragend', (e) => {
                            e.target.classList.remove('dragging');
                        });
                        
                        sourceContainer.appendChild(dragItem);
                    });
                    
                    // Drop zone event listeners
                    targetContainer.addEventListener('dragover', (e) => {
                        e.preventDefault(); // Allow dropping
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
                            // Remove any existing item from the target container
                            const existingItem = targetContainer.querySelector('.drag-item');
                            if (existingItem) {
                                sourceContainer.appendChild(existingItem);
                            }
                            
                            // Move the dropped item to the target container
                            targetContainer.appendChild(droppedItem);
                            
                            // Save the dropped item
                            saveAnswer(question.question_id, [answerId]);
                        }
                        
                        e.target.classList.remove('drag-over');
                    });
                    
                    // Add some extra styling
                    answersDiv.style.display = 'grid';
                    answersDiv.style.gridTemplateColumns = '1fr 1fr';
                    answersDiv.style.gap = '20px';
                    answersDiv.appendChild(sourceContainer);
                    answersDiv.appendChild(targetContainer);
                    break;

                case 'matching_type':
                    if (!question.left_items || !question.right_items) {
                        console.error('Missing left or right items for matching type question');
                        answersDiv.innerHTML = '<p>Error: Matching items not configured properly</p>';
                        return;
                    }

                    try {
                        // Parse the JSON strings for left and right items
                        const leftItems = JSON.parse(question.left_items);
                        const rightItems = JSON.parse(question.right_items);

                        // Create match container
                        const matchContainer = document.createElement('div');
                        matchContainer.className = 'match-container';
                        
                        // Left items column
                        const leftColumn = document.createElement('div');
                        leftColumn.className = 'left-items';
                        leftColumn.innerHTML = '<h4>Items to Match</h4>';
                        
                        // Add left items (numbered)
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

                        // Right items column
                        const rightColumn = document.createElement('div');
                        rightColumn.className = 'right-items';
                        rightColumn.innerHTML = '<h4>Match With</h4>';
                        
                        // Add right items (lettered)
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

                        // Add columns to container
                        matchContainer.appendChild(leftColumn);
                        matchContainer.appendChild(rightColumn);
                        answersDiv.appendChild(matchContainer);

                        // Pairs display area
                        const pairsDisplay = document.createElement('div');
                        pairsDisplay.className = 'pairs-display';
                        pairsDisplay.innerHTML = '<h4>Your Matches:</h4><div id="pairs-list-' + question.question_id + '"></div>';
                        answersDiv.appendChild(pairsDisplay);

                        // Clear button
                        const clearBtn = document.createElement('button');
                        clearBtn.textContent = 'Clear All Matches';
                        clearBtn.className = 'clear-matches-btn';
                        clearBtn.addEventListener('click', function() {
                            clearAllMatches(question.question_id);
                        });
                        answersDiv.appendChild(clearBtn);

                        // Initialize matching data for this question
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
                        default:
                            console.error('Unknown question type:', questionType);
                            answersDiv.innerHTML = `<p>Unable to render answers for question type: ${questionType}</p>`;
                    }
        }

        function selectMatchItem(item, questionId) {
            const side = item.dataset.side;
            const matchData = window.matchingData[questionId];
            
            // Remove previous selection from the same side
            document.querySelectorAll(`[data-side="${side}"]`).forEach(el => {
                if (el.closest('.question').querySelector('.question-text').textContent.includes(questionId) || 
                    el.getAttribute('data-question-id') === questionId.toString()) {
                    el.classList.remove('selected');
                }
            });

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
            
            // Save to answers
            saveAnswer(questionId, matchData.matches);
            
            // Clear selections
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
                
                // Clean up the text (remove numbering)
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
        }

        // Modify fetch to add error handling
        function renderQuestions() {
    const quizQuestionsDiv = document.getElementById('quiz-questions');
    
    questions.forEach((question, index) => {
        const questionDiv = document.createElement('div');
        questionDiv.className = 'question';
        questionDiv.dataset.questionId = question.question_id;
        questionDiv.dataset.questionType = question.question_type;

        const questionContent = document.createElement('div');
        questionContent.style.position = 'relative';

        // Add instructions if they exist (before the question)
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

        // Add TTS button to the question text
        const ttsButton = createTTSButton(question.question_id, question.question_text, index + 1);
        questionNumberText.appendChild(ttsButton);

        questionContent.appendChild(questionNumberText);
        questionDiv.appendChild(questionContent);
        
        // Answers container
        const answersDiv = document.createElement('div');
        answersDiv.className = 'answers';
        answersDiv.id = `answers-${question.question_id}`;
        
        fetch(`allZapped_getAnswer.php?question_id=${question.question_id}`)
            .then(response => response.json())
            .then(data => {
                renderAnswers(data, question, answersDiv, question.question_type);
            })
            .catch(error => {
                console.error('Error fetching answers:', error);
                answersDiv.innerHTML = `<p>Error loading answers: ${error.message}</p>`;
            });
        
        questionDiv.appendChild(answersDiv);
        quizQuestionsDiv.appendChild(questionDiv);
    });
}
        function saveAnswer(questionId, answer) {
            // Standardize answer format based on question type
            const questionDiv = document.querySelector(`[data-question-id="${questionId}"]`);
            if (!questionDiv) return;
            
            const questionType = questionDiv.dataset.questionType;
            
            switch(questionType) {
                case 'multiple_choice':
                    // Store the answer ID if available, otherwise the text
                    const selectedButton = questionDiv.querySelector('.answer-button.selected');
                    userAnswers[questionId] = selectedButton ? 
                        (selectedButton.dataset.answerId || selectedButton.textContent.trim()) : 
                        answer;
                    break;
                case 'true_or_false':
                    // Store either answer ID or text
                    userAnswers[questionId] = answer.toString().trim();
                    break;
                    
                case 'identification':
                case 'fill_in_the_blanks':
                case 'enumeration':
                    // Store as string
                    userAnswers[questionId] = answer.toString().trim();
                    break;
                    
                case 'matching_type':
                    // Ensure matches are in consistent format
                    if (Array.isArray(answer)) {
                        // Store both IDs and text content for matching type
                        userAnswers[questionId] = answer.map(match => ({
                            left: match.left,
                            right: match.right,
                            leftText: document.querySelector(`[data-question-id="${questionId}"] [data-answer-id="${match.left}"]`)?.textContent || '',
                            rightText: document.querySelector(`[data-question-id="${questionId}"] [data-answer-id="${match.right}"]`)?.textContent || ''
                        }));
                    }
                    break;
                    
                default:
                    userAnswers[questionId] = answer;
            }
        }

        function submitQuiz(isForced = false) {
            console.log("User answers before submission:", userAnswers);

            // Convert answers to consistent format
            const submissionAnswers = {};
            Object.entries(userAnswers).forEach(([questionId, answer]) => {
                const questionDiv = document.querySelector(`[data-question-id="${questionId}"]`);
                if (!questionDiv) return;
                
                const questionType = questionDiv.dataset.questionType;
                
                // Standardize answer format for submission
                if (questionType === 'matching_type' && Array.isArray(answer)) {
                    // Prepare matching type answers for submission
                    submissionAnswers[questionId] = answer.map(m => ({
                        left: m.leftText.replace(/^\d+\.\s*/, '').trim(),
                        right: m.rightText.replace(/^[A-Z]\.\s*/, '').trim()
                    }));
                } else {
                    submissionAnswers[questionId] = answer;
                }
            });

            console.log("Processed answers for submission:", submissionAnswers);

            isSubmitting = true;
            clearInterval(autoSaveInterval);
            clearSavedProgress();

            // Disable the beforeunload handler during submission
            window.onbeforeunload = null;
            
            // Ensure we always send at least an empty answers object
            const submissionData = {
                answers: Object.keys(userAnswers).length > 0 ? userAnswers : {},
                quiz_id: <?php echo $quiz_id; ?>,
                subject_id: <?php echo $subject_id; ?>
            };

            console.log("Submitting quiz with data:", submissionData); // Debug log
            
            fetch('allZapped_submitQuiz.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(submissionData)
            })
            .then(response => response.json())    
            .then(data => {
                if (data.success) {
                    // Store the result data in sessionStorage temporarily
                    sessionStorage.setItem('quizResult', JSON.stringify({
                        score: data.score,
                        total: data.total,
                        quiz_id: <?php echo $quiz_id; ?>,
                        wrong_answers: data.wrong_answers,
                        subject_id: <?php echo $subject_id; ?>
                    }));
                    
                    if (isForced) {
                        window.location.href = 'quiz_result.php';
                    } else {
                        window.location.href = 'process_quiz_result.php';
                    }
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

        function startTimer(duration) {
            let timer = duration, minutes, seconds;
            const timerInterval = setInterval(function () {
                minutes = parseInt(timer / 60, 10);
                seconds = parseInt(timer % 60, 10);

                minutes = minutes < 10 ? "0" + minutes : minutes;
                seconds = seconds < 10 ? "0" + seconds : seconds;

                document.getElementById('timer').textContent = `${minutes}:${seconds}`;

                if (--timer < 0) {
                    clearInterval(timerInterval);
                    submitQuiz(true);
                }
            }, 1000);
        }

        // Clear saved progress on successful submission
        function clearSavedProgress() {
            localStorage.removeItem(`quizProgress_${<?php echo $quiz_id; ?>}`);
            
            // Also clear server-side progress
            fetch('allZapped_clearProgress.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    attempt_id: <?php echo $attempt_id; ?>,
                    quiz_id: <?php echo $quiz_id; ?>
                })
            });
        }

        // Initialize on page load
        window.onload = function() {
            // Initialize matchingData object
            window.matchingData = {};            

           // Reset loading message state
            const loadingMessage = document.getElementById('loadingMessage');
            loadingMessage.style.display = 'flex';
            loadingMessage.classList.remove('fade-out', 'success-message');
            loadingMessage.querySelector('.loading-spinner').style.display = 'block';
            document.getElementById('loadingText').textContent = 'Loading your saved answers...';
            
            renderQuestions();
            
            // After questions are rendered, restore answers
            setTimeout(() => {
                restoreSavedAnswers();
                restoreAnswerSelections();
                
                // Start auto-save
                autoSaveInterval = setInterval(autoSaveProgress, AUTO_SAVE_INTERVAL);
            }, 500);
            
            // Start timer with remaining time from PHP
            startTimer(<?php echo $remaining_time; ?>);
        };
    </script>
    
    <?php endif; ?>
    <?php $conn->close(); ?>
    </body>
    </html>