<?php
session_start();
if (strpos($_SESSION['account_number'], 'T') !== 0) {
    header("Location: login.php");
    exit();
}
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "rawrit";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
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

        $stmt_question = $conn->prepare("INSERT INTO questions (quiz_id, question) VALUES (?, ?)");
        if ($stmt_question === false) {
            die('Prepare failed: ' . htmlspecialchars($conn->error));
        }

        $stmt_answer = $conn->prepare("INSERT INTO answers (question_id, answer, is_correct) VALUES (?, ?, ?)");
                if ($stmt_answer === false) {
                    die('Prepare failed: ' . htmlspecialchars($conn->error));
                }

        foreach ($questions as $index => $question) {
            $stmt_question->bind_param("is", $quiz_id, $question);
            if ($stmt_question->execute()) {
                $question_id = $stmt->insert_id;

                $correct_answers = explode(',', $answers[$index]); // Split answers by commas
                foreach ($correct_answers as $answer) {
                    $correct_answer = trim($correct_answer);
                    $is_correct = 1; // Since it's an enumeration type, all provided answers are correct
                    $stmt_answer->bind_param("isi", $question_id, $answer, $is_correct);
                    $stmt_answer->execute();
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
            padding: 40px;
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
            margin-left: 15%;
            font-weight: 500;
        }

        label[for=title]{
            font-size: 22px;
            margin-left: 2%;
            font-weight: 500;
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
            font-size: 17px;
        }

        input[type=number]{
            width: 10%;
            border-radius: 10px;
            padding: 10px;
            border: 3px solid #B9B6B6;
            margin-right: 2%;
            font-family: Fredoka;
        }

        .quiz-form {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 20px;
        }
        
        .question-container {
            background-color: #fff5e1;
            padding: 30px;
            margin-bottom: 15px;
            border-radius: 10px;
            border: 2px solid #f8b500;
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
        
        .add-question-btn:hover {
            background-color: #f8b500;
            color: white;
        }
        
        /* Hide remove buttons when there's only one */
        .single-question .btn-removeQuestion {
            display: none;
        }

        .number-buttons {
            display: flex;
            margin-top: 20px;
            align-items: center;
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

    <h1>Create Enumeration Quiz</h1>

    <div class="create-q-cont">
        <form id="quiz-form" method="POST" action="">
            <input type="hidden" name="subject_id" value="<?php echo htmlspecialchars($subject_id); ?>">
            <input type="hidden" name="quiz_type" value="Enumeration">

            <div class="form-group">
                <label for="title">Quiz Title:</label>
                <input type="text" id="title" name="title" required>
        
                <label for="timer">Timer (minutes):</label>
                <input type="number" id="timer" name="timer" min="1" required>
            </div>

            <div id="questionsContainer"></div>

            <div class="number-buttons" id="numberButtons">
                <button type="button" class="add-question-btn" id="addQuestionBtn">
                    <i class="fas fa-plus"></i> Add Question
                </button>
            </div>

            <div class="actions">
                <button type="button" class="btn btn-back" onclick="goBack()">
                    <i class="fas fa-arrow-left"></i> Back
                </button>
                <button type="submit" class="btn btn-saveQuiz">
                    <i class="fas fa-save"></i> Save Quiz
                </button>
            </div>
        </form>
    </div>

    <script>
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
                    <input type="text" 
                           name="questions[]" 
                           required 
                           placeholder="Enter question text">
                    <label style="margin-top: 10px; display: block;">Correct Answers (separated by commas):</label>
                    <input type="text" 
                           name="correct_answer[]" 
                           required 
                           placeholder="Enter correct answers separated by commas">
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

        document.addEventListener('DOMContentLoaded', function() {
            const addQuestionBtn = document.getElementById('addQuestionBtn');
            addQuestionBtn.addEventListener('click', addQuestion);

            // Add first question automatically
            addQuestion();
        });

        document.getElementById('quiz-form').addEventListener('submit', function(e) {
            e.preventDefault();       
            
            const formData = new FormData(this);
            const allQuestionsFilled = Array.from(document.querySelectorAll('.question-container')).every(questionDiv => {
                const inputs = questionDiv.querySelectorAll('input[type="text"]');
                return Array.from(inputs).every(input => input.value.trim() !== '');
            });

            if (!allQuestionsFilled) {
                alert('Please fill all questions and answers before submitting.');
                return;
            }
            
            fetch('t_enum.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message); // Show success message
                    window.location.href = `t_quizDash.php?subject_id=${data.subject_id}`; // Redirect to subject dashboard
                } else {
                    alert('Error creating quiz: ' + (data.message));
                    console.error('Error details', data);
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