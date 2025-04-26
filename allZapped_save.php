<?php
session_start();

// Check if user is a teacher
if (!isset($_SESSION['account_number']) || !str_starts_with($_SESSION['account_number'], 'T')) {
    die(json_encode(['success' => false, 'message' => 'Unauthorized access']));
}

// Database connection
$conn = new mysqli('localhost', 'root', '', 'rawrit');
if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => 'Database connection failed']));
}

try {
    // Validate basic quiz information
    $subject_id = filter_input(INPUT_POST, 'subject_id', FILTER_VALIDATE_INT);
    $title = trim($_POST['title'] ?? '');
    $timer = filter_input(INPUT_POST, 'timer', FILTER_VALIDATE_INT);

    if (!$subject_id || empty($title) || !$timer) {
        throw new Exception('Missing required quiz information');
    }

    // Start transaction
    $conn->begin_transaction();

    // Create quiz
    $quiz = $conn->prepare("INSERT INTO quizzes (subject_id, title, timer) VALUES (?, ?, ?)");
    $quiz->bind_param("iss", $subject_id, $title, $timer);
    $quiz->execute();
    $quiz_id = $conn->insert_id;

    // Process questions
    $questions = $_POST['questions'] ?? [];
    $types = $_POST['question_type'] ?? [];

    if (empty($questions)) {
        throw new Exception('No questions provided');
    }

    foreach ($questions as $index => $question_text) {
        if (empty(trim($question_text))) continue;

        // Add question
        $question = $conn->prepare("INSERT INTO questions (quiz_id, type, question_text) VALUES (?, ?, ?)");
        $question->bind_param("iss", $quiz_id, $types[$index], $question_text);
        $question->execute();
        $question_id = $conn->insert_id;

        // Add answers/options
        $option = $conn->prepare("INSERT INTO question_options (question_id, option_text, is_correct) VALUES (?, ?, ?)");
        
        switch ($types[$index]) {
            case 'multiple_choice':
                foreach ($_POST['options'][$index] ?? [] as $opt_index => $opt_text) {
                    if (empty(trim($opt_text))) continue;
                    $is_correct = ($opt_index + 1 == ($_POST['correct_option'][$index] ?? 0)) ? 1 : 0;
                    $option->bind_param("isi", $question_id, $opt_text, $is_correct);
                    $option->execute();
                }
                break;

            case 'true_false':
            case 'enumeration':
            case 'fill_in_the_blanks':
            case 'drag_and_drop':
            case 'identification':
                $correct_answer = trim($_POST['correct_option'][$index] ?? '');
                if (!empty($correct_answer)) {
                    $is_correct = 1;
                    $option->bind_param("isi", $question_id, $correct_answer, $is_correct);
                    $option->execute();
                }
                break;

            case 'matching_type':
                $lefts = array_map('trim', explode(',', $_POST['left_items'][$index] ?? ''));
                $rights = array_map('trim', explode(',', $_POST['right_items'][$index] ?? ''));
                
                foreach ($lefts as $i => $left) {
                    if (empty($left) || empty($rights[$i] ?? '')) continue;
                    $match_pair = "$left - {$rights[$i]}";
                    $is_correct = 1;
                    $option->bind_param("isi", $question_id, $match_pair, $is_correct);
                    $option->execute();
                }
                break;
        }
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Quiz created successfully', 'quiz_id' => $quiz_id]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    $conn->close();
}