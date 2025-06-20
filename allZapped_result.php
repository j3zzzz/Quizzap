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

// Function to check if answer is JSON matching format
function isJsonMatchingFormat($answer) {
    $decoded = json_decode($answer, true);
    if (!is_array($decoded) || empty($decoded)) {
        return false;
    }
    
    // Check if first item has matching structure
    $first = $decoded[0];
    return isset($first['left']) || isset($first['leftText']) || 
           (isset($first['leftText']) && isset($first['rightText']));
}

// Function to parse matching answers
function parseMatchingAnswer($answer) {
    $decoded = json_decode($answer, true);
    $matches = [];
    
    if (is_array($decoded)) {
        foreach ($decoded as $match) {
            $leftText = '';
            $rightText = '';
            
            // Handle different JSON formats
            if (isset($match['leftText'], $match['rightText'])) {
                $leftText = $match['leftText'];
                $rightText = $match['rightText'];
            } elseif (isset($match['left'], $match['right'])) {
                $leftText = $match['left'];
                $rightText = $match['right'];
            }
            
            // Clean up the text by removing numbering/lettering
            $leftText = preg_replace('/^\d+\.\s*/', '', $leftText);
            $rightText = preg_replace('/^[A-Z]\.\s*/', '', $rightText);
            
            if (!empty($leftText) && !empty($rightText)) {
                $matches[] = [
                    'left' => trim($leftText),
                    'right' => trim($rightText)
                ];
            }
        }
    }
    
    return $matches;
}
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
        .matching-item {
            margin: 5px 0;
            padding: 5px;
            background-color: rgba(0,0,0,0.05);
            border-radius: 3px;
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
        
        <?php 
        // Always check if answer contains JSON matching data first
        $student_answer = $answer['student_answer'] ?? '';
        $is_json_matching = false;
        $student_matches = [];
        
        // Check if the answer looks like JSON matching format
        if (strpos($student_answer, '"leftText"') !== false && strpos($student_answer, '"rightText"') !== false) {
            $decoded = json_decode($student_answer, true);
            if (is_array($decoded)) {
                $is_json_matching = true;
                foreach ($decoded as $match) {
                    if (isset($match['leftText'], $match['rightText'])) {
                        // Clean up the text by removing numbering/lettering
                        $leftText = preg_replace('/^\d+\.\s*/', '', $match['leftText']);
                        $rightText = preg_replace('/^[A-Z]\.\s*/', '', $match['rightText']);
                        
                        $student_matches[] = [
                            'left' => trim($leftText),
                            'right' => trim($rightText)
                        ];
                    }
                }
            }
        }
        
        if ($is_json_matching || $answer['question_type'] === 'matching_type' || $answer['question_type'] === 'matching'): 
            // Get correct matches from database
            $correct_matches = [];
            $sql = "SELECT answer_text FROM answers WHERE question_id = ? AND is_correct = 1";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $answer['question_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $parts = explode('|', $row['answer_text']);
                if (count($parts) >= 2) {
                    $correct_matches[] = [
                        'left' => trim($parts[0]),
                        'right' => trim($parts[1])
                    ];
                }
            }
        ?>
            
            <p><strong>Your Answer:</strong></p>
            <?php if (!empty($student_matches)): ?>
                <div>
                    <?php foreach ($student_matches as $match): ?>
                        <div class="matching-item">
                            <?php echo htmlspecialchars($match['left']) . " - " . htmlspecialchars($match['right']); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="matching-item">No matches submitted</div>
            <?php endif; ?>
            
            <?php if (!$answer['is_correct'] && !empty($correct_matches)): ?>
                <p><strong>Correct Answer:</strong></p>
                <div>
                    <?php foreach ($correct_matches as $match): ?>
                        <div class="matching-item">
                            <?php echo htmlspecialchars($match['left']) . " - " . htmlspecialchars($match['right']); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <!-- Regular question types -->
            <p><strong>Your Answer:</strong> <?php echo htmlspecialchars($student_answer); ?></p>
            <?php if (!$answer['is_correct']): ?>
                <p><strong>Correct Answer:</strong> <?php echo htmlspecialchars($answer['system_answer'] ?? 'Not specified'); ?></p>
            <?php endif; ?>
        <?php endif; ?>
        
        <p>
            <strong>Points:</strong> 
            <?php printf("%.2f / %.2f", $answer['points_earned'], $answer['points_possible']); ?>
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