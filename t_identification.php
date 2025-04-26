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
    <title>Identification Quiz Creator</title>
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
            font-family: Tilt Warp Regular;
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
            font-family: Tilt Warp Regular;
            font-size: 22px;
        }

        label[for=timer]{
            font-size: 25px;
            margin-left: 13%;
        }

        label[for=title]{
            font-size: 30px;
            margin-left: 3%;
        }

        #title{
            width: 35%;
        }

        input[type=text]{
            width: 100%;
            padding: 15px;
            border-radius: 10px;
            padding: 10px;
            border: 3px solid #B9B6B6;
            margin-top: 1%;
            font-family: Tilt Warp Regular;
            font-size: 20px;
        }

        input[type=number]{
            width: 6%;
            border-radius: 10px;
            padding: 10px;
            border: 3px solid #B9B6B6;
            margin-right: 3%;
            font-family: Tilt Warp Regular;
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

        .ans-btn{
            justify-content: space-between;
            align-items: center;
            font-family: Purple Smile;
            font-size: 20px;
            width: 30%;
            padding: 10px;
            border-radius: 15px;
            border: none;
            border: 3px solid #A34404;
            background: white;
            color: #A34404;
        }

        #question-container{
            background: #fff5e1;
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
            font-family: Tilt Warp Regular;
            font-weight: bold;
        }

        .question-number-button.active {
            background-color: #f8b500;
            color: white;
        }


        .question-number-button.completed {
            background-color: #F8b500;
            color: white;
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
            font-family: Tilt Warp Regular;
            font-weight: bold;
            margin-left: 10px;
        }

        .add-icon:hover {
            background-color: #f8b500;
            color: white;
        }

        .question-box {
            margin-bottom: 20px;
            padding: 40px;
            background-color: #fff5e1;
            border: 2px solid #f8b500;
            border-radius: 10px;
            display: none;
            margin-left: 3%;
            margin-right: 3%;
        }

        .submit-btn{
            background-color: #F8b500;
            color: white;
            width: 15%;
            border-radius: 10px;
            border: 2px solid #F8b500;
            padding: 10px;
            font-size: 15px;
            font-family: Tilt Warp Regular;
            margin-bottom: 1.5%;
            margin-left: 80%;
            box-shadow: 0 6px 0 0 #BC8900;
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

    <h1>Create Identification Quiz</h1> 

    <div class="create-q-cont">
    <div class="container">
        <form id="quiz-form" action="" method="POST"><br>
            <input type="hidden" name="subject_id" value="<?php echo htmlspecialchars($subject_id); ?>">
            <label for="title">Quiz Title:</label>
            <input type="text" id="title" name="title" required>

            <label for="timer">Timer (minutes):</label>
            <input type="number" id="timer" name="timer" required><br><br><br>

            <div id="questions-container">
                <div class="question-box" id="question-1">
                    <label for="question_1">Question 1:</label><br>
                    <input type="text" id="question_1" name="questions[]" rows="3" cols="50" required><br><br>
                    <label for="correct_answer_1">Correct Answer :</label><br>
                    <input type="text" id="correct_answer_1" name="correct_answer[]" required><br><br>
                </div>
            </div><br><br>

            <div class="question-number-buttons" id="question-number-buttons">
                <button type="button" class="question-number-button" onclick="showQuestion(1)">1</button>
                <span class="add-icon" onclick="addQuestion()">&#43;</span>
            </div>
            <br>
            <input type="hidden" id="quiz_type" name="quiz_type" value="Identification">
            <input class="submit-btn"type="submit" value="Save Quiz">
            
        </form>
    </div>

    <script>
        let questionCount = 1;

        function addQuestion() {
            questionCount++;
            console.log('Adding question:', questionCount);

            const questionsDiv = document.getElementById('questions-container');
            const newQuestionNumberButtonsDiv = document.getElementById('question-number-buttons');

            const newQuestionDiv = document.createElement('div');
            newQuestionDiv.className = 'question-box';
            newQuestionDiv.setAttribute('id', `question-${questionCount}`);
            newQuestionDiv.style.display = 'none'; // Hide initially

            newQuestionDiv.innerHTML = `
                <label for="question_${questionCount}">Question ${questionCount}:</label><br>
                <input type="text" id="question_${questionCount}" name="questions[]" rows="3" cols="50" required><br><br>
                <label for="correct_answer_${questionCount}">Correct Answer :</label><br>
                <input type="text" id="correct_answer_${questionCount}" name="correct_answer[]" required><br><br>
            `;
            questionsDiv.appendChild(newQuestionDiv);

            const buttonContainer = document.getElementById('question-number-buttons');
            const newButton = document.createElement('button');
            newButton.type = 'button';
            newButton.classList.add('question-number-button');
            newButton.textContent = questionCount;
            newButton.addEventListener('click', (function(qnumber) {
                return function() {
                    showQuestion(qnumber);
                };
            })(questionCount));

            newQuestionNumberButtonsDiv.insertBefore(newButton, newQuestionNumberButtonsDiv.querySelector('.add-icon'));

            showQuestion(questionCount);
        }    

        function showQuestion(number) {
            console.log("Switching to question:", number);

            const allQuestions = document.querySelectorAll('.question-box');
            allQuestions.forEach((question, index) => {
                console.log("Hiding question:", index + 1);
                question.style.display = 'none';
            });

            const selectedQuestion = document.getElementById(`question-${number}`);
            if (selectedQuestion) {    
                console.log("Showing question:", number);
                selectedQuestion.style.display = 'block';
            } else {
                console.log("Question not found:", number);
            }

            const allButtons = document.querySelectorAll('.question-number-button');
            allButtons.forEach((button) => {
                button.classList.remove('active');
            });

            const activeButton = Array.from(allButtons).find(button => button.textContent == number);
            if (activeButton) {
                console.log("Setting active button for question:", number);
                activeButton.classList.add('active');
            } else {
                console.log("Active button not found for question:", number);
            }
        }        
        
        function initQuizForm() {
            showQuestion(1); // Show the first question on load
        }

        window.onload = function() {
            initQuizForm();

        document.getElementById('quiz-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
        const formData = new FormData(this);
        const allQuestionsFilled = Array.from(document.querySelectorAll('.question-box')).every(questionDiv => {
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
        })
    });
};
    </script>

</body>
</html>
