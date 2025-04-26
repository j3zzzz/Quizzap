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

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
   echo json_encode(["success" => false, "error" => "Connection failed: " . $conn->connect_error]);
   exit;
}

// Check if student is logged in and fetch account_number
if (!isset($_SESSION['account_number'])) {
    echo json_encode(["success" => false, "error" => "User not logged in or account number missing from session."]);
    exit;
}
$account_number = $_SESSION['account_number'];

// Fetch student_id from the database using account_number
$sql = "SELECT student_id FROM students WHERE account_number = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(["success" => false, "error" => "Failed to prepare statement: " . $conn->error]);
    exit;
}
$stmt->bind_param("s", $account_number);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $student_id = $row['student_id'];
} else {
    echo json_encode(["success" => false, "error" => "Student not found."]);
    exit;
}
$stmt->close();

// Get JSON input data
$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    echo json_encode(["success" => false, "error" => "Invalid input data."]);
    exit;
}

$answers = $data['answers'];
$quiz_id = $data['quiz_id'];
$is_final_submit = !($data['partial_submit'] ?? false);

// Validate required parameters
if (!$quiz_id) {
    echo "Quiz ID is missing.", $conn;
}

// Detect partial submission with enhanced methods
function detectPartialSubmit() {
    $partialSubmit = false;

    // Check browser back button or refresh
    if (isset($_SERVER['HTTP_CACHE_CONTROL']) && 
        (strpos($_SERVER['HTTP_CACHE_CONTROL'], 'max-age=0') !== false || 
         strpos($_SERVER['HTTP_CACHE_CONTROL'], 'no-cache') !== false)) {
        $partialSubmit = true;
    }

    // Check for browser navigation events
    if (isset($_SERVER['HTTP_SEC_FETCH_MODE']) && 
        $_SERVER['HTTP_SEC_FETCH_MODE'] === 'navigate') {
        $partialSubmit = true;
    }

    // Check browser referrer
    if (isset($_SERVER['HTTP_REFERER']) && 
        parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH) !== parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)) {
        $partialSubmit = true;
    }

    return $partialSubmit;
}

// If no answers submitted and detected as partial submit
$is_partial_submit = detectPartialSubmit() && empty($answers);

if ($is_partial_submit) {
    // Fetch quiz details for partial submission
    $sql = "SELECT 
                q.quiz_type, 
                q.quiz_name,
                COUNT(que.question_id) as total_questions,
                MAX(que.quiz_duration) as quiz_duration
            FROM quizzes q
            LEFT JOIN questions que ON q.quiz_id = que.quiz_id
            WHERE q.quiz_id = ?
            GROUP BY q.quiz_id, q.quiz_type, q.quiz_name";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $quiz_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo "Quiz not found or no questions available.", $conn;
    }

    $quizDetails = $result->fetch_assoc();
    $stmt->close();

    // Log partial attempt
    $sql_log = "INSERT INTO quiz_partial_attempts 
                (student_id, quiz_id, account_number, created_at) 
                VALUES (?, ?, ?, NOW())";
    $stmt_log = $conn->prepare($sql_log);
    $stmt_log->bind_param("iis", $student_id, $quiz_id, $account_number);
    $stmt_log->execute();
    $stmt_log->close();

    // Return partial submission details
    echo json_encode([
        "success" => true,
        "quiz_id" => $quiz_id,
        "quiz_name" => $quizDetails['quiz_name'],
        "quiz_type" => $quizDetails['quiz_type'],
        "total_questions" => $quizDetails['total_questions'],
        "quiz_duration" => $quizDetails['quiz_duration'] ?? null,
        "partial_submit" => true,
        "message" => "Partial submission detected. Resume or restart quiz."
    ]);
    $conn->close();
    exit;
}

// Retrieve the quiz type from the database
$sql = "SELECT quiz_type FROM quizzes WHERE quiz_id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(["success" => false, "error" => "Failed to prepare statement: " . $conn->error]);
    exit;
}

$stmt->bind_param("i", $quiz_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $quiz_type = $row['quiz_type'];
} else {
    echo json_encode(["success" => false, "error" => "Quiz not found."]);
    exit;
}

$stmt->close();

$score = 0;
$total = count($answers);
$wrong_answers = [];

foreach ($answers as $question_id => $answer) {
    $is_correct = 0;

    if ($quiz_type === 'True or False' || $quiz_type === 'Multiple Choice' || $quiz_type === 'Drag & Drop') {
        // For True or False Multiple Choice and Drag and Drop/
        $sql = "SELECT is_correct FROM answers WHERE answer_id = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            echo json_encode(["success" => false, "error" => "Failed to prepare statement: " . $conn->error]);
            exit;
        }
        $stmt->bind_param("i", $answer);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $is_correct = ($row['is_correct'] === 1) ? 1 : 0;
            if ($row['is_correct'] == 1) {
                $score++;
            } else {
                $wrong_answers[$question_id] = $answer;
            }
        } else {
            echo json_encode(["success" => false, "error" => "Answer not found."]);
            exit;
        }
        $stmt->close();

    } elseif ($quiz_type === 'Enumeration' || $quiz_type === 'Fill in the Blanks' || $quiz_type == 'Identification') {
        // For Enumeration type
        $sql = "SELECT answer_text FROM answers WHERE question_id = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            echo json_encode(["success" => false, "error" => "Failed to prepare statement: " . $conn->error]);
            exit;
        }
        $stmt->bind_param("i", $question_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $correct_answers = explode(',', $row['answer_text']);
            $submitted_answers = explode(',', $answer);

            // Trim and make both arrays lowercase for case-insensitive comparison
            $correct_answers = array_map('strtolower', array_map('trim', $correct_answers));
            $submitted_answers = array_map('strtolower', array_map('trim', $submitted_answers));

            // Compare submitted answers with correct answers (case-insensitive)
            $correct_count = count(array_uintersect($correct_answers, $submitted_answers, 'strcasecmp'));
            $is_correct = ($correct_count == count($correct_answers)) ? 1 : 0;

            $score += $correct_count;
            if ($correct_count != count($correct_answers)) {
                $wrong_answers[$question_id] = $answer;
            }
        } else {
            echo json_encode(["success" => false, "error" => "Question not found."]);
            exit;
        }
        $stmt->close();

    } elseif ($quiz_type === 'Matching Type') {
        // For Matching type
        $sql = "SELECT matching_config FROM questions WHERE question_id = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            echo json_encode(["success" => false, "error" => "Failed to prepare statement: " . $conn->error]);
            exit;
        }
        $stmt->bind_param("i", $question_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            // Assuming matching_config is stored as a JSON string
            $correct_mappings = json_decode($row['matching_config'], true);

            // Check if the submitted answer matches the correct mapping
            if (isset($correct_mappings[$answer])) {
                $is_correct = 1;
                $score++;
            } else {
                $wrong_answers[$question_id] = $answer;
            }
        } else {
            echo json_encode(["success" => false, "error" => "Question not found."]);
            exit;
        }
        $stmt->close();
    }

    // Insert the student's answer into the student_answers table for ALL quiz types
    $sql_insert = "INSERT INTO student_answers (student_id, quiz_id, question_id, answer, is_correct) 
                    VALUES (?, ?, ?, ?, ?)";
    $stmt_insert = $conn->prepare($sql_insert);
    if (!$stmt_insert) {
        echo json_encode(["success" => false, "error" => "Failed to prepare statement for inserting answers: " . $conn->error]);
        exit;
    }
    $stmt_insert->bind_param("iiisi", $student_id, $quiz_id, $question_id, $answer, $is_correct);
    $stmt_insert->execute();
    $stmt_insert->close();    
}

if ($is_final_submit) {
    $sql = "INSERT INTO quiz_attempts (quiz_id, account_number, score) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(["success" => false, "error" => "Failed to prepare statement: " . $conn->error]);
        exit;
    }
    $stmt->bind_param("isi", $quiz_id, $account_number, $score);
    if (!$stmt->execute()) {
        echo json_encode(["success" => false, "error" => "Failed to execute statement: " . $stmt->error]);
        exit;
    }
}

echo json_encode([
    "success" => true,
    "score" => $score,
    "total" => $total,
    "wrong_answers" => $wrong_answers,
    "is_final_submit" => $is_final_submit
]);

$conn->close();
?>
