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

$subject_id = $_GET['subject_id'];
$response = ["success" => false, "message" => "", "subject_id" => ""];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $subject_id = $_POST['subject_id'];
    $quiz_title = $_POST['title'];
    $timer = $_POST['timer'];
    $questions = $_POST['questions'];
    $answers = $_POST['answers'];
    $correct = $_POST['correct'];
    $quiz_type = $_POST['quiz_type'];
    $instructions = $_POST['instructions'] ?? [];
    $start_date = $_POST['start_date'] ?? null;
    $end_date = $_POST['end_date'] ?? null;

    // Debugging: Check if quiz_type is set correctly
    if (empty($quiz_type)) {
        $response["message"] = "Quiz type is not set.";
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }    

    $stmt = $conn->prepare("INSERT INTO quizzes (subject_id, title, timer, quiz_type, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isisss", $subject_id, $quiz_title, $timer, $quiz_type, $start_date, $end_date);
    
    if ($stmt->execute()) {
        $quiz_id = $stmt->insert_id;
        $stmt->close();

        foreach ($questions as $index => $question) {
            $instruction = isset($instructions[$index]) ? trim($instructions[$index]) : null;

            $stmt = $conn->prepare("INSERT INTO questions (quiz_id, question, instructions) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $quiz_id, $question, $instruction);

            if ($stmt->execute()) {
                $question_id = $stmt->insert_id;
                foreach ($answers[$index] as $answer_index => $answer) {
                    $is_correct = ($correct[$index] == $answer_index) ? 1 : 0;
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
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
} else {
    
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <title>Create Quiz</title>

    <style>
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
            padding: 20px;
            background-color: white;
            box-shadow: 5px 6px 0 0 #BC8900;
        }

        label{
            color: black;
            font-family: Fredoka;
            font-size: 20px;
            font-weight: 500;
        }

        label[for=timer]{
            font-size: 22px;
            margin-left: 8%;
        }

        label[for=title]{
            font-size: 22px;
            margin-left: 3%;
        }

        #title{
            width: 35%;
        }

        input[type=text]{
            width: 100%;
            border-radius: 10px;
            padding: 10px;
            border: 3px solid #B9B6B6;
            margin-top: 1%;
            text-transform: capitalize;
            font-family: Fredoka;
        }

        input[type=number]{
            width: 6%;
            border-radius: 10px;
            padding: 10px;
            border: 3px solid #B9B6B6;
            margin-right: 3%;
            font-family: Fredoka;
        }

        input[type=radio]{
            height: 5%;
            margin-right: 2%;
            margin-left: .5%;
        }

        #timer {
            float: right;
        }

        .question {
            margin-bottom: 20px;
            padding: 40px;
            background-color: #fff5e1;
            border: 2px solid #f8b500;
            border-radius: 10px;
            display: none;
            margin-left: 3%;
            margin-right: 3%;
        }

        .question-number-buttons {
            display: flex;
            gap: 5px;
            margin-top: 5px;
            margin-left: 20px;
            flex-wrap: wrap;
        }

        .question-number-button {
            width: 40px;
            height: 40px;
            border: 2px solid #f8b500;
            border-radius: 50%;
            background-color: white;
            color: #f8b500;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: Fredoka;
            font-weight: bold;
        }

        .question-number-button.active {
            background-color: #f8b500;
            color: white;
        }


        .question-number-button.completed {
            background-color: #FFEFE4;
            color: #A34404;
        }

        .add-icon {
            width: 40px;
            height: 40px;
            border: 2px solid #f8b500;
            border-radius: 50%;
            background-color: white;
            color: #f8b500;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: Fredoka;
            font-weight: bold;
            margin-left: 10px;
        }

        .add-icon:hover {
            background-color: #f8b500;
            color: white;
        }

        .question.active {
            display: block;
        }

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

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
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

        .hint {
            display: block;
            color: #888;
            font-size: 11px;
            margin-top: 5px;
            font-style: italic;
            font-family: Fredoka;
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

        .save-btn {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: background-color 0.3s;
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

        .submit-btn{
            background-color: #f8b500;
            color: white;
            width: 15%;
            border-radius: 10px;
            border: 2px solid #F8b500;
            padding: 10px;
            font-size: 15px;
            font-family: Fredoka;
            font-weight: 500;
            margin-bottom: 1.5%;
            margin-left: 80%;
            box-shadow: 0 6px 0 0 #BC8900;
            cursor: pointer;
        }

        .submit-btn:hover{
            background-color: white;
            color: #f8b500;
        }

        .submit-btn:active{
            background-color: #f8b500;
            color: white;
        }
    </style>
</head>
<body>

    <header>
        <div class="logo"><img src="img/logo1.png" width="200px" height="80px"></div>
        <div class="actions">
            <div class="profile"><img src="img/default.png" width="50px" height="50px"></div>
        </div>
    </header>

    <h1>Create Multiple Choice Quiz</h1>
    
    <div class="create-q-cont">
        
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <div id="save-status" style="font-family: Fredoka; color: #4CAF50; font-size: 14px;"></div>
            <button type="button" onclick="clearLocalStorage()" style="background-color: #ff4444; color: white; border: none; padding: 8px 15px; border-radius: 6px; cursor: pointer; font-family: Fredoka; font-size: 12px;">
                Clear Saved Progress
            </button>
        </div>
    
        <form id="quiz-form" method="post" action="">
            <input type="hidden" name="subject_id" value="<?php echo htmlspecialchars($subject_id); ?>"><br>
            <label for="title">Quiz Title : </label>
            <input type="text" id="title" name="title" required>
            
            <label for="timer" id="timer-label">Timer (in minutes) : </label>
            <input type="number" id="timer" name="timer" required><br><br><br>

            <div id="questions">

                <label for="instructions-1" style="font-size: 18px; color: #666;">Instructions (optional): </label><br>
                <input type="text" id="instructions-1" name="instructions[]" placeholder="Additional instructions for this question" style="margin-bottom: 15px;"><br>

                <div class="question active" data-question="1">
                    <label for="question-[]" style="font-size: 30px;">Question 1 : </label><br>
                    <input class="qstn" type="text" id="question-[]" name="questions[]" required><br><br><br>
                    
                    <input type="radio" name="correct[0]" value="0" required>
                    <label for="answer-1-1">Answer 1 : </label><br>
                    <input type="text" id="answer-1-1" name="answers[0][]" required><br><br>
                    
                    <input type="radio" name="correct[0]" value="1">
                    <label for="answer-1-2">Answer 2 : </label><br>
                    <input type="text" id="answer-1-2" name="answers[0][]" required><br><br>
                    
                    <input type="radio" name="correct[0]" value="2">
                    <label for="answer-1-3">Answer 3 : </label><br>
                    <input type="text" id="answer-1-3" name="answers[0][]" required><br><br>
                    
                    <input type="radio" name="correct[0]" value="3">
                    <label for="answer-1-4">Answer 4 : </label><br>
                    <input type="text" id="answer-1-4" name="answers[0][]" required><br><br>
                </div>
            </div><br><br>

            <div class="question-number-buttons" id="question-number-buttons">
                <button type="button" class="question-number-button active" onclick="showQuestion(1)">1</button>
                <span class="add-icon" onclick="addQuestion()">&#43;</span>
            </div>
            
            <input type="hidden" name="start_date" value="">
            <input type="hidden" name="end_date" value="">
            <input type="hidden" id="quiz_type" name="quiz_type" value="Multiple Choice">
            <input class="submit-btn" type="submit" value="Create Quiz">
            <button type="button" onclick="openQuizSettings()" style="margin-left: 10px; background-color: #f8b500; color: white; border: none; padding: 10px 15px; border-radius: 8px; cursor: pointer; font-family: Fredoka;">
                <i class="fas fa-cog"></i> Quiz Settings
            </button>    
        </form>
    </div>

    <!-- Quiz Settings Modal -->
    <div id="quiz-settings-modal" class="modal" style="display:none;">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal()">&times;</span>
            <h2 style="color: #f8b500; text-align: center; margin-bottom: 25px;">Quiz Availability Settings</h2>
            
            <div class="settings-container">
                <div class="setting-group">
                    <div class="setting-header">
                        <i class="fas fa-calendar-alt" style="color: #f8b500; margin-right: 10px;"></i>
                        <h3>Time Settings</h3>
                    </div>
                    
                    <div class="form-group">
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
                    
                    <div class="form-group">
                        <label for="end-date">
                            <i class="fas fa-stop-circle" style="color: #f44336;"></i> End Date:
                        </label>
                        <input type="datetime-local" id="end-date" name="end_date" class="form-input" min="" required>
                        <small class="hint">Students won't be able to take the quiz after this date</small>
                    </div>
                </div>
                
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
        let questionCount = 1;

        function addQuestion() {
            questionCount++;
            console.log('Adding question:', questionCount);
            
            const questionsDiv = document.getElementById('questions');
            const questionNumberButtonsDiv = document.getElementById('question-number-buttons');
            
            // Create a new question input section
            const newQuestionDiv = document.createElement('div');
            newQuestionDiv.className = 'question';
            newQuestionDiv.setAttribute('data-question', questionCount);
            newQuestionDiv.style.display = 'none'; // Hide initially
            newQuestionDiv.innerHTML = `
                
                <label for="instructions-${questionCount}" style="font-size: 18px; color: #666;">Instructions (optional): </label><br>
                <input type="text" id="instructions-${questionCount}" name="instructions[]" placeholder="Additional instructions for this question" style="margin-bottom: 15px;"><br>
                
                <label for="question-${questionCount}" style="font-size:30px">Question ${questionCount} : </label>
                <input type="text" id="question-${questionCount}" name="questions[]" required><br><br><br>
                
                <input type="radio" name="correct[${questionCount - 1}]" value="0" required>
                <label for="answer-${questionCount}-1">Answer 1 : </label><br>
                <input type="text" id="answer-${questionCount}-1" name="answers[${questionCount - 1}][]" required><br><br>
                
                <input type="radio" name="correct[${questionCount - 1}]" value="1">
                <label for="answer-${questionCount}-2">Answer 2 : </label><br>
                <input type="text" id="answer-${questionCount}-2" name="answers[${questionCount - 1}][]" required><br><br>
                
                <input type="radio" name="correct[${questionCount - 1}]" value="2">
                <label for="answer-${questionCount}-3">Answer 3 : </label><br>
                <input type="text" id="answer-${questionCount}-3" name="answers[${questionCount - 1}][]" required><br><br>
                
                <input type="radio" name="correct[${questionCount - 1}]" value="3">
                <label for="answer-${questionCount}-4">Answer 4 : </label><br>
                <input type="text" id="answer-${questionCount}-4" name="answers[${questionCount - 1}][]" required><br><br>
            `;
            questionsDiv.appendChild(newQuestionDiv);
            
            // Create a new question number button
            const newButton = document.createElement('button');
            newButton.type = 'button';
            newButton.className = 'question-number-button';
            newButton.textContent = questionCount;
            newButton.addEventListener('click', (function(qnumber) {
                return function() {
                showQuestion(qnumber);
            };
            })(questionCount));

            questionNumberButtonsDiv.insertBefore(newButton, questionNumberButtonsDiv.querySelector('.add-icon'));
            
            // Update active question view
            showQuestion(questionCount);
        }

        function showQuestion(questionNumber) {
            // Hide all questions
            document.querySelectorAll('.question').forEach(question => {
                question.style.display = 'none';
            });
            
            // Show the selected question
            const selectedQuestion = document.querySelector(`.question[data-question="${questionNumber}"]`);
            console.log('Button for question:', questionNumber);

            if (selectedQuestion) {
                selectedQuestion.style.display = 'block';
            }
            
            // Update the button active state
            document.querySelectorAll('.question-number-button').forEach(button => {
                button.classList.remove('active');
            });
            document.querySelectorAll('.question-number-button')[questionNumber - 1].classList.add('active');
        }

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
            
            // Ensure end date is after start date if start date is set
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
            
            // Update the hidden inputs
            document.querySelector('input[name="start_date"]').value = startDate;
            document.querySelector('input[name="end_date"]').value = endDate;
            
            closeModal();
        }

        document.getElementById('quiz-form').addEventListener('submit', function(e) {
            e.preventDefault();

            // Check if settings have been saved
            if (!document.querySelector('input[name="end_date"]').value) {
                alert('Please set quiz availability settings before submitting');
                openQuizSettings();
                return;
            }
            
            const formData = new FormData(this);
            const allQuestionsFilled = Array.from(document.querySelectorAll('.question')).every(questionDiv => {
                const inputs = questionDiv.querySelectorAll('input[type="text"]');
                return Array.from(inputs).every(input => input.value.trim() !== '');
            });

            if (!allQuestionsFilled) {
                alert('Please fill all questions and answers before submitting.');
                return;
            }
            
            fetch('t_save_quiz.php', {
                method: 'POST',
                body: formData
            }).then(response => response.json()).then(data => {
                if (data.success) {
                    alert(data.message); // Show success message
                    window.location.href = `t_quizDash.php?subject_id=${data.subject_id}`; // Redirect to subject dashboard
                } else {
                    console.error('Error creating quiz: ' + data.message);
                }
            }).catch(error => console.error('Error:', error));
        });


    </script>

</body>
</html>
