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
    $quiz_type = "Matching Type";
    
    // Prepare quiz insertion
    $stmt = $conn->prepare("INSERT INTO quizzes (subject_id, title, timer, quiz_type) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $subject_id, $quiz_title, $timer, $quiz_type);
    
    if ($stmt->execute()) {
        $quiz_id = $stmt->insert_id;
        $stmt->close();
        
        // Process each question
        foreach ($_POST['questions'] as $index => $question_text) {
            // Prepare question insertion
            $stmt = $conn->prepare("INSERT INTO questions (quiz_id, question_text) VALUES (?, ?)");
            $stmt->bind_param("is", $quiz_id, $question_text);
            
            if ($stmt->execute()) {
                $question_id = $stmt->insert_id;
                
                $left_images = [];
                // Correct image upload handling
                if (isset($_FILES['left_images']['tmp_name'][$index])) {
                    foreach ($_FILES['left_images']['tmp_name'][$index] as $key => $tmp_name) {
                        if (!empty($tmp_name)) {
                            $original_name = $_FILES['left_images']['name'][$index][$key];
                            $file_name = uniqid() . '_' . $original_name;
                            $upload_path = 'uploads/left_images/' . $file_name;
                            
                            // Ensure upload directory exists
                            if (!is_dir('uploads/left_images')) {
                                mkdir('uploads/left_images', 0777, true);
                            }
                            
                            if (move_uploaded_file($tmp_name, $upload_path)) {
                                $left_images[] = $file_name;
                            } else {
                                throw new Exception("Failed to upload left image: " . $original_name);
                            }
                        }
                    }
                }
                
                // Process right items
                $right_items = [];
                if (isset($_POST['right_items'][$index])) {
                    foreach ($_POST['right_items'][$index] as $right_item) {
                        if (!empty($right_item)) {
                            $right_items[] = $right_item;
                        }
                    }
                }
                
                // Process matching configuration
                $matching_config = [];
                if (isset($_POST['matching_config'][$index])) {
                    foreach ($_POST['matching_config'][$index] as $key => $match_index) {
                        // Ensure the match index is valid
                        if (isset($right_items[$match_index])) {
                            $matching_config[$key] = $right_items[$match_index];
                        }
                    }
                }
                
                // Encode paths and configurations
                $left_items_json = json_encode($left_images);
                $right_items_json = json_encode($right_items);
                $matching_config_json = json_encode($matching_config);
                
                // Update question with matching details
                $stmt = $conn->prepare("UPDATE questions SET 
                    left_items = ?, 
                    right_items = ?, 
                    matching_config = ? 
                    WHERE question_id = ?");
                $stmt->bind_param("sssi", 
                    $left_items_json, 
                    $right_items_json, 
                    $matching_config_json, 
                    $question_id
                );

                if (!$stmt->execute()) {
                    throw new Exception("Error saving matching question details: " . $stmt->error);
                }
                                
                // Store answer for verification
                $stmt = $conn->prepare("INSERT INTO answers (question_id, answer_text, is_correct) VALUES (?, ?, 1)");
                $stmt->bind_param("is", $question_id, $matching_config_json);
                $stmt->execute();
                
                $stmt->close();
            } else {
                throw new Exception("Error inserting question: " . $stmt->error);
            }
        }
        
        $response["success"] = true;
        $response["message"] = "Quiz created successfully.";
        $response["subject_id"] = $subject_id;
    } else {
        $response["message"] = "Error: " . $stmt->error;
    }
    
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
    <title>Create Matching Quiz</title>
    <style>
        .question-count {
            text-align: center;
            margin-bottom: 15px;
            font-size: 18px;
            color: #f8b500;
        }

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

        input[type=file]{
            top: 5%;
            margin-right: 3%;
            font-family: Tilt Warp Regular;
        }

        .choose-file::-webkit-file-upload-button {
            visibility: hidden;
            width: 0;
        }

        .choose-file::before {
            content: 'Choose a CSV File';
            display: inline-block;
            color: #f8b500;
            background-color: whitesmoke;
            border: 2px solid #f8b500;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
        }

        .question {
            margin-bottom: 20px;
            padding: 40px;
            background-color: #fff5e1;
            border: 1px solid #f0c808;
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
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            margin-left: 3%;
        }

        .question-number-button {
            width: 40px;
            height: 40px;
            font-family: Tilt Warp Regular;
            font-size: 18px;
            color: white;
            cursor: pointer;
            align-items: center;
            justify-content: center;
            margin-left: 10px;
            display: flex;
            border: 2px solid #f8b500;
            border-radius: 50%;
            
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

        select{
            position: absolute;
            width: 30%;
            padding: 5px;
            border-radius: 10px;
            border: 3px solid #B9B6B6;
            font-family: Tilt Warp Regular;
            background-color: #f0f0f0;
            cursor: pointer;
            left: 50%;
            top: 50%;
        }

        .matching-configuration{
            background: white;
            border: 2px solid #f8b500;
            border-radius: 10px;
            padding: 20px;
            font-family: Tilt Warp Regular;
        }

        h4{
            font-weight: lighter;
            font-size: 20px;
        }

        .match-row{
            position: relative;
        }

        .left-items-section{
            background: white;
            border: 2px solid #f8b500;
            border-radius: 10px;
            padding: 20px;
            font-family: Tilt Warp Regular;
        }

        .left-image-input{
            padding: 10px;
        }

        .right-items-section{
            background: white;
            border: 2px solid #f8b500;
            border-radius: 10px;
            padding: 20px;
            font-family: Tilt Warp Regular;
        }

        .right-item-input{
            padding: 10px;
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
        
        .error-message {
            color: #ff4444;
            font-size: 0.9em;
            margin-top: 5px;
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

    </style>
</head>
<body>
    <header>
        <div class="logo"><img src="img/logo1.png" width="200px" height="80px"></div>
        <div class="actions">
            <div class="profile"><img src="img/default.png" width="50px" height="50px"></div>
        </div>
    </header>

    <h1>Create Matching Quiz</h1> 
    
    <div class="create-q-cont">
        
        <form id="quiz-form" method="post" enctype="multipart/form-data" action="">
            <input type="hidden" name="subject_id" value="<?php echo htmlspecialchars($subject_id); ?>">
            <label for="title">Quiz Title:</label>
            <input type="text" id="title" name="title" required>
            
            <label for="timer" id="timer-label">Timer (in minutes):</label>
            <input type="number" id="timer" name="timer" required><br><br><br>

            <div id="questions">
                <div class="question active" data-question="1">
                    <label>Question 1:</label>
                    <input type="text" name="questions[]" required><br><br>
                    
                    <div class="matching-type-container">
                        <div class="left-items-section">
                            <label>Left Items (Images):</label>
                            <div id="left-images-container-1">
                                <div class="left-image-input">
                                    <input type="file" name="left_images[0][]" accept="image/*" class="choose-file" required>
                                    <button type="button" onclick="this.parentElement.remove()">Remove</button><br>
                                </div>
                            </div><br>
                            <button type="button" onclick="addLeftImageInput(1)">Add More Images</button>
                        </div><br>

                        <div class="right-items-section">
                            <label>Right Items:</label>
                            <div id="right-items-container-1">
                                <div class="right-item-input">
                                    <input type="text" name="right_items[0][]" placeholder="Matching Item 1" required><br><br>
                            
                                </div>
                            </div><br>
                            <button type="button" onclick="addRightItemInput(1)">Add More Items</button>
                        </div>
                    </div><br>

                    <div class="matching-configuration">
                        <h4>Configure Matches</h4><br>
                        <div id="match-configuration-1"></div>
                    </div>
                </div>
            </div>

            <div class="question-number-buttons" id="question-number-buttons">
                <button type="button" class="question-number-button active" onclick="showQuestion(1)">1</button>
                <span class="add-icon" onclick="addQuestion()">&#43;</span>
            </div>
            
            <input type="hidden" id="quiz_type" name="quiz_type" value="Matching Type">
            <button class="submit-btn" type="submit">Submit Quiz</button>    
        </form>
    </div>
    
    <script>
        let questionCount = 1;
        const answerCounts = { 1: 1};

//        function updateQuestionCount() {
//            document.getElementById('total-questions').textContent = questionCount;
//        }

        function addQuestion() {
            questionCount++;
            answerCounts[questionCount] = 1;
            console.log('Adding answer for question 1:', answerCounts);
            
            const questionsDiv = document.getElementById('questions');
            const questionNumberButtonsDiv = document.getElementById('question-number-buttons');
            
            // Create a new question input section
            const newQuestionDiv = document.createElement('div');
            newQuestionDiv.className = 'question';
            newQuestionDiv.setAttribute('data-question', questionCount);
            newQuestionDiv.style.display = 'none';
            newQuestionDiv.innerHTML = `
                <label>Question ${questionCount}:</label>
                <input type="text" name="questions[]" required><br><br>
                
                <div class="matching-type-container">
                    <div class="left-items-section">
                        <label>Left Items (Images):</label>
                        <div id="left-images-container-${questionCount}">
                            <div class="left-image-input">
                                <input type="file" name="left_images[${questionCount-1}][]" accept="image/*" class="choose-file" required>
                            </div>
                        </div>
                        <button type="button" onclick="addLeftImageInput(${questionCount})">Add More Images</button>
                    </div><br>

                    <div class="right-items-section">
                        <label>Right Items:</label>
                        <div id="right-items-container-${questionCount}">
                            <div class="right-item-input">
                                <input type="text" name="right_items[${questionCount-1}][]" placeholder="Matching Item 1" required><br>
                            </div>
                        </div>
                        <button type="button" onclick="addRightItemInput(${questionCount})">Add More Items</button>
                    </div><br>
                </div><br>

                <div class="matching-configuration">
                    <h4>Configure Matches</h4>
                    <div id="match-configuration-${questionCount}"></div>
                </div>
            `;
            questionsDiv.appendChild(newQuestionDiv);
            
            // Create a new question number button
            const newButton = document.createElement('button');
            newButton.type = 'button';
            newButton.className = 'question-number-button';
            newButton.textContent = questionCount;
            newButton.addEventListener('click', () => showQuestion(questionCount));

            questionNumberButtonsDiv.insertBefore(newButton, questionNumberButtonsDiv.querySelector('.add-icon'));
            
            // Update active question view and question count
//            showQuestion(questionCount);
//            updateQuestionCount();
        }

        // New functions for handling matching type
        function addLeftImageInput(questionIndex) {
            const container = document.getElementById(`left-images-container-${questionIndex}`);
            const newInput = document.createElement('div');
            newInput.className = 'left-image-input';
            newInput.innerHTML = `
                <input type="file" class="choose-file" name="left_images[${questionIndex}][]" accept="image/*" 
                    onchange="handleLeftImageUpload(this, ${questionIndex})" required>
                <button type="button" onclick="this.parentElement.remove()">Remove</button>
            `;
            container.appendChild(newInput);
        }

        function addRightItemInput(questionIndex) {
            answerCounts[questionIndex] = (answerCounts[questionIndex] || 1) + 1;
            const currentAnswerCount = answerCounts[questionIndex];
            console.log(`Adding answer ${currentAnswerCount} to question ${questionIndex}`);

            const container = document.getElementById(`right-items-container-${questionIndex}`);
            const newInput = document.createElement('div');
            newInput.className = 'right-item-input';
            newInput.innerHTML = `
                <input type="text" name="right_items[${questionIndex}][]" placeholder="Matching Item ${currentAnswerCount}" required>
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

        function showQuestion(questionNumber) {
            // Hide all questions
            document.querySelectorAll('.question').forEach(question => {
                question.style.display = 'none';
            });
            
            // Show the selected question
            const selectedQuestion = document.querySelector(`.question[data-question="${questionNumber}"]`);

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
    
    // Validate all questions are filled
    const allQuestionsFilled = Array.from(document.querySelectorAll('.question')).every(questionDiv => {
        const questionInputs = questionDiv.querySelectorAll('input[type="text"], input[type="file"]');
        return Array.from(questionInputs).every(input => 
            input.type === 'file' ? input.files.length > 0 : input.value.trim() !== ''
        );
    });

    if (!allQuestionsFilled) {
        alert('Please fill all questions, images, and matching items before submitting.');
        return;
    }
    
    // Manually create FormData to ensure all fields are included
    const formData = new FormData();
    
    // Add basic form fields
    formData.append('subject_id', document.querySelector('input[name="subject_id"]').value);
    formData.append('title', document.getElementById('title').value);
    formData.append('timer', document.getElementById('timer').value);
    formData.append('quiz_type', 'Matching Type');

    // Process each question
    document.querySelectorAll('.question').forEach((questionDiv, questionIndex) => {
        // Add question text
        const questionText = questionDiv.querySelector('input[type="text"]').value;
        formData.append(`questions[${questionIndex}]`, questionText);

        // Add left images
        const leftImagesContainer = questionDiv.querySelector(`#left-images-container-${questionIndex + 1}`);
        leftImagesContainer.querySelectorAll('input[type="file"]').forEach((fileInput, imageIndex) => {
            if (fileInput.files.length > 0) {
                formData.append(`left_images[${questionIndex}][]`, fileInput.files[0]);
            }
        });

        // Add right items
        const rightItemsContainer = questionDiv.querySelector(`#right-items-container-${questionIndex + 1}`);
        rightItemsContainer.querySelectorAll('input[type="text"]').forEach((itemInput, itemIndex) => {
            if (itemInput.value.trim() !== '') {
                formData.append(`right_items[${questionIndex}][]`, itemInput.value);
            }
        });

        // Add matching configuration
        const matchConfigContainer = questionDiv.querySelector(`#match-configuration-${questionIndex + 1}`);
        matchConfigContainer.querySelectorAll('select').forEach((select, matchIndex) => {
            formData.append(`matching_config[${questionIndex}][]`, select.value);
        });
    });
    
    // Send the form data
    fetch('', {
        method: 'POST',
        body: formData
    }).then(response => response.json()).then(data => {
        if (data.success) {
            alert(data.message);
            window.location.href = `t_quizDash.php?subject_id=${data.subject_id}`;
        } else {
            console.error('Error creating quiz: ' + data.message);
            alert('Failed to create quiz. Please try again.');
        }
    }).catch(error => {
        console.error('Error:', error);
        alert('An error occurred while creating the quiz.');
    });
});
    </script>
</body>
</html>