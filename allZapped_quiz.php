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
    $sql = "SELECT question_id, question_text, question_type, left_items, right_items FROM questions WHERE quiz_id = $quiz_id";
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
                font-family: Fredoka;
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
                font-family: Fredoka;
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
                font-weight: 600;
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
                font-family: Fredoka;
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
                font-family: Fredoka;
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

            .drop-zone h4 {
                font-weight: lighter;
                margin-bottom: 10px;
                color: #333;
            }

            .match-container {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 20px;
                margin-bottom: 20px;
            }

            .left-items, .right-items {
                border: 1px solid #f8b500;
                border-radius: 8px;
                padding: 15px;
                background-color: #fff5e1;
                color: black;
                font-weight: 500;
            }

            .match-item {
                padding: 10px;
                margin: 5px 0;
                cursor: pointer;
                border-radius: 5px;
                background-color: #f9f9f9;
                border: 1px solid #ddd;
                color: black;
            }

            .match-item:hover {
                background-color: #f8b500;
                color: white;
            }

            .match-item.selected {
                background-color: #f8b500;
                color: white;
            }

            .match-item.matched {
                background-color: #FCEF91;
                border-color: #f8b500;
                color: black;
                cursor: default;
            }

            .pairs-display {
                margin-top: 20px;
                padding: 15px;
                background-color: #f9f9f9;
                border-radius: 8px;
                border: 1px solid #ddd;
            }

            .clear-matches-btn {
                margin-top: 10px;
                padding: 8px 15px;
                background-color: #dc3545;
                color: white;
                border: none;
                border-radius: 5px;
                cursor: pointer;
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
                font-family: Fredoka;
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
                    if (!question.left_items || !question.right_items) {
                        console.error('Missing left or right items for matching type question');
                        answersDiv.innerHTML = '<p>Error: Matching items not configured properly</p>';
                        return;
                    }

                    try {
                        // Parse the JSON strings for left and right items
                        const leftItems = JSON.parse(question.left_items);
                        const rightItems = JSON.parse(question.right_items);

                        // Create match container
                        const matchContainer = document.createElement('div');
                        matchContainer.className = 'match-container';
                        
                        // Left items column
                        const leftColumn = document.createElement('div');
                        leftColumn.className = 'left-items';
                        leftColumn.innerHTML = '<h4>Items to Match</h4>';
                        
                        // Add left items (numbered)
                        leftItems.forEach((item, index) => {
                            const matchItem = document.createElement('div');
                            matchItem.className = 'match-item';
                            matchItem.textContent = `${index + 1}. ${item}`;
                            matchItem.dataset.answerId = `left_${index}`;
                            matchItem.dataset.side = 'left';
                            matchItem.dataset.itemIndex = index;
                            matchItem.addEventListener('click', function() {
                                selectMatchItem(this, question.question_id);
                            });
                            leftColumn.appendChild(matchItem);
                        });

                        // Right items column
                        const rightColumn = document.createElement('div');
                        rightColumn.className = 'right-items';
                        rightColumn.innerHTML = '<h4>Match With</h4>';
                        
                        // Add right items (lettered)
                        const letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];
                        rightItems.forEach((item, index) => {
                            const matchItem = document.createElement('div');
                            matchItem.className = 'match-item';
                            matchItem.textContent = `${letters[index]}. ${item}`;
                            matchItem.dataset.answerId = `right_${index}`;
                            matchItem.dataset.side = 'right';
                            matchItem.dataset.itemIndex = index;
                            matchItem.addEventListener('click', function() {
                                selectMatchItem(this, question.question_id);
                            });
                            rightColumn.appendChild(matchItem);
                        });

                        // Add columns to container
                        matchContainer.appendChild(leftColumn);
                        matchContainer.appendChild(rightColumn);
                        answersDiv.appendChild(matchContainer);

                        // Pairs display area
                        const pairsDisplay = document.createElement('div');
                        pairsDisplay.className = 'pairs-display';
                        pairsDisplay.innerHTML = '<h4>Your Matches:</h4><div id="pairs-list-' + question.question_id + '"></div>';
                        answersDiv.appendChild(pairsDisplay);

                        // Clear button
                        const clearBtn = document.createElement('button');
                        clearBtn.textContent = 'Clear All Matches';
                        clearBtn.className = 'clear-matches-btn';
                        clearBtn.addEventListener('click', function() {
                            clearAllMatches(question.question_id);
                        });
                        answersDiv.appendChild(clearBtn);

                        // Initialize matching data for this question
                        if (!window.matchingData) {
                            window.matchingData = {};
                        }
                        window.matchingData[question.question_id] = {
                            selectedLeft: null,
                            selectedRight: null,
                            matches: []
                        };

                    } catch (e) {
                        console.error('Error parsing matching items:', e);
                        answersDiv.innerHTML = '<p>Error loading matching items</p>';
                    }
                    break;
                        default:
                            console.error('Unknown question type:', questionType);
                            answersDiv.innerHTML = `<p>Unable to render answers for question type: ${questionType}</p>`;
                    }
                }

        function selectMatchItem(item, questionId) {
            const side = item.dataset.side;
            const matchData = window.matchingData[questionId];
            
            // Remove previous selection from the same side
            document.querySelectorAll(`[data-side="${side}"]`).forEach(el => {
                if (el.closest('.question').querySelector('.question-text').textContent.includes(questionId) || 
                    el.getAttribute('data-question-id') === questionId.toString()) {
                    el.classList.remove('selected');
                }
            });

            // Select current item
            item.classList.add('selected');

            if (side === 'left') {
                matchData.selectedLeft = item;
            } else {
                matchData.selectedRight = item;
            }

            // If both sides have selections, create a match
            if (matchData.selectedLeft && matchData.selectedRight) {
                createMatch(questionId);
            }
        }

        function createMatch(questionId) {
            const matchData = window.matchingData[questionId];
            const leftItem = matchData.selectedLeft;
            const rightItem = matchData.selectedRight;
            
            const leftId = leftItem.dataset.answerId;
            const rightId = rightItem.dataset.answerId;
            
            // Check if either item is already matched
            const existingMatch = matchData.matches.find(m => m.left === leftId || m.right === rightId);
            if (existingMatch) {
                alert('One of these items is already matched. Clear existing matches first.');
                return;
            }

            // Create the match
            const match = {
                left: leftId,
                leftText: leftItem.textContent,
                right: rightId,
                rightText: rightItem.textContent
            };
            
            matchData.matches.push(match);
            
            // Update display
            updateMatchesDisplay(questionId);
            
            // Save to answers
            saveAnswer(questionId, matchData.matches);
            
            // Clear selections
            leftItem.classList.remove('selected');
            rightItem.classList.remove('selected');
            leftItem.classList.add('matched');
            rightItem.classList.add('matched');
            
            matchData.selectedLeft = null;
            matchData.selectedRight = null;
        }

        function updateMatchesDisplay(questionId) {
            const matchData = window.matchingData[questionId];
            const pairsList = document.getElementById('pairs-list-' + questionId);
            
            pairsList.innerHTML = '';
            
            matchData.matches.forEach((match, index) => {
                const pairElement = document.createElement('div');
                pairElement.className = 'match-pair';
                pairElement.style.display = 'flex';
                pairElement.style.justifyContent = 'space-between';
                pairElement.style.alignItems = 'center';
                pairElement.style.padding = '8px 12px';
                pairElement.style.backgroundColor = '#FCEF91';
                pairElement.style.border = '1px solid #F8B500';
                pairElement.style.borderRadius = '5px';
                pairElement.style.marginBottom = '5px';
                
                pairElement.innerHTML = `
                    <span style="flex: 1; font-weight: 500; color: black;">${match.leftText}</span>
                    <span style="margin: 0 10px; font-weight: 500; color: #28a745;">↔</span>
                    <span style="flex: 1; font-weight: 500; color: black;">${match.rightText}</span>
                    <button onclick="removeMatch(${questionId}, ${index})" style="
                        background: #dc3545; 
                        color: white; 
                        border: none; 
                        border-radius: 3px; 
                        padding: 2px 6px; 
                        cursor: pointer; 
                        font-size: 12px;
                        margin-left: 10px;
                    ">×</button>
                `;
                
                pairsList.appendChild(pairElement);
            });
            
            if (matchData.matches.length === 0) {
                pairsList.innerHTML = '<p style="color: #666; font-style: italic;">No matches yet</p>';
            }
        }

        function removeMatch(questionId, matchIndex) {
            const matchData = window.matchingData[questionId];
            const removedMatch = matchData.matches[matchIndex];
            
            // Remove the match
            matchData.matches.splice(matchIndex, 1);
            
            // Remove 'matched' class from items
            document.querySelectorAll('.match-item').forEach(item => {
                if (item.dataset.answerId === removedMatch.left || item.dataset.answerId === removedMatch.right) {
                    item.classList.remove('matched');
                }
            });
            
            // Update display and save
            updateMatchesDisplay(questionId);
            saveAnswer(questionId, matchData.matches);
        }

        function clearAllMatches(questionId) {
            const matchData = window.matchingData[questionId];
            
            // Clear all matches
            matchData.matches = [];
            matchData.selectedLeft = null;
            matchData.selectedRight = null;
            
            // Remove all visual indicators
            document.querySelectorAll('.match-item').forEach(item => {
                item.classList.remove('selected', 'matched');
            });
            
            // Update display and save
            updateMatchesDisplay(questionId);
            delete userAnswers[questionId];
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