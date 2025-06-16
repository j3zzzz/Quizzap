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

// Extract student_id from session (assuming account_number format is like "S001", "S002", etc.)
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

// Function to get user answer from database
function getUserAnswerFromDatabase($conn, $student_id, $question_id) {
    $sql = "SELECT answer FROM student_answers WHERE student_id = ? AND question_id = ? ORDER BY answered_at DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $student_id, $question_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['answer'];
    }
    return null;
}

// Fetch quiz type galing sa quizzes table
$quiz_type_sql = "SELECT quiz_type FROM quizzes WHERE quiz_id = ?";
$quiz_type_stmt = $conn->prepare($quiz_type_sql);

// Check if preparation was successful
if ($quiz_type_stmt === false) {
    // If preparation fails, output the specific error
    die("Prepare failed: (" . $conn->errno . ") " . $conn->error);
}

$quiz_type_stmt->bind_param("i", $quiz_id);
$quiz_type_stmt->execute();
$quiz_type_result = $quiz_type_stmt->get_result(); 
$quiz_type_row = $quiz_type_result->fetch_assoc();
$quiz_type = $quiz_type_row['quiz_type'] ?? null;
$quiz_type_stmt->close();

// pang calculate ng total scores based sa correct answers for both allzapped and specific quiz types
$total_correct_answers = 0;

if ($quiz_type) {
    
    if ($quiz_type === 'Enumeration') {
        // For enumeration quizzes, count all possible correct answers
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

                // If this question was answered correctly (not in wrong_answers)
                if (!isset($wrong_answers[$row['question_id']])) {
                    $enum_score += count($correct_answers); // Add points for each correct answer
                }
            }
        }
        $adjusted_score = $enum_score;
        $total_stmt->close();
    } else {
        // If quiz has mixed question types
        $total_sql = "SELECT q.question_id, q.question_type, a.answer_text 
                      FROM questions q 
                      LEFT JOIN answers a ON q.question_id = a.question_id
                      WHERE quiz_id = ? AND a.is_correct = 1";

        $total_stmt = $conn->prepare($total_sql);
        $total_stmt->bind_param("i", $quiz_id);
        $total_stmt->execute();
        $total_result = $total_stmt->get_result();

        while ($row = $total_result->fetch_assoc()) {
            if (!empty($row['answer_text'])) {
                if ($row['question_type'] === 'enumeration') {
                    $correct_answers = explode(',', $row['answer_text']);
                    $total_correct_answers += count($correct_answers);
                } else {
                    // For other question types, just add 1
                    $total_correct_answers++;
                }
            }
        }              
        $total_stmt->close();
    }
}

$display_score = ($quiz_type === 'Enumeration') ? $adjusted_score : $score; 

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
        // For Multiple Choice, True/False, and Drag & Drop - don't split answers
        if (in_array($quiz_type, ['Multiple Choice', 'True or False', 'Drag & Drop'])) {
            // Clean answer text
            $cleaned_answer = preg_replace('/^[\[\]"\']+|[\[\]"\']+$/', '', $answer_row['answer_text']);
            $answer_row['individual_answer'] = trim($cleaned_answer);
            $answers[] = $answer_row;
        } else {
            // For enumeration - keep the splitting logic
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
$stmt->close();

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

        .options {
            height: fit-content;
            width: 90%;
            margin: auto;
        }

        #quizzes {
            float: left;
        }

        #rankings {
            float: right;
            margin-right: 5%;
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

        h1{
            font-family: Purple Smile;
            font-size: 30px;
            color: white;
            letter-spacing: 1px;    
        }

        h2{
            font-family: Tilt Warp Regular;
        }

        a{
            float: left;
            margin-top: 3%;
            margin-left: 5%;
            text-decoration: none;
            font-size: 20px;
            font-family: Tilt Warp Regular;
            color: #605F5F;
        }

        .score{
            float: right;
            color: #f8b500;
            font-family: Tilt Warp Regular;
            font-size: 22px;
            margin-top: -2%;
        }

        .question{
            font-family: Tilt Warp Regular;
        }

        .question p {
            margin-left: -2%;
        }

        .qstn{
            font-size: 22px;
        }

        .qstn-con{
            width: 100%;
            border-radius: 15px;
            border: 2px solid #f8b500;
            padding: 30px;
            margin-bottom: 10px;
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

        .individual-answer {
            padding: 5px 10px;
            margin: 3px 0;
            border-radius: 3px;
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
                        // Check if user got this question wrong
                        $user_got_wrong = isset($wrong_answers[$question['question_id']]);
                        
                        if ($user_got_wrong) {
                            // User got it wrong - show all options with proper coloring
                            $user_answer_data = $wrong_answers[$question['question_id']];
                            $user_selected_answer_id = null;
                            $user_selected_answer_text = null;
                            
                            // Get user's selected answer
                            if (is_array($user_answer_data)) {
                                if (isset($user_answer_data['answer_id'])) {
                                    $user_selected_answer_id = $user_answer_data['answer_id'];
                                }
                                if (isset($user_answer_data['answer_text'])) {
                                    $user_selected_answer_text = $user_answer_data['answer_text'];
                                }
                            } else {
                                $user_selected_answer_text = $user_answer_data;
                            }
                            
                            // Display all answers with appropriate styling
                            foreach ($question['answers'] as $answer) {
                                $answer_style = '';
                                $answer_marker = '';
                                $is_user_selection = false;
                                
                                // Check if this is the user's selected answer
                                if ($user_selected_answer_id && $user_selected_answer_id == $answer['answer_id']) {
                                    $is_user_selection = true;
                                } elseif ($user_selected_answer_text && trim($user_selected_answer_text) === trim($answer['individual_answer'])) {
                                    $is_user_selection = true;
                                }
                                
                                if ($answer['is_correct'] == 1) {
                                    // Correct answer - green with checkmark
                                    $answer_style = 'color: green; font-weight: bold;';
                                    $answer_marker = ' ✓';
                                } elseif ($is_user_selection) {
                                    // User's wrong selection - red with X
                                    $answer_style = 'color: red; font-weight: bold;';
                                    $answer_marker = ' (Your answer)';
                                } else {
                                    // Other incorrect options - black (neutral)
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
                            // User got it correct - only show the correct answer in green
                            foreach ($question['answers'] as $answer) {
                                if ($answer['is_correct'] == 1) {
                                    echo '<div class="individual-answer">';
                                    echo '<span style="color: green; font-weight: bold;">';
                                    echo htmlspecialchars($answer['individual_answer']) . ' ✓';
                                    echo '</span>';
                                    echo '</div>';
                                    break; // Only show one correct answer
                                }
                            }
                        }
                        ?>
                    </div>
                <?php else: ?>
                    <!-- Enumeration/Identification/Fill in the blanks question display -->
                    <div class="answers">
                        <?php 
                            // Display correct answers
                            echo "<div class='correct-answers'><strong>Correct Answer(s):</strong>";
                            foreach ($question['answers'] as $answer) {
                                echo "<div class='individual-answer' style='color: green; font-weight: bold;'>" . 
                                    htmlspecialchars($answer['individual_answer']) . " ✓</div>";
                            }
                            echo "</div>";

                            // Get user's actual answer from database using the quiz_id as well
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
                                //$is_correct = $user_answer_row['is_correct'];

                                // Debug output - remove in production
                                error_log("Found answer for question {$question['question_id']}: $user_actual_answer");
                                
                                // Check if this question is in wrong_answers array
                                $is_wrong = isset($wrong_answers[$question['question_id']]);
                                
                                echo "<div class='user-answer'><strong>Your Answer:</strong>";
                                
                                // For enumeration questions, split the answer by commas
                                if ($question['question_type'] === 'enumeration') {
                                    $user_answers = array_map('trim', explode(',', $user_actual_answer));
                                    
                                    // Get correct answers for comparison
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
                                } 
                                // For identification and fill-in-the-blanks
                                else {
                                    $color = $is_wrong ? 'red' : 'green';
                                    $mark = $is_wrong ? '✗' : '✓';
                                    echo "<div class='individual-answer' style='color: $color; font-weight: bold;'>" . 
                                        htmlspecialchars($user_actual_answer) . " $mark</div>";
                                }
                                echo "</div>";
                            } else {
                                error_log("No answer found for question {$question['question_id']} and student $student_id");
                                // No answer found in database
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
// Close connection at the very end
$conn->close();
?>

</body>
</html>