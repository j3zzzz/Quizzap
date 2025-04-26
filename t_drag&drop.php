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
 
$subject_id = $_GET['subject_id'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Drag & Drop Quiz Creator</title>
    

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
        border-radius: 10px;
        padding: 10px;
        border: 3px solid #B9B6B6;
        font-family: Tilt Warp Regular;
        font-size: 20px;
    }

    .answer_input {
        width: 180px !important;
        margin-bottom: 10px;
    }

    input[type=number]{
        width: 8%;
        border-radius: 10px;
        padding: 10px;
        border: 3px solid #B9B6B6;
        margin-right: 3%;
        font-family: Tilt Warp Regular;
        font-size: 15px;
        text-align: center;        
    }

    input[type="radio"] {
        accent-color: #f8b500;
    }

    input[type="radio"]:selected {
        border: white;
    }

    label[for="timer"] {
        margin-left: 12%;
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
        font-family: Purple Smile;
        font-size: 20px;
        width: 30%;
        padding: 10px;
        border-radius: 15px;
        border: 3px solid #A34404;
        background: white;
        color: #A34404;
    }

    .question-number-buttons {
        font-family: 'Tilt Warp';
        max-width: 100%;
        display: flex;
        align-items: center;
        margin-bottom: 20px;
        margin-left: 3%;
    }

    .question-number-button {
        width: 40px;
        height: 40px;
        margin-left: .5%;
        padding: 5px 10px;
        cursor: pointer;
        border-radius: 50% !important;
        font-family: Tilt Warp Regular;
        background-color: white;
        border: 2px solid #F8B500;
        color: #F8B500;
        border-radius: 4px;
        position: relative;
    }

    .question-number-button:hover {
        background-color: #f8b500;
        color: white;
    }

    .question-number-button.active {
        background-color: #f8b500;
        color: white;
    }

    .question-number-button.completed {
        background-color: #F8b500;
        color: white;
    }

    .answers-container {
        align-items: center;
        width: 100%;
        padding: 20px 60px;
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .add-icon {
        width: 40px;
        height: 40px;
        font-family: Tilt Warp Regular;
        font-size: 24px;
        color: #f8b500;
        cursor: pointer;
        align-items: center;
        justify-content: center;
        margin-left: 10px;
        display: flex;
        border: 2px solid #f8b500;
        border-radius: 50%;
        gap: 5px; /* Space between buttons */
    }

    .add-icon:hover {
        background-color: #f8b500 !important;
        color: white !important;
    }

    .add-answer, .remove-answer {
        font-family: 'Tilt Warp';
        font-size: 20px;
        color: #f8b500;
        cursor: pointer;
        display: flex;
        flex-wrap: wrap;
        line-height: 1;
        align-self: center;
        gap: 5px; /* Space between buttons */
    }

    .container span:hover {
        color: #e4a600;
        transition: 0.2s;
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

    .submit-btn:hover{
        background-color: white;
        color: #f8b500;
    }

    .submit-btn:active {
        background-color: #f8b500;
        color: white;
        box-shadow: 0 4px 0 0 #BC8900;
    }

    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateX(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes slideOut {
        from {
            opacity: 1;
            transform: translateX(0);
        }

        to {
            opacity: 0;
            transform: translateY(-20px);
        }
    }

    .slideOut {
        animation: slideOut 0.3s ease-out forwards;
    }

    .answer-wrapper {
        margin-top: 10px;
        display: flex;
        align-self: center;
        align-items: center;
        margin-bottom: 10px;
        line-height: 1;
        animation: slideIn 0.3s ease-out forwards;
        opacity: 0;
    }

    .answer-wrapper input[type="radio"] {
        margin-right: 10px;
        line-height: 1;
    }

    /* Styling the text inputs */
    .answer-wrapper input[type="text"] {
        line-height: 1;
        flex-grow: 1; /* Make the text input take up remaining space */
        margin-right: 5px; /* Space between text input and buttons */
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

    <h1>Drag & Drop Quiz</h1> 

    <div class="create-q-cont">
    <div class="container">
        <form id="quiz-form" action="t_save_quiz.php" method="POST"><br>
            <input type="hidden" name="subject_id" value="<?php echo htmlspecialchars($subject_id); ?>">
            <label for="title">Quiz Title:</label>
            <input type="text" id="title" name="title" required>

            <label for="timer">Timer (minutes):</label>
            <input type="number" id="timer" name="timer" required><br><br><br>

            <div id="questions-container">
                <div class="question-box" id="question-1">
                    <label for="question_1">Question 1:</label><br>
                    <input type="text" id="question_1" name="questions[]" required><br><br>
                    <label for="correct_answer_1">Choices:</label><br>

                    <div class="answers-container" id="answers-container-1">
                        <input type="radio" name="correct_answer[0]" class="correct-answer-1" value="0">
                        <input type="text" class="answer_input" id="correct_answer_1_1" name="answers[0][]" required> 
                        <span class="add-answer" id="add-answer-1" onclick="addAnswer(1)">&#43;</span>
                        <span class="remove-answer" id="remove-answer-1" onclick="removeAnswer(1)" style="display: none;">&#8722;</span>
                    </div>   
                    <br><br>


                </div>
            </div>

            <br><br>

            <div class="question-number-buttons" id="question-number-buttons">
                <button type="button" class="question-number-button" onclick="showQuestion(1)">1</button>
                <span class="add-icon" onclick="addQuestion()">&#43;</span>
            </div>
            <br>
            <input type="hidden" id="quiz_type" name="quiz_type" value="Drag & Drop">
            <input class="submit-btn"type="submit" value="Save Quiz">
        </form>
    </div>

    <script>
        let questionCount = 1;

        const answerCounts = { 1: 1};

        function addQuestion() {
            questionCount++;
            answerCounts[questionCount] = 1;
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
                <label for="correct_answer_${questionCount}">Choices:</label><br>

                <div class="answers-container" id="answers-container-${questionCount}">

                    <input type="radio" name="correct_answer[${questionCount-1}]" class="correct-answer-${questionCount}" value="0">

                    <input type="text" class="answer_input" id="correct_answer_${questionCount}_1" name="answers[${questionCount - 1}][]" required>

                    <span class="add-answer" id="add-answer-${questionCount}" onclick="addAnswer(${questionCount})">&#43;</span>

                    <span class="remove-answer" id="remove-answer-${questionCount}" onclick="removeAnswer(${questionCount})" style="display: none;">&#8722;</span>
                </div>    
                <br><br>
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
 
        function addAnswer(questionNum) {
                answerCounts[questionNum] = (answerCounts[questionNum] || 1) + 1;
                const currentAnswerCount = answerCounts[questionNum];

            console.log(`Adding answer ${currentAnswerCount} to question ${questionNum}`);

            //gets the answer container of a current question    
            const answersContainer = document.getElementById(`answers-container-${questionNum}`);

            if (!answersContainer) {
                console.error('Answers container not found for question:', questionNum);
                return;
            }

            const wrapper = document.createElement('div');
            wrapper.className = 'answer-wrapper';
            wrapper.style.width = 'fit-content';
            wrapper.style.display = 'inline';

            //creates a  new radio button    
            const newAnswerRadio = document.createElement('input');
            newAnswerRadio.type = 'radio';
            newAnswerRadio.name = `correct_answer[${questionNum-1}]`;
            newAnswerRadio.className = `correct-answer-${questionNum}`;
            newAnswerRadio.value = currentAnswerCount - 1; //value na nag i-identify ng answer ay correct
            

            //creates a new user input    
            const newAnswerInput = document.createElement('input');
            newAnswerInput.type = 'text';
            newAnswerInput.className = 'answer_input';
            newAnswerInput.id = `correct_answer_${questionNum}_${currentAnswerCount}`; //unique id for each answer
            newAnswerInput.name = `answers[${questionNum-1}][]`; // array input for correct answers per question
            newAnswerInput.required = true;

            wrapper.appendChild(newAnswerRadio);
            wrapper.appendChild(newAnswerInput);

            //select the "Add Answer" button sa isang specific question
            const addAnswerButton = document.getElementById(`add-answer-${questionNum}`);
            //select the "Remove Answer" button sa isang question
            const removeAnswerButton = document.getElementById(`remove-answer-${questionNum}`);

            if (addAnswerButton && answersContainer.contains(addAnswerButton)) {
                //inserts the new answer input before the button
                answersContainer.insertBefore(wrapper, addAnswerButton);

                wrapper.offsetHeight;

                const answerInputs = answersContainer.querySelectorAll('.answer_input');
                if (answerInputs.length > 1) {
                    removeAnswerButton.style.display = 'inline';
                }
            } else {
                console.error('Add Answer button not found or is not a child of the answers container');
            }
        }

        function removeAnswer(questionNum) {
            const answersContainer = document.getElementById(`answers-container-${questionNum}`);
            const wrappers = answersContainer.querySelectorAll('.answer-wrapper');

            //kung ang wrapper ay more than one, pwede mag remove     
            if (wrappers.length > 0) {
                const wrappersToRemove = wrappers[wrappers.length - 1];
                wrappersToRemove.classList.add('slide-out');

                answerCounts[questionNum]--;

                setTimeout(() => {
                    wrappersToRemove.remove();
                    console.log("Answer has been removed");

                const answerRadios = answersContainer.querySelectorAll('input[type="radio"]');
                if (answerRadios.length <= 2) {
                    answerRadios[0].checked = true; //ine-ensure na isang radio button lang yung always na nakacheck
                }        
                
                if (wrappers.length -1 === 0) {
                    const removeAnswerButton = document.getElementById(`remove-answer-${questionNum}`);
                    if (removeAnswerButton) {
                        removeAnswerButton.style.display = 'none';
                    }
                }
                updateRadioValues(questionNum);
                }, 300);
            }
        }

        function updateRadioValues(questionNum) {
            const answersContainer = document.getElementById(`answers-container-${questionNum}`);
            const radios = answersContainer.querySelectorAll('input[type="radio"]');
            
            radios.forEach((radio, index) => {
                radio.value = index;
            });
        }    

        function validateForm() {
            const questions = document.querySelectorAll('.question-box');
            
            for (let index = 0; index < questions.length; index++) {
                const question = questions[index];
                const questionInput = question.querySelector(`input[name="questions[]"]`);
                const answers = question.querySelectorAll('.answer_input');
                const selectedRadio = question.querySelector(`input[name="correct_answer[${index}]"]:checked`);

                if (!questionInput.value.trim()) {
                    alert(`Please fill in Question ${index + 1}`);
                    return false;
                }

                if (answers.length < 2) {
                    alert(`Question ${index + 1} must have at least two answers`);
                    return false;
                }

                const emptyAnswer = Array.from(answers).find(answer => !answer.value.trim());
                if (emptyAnswer) {
                    alert(`Please fill in all answers for question ${index + 1}`);
                    return false;
                }

                if (!selectedRadio) {
                    alert(`Please select a correct answer for Question ${index + 1}`);
                    return false;
                }
            }

            return true;
        }

        function initQuizForm() {
            showQuestion(1); // Show the first question on load
        }

        window.onload = function() {
            initQuizForm();

        document.getElementById('quiz-form').addEventListener('submit', function(e) {
        e.preventDefault();    

        if (!validateForm()) {
            return;
        }    
    
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
        console.log('Failes to save quiz: ' + (error.message));
        console.error('Fetch error: ', error);
    });
});
};    
</script>

</body>
</html>


