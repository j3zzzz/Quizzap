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
            font-family: Tilt Warp Regular;
            font-size: 22px;
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
            font-family: Tilt Warp Regular;
            font-size: 18px;
        }

        input[type=number] {
            width: 6%;
            border-radius: 10px;
            padding: 10px;
            border: 3px solid #B9B6B6;
            margin-right: 3%;
            font-family: Tilt Warp Regular;
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
            font-family: Tilt Warp Regular;
            background-color: #f0f0f0;
            cursor: pointer;
        }

        button {
            background-color: #fbbd08;
            color: white;
            border: none;
            padding: 12px 24px;
            font-family: Tilt Warp Regular;
            font-size: 16px;
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        button:hover {
            background-color: #F8B500;
        }

        .question {
            background-color: #fff5e1;
            border: 1px solid #f0c808;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            font-family: Tilt Warp Regular;
        }

        .question input[type=text] {
            margin-bottom: 10px;
        }

        h4 {
            color: #444;
            margin-bottom: 10px;
            font-family: Tilt Warp Regular;
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
            font-family: Tilt Warp Regular;
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

        const answerCounts = { 1: 1};

        function addQuestion() {
            const questType = document.getElementById("questType").value;
            answerCounts[questionCounter] = 1;
            const container = document.createElement('div');
            container.className = 'question';
            container.innerHTML = `
                <div class="question-header">
                    <div class="question-number">Question ${questionCounter}</div>
                    <button type="button" onclick="this.parentElement.parentElement.remove()" class="delete-btn">Delete</button>
                </div>
                <input type="hidden" name="question_type[]" value="${questType}">
                ${getQuestionTemplate(questType, questionCounter-1)}
            `;
            
            document.getElementById("questionsContainer").appendChild(container);
            questionCounter++;
        }

        function previewImages(input, previewId) {
            const previewContainer = document.getElementById(previewId);
            previewContainer.innerHTML = ''; // Clear previous previews

            if (input.files) {
                Array.from(input.files).forEach(file => {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        previewContainer.appendChild(img);
                    }
                    reader.readAsDataURL(file);
                });
            }
        }

        function getQuestionTemplate(questType, index) {
            switch (questType) {
                case "multiple_choice":
                    return `
                    <h4>Multiple Choice</h4>
                        <div class="question-container">
                        <label>Question: </label>
                        <input type="text" name="questions[]" required>
                            <input type="radio" name="correct[${questionCounter - 1}]" value="0" required>
                            <label for="answer-${questionCounter}-1">Answer 1 : </label>
                            <input type="text" id="answer-${questionCounter}-1" name="answers[${questionCounter - 1}][]" required>
                            
                            <input type="radio" name="correct[${questionCounter - 1}]" value="1">
                            <label for="answer-${questionCounter}-2">Answer 2 : </label>
                            <input type="text" id="answer-${questionCounter}-2" name="answers[${questionCounter - 1}][]" required>
                            
                            <input type="radio" name="correct[${questionCounter - 1}]" value="2">
                            <label for="answer-${questionCounter}-3">Answer 3 : </label>
                            <input type="text" id="answer-${questionCounter}-3" name="answers[${questionCounter - 1}][]" required>
                            
                            <input type="radio" name="correct[${questionCounter - 1}]" value="3">
                            <label for="answer-${questionCounter}-4">Answer 4 : </label>
                            <input type="text" id="answer-${questionCounter}-4  " name="answers[${questionCounter - 1}][]" required>
                        </div>`;
                case "true_or_false":
                    return `
                        <h4>True or False</h4>
                        <label>Question: </label>
                        <input type="text" name="questions[]" required>
                        <label>Correct Answer: </label>
                        <select name="correct_option[]">
                            <option value="True">True</option>
                            <option value="False">False</option>
                        </select>`;
                case "enumeration":
                    return `
                        <h4>Enumeration</h4>
                        <label>Question: </label>
                        <input type="text" name="questions[]" required>
                        <label>Correct Answers (comma separated): </label>
                        <input type="text" name="correct_option[]" placeholder="e.g. answer1, answer2, answer3">`;
                case "identification":
                    return `
                        <h4> </h4>
                        <label>Question: </label>
                        <input type="text" name="questions[]" required>
                        <label>Correct Answer: </label>
                        <input type="text" name="correct_option[]">`;
                case "fill_in_the_blanks":
                    return `
                        <h4>Fill in the Blanks</h4>
                        <label>Question (use '_____' for the blank): </label>
                        <input type="text" name="questions[]" required>
                        <label>Correct Answer: </label>
                        <input type="text" name="correct_option[]">`;
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
                        <div class="matching-type-container">
                            <div class="left-items-section">
                                <label>Left Items (Images):</label>
                                <div id="left-images-container-${index}">
                                    <div class="left-image-input">
                                        <input type="file" name="left_images[${index}][]" accept="image/*" 
                                               onchange="handleLeftImageUpload(this, ${index})" required>
                                    </div>
                                </div>
                                <button type="button" onclick="addLeftImageInput(${index})">Add More Images</button>
                            </div>

                            <div class="right-items-section">
                                <label>Right Items:</label>
                                <div id="right-items-container-${index}">
                                    <div class="right-item-input">
                                        <input type="text" name="right_items[${index}][]" placeholder="Matching Item" required>
                                    </div>
                                </div>
                                <button type="button" onclick="addRightItemInput(${index})">Add More Items</button>
                            </div>

                            <div class="matching-configuration">
                                <h4>Configure Matches</h4>
                                <div id="match-configuration-${index}"></div>
                            </div>
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

        // New functions for handling matching type
        function addLeftImageInput(questionIndex) {
            const container = document.getElementById(`left-images-container-${questionIndex}`);
            const newInput = document.createElement('div');
            newInput.className = 'left-image-input';
            newInput.innerHTML = `
                <input type="file" name="left_images[${questionIndex}][]" accept="image/*" 
                       onchange="handleLeftImageUpload(this, ${questionIndex})" required>
                <button type="button" onclick="this.parentElement.remove()">Remove</button>
            `;
            container.appendChild(newInput);
        }

        function addRightItemInput(questionIndex) {
            const container = document.getElementById(`right-items-container-${questionIndex}`);
            const newInput = document.createElement('div');
            newInput.className = 'right-item-input';
            newInput.innerHTML = `
                <input type="text" name="right_items[${questionIndex}][]" placeholder="Matching Item" required>
                <button type="button" onclick="this.parentElement.remove()">Remove</button>
            `;
            container.appendChild(newInput);
        }

        function handleLeftImageUpload(input, questionIndex) {
            const file = input.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    updateMatchConfiguration(questionIndex);
                };
                reader.readAsDataURL(file);
            }
        }

        function updateMatchConfiguration(questionIndex) {
            const leftImagesContainer = document.getElementById(`left-images-container-${questionIndex}`);
            const rightItemsContainer = document.getElementById(`right-items-container-${questionIndex}`);
            const matchConfigContainer = document.getElementById(`match-configuration-${questionIndex}`);

            // Clear previous configuration
            matchConfigContainer.innerHTML = '';

            const leftImages = leftImagesContainer.querySelectorAll('input[type="file"]');
            const rightItems = rightItemsContainer.querySelectorAll('input[type="text"]');

            if (leftImages.length > 0 && rightItems.length > 0) {
                leftImages.forEach((leftImage, index) => {
                    if (leftImage.files.length > 0) {
                        const matchRow = document.createElement('div');
                        matchRow.className = 'match-row';

                        // Display image preview
                        const imgPreview = document.createElement('img');
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            imgPreview.src = e.target.result;
                            imgPreview.style.width = '100px';
                            imgPreview.style.height = '100px';
                            imgPreview.style.objectFit = 'cover';
                        };
                        reader.readAsDataURL(leftImage.files[0]);

                        // Create dropdown to select matching right item
                        const matchSelect = document.createElement('select');
                        matchSelect.name = `matching_config[${questionIndex}][]`;
                        rightItems.forEach((rightItem, rightIndex) => {
                            const option = document.createElement('option');
                            option.value = rightIndex;
                            option.text = rightItem.value || `Item ${rightIndex + 1}`;
                            matchSelect.appendChild(option);
                        });

                        matchRow.appendChild(imgPreview);
                        matchRow.appendChild(matchSelect);
                        matchConfigContainer.appendChild(matchRow);
                    }
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
            throw new Error('Network response was not ok');
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