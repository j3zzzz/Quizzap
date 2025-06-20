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

if (!isset($_GET['subject_id'])) {
    header("Location: t_quizDash.php"); 
    exit();
}

$subject_id = intval($_GET['subject_id']);

// Validate that the subject exists
$stmt = $conn->prepare("SELECT subject_id FROM subjects WHERE subject_id = ?");
$stmt->bind_param("i", $subject_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Invalid subject selected");
}   

$conn->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Quiz Creator</title>
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

        h1 {
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

        .quiz-controls {
            position: sticky;
            top: 0;
            background-color: white;
            padding: 15px 0;
            border-bottom: 2px solid #f8b500;
            margin-bottom: 20px;
            z-index: 100;
        }

        label {
            color: black;
            font-family: Fredoka;
            font-size: 20px;
        }

        label[for=timer] {
            font-size: 25px;
            margin-left: 13%;
        }

        label[for=title] {
            font-size: 30px;
            margin-left: 2%;
            margin-right: 1%;
        }

        label[for=answer]{
            width: 90% !important;
        }


        #title {
            width: 35%;
        }

        input[type=text] {
            width: 100%;
            padding: 15px;
            border-radius: 10px;
            padding: 10px;
            border: 3px solid #B9B6B6;
            margin-top: 1%;
            font-family: Fredoka;
            font-size: 18px;
        }

        input[type=number] {
            width: 6%;
            border-radius: 10px;
            padding: 10px;
            border: 3px solid #B9B6B6;
            margin-right: 3%;
            font-family: Fredoka;
        }

        input[type=radio]{
            width: 5%;
            float: right;
            margin-right: 2%;
            margin-left: .5%;
        }

        select {
            width: 30%;
            padding: 10px;
            border-radius: 10px;
            border: 3px solid #B9B6B6;
            font-family: Fredoka;
            background-color: #f0f0f0;
            cursor: pointer;
        }

        button {
            background-color: #fbbd08;
            color: white;
            border: none;
            padding: 12px 24px;
            font-family: Fredoka;
            font-size: 16px;
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        button:hover {
            background-color: #F8B500;
        }

        .delete-btn {
            background-color:#f44336;
            color: white;
            border: none;
            padding: 12px 24px;
            font-family: Fredoka;
            font-size: 16px;
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .question {
            background-color: #fff5e1;
            border: 1px solid #f0c808;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            font-family: Fredoka;
        }

        .question input[type=text] {
            margin-bottom: 10px;
        }

        h4 {
            color: #444;
            margin-bottom: 10px;
            font-family: Fredoka;
        }

        .question-number {
            font-weight: bold;
            color: #fbbd08;
            margin-bottom: 10px;
            font-size: 18px;
        }

        input[type="submit"] {
            background-color: #fbbd08;
            color: white;
            border: none;
            padding: 12px 24px;
            font-size: 16px;
            border-radius: 8px;
            cursor: pointer;
            display: block;
            margin: 20px auto 0;
            transition: background-color 0.3s;
            font-family: Fredoka;
        }

        input[type="submit"]:hover {
            background-color: #e6a700;
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
            display: flex;
            align-items: center;
        }

        .quiz-timer label {
            margin-right: 8px;
            font-size: 16px;
        }

        #questionsContainer {
            margin-top: 20px;
            max-height: 70vh;
            overflow-y: auto;
            padding-right: 10px;
        }

        .type-selector {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .hidden-input {
            display: none;
        }

        button{

        }

        .question-container {
            display: grid;
            gap: 15px;
            margin-bottom: 20px;
        }

        .drag-input{
            width: 180px !important;
            margin-bottom: 10px;
        }
        
        .input-group {
            display: grid;
            grid-template-columns: 150px 1fr;
            align-items: center;
            gap: 10px;
        }
        
        .matching-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .options-group {
            display: grid;
            gap: 10px;
        }
        
        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
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
            font-family: Fredoka;
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
            font-family: 'Fredoka';
            font-size: 20px;
            color: #f8b500;
            cursor: pointer;
            display: flex;
            flex-wrap: wrap;
            line-height: 1;
            align-self: center;
            gap: 5px; /* Space between buttons */
        }
        
        /* Form validation styles */
        input:invalid, select:invalid {
            border-color: #ff4444;
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
        
        .error-message {
            color: #ff4444;
            font-size: 0.9em;
            margin-top: 5px;
        }

        .matching-image-preview img{
            width: 300px;
            height: 300px;
        }

        .matching-pairs-section {
            margin-top: 15px;
        }

        .matching-pairs-container {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin: 10px 0;
        }

        .matching-pair {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .matching-column {
            flex: 1;
        }

        .pair-number {
            font-weight: bold;
            min-width: 30px;
        }

        .remove-pair {
            background-color: #ff4444;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 8px;
            cursor: pointer;
        }

        .add-pair {
            background-color: #f8b500;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 8px 15px;
            cursor: pointer;
            font-family: Fredoka;
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

    <h1>Create All Zapped Quiz</h1> 

    <div class="create-q-cont">
        <form id="quiz-form" method="post" action="allZapped_saveQuiz.php" enctype="multipart/form-data">
            <div class="quiz-header">
                <input type="hidden" name="subject_id" value="<?php echo isset($subject_id) ? htmlspecialchars($subject_id) : ''; ?>">
                <label for="title">Quiz Title: </label>
                <input type="text" name="title" id="title" placeholder="Enter quiz title" required>
                <div class="quiz-timer">
                    <label for="timer">Set timer: </label>
                    <input type="number" name="timer" id="timer" min="1" required>
                </div>
            </div>

            <div class="quiz-controls">
                <div class="type-selector"> 
                    <label for="questType">Quiz Type:</label>
                    <select id="questType">
                        <option value="multiple_choice">Multiple Choice</option>
                        <option value="enumeration">Enumeration</option>
                        <option value="identification">Identification</option>
                        <option value="fill_in_the_blanks">Fill in the Blanks</option>
                        <option value="true_or_false">True or False</option>
                        <option value="drag_and_drop">Drag & Drop</option>
                        <option value="matching_type">Matching type</option>
                    </select>
                    <button type="button" onclick="addQuestion()">Add Question</button>
                </div>
            </div>

            <div id="questionsContainer"></div>
            <input type="hidden" id="quiz_type" name="quiz_type" value="All Zapped">
            <input type="submit" name="submit" value="Create Quiz">
        </form>
    </div>

    <script>
        let questionCounter = 1;
    const answerCounts = { 1: 1 };

    function addQuestion() {
        const questType = document.getElementById("questType").value;
        answerCounts[questionCounter] = 1;
        const container = document.createElement('div');
        container.className = 'question';
        container.dataset.questionNumber = questionCounter;
        container.innerHTML = `
            <div class="question-header">
                <div class="question-number">Question ${questionCounter}</div>
                <button type="button" onclick="deleteQuestion(this)" class="delete-btn">Delete</button>
            </div>
            <input type="hidden" name="question_type[]" value="${questType}">
            ${getQuestionTemplate(questType, questionCounter-1)}
        `;
        
        document.getElementById("questionsContainer").appendChild(container);
        questionCounter++;
    }

    function deleteQuestion(button) {
        const questionDiv = button.closest('.question');
        questionDiv.remove();
        renumberQuestions();
    }

    function renumberQuestions() {
        const questions = document.querySelectorAll('.question');
        questions.forEach((question, index) => {
            const questionNumber = index + 1;
            question.dataset.questionNumber = questionNumber;
            question.querySelector('.question-number').textContent = `Question ${questionNumber}`;
            
            // Update any indexes in the question content
            const questionIndex = questionNumber - 1;
            const inputs = question.querySelectorAll('input[name^="answers["], input[name^="correct["], input[name^="left_items["], input[name^="right_items["]');
            inputs.forEach(input => {
                const name = input.name;
                input.name = name.replace(/\[\d+\]/, `[${questionIndex}]`);
            });
        });
        
        questionCounter = questions.length + 1;
    }

        function getQuestionTemplate(questType, index) {
            switch (questType) {
                case "multiple_choice":
                    return `
                    <h4>Multiple Choice</h4>
                        <div class="question-container">
                        <label>Question: </label>
                        <input type="text" name="questions[]" required>
                            <input type="radio" name="correct[${index}]" value="0" required>
                            <label for="answer-${index}-1">Answer 1 : </label>
                            <input type="text" id="answer-${index}-1" name="answers[${index}][]" required>
                            
                            <input type="radio" name="correct[${index}]" value="1">
                            <label for="answer-${index}-2">Answer 2 : </label>
                            <input type="text" id="answer-${index}-2" name="answers[${index}][]" required>
                            
                            <input type="radio" name="correct[${index}]" value="2">
                            <label for="answer-${index}-3">Answer 3 : </label>
                            <input type="text" id="answer-${index}-3" name="answers[${index}][]" required>
                            
                            <input type="radio" name="correct[${index}]" value="3">
                            <label for="answer-${index}-4">Answer 4 : </label>
                            <input type="text" id="answer-${index}-4" name="answers[${index}][]" required>
                        </div>`;
                case "true_or_false":
                    return `
                        <h4>True or False</h4>
                        <label>Question: </label>
                        <input type="text" name="questions[]" required>
                        <label>Correct Answer: </label>
                        <select name="correct_option[${index}]">
                            <option value="True">True</option>
                            <option value="False">False</option>
                        </select>`;
                case "enumeration":
                    return `
                        <h4>Enumeration</h4>
                        <label>Question: </label>
                        <input type="text" name="questions[]" required>
                        <label>Correct Answers (comma separated): </label>
                        <input type="text" name="correct_option[${index}]" placeholder="e.g. answer1, answer2, answer3" required>`;
                case "identification":
                    return `
                        <h4>Identification</h4>
                        <label>Question: </label>
                        <input type="text" name="questions[]" required>
                        <label>Correct Answer: </label>
                        <input type="text" name="correct_option[${index}]" required>`;
                case "fill_in_the_blanks":
                    return `
                        <h4>Fill in the Blanks</h4>
                        <label>Question (use '_____' for the blank): </label>
                        <input type="text" name="questions[]" required>
                        <label>Correct Answer: </label>
                        <input type="text" name="correct_option[${index}]" required>`;
                case "drag_and_drop":
                    return `
                        <h4>Drag and Drop</h4>
                        <label for="question">Question:</label>
                        <input type="text" name="questions[]" required>
                        <br><br>
                        <label>Choices:</label>
                        <div class="answers-container" id="answers-container-${index}">
                            <div class="answer-wrapper">
                                <input type="radio" name="correct_answer[${index}]" value="0">
                                <input type="text" class="drag-input" name="answers[${index}][]" required placeholder="Answer 1">
                            </div>
                            <span class="add-answer" onclick="addAnswer(${index})">&#43;</span>
                            <span class="remove-answer" onclick="removeAnswer(${index})" style="display: none;">&#8722;</span>
                        </div>`;
                        case "matching_type":
    return `
        <h4>Matching Type</h4>
        <label>Question:</label>
        <input type="text" name="questions[]" required>
        <div class="matching-pairs-section">
            <label>Matching Pairs:</label>
            <div class="matching-pairs-container" id="matching-pairs-${index}">
                <div class="matching-pair">
                    <div class="pair-number">1</div>
                    <div class="matching-column">
                        <input type="text" name="left_items[${index}][]" required placeholder="Left item">
                    </div>
                    <div class="matching-column">
                        <input type="text" name="right_items[${index}][]" required placeholder="Right item">
                    </div>
                    <button type="button" class="remove-pair" onclick="removeMatchingPair(this, ${index})">
                        Remove
                    </button>
                </div>
            </div>
            <button type="button" class="add-pair" onclick="addMatchingPair(${index})">
                Add Another Pair
            </button>
        </div>`;
                                default:
                                    return '';
                            }
        }

        function addAnswer(questionIndex) {
            const answersContainer = document.getElementById(`answers-container-${questionIndex}`);

            if (!answersContainer) {
                console.error('Answers container not found for question:', questionIndex);
                return;
            }

            // Count existing answers
            const existingAnswers = answersContainer.querySelectorAll('.answer-wrapper');
            const newAnswerIndex = existingAnswers.length;

            // Create a new wrapper div for the answer
            const wrapper = document.createElement('div');
            wrapper.className = 'answer-wrapper';
            wrapper.innerHTML = `
                <input type="radio" name="correct_answer[${questionIndex}]" value="${newAnswerIndex}">
                <input type="text" class="drag-input" 
                       name="answers[${questionIndex}][]" required placeholder="Answer ${newAnswerIndex + 1}">
            `;

            // Add event listener to ensure only one radio can be selected
            const radioButton = wrapper.querySelector('input[type="radio"]');
            radioButton.addEventListener('change', function() {
                const allRadios = answersContainer.querySelectorAll('input[type="radio"]');
                allRadios.forEach(radio => {
                    if (radio !== this) {
                        radio.checked = false;
                    }
                });
            });

            // Insert the new wrapper before the add/remove buttons
            const addAnswerButton = answersContainer.querySelector('.add-answer');
            const removeAnswerButton = answersContainer.querySelector('.remove-answer');
            
            // Insert new answer before the buttons
            answersContainer.insertBefore(wrapper, addAnswerButton);

            // Show remove button
            if (removeAnswerButton) {
                removeAnswerButton.style.display = 'inline-block';
            }
        }

        function removeAnswer(questionIndex) {
            const answersContainer = document.getElementById(`answers-container-${questionIndex}`);

            if (!answersContainer) {
                console.error('Answers container not found for question:', questionIndex);
                return;
            }

            const wrappers = answersContainer.querySelectorAll('.answer-wrapper');
            const removeAnswerButton = answersContainer.querySelector('.remove-answer');

            // Ensure at least one answer remains
            if (wrappers.length > 1) {
                // Remove the last answer wrapper
                const lastWrapper = wrappers[wrappers.length - 1];
                lastWrapper.remove();

                // Hide remove button if only one answer remains
                if (wrappers.length - 1 === 1 && removeAnswerButton) {
                    removeAnswerButton.style.display = 'none';
                }

                // Reindex radio buttons and inputs
                const remainingWrappers = answersContainer.querySelectorAll('.answer-wrapper');
                remainingWrappers.forEach((wrapper, index) => {
                    const radio = wrapper.querySelector('input[type="radio"]');
                    const input = wrapper.querySelector('input[type="text"]');
                    
                    radio.value = index;
                    radio.name = `correct_answer[${questionIndex - 1}]`;
                    input.name = `answers[${questionIndex - 1}][]`;
                    input.placeholder = `Answer ${index + 1}`;

                    // Ensure only one radio can be selected
                    radio.addEventListener('change', function() {
                        const allRadios = answersContainer.querySelectorAll('input[type="radio"]');
                        allRadios.forEach(r => {
                            if (r !== this) {
                                r.checked = false;
                            }
                        });
                    });
                });
            }
        }

        function updateRadioValues(questionNum) {
            const answersContainer = document.getElementById(`answers-container-${questionNum}`);
            const radios = answersContainer.querySelectorAll('input[type="radio"]');
            
            radios.forEach((radio, index) => {
                radio.value = index;
            });
        }   

        function addMatchingPair(questionIndex) {
    const container = document.getElementById(`matching-pairs-${questionIndex}`);
    const pairCount = container.querySelectorAll('.matching-pair').length;
    
    const pairDiv = document.createElement('div');
    pairDiv.className = 'matching-pair';
    pairDiv.innerHTML = `
        <div class="pair-number">${pairCount + 1}</div>
        <div class="matching-column">
            <input type="text" name="left_items[${questionIndex}][]" required placeholder="Left item">
        </div>
        <div class="matching-column">
            <input type="text" name="right_items[${questionIndex}][]" required placeholder="Right item">
        </div>
        <button type="button" class="remove-pair" onclick="removeMatchingPair(this, ${questionIndex})">
            <i class="fas fa-trash"></i>
        </button>
    `;
    container.appendChild(pairDiv);
}

function removeMatchingPair(button, questionIndex) {
    const pairContainer = button.closest('.matching-pair');
    const pairsContainer = pairContainer.parentElement;
    
    if (pairsContainer.children.length > 1) {
        pairContainer.remove();
        
        // Update pair numbers after removal
        const pairs = pairsContainer.querySelectorAll('.matching-pair');
        pairs.forEach((pair, index) => {
            pair.querySelector('.pair-number').textContent = index + 1;
        });
    }
}

    // Validate form
    function validateForm() {
        // Get the quiz title input value and trim whitespace
        const title = document.querySelector('input[name="title"]').value.trim();

        // Get the timer input value
        const timer = document.querySelector('input[name="timer"]').value;

        // Select all elements with the class 'question'
        const questions = document.querySelectorAll('.question');
        
        // Validate title - must not be empty
        if (!title) {
            alert('Please enter a quiz title');
            return;
        }

        // Validate timer - must be a positive number
        if (!timer || timer < 1) {
            alert('Please enter a valid timer value');
            return;
        }

        // Check if there are any questions
        if (questions.length === 0) {
            alert('Please add at least one question');
            return;
        }

        // Check if all questions are completely filled out
        const allQuestionsFilled = Array.from(questions).every(questionDiv => {
            const inputs = questionDiv.querySelectorAll('input[type="text"]');
            return Array.from(inputs).every(input => input.value.trim() !== '');
        });

        // If not all questions are filled, show an alert
        if (!allQuestionsFilled) {
            alert('Please fill all questions and answers');
            return;
        }
        const allQuestionsValid = Array.from(questions).every((questionDiv, index) => {
            const questionType = questionDiv.querySelector('input[name="question_type[]"]').value;
            
            if (questionType === 'multiple_choice') {
                const checkedRadio = questionDiv.querySelector('input[type="radio"]:checked');
                if (!checkedRadio) {
                    alert(`Please select a correct answer for multiple choice question ${index + 1}`);
                    return false;
                }
            }
            // Other question type validations...
            
            return true;
        });

        if (!allQuestionsValid) {
            return false;
        }

        // If all validations pass, return true
        return true;
    }

document.getElementById('quiz-form').addEventListener('submit', function(e) {
    e.preventDefault();    

    if (!validateForm()) {
        return;
    }    

    const formData = new FormData(this);
    const subjectId = document.querySelector('input[name="subject_id"]').value;

    fetch('allZapped_saveQuiz.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            return response.text().then(text => {
                throw new Error('Network response was not ok');
            }); 
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            alert(data.message);
            window.location.href = `t_quizDash.php?subject_id=${subjectId}`;
        } else {
            alert('Error creating quiz: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
            // If it's a response error, log the response details
            if (error.response) {
                console.error('Response Error:', error.response);
                console.error('Response Status:', error.response.status);
                console.error('Response Data:', error.response.data);
                
                // Try to parse and log any error message from the server
                if (error.response.data) {
                    alert(error.response.data.message || 'An error occurred while saving the quiz.');
                }
            } else if (error.request) {
                console.error('Request Error:', error.request);
                alert('No response received from the server.');
            } else {
                console.error('Error Message:', error.message);
                alert('An unexpected error occurred: ' + error.message);
            }
    });
});
        
</script>
        
</body>
</html>