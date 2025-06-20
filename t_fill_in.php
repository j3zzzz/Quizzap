<?php
session_start();

// Check if the user has the correct account number prefix
if (strpos($_SESSION['account_number'], 'T') !== 0) {
    header("Location: login.php");
    exit();
}

$subject_id = isset($_GET['subject_id']) ? $_GET['subject_id'] : '';

$response = ["success" => false, "message" => "", "subject_id" => ""];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Fill in the Blanks Quiz</title>
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
        .answer-container {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        .answer-input {
            flex-grow: 1;
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
        .remove-answer {
            font-size: 13px;
            background-color: #ff4444;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 10px;
            cursor: pointer;
            width: 3%;
            height: 20%; 
            margin-top: 2%;
            align-items: center;
            width: 5%;
        }
        .add-answer {
            font-family: Fredoka;
            font-weight: 500;
            background-color: white;
            color: #f8b500;
            border: 2px solid #f8b500;
            border-radius: 5px;
            padding: 8px;
            cursor: pointer;
            margin-top: 10px;
        }

        .add-answer:hover{
            background-color: #f8b500;
            color: white;
            cursor: pointer;
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
        
        .add-question-btn:hover {
            background-color: #f8b500;
            color: white;
        }
        
        /* Hide remove buttons when there's only one */
        .single-answer .remove-answer,
        .single-question .btn-removeQuestion {
            display: none;
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

    <h1>Create Fill in the Blanks Quiz</h1>

    <div class="create-q-cont">
        <form id="quiz-form" method="POST" action="t_save_quiz.php">
            <input type="hidden" name="subject_id" value="<?php echo htmlspecialchars($subject_id); ?>">
            <input type="hidden" name="quiz_type" value="Fill in the Blanks">

            <div class="form-group">
                <label for="title">Quiz Title:</label>
                <input type="text" id="title" name="title" required>
        
                <label for="timer">Timer (minutes):</label>
                <input type="number" id="timer" name="timer" min="1" required>
            </div>

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
                           placeholder="Enter question text (use ___ for blanks)">
                </div>
                <div class="answers-section">
                    <label>Possible Answers:</label>
                    <div class="answer-list">
                        <div class="answer-container single-answer">
                            <input type="text" 
                                   name="blanks_answers[${currentQuestions}][]" 
                                   class="answer-input" 
                                   required 
                                   placeholder="Enter an answer">
                            <button type="button" class="remove-answer" onclick="removeAnswer(this)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <button type="button" class="add-answer" onclick="addAnswer(${currentQuestions})">
                        <i class="fas fa-plus"></i> Add Another Answer
                    </button>
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

        function addAnswer(questionIndex) {
            const answerList = event.target.previousElementSibling;
            const answerContainer = document.createElement('div');
            answerContainer.className = 'answer-container';
            answerContainer.innerHTML = `
                <input type="text" 
                       name="blanks_answers[${questionIndex}][]" 
                       class="answer-input" 
                       required 
                       placeholder="Enter an answer">
                <button type="button" class="remove-answer" onclick="removeAnswer(this)">
                    <i class="fas fa-trash"></i>
                </button>
            `;
            answerList.appendChild(answerContainer);
            
            // Remove single-answer class from all containers in this question
            const containers = answerList.querySelectorAll('.answer-container');
            containers.forEach(container => {
                container.classList.remove('single-answer');
            });
        }

        function removeAnswer(button) {
            const container = button.parentElement;
            const answerList = container.parentElement;
            
            if (answerList.children.length > 1) {
                container.remove();
                
                // If only one answer left, add single-answer class
                if (answerList.children.length === 1) {
                    answerList.querySelector('.answer-container').classList.add('single-answer');
                }
            }
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