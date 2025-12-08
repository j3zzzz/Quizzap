<?php
session_start();
if (strpos($_SESSION['account_number'], 'T') !== 0) {
    header("Location: login.php");
    exit();
}

// Enable error reporting
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

$loggedInUser = $_SESSION['account_number'];

// Query to fetch the teacher's profile pic
$sql = "SELECT profile_pic FROM teachers WHERE account_number = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $loggedInUser);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $profile_pic = $row['profile_pic'] ? $row['profile_pic'] : 'default-profile.jpg';
} else {
    $profile_pic = 'default-profile.jpg';
}

$response = ["success" => false, "message" => "", "subject_id" => ""];

// Check if the form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $subject_id = $_POST['subject_id'];
    $quiz_title = $_POST['title'];
    $timer = $_POST['timer'];
    $questions = $_POST['questions'];
    $answers = $_POST['correct_answer'];
    $quiz_type = $_POST['quiz_type']; // Set the quiz type

    $stmt = $conn->prepare("INSERT INTO quizzes (subject_id, title, timer, quiz_type) VALUES (?, ?, ?, ?)");
    if ($stmt === false) {
        die('Prepare failed: ' . htmlspecialchars($conn->error));
    }
    $stmt->bind_param("isis", $subject_id, $quiz_title, $timer, $quiz_type);
    
    if ($stmt->execute()) {
        $quiz_id = $stmt->insert_id;
        $stmt->close();

        $stmt_question = $conn->prepare("INSERT INTO questions (quiz_id, question_text) VALUES (?, ?)");
        if ($stmt_question === false) {
            die('Prepare failed: ' . htmlspecialchars($conn->error));
        }

        $stmt_answer = $conn->prepare("INSERT INTO answers (question_id, answer_text, is_correct) VALUES (?, ?, ?)");
        if ($stmt_answer === false) {
            die('Prepare failed: ' . htmlspecialchars($conn->error));
        }

        foreach ($questions as $index => $question) {
            $stmt_question->bind_param("is", $quiz_id, $question);
            if ($stmt_question->execute()) {
                $question_id = $conn->insert_id; // Changed from $stmt->insert_id to $conn->insert_id
                
                $correct_answers = explode(',', $answers[$index]);
                foreach ($correct_answers as $answer) {
                    $answer = trim($answer);
                    if (!empty($answer)) {
                        $is_correct = 1;
                        $stmt_answer->bind_param("isi", $question_id, $answer, $is_correct);
                        $stmt_answer->execute();
                    }
                }
            }
        }
        
        $stmt_question->close();
        $stmt_answer->close();

        $response["success"] = true;
        $response["message"] = "Quiz created successfully.";
        $response["subject_id"] = $subject_id;
    } else {
        $response["message"] = "Error: " . $stmt->error;
    }
    $stmt->close();
    $conn->close();
    
    // Clean output buffer and send JSON response
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
} else {
    $subject_id = $_GET['subject_id'];
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enumeration Quiz Creator</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Fredoka', sans-serif;
        }

        body, html {
            font-family: 'Fredoka', sans-serif;
            height: 100%;
            transition: background-color 0.3s, color 0.3s;
            overflow-x: hidden;
            background-color: #ffffff;
        }

        body.dark-mode {
            background-color: #1a1a1a;
            color: #e0e0e0;
        }

        /* Header - Responsive */
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background-color: white;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            flex-wrap: wrap;
            gap: 1rem;
        }

        body.dark-mode header {
            background-color: #2d2d2d;
        }

        header .logo {
            display: flex;
            align-items: center;
        }

        header .logo img {
            height: clamp(40px, 8vw, 60px);
            width: auto;
        }

        .profile {
            position: relative;
            cursor: pointer;
        }

        .profile-pic {
            width: clamp(40px, 8vw, 50px);
            height: clamp(40px, 8vw, 50px);
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #f8b500;
        }

        /* Main Content */
        .main-container {
            padding: clamp(1rem, 3vw, 2rem);
            max-width: 1400px;
            margin: 0 auto;
        }

        h1 {
            color: #f8b500;
            text-align: center;
            font-size: clamp(1.8rem, 5vw, 2.5rem);
            margin-bottom: clamp(1rem, 3vw, 2rem);
            font-weight: 600;
        }

        /* Quiz Container - Responsive */
        .create-q-cont {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto clamp(2rem, 5vw, 3rem) auto;
            border: 2px solid #f8b500;
            border-radius: clamp(10px, 2vw, 15px);
            padding: clamp(1rem, 3vw, 2rem);
            background-color: white;
            box-shadow: 5px 6px 0 0 #BC8900;
        }

        body.dark-mode .create-q-cont {
            background-color: #2d2d2d;
            color: #e0e0e0;
        }

        /* Form Elements */
        label {
            display: block;
            color: #333;
            font-size: clamp(0.9rem, 1.5vw, 1rem);
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        body.dark-mode label {
            color: #e0e0e0;
        }

        input[type="text"],
        input[type="number"] {
            width: 100%;
            border-radius: 8px;
            padding: clamp(0.5rem, 1.5vw, 0.75rem);
            border: 2px solid #B9B6B6;
            font-size: clamp(0.9rem, 1.5vw, 1rem);
            background-color: white;
            color: #333;
            transition: border-color 0.3s;
        }

        body.dark-mode input[type="text"],
        body.dark-mode input[type="number"] {
            background-color: #3d3d3d;
            color: #e0e0e0;
            border-color: #555;
        }

        input[type="text"]:focus,
        input[type="number"]:focus {
            border-color: #f8b500;
            outline: none;
        }

        /* Quiz Header - Responsive */
        .quiz-header {
            display: grid;
            grid-template-columns: 1fr;
            gap: clamp(1rem, 2vw, 1.5rem);
            margin-bottom: clamp(1.5rem, 3vw, 2rem);
        }

        @media (min-width: 768px) {
            .quiz-header {
                grid-template-columns: 1fr 1fr;
                align-items: end;
            }
        }

        .quiz-title-group,
        .quiz-timer-group {
            width: 100%;
        }

        /* Questions Container */
        .questions-container {
            margin-bottom: clamp(1.5rem, 3vw, 2rem);
            max-height: 60vh;
            overflow-y: auto;
            padding-right: 5px;
        }

        /* Scrollbar styling */
        .questions-container::-webkit-scrollbar {
            width: 8px;
        }

        .questions-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        .questions-container::-webkit-scrollbar-thumb {
            background: #f8b500;
            border-radius: 4px;
        }

        body.dark-mode .questions-container::-webkit-scrollbar-track {
            background: #3d3d3d;
        }

        /* Question Container - Responsive */
        .question-container {
            background-color: #fff5e1;
            padding: clamp(1rem, 2vw, 1.5rem);
            margin-bottom: clamp(1rem, 2vw, 1.5rem);
            border-radius: 10px;
            border: 2px solid #f8b500;
            position: relative;
        }

        body.dark-mode .question-container {
            background-color: #3d3d3d;
        }

        .question-number {
            font-size: clamp(1.1rem, 2vw, 1.3rem);
            font-weight: 600;
            margin-bottom: clamp(0.75rem, 1.5vw, 1rem);
            color: #333;
        }

        body.dark-mode .question-number {
            color: #e0e0e0;
        }

        /* Buttons - Responsive */
        .btn {
            padding: clamp(0.6rem, 1.5vw, 0.8rem) clamp(1rem, 2vw, 1.5rem);
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: clamp(0.85rem, 1.5vw, 0.95rem);
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            min-height: 44px;
            min-width: 44px;
            text-decoration: none;
        }

        .btn i {
            font-size: clamp(0.9rem, 1.5vw, 1rem);
        }

        .btn-back {
            background-color: white;
            color: #B9B6B6;
            border: 2px solid #B9B6B6;
        }

        body.dark-mode .btn-back {
            background-color: #3d3d3d;
            color: #e0e0e0;
            border-color: #555;
        }

        .btn-back:hover {
            background-color: #f0f0f0;
        }

        body.dark-mode .btn-back:hover {
            background-color: #444;
        }

        .btn-saveQuiz {
            background-color: #f8b500;
            color: white;
            box-shadow: 0 5px 0 0 #BC8900;
        }

        .btn-saveQuiz:hover {
            background-color: #e5941f;
            transform: translateY(-2px);
            box-shadow: 0 7px 0 0 #BC8900;
        }

        .btn-saveQuiz:active {
            transform: translateY(0);
            box-shadow: 0 3px 0 0 #BC8900;
        }

        .btn-settings {
            background-color: #f8b500;
            color: white;
            box-shadow: 0 5px 0 0 #BC8900;
        }

        .btn-settings:hover {
            background-color: #e5941f;
            transform: translateY(-2px);
            box-shadow: 0 7px 0 0 #BC8900;
        }

        .btn-removeQuestion {
            background-color: #f44336;
            color: white;
            margin-top: 1rem;
            width: 100%;
        }

        .btn-removeQuestion:hover {
            background-color: #d32f2f;
        }

        .single-question .btn-removeQuestion {
            display: none;
        }

        /* Add Question Button */
        .add-question-btn {
            width: 100%;
            max-width: 200px;
            background-color: white;
            color: #f8b500;
            border: 2px solid #f8b500;
            margin: 0 auto clamp(1.5rem, 3vw, 2rem) auto;
            display: block;
        }

        body.dark-mode .add-question-btn {
            background-color: #3d3d3d;
            color: #f8b500;
        }

        .add-question-btn:hover {
            background-color: #f8b500;
            color: white;
        }

        /* Actions Bar - Responsive */
        .actions-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: clamp(0.75rem, 2vw, 1rem);
            margin-top: clamp(1.5rem, 3vw, 2rem);
            padding-top: clamp(1rem, 2vw, 1.5rem);
            border-top: 1px solid #e0e0e0;
        }

        body.dark-mode .actions-bar {
            border-top-color: #555;
        }

        .left-actions,
        .right-actions {
            display: flex;
            gap: clamp(0.5rem, 1.5vw, 0.75rem);
            flex-wrap: wrap;
        }

        .right-actions {
            justify-content: flex-end;
        }

        /* Modal Styles - Responsive */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            overflow-y: auto;
        }

        .modal-content {
            width: 90%;
            max-width: 700px;
            padding: clamp(1rem, 3vw, 2rem);
            border-radius: 12px;
            background-color: #f9f9f9;
            margin: 5% auto;
            position: relative;
        }

        body.dark-mode .modal-content {
            background-color: #2d2d2d;
            color: #e0e0e0;
        }

        .close-modal {
            position: absolute;
            top: 1rem;
            right: 1.5rem;
            cursor: pointer;
            font-weight: bold;
            font-size: clamp(1.2rem, 2vw, 1.5rem);
            color: #666;
            background: none;
            border: none;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        body.dark-mode .close-modal {
            color: #e0e0e0;
        }

        .modal-title {
            color: #f8b500;
            text-align: center;
            margin-bottom: clamp(1.5rem, 3vw, 2rem);
            font-size: clamp(1.3rem, 3vw, 1.8rem);
        }

        /* Settings Container */
        .settings-container {
            display: flex;
            flex-direction: column;
            gap: clamp(1rem, 2vw, 1.5rem);
        }

        .setting-group {
            background: #f3f3f3;
            border-radius: 10px;
            padding: clamp(1rem, 2vw, 1.5rem);
            border-left: 4px solid #f8b500;
        }

        body.dark-mode .setting-group {
            background: #3d3d3d;
        }

        .setting-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .setting-header i {
            color: #f8b500;
            font-size: clamp(1rem, 1.5vw, 1.2rem);
        }

        .setting-header h3 {
            color: #333;
            font-size: clamp(1rem, 1.5vw, 1.2rem);
            margin: 0;
        }

        body.dark-mode .setting-header h3 {
            color: #e0e0e0;
        }

        .form-group {
            margin-bottom: clamp(0.75rem, 1.5vw, 1rem);
        }

        .input-group {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .input-group .form-input {
            flex: 1;
            min-width: 200px;
        }

        .form-input {
            width: 100%;
            padding: clamp(0.5rem, 1.5vw, 0.75rem);
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: clamp(0.9rem, 1.5vw, 1rem);
            background-color: white;
            color: #333;
        }

        body.dark-mode .form-input {
            background-color: #3d3d3d;
            color: #e0e0e0;
            border-color: #555;
        }

        .hint {
            display: block;
            color: #888;
            font-size: clamp(0.75rem, 1.2vw, 0.85rem);
            margin-top: 0.5rem;
            font-style: italic;
        }

        body.dark-mode .hint {
            color: #b0b0b0;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
            margin-top: clamp(1.5rem, 3vw, 2rem);
            padding-top: clamp(1rem, 2vw, 1.5rem);
            border-top: 1px solid #eee;
            flex-wrap: wrap;
        }

        body.dark-mode .modal-footer {
            border-top-color: #555;
        }

        .save-btn {
            background-color: #4CAF50;
            color: white;
        }

        .save-btn:hover {
            background-color: #3e8e41;
        }

        .cancel-btn {
            background-color: #f44336;
            color: white;
        }

        .cancel-btn:hover {
            background-color: #d32f2f;
        }

        .secondary-btn {
            background-color: #f8b500;
            color: white;
            padding: 0.5rem 1rem;
            font-size: clamp(0.8rem, 1.2vw, 0.9rem);
        }

        .secondary-btn:hover {
            background-color: #e5941f;
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .quiz-header {
                grid-template-columns: 1fr;
            }
            
            .actions-bar {
                flex-direction: column;
                align-items: stretch;
            }
            
            .left-actions,
            .right-actions {
                width: 100%;
                justify-content: center;
            }
            
            .right-actions {
                order: -1;
                margin-bottom: 1rem;
            }
        }

        @media (max-width: 576px) {
            .create-q-cont {
                padding: 1rem;
            }
            
            .question-container {
                padding: 0.75rem;
            }
            
            .modal-content {
                width: 95%;
                padding: 1rem;
                margin: 2% auto;
            }
            
            .input-group {
                flex-direction: column;
            }
            
            .input-group .form-input {
                min-width: unset;
            }
            
            .modal-footer {
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            header {
                padding: 0.75rem;
            }
            
            h1 {
                font-size: 1.5rem;
            }
            
            .btn {
                padding: 0.75rem 1rem;
                font-size: 0.9rem;
            }
        }

        @media (max-width: 375px) {
            .main-container {
                padding: 0.75rem;
            }
            
            h1 {
                font-size: 1.3rem;
            }
            
            .btn {
                padding: 0.6rem 0.8rem;
                font-size: 0.85rem;
            }
            
            .modal-content {
                width: 98%;
                padding: 0.75rem;
            }
        }

        /* Utility Classes */
        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        /* Focus Styles for Accessibility */
        button:focus-visible,
        input:focus-visible,
        a:focus-visible {
            outline: 2px solid #f8b500;
            outline-offset: 2px;
        }

        /* Smooth Transitions */
        * {
            transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
        }
    </style>
</head>
<body>
    <header>
        <div class="logo">
            <img src="img/logo1.png" alt="QuizZap Logo">
        </div>
        <div class="profile" onclick="profileDropdown()">
            <img src="uploads/profiles/<?php echo htmlspecialchars($profile_pic); ?>" alt="Profile Picture" class="profile-pic" onerror="this.src='uploads/profiles/default-profile.jpg'">
        </div>
    </header>

    <div class="main-container">
        <h1>Create Enumeration Quiz</h1>
        
        <div class="create-q-cont">
            <form id="quiz-form" method="post" action="">
                <input type="hidden" name="subject_id" value="<?php echo htmlspecialchars($subject_id); ?>">
                <input type="hidden" name="quiz_type" value="Enumeration">
                <input type="hidden" name="end_date" value="">
                <input type="hidden" name="start_date" value="">

                <div class="quiz-header">
                    <div class="quiz-title-group">
                        <label for="title">Quiz Title:</label>
                        <input type="text" id="title" name="title" required placeholder="Enter quiz title">
                    </div>

                    <div class="quiz-timer-group">
                        <label for="timer">Timer (minutes):</label>
                        <input type="number" id="timer" name="timer" min="1" required placeholder="Enter time in minutes">
                    </div>
                </div>

                <div class="questions-container" id="questionsContainer"></div>

                <button type="button" class="btn add-question-btn" id="addQuestionBtn">
                    <i class="fas fa-plus"></i> Add Question
                </button>

                <div class="actions-bar">
                    <div class="left-actions">
                        <button type="button" class="btn btn-back" onclick="goBack()">
                            <i class="fas fa-arrow-left"></i> Back
                        </button>
                    </div>
                    <div class="right-actions">
                        <button type="button" class="btn btn-settings" onclick="openQuizSettings()">
                            <i class="fas fa-cog"></i> Quiz Settings
                        </button>
                        <button type="submit" class="btn btn-saveQuiz">
                            <i class="fas fa-save"></i> Save Quiz
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Quiz Settings Modal -->
    <div id="quiz-settings-modal" class="modal" style="display:none;">
        <div class="modal-content">
            <button class="close-modal" onclick="closeModal()">&times;</button>
            <h2 class="modal-title">Quiz Availability Settings</h2>
            
            <div class="settings-container">
                <div class="setting-group">
                    <div class="setting-header">
                        <i class="fas fa-calendar-alt"></i>
                        <h3>Time Settings</h3>
                    </div>
                    
                    <div class="form-group">
                        <label for="start-date">
                            <i class="fas fa-play-circle" style="color: #4CAF50; margin-right: 5px;"></i>
                            Start Date:
                        </label>
                        <div class="input-group">
                            <input type="datetime-local" id="start-date" name="start_date" class="form-input" min="">
                            <button type="button" onclick="setStartDateToday()" class="btn secondary-btn">
                                <i class="fas fa-clock"></i> Now
                            </button>
                        </div>
                        <small class="hint">Leave empty to make available immediately</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="end-date">
                            <i class="fas fa-stop-circle" style="color: #f44336; margin-right: 5px;"></i>
                            End Date:
                        </label>
                        <input type="datetime-local" id="end-date" name="end_date" class="form-input" min="" required>
                        <small class="hint">Students won't be able to take the quiz after this date</small>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" onclick="closeModal()" class="btn cancel-btn">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="button" onclick="saveQuizSettings()" class="btn save-btn">
                        <i class="fas fa-save"></i> Save Settings
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Dark Mode Functionality - Auto apply based on localStorage
        const isDarkMode = localStorage.getItem('darkMode') === 'true';

        // Apply dark mode on page load if enabled
        if (isDarkMode) {
            document.body.classList.add('dark-mode');
        }

        let currentQuestions = 0;
        const maxQuestions = 20;

        function addQuestion() {
            if (currentQuestions >= maxQuestions) {
                alert('Maximum number of questions reached!');
                return;
            }

            const container = document.getElementById('questionsContainer');
            const questionDiv = document.createElement('div');
            questionDiv.className = 'question-container';
            if (currentQuestions === 0) {
                questionDiv.classList.add('single-question');
            }
            const questionNumber = currentQuestions + 1;

            questionDiv.innerHTML = `
                <div class="question-number">Question ${questionNumber}</div>
                <div class="form-group">
                    <label>Instructions (optional):</label>
                    <input type="text" name="instructions[]" placeholder="Additional instructions for this question">
                </div>
                <div class="form-group">
                    <label>Question:</label>
                    <input type="text" name="questions[]" required placeholder="Enter question text">
                </div>
                <div class="form-group">
                    <label>Correct Answers (separated by commas):</label>
                    <input type="text" name="correct_answer[]" required placeholder="Enter correct answers separated by commas">
                </div>
                <button type="button" class="btn btn-removeQuestion" onclick="removeQuestion(this)">
                    <i class="fas fa-trash"></i> Remove Question
                </button>
            `;

            container.appendChild(questionDiv);
            currentQuestions++;
            
            // Update single-question class for all questions
            updateQuestionRemoveButtons();
            
            // Scroll to the new question
            questionDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        function removeQuestion(button) {
            if (document.querySelectorAll('.question-container').length > 1) {
                const question = button.closest('.question-container');
                question.remove();
                currentQuestions--;
                updateQuestionNumbers();
                updateQuestionRemoveButtons();
            }
        }

        function updateQuestionRemoveButtons() {
            const questions = document.querySelectorAll('.question-container');
            questions.forEach(question => {
                if (questions.length === 1) {
                    question.classList.add('single-question');
                } else {
                    question.classList.remove('single-question');
                }
            });
        }

        function updateQuestionNumbers() {
            const questions = document.querySelectorAll('.question-container');
            questions.forEach((question, index) => {
                const numberDiv = question.querySelector('.question-number');
                numberDiv.textContent = `Question ${index + 1}`;
            });
            currentQuestions = questions.length;
        }

        function goBack() {
            window.history.back();
        }

        function profileDropdown() {
            // Add your profile dropdown functionality here
        }

        // Quiz Settings Modal Functions
        function openQuizSettings() {
            const modal = document.getElementById('quiz-settings-modal');
            modal.style.display = 'block';
            
            const now = new Date();
            const year = now.getFullYear();
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const day = String(now.getDate()).padStart(2, '0');
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            
            const minDateTime = `${year}-${month}-${day}T${hours}:${minutes}`;
            
            document.getElementById('start-date').min = minDateTime;
            document.getElementById('end-date').min = minDateTime;
            
            const startDateInput = document.getElementById('start-date');
            const endDateInput = document.getElementById('end-date');
            
            startDateInput.addEventListener('change', function() {
                endDateInput.min = this.value;
            });
        }

        function closeModal() {
            document.getElementById('quiz-settings-modal').style.display = 'none';
        }

        function setStartDateToday() {
            const now = new Date();
            const year = now.getFullYear();
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const day = String(now.getDate()).padStart(2, '0');
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            
            document.getElementById('start-date').value = `${year}-${month}-${day}T${hours}:${minutes}`;
            
            const endDateInput = document.getElementById('end-date');
            if (!endDateInput.value || new Date(endDateInput.value) <= now) {
                now.setHours(now.getHours() + 1);
                const endYear = now.getFullYear();
                const endMonth = String(now.getMonth() + 1).padStart(2, '0');
                const endDay = String(now.getDate()).padStart(2, '0');
                const endHours = String(now.getHours()).padStart(2, '0');
                const endMinutes = String(now.getMinutes()).padStart(2, '0');
                
                endDateInput.value = `${endYear}-${endMonth}-${endDay}T${endHours}:${endMinutes}`;
                endDateInput.min = document.getElementById('start-date').value;
            }
        }

        function saveQuizSettings() {
            const startDate = document.getElementById('start-date').value;
            const endDate = document.getElementById('end-date').value;

            if (startDate && new Date(endDate) <= new Date(startDate)) {
                alert('End date must be after start date');
                return;
            }
            
            const form = document.getElementById('quiz-form');
            
            ['start_date', 'end_date'].forEach(name => {
                const existing = form.querySelector(`input[name="${name}"]`);
                if (existing) existing.remove();
            });
            
            if (startDate) {
                const startInput = document.createElement('input');
                startInput.type = 'hidden';
                startInput.name = 'start_date';
                startInput.value = startDate;
                form.appendChild(startInput);
            }
            
            const endInput = document.createElement('input');
            endInput.type = 'hidden';
            endInput.name = 'end_date';
            endInput.value = endDate;
            form.appendChild(endInput);
            
            document.querySelector('input[name="start_date"]').value = startDate;
            document.querySelector('input[name="end_date"]').value = endDate;
            
            closeModal();
        }

        document.addEventListener('DOMContentLoaded', function() {
            const addQuestionBtn = document.getElementById('addQuestionBtn');
            addQuestionBtn.addEventListener('click', addQuestion);

            // Close modal when clicking outside
            window.addEventListener('click', function(event) {
                const modal = document.getElementById('quiz-settings-modal');
                if (event.target === modal) {
                    closeModal();
                }
            });

            // Add first question automatically
            addQuestion();
        });

        document.getElementById('quiz-form').addEventListener('submit', function(e) {
            e.preventDefault();

            if (!document.querySelector('input[name="end_date"]').value) {
                alert('Please set quiz availability settings before submitting');
                openQuizSettings();
                return;
            }
            
            const formData = new FormData(this);
            const allQuestionsFilled = Array.from(document.querySelectorAll('.question-container')).every(questionDiv => {
                const questionInput = questionDiv.querySelector('input[name="questions[]"]');
                const answerInput = questionDiv.querySelector('input[name="correct_answer[]"]');
                
                if (!questionInput.value.trim()) return false;
                if (!answerInput.value.trim()) return false;
                
                return true;
            });

            if (!allQuestionsFilled) {
                alert('Please fill all questions and answers before submitting.');
                return;
            }
            
            fetch('t_save_quiz.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success alert
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: data.message,
                        confirmButtonColor: '#f8b500',
                        confirmButtonText: 'OK'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Redirect after confirmation
                            window.location.href = `t_quizDash.php?subject_id=${data.subject_id}`;
                        }
                    });
                } else {
                    // Show error alert
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Failed to create quiz',
                        confirmButtonColor: '#f44336'
                    });
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to save quiz: ' + error.message,
                    confirmButtonColor: '#f44336'
                });
                console.error('Fetch error:', error);
            });
        });
    </script>
</body>
</html>