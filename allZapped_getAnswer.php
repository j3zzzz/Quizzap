<?php
// Set JSON header first
header('Content-Type: application/json');

// Prevent any accidental output
error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    // Validate input first
    if (!isset($_GET['question_id']) || !is_numeric($_GET['question_id'])) {
        throw new Exception("Invalid or missing question_id parameter");
    }

    $question_id = (int)$_GET['question_id'];

    // Database connection
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "rawrit";
    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // Prepare statement to prevent SQL injection
    $stmt = $conn->prepare("SELECT q.question_type, a.* FROM answers a 
                            JOIN questions q ON a.question_id = q.question_id 
                            WHERE a.question_id = ?");
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("i", $question_id);

    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $result = $stmt->get_result();

    $answers = [];
    $question_type = null;

    while ($row = $result->fetch_assoc()) {
        // Store question type
        $question_type = $row['question_type'];
        
        // Remove question_type from the answer data
        unset($row['question_type']);
        $answers[] = $row;
    }

    // For matching type, ensure side is added if not present
    if ($question_type === 'matching_type') {
        foreach ($answers as &$answer) {
            if (!isset($answer['side'])) {
                // Check if you have an 'is_left' field
                if (isset($answer['is_left'])) {
                    $answer['side'] = ($answer['is_left'] == 1) ? 'left' : 'right';
                } else {
                    // Fallback: alternate between left and right
                    static $counter = 0;
                    $answer['side'] = ($counter % 2 == 0) ? 'left' : 'right';
                    $counter++;
                }
            }
        }
    }

    $stmt->close();
    $conn->close();

    // Send JSON response
    echo json_encode($answers);
 
} catch (Exception $e) {
    // Log error for debugging
    error_log("Error in allZapped_getAnswer.php: " . $e->getMessage());
    
    // Send error response
    http_response_code(500);
    echo json_encode([
        "error" => true,
        "message" => $e->getMessage()
    ]);
}
?>  