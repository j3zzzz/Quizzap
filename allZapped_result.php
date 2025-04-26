<?php
session_start();

// Ensure only logged-in users can access
if (!isset($_SESSION['account_number'])) {
    header("Location: login.php");
    exit();
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "rawrit";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Validate attempt_id
if (!isset($_GET['attempt_id'])) {
    die("No quiz attempt selected");
}

$attempt_id = intval($_GET['attempt_id']);

// Fetch quiz attempt details
$attempt_stmt = $conn->prepare("
    SELECT qa.*, q.title AS quiz_title 
    FROM quiz_attempts qa
    JOIN quizzes q ON qa.quiz_id = q.quiz_id
    WHERE qa.attempt_id = ? AND qa.student_id = ?
");
$attempt_stmt->bind_param("is", $attempt_id, $_SESSION['account_number']);
$attempt_stmt->execute();
$attempt_result = $attempt_stmt->get_result();

if ($attempt_result->num_rows === 0) {
    die("Quiz attempt not found");
}

$attempt = $attempt_result->fetch_assoc();

// Fetch student answers with question details
$answers_stmt = $conn->prepare("
    SELECT 
        sa.*, 
        q.question_text, 
        q.question_type,
        a.correct_answer AS system_answer
    FROM student_answers sa
    JOIN questions q ON sa.question_id = q.question_id
    LEFT JOIN answers a ON q.question_id = a.question_id AND a.is_correct = 1
    WHERE sa.quiz_attempt_id = ?
    ORDER BY sa.answer_id
");
$answers_stmt->bind_param("i", $attempt_id);
$answers_stmt->execute();
$answers_result = $answers_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Quiz Results</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .result-container {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .result-header {
            text-align: center;
            margin-bottom: 20px;
        }
        .score {
            font-size: 24px;
            color: #4CAF50;
            text-align: center;
        }
        .questions-list {
            margin-top: 20px;
        }
        .question-result {
            margin-bottom: 15px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .correct {
            background-color: #e8f5e9;
            border-color: #4CAF50;
        }
        .incorrect {
            background-color: #ffebee;
            border-color: #F44336;
        }
    </style>
</head>
<body>
    <div class="result-container">
        <div class="result-header">
            <h1>Quiz Results</h1>
            <h2><?php echo htmlspecialchars($attempt['quiz_title']); ?></h2>
        </div>

        <div class="score">
            Score: <?php 
                printf("%.2f / %.2f (%.2f%%)", 
                    $attempt['score'], 
                    $attempt['total_points'], 
                    ($attempt['score'] / $attempt['total_points']) * 100
                ); 
            ?>
        </div>

        <div class="questions-list">
            <?php while ($answer = $answers_result->fetch_assoc()): ?>
                <div class="question-result <?php echo $answer['is_correct'] ? 'correct' : 'incorrect'; ?>">
                    <h3>Question: <?php echo htmlspecialchars($answer['question_text']); ?></h3>
                    <p><strong>Your Answer:</strong> <?php echo htmlspecialchars($answer['student_answer'] ?? 'No answer'); ?></p>
                    <?php if (!$answer['is_correct']): ?>
                        <p><strong>Correct Answer:</strong> <?php echo htmlspecialchars($answer['system_answer'] ?? 'Not specified'); ?></p>
                    <?php endif; ?>
                    <p>
                        <strong>Points:</strong> 
                        <?php printf("%.2f / %.2f", $answer['points_earned'], $answer['points_earned'] > 0 ? $answer['points_earned'] : 0); ?>
                    </p>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
</body>
</html>

<?php
// Close connections
$attempt_stmt->close();
$answers_stmt->close();
$conn->close();
?>