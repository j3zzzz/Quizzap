<?php
session_start();

// Check if we have quiz result data in session
if (!isset($_SESSION['quiz_result'])) {
    // No quiz data found, redirect to select quiz
    header("Location: select_quiz.php");
    exit();
}

// Get data from session
$result_data = $_SESSION['quiz_result'];

error_log("Quiz result data: " . print_r($result_data, true));

$quiz_id = $result_data['quiz_id'];
$score = $result_data['score'];
$total = $result_data['total'];
$wrong_answers = $result_data['wrong_answers'];
$subject_id = $result_data['subject_id'];

// Clear the session data after use
unset($_SESSION['quiz_result']);

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "rawrit";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (strpos($_SESSION['account_number'], 'S') !== 0) {
    header("Location: login.php");
    exit();
}

// Extract student_id from session
$account_number = $_SESSION['account_number'];
$sql = "SELECT student_id FROM students WHERE account_number = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $account_number);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $student_id = $row['student_id'];
} else {
    die("Student not found for account number: $account_number");
}
$stmt->close();

error_log("Current session - account_number: $account_number, student_id: $student_id");
error_log("Looking for answers for quiz_id: $quiz_id");

function getUserAnswerText($wrong_answers, $question_id) {
    if (isset($wrong_answers[$question_id]) && isset($wrong_answers[$question_id]['answer_text'])) {
        return $wrong_answers[$question_id]['answer_text'];
    }
    return null;
}

// Fetch quiz type from quizzes table
$quiz_type_sql = "SELECT quiz_type FROM quizzes WHERE quiz_id = ?";
$quiz_type_stmt = $conn->prepare($quiz_type_sql);

if ($quiz_type_stmt === false) {
    die("Prepare failed: (" . $conn->errno . ") " . $conn->error);
}

$quiz_type_stmt->bind_param("i", $quiz_id);
$quiz_type_stmt->execute();
$quiz_type_result = $quiz_type_stmt->get_result(); 
$quiz_type_row = $quiz_type_result->fetch_assoc();
$quiz_type = $quiz_type_row['quiz_type'] ?? null;
$quiz_type_stmt->close();

error_log("Quiz type from database: " . $quiz_type);

// Map quiz types to question types
$quiz_type_to_question_type = [
    'Multiple Choice' => 'multiple_choice',
    'True or False' => 'true_or_false',
    'Drag & Drop' => 'drag_and_drop',
    'Enumeration' => 'enumeration',
    'Matching Type' => 'matching_type',
    'Identification' => 'identification',
    'Fill in the Blanks' => 'fill_in_the_blanks'
];

// Get the corresponding question type for the quiz
$question_type_for_quiz = $quiz_type_to_question_type[$quiz_type] ?? null;
error_log("Mapped question type for quiz: " . $question_type_for_quiz);

// Function to get correct answer count for enumeration questions
function getEnumerationTotalPoints($conn, $quiz_id) {
    $total_points = 0;
    
    // Get all enumeration questions for this quiz
    $sql = "SELECT q.question_id, a.answer_text 
            FROM questions q 
            LEFT JOIN answers a ON q.question_id = a.question_id 
            WHERE q.quiz_id = ? AND q.question_type = 'enumeration'";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $quiz_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        if (!empty($row['answer_text'])) {
            $answers = explode(',', $row['answer_text']);
            $total_points += count($answers);
        }
    }
    $stmt->close();
    
    return $total_points;
}

// Function to get total points for non-enumeration questions
function getNonEnumerationTotalPoints($conn, $quiz_id) {
    $total_points = 0;
    
    // Count questions that are NOT enumeration
    $sql = "SELECT COUNT(*) as count 
            FROM questions 
            WHERE quiz_id = ? AND question_type != 'enumeration'";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $quiz_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $total_points = $row['count'];
    }
    $stmt->close();
    
    return $total_points;
}

// Calculate total possible score
$enumeration_total = getEnumerationTotalPoints($conn, $quiz_id);
$non_enumeration_total = getNonEnumerationTotalPoints($conn, $quiz_id);
$total_correct_answers = $enumeration_total + $non_enumeration_total;

// Calculate display score
if ($quiz_type === 'Enumeration') {
    // Pure enumeration quiz - score is already calculated correctly in the submission
    $display_score = $score;
    $total_correct_answers = $enumeration_total;
    
    // Debug: Add logging to verify
    error_log("Pure Enumeration Quiz - Display Score: $display_score, Enumeration Total: $enumeration_total");
} else if ($quiz_type === 'Matching Type') {
    // For matching type quizzes
    $display_score = $score;
    $total_correct_answers = $total; // total from session
} else {
    // For other quiz types (mixed quizzes)
    // Need to count enumeration answers in mixed quizzes too
    if ($enumeration_total > 0) {
        // Mixed quiz with enumeration questions
        // The score from session should already include correct enumeration answers
        $display_score = $score;
        $total_correct_answers = $enumeration_total + $non_enumeration_total;
    } else {
        // No enumeration questions in this quiz
        $display_score = $score;
        $total_correct_answers = $total; // total from session
    }
}

// Additional safety check
if ($total_correct_answers === 0 && $quiz_type === 'Enumeration') {
    $total_correct_answers = $enumeration_total;
}


$sql = "SELECT * FROM questions WHERE quiz_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $quiz_id);
$stmt->execute();
$result = $stmt->get_result();

$questions = [];
while ($row = $result->fetch_assoc()) {
    $question_id = $row['question_id'];

    $answers_sql = "SELECT * FROM answers WHERE question_id = ?";
    $answers_stmt = $conn->prepare($answers_sql);
    $answers_stmt->bind_param("i", $question_id);
    $answers_stmt->execute();
    $answers_result = $answers_stmt->get_result();

    $answers = [];
    while ($answer_row = $answers_result->fetch_assoc()) {
        // Clean and prepare answer for display
        $cleaned_answer = preg_replace('/^[\[\]"\']+|[\[\]"\']+$/', '', $answer_row['answer_text']);
        $answer_row['individual_answer'] = trim($cleaned_answer);
        $answers[] = $answer_row;
    }
    $answers_stmt->close();

    // Override the question_type based on quiz type for display purposes
    if ($question_type_for_quiz) {
        $row['question_type'] = $question_type_for_quiz;
    }
    
    $row['answers'] = $answers;
    $questions[] = $row;
}
$stmt->close();

// Debug: Check if answers are being fetched
error_log("Number of questions fetched: " . count($questions));
foreach ($questions as $q) {
    error_log("Question ID: " . $q['question_id'] . ", Display Type: " . $q['question_type'] . ", Answers count: " . count($q['answers']));
    foreach ($q['answers'] as $a) {
        error_log("  - Answer ID: " . $a['answer_id'] . ", Answer: " . $a['individual_answer'] . ", Is correct: " . $a['is_correct']);
    }
}

// If subject_id is not passed via the URL, fetch it from the database
if (!$subject_id) {
    $subject_sql = "SELECT subject_id FROM quizzes WHERE quiz_id = ?";
    $subject_stmt = $conn->prepare($subject_sql);
    $subject_stmt->bind_param("i", $quiz_id);
    $subject_stmt->execute();
    $subject_result = $subject_stmt->get_result();
    
    if ($subject_result->num_rows > 0) {
        $subject_row = $subject_result->fetch_assoc();
        $subject_id = $subject_row['subject_id'];
    } else {
        // Only redirect if we really can't get the subject_id
        header("Location: select_quiz.php");
        exit();
    }
    $subject_stmt->close();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" type="text/css" href="other resources/fontawesome-free-6.5.2-web/css/all.min.css">
    <title>Quiz Result</title>
    <style type="text/css">
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, Helvetica, sans-serif;
            background-color: #ffffff;
            transition: background-color 0.3s, color 0.3s;
        }

        body.dark-mode {
            background-color: #1a1a1a;
            color: #e0e0e0;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            background-color: white;
        }

        body.dark-mode header {
            background-color: #2d2d2d;
        }

        header .logo {
            font-size: 24px;
            font-weight: bold;
            margin-left: 30px;
            margin-top: 3px;
        }

        header .actions .profile img {
            width: 40px;
            height: 40px;
            background-color: #ffffff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #f5a623;
            font-size: 1.5rem;
        }

        nav p{
            font-family: Purple Smile;
            color: white;
            font-size: 30px;
            margin-right: 30px;
        }

        .options {
            height: fit-content;
            width: 90%;
            margin: auto;
        }

        #quizzes {
            float: left;
            font-weight: 500;
        }

        #rankings {
            float: right;
            margin-right: 5%;
            font-weight: 500;
        }

        .container{
            width: 80%;
            background-color: white;
            border-radius: 15px;
            border: 3px solid #E3E2E2;
            box-shadow: 0 4px 8px 0 rgba(0, 0, 0, 0.2), 0 6px 20px 0 rgba(0, 0, 0, 0.19);
            padding: 5%;
            margin-left: 10%;
            margin-top: 5%;
        }

        body.dark-mode .container {
            background-color: #2d2d2d;
            border-color: #444;
            color: #e0e0e0;
        }

        h1{
            font-family: Fredoka;
            font-size: 30px;
            color: white;
            letter-spacing: 1px;    
        }

        h2{
            font-family: Fredoka;
        }

        body.dark-mode h2 {
            color: #e0e0e0;
        }

        a{
            float: left;
            margin-top: 3%;
            margin-left: 5%;
            text-decoration: none;
            font-size: 20px;
            font-family: Fredoka;
            color: #605F5F;
        }

        body.dark-mode a {
            color: #b0b0b0;
        }

        .score{
            float: right;
            color: #f8b500;
            font-family: Fredoka;
            font-weight: 500;
            font-size: 22px;
            margin-top: -2%;
        }

        .question{
            font-family: Fredoka;
            font-weight: 500;
        }

        .question p {
            margin-left: -2%;
        }

        .qstn{
            font-size: 22px;
        }

        body.dark-mode .qstn {
            color: #e0e0e0;
        }

        .qstn-con{
            width: 100%;
            border-radius: 15px;
            border: 2px solid #f8b500;
            padding: 30px;
            margin-bottom: 10px;
        }

        body.dark-mode .qstn-con {
            border-color: #f8b500;
            background-color: #333;
        }

        .individual-answer {
            padding: 10px;
            border-radius: 5px;
        }

        .user-answer span {
            font-weight: lighter ;
        }

        .answers {
            margin-top: 10px;
            margin-left: 20px;
        }
        .individual-answer {
            padding: 5px 0;
        }
        .correct-answers {
            margin-bottom: 10px;
            padding: 8px;
            border-radius: 5px;
        }

        .user-answer {
            margin-top: 10px;
            padding: 8px;
            background-color: #fff8f8;
            border-radius: 5px;
        }

        body.dark-mode .user-answer {
            background-color: #3a2a2a;
        }

        .individual-answer {
            padding: 5px 10px;
            margin: 3px 0;
            border-radius: 3px;
        }

        .match-stats {
            margin-top: 10px;
            font-style: italic;
            color: #666;
        }

        body.dark-mode .match-stats {
            color: #999;
        }

        /* width */
        ::-webkit-scrollbar {
          width: 10px;
          height: 10px;
        }

        /* Track */
        ::-webkit-scrollbar-track {
          box-shadow: inset 0 0 5px grey; 
          border-radius: 10px;
        }
         
        /* Handle */
        ::-webkit-scrollbar-thumb {
          background: #CF5300; 
          border-radius: 10px;
        }

        /* Handle on hover */
        ::-webkit-scrollbar-thumb:hover {
          background: #A34404; 
        }
    </style>
</head>
<body>

    <header>
        <div class="logo"><img src="img/logo1.png" width="200px" height="80px"></div>
        <div class="actions">
            <div class="profile"><img src="uploads/profiles/default-profile.jpg" width="50px" height="50px"></div>
        </div>
    </header>

    <div class="options">
        <a id="quizzes" href="select_quiz.php?subject_id=<?php echo $subject_id; ?>"><span><i class="fa-solid fa-angle-left"></i> Back to Quizzes</span></a>
        <a id="rankings" href="s_rankings.php?quiz_id=<?php echo $quiz_id; ?>"><span> See Rankings <i class="fa-solid fa-angle-right"></i></span></a>   
    </div>
        
    <br>

    <div class="container">
    <p class="score">Your score: <?php echo $display_score . " / " . $total_correct_answers; ?></p>

    <h2>Review Questions</h2><br>
    <div id="questions">
        <?php $question_no = 1; ?>
        <?php foreach ($questions as $question): ?>
            <div class="qstn-con">
            <div class="question">
                <p class="qstn"><?php echo $question_no . '.' . ' ' . $question['question_text']; ?></p>
            
            <div class="answers">  
                <?php 
                // Check individual question type for proper display logic
                // Use the mapped question type from the quiz
                $is_multiple_choice_type = in_array($question['question_type'], ['multiple_choice', 'true_or_false', 'drag_and_drop']);
                
                error_log("Processing question ID: " . $question['question_id'] . ", Display Type: " . $question['question_type'] . ", Is MC type: " . ($is_multiple_choice_type ? 'Yes' : 'No'));
                ?>

                <?php if ($is_multiple_choice_type): ?>
                    <div class="answers">
                        <?php 
                        // Get user's answer text from wrong_answers session data
                        $user_answer_text = null;
                        if (isset($wrong_answers[$question['question_id']]) && isset($wrong_answers[$question['question_id']]['answer_text'])) {
                            $user_answer_text = trim($wrong_answers[$question['question_id']]['answer_text']);
                            error_log("User answer text from session for question " . $question['question_id'] . ": " . $user_answer_text);
                        }
                        
                        if (empty($question['answers'])) {
                            echo "<div class='individual-answer'>No answers found for this question</div>";
                        } else {
                            // Show all answer options
                            foreach ($question['answers'] as $answer) {
                                $answer_style = '';
                                $answer_marker = '';
                                
                                $answer_text = trim($answer['individual_answer'] ?? '');
                                
                                if ($answer['is_correct'] == 1) {
                                    $answer_style = 'color: green; font-weight: 600;';
                                    $answer_marker = ' ✓';
                                } elseif ($user_answer_text && strcasecmp($user_answer_text, $answer_text) === 0) {
                                    $answer_style = 'color: red; font-weight: 600;';
                                    $answer_marker = ' (Your answer)';
                                } else {
                                    $answer_style = 'color: black;';
                                    $answer_marker = '';
                                }
                                
                                echo '<div class="individual-answer">';
                                echo '<span style="' . $answer_style . '">';
                                echo htmlspecialchars($answer_text) . $answer_marker;
                                echo '</span>';
                                echo '</div>';
                            }
                        }
                        ?>
                    </div>
                <?php elseif ($question['question_type'] === 'matching_type'): ?>
                    <div class="answers">
                            <?php 
                            $user_got_wrong = isset($wrong_answers[$question['question_id']]);
                            
                            if ($user_got_wrong) {
                                $wrong_data = $wrong_answers[$question['question_id']];
                                
                                echo "<div class='correct-answers'><strong>Correct Matches:</strong>";
                                foreach ($wrong_data['correct_matches'] as $correct_match) {
                                    echo "<div class='individual-answer' style='color: green; font-weight: 600;'>";
                                    echo htmlspecialchars($correct_match['left']) . " → " . htmlspecialchars($correct_match['right']) . " ✓";
                                    echo "</div>";
                                }
                                echo "</div>";
                                
                                echo "<div class='user-answer'><strong>Your Matches:</strong>";
                                if (!empty($wrong_data['submitted_matches'])) {
                                    foreach ($wrong_data['submitted_matches'] as $submitted_match) {
                                        $is_correct = false;
                                        foreach ($wrong_data['correct_matches'] as $correct_match) {
                                            if (strcasecmp($submitted_match['left'], $correct_match['left']) === 0 && 
                                                strcasecmp($submitted_match['right'], $correct_match['right']) === 0) {
                                                $is_correct = true;
                                                break;
                                            }
                                        }
                                        
                                        $color = $is_correct ? 'green' : 'red';
                                        $mark = $is_correct ? '✓' : '✗';
                                        
                                        echo "<div class='individual-answer' style='color: $color; font-weight: bold;'>";
                                        echo htmlspecialchars($submitted_match['left']) . " → " . htmlspecialchars($submitted_match['right']) . " $mark";
                                        echo "</div>";
                                    }
                                } else {
                                    echo "<div class='individual-answer' style='color: red; font-weight: bold;'>No matches submitted ✗</div>";
                                }
                                echo "</div>";
                                
                                // Show matching statistics
                                $total_pairs = count($wrong_data['correct_matches']);
                                $correct_pairs = $wrong_data['correct_count'] ?? 0;
                                echo "<div class='match-stats'>You matched $correct_pairs out of $total_pairs pairs correctly.</div>";
                            } else {
                                // User got all matches correct
                                // Fetch correct answers from database
                                $correct_matches_sql = "SELECT answer_text FROM answers WHERE question_id = ? AND is_correct = 1";
                                $correct_matches_stmt = $conn->prepare($correct_matches_sql);
                                $correct_matches_stmt->bind_param("i", $question['question_id']);
                                $correct_matches_stmt->execute();
                                $correct_matches_result = $correct_matches_stmt->get_result();
                                
                                echo "<div class='correct-answers'><strong>Correct Matches:</strong>";
                                $total_pairs = 0;
                                while ($match_row = $correct_matches_result->fetch_assoc()) {
                                    $match_parts = explode('|', $match_row['answer_text']);
                                    if (count($match_parts) >= 2) {
                                        $total_pairs++;
                                        echo "<div class='individual-answer' style='color: green; font-weight: 600;'>";
                                        echo htmlspecialchars(trim($match_parts[0])) . " → " . htmlspecialchars(trim($match_parts[1])) . " ✓";
                                        echo "</div>";
                                    }
                                }
                                echo "</div>";
                                $correct_matches_stmt->close();
                                
                                // Fetch and display user's correct answers
                                $user_answer_sql = "SELECT answer FROM student_answers 
                                                    WHERE student_id = ? AND question_id = ? 
                                                    ORDER BY answered_at DESC LIMIT 1";
                                $user_answer_stmt = $conn->prepare($user_answer_sql);
                                $user_answer_stmt->bind_param("ii", $student_id, $question['question_id']);
                                $user_answer_stmt->execute();
                                $user_answer_result = $user_answer_stmt->get_result();
                                
                                if ($user_answer_result->num_rows > 0) {
                                    $user_answer_row = $user_answer_result->fetch_assoc();
                                    $user_matches = json_decode($user_answer_row['answer'], true);
                                    
                                    if (is_array($user_matches) && count($user_matches) > 0) {
                                        echo "<div class='user-answer'><strong>Your Matches:</strong>";
                                        foreach ($user_matches as $match) {
                                            echo "<div class='individual-answer' style='color: green; font-weight: 600;'>";
                                            echo htmlspecialchars($match['left']) . " → " . htmlspecialchars($match['right']) . " ✓";
                                            echo "</div>";
                                        }
                                        echo "</div>";
                                    }
                                }
                                $user_answer_stmt->close();
                                
                                // Show perfect match message
                                echo "<div class='match-stats'>You matched all $total_pairs pairs correctly!</div>";
                            }
                            ?>
                    </div>
                
                <?php elseif ($question['question_type'] === 'enumeration'): ?>
                    <div class="answers">
                        <?php 
                            // Display correct answers
                            echo "<div class='correct-answers'><strong>Correct Answer(s):</strong>";
                            foreach ($question['answers'] as $answer) {
                                echo "<div class='individual-answer' style='color: green; font-weight: 600;'>" . 
                                    htmlspecialchars($answer['individual_answer'] ?? 'No answer text') . " ✓</div>";
                            }
                            echo "</div>";

                            // Get user's answer
                            $user_answer_sql = "SELECT answer FROM student_answers 
                                                WHERE student_id = ? 
                                                AND question_id = ? 
                                                ORDER BY answered_at DESC LIMIT 1";
                            $user_answer_stmt = $conn->prepare($user_answer_sql);
                            $user_answer_stmt->bind_param("ii", $student_id, $question['question_id']);
                            $user_answer_stmt->execute();
                            $user_answer_result = $user_answer_stmt->get_result();
                            
                            if ($user_answer_result->num_rows > 0) {
                                $user_answer_row = $user_answer_result->fetch_assoc();
                                $user_actual_answer = $user_answer_row['answer'];
                                
                                $is_wrong = isset($wrong_answers[$question['question_id']]);
                                
                                echo "<div class='user-answer'><strong>Your Answer:</strong>";
                                
                                $user_answers = array_map('trim', explode(',', $user_actual_answer));
                                
                                $correct_answer_text = '';
                                foreach ($question['answers'] as $answer) {
                                    if ($answer['is_correct'] == 1) {
                                        $correct_answer_text = $answer['answer_text'];
                                        break;
                                    }
                                }
                                $correct_answers = array_map('trim', explode(',', $correct_answer_text));
                                
                                foreach ($user_answers as $user_ans) {
                                    $is_correct = in_array(strtolower(trim($user_ans)), array_map('strtolower', array_map('trim', $correct_answers)));
                                    $color = $is_correct ? 'green' : 'red';
                                    $mark = $is_correct ? '✓' : '✗';
                                    echo "<div class='individual-answer' style='color: $color; font-weight: bold;'>" . 
                                        htmlspecialchars($user_ans) . " $mark</div>";
                                }
                                echo "</div>";
                            } else {
                                echo "<div class='user-answer'><strong>Your Answer:</strong> ";
                                echo "<div class='individual-answer' style='color: red; font-weight: bold;'>No answer recorded ✗</div>";
                                echo "</div>";
                            }
                            $user_answer_stmt->close();
                        ?>
                    </div>
                <?php elseif (in_array($question['question_type'], ['identification', 'fill_in_the_blanks'])): ?>
                    <div class="answers">
                        <?php 
                            // Display correct answers
                            echo "<div class='correct-answers'><strong>Correct Answer(s):</strong>";
                            foreach ($question['answers'] as $answer) {
                                echo "<div class='individual-answer' style='color: green; font-weight: 600;'>" . 
                                    htmlspecialchars($answer['individual_answer'] ?? 'No answer text') . " ✓</div>";
                            }
                            echo "</div>";

                            $user_answer_sql = "SELECT answer FROM student_answers 
                                                WHERE student_id = ? 
                                                AND question_id = ? 
                                                ORDER BY answered_at DESC LIMIT 1";
                            $user_answer_stmt = $conn->prepare($user_answer_sql);
                            $user_answer_stmt->bind_param("ii", $student_id, $question['question_id']);
                            $user_answer_stmt->execute();
                            $user_answer_result = $user_answer_stmt->get_result();
                            
                            if ($user_answer_result->num_rows > 0) {
                                $user_answer_row = $user_answer_result->fetch_assoc();
                                $user_actual_answer = $user_answer_row['answer'];

                                $is_wrong = isset($wrong_answers[$question['question_id']]);
                                
                                echo "<div class='user-answer'><strong>Your Answer:</strong>";
                                
                                $color = $is_wrong ? 'red' : 'green';
                                $mark = $is_wrong ? '✗' : '✓';
                                echo "<div class='individual-answer' style='color: $color; font-weight: bold;'>" . 
                                    htmlspecialchars($user_actual_answer) . " $mark</div>";
                                echo "</div>";
                            } else {
                                echo "<div class='user-answer'><strong>Your Answer:</strong> ";
                                echo "<div class='individual-answer' style='color: red; font-weight: bold;'>No answer recorded ✗</div>";
                                echo "</div>";
                            }
                            $user_answer_stmt->close();
                        ?>
                    </div>
                <?php else: ?>
                    <div class="answers">
                        <div class="individual-answer" style="color: red; font-weight: bold;">
                            Unknown question type: <?php echo $question['question_type']; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>    
         </div>
    </div>
    <?php $question_no++; ?>
    <?php endforeach; ?>
</div>
</div>

<br>

<?php
// Close connection at the very end
$conn->close();
?>

<script>
    // Store the subject_id from PHP
    const subjectId = <?php echo $subject_id; ?>;

    // Handle back button
    window.addEventListener('popstate', function() {
        // Immediately redirect to select_quiz.php
        window.location.href = `select_quiz.php?subject_id=${subjectId}`;
    });

    // Push a state to the history stack
    history.pushState(null, null, window.location.href);

    // Also handle when user manually types in back button or uses keyboard
    window.onbeforeunload = function() {
    };

    // To prevent the initial back button press from working
    window.history.forward();

    // Optional: Disable right-click menu
    document.addEventListener('contextmenu', function(e) {
        e.preventDefault();
    });

    document.addEventListener('DOMContentLoaded', function() {
        // Clear sessionStorage after page loads
        sessionStorage.removeItem('quizResult');
        
        // Dark Mode Functionality - Auto apply based on localStorage
        const isDarkMode = localStorage.getItem('darkMode') === 'true';
        if (isDarkMode) {
            document.body.classList.add('dark-mode');
        }
    });
</script>

</body>
</html>