<?php
session_start();
if (!isset($_SESSION['account_number']) || strpos($_SESSION['account_number'], 'S') !== 0) {
    header("Location: login.php");
    exit();
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "rawrit"; 

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$loggedInUser = $_SESSION['account_number'];

//query para sa profile pic
$sql = "SELECT profile_pic FROM students WHERE account_number = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $loggedInUser);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $profile_pic = $row['profile_pic'] ? $row['profile_pic'] : 'uploads/profiles/default-profile.jpg';
} else {
    $profile_pic = 'uploads/profiles/default-profile.jpg';
}

$stmt->close();

//query to fetch the student_id that will be used in any of the data sa mga cards
$stud_id_sql = $conn->prepare
            ("SELECT student_id 
              FROM students
              WHERE  account_number = ?;
            ");
$stud_id_sql->bind_param("s", $loggedInUser);
$stud_id_sql->execute();
$stud_result = $stud_id_sql->get_result();

if ($stud_result->num_rows > 0) {
    $student_row = $stud_result->fetch_assoc();
    $student_id = $student_row['student_id'];

    //query para sa quiz na di pa natatake
    $not_taken_sql = $conn->prepare
                    ("SELECT DISTINCT s.subject_name, q.title, q.quiz_id
                    FROM quizzes q
                    JOIN subjects s ON q.subject_id = s.subject_id
                    JOIN enrollments e ON q.subject_id = e.subject_id
                    LEFT JOIN quiz_attempts qa ON q.quiz_id = qa.quiz_id AND qa.account_number = ?
                    WHERE e.student_id = ? AND qa.attempt_id IS NULL
                    ORDER BY q.quiz_id DESC");
    $not_taken_sql->bind_param("si", $loggedInUser, $student_id);

    // Check if execute() failed
    if (!$not_taken_sql->execute()) {
        echo "Execute failed: " . $not_taken_sql->error;
        exit;
    }

    $not_taken_result = $not_taken_sql->get_result();
    
    //query para sa latest high score
    $latest_high_score_sql = $conn->prepare
    ("SELECT s.subject_name, 
             q.title AS quiz_title, 
             MAX(qa.score) AS highest_score, 
             qa.attempt_time AS latest_attempt_date
      FROM quiz_attempts qa
      JOIN quizzes q ON qa.quiz_id = q.quiz_id
      JOIN subjects s ON q.subject_id = s.subject_id
      WHERE qa.account_number = ?
      GROUP BY s.subject_name, q.title
      HAVING highest_score > 0
      ORDER BY latest_attempt_date DESC
      LIMIT 5"); // Limit to top 5 latest high scores
    if ($latest_high_score_sql === false) {
        die("Prepare failed for latest high score query: " . $conn->error);
    }  
    $latest_high_score_sql->bind_param("s", $loggedInUser);

    // Check if execute() failed
    if (!$latest_high_score_sql->execute()) {
    echo "Execute failed: " . $latest_high_score_sql->error;
    exit;
    }

    $latest_high_score_result = $latest_high_score_sql->get_result();
} else {
    echo "No student found for this account.";
    $not_taken_result = null;
}

// Query for difficult questions
$difficult_questions_sql = $conn->prepare(
    "SELECT s.subject_name, 
            q.title AS quiz_title, 
            qq.question_text,
            COUNT(*) AS total_attempts,
            MIN(qa.score) AS lowest_score
    FROM quiz_attempts qa
    JOIN quizzes q ON qa.quiz_id = q.quiz_id
    JOIN subjects s ON q.subject_id = s.subject_id
    JOIN questions qq ON qa.quiz_id = qq.quiz_id
    WHERE qa.account_number = ?
      AND qa.score < (SELECT MAX(score) FROM quiz_attempts WHERE quiz_id = quiz_id AND account_number = ?)
    GROUP BY s.subject_name, q.title, qq.question_text
    HAVING total_attempts > 1 AND lowest_score <= 0.5 * (SELECT MAX(score) FROM quiz_attempts WHERE quiz_id = quiz_id AND account_number = ?)
    ORDER BY total_attempts DESC, lowest_score ASC
    LIMIT 5"
);
if ($difficult_questions_sql === false) {
    die("Prepare failed for difficult questions query: " . $conn->error);
}
$difficult_questions_sql->bind_param("sss", $loggedInUser, $loggedInUser, $loggedInUser);

// Check if execute() failed
if (!$difficult_questions_sql->execute()) {
    echo "Execute failed: " . $difficult_questions_sql->error;
    exit;
}

$difficult_questions_result = $difficult_questions_sql->get_result();

$conn->close();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" type="text/css" href="other resources/fontawesome-free-6.5.2-web/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <title>QuizZap Dashboard</title>
    <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: 'Fredoka';
    }

    body, html {
        font-family: 'Fredoka';
        height: 100%;
        transition: background-color 0.3s, color 0.3s;
        overflow-x: hidden;
    }

    body.dark-mode {
        background-color: #1a1a1a;
        color: #e0e0e0;
    }

    .container {
        font-family: 'Fredoka';
        display: flex;
        min-height: 100vh;
        flex-direction: column;
        width: 100%;
    }

    /* Sidebar styling */
    .sidebar {
        position: fixed;
        width: 250px;
        height: 100vh;
        background-color: #f8b500;
        color: #ffffff;
        padding: 2rem 1rem;
        display: flex;
        flex-direction: column;
        justify-content: flex-start;
        transition: all 0.3s ease;
        z-index: 1000;
        overflow-y: auto;
    }

    body.dark-mode .sidebar {
        background-color: #333;
    }

    .sidebar.collapsed {
        width: 70px;
        padding: 2rem 0.5rem;
    }

    .sidebar .logo {
        margin-bottom: 1rem;
        margin-left: 5%;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-shrink: 0;
    }

    .sidebar.collapsed .logo {
        margin-left: 0;
        justify-content: center;
    }

    .toggle-btn {
        background: none;
        border: none;
        color: white;
        font-size: 1.5rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 5px;
        border-radius: 4px;
        transition: background 0.2s;
        min-height: 44px;
        min-width: 44px;
    }

    .toggle-btn:hover {
        background-color: rgba(255, 255, 255, 0.1);
    }

    .sidebar .menu {
        margin-top: 40%;
        display: flex;
        flex-direction: column;
        flex-grow: 1;
        gap: 0.5rem;
    }

    .sidebar.collapsed .menu {
        align-items: center;
        margin-top: 45%;
    }

    .sidebar .menu a {
        color: #ffffff;
        text-decoration: none;
        padding: 1rem;
        display: flex;
        align-items: center;
        font-size: 1rem;
        border-radius: 5px;
        transition: background 0.3s;
        font-family: 'Fredoka';
        letter-spacing: 1px;
        width: 100%;
        min-height: 50px;
    }

    .sidebar.collapsed .menu a {
        justify-content: center;
        padding: 1rem 0;
        width: 90%;
    }

    .sidebar .menu a span {
        margin-left: 0.5rem;
        transition: opacity 0.2s;
        font-family: 'Fredoka';
        font-weight: bold;
        font-size: clamp(16px, 1.5vw, 20px);
    }

    .sidebar.collapsed .menu a span {
        opacity: 0;
        width: 0;
        height: 0;
        overflow: hidden;
        display: none;
    }

    .sidebar .menu a:hover,
    .sidebar .menu a.active {
        background-color: white;
        color: #f8b500;
    }

    body.dark-mode .sidebar .menu a:hover,
    body.dark-mode .sidebar .menu a.active {
        background-color: #444;
        color: #f8b500;
    }

    .sidebar .menu a i {
        margin-right: 0.5rem;
        min-width: 20px;
        text-align: center;
        font-size: clamp(1rem, 1.2vw, 1.5rem);
        flex-shrink: 0;
    }

    .sidebar.collapsed .menu a i {
        margin-right: 0;
        font-size: 1.2rem;
    }

    .sidebar.collapsed .logo-img {
        display: none;
    }

    .sidebar.collapsed .logo-icon {
        display: block !important;
    }

    .sidebar.collapsed hr {
        margin: 0.5rem auto;
        width: 50%;
    }

    /* Top Navigation for ALL screen sizes */
    .top-nav {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        background-color: #f8b500;
        padding: 1rem;
        z-index: 1000;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        align-items: center;
        justify-content: space-between;
        height: 70px;
    }
    
    body.dark-mode .top-nav {
        background-color: #333;
    }
    
    .top-nav .logo {
        display: flex;
        align-items: center;
    }
    
    .top-nav .logo img {
        height: 40px;
        width: auto;
    }
    
    .top-nav .menu {
        display: flex !important;
        position: static;
        flex-direction: row;
        background: none;
        box-shadow: none;
        width: auto;
        padding: 0;
        margin: 0;
        gap: 1.5rem;
    }
    
    .top-nav .menu a {
        color: #ffffff;
        text-decoration: none;
        padding: 0.75rem;
        display: flex;
        align-items: center;
        font-size: 1rem;
        border-radius: 8px;
        transition: background 0.3s;
        min-height: 44px;
        min-width: 44px;
        justify-content: center;
        position: relative;
    }
    
    .top-nav .menu a i {
        font-size: 1.4rem;
        margin-right: 0;
    }
    
    .top-nav .menu a span {
        display: none;
    }
    
    .top-nav .menu a:hover,
    .top-nav .menu a.active {
        background-color: rgba(255, 255, 255, 0.2);
    }
    
    .top-nav .menu a::after {
        content: attr(title);
        position: absolute;
        bottom: -30px;
        left: 50%;
        transform: translateX(-50%);
        background-color: #333;
        color: white;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 0.75rem;
        white-space: nowrap;
        opacity: 0;
        transition: opacity 0.3s;
        pointer-events: none;
    }
    
    .top-nav .menu a:hover::after {
        opacity: 1;
    }
    
    /* Profile in top nav */
    .top-nav-profile {
        display: flex;
        align-items: center;
        position: relative;
    }
    
    .top-nav-profile .profile {
        width: 45px;
        height: 45px;
        background-color: #ffffff;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #f5a623;
        font-size: 1.5rem;
        cursor: pointer;
        flex-shrink: 0;
        position: relative;
        border: 2px solid white;
    }
    
    body.dark-mode .top-nav-profile .profile {
        background-color: #333;
    }
    
    .top-nav-toggle {
        display: none !important;
    }

    /* Dashboard content area */
    .content {
        flex: 1;
        background-color: #ffffff;
        padding: 2rem;
        margin-left: 250px;
        transition: margin-left 0.3s ease, background-color 0.3s;
        width: calc(100% - 250px);
        min-height: 100vh;
    }

    body.dark-mode .content {
        background-color: #1a1a1a;
        color: #e0e0e0;
    }

    .content.expanded {
        margin-left: 70px;
        width: calc(100% - 70px);
    }

    .content-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 2rem;
        flex-wrap: wrap;
        gap: 1rem;
        width: 100%;
    }

    .content-header h1 {
        font-size: clamp(1.5rem, 4vw, 2rem);
        color: #333333;
        font-family: 'Fredoka';
        margin-bottom: 0.5rem;
        line-height: 1.2;
    }

    body.dark-mode .content-header h1 {
        color: #e0e0e0;
    }

    .content-header p {
        color: #999;
        font-size: clamp(0.9rem, 2vw, 1rem);
        margin-top: 0.5rem;
        font-family: 'Fredoka';
        font-weight: 500;
        line-height: 1.4;
    }

    body.dark-mode .content-header p {
        color: #b0b0b0;
    }

    .content-header .actions {
        display: flex;
        align-items: center;
        gap: 1rem;
        flex-shrink: 0;
    }

    .content-header .actions a {
        background-color: #F8B500;
        color: #ffffff;
        border: none;
        padding: 0.5rem 1rem;
        border-radius: 5px;
        font-size: 1rem;
        text-decoration: none;
        cursor: pointer;
        font-family: 'Fredoka';
        white-space: nowrap;
        min-height: 44px;
        display: flex;
        align-items: center;
    }

    .content-header .actions a:hover {
        background-color: #e5941f;
    }

    /* Profile in content header for larger screens */
    .content-header .actions .profile {
        width: 50px;
        height: 50px;
        background-color: #ffffff;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #f5a623;
        font-size: 1.5rem;
        cursor: pointer;
        flex-shrink: 0;
        position: relative;
    }

    body.dark-mode .content-header .actions .profile {
        background-color: #333;
    }

    .cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
        gap: 1.5rem;
        padding-bottom: 2rem;
        width: 100%;
    }

    .quizzes-card {
        background-color: #ffffff;
        padding: 1.5rem;
        border-radius: 10px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        min-height: 300px;
        z-index: 4 !important;
        transition: background-color 0.3s;
        display: flex;
        flex-direction: column;
    }

    body.dark-mode .quizzes-card {
        background-color: #2d2d2d;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
    }

    .quizzes-card #quizzes-card-header {
        margin-bottom: 1rem;
        flex-shrink: 0;
    }

    #quizzes-cont {
        padding: 0.5rem 0;
        overflow: auto;
        flex-grow: 1;
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .quizzes-card .bolt {
        color: #e5941f;
        float: left;
        line-height: 1;
        font-size: clamp(30px, 4vw, 40px);
        margin-right: 2%;
    } 

    .quizzes-card h3 {
        font-family: 'Fredoka';
        font-size: clamp(1.2rem, 2.5vw, 1.5rem);
    }

    body.dark-mode .quizzes-card h3 {
        color: #e0e0e0;
    }

    .quizzes-card h5 {
        font-family: Fredoka;
        color: #999;
        font-weight: lighter;
        font-size: clamp(0.9rem, 1.5vw, 1rem);
    }

    body.dark-mode .quizzes-card h5 {
        color: #b0b0b0;
    }

    #quizzes-cont a {
        background-color: #F8B500;
        text-decoration: none;
        color: white;
        padding: 12px 16px;
        border-radius: 10px;
        display: block;
        width: 100%;
        cursor: pointer;
        border: 3px solid #f8b500;
        font-size: clamp(0.9rem, 1.5vw, 1rem);
        text-align: center;
        min-height: 44px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
    }

    #quizzes-cont a:hover {
        background-color: white;
        color: #F8B500;  
    }

    body.dark-mode #quizzes-cont a:hover {
        background-color: #2d2d2d;
        color: #F8B500;  
    }

    .quizzes-card h4 {
        color: #6666;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: lighter;
        margin-top: 2rem;
        text-align: center;
        font-size: clamp(1rem, 1.5vw, 1.2rem);
    }

    body.dark-mode .quizzes-card h4 {
        color: #b0b0b0;
    }

    /* Tooltip ng Quiz na di pa nate-take */
    .quiz-sub {
        position: relative;
        width: 100%;
    }

    .quiz-sub .tooltiptext {
        font-family: 'Fredoka';
        font-size: 12px;
        visibility: hidden;
        width: 130px;
        background-color: #dfa200;
        color: white;
        text-align: center;
        border-radius: 6px;
        padding: 5px 2px;
        border: 2px solid #dfa200;
        position: absolute;
        z-index: 10000;
        bottom: 70%;
        left: 70%;
        transform: translateX(-50%);
        margin-bottom: 5px;
        opacity: 0;
        transition: opacity 0.7s;
    }

    .quiz-sub .tooltiptext::after {
        content: "";
        position: absolute;
        top: 100%;
        left: 50%;
        margin-left: -5px;
        border-width: 5px;
        border-style: solid;
        border-color: #dfa200 transparent transparent transparent;
    }

    .quiz-sub:hover .tooltiptext {
        visibility: visible;
        opacity: 1;
    }

    .high-score-card {
        background-color: #ffffff;
        padding: 1.5rem;
        border-radius: 16px;
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
        min-height: 260px;
        width: 100%;
        transition: all 0.3s ease;
        display: flex;
        flex-direction: column;
        position: relative;
        overflow: hidden;
    }

    body.dark-mode .high-score-card {
        background-color: #2d2d2d;
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
    }

    .high-score-header {
        margin-bottom: 1.5rem;
        display: flex;
        align-items: flex-start;
        flex-shrink: 0;
        gap: 12px;
    }

    .header-text h3 {
        margin: 0;
        font-size: clamp(1.2rem, 2.5vw, 1.5rem);
        color: #333;
        font-weight: 600;
    }

    body.dark-mode .header-text h3 {
        color: #e0e0e0;
    }

    .header-text p {
        margin: 4px 0 0 0;
        color: #999;
        font-size: 0.9rem;
    }

    body.dark-mode .header-text p {
        color: #b0b0b0;
    }

    .high-score-card .star {
        color: #F8B500;
        font-size: clamp(26px, 4vw, 32px);
        margin-top: 4px;
        flex-shrink: 0;
    }

    #high-score-quiz {
        flex-grow: 1;
        display: flex;
        flex-direction: column;
        gap: 1rem;
        overflow-y: auto;
    }

    .high-score-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1rem;
        background: #f8f9fa;
        border-radius: 12px;
        transition: all 0.3s ease;
        border-left: 4px solid #F8B500;
    }

    body.dark-mode .high-score-item {
        background: #3a3a3a;
    }

    .high-score-item:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    body.dark-mode .high-score-item:hover {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    }

    /* Performance-based styling */
    .high-score-item[data-performance="excellent"] {
        border-left-color: #4CAF50;
    }

    .high-score-item[data-performance="good"] {
        border-left-color: #2196F3;
    }

    .high-score-item[data-performance="average"] {
        border-left-color: #FF9800;
    }

    .high-score-item[data-performance="needs-improvement"] {
        border-left-color: #F44336;
    }

    .score-info {
        flex: 1;
    }

    .quiz-title {
        font-weight: 600;
        font-size: 1rem;
        color: #333;
        margin-bottom: 4px;
    }

    body.dark-mode .quiz-title {
        color: #e0e0e0;
    }

    .quiz-subject {
        font-size: 0.85rem;
        color: #666;
        margin-bottom: 4px;
    }

    body.dark-mode .quiz-subject {
        color: #b0b0b0;
    }

    .attempt-date {
        font-size: 0.75rem;
        color: #999;
    }

    body.dark-mode .attempt-date {
        color: #888;
    }

    .score-display {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 6px;
    }

    .score-circle {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: conic-gradient(#F8B500 0%, #F8B500 var(--score-percent), #f0f0f0 var(--score-percent), #f0f0f0 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }

    body.dark-mode .score-circle {
        background: conic-gradient(#F8B500 0%, #F8B500 var(--score-percent), #3a3a3a var(--score-percent), #3a3a3a 100%);
    }

    .score-circle::before {
        content: '';
        position: absolute;
        width: 50px;
        height: 50px;
        background: white;
        border-radius: 50%;
    }

    body.dark-mode .score-circle::before {
        background: #2d2d2d;
    }

    .score-value {
        position: relative;
        z-index: 1;
        font-weight: 700;
        font-size: 1rem;
        color: #333;
    }

    body.dark-mode .score-value {
        color: #e0e0e0;
    }

    .performance-indicator {
        font-size: 0.7rem;
        padding: 2px 8px;
        border-radius: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .performance-indicator.excellent {
        background-color: #4CAF50;
        color: white;
    }

    .performance-indicator.good {
        background-color: #2196F3;
        color: white;
    }

    .performance-indicator.average {
        background-color: #FF9800;
        color: white;
    }

    .performance-indicator.needs-improvement {
        background-color: #F44336;
        color: white;
    }

    .no-scores-message {
        text-align: center;
        padding: 2rem 1rem;
        color: #999;
    }

    .no-scores-message i {
        font-size: 3rem;
        margin-bottom: 1rem;
        color: #ddd;
    }

    .no-scores-message h4 {
        margin: 0 0 0.5rem 0;
        font-weight: 500;
    }

    .no-scores-message p {
        margin: 0;
        font-size: 0.9rem;
    }

    .difficult-question-card {
        background-color: #ffffff;
        padding: 1.5rem;
        border-radius: 10px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        transition: background-color 0.3s;
        display: flex;
        flex-direction: column;
        min-height: 300px;
        width: 100%;
    }

    body.dark-mode .difficult-question-card {
        background-color: #2d2d2d;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
    }

    .difficult-question-header {
        display: flex;
        align-items: center;
        flex-shrink: 0;
    }

    .difficult-question-card h3 {
        display: flex;
        align-items: center;
        font-size: clamp(1.2rem, 2.5vw, 1.5rem);
    }

    body.dark-mode .difficult-question-card h3 {
        color: #e0e0e0;
    }

    .question {
        color: #e5941f;
        float: left;
        line-height: 1;
        font-size: clamp(30px, 4vw, 40px);
        margin-right: 2%;
    }

    table {
        border-collapse: collapse;
        margin-top: 3%;
        border-radius: 5px;
        width: 100%;
        font-size: clamp(0.8rem, 1.2vw, 1rem);
        width: ;
    }

    th, td {
        padding: 0.85rem;
        font-size: 12px;
    }

    td {
       text-align: center; 
       color: #333;
       word-wrap: break-word;
       max-width: 200px;
    }

    body.dark-mode td {
        color: #e0e0e0;
    }

    th {
        color: white;
        background-color: #F8B500;
        font-weight: lighter;
        text-align: center;
        font-size: clamp(0.8rem, 1.2vw, 1rem);
    }

    tr:nth-child(even) {
        background-color: #f2f2f2;
    }

    body.dark-mode tr:nth-child(even) {
        background-color: #3a3a3a;
    }

    body.dark-mode tr:nth-child(odd) {
        background-color: #2d2d2d;
    }

    table thead th:first-child {
        border-top-left-radius: 10px !important;
    }

    table thead th:last-child {
        border-top-right-radius: 10px !important;
    }

    .dropdown-content {
        width: min(300px, 90vw);
        right: 0;
        display: none;
        position: absolute;
        background-color: #F8B500;
        border-radius: 15px;
        box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
        z-index: 1001;
        padding: 10px 0;
        top: 100%;
        margin-top: 10px;
    }

    .dropdown-content:before {
        content: " ";
        position: absolute;
        background: #F8B500;
        width: 20px;
        height: 20px;
        top: -5px;
        right: 20px;
        transform: rotate(45deg);
        z-index: -1;
    }

    .dropdown-content button {
        background-color: white;
        font-family: 'Fredoka';
        color: white;
        font-size: clamp(16px, 2vw, 18px);
        font-weight: lighter;
        border: 2px solid white !important;
        width: 90% !important;
        padding: 12px 20px !important;
        margin: 8px auto !important;
        text-decoration: none;
        display: block;
        text-align: center;
        background-color: transparent;
        transition: background-color 0.3s, color 0.3s;
        border-radius: 10px;
        cursor: pointer;
        letter-spacing: 1px;
        box-sizing: border-box;
        min-height: 44px;
    }

    .dropdown-content button i{
        margin-right: 4px;
    }

    .dropdown-content a:hover, .dropdown-content button:hover {
        background-color: white !important;
        color: #F8B500;
    }

    .show {
        display: block;
    }

    .modal {
        display: none;
        position: fixed;
        z-index: 1002;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0,0,0,0.4);
    }

    /* Modal Animation */
    @keyframes animatetop {
        from {top:-100px; opacity:0} 
        to {top:0; opacity:1}
    }

    .modal-body {
        overflow: auto;
        height: 100%;
        width: 100%;
    } 

    .modal-content {
        position: relative;
        background-color: #FFFFFF;
        border-radius: 20px;
        padding: clamp(20px, 3vw, 30px) clamp(25px, 4vw, 40px);
        width: min(90%, 600px);
        height: auto;
        max-height: 80vh;
        margin: 10vh auto;
        animation: animatetop 0.4s;
        z-index: 1003;
        left: 0;
        transform: none;
    }

    body.dark-mode .modal-content {
        background-color: #2d2d2d;
        color: #e0e0e0;
    }

    #ready {
        font-size: clamp(16px, 2vw, 18px);
        color: black;
        font-family: Fredoka;
        text-align: left;
    }

    body.dark-mode #ready {
        color: #e0e0e0;
    }   

    .modal-content button {
        font-family: Fredoka;
        color: white;
        font-size: clamp(16px, 2vw, 18px);
        width: min(200px, 80%);
        background-color: #F8B500;
        padding: 12px 20px;
        border: none;
        border-radius: 10px;
        margin: 20px auto 0;
        cursor: pointer;
        box-shadow: 0 4px 0 0 #BC8900;
        display: block;
        min-height: 44px;
        transition: all 0.3s ease;
    }

    .modal-content button:hover {
        background-color: white;
        color: #f8b500;
        border: 2px solid #f8b500;
        transform: translateY(-2px);
        box-shadow: 0 6px 0 0 #BC8900;
    }

    .modal-content button:active {
        background-color: #f8b500;
        color: white;
        transform: translateY(2px);
        box-shadow: 0 2px 0 0 #BC8900;
    }

    .modal-dialog {
        background: none;
        -webkit-animation-name: animatetop;
        -webkit-animation-duration: 0.6s;
        animation-name: animatetop;
        animation-duration: 0.6s;
        z-index: 2;
    }

    .modal-dialog img {
        height: auto;
        max-height: 150px;
        width: auto;
        max-width: 60%;
        display: block;
        margin: 20px auto;
        animation: animatetop 0.1s;
        z-index: 2;
        filter: drop-shadow(6px -1px 5px black);
    }

    /* The Close Button */
    .close {
        color: black;
        float: right;
        margin-top: -10px;
        font-size: 28px;
        font-weight: bold;
        transition: 0.3s;
        line-height: 1;
        cursor: pointer;
        min-height: 44px;
        min-width: 44px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    body.dark-mode .close {
        color: #e0e0e0;
    }

    .close:hover,
    .close:focus {
        color: #ed5e00;
        text-decoration: none;
        transform: scale(1.1);
    }

    .modal-body h1 {
        font-family: Fredoka;
        font-size: clamp(1.5rem, 4vw, 2rem);
        text-align: center;
        margin-top: 1%;
        color: #f8b500;
        line-height: 1.2;
    }

    .modal-body h2 {
        font-family: Fredoka;
        margin-top: 4%;
        padding-bottom: 5px;
        padding-top: 1px;
        letter-spacing: 1px;
        text-align: left;
        font-size: clamp(1rem, 2vw, 1.2rem);
    }

    body.dark-mode .modal-body h2 {
        color: #e0e0e0;
    }

    .modal-body span {
        font-family: Fredoka;
        float: right;
        font-size: clamp(16px, 2vw, 18px);
        font-weight: lighter;
        text-align: center;
        margin-top: -2rem;
    }

    body.dark-mode .modal-body span {
        color: #e0e0e0;
    }

    .profile-pic {
        border: 2px solid #f8b500;
        object-fit: cover;
    }

    .modal-body .availability-span {
        line-height: 1.3;
        margin-top: 0.5rem;
        float: none;
        display: block;
        text-align: left;
    }

    /* Dark Mode Toggle Button */
    .dark-mode-toggle {
        position: fixed;
        bottom: 20px;
        right: 20px;
        background-color: #f8b500;
        color: white;
        border: none;
        border-radius: 50%;
        width: clamp(50px, 8vw, 60px);
        height: clamp(50px, 8vw, 60px);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: clamp(1.2rem, 2.5vw, 1.5rem);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        z-index: 999;
        transition: background-color 0.3s;
        min-height: 44px;
        min-width: 44px;
    }

    .dark-mode-toggle:hover {
        background-color: #e5941f;
        transform: scale(1.05);
    }

    body.dark-mode .dark-mode-toggle {
        background-color: #444;
    }

    
    /* Updated Mobile Responsive Styles - Icon Navigation */
    @media (max-width: 768px) {
        .sidebar {
            display: none;
        }
        
        .top-nav {
            display: flex;
            height: 70px;
            padding: 0.75rem;
        }
        
        .top-nav .logo {
            flex: 1;
        }
        
        .top-nav .logo img {
            height: 35px;
        }
        
        .top-nav .menu {
            gap: 1rem;
        }
        
        .top-nav .menu a {
            padding: 0.6rem;
            min-height: 44px;
            min-width: 44px;
        }
        
        .top-nav .menu a i {
            font-size: 1.3rem;
        }
        
        .top-nav-profile .profile {
            width: 40px;
            height: 40px;
        }
        
        .content {
            padding: 1rem;
            margin-left: 0;
            width: 100%;
            margin-top: 70px;
        }
        
        .content.expanded {
            margin-left: 0;
            width: 100%;
        }
        
        .mobile-menu-toggle {
            display: none;
        }
        
        .cards {
            grid-template-columns: 1fr;
            gap: 1rem;
        }
        
        .quizzes-card, .high-score-card, .difficult-question-card {
            padding: 1rem;
            min-height: auto;
            margin-bottom: 0.5rem;
        }
        
        /* Adjust icon sizes for mobile */
        .quizzes-card .bolt,
        .high-score-card .star,
        .difficult-question-card .question {
            font-size: 28px;
            margin-right: 4%;
        }
        
        .quizzes-card h3,
        .high-score-card h3,
        .difficult-question-card h3 {
            font-size: 1.3rem;
        }
        
        .quizzes-card h5 {
            font-size: 0.9rem;
        }
        
        #quizzes-cont a {
            font-size: 0.9rem;
            padding: 12px 10px;
            min-height: 44px;
        }
        
        /* Improve table readability on mobile */
        table {
            font-size: 0.8rem;
            display: block;
            overflow-x: auto;
        }
        
        table, th, td {
            padding: 0.9rem;
        }
        
        /* Adjust high score items for mobile */
        .high-score-item {
        padding: 0.75rem;
    }
    
    .score-circle {
        width: 50px;
        height: 50px;
    }
    
    .score-circle::before {
        width: 42px;
        height: 42px;
    }
    
    .score-value {
        font-size: 0.9rem;
    }
    
    .quiz-title {
        font-size: 0.9rem;
    }
    
    .quiz-subject {
        font-size: 0.8rem;
    }
    
    .performance-indicator {
        font-size: 0.65rem;
    }
            
        .content-header {
            margin-top: 0;
            flex-direction: row;
            align-items: flex-start;
            gap: 1rem;
        }
        
        .content-header > div:first-child {
            flex: 1;
            text-align: left;
        }
        
        /* Hide profile in content header on mobile */
        .content-header .actions {
            display: none;
        }
        
        .dropdown-content {
            right: -10000px;
        }
        
        .content-header h1 {
            font-size: 1.5rem;
            margin-bottom: 0.25rem;
        }
        
        .content-header p {
            font-size: 0.9rem;
        }
        
        .dropdown-content {
            right: 0;
            left: auto;
        }
        
        .quiz-sub .tooltiptext {
            display: none;
        }
        
        table {
            display: block;
            overflow-x: auto;
        }
        
        #high-score-quiz {
            gap: 0.75rem;
        }
        
        .high-score-item {
            grid-template-columns: 1fr;
            text-align: center;
            gap: 0.5rem;
        }
    }

    /* For screens larger than 768px - Show sidebar and content header profile */
    @media (min-width: 769px) {
        .top-nav {
            display: none;
        }
        
        .content-header .actions {
            display: flex;
        }

        .dropdown-content {
            width: min(260px, 80vw);
            right: 5px;
            margin-top: 5px;
        }
        
        .dropdown-content:before {
            right: 15px;
            width: 14px;
            height: 14px;
            top: -7px;
        }
        
        .dropdown-content button {
            font-size: 15px;
            padding: 9px 14px;
            min-height: 40px;
        }
    }

    @media (max-width: 576px) {
        .top-nav {
            height: 60px;
            padding: 0.5rem;
        }
        
        .top-nav .logo img {
            height: 30px;
        }
        
        .top-nav .menu {
            gap: 0.75rem;
        }
        
        .top-nav .menu a {
            padding: 0.5rem;
            min-height: 40px;
            min-width: 40px;
        }
        
        .top-nav .menu a i {
            font-size: 1.2rem;
        }
        
        .top-nav-profile .profile {
            width: 35px;
            height: 35px;
        }
        
        .content {
            padding: 0.75rem;
            margin-top: 60px;
        }
        
        .cards {
            grid-template-columns: 1fr;
        }
        
        .quizzes-card, .high-score-card, .difficult-question-card {
            padding: 0.75rem;
        }
        
        .modal-content {
            width: 95%;
            padding: 20px;
            margin: 5vh auto;
        }
        
        .content-header {
            margin-top: 0;
            flex-direction: row;
            align-items: flex-start;
            padding: 0 0.5rem;
        }
        
        .content-header h1 {
            font-size: 1.3rem;
        }
        
        .content-header p {
            font-size: 0.85rem;
        }
        
        /* Hide profile in content header on mobile */
        .content-header .actions {
            display: none;
        }
        
        .dropdown-content {
            right: 2%;
        }
        
        .content-header .actions {
            gap: 0.5rem;
        }
        
        .content-header .actions a {
            padding: 0.4rem 0.8rem;
            font-size: 0.9rem;
        }
        
        .dark-mode-toggle {
            bottom: 10px;
            right: 10px;
            width: 50px;
            height: 50px;
        }
        
        #quizzes-cont a {
            padding: 10px 12px;
            font-size: 0.9rem;
        }
        
        /* Additional 576px specific fixes */
        .quizzes-card .bolt,
        .high-score-card .star,
        .difficult-question-card .question {
            font-size: 26px;
            margin-right: 3%;
        }
        
        .quizzes-card h3,
        .high-score-card h3,
        .difficult-question-card h3 {
            font-size: 1.2rem;
        }
        
        table, th, td {
            padding: 0.4rem;
            font-size: 0.75rem;
        }
    }

    @media (max-width: 425px) {
        .top-nav {
            padding: 0.4rem;
            height: 55px;
        }
        
        .top-nav .logo img {
            height: 25px;
        }
        
        .top-nav .menu {
            gap: 0.5rem;
        }
        
        .top-nav .menu a {
            padding: 0.4rem;
            min-height: 38px;
            min-width: 38px;
        }
        
        .top-nav .menu a i {
            font-size: 1.1rem;
        }
        
        .top-nav-profile .profile {
            width: 32px;
            height: 32px;
        }
        
        .content {
            padding: 0.5rem;
            margin-top: 55px;
        }

        .content-header {
            margin-top: 0;
            flex-direction: row;
            align-items: flex-start;
            padding: 0 0.5rem;
        }
        
        .content-header h1 {
            font-size: 1.3rem;
        }
        
        .content-header p {
            font-size: 0.85rem;
        }

        /* Hide profile in content header on mobile */
        .content-header .actions {
            display: none;
        }
        
        .dropdown-content {
            width: min(240px, 75vw);
            right: 2px;
            border-radius: 10px;
        }
        
        .dropdown-content:before {
            right: 12px;
            width: 12px;
            height: 12px;
            top: -6px;
        }
        
        .dropdown-content button {
            font-size: 14px;
            padding: 8px 12px;
            min-height: 38px;
            margin: 4px auto;
        }
        
        .dropdown-content button i {
            margin-right: 4px;
            font-size: 14px;
        }
        
        .content-header .actions {
            gap: 0.5rem;
        }
        
        .content-header .actions a {
            padding: 0.4rem 0.8rem;
            font-size: 0.9rem;
        }

        .cards{
            align-items: flex-start;
        }
        
        .quizzes-card, .high-score-card, .difficult-question-card {
            padding: 1rem;
            min-height: auto;
            margin-bottom: 0.5rem;
        }
        
        .quizzes-card .bolt,
        .high-score-card .star,
        .difficult-question-card .question {
            font-size: 24px;
            margin-right: 3%;
        }
        
        .quizzes-card h3,
        .high-score-card h3,
        .difficult-question-card h3 {
            font-size: 1.1rem;
        }
        
        #quizzes-cont a {
            font-size: 0.85rem;
            padding: 10px 8px;
        }
        
        /* Modal adjustments for small screens */
        .modal-content {
            width: 95%;
            padding: 25px;
            margin: 2vh auto;
        }
        
        .modal-body h1 {
            font-size: 1.3rem;
        }
        
        .modal-body h2 {
            font-size: 0.95rem;
        }
        
        .start-quiz-btn {
            font-size: 0.5rem;
            padding: 10px 15px;
        }
        
        /* Additional 425px specific fixes */
        .content-header .actions {
            flex-wrap: wrap;
            justify-content: flex-end;
        }
        
        .content-header .actions a {
            font-size: 0.85rem;
            padding: 0.35rem 0.7rem;
        }
        
        .high-score-item {
            padding: 0.5rem;
        }
        
        .quiz-title, .score p {
            font-size: 0.85rem;
        }
    }

    @media (max-width: 375px) {
        .top-nav {
            padding: 0.3rem;
            height: 50px;
        }
        
        .top-nav .logo img {
            height: 30px;
        }
        
        .top-nav .menu {
            gap: 0.4rem;
        }
        
        .top-nav .menu a {
            padding: 0.3rem;
            min-height: 36px;
            min-width: 36px;
        }
        
        .top-nav .menu a i {
            font-size: 1rem;
        }
        
        .top-nav-profile .profile {
            width: 30px;
            height: 30px;
        }
        
        .content {
            padding: 0.5rem;
            margin-top: 50px;
        }
        
        .quizzes-card, .high-score-card, .difficult-question-card {
            padding: 0.5rem;
        }
        
        #quizzes-cont a {
            font-size: 0.8rem;
            padding: 8px 6px;
        }
        
        .content-header .actions a {
            padding: 0.4rem 0.8rem;
            font-size: 0.9rem;
        }
        
        .content-header .actions {
            justify-content: center;
            margin-top: 0.5rem;
        }
        
        .dropdown-content {
            width: min(220px, 70vw);
            right: 0;
        }
        
        .dropdown-content:before {
            right: 10px;
        }
        
        .dropdown-content button {
            font-size: 13px;
            padding: 7px 10px;
            min-height: 36px;
            letter-spacing: 0.3px;
        }

        /* Additional 375px specific fixes */
        .content-header {
            margin-top: 0;
            flex-direction: column;
            align-items: flex-start;
            gap: 0.75rem;
        }
        
        .content-header > div:first-child {
            width: 100%;
        }
        
        .content-header .actions {
            width: 100%;
            justify-content: space-between;
        }
        
        .quizzes-card .bolt,
        .high-score-card .star,
        .difficult-question-card .question {
            font-size: 22px;
            margin-right: 2%;
        }
        
        .quizzes-card h3,
        .high-score-card h3,
        .difficult-question-card h3 {
            font-size: 1rem;
        }
        
        .quizzes-card h5 {
            font-size: 0.8rem;
        }
        
        table, th, td {
            padding: 0.3rem;
            font-size: 0.7rem;
        }
        
        .modal-content {
            padding: 25px;
            margin: 1vh auto;
        }
        
        .modal-body h1 {
            font-size: 1.1rem;
        }
        
        .modal-body h2 {
            font-size: 0.85rem;
        }
        
        .start-quiz-btn {
            font-size: 0.85rem;
            padding: 5px 9px;
            width: 100%;
        }
    }

    /* Utility classes for better responsive behavior */
    .sr-only {
        position: absolute;
        width: 1px;
        height: 1px;
        padding: 0;
        margin: -1px;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        white-space: nowrap;
        border: 0;
    }

    /* Improve focus accessibility */
    button:focus-visible,
    a:focus-visible,
    .quiz-link:focus-visible {
        outline: 2px solid #f8b500;
        outline-offset: 2px;
    }

    /* Smooth scrolling */
    html {
        scroll-behavior: smooth;
    }

    /* Prevent horizontal scroll */
    body {
        overflow-x: hidden;
    }

    
</style>
</head>
<body>
    <!-- Top Navigation for Mobile - UPDATED WITH PROFILE -->
    <nav class="top-nav" id="topNav">
        <div class="logo">
            <img src="img/logo 6.png" alt="QuizZap Logo">
        </div>
        <div class="menu" id="topNavMenu">
            <a href="s_Home.php" class="active" title="Dashboard">
                <i class="fa-solid fa-house"></i>
                <span>Dashboard</span>
            </a>
            <a href="s_Classes.php" title="Classes">
                <i class="fa-regular fa-address-book"></i>
                <span>Classes</span>
            </a>
        </div>
        <div class="top-nav-profile">
            <div class="profile" onclick="profileDropdown()">
                <img src="uploads/profiles/<?php echo htmlspecialchars($profile_pic); ?>" alt="Profile Picture" class="profile-pic" onerror="this.src='uploads/profiles/default-profile.jpg'" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
                <div id="dropdown" class="dropdown-content">
                    <button onclick="window.location.href='s_Profile.php'"><i class="fa-solid fa-user"></i>  Profile</button> 
                    <form action="logout.php" method="POST">
                        <button><i class="fa-solid fa-right-from-bracket"></i>  Logout</button>
                    </form>
                </div>
            </div>
        </div>
        <!-- Hamburger button removed -->
    </nav>

    <!-- Dark Mode Toggle Button -->
    <button class="dark-mode-toggle" id="darkModeToggle">
        <i class="fas fa-moon"></i>
    </button>

    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <header>
                <button id="toggleSidebar" class="toggle-btn">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="logo">
                    <img src="img/logo4.png" width="200px" height="80px" class="logo-img">
                    <img src="img/logo 6.png" width="50px" height="50px" class="logo-icon" style="display: none; margin-top: 10%;">
                </div>
            </header>
            <hr style="border: 1px solid white;">
            <div class="menu">
                <a href="s_Home.php" class="active" title="Dashboard">
                    <i class="fa-solid fa-house"></i>
                    <span>Dashboard</span>
                </a>
                <a href="s_Classes.php" title="Classes">
                <i class="fa-regular fa-address-book"></i>
                    <span>Classes</span>
                </a>
            </div>
        </div>

        <!-- Content Area -->
        <div class="content">
            <div class="content-header">
                <div>
                    <h1>Hi, <?php echo htmlspecialchars($_SESSION['fname']); ?>!</h1>
                    <p>Are you ready to start your journey to learning and testing your knowledge here?</p>
                </div>
                <!-- Profile in content header for larger screens -->
                <div class="actions">
                    <div class="profile" onclick="profileDropdown()">
                        <img src="uploads/profiles/<?php echo htmlspecialchars($profile_pic); ?>" alt="Profile Picture" class="profile-pic" onerror="this.src='uploads/profiles/default-profile.jpg'" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
                        <div id="dropdown" class="dropdown-content">
                            <button onclick="window.location.href='s_Profile.php'"><i class="fa-solid fa-user"></i> Profile</button> 
                            <form action="logout.php" method="POST">
                                <button><i class="fa-solid fa-right-from-bracket"></i> Logout</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <div class = "cards">
                <div class="left-card">
                    <div class = "quizzes-card">
                        <div id="quizzes-card-header">
                            <i class="fa-solid fa-bolt bolt"></i>    
                            <h3>The ZAP! Starts Now!</h3>
                            <h5>Start answering quizzes now!</h5>
                        </div>    
                        <br>

                        <div id = "quizzes-cont">
                            <?php
                            if ($not_taken_result->num_rows > 0) {
                                while ($row = $not_taken_result->fetch_assoc()) {
                            ?>
                            <div class="quiz-sub">
                                <span class="tooltiptext">Subject: <?php echo htmlspecialchars($row['subject_name'])?></span>
                                <a class="quiz-link" data-quiz-id="<?php echo htmlspecialchars($row['quiz_id']);?>"><?php echo htmlspecialchars($row['title']);?></a> <br><br>
                            </div>    
                            <?php 
                                }
                            } else {
                                echo "<h4>You don't have any missed quizzes.</h4>";
                            }            
                            ?>    
                        </div>        
                    </div>  
                </div>
                
                <div class="right-card">
                    <div class="high-score-card">
                        <div class="high-score-header">    
                            <i class="fa-solid fa-trophy star"></i>
                            <div class="header-text">
                                <h3>Your Latest High Scores</h3>
                                <p>Your recent quiz achievements</p>
                            </div>
                        </div>    
                        <div id="high-score-quiz">                               
                            <?php 
                            if ($latest_high_score_result->num_rows > 0) {
                                while ($row = $latest_high_score_result->fetch_assoc()) {
                                    // Calculate percentage for visual indicator
                                    $scorePercent = min(100, ($row['highest_score'] / 100) * 100);
                                    // Determine performance level
                                    $performanceLevel = $scorePercent >= 90 ? 'excellent' : ($scorePercent >= 75 ? 'good' : ($scorePercent >= 60 ? 'average' : 'needs-improvement'));
                            ?>
                                <div class="high-score-item" data-performance="<?php echo $performanceLevel; ?>">
                                    <div class="score-info">
                                        <div class="quiz-title"><?php echo htmlspecialchars($row['quiz_title']); ?></div>
                                        <div class="quiz-subject"><?php echo htmlspecialchars($row['subject_name']); ?></div>
                                        <div class="attempt-date"><?php echo date('M j, Y', strtotime($row['latest_attempt_date'])); ?></div>
                                    </div>
                                    <div class="score-display">
                                        <div class="score-circle" data-percent="<?php echo $scorePercent; ?>">
                                            <div class="score-value"><?php echo htmlspecialchars($row['highest_score']); ?></div>
                                        </div>
                                        <div class="performance-indicator <?php echo $performanceLevel; ?>">
                                            <?php 
                                            switch($performanceLevel) {
                                                case 'excellent': echo 'Excellent!'; break;
                                                case 'good': echo 'Good Job'; break;
                                                case 'average': echo 'Not Bad'; break;
                                                default: echo 'Keep Trying'; break;
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </div>   
                            <?php
                                }
                            } else {
                                echo '<div class="no-scores-message">
                                        <i class="fa-solid fa-chart-line"></i>
                                        <h4>No high scores yet</h4>
                                        <p>Complete quizzes to see your achievements here</p>
                                    </div>';
                            }
                            ?>   
                        </div>
                    </div>
                    <br>
                                        <div class="difficult-question-card">
                        <div class="difficult-question-header">    
                            <i class="fa-solid fa-question question"></i>
                            <h3>Difficult Questions</h3>
                        </div>
                        <div class="table-container">
                            <?php 
                            if ($difficult_questions_result && $difficult_questions_result->num_rows > 0) { ?>
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Subject</th>
                                            <th>Quiz</th>
                                            <th>Question</th>
                                            <th>Attempts</th>
                                            <th>Lowest Score</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($row = $difficult_questions_result->fetch_assoc()) { ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($row['subject_name']); ?></td>
                                                <td><?php echo htmlspecialchars($row['quiz_title']); ?></td>
                                                <td class="question-cell"><?php echo htmlspecialchars($row['question_text']); ?></td>
                                                <td><?php echo htmlspecialchars($row['total_attempts']); ?></td>
                                                <td><?php echo htmlspecialchars($row['lowest_score']); ?></td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            <?php } else { ?>
                                <div class="no-questions-message">
                                    <i class="fa-solid fa-check-circle"></i>
                                    <h4>No difficult questions found</h4>
                                    <p>Great job! You're handling all questions well.</p>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
            </div>
        </div>
    </div>

    <!-- Modal for Quiz Details -->
    <div id="myModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <div class="modal-body" id="modal-body">
                    <!-- Quiz details will be displayed here -->
            </div>
            <br><br>
        </div>
    </div>

    <script>
        // Sidebar toggle functionality
        const toggleSidebar = document.getElementById('toggleSidebar');
        const sidebar = document.getElementById('sidebar');
        const content = document.querySelector('.content');

        toggleSidebar.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
            content.classList.toggle('expanded');
        });

        // Profile dropdown functionality
        function profileDropdown() {
            // Close all dropdowns first
            const allDropdowns = document.querySelectorAll('.dropdown-content.show');
            allDropdowns.forEach(drop => {
                drop.classList.remove('show');
            });
            
            // Toggle the clicked dropdown
            const dropdowns = document.querySelectorAll('.dropdown-content');
            dropdowns.forEach(dropdown => {
                dropdown.classList.toggle('show');
            });
        }

        // Close the dropdown if clicked outside
        window.onclick = function(event) {
            if (!event.target.matches('.profile') && !event.target.matches('.profile-pic') && !event.target.closest('.profile')) {
                var dropdowns = document.getElementsByClassName("dropdown-content");
                for (var i = 0; i < dropdowns.length; i++) {
                    var openDropdown = dropdowns[i];
                    if (openDropdown.classList.contains('show')) {
                        openDropdown.classList.remove('show');
                    }
                }
            }
        }

        // Quiz modal functionality
        const modal = document.getElementById("myModal");
        const closeModal = document.querySelector(".close");
        const quizLinks = document.querySelectorAll(".quiz-link");

        quizLinks.forEach(link => {
            link.addEventListener('click', function() {
                const quizId = this.getAttribute('data-quiz-id');
                fetchQuizDetails(quizId);
            });
        });

        closeModal.addEventListener('click', function() {
            modal.style.display = "none";
        });

        window.addEventListener('click', function(event) {
            if (event.target == modal) {
                modal.style.display = "none";
            }
        });

        function fetchQuizDetails(quizId) {
            fetch('s_quiz_details.php?quiz_id=' + quizId)
                .then(response => {
                    console.log('Response status:', response.status);
                    console.log('Response headers:', response.headers);
                    
                    // First, get the response as text to see what we're actually getting
                    return response.text().then(text => {
                        console.log('Raw response:', text);
                        
                        // Try to parse as JSON
                        try {
                            const jsonData = JSON.parse(text);
                            return jsonData;
                        } catch (e) {
                            console.error('JSON parse error:', e);
                            throw new Error('Not valid JSON: ' + text.substring(0, 100));
                        }
                    });
                })
                .then(data => {
                    if (data.error) {
                        document.getElementById('modal-body').innerHTML = '<p>Error: ' + data.error + '</p>';
                    } else {
                        // Format the JSON data as HTML
                        const quiz = data;
                        
                        // Format dates for display
                        function formatDateForDisplay(date) {
                            if (!date || date === 'null') return 'Always available';
                            try {
                                return new Date(date).toLocaleString('en-US', {
                                    year: 'numeric',
                                    month: 'short',
                                    day: 'numeric',
                                    hour: '2-digit',
                                    minute: '2-digit'
                                });
                            } catch (e) {
                                return 'Invalid date';
                            }
                        }
                        
                        let availability = "Always available";
                        if (quiz.start_date && quiz.end_date && quiz.start_date !== 'null' && quiz.end_date !== 'null') {
                            availability = formatDateForDisplay(quiz.start_date) + " to " + formatDateForDisplay(quiz.end_date);
                        }
                        
                        const htmlContent = `
                            <h1>${quiz.title || 'No Title'}</h1>
                            <div class="detail-row">
                                <h2>Number of Questions: </h2> <span>${quiz.num_of_questions || 0}</span> 
                                <h2>Quiz Type: </h2> <span>${quiz.quiz_type || 'Not specified'}</span> 
                                <h2>Time Limit: </h2> <span>${quiz.timer || 0} minute/s</span> 
                                <h2>Availability: </h2> <span class="availability-span">${availability}</span>
                            </div><br>
                            <button class="start-quiz-btn" data-quiz-id="${quizId}" data-quiz-type="${quiz.quiz_type || ''}" style="
                                font-family: Fredoka;
                                color: white;
                                font-size: 18px;
                                width: 80%;
                                background-color: #F8B500;
                                padding: 10px 15px;
                                border: none;
                                border-radius: 10px;
                                margin: auto;
                                cursor: pointer;
                                box-shadow: 0 6px 0 0 #BC8900;
                            ">Start Quiz</button><br>
                        `;
                        
                        document.getElementById('modal-body').innerHTML = htmlContent;
                        
                        // Add event listener to the new button
                        const startQuizBtn = document.querySelector('.start-quiz-btn');
                        if (startQuizBtn) {
                            startQuizBtn.addEventListener('click', function() {
                                const quizId = this.getAttribute('data-quiz-id');
                                const quizType = this.getAttribute('data-quiz-type');
                                startQuiz(quizId, quizType);
                            });
                        }
                    }
                    modal.style.display = "block";
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    document.getElementById('modal-body').innerHTML = '<p>Error loading quiz details: ' + error.message + '</p>';
                    modal.style.display = "block";
                });
        }

        // Add this function to handle quiz start
        function startQuiz(quizId, quizType) {
            if (quizType === "All Zapped") {
                window.location.href = "allZapped_quiz.php?quiz_id=" + quizId;
            } else {
                window.location.href = "s_quiz.php?quiz_id=" + quizId;
            }
        }

        // Dark Mode Toggle Functionality
        const darkModeToggle = document.getElementById('darkModeToggle');
        const body = document.body;

        // Check for saved dark mode preference
        const isDarkMode = localStorage.getItem('darkMode') === 'true';
        
        // Apply dark mode if previously enabled
        if (isDarkMode) {
            body.classList.add('dark-mode');
            darkModeToggle.innerHTML = '<i class="fas fa-sun"></i>';
        }

        darkModeToggle.addEventListener('click', () => {
            body.classList.toggle('dark-mode');
            
            // Update button icon and save preference
            if (body.classList.contains('dark-mode')) {
                darkModeToggle.innerHTML = '<i class="fas fa-sun"></i>';
                localStorage.setItem('darkMode', 'true');
            } else {
                darkModeToggle.innerHTML = '<i class="fas fa-moon"></i>';
                localStorage.setItem('darkMode', 'false');
            }
        });

        // Handle window resize
        window.addEventListener('resize', function() {
            // No need for mobile menu toggle anymore
        });

        // Handle touch events for better mobile interaction
        document.addEventListener('DOMContentLoaded', function() {
            // Improve touch targets for mobile
            const touchElements = document.querySelectorAll('a, button, .quiz-link');
            touchElements.forEach(element => {
                element.style.minHeight = '44px';
                element.style.minWidth = '44px';
                element.style.display = 'flex';
                element.style.alignItems = 'center';
                element.style.justifyContent = 'center';
            });
            
            // Prevent zoom on double tap for buttons (iOS)
            document.addEventListener('touchstart', function() {}, {passive: true});
        });

        // Optimize sidebar for touch
        sidebar.addEventListener('touchstart', function(e) {
            e.stopPropagation();
        }, {passive: true});

        // Animate score circles
function animateScoreCircles() {
    const scoreCircles = document.querySelectorAll('.score-circle');
    
    scoreCircles.forEach(circle => {
        const percent = circle.getAttribute('data-percent');
        circle.style.setProperty('--score-percent', `${percent}%`);
        
        // Add animation
        setTimeout(() => {
            circle.style.transition = 'all 0.8s ease-out';
        }, 100);
    });
}

// Call this function after the DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    animateScoreCircles();
});
    </script>
</body>
</html>