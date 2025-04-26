<?php
session_start();
if (strpos($_SESSION['account_number'], 'S') !== 0) {
    header("Location: login.php");
    exit();
}

// Check if the back button was clicked
$partialSubmit = isset($_SERVER['HTTP_REFERER']) && 
                (strpos($_SERVER['HTTP_REFERER'], $_SERVER['HTTP_HOST']) === false ||
                isset($_SERVER['HTTP_CACHE_CONTROL']) && $_SERVER['HTTP_CACHE_CONTROL'] === 'max-age=0');

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "rawrit";

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    // Output as JSON instead of HTML/script
    echo json_encode(["success" => false, "error" => "Connection failed: " . $conn->connect_error]);
    exit;
}

if (!isset($_GET['quiz_id'])) {
    // Output as JSON instead of HTML/script
    echo json_encode(["success" => false, "error" => "Quiz ID is not specified."]);
    exit;
}

$quiz_id = $_GET['quiz_id'];
$stmt = $conn->prepare("SELECT * FROM quizzes WHERE quiz_id = ?");
$stmt->bind_param("i", $quiz_id);
$stmt->execute();
$result = $stmt->get_result();
$quiz = $result->fetch_assoc();

if(!$quiz) {
    echo json_encode(["success" => false, "error" => "Quiz not found."]);
    }
$quiz_type = $quiz['quiz_type'];  // Assuming you have a quiz_type column

$subject_id = $quiz['subject_id'];

$sql = "SELECT * FROM questions WHERE quiz_id = $quiz_id";
$result = $conn->query($sql);

$questions = [];
while ($row = $result->fetch_assoc()) {
    $questions[] = $row;
}


$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" type="text/css" href="other resources/fontawesome-free-6.5.2-web/css/all.min.css">
    <title>Take Quiz</title>
    
    <style type="text/css">
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

        nav p{
            font-family: Purple Smile;
            color: white;
            font-size: 30px;
            margin-right: 30px;
        }


        p{
            font-size: 30px;
            font-family: Tilt Warp;
            color: white;
        }


        h1 {
            font-family: Tilt Warp;
            width: fit-content;
            letter-spacing: 2px;
        }

        .quiz-cont {
            background-color: #FFFFFF;
            font-family: Tilt Warp Regular;
            color: #f8b500;
            margin-top: 2%;
            margin-bottom: 5%;
            margin-left: auto;
            margin-right: auto;
            padding: 50px 50px;
            width: 90%;
            height: 100%;
            border: 2px solid #f8b500;
            border-radius: 10px;
            box-shadow: 4px 4px 0 0 #BC8900;
        }

        .question-info {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 5%;
            margin-bottom: 15px;
            margin-left: 40px;
        }


        #question-text {
            font-family: Tilt Warp Regular;
            letter-spacing: 2px;
            width: none;
            flex-grow: 1;
            color: black;
        }

        #question-number {
            font-family: Tilt Warp Regular;
            font-size: 28px;
            color: black;
        }

        .timer {
            background-color: white;
            font-family: Tilt Warp Regular;
            font-size: 20px;
            color: #707070;
            padding: 10px;
            width: 8%;
            margin-top: -5%;
            float: right;
            border-radius: 5px;
            text-align: center;
            vertical-align: middle;
            align-content: center;
            border: 2px solid #f8b500;
        }

        #answers {
            width: 95%;
            margin-left: 6%;
        }

        .answer-button {
            font-family: Tilt Warp Regular;
            background-color: white;
            border-radius: 10px;
            display: inline-block;
            padding: 5px 20px;
            margin: 15px 15px 15px 25px;
            border: 2px solid #f8b500;
            border-radius: 20px;
            cursor: pointer;
            width: 42%;
            height: 50px;
            color: #f8b500;
            font-weight: bolder;
            font-size: 23px;
            letter-spacing: 1px;
            text-align: center;
            box-sizing: border-box;
            box-shadow: 0 5px 0 0 #BC8900;
        }

        .answer-button:hover {
              background-color: #f8b500;
              color: #ffffff;
        }

        .answer-button.selected {
              background-color: #f8b500;
              color: white;
        }

        .answer-input {
            width: 100%;
            padding: 10px;
            border-radius: 15px;
            font-family: Tilt Warp Regular;
            font-size: 18px;
            border: 2px solid #B9B6B6;
        }

        .fa-circle-arrow-right {
            float: right;
            font-size: 30px;
        }

        .fa-circle-arrow-left {
            float: left;
            font-size: 30px;
        }

        #tts {
            margin-top: 1%;
            position: absolute;
            font-size: 25px;
            cursor: pointer;
            padding: 4px 5px;
            background-color: transparent;
            transition: 0.3s;
        }

        #tts:hover {
            background-color: #f8b500;
            color: white;
            border-radius: 5px;
        }

        .speaker .speaker-tooltip {
            visibility: hidden;
            width: 120px;
            background-color: #f8b500;
            text-align: center;
            border-radius: 6px;
            padding: 5px 0;
            position: absolute;
            z-index: 1;
            top: 37%;
            left: 12%;
            color: white;
        }

        .speaker .speaker-tooltip::after {
            content: "";
            position: absolute;
            top: 50%;
            right: 100%;
            margin-top: -5%;
            border-width: 5px;
            border-style: solid;
            border-color: transparent #f8b500 transparent transparent;
        }

        .speaker:hover .speaker-tooltip {
            visibility: visible;
        }

        .question-btn {
            font-family: Tilt Warp Regular;
            font-size: 18px;
            margin: 0 5px;
            padding: 5px 10px;
            border: 2px solid #f8b500;
            border-radius: 50%;
            width: 35px;
            text-align: center;
            background-color: white;
            color: #f8b500;
        }
          
        .question-btn-container {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: 10px;
        }

        .answered {
            background-color: #f8b500;
            color: white;
        }

        .question-drag-container {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 20px;
        }

        .drop-zone {
            width: 200px;
            height: 100px;
            border: 2px dashed #f8b500;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #fff;
            transition: all 0.3s ease;
            margin-left: 20px;
        }

        .drop-zone.dragover {
            background-color: rgba(248, 181, 0, 0.1);
            border-style: solid;
        }

        .drop-zone.dropped {
            border-style: solid;
            background-color: #fff;
            color: #000;
        }

        .drop-zone-prompt {
            color: #999;
            font-size: 16px;
            font-family: Tilt Warp Regular;
        }

        .choices-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 20px;
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 10px;
            border: 1px solid #a4a4a4ed;
            margin-right: 5%;
        }

        .draggable {
            padding: 10px 20px;
            background-color: white;
            border: 2px solid #f8b500;
            border-radius: 8px;
            cursor: move;
            font-family: Tilt Warp Regular;
            color: #f8b500;
            transition: all 0.3s ease;
            box-shadow: 0 2px 0 0 #BC8900;
        }

        .draggable:hover {
            background-color: #f8b500;
            color: white;
        }

        .draggable.dragging {
            opacity: 0.5;
            transform: scale(0.95);
        }      

        .matching-container {
            padding: 5%;
            display: flex;
            gap: 20px;
            border: 2px solid #f8b500;
            border-radius: 5px;
            background-color: #fff6e6;
        }

        .matching-image {
            max-width: 100%;
            height: auto;
            display: block;
            margin: 10px 0;
        }

        .matching-left-side {
            display: flex;
            flex-direction: column;
            gap: 10px;
            width: 50%;
        }

        .matching-right-side {
            display: flex;
            flex-direction: column;
            gap: 10px;
            width: 50%;
        }

        .matching-left-item {
            padding: 10px;
            border-radius: 8px;
            text-align: center;
        }

        .matching-image {
            max-width: 300px;
            max-height: 300px;
            object-fit: contain;
        }

        .matching-select {
            margin-top: 6%;
            margin-bottom: 35%;
            width: 100%;
            padding: 10px;
            border: 2px solid #f8b500;
            border-radius: 8px;
            font-family: 'Tilt Warp Regular';
        }  

        .submit-btn {
            background-color: #f8b500;
            color: white;
            width: 100%;
            border-radius: 10px;
            border:none;
            padding: 10px;
            font-size: 18px;
            font-family: Tilt Warp Regular;
            margin-top: 2%;
            width: 60%;
            box-shadow: 0 6px 0 0 #BC8900;
        }

        .submit-btn:hover {
            -ms-transform: scale(1.5); /* IE 9 */
            -webkit-transform: scale(1.5); /* Safari 3-8 */
            transform: scale(1.1); 
            transition: transform .2s;
            box-shadow: 0 4px 0 0 #BC8900;
        }

        .submit-btn:active {
            background-color: #A34404;  
            transform: translateY(4px);
        }

        .submit-btn:active {
            background-color: #A34404;
            box-shadow: 5px 6px 0 0 rgba(0, 0, 0, 0.3);
        } 
    </style>


</head>
<body>

<header>
    <div class="logo"><img src="img/logo1.png" onclick="window.location.href='s_Home.php';" style="cursor: pointer;" width="200px" height="80px"></div>
    <div class="actions">
        <div class="profile"><img src="img/default.png" width="50px" height="50px"></div>
    </div>
</header>

<div class="quiz-cont">
    <div id="quiz-header">
        <h1><?php echo htmlspecialchars($quiz['title']); ?></h1> 
        <div class="speaker">
            <span><i class="fa-solid fa-volume-high"  id="tts"></i></span>
            <span class="speaker-tooltip">Read Aloud</span>
        </div>
        <div id="timer" class="timer"></div>
    </div><br>

    <div id="question">
        <div class="question-info"> 
            <p id="question-number"></p>
            <p id="question-text"></p>
        </div>
        <div id="answers"></div>
    </div><br>

    <div class="question-btn-container">
        <i class="fa-solid fa-circle-arrow-left" onclick="previousQuestion()"></i>
        <div id="question-buttons"></div>
        <i class="fa-solid fa-circle-arrow-right" onclick="nextQuestion()"></i>
    </div>
    <center>
    <button onclick="submitQuiz()" class="submit-btn">Submit Quiz</button>
    </center>
</div>

<script>
        let currentQuestion = 0;
        const questions = <?php echo json_encode($questions); ?>;
        const quizType = <?php echo json_encode($quiz_type); ?>; 
        const userAnswers = {};
        const timerDuration = <?php echo $quiz['timer'] * 60; ?>;
        let timer;
        const partialSubmit = <?php echo json_encode($partialSubmit); ?>;

        var tts = document.querySelector('#tts');
        var synth = window.speechSynthesis;
        var voices = [];
        var defaultVoice = "Microsoft David - English (United States)"; // Set the default voice name

        PopulateVoices();
        if(speechSynthesis !== undefined){
            speechSynthesis.onvoiceschanged = PopulateVoices;
        }

        tts.addEventListener('click', ()=> {
            var questionText = document.getElementById('question-text').innerText;
            var questionNumber = document.getElementById('question-number').innerText;

            var questionNumSpeech = new SpeechSynthesisUtterance(`Question Number ${questionNumber}`);

            voices.forEach((voice)=> {
                if (voice.name === defaultVoice) {
                    questionNumSpeech.voice = voice;
                }
            });

            synth.speak(questionNumSpeech);

            var questionTextSpeech = new SpeechSynthesisUtterance(questionText);
            // Set the default voice if available
            voices.forEach((voice)=>{
                if(voice.name === defaultVoice){
                    questionTextSpeech.voice = voice;
                }
            });
            synth.speak(questionTextSpeech);

        var answers = document.querySelectorAll('#answers');
        answers.forEach((answers) => {
            var answerText = answers.innerText;
            var toSpeakAnswer = new SpeechSynthesisUtterance(answerText);
            voices.forEach((voice) => {
                if (voice.name === defaultVoice) {
                    toSpeakAnswer.voice = voice;
                }
            });
            synth.speak(toSpeakAnswer);
        });
    });

        function PopulateVoices(){
            voices = synth.getVoices();
        }

        //para macheck yung mga type written na answers regardless sa sagot
        function compareAnswersCaseInsensitive(userAnswer, correctAnswer) {
            // Trim whitespace
            const trimmedUserAnswer = userAnswer.trim();
            const trimmedCorrectAnswer = correctAnswer.trim();

            // If the lengths are different, it's not a match
            if (trimmedUserAnswer.length !== trimmedCorrectAnswer.length) {
                return false;
            }

            // Compare character by character (case-insensitive)
            return trimmedUserAnswer.toLowerCase() === trimmedCorrectAnswer.toLowerCase();
        }

        // When checking answers during quiz submission or grading
        function checkAnswer(userAnswer, correctAnswer, quizType) {
            if (quizType === 'Enumeration' || quizType === 'Identification') {
                // Split multiple answers if needed (for Enumeration type)
                if (quizType === 'Enumeration') {
                    const userAnswers = userAnswer.split(',').map(answer => answer.trim());
                    const correctAnswers = correctAnswer.split(',').map(answer => answer.trim());
                    
                    // Check if all user answers match correct answers (case-insensitive)
                    return userAnswers.length === correctAnswers.length && 
                        userAnswers.every((answer, index) => 
                            compareAnswersCaseInsensitive(answer, correctAnswers[index])
                        );
                }
                
                // For Identification type, simple case-insensitive comparison
                return compareAnswersCaseInsensitive(userAnswer, correctAnswer);
            }
            
            // Handle other quiz types as needed
            return false;
        }

        function showQuestion(index) {
            if (index >= 0 && index < questions.length) {
                currentQuestion = index;
                document.getElementById('question-number').innerText = `${index + 1}.  `;
                document.getElementById('question-text').innerText = questions[index].question_text;

                fetch('s_get_answers.php?question_id=' + questions[index].question_id)
                .then(response => response.json())
                .then(data => {  
                    const answersDiv = document.getElementById('answers');
                    answersDiv.innerHTML = '';

                if (quizType === 'True or False') {
                    // Render True/False buttons
                    ['True', 'False'].forEach((answerText, i) => {
                            const answerButton = document.createElement('button');
                            answerButton.innerText = answerText;
                            answerButton.className = 'answer-button';

                            answerButton.onclick = function() {
                                const answerId = data[i].answer_id;
                                saveAnswer(questions[index].question_id, answerId);
                                document.querySelectorAll('.answer-button').forEach(btn => btn.classList.remove('selected'));
                                answerButton.classList.add('selected');
                                document.getElementById(`question-btn-${index}`).classList.add('answered');
                            };
                            answersDiv.appendChild(answerButton);
                        });    
                } else if (quizType === 'Drag & Drop') {
                    const questionContainer = document.createElement('div');
                    questionContainer.className = 'question-drag-container';

                    const dropZone = document.createElement('div');
                    dropZone.className = 'drop-zone';
                    dropZone.innerHTML = '<span class = "drop-zone-prompt">Drop answer here!</span>';
                    dropZone.setAttribute('data-question-id', questions[index].question_id);

                    const choicesContainer = document.createElement('div');
                    choicesContainer.className = 'choices-container';

                    data.forEach((answer, i ) => {
                        const draggable = document.createElement('div');
                        draggable.className = 'draggable';
                        draggable.setAttribute('draggable', 'true');
                        draggable.setAttribute('data-answer-id', answer.answer_id);
                        draggable.textContent = answer.answer_text;
                        // Add drag event listeners
                        draggable.addEventListener('dragstart', handleDragStart);
                        draggable.addEventListener('dragend', handleDragEnd);
                        choicesContainer.appendChild(draggable);
                    });

                    dropZone.addEventListener('dragover', handleDragOver);
                    dropZone.addEventListener('drop', handleDrop);
                    dropZone.addEventListener('dragenter', handleDragEnter);
                    dropZone.addEventListener('dragleave', handleDragLeave);
                    
                    // Append elements
                    questionContainer.appendChild(dropZone);
                    answersDiv.appendChild(questionContainer);
                    answersDiv.appendChild(choicesContainer);
                    
                    // If there's a saved answer, show it in the drop zone
                    if (userAnswers[questions[index].question_id]) {
                        const savedAnswer = data.find(a => a.answer_id === userAnswers[questions[index].question_id]);
                        if (savedAnswer) {
                            dropZone.innerHTML = savedAnswer.answer_text;
                            dropZone.classList.add('dropped');
                        }
                    }
                } else if (quizType === 'Enumeration') {
                    // Render input field for enumeration type
                    const answerInput = document.createElement('input');
                    answerInput.type = 'text';
                    answerInput.className = 'answer-input';
                    answerInput.placeholder = 'Enter your answers separated by commas';
                    answerInput.oninput = function() {
                        saveAnswer(questions[index].question_id, answerInput.value.trim());
                        document.getElementById(`question-btn-${index}`).classList.add('answered');
                    };
                    answersDiv.appendChild(answerInput);

                    // If there is a saved answer, show it in the input field
                    if (userAnswers[questions[index].question_id]) {
                        answerInput.value = userAnswers[questions[index].question_id];
                    }
                } else if (quizType === 'Matching Type') {
                    // Parse the left_items, right_items, and matching_config from the current question
                    const leftItems = JSON.parse(questions[index].left_items || '[]');
                    const rightItems = JSON.parse(questions[index].right_items || '[]');

                    const matchingContainer = document.createElement('div');
                    matchingContainer.className = 'matching-container';

                    // Create left side container for items to match
                    const leftSideContainer = document.createElement('div');
                    leftSideContainer.className = 'matching-left-side';

                    // Display all left items as images
                    leftItems.forEach((item, i) => {
                        const leftItemDiv = document.createElement('div');
                        leftItemDiv.className = 'matching-left-item';

                        const img = document.createElement('img');
                        const imagePath = 'uploads/left_images/' + item;

                        console.log(`Image source: ${imagePath}`); // Log for debugging
                        
                        img.src = imagePath;
                        img.alt = `Left Item ${i + 1}`;
                        img.className = 'matching-image';

                        // Error handling for image loading
                        img.onerror = function() {
                            console.error(`Failed to load image: ${this.src}`);
                            this.alt = 'Image not available'; // Provide alt text if the image fails
                            this.src = ''; // Optionally set a fallback or empty source
                        };

                        leftItemDiv.appendChild(img);

                        // Add a unique identifier to match with right items
                        leftItemDiv.setAttribute('data-left-item-index', i);
                        leftSideContainer.appendChild(leftItemDiv);
                    });

                    // Create right side container for selection
                    const rightSideContainer = document.createElement('div');
                    rightSideContainer.className = 'matching-right-side';

                    // Ensure ALL left items have a selection option
                    leftItems.forEach((leftItem, i) => {
                        const rightSelectContainer = document.createElement('div');
                        rightSelectContainer.className = 'matching-right-select-container';

                        const select = document.createElement('select');
                        select.className = 'matching-select';
                        select.setAttribute('data-left-item-index', i);

                        // Add a default "Select" option
                        const defaultOption = document.createElement('option');
                        defaultOption.value = '';
                        defaultOption.textContent = 'Select';
                        select.appendChild(defaultOption);

                        // Populate select options with right items
                        rightItems.forEach((rightItem, j) => {
                            const option = document.createElement('option');
                            option.value = j;
                            option.textContent = rightItem;
                            select.appendChild(option);
                        });

                        // Event listener to save the matching answer
                        select.addEventListener('change', function() {
                            const leftItemIndex = this.getAttribute('data-left-item-index');

                            // Get the existing answers or initialize an empty object
                            const existingAnswers = userAnswers[questions[index].question_id] 
                                ? JSON.parse(userAnswers[questions[index].question_id]) 
                                : {};

                            // Only save if an option is selected (not the default "Select")
                            if (this.value !== '') {
                                // Update the answer for this specific item
                                existingAnswers[`Item ${parseInt(leftItemIndex) + 1}`] = this.options[this.selectedIndex].text;

                                // Save the updated answers
                                saveAnswer(questions[index].question_id, JSON.stringify(existingAnswers));

                                // Mark the question as answered
                                document.getElementById(`question-btn-${index}`).classList.add('answered');
                            }
                        });

                        // For restoring answers
                        if (userAnswers[questions[index].question_id]) {
                            const savedAnswers = JSON.parse(userAnswers[questions[index].question_id]);

                            leftItems.forEach((leftItem, i) => {
                                const itemKey = `Item ${i + 1}`;

                                if (savedAnswers[itemKey]) {
                                    const select = rightSideContainer.querySelector(`select[data-left-item-index="${i}"]`);

                                    // Find and set the option that matches the saved answer text
                                    for (let j = 0; j < select.options.length; j++) {
                                        if (select.options[j].text === savedAnswers[itemKey]) {
                                            select.selectedIndex = j;
                                            break;
                                        }
                                    }
                                }
                            });
                        }

                        rightSelectContainer.appendChild(select);
                        rightSideContainer.appendChild(rightSelectContainer);
                    });

                    // Combine left and right containers
                    matchingContainer.appendChild(leftSideContainer);
                    matchingContainer.appendChild(rightSideContainer);

                    // Append to the answers div
                    answersDiv.appendChild(matchingContainer);
                } else if (quizType === 'Identification') {
                    // Render input field for identification type
                    const answerInput = document.createElement('input');
                    answerInput.type = 'text';
                    answerInput.className = 'answer-input';
                    answerInput.placeholder = 'Enter your answers';
                    answerInput.oninput = function() {
                        saveAnswer(questions[index].question_id, answerInput.value.trim());
                        document.getElementById(`question-btn-${index}`).classList.add('answered');
                    };
                    answersDiv.appendChild(answerInput);

                    // If there is a saved answer, show it in the input field
                    if (userAnswers[questions[index].question_id]) {
                        answerInput.value = userAnswers[questions[index].question_id];
                    }
                } else if (quizType === 'Fill in the Blanks') {
                    // Handle fill-in-the-blank type
                    const fillInput = document.createElement('input');
                    fillInput.type = 'text';
                    fillInput.className = 'answer-input';
                    fillInput.placeholder = 'Enter your answer here';
                    fillInput.oninput = function() {
                        saveAnswer(questions[index].question_id, fillInput.value.trim());
                        document.getElementById(`question-btn-${index}`).classList.add('answered');
                    };
                    answersDiv.appendChild(fillInput);

                    if (userAnswers[questions[index].question_id]) {
                        fillInput.value = userAnswers[questions[index].question_id];
                    }
                } else {
                    const labels = ['A', 'B', 'C', 'D'];
                    data.forEach((answer , i) => {
                        const answerButton = document.createElement('button');
                        answerButton.innerText = `${labels[i]}. ${answer.answer_text}`;
                        answerButton.className = 'answer-button';
                        answerButton.onclick = function() {
                            saveAnswer(questions[index].question_id, answer.answer_id);
                            document.querySelectorAll('.answer-button').forEach(btn => btn.classList.remove('selected'));
                            answerButton.classList.add('selected');
                            document.getElementById(`question-btn-${index}`).classList.add('answered');
                        };
                        answersDiv.appendChild(answerButton);
                    });
                    
                }

                });
            }
        }

        let draggingElement = null;

        function handleDragStart(e) {
            this.classList.add('dragging');
            draggingElement = this;
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', this.getAttribute('data-answer-id'));
        }

        function handleDragEnd(e) {
            this.classList.remove('dragging');
            draggingElement = null;
        }

        function handleDragOver(e) {
            if (e.preventDefault) {
                e.preventDefault();
            }
            e.dataTransfer.dropEffect = 'move';
            return false;
        }

        function handleDragEnter(e) {
            this.classList.add('dragover');
        }

        function handleDragLeave(e) {
            this.classList.remove('dragover');
        }

        function handleDrop(e) {
            e.preventDefault();
            this.classList.remove('dragover');
            
            if (draggingElement) {
                const answerId = draggingElement.getAttribute('data-answer-id');
                const questionId = this.getAttribute('data-question-id');
                
                // Save the answer
                saveAnswer(questionId, answerId);
                
                // Update drop zone appearance
                this.innerHTML = draggingElement.textContent;
                this.classList.add('dropped');
                
                // Mark question as answered
                document.getElementById(`question-btn-${currentQuestion}`).classList.add('answered');
            }
            
            return false;
        }

        function saveAnswer(questionId, answerId) {
            userAnswers[questionId] = answerId;
        }

        function nextQuestion() {
            if (currentQuestion < questions.length - 1) {
            showQuestion(currentQuestion + 1);
            }
        }

        function previousQuestion() {
            if (currentQuestion > 0) {
            showQuestion(currentQuestion - 1);
            }
        }

        // Enhanced back button detection and automatic submission
        function setupBackButtonDetection() {

            let isSubmitting = false;

            // Detect back button or page navigation
            window.addEventListener('popstate', function(event) {
                if (!window.isSubmitting && Object.keys(userAnswers).length > 0 && !window.isIntentionalSubmit) {
                    event.preventDefault();
                    handleUnloadWithAnswers();
                }
            });

            // Detect browser page hide/show events
            window.addEventListener('pageshow', function(event) {
                if ((event.persisted ||
                    (window.performance && 
                    window.performance.navigation.type === window.performance.navigation.TYPE_BACK_FORWARD)) && !window.isSubmitting &&Object.keys(userAnswers).length > 0 && !window.isIntentionalSubmit) {
                     
                    handleUnloadWithAnswers();
                }
            });

            // Handle page visibility changes
            document.addEventListener('visibilitychange', function() {
                if (document.visibilityState === 'hidden' && 
                    !window.isSubmitting &&
                    Object.keys(userAnswers).length > 0 && !window.isIntentionalSubmit) {
                    
                    handleUnloadWithAnswers();
                }
            });

            // Add beforeunload event to catch navigation attempts
            window.addEventListener('beforeunload', function(event) {
                if (!window.isSubmitting && Object.keys(userAnswers).length > 0 && !window.isIntentionalSubmit) {
                    
                    console.log('Page about to unload with answers');
                    submitQuiz(true);

                    event.preventDefault();
                    event.returnValue = '';
                    return 'You have unsaved answers. Are you sure you want to leave?';
                }
            });
        }    

        function handleUnloadWithAnswers() {
            // Prevent multiple submissions
            if (window.isSubmitting) return;

            // Confirm with user before submitting
            const confirmSubmit = confirm('You have unsaved answers. Do you want to submit the quiz?');
            
            if (confirmSubmit) {
                window.isSubmitting = true;
                submitQuiz(true);
            }
        }

        function submitQuiz(isPartialSubmit = false) {
            // If no answers and not explicitly marked for partial submit, skip submission
            if (Object.keys(userAnswers).length === 0 && !isPartialSubmit) {
                return;
            }

            // Prevent multiple submissions
            if (window.isSubmitting) {
                return;
            }

            window.isIntentionalSubmit = !isPartialSubmit;
            window.isSubmitting = true;

            fetch('s_submit_quiz.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ 
                    answers: userAnswers, 
                    quiz_id: <?php echo $quiz_id; ?>,
                    partial_submit: isPartialSubmit
                 })
            })
            .then(response => response.json())    
            .then(data => {
                if (data.success) {
                    const queryParams = new URLSearchParams({
                        score: data.score,
                        total: data.total,
                        quiz_id: <?php echo $quiz_id; ?>,
                        wrong_answers: JSON.stringify(data.wrong_answers),
                        partial_submit: isPartialSubmit ? '1' : '0'
                    });
                    window.location.href = `quiz_result.php?${queryParams.toString()}`;
                } else {
                    alert('Error submitting quiz.'); //+ data.error
                    window.location.href = 'select_quiz.php?subject_id=<?php echo $subject_id; ?>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('There was an error submitting your quiz. Please try again.');
                window.location.href = 'select_quiz.php?subject_id=<?php echo $subject_id; ?>';
            });   
        }

        function startTimer(duration) {
            let timer = duration;
            const timerElement = document.getElementById('timer');

            function updateTimer() {
                const minutes = parseInt(timer / 60, 10);
                const seconds = parseInt(timer % 60, 10);

                timerElement.textContent = 
                    (minutes < 10 ? "0" + minutes : minutes) +
                    ":" +
                    (seconds < 10 ? "0" + seconds : seconds);

                if (timer <= 0) {
                    timerElement.textContent = "00:00";
                    submitQuiz();
                    return;
                }
                
                timer--;
                setTimeout(updateTimer, 1000);
            }

            updateTimer();
        }

        function goToQuestion(index) {
            showQuestion(index);
        }

        window.onload = function() {
            showQuestion(0);
            startTimer(timerDuration);
            const questionButtonsDiv = document.getElementById('question-buttons');
            questions.forEach((_, index) => {
                const questionButton = document.createElement('button');
                questionButton.innerText = index + 1;
                questionButton.id = `question-btn-${index}`;
                questionButton.className = 'question-btn';
                questionButton.onclick = function() {
                    goToQuestion(index);
                };
                questionButtonsDiv.appendChild(questionButton);
            });
            
                // Reset flags
                window.isSubmitting = false;
                window.isIntentionalSubmit = false;

            setupBackButtonDetection();

            if (partialSubmit && Object.keys(userAnswers).length > 0) {
                submitQuiz(true);
            }
        };
    </script>

</body>
</html>