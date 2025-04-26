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

$response = ["success" => false, "message" => "", "subject_id" => ""];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $subject_id = $_POST['subject_id'];
    $quiz_title = $_POST['title'];
    $timer = $_POST['timer'];
    $questions = $_POST['questions'];
    $answers = $_POST['answers'];
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
            margin-left: 8%;
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
            background-color: white;
            border: 2px solid #DCDCDC;
            border-radius: 10px;
            display: none;
            margin-left: 3%;
            margin-right: 3%;
        }

        .ans-btn{
            justify-content: space-between;
            align-items: center;
            font-family: Tilt Warp Regular;
            font-size: 20px;
            width: 30%;
            padding: 10px;
            border-radius: 15px;
            border: 3px solid #f8b500;
            background: white;
            color: #f8b500;
        }

        .ans-btn:hover{
            background-color: #f8b500;
            color: white;
        }

        .ans-btn:active{
            background-color: #f8b500;
            color: white;
        }

        .question-number-buttons {
            max-width: 100%;
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            margin-left: 3%;
        }

        .question-number-button {
            margin-left: .5%;
            padding: 5px 10px;
            cursor: pointer;
            background-color: #F8B500;
            border: 1px solid #F8B500;
            color: white;
            border-radius: 4px;
            position: relative;
        }

        .question-number-button.active {
            background-color: white;
            color: #f8b500;
        }


        .question-number-button.completed {
            background-color: #F8b500;
            color: white;
        }

        .add-icon {
            font-size: 24px;
            color: #F8B500;
            cursor: pointer;
            margin-left: 10px;
        }

        .question.active {
            display: block;
        }

        .submit-btn{
            background-color: #f8b500;
            color: white;
            width: 15%;
            border-radius: 10px;
            border: 2px solid #f8b500;
            padding: 10px;
            font-size: 15px;
            font-family: Tilt Warp Regular;
            margin-bottom: 1.5%;
            margin-left: 80%;
            box-shadow: 0 6px 0 0 #BC8900;
        }

        .submit-btn:hover{
            background-color: white;
            color: #f8b500;
        }

        .submit-btn:active {
            background-color: #f8b500;
            color: white;
            box-shadow: 0 4px 0 0 #BC8900;
        }

        .selected {
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

    <h1>Create True or False Quiz</h1> 
    
    <div class="create-q-cont">
        <form id="quiz-form" method="post" action="create_quiz.php">
            <input type="hidden" name="subject_id" value="<?php echo htmlspecialchars($subject_id); ?>">
            <label for="title">Quiz Title:</label>
            <input type="text" id="title" name="title" required>
            
            <label for="timer" id="timer-label">Timer (in minutes):</label>
            <input type="number" id="timer" name="timer" required><br><br><br>

            <div id="questions">
                <div class="question active" data-question="1">
                    <label for="question-1">Question 1:</label>
                    <input type="text" id="question-1" name="questions[]" required><br><br>
                    
                    <button type="button" class="ans-btn" data-question="1" data-answer="True" onclick="selectAnswer(1, 'True')">True</button>

                    <button type="button" class="ans-btn" data-question="1" data-answer="False" onclick="selectAnswer(1, 'False')">False</button>

                    <input type="hidden" name="correct[]" id="correct-answer-1" required>
                    
                </div>
            </div>

            <div class="question-number-buttons" id="question-number-buttons">
                <button type="button" class="question-number-button active" onclick="showQuestion(1)">1</button>
                <span class="add-icon" onclick="addQuestion()">&#43;</span>
            </div>
            
            <input type="hidden" name="quiz_type" value="True or False">
            <button class="submit-btn" type="submit">Submit Quiz</button>    
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
                <label for="question-${questionCount}">Question ${questionCount}:</label>
                <input type="text" id="question-${questionCount}" name="questions[]" required><br><br>  
                
                <button type="button" class="ans-btn" data-question="${questionCount}" data-answer="True" onclick="selectAnswer(${questionCount}, 'True')">True</button>

                <button type="button" class="ans-btn" data-question="${questionCount}" data-answer="False" onclick="selectAnswer(${questionCount}, 'False')">False</button>

                <input type="hidden" name="correct[]" id="correct-answer-${questionCount}" required>


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

        function selectAnswer(questionCount, answer) {
                    document.querySelectorAll(`.ans-btn[data-question="${questionCount}"]`).forEach(button => {
                        button.classList.remove('selected');
                    });
                    document.querySelector(`.ans-btn[data-question="${questionCount}"][data-answer="${answer}"]`).classList.add('selected');
                    
                    document.getElementById(`correct-answer-${questionCount}`).value = answer;
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
            })
        });


</script>

</body>
</html>