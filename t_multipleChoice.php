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
    $quiz_type = $_POST['quiz_type']; // Set the quiz type

    // Debugging: Check if quiz_type is set correctly
    if (empty($quiz_type)) {
        $response["message"] = "Quiz type is not set.";
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }    

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
        <form id="quiz-form" method="post" action="">
            <input type="hidden" name="subject_id" value="<?php echo htmlspecialchars($subject_id); ?>"><br>
            <label for="title">Quiz Title : </label>
            <input type="text" id="title" name="title" required>
            
            <label for="timer" id="timer-label">Timer (in minutes) : </label>
            <input type="number" id="timer" name="timer" required><br><br><br>

            <div id="questions">
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
            
            <input type="hidden" id="quiz_type" name="quiz_type" value="Multiple Choice">
            <input class="submit-btn" type="submit" value="Submit Quiz">    
        </form>
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

document.getElementById('quiz-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
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
