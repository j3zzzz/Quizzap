<?php
session_start();
if (strpos($_SESSION['account_number'], 'T') !== 0) {
    header("Location: login.php");
    exit();
}

// Start output buffering to prevent accidental output
ob_start();

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

//query to fetch the teacher's profile pic
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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $subject_id = $_POST['subject_id'];
    $quiz_title = $_POST['title'];
    $timer = $_POST['timer'];
    $questions = $_POST['questions'];
    $correct = $_POST['correct'];
    $quiz_type = $_POST['quiz_type']; // Set the quiz type
    
    $stmt = $conn->prepare("INSERT INTO quizzes (subject_id, title, timer, quiz_type) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isis", $subject_id, $quiz_title, $timer, $quiz_type);
    
    if ($stmt->execute()) {
        $quiz_id = $stmt->insert_id;
        $stmt->close();

        foreach ($questions as $index => $question) {
            $stmt = $conn->prepare("INSERT INTO questions (quiz_id, question) VALUES (?, ?)");
            $stmt->bind_param("is", $quiz_id, $question);
            if ($stmt->execute()) {
                $question_id = $stmt->insert_id;
                // For True/False, we always insert both answers
                $answers = ['True', 'False'];
                foreach ($answers as $answer_index => $answer) {
                    $is_correct = ($correct[$index] == $answer) ? 1 : 0;
                    $stmt = $conn->prepare("INSERT INTO answers (question_id, answer, is_correct) VALUES (?, ?, ?)");
                    $stmt->bind_param("isi", $question_id, $answer, $is_correct);
                    $stmt->execute();
                }
            }
        }
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
    <title>Create True or False Quiz</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
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
            background-color: #2d2d2d;
        }

        header .logo {
            font-size: 24px;
            font-weight: bold;
            margin-left: 30px;
            margin-top: 3px;
        }

        h1{
            position: relative;
            font-family: Fredoka;
            color: #f8b500;
            text-align: center;
            font-size: 50px;
        }

        .create-q-cont {
            width: 70%;
            margin: auto;
            margin-top: 3%;
            margin-bottom: 3%;
            border: 2px solid #f8b500;
            border-radius: 15px;
            padding: 40px;
            background-color: white;
            box-shadow: 5px 6px 0 0 #BC8900;
        }

        body.dark-mode .create-q-cont {
            background-color: #2d2d2d;
            color: #e0e0e0;
        }

        label{
            color: #555;
            font-family: Fredoka;
            font-size: 14px;
            font-weight: 500;
        }

        body.dark-mode label {
            color: #e0e0e0;
        }

        label[for=timer]{
            font-size: 22px;
            margin-left: 15%;
            font-weight: 500;
            color: black;
            margin-right: 8px;
        }

        input [type=timer] {
            width: 50%;
        }

        label[for=title]{
            font-size: 22px;
            margin-left: 2%;
            font-weight: 500;
            color: black;
        }

        #title{
            width: 35%;
        }

        input[type=text]{
            width: 100%;
            border-radius: 10px;
            padding: 10px;
            border: 3px solid #B9B6B6;
            text-transform: capitalize;
            font-family: Fredoka;
            font-size: 17px;
            background-color: white;
            color: black;
        }

        body.dark-mode input[type=text] {
            background-color: #3d3d3d;
            color: #e0e0e0;
            border-color: #555;
        }

        input[type=number]{
            width: 30%;
            border-radius: 10px;
            padding: 10px;
            border: 3px solid #B9B6B6;
            margin-right: 2%;
            font-family: Fredoka;
            background-color: white;
            color: black;
        }

        body.dark-mode input[type=number] {
            background-color: #3d3d3d;
            color: #e0e0e0;
            border-color: #555;
        }

        .question {
            margin-bottom: 20px;
            padding: 40px;
            background-color: #fff5e1;
            border: 2px solid #DCDCDC;
            border-radius: 10px;
            display: none;
            margin-left: 3%;
            margin-right: 3%;
        }

        body.dark-mode .question {
            background-color: #3d3d3d;
        }

        .quiz-form {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        body.dark-mode .quiz-form {
            background-color: #2d2d2d;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .modal-form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
            font-size: 14px;
        }

        .form-input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            transition: border 0.3s;
            background-color: white;
            color: black;
        }

        .question-container {
            background-color: #fff5e1;
            padding: 30px;
            margin-bottom: 15px;
            border-radius: 10px;
            border: 2px solid #f8b500;
        }

        body.dark-mode .question-container {
            background-color: #3d3d3d;
        }

        .answer-container {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        .btn-saveQuiz {
            background-color: #f8b500;
            color: white;
            font-family: Fredoka;
            font-weight: 500;
            box-shadow: 0 5px 0 0 #BC8900;
        }
        .btn-removeQuestion {
            margin-top: 2%;
            background-color: #f44336;
            color: white;
            font-family: Fredoka;
            font-weight: 500;
        }
        .btn-back {
            background-color: white;
            color: #B9B6B6;
            border: 2px solid #B9B6B6;
            font-family: Fredoka;
            font-weight: 500;
        }

        body.dark-mode .btn-back {
            background-color: #3d3d3d;
            color: #e0e0e0;
            border-color: #555;
        }

        body.dark-mode .form-group label {
            color: #e0e0e0;
        }

        body.dark-mode .form-input {
            background-color: #3d3d3d;
            color: #e0e0e0;
            border-color: #555;
        }

        .form-input:focus {
            border-color: #f8b500;
            outline: none;
        }

        .input-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .input-group .form-input {
            flex: 1;
        }

        .actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            justify-content: space-between;
        }
        .question-number {
            font-family: Fredoka;
            font-size: 25px;
            margin-bottom: 10px;
            font-weight: 500;
        }
        .answer-btn {
            width: 50%;
            font-family: Fredoka;
            font-weight: 500;
            background-color: white;
            color: #f8b500;
            border: 2px solid #f8b500;
            border-radius: 5px;
            padding: 8px 20px;
            cursor: pointer;
            margin-top: 10px;
            font-size: 16px;
        }

        body.dark-mode .answer-btn {
            background-color: #3d3d3d;
            color: #f8b500;
        }

        .answer-btn:hover {
            background-color: #f8b500;
            color: white;
            cursor: pointer;
        }

        body.dark-mode .answer-btn:hover {
            background-color: #f8b500;
            color: white;
        }

        .answer-btn.selected {
            background-color: #f8b500;
            color: white;
        }

        .number-btn {
            width: 40px;
            height: 40px;
            border: 2px solid #f8b500;
            border-radius: 10px;
            background-color: white;
            color: #f8b500;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: Fredoka;
            font-weight: 500;
        }

        body.dark-mode .number-btn {
            background-color: #3d3d3d;
            color: #f8b500;
        }

        .number-btn:hover {
            background-color: #f8b500;
            color: white;
        }

        .error-message {
            color: red;
            margin-top: 5px;
            font-size: 14px;
        }

        .number-buttons {
            display: flex;
            margin-top: 20px;
            align-items: center;
        }
        
        .add-question-btn {
            width: 120px;
            height: 40px;
            padding: 10px;
            border: 2px solid #f8b500;
            border-radius: 5px;
            background-color: white;
            color: #f8b500;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: Fredoka;
            font-weight: 500;
        }

        body.dark-mode .add-question-btn {
            background-color: #3d3d3d;
            color: #f8b500;
        }
        
        .add-question-btn:hover {
            background-color: #f8b500;
            color: white;
        }
        
        /* Hide remove buttons when there's only one */
        .single-question .btn-removeQuestion {
            display: none;
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
          background: #f8b500; 
          border-radius: 10px;
        }

        /* Handle on hover */
        ::-webkit-scrollbar-thumb:hover {
          background: #f8b500; 
        }

        .profile {
            position: relative;
            cursor: pointer;
        }

        .profile-pic {
            border: 2px solid #f8b500;
        }

        /* Quiz Settings Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            width: 700px;
            max-width: 90%;
            padding: 30px;
            border-radius: 12px;
            background-color: #f9f9f9;
            margin: 5% auto auto auto;
            font-family: Fredoka;
        }

        body.dark-mode .modal-content {
            background-color: #2d2d2d;
            color: #e0e0e0;
        }

        .close-modal {
            cursor: pointer;
            font-weight: bold;
            float: right;
            font-size: 24px;
        }

        .settings-container {
            width: 100%;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
            
        .setting-group {
            background: #f3f3f3;
            border-radius: 10px;
            padding: 20px;
            border-left: 4px solid #f8b500;
            width: 100%;
        }

        body.dark-mode .setting-group {
            background: #3d3d3d;
        }

        .setting-header {
            display: flex;
            margin-bottom: 5px;
            font-family: Fredoka;
        }

        .setting-header h3 {
            color: #333;
            font-size: 18px;
            margin: 0;
        }

        body.dark-mode .setting-header h3 {
            color: #e0e0e0;
        }

        .hint {
            display: block;
            color: #888;
            font-size: 11px;
            margin-top: 5px;
            font-style: italic;
            font-family: Fredoka;
        }

        body.dark-mode .hint {
            color: #aaa;
        }

        .modal-footer {
            width: 100%;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        body.dark-mode .modal-footer {
            border-top-color: #555;
        }

        .save-btn {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: background-color 0.3s;
            font-family: Fredoka;
            font-size: 16px;
        }

        .save-btn:hover {
            background-color: #3e8e41;
        }

        .cancel-btn {
            background-color: #f44336;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: background-color 0.3s;
            font-family: Fredoka;
            font-size: 16px;
        }

        .cancel-btn:hover {
            background-color: #d32f2f;
        }

        .secondary-btn {
            background-color: #f8b500;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 12px;
            transition: background-color 0.3s;
        }

        .secondary-btn:hover {
            background-color: #e6a700;
        }

        .btn-settings {
            background-color: #f8b500;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-family: Fredoka;
            margin-right: 10px;
            box-shadow: 0 5px 0 0 #BC8900;
        }

        .quiz-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .quiz-header input {
            flex: 1;
            margin-right: 20px;
            font-size: 18px;
        }

        .quiz-timer {
            float: right;
        }
    </style>
</head>
<body>

    <header>
        <div class="logo"><img src="img/logo1.png" width="200px" height="80px"></div>
        <div class="actions">
            <div class="profile" onclick="profileDropdown()">
                <img src="uploads/profiles/<?php echo htmlspecialchars($profile_pic); ?>" alt="Profile Picture" class="profile-pic" onerror="this.src='uploads/profiles/default-profile.jpg'" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
            </div>
        </div>
    </header>

    <h1>Create True or False Quiz</h1>

    <div class="create-q-cont">
        <form id="quiz-form" method="POST" action="t_save_quiz.php">
            <input type="hidden" name="subject_id" value="<?php echo htmlspecialchars($subject_id); ?>">
            <input type="hidden" name="quiz_type" value="True or False">
            <input type="hidden" name="end_date" value="">
            <input type="hidden" name="start_date" value="">

            <div class="quiz_header">
                <label for="title">Quiz Title:</label>
                <input type="text" id="title" name="title" required>

                <div class="quiz-timer">
                    <label for="timer">Timer (minutes):</label>
                    <input type="number" id="timer" name="timer" min="1" required>
                </div>
            </div>

            <br>

            <div id="questionsContainer"></div>

            <div class="number-buttons" id="numberButtons">
                <button type="button" class="add-question-btn" id="addQuestionBtn">
                    <i class="fas fa-plus"></i>  Add Question
                </button>
            </div>

            <div class="actions">
                <button type="button" class="btn btn-back" onclick="goBack()">
                    <i class="fas fa-arrow-left"></i> Back
                </button>
                <div>
                    <button type="button" class="btn-settings" onclick="openQuizSettings()">
                        <i class="fas fa-cog"></i> Quiz Settings
                    </button>
                    <button type="submit" class="btn btn-saveQuiz">
                        <i class="fas fa-save"></i> Save Quiz
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Quiz Settings Modal -->
    <div id="quiz-settings-modal" class="modal" style="display:none;">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal()">&times;</span>
            <h2 style="color: #f8b500; text-align: center; margin-bottom: 25px;">Quiz Availability Settings</h2>
            
            <div class="settings-container">
                <!-- Single centered Time Settings group -->
                <div class="setting-group">
                    <div class="setting-header">
                        <i class="fas fa-calendar-alt" style="color: #f8b500; margin-right: 10px;"></i>
                        <h3>Time Settings</h3>
                    </div>
                    
                    <div class="modal-form-group">
                        <label for="start-date">
                            <i class="fas fa-play-circle" style="color: #4CAF50;"></i> Start Date:
                        </label>
                        <div class="input-group">
                            <input type="datetime-local" id="start-date" name="start_date" class="form-input" min="">
                            <button type="button" onclick="setStartDateToday()" class="secondary-btn">
                                <i class="fas fa-clock"></i> Now
                            </button>
                        </div>
                        <small class="hint">Leave empty to make available immediately</small>
                    </div>
                    <br>
                    <div class="modal-form-group">
                        <label for="end-date">
                            <i class="fas fa-stop-circle" style="color: #f44336;"></i> End Date:
                        </label>
                        <input type="datetime-local" id="end-date" name="end_date" class="form-input" min="" required>
                        <small class="hint">Students won't be able to take the quiz after this date</small>
                    </div>
                </div>
                
                <!-- Footer buttons -->
                <div class="modal-footer">
                    <button type="button" onclick="closeModal()" class="cancel-btn">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="button" onclick="saveQuizSettings()" class="save-btn">
                        <i class="fas fa-save"></i> Save Settings
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Dark Mode Functionality - Auto apply based on localStorage
        // Check for saved dark mode preference
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
                <div class="form-group">
                    <div class="question-number">Question ${questionNumber}</div>
                    <label>Instructions (optional):</label>
                    <input type="text" name="instructions[]" placeholder="Additional instructions for this question"> <br> <br>
                    <label>Question:</label>
                    <input type="text" 
                           name="questions[]" 
                           required 
                           placeholder="Enter question text">
                </div>
                <div class="answers-section">
                    <label>Select Correct Answer:</label>
                    <div class="answer-container">
                        <button type="button" class="answer-btn" onclick="selectAnswer(this, ${currentQuestions}, 'True')">True</button>
                        <button type="button" class="answer-btn" onclick="selectAnswer(this, ${currentQuestions}, 'False')">False</button>
                    </div>
                    <input type="hidden" name="correct[]" id="correct-${currentQuestions}" required>
                </div>
                <button type="button" class="btn btn-removeQuestion" onclick="removeQuestion(this)">
                    <i class="fas fa-trash"></i> Remove Question
                </button>
            `;

            container.appendChild(questionDiv);
            currentQuestions++;
            
            // Update single-question class for all questions
            updateQuestionRemoveButtons();
        }

        function selectAnswer(button, questionIndex, answer) {
            // Remove selected class from all buttons in this question
            const questionDiv = button.closest('.question-container');
            questionDiv.querySelectorAll('.answer-btn').forEach(btn => {
                btn.classList.remove('selected');
            });
            
            // Add selected class to clicked button
            button.classList.add('selected');
            
            // Update hidden input with selected answer
            document.getElementById(`correct-${questionIndex}`).value = answer;
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
        }

        function goBack() {
            window.history.back();
        }

        // Quiz Settings Modal Functions
        function openQuizSettings() {
            const modal = document.getElementById('quiz-settings-modal');
            modal.style.display = 'block';
            
            // Set minimum dates to current date/time
            const now = new Date();
            const year = now.getFullYear();
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const day = String(now.getDate()).padStart(2, '0');
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            
            const minDateTime = `${year}-${month}-${day}T${hours}:${minutes}`;
            
            document.getElementById('start-date').min = minDateTime;
            document.getElementById('end-date').min = minDateTime;
            
            // Also ensure end date is after start date if start date is set
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
            
            // Also update end date min if needed
            const endDateInput = document.getElementById('end-date');
            if (!endDateInput.value || new Date(endDateInput.value) <= now) {
                // Add 1 hour as default end time
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

            
            // Validate end date is after start date if start date is set
            if (startDate && new Date(endDate) <= new Date(startDate)) {
                alert('End date must be after start date');
                return;
            }
            
            // Create hidden inputs in the main form
            const form = document.getElementById('quiz-form');
            
            // Remove any existing hidden inputs
            ['start_date', 'end_date'].forEach(name => {
                const existing = form.querySelector(`input[name="${name}"]`);
                if (existing) existing.remove();
            });
            
            // Add new hidden inputs
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
            
            // Update the hidden inputs that already exist
            document.querySelector('input[name="start_date"]').value = startDate;
            document.querySelector('input[name="end_date"]').value = endDate;
            
            closeModal();
        }

        function profileDropdown() {
            // Add your profile dropdown functionality here
            alert('Profile dropdown clicked');
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
            
            // Check if settings have been saved
            if (!document.querySelector('input[name="end_date"]').value) {
                alert('Please set quiz availability settings before submitting');
                openQuizSettings();
                return;
            }

            const formData = new FormData(this);
            const allQuestionsFilled = Array.from(document.querySelectorAll('.question-container')).every(questionDiv => {
                // Check if question text is filled (skip instructions field)
                const questionInput = questionDiv.querySelector('input[name="questions[]"]');
                
                // Debug: log what we found
                console.log('Question input found:', questionInput);
                
                if (!questionInput) {
                    console.error('Question input not found in:', questionDiv);
                    return false;
                }
                
                if (!questionInput.value.trim()) {
                    console.log('Question is empty');
                    return false;
                }
                
                // Check if an answer is selected
                const correctInput = questionDiv.querySelector('input[name="correct[]"]');
                console.log('Correct input found:', correctInput, 'Value:', correctInput ? correctInput.value : 'null');
                
                if (!correctInput || !correctInput.value) {
                    console.log('No answer selected');
                    return false;
                }
                
                return true;
            });

            if (!allQuestionsFilled) {
                alert('Please fill all questions and select correct answers before submitting.');
                return;
            }
            
            fetch('t_save_quiz.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(text => {
                try {
                    const data = JSON.parse(text);    
                    if (data && data.success) {
                        alert(data.message); // Show success message
                        window.location.href = `t_quizDash.php?subject_id=${data.subject_id}`; // Redirect to subject dashboard
                    } else {
                        alert('Error creating quiz: ' + (data.message));
                        error_log('Error details', data);
                    }
                } catch (error) {    
                    console.log('Failed to parse server response ' + text);
                    console.error('Invalid JSON Response: ', text);
                }
            })
            .catch(error => {
                console.log('Failed to save quiz: ' + (error.message));
                console.error('Fetch error: ', error);
            });
        });
    </script>
</body>
</html>