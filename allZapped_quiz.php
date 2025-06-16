    <?php
    session_start();
    if (strpos($_SESSION['account_number'], 'S') !== 0) {
        header("Location: login.php");
        exit();
    }

    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "rawrit";

    $conn = new mysqli($servername, $username, $password, $dbname);

    // Check connection
    if ($conn->connect_error) {
        echo json_encode(["success" => false, "error" => "Connection failed: " . $conn->connect_error]);
        exit;
    }

    if (!isset($_GET['quiz_id'])) {
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

    $subject_id = $quiz['subject_id'];

    // Limit to 10 questions
    $sql = "SELECT question_id, question_text, question_type FROM questions WHERE quiz_id = $quiz_id";
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
        <link rel="stylesheet" type="text/css" href="font/fontawesome-free-6.5.2-web/css/all.min.css">
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

            .quiz-container {
                background-color: #FFFFFF;
                font-family: Tilt Warp Regular;
                color: #f8b500;
                margin: 2% auto 5%;
                padding: 40px 50px;
                width: 90%;
                border: 2px solid #f8b500;
                border-radius: 10px;
                box-shadow: 4px 4px 0 0 #BC8900;
            }

            .quiz-header {
                text-align: center;
                margin-bottom: 20px;
            }

            .timer {
                background-color: white;
                font-family: Tilt Warp Regular;
                font-size: 20px;
                color: #707070;
                display: flex;
                padding: 10px;
                width: 7%;
                margin-top: -4%;
                float: right;
                border-radius: 5px;
                text-align: center;
                vertical-align: middle;
                align-content: center;
                border: 2px solid #f8b500;
            }

            .question {
                background-color: #fff5e1;
                border: 1px solid #f0c808;
                border-radius: 8px;
                padding: 30px;
                margin-bottom: 30px;
            }

            .question-text {
                color: black;
                font-size: 23px;
                margin-bottom: 15px;
            }

            .answers {
                display: grid;
                gap: 10px;
            }

            .answer-button {
                display: block;
                width: 100%;
                padding: 10px;
                background-color: #f9f9f9;
                border: 1px solid #f8b500;
                border-radius: 5px;
                cursor: pointer;
                color: black;
                text-align: left;
                font-family: Tilt Warp Regular;
                font-size: 17px;
                margin-bottom: 4px;
            }

            .answer-button:hover {
                background-color: #f8b500;
                color: white;
            }

            .answer-button.selected {
                background-color: #f8b500;
                color: white;
            }

            input[type="text"] {
                width: 100%;
                padding: 10px;
                border-radius: 5px;
                border: 1px solid #f8b500;
                font-size: 17px;
                font-family: Tilt Warp Regular;
                margin-bottom: 10px;
            }

            .drag-item {
                background-color: #fff5e1;
                border: 1px solid #f8b500;
                padding: 10px;
                margin: 5px 0;
                cursor: move;
                border-radius: 5px;
                color: black;
            }

            .drop-zone {
                border: 2px dashed #f8b500;
                border-radius: 10px;
                padding: 15px;
                min-height: 50px;
                margin-bottom: 15px;
            }

            .drop-zone h4{
                font-weight: lighter;
            }

            .matching-container {
                display: flex;
                justify-content: space-between;
                gap: 20px;
                padding: 20px;
                border: 1px solid #f8b500;
                border-radius: 8px;
                background-color: #fff;
            }

            .matching-left-side, .matching-right-side {
                flex: 1;
                display: flex;
                flex-direction: column;
                gap: 15px;
            }

            .matching-left-item {
                padding: 15px;
                background-color: #fff5e1;
                border: 1px solid #f8b500;
                border-radius: 5px;
                min-height: 50px;
                display: flex;
                align-items: center;
            }

            .matching-right-select-container {
                padding: 10px;
                background-color: #fff5e1;
                border: 1px solid #f8b500;
                border-radius: 5px;
            }

            .matching-label {
                display: block;
                margin-bottom: 5px;
                color: #333;
                font-size: 0.9em;
            }

            .matching-select {
                width: 100%;
                padding: 8px;
                border: 1px solid #f8b500;
                border-radius: 5px;
                background-color: white;
                font-family: inherit;
            }

            .matching-select:focus {
                outline: none;
                border-color: #d99b00;
                box-shadow: 0 0 0 2px rgba(248, 181, 0, 0.2);
            }

            .matching-image {
                max-width: 100%;
                height: auto;
                display: block;
                border-radius: 4px;
            }

            .submit-btn {
                display: block;
                width: 100%;
                padding: 15px;
                background-color: #f8b500;
                color: white;
                border: none;
                border-radius: 5px;
                font-size: 18px;
                font-family: Tilt Warp Regular;
                cursor: pointer;
                margin-top: 20px;
                box-shadow: 0 5px 0 0 #BC8900;
            }

            .submit-btn:hover {
                background-color: #e6a500;
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

    <div class="quiz-container">
        <div id="quiz-header">
            <h1><?php echo htmlspecialchars($quiz['title']); ?></h1> 
            <div id="timer" class="timer"><?php echo $quiz['timer']; ?></div>
        </div><br><br>

        <div id="quiz-questions">
            <!-- Questions will be dynamically inserted here -->
        </div>

        <div class="submit-cont">
            <button onclick="submitQuiz()" class="submit-btn">Submit Quiz</button>
        </div>
    </div>

    <script>
        const questions = <?php echo json_encode($questions); ?>;
        const userAnswers = {};
        const timerDuration = <?php echo $quiz['timer'] * 60; ?>;

        function renderAnswers(data, question, answersDiv, questionType) {
        // Clear any existing answers
        answersDiv.innerHTML = '';

        // Debug logging to understand the data and question type
        console.log('Question Type:', questionType);
        console.log('Answers Data:', data);

        // Render answers based on the specific question type
        switch(questionType) {
            case 'true_or_false':
            // For True/False questions, create both options
            ['True', 'False'].forEach((answerText) => {
                const answerButton = document.createElement('button');
                answerButton.innerText = answerText;
                answerButton.className = 'answer-button';
                answerButton.onclick = function() {
                    // Save both the text and the answer_id if available
                    const answerObj = data.find(item => 
                        item.answer_text && item.answer_text.trim().toLowerCase() === answerText.toLowerCase()
                    );
                    
                    if (answerObj) {
                        saveAnswer(question.question_id, answerObj.answer_id);
                    } else {
                        // Fallback to using the text if answer_id not found
                        saveAnswer(question.question_id, answerText);
                    }
                    
                    answersDiv.querySelectorAll('.answer-button').forEach(btn => btn.classList.remove('selected'));
                    answerButton.classList.add('selected');
                };
                answersDiv.appendChild(answerButton);
            });
            break;

            case 'identification':
            case 'enumeration':
            case 'fill_in_the_blanks':
                const answerInput = document.createElement('input');
                answerInput.type = 'text';
                answerInput.placeholder = `Enter your ${questionType.replace(/_/g, ' ').toLowerCase()} answer`;
                answerInput.className = 'form-control'; // Added for better styling
                answerInput.oninput = function() {
                    saveAnswer(question.question_id, answerInput.value.trim());
                };
                answersDiv.appendChild(answerInput);
                break;

            case 'multiple_choice':
                if (!data || data.length === 0) {
                    console.error('No answers found for Multiple Choice question');
                    return;
                }
                
                const labels = ['A', 'B', 'C', 'D'];
                data.forEach((answer, i) => {
                    if (!answer) return; // Skip if answer is undefined
                    
                    const answerButton = document.createElement('button');
                    answerButton.innerText = `${labels[i]}. ${answer.answer_text || 'No text'}`;
                    answerButton.className = 'answer-button';
                    answerButton.onclick = function() {
                        saveAnswer(question.question_id, answer.answer_id);
                        answersDiv.querySelectorAll('.answer-button').forEach(btn => btn.classList.remove('selected'));
                        answerButton.classList.add('selected');
                    };
                    answersDiv.appendChild(answerButton);
                });
                break;

            case 'drag_and_drop':
                if (!data || data.length === 0) {
                    console.error('No answers found for Drag and Drop question');
                    return;
                }
                // Create source and target containers
                const sourceContainer = document.createElement('div');
                const targetContainer = document.createElement('div');
                sourceContainer.className = 'drop-zone source-zone';
                targetContainer.className = 'drop-zone target-zone';
                
                sourceContainer.innerHTML = '<h4>Drag Items</h4>';
                targetContainer.innerHTML = '<h4>Drop Item Here</h4>';
                
                // Shuffle the items to make it more challenging
                const shuffledData = data.sort(() => Math.random() - 0.5);
                
                // Create draggable items for source container
                shuffledData.forEach((item, index) => {
                    const dragItem = document.createElement('div');
                    dragItem.className = 'drag-item';
                    dragItem.draggable = true;
                    dragItem.dataset.answerId = item.answer_id;
                    dragItem.innerText = item.answer_text;
                    
                    // Drag event listeners
                    dragItem.addEventListener('dragstart', (e) => {
                        e.dataTransfer.setData('text/plain', e.target.dataset.answerId);
                        e.target.classList.add('dragging');
                    });
                    
                    dragItem.addEventListener('dragend', (e) => {
                        e.target.classList.remove('dragging');
                    });
                    
                    sourceContainer.appendChild(dragItem);
                });
                
                // Drop zone event listeners
                targetContainer.addEventListener('dragover', (e) => {
                    e.preventDefault(); // Allow dropping
                    e.target.classList.add('drag-over');
                });
                
                targetContainer.addEventListener('dragleave', (e) => {
                    e.target.classList.remove('drag-over');
                });
                
                targetContainer.addEventListener('drop', (e) => {
                    e.preventDefault();
                    const answerId = e.dataTransfer.getData('text/plain');
                    const droppedItem = document.querySelector(`.drag-item[data-answer-id="${answerId}"]`);
                    
                    if (droppedItem) {
                        // Remove any existing item from the target container
                        const existingItem = targetContainer.querySelector('.drag-item');
                        if (existingItem) {
                            sourceContainer.appendChild(existingItem);
                        }
                        
                        // Move the dropped item to the target container
                        targetContainer.appendChild(droppedItem);
                        
                        // Save the dropped item
                        saveAnswer(question.question_id, [answerId]);
                    }
                    
                    e.target.classList.remove('drag-over');
                });
                
                // Add some extra styling
                answersDiv.style.display = 'grid';
                answersDiv.style.gridTemplateColumns = '1fr 1fr';
                answersDiv.style.gap = '20px';
                answersDiv.appendChild(sourceContainer);
                answersDiv.appendChild(targetContainer);
                break;

                case 'matching_type':
                    if (!data || data.length === 0) {
                        console.error('No matching data found');
                        return;
                    }
                    
                    // Create matching container
                    const matchingContainer = document.createElement('div');
                    matchingContainer.className = 'matching-container';
                    
                    // Left side (source items)
                    const leftSide = document.createElement('div');
                    leftSide.className = 'matching-left-side';
                    
                    // Right side (destination items)
                    const rightSide = document.createElement('div');
                    rightSide.className = 'matching-right-side';
                    
                    // Separate left and right items
                    const leftItems = data.filter(item => item.side === 'left');
                    const rightItems = data.filter(item => item.side === 'right');
                    
                    console.log('Left Items:', leftItems);
                    console.log('Right Items:', rightItems);
                    
                    // Shuffle right items to make matching more challenging
                    const shuffledRightItems = rightItems.sort(() => Math.random() - 0.5);
                    
                    // Create left side items
                    leftItems.forEach((leftItem, index) => {
                        const leftItemDiv = document.createElement('div');
                        leftItemDiv.className = 'matching-left-item';
                        leftItemDiv.dataset.leftItemId = leftItem.answer_id;
                        
                        // Check if the item has an image
                        if (leftItem.image_url) {
                            const img = document.createElement('img');
                            img.src = leftItem.image_url;
                            img.alt = leftItem.answer_text || `Image ${index + 1}`;
                            img.className = 'matching-image';
                            leftItemDiv.appendChild(img);
                        } else {
                            // If no image, display text
                            leftItemDiv.innerText = leftItem.answer_text || `Item ${index + 1}`;
                        }
                        
                        leftSide.appendChild(leftItemDiv);
                    });
                    
                    // Create matching dropdowns
                    leftItems.forEach((leftItem, index) => {
                        const selectContainer = document.createElement('div');
                        selectContainer.className = 'matching-right-select-container';
                        
                        const selectLabel = document.createElement('label');
                        selectLabel.className = 'matching-label';
                        selectLabel.innerText = `Match for ${leftItem.answer_text || `Item ${index + 1}`}`;
                        
                        const select = document.createElement('select');
                        select.className = 'matching-select';
                        select.dataset.leftItemId = leftItem.answer_id;
                        
                        // Add default option
                        const defaultOption = document.createElement('option');
                        defaultOption.value = '';
                        defaultOption.innerText = 'Select an option';
                        select.appendChild(defaultOption);
                        
                        // Populate select with right items
                        shuffledRightItems.forEach(rightItem => {
                            const option = document.createElement('option');
                            option.value = rightItem.answer_id;
                            option.innerText = rightItem.answer_text || `Option ${rightItem.answer_id}`;
                            select.appendChild(option);
                        });
                        
                        // Event listener to save the matching
                        select.addEventListener('change', function() {
                            const selectedRightItemId = this.value;
                            const leftItemId = this.dataset.leftItemId;
                            
                            // Save the matching as an array of objects
                            saveAnswer(question.question_id, [{
                                left: leftItemId,
                                right: selectedRightItemId
                            }]);
                        });
                        
                        selectContainer.appendChild(selectLabel);
                        selectContainer.appendChild(select);
                        rightSide.appendChild(selectContainer);
                    });
                    
                    matchingContainer.appendChild(leftSide);
                    matchingContainer.appendChild(rightSide);
                    answersDiv.appendChild(matchingContainer);

                    break;

        

            default:
                console.error('Unknown question type:', questionType);
                answersDiv.innerHTML = `<p>Unable to render answers for question type: ${questionType}</p>`;
        }
    }

    // Modify fetch to add error handling
    function renderQuestions() {
        const quizQuestionsDiv = document.getElementById('quiz-questions');
        
        questions.forEach((question, index) => {
            const questionDiv = document.createElement('div');
            questionDiv.className = 'question';
            
            // Question number and text
            const questionNumberText = document.createElement('p');
            questionNumberText.innerText = `${index + 1}. ${question.question_text} `;     
            questionNumberText.className = 'question-text';
            questionDiv.appendChild(questionNumberText);
            
            // Answers container
            const answersDiv = document.createElement('div');
            answersDiv.className = 'answers';
            answersDiv.id = `answers-${question.question_id}`;
            
            // Fetch and render answers for this question
            fetch(`allZapped_getAnswer.php?question_id=${question.question_id}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    // Pass the question_type from the current question
                    renderAnswers(data, question, answersDiv, question.question_type);
                })
                .catch(error => {
                    console.error('Error fetching answers:', error);
                    answersDiv.innerHTML = `<p>Error loading answers: ${error.message}</p>`;
                });
            
            questionDiv.appendChild(answersDiv);
            quizQuestionsDiv.appendChild(questionDiv);
        });
    }
        function saveAnswer(questionId, answer) {
            userAnswers[questionId] = answer;
        }

        function submitQuiz() {
            fetch('allZapped_submitQuiz.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ 
                    answers: userAnswers, 
                    quiz_id: <?php echo $quiz_id; ?>,
                    subject_id: <?php echo $subject_id; ?>
                })
            })
            .then(response => response.json())    
            .then(data => {
                if (data.success) {
                    // Store the result data in sessionStorage temporarily
                    sessionStorage.setItem('quizResult', JSON.stringify({
                        score: data.score,
                        total: data.total,
                        quiz_id: <?php echo $quiz_id; ?>,
                        wrong_answers: data.wrong_answers,
                        subject_id: <?php echo $subject_id; ?>
                    }));
                    
                    // Redirect to a processing page that will set the PHP session
                    window.location.href = 'process_quiz_result.php';
                } else {
                    alert('Error submitting quiz: ' + (data.error || 'Unknown error'));
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
            let timer = duration, minutes, seconds;
            const timerInterval = setInterval(function () {
                minutes = parseInt(timer / 60, 10);
                seconds = parseInt(timer % 60, 10);

                minutes = minutes < 10 ? "0" + minutes : minutes;
                seconds = seconds < 10 ? "0" + seconds : seconds;

                document.getElementById('timer').textContent = `${minutes}:${seconds}`;

                if (--timer < 0) {
                    clearInterval(timerInterval);
                    submitQuiz();
                }
            }, 1000);
        }

        // Initialize on page load
        window.onload = function() {
            renderQuestions();
            startTimer(timerDuration);
        };
    </script>

    </body>
    </html>