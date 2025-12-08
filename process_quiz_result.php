<?php
session_start();

// Check if we have POST data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if ($data && isset($data['score'], $data['total'], $data['quiz_id'])) {
        // Store in session
        $_SESSION['quiz_result'] = [
            'score' => $data['score'],
            'total' => $data['total'],
            'quiz_id' => $data['quiz_id'],
            'wrong_answers' => $data['wrong_answers'] ?? [],
            'subject_id' => $data['subject_id'] ?? null
        ];
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid data']);
    }
} else {
    // Redirect if accessed directly
    header("Location: select_quiz.php");
    exit();
}
?>