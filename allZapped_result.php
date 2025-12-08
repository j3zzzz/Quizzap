<?php
session_start();

// Check if we have quiz result data in session, TEMPORARY LANG
if (!isset($_SESSION['quiz_result'])) {
    // Try to get from POST data if coming from submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quiz_result'])) {
        $_SESSION['quiz_result'] = json_decode($_POST['quiz_result'], true);
    } else {
        // No quiz data found, redirect to select quiz
        header("Location: select_quiz.php");
        exit();
    }
}

// Get data from session
$result_data = $_SESSION['quiz_result'];

error_log("Quiz result data: " . print_r($result_data, true));

$quiz_id = $result_data['quiz_id'];
$score = $result_data['score'];
$total = $result_data['total'];
$wrong_answers = $result_data['wrong_answers'];
$subject_id = $result_data['subject_id'];

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

error_log("POST data received: " . print_r($_POST, true));
error_log("Session quiz_result: " . print_r($_SESSION['quiz_result'] ?? 'Not set', true));

// Function to get user answer from database
function getUserAnswerFromDatabase($conn, $student_id, $question_id) {
    $sql = "SELECT answer FROM student_answers WHERE student_id = ? AND question_id = ? ORDER BY answered_at DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $student_id, $question_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return !empty($row['answer_text']) ? $row['answer_text'] : $row['answer'];
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

// If subject_id is not passed via the URL, fetch it from the database
if (empty($subject_id)) {
    $subject_sql = "SELECT subject_id FROM quizzes WHERE quiz_id = ?";
    $subject_stmt = $conn->prepare($subject_sql);
    $subject_stmt->bind_param("i", $quiz_id);
    $subject_stmt->execute();
    $subject_result = $subject_stmt->get_result();
    
    if ($subject_result->num_rows > 0) {
        $subject_row = $subject_result->fetch_assoc();
        $subject_id = $subject_row['subject_id'];
    } else {
        // Fallback: redirect to home if we can't get subject_id
        echo "<script>alert('Unable to retrieve quiz information.'); window.location.href='s_Home.php';</script>";
        exit();
    }
    $subject_stmt->close();
}

// Calculate total scores based on correct answers
$total_correct_answers = 0;
$matching_questions = [];

if ($quiz_type) {
    if ($quiz_type === 'Enumeration') {
        // For pure enumeration quizzes
        $total_sql = "SELECT q.question_id, a.answer_text, q.question_type
                      FROM questions q 
                      LEFT JOIN answers a ON q.question_id = a.question_id 
                      WHERE q.quiz_id = ?";
    
        $total_stmt = $conn->prepare($total_sql);
        $total_stmt->bind_param("i", $quiz_id);
        $total_stmt->execute();
        $total_result = $total_stmt->get_result();

        $enum_score = 0;
        while ($row = $total_result->fetch_assoc()) {
            if (!empty($row['answer_text'])) {
                $correct_answers = explode(',', $row['answer_text']);
                $total_correct_answers += count($correct_answers);

                if (!isset($wrong_answers[$row['question_id']])) {
                    $enum_score += count($correct_answers);
                }
            }
        }
        $adjusted_score = $enum_score;
        $total_stmt->close();
    } else {
        // For mixed quiz types - need to count each question type correctly
        $total_sql = "SELECT q.question_id, q.question_type, a.answer_text, a.is_correct
                      FROM questions q 
                      LEFT JOIN answers a ON q.question_id = a.question_id
                      WHERE q.quiz_id = ?";

        $total_stmt = $conn->prepare($total_sql);
        $total_stmt->bind_param("i", $quiz_id);
        $total_stmt->execute();
        $total_result = $total_stmt->get_result();

        $total = 0;
        $processed_questions = [];
        
        while ($row = $total_result->fetch_assoc()) {
        $question_id = $row['question_id'];
        
        // Skip if already processed
        if (in_array($question_id, $processed_questions)) {
            continue;
        }
        
        $processed_questions[] = $question_id;
        
        // Only count correct answers
        if ($row['is_correct'] != 1) {
            continue;
        }
        
        if ($row['question_type'] === 'enumeration') {
            // Each enumeration answer is worth 1 point
            if (!empty($row['answer_text'])) {
                $correct_answers = explode(',', $row['answer_text']);
                $total += count($correct_answers);
            }
        } elseif ($row['question_type'] === 'matching_type') {
            // Each matching pair is worth 1 point
            $pair_count_sql = "SELECT COUNT(*) as pair_count FROM answers 
                            WHERE question_id = ? AND is_correct = 1";
            $pair_stmt = $conn->prepare($pair_count_sql);
            $pair_stmt->bind_param("i", $question_id);
            $pair_stmt->execute();
            $pair_result = $pair_stmt->get_result();
            $pair_row = $pair_result->fetch_assoc();
            $total += $pair_row['pair_count'];
            $pair_stmt->close();
        } else {
            // Multiple choice, true/false, etc. - 1 point each
            $total++;
        }
    }
        
        $total_stmt->close();
    }
}

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
$total_possible_score = $enumeration_total + $non_enumeration_total;


// Calculate display score
if ($quiz_type === 'Enumeration') {
    // Pure enumeration quiz
    $display_score = $score;
    $total_correct_answers = $enumeration_total;
} else {
    $display_score = $score;
    $total_correct_answers = $total_possible_score;
}

// If there are matching questions, adjust the score based on correct pairs
if (!empty($matching_questions) && isset($wrong_answers)) {
    $matching_score = 0;
    $matching_total = 0;
    
    foreach ($matching_questions as $question_id => $correct_answer) {
        // Count total pairs for this question
        $pairs = explode('|', $correct_answer);
        $matching_total += count($pairs);
        
        if (isset($wrong_answers[$question_id])) {
            $wrong_data = $wrong_answers[$question_id];
            if (isset($wrong_data['correct_matches']) && isset($wrong_data['submitted_matches'])) {
                // Count correct pairs
                $correct_pairs = 0;
                foreach ($wrong_data['submitted_matches'] as $submitted_match) {
                    foreach ($wrong_data['correct_matches'] as $correct_match) {
                        if (strcasecmp($submitted_match['left'], $correct_match['left']) === 0 && 
                            strcasecmp($submitted_match['right'], $correct_match['right']) === 0) {
                            $correct_pairs++;
                            break;
                        }
                    }
                }
                $matching_score += $correct_pairs;
            }
        } else {
            // If not in wrong_answers, all pairs were correct
            $matching_score += count($pairs);
        }
    }
    
    // For matching questions, the base score already counted them as wrong (0 points each)
    // So we just add the partial credit
    $display_score += $matching_score;
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
        if (in_array($quiz_type, ['Multiple Choice', 'True or False', 'Drag & Drop'])) {
            $cleaned_answer = preg_replace('/^[\[\]"\']+|[\[\]"\']+$/', '', $answer_row['answer_text']);
            $answer_row['individual_answer'] = trim($cleaned_answer);
            $answers[] = $answer_row;
        } elseif ($row['question_type'] === 'matching_type') {
            $answer_row['individual_answer'] = $answer_row['answer_text'];
            $answers[] = $answer_row;
        } else {
            $cleaned_answer = preg_replace('/^[\[\]"\']+|[\[\]"\']+$/', '', $answer_row['answer_text']);
            $split_answers = preg_split('/\s*,\s*/', $cleaned_answer);
            foreach ($split_answers as $individual_answer) {
                $clean_individual_answer = preg_replace('/^[\[\]"\']+|[\[\]"\']+$/', '', trim($individual_answer));
                $answer_row['individual_answer'] = $clean_individual_answer;
                $answers[] = $answer_row;
            }
        }
    }
    $answers_stmt->close();

    $row['answers'] = $answers;
    $questions[] = $row;
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
            <?php 
            error_log("Displaying results for quiz $quiz_id with questions: " . print_r(array_column($questions, 'question_id'), true));
            ?>
            
            <div class="answers">  
                <?php 
                // Check individual question type for proper display logic
                $is_multiple_choice_type = in_array($question['question_type'], ['multiple_choice', 'true_or_false', 'drag_and_drop']);
                ?>

                <?php if ($is_multiple_choice_type): ?>
                    <div class="answers">
                        <?php 
                        $user_got_wrong = isset($wrong_answers[$question['question_id']]);
                        
                        if ($user_got_wrong) {
                            // Get the user's actual selected answer from the database
                            $user_answer_sql = "SELECT answer FROM student_answers 
                                            WHERE student_id = ? 
                                            AND question_id = ? 
                                            ORDER BY answered_at DESC LIMIT 1";
                            $user_answer_stmt = $conn->prepare($user_answer_sql);
                            $user_answer_stmt->bind_param("ii", $student_id, $question['question_id']);
                            $user_answer_stmt->execute();
                            $user_answer_result = $user_answer_stmt->get_result();
                            
                            $user_selected_answer_id = null;
                            if ($user_answer_result->num_rows > 0) {
                                $user_answer_row = $user_answer_result->fetch_assoc();
                                $user_selected_answer_id = $user_answer_row['answer'];
                            }
                            $user_answer_stmt->close();
                            
                            foreach ($question['answers'] as $answer) {
                                $answer_style = '';
                                $answer_marker = '';
                                
                                if ($answer['is_correct'] == 1) {
                                    $answer_style = 'color: green; font-weight: 600;';
                                    $answer_marker = ' ✓';
                                } elseif ($user_selected_answer_id && $user_selected_answer_id == $answer['answer_id']) {
                                    $answer_style = 'color: red; font-weight: 600;';
                                    $answer_marker = ' (Your answer)';
                                } else {
                                    $answer_style = 'color: black;';
                                    $answer_marker = '';
                                }
                                
                                echo '<div class="individual-answer">';
                                echo '<span style="' . $answer_style . '">';
                                echo htmlspecialchars($answer['individual_answer']) . $answer_marker;
                                echo '</span>';
                                echo '</div>';
                            }
                        } else {
                            // User got it right - just show correct answer
                            foreach ($question['answers'] as $answer) {
                                if ($answer['is_correct'] == 1) {
                                    echo '<div class="individual-answer">';
                                    echo '<span style="color: green; font-weight: 600;">';
                                    echo htmlspecialchars($answer['individual_answer']) . ' ✓';
                                    echo '</span>';
                                    echo '</div>';
                                    break;
                                }
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
                            echo "</div>";
                            
                            // Show matching statistics
                            $total_pairs = count($wrong_data['correct_matches']);
                            $correct_pairs = 0;
                            foreach ($wrong_data['submitted_matches'] as $submitted_match) {
                                foreach ($wrong_data['correct_matches'] as $correct_match) {
                                    if (strcasecmp($submitted_match['left'], $correct_match['left']) === 0 && 
                                        strcasecmp($submitted_match['right'], $correct_match['right']) === 0) {
                                        $correct_pairs++;
                                        break;
                                    }
                                }
                            }
                            echo "<div class='match-stats'>You matched $correct_pairs out of $total_pairs pairs correctly.</div>";
                        } else {
                            // User got all matches correct
                            $correct_matches_sql = "SELECT answer_text FROM answers WHERE question_id = ? AND is_correct = 1";
                            $correct_matches_stmt = $conn->prepare($correct_matches_sql);
                            $correct_matches_stmt->bind_param("i", $question['question_id']);
                            $correct_matches_stmt->execute();
                            $correct_matches_result = $correct_matches_stmt->get_result();
                            
                            echo "<div class='correct-answers'><strong>Correct Matches:</strong>";
                            while ($match_row = $correct_matches_result->fetch_assoc()) {
                                $match_parts = explode('|', $match_row['answer_text']);
                                if (count($match_parts) >= 2) {
                                    echo "<div class='individual-answer' style='color: green; font-weight: 600;'>";
                                    echo htmlspecialchars(trim($match_parts[0])) . " → " . htmlspecialchars(trim($match_parts[1])) . " ✓";
                                    echo "</div>";
                                }
                            }
                            echo "</div>";
                            
                            // Show perfect match message
                            $total_pairs_sql = "SELECT COUNT(*) as total FROM answers WHERE question_id = ? AND is_correct = 1";
                            $total_pairs_stmt = $conn->prepare($total_pairs_sql);
                            $total_pairs_stmt->bind_param("i", $question['question_id']);
                            $total_pairs_stmt->execute();
                            $total_pairs_result = $total_pairs_stmt->get_result();
                            $total_pairs_row = $total_pairs_result->fetch_assoc();
                            $total_pairs = $total_pairs_row['total'];
                            
                            echo "<div class='match-stats'>You matched all $total_pairs pairs correctly!</div>";
                            
                            $correct_matches_stmt->close();
                            $total_pairs_stmt->close();
                        }
                        ?>
                    </div>
                <?php elseif (in_array($question['question_type'], ['enumeration', 'identification', 'fill_in_the_blanks'])): ?>
                    <div class="answers">
                        <?php 
                            // Display correct answers
                            echo "<div class='correct-answers'><strong>Correct Answer(s):</strong>";
                            foreach ($question['answers'] as $answer) {
                                echo "<div class='individual-answer' style='color: green; font-weight: 600;'>" . 
                                    htmlspecialchars($answer['individual_answer']) . " ✓</div>";
                            }
                            echo "</div>";

                            // Get user's answer
                            $user_answer = getUserAnswerFromDatabase($conn, $student_id, $question['question_id']);
                            
                            if ($user_answer !== null) {
                                $is_wrong = isset($wrong_answers[$question['question_id']]);
                                
                                echo "<div class='user-answer'><strong>Your Answer:</strong>";
                                
                                if ($question['question_type'] === 'enumeration') {
                                    $user_answers = is_array($user_answer) ? 
                                        $user_answer : 
                                        array_map('trim', explode(',', $user_answer));
                                    
                                    foreach ($user_answers as $user_ans) {
                                        $is_correct = false;
                                        foreach ($question['answers'] as $correct_answer) {
                                            if (strcasecmp(trim($user_ans), trim($correct_answer['individual_answer'])) === 0) {
                                                $is_correct = true;
                                                break;
                                            }
                                        }
                                        $color = $is_correct ? 'green' : 'red';
                                        $mark = $is_correct ? '✓' : '✗';
                                        echo "<div class='individual-answer' style='color: $color; font-weight: bold;'>" . 
                                            htmlspecialchars($user_ans) . " $mark</div>";
                                    }
                                } else {
                                    $color = $is_wrong ? 'red' : 'green';
                                    $mark = $is_wrong ? '✗' : '✓';
                                    echo "<div class='individual-answer' style='color: $color; font-weight: bold;'>" . 
                                        htmlspecialchars($user_answer) . " $mark</div>";
                                }
                                echo "</div>";
                            } else {
                                echo "<div class='user-answer'><strong>Your Answer:</strong> ";
                                echo "<div class='individual-answer' style='color: red; font-weight: bold;'>No answer recorded ✗</div>";
                                echo "</div>";
                            }
                        ?>
                    </div>
                <?php else: ?>
                    <!-- Enumeration/Identification/Fill in the blanks question display -->
                    <div class="answers">
                        <?php 
                            echo "<div class='correct-answers'><strong>Correct Answer(s):</strong>";
                            foreach ($question['answers'] as $answer) {
                                echo "<div class='individual-answer' style='color: green; font-weight: 600;'>" . 
                                    htmlspecialchars($answer['individual_answer']) . " ✓</div>";
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

                                error_log("Found answer for question {$question['question_id']}: $user_actual_answer");
                                
                                $is_wrong = isset($wrong_answers[$question['question_id']]);
                                
                                echo "<div class='user-answer'><strong>Your Answer:</strong>";
                                
                                if ($question['question_type'] === 'enumeration') {
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
                                } else {
                                    $color = $is_wrong ? 'red' : 'green';
                                    $mark = $is_wrong ? '✗' : '✓';
                                    echo "<div class='individual-answer' style='color: $color; font-weight: bold;'>" . 
                                        htmlspecialchars($user_actual_answer) . " $mark</div>";
                                }
                                echo "</div>";
                            } else {
                                error_log("No answer found for question {$question['question_id']} and student $student_id");
                                echo "<div class='user-answer'><strong>Your Answer:</strong> ";
                                echo "<div class='individual-answer' style='color: red; font-weight: bold;'>No answer recorded ✗</div>";
                                echo "</div>";
                            }
                            $user_answer_stmt->close();
                        ?>
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

unset($_SESSION['quiz_result']);

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
