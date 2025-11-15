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
            z-index: 999;
        }

        body.dark-mode .sidebar {
            background-color: #333;
        }

        .sidebar.collapsed {
            width: 90px;
            padding: 2rem 0.5rem;
        }

        .sidebar .logo {
            margin-bottom: 1rem;
            margin-left: 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
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
        }

        .toggle-btn:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .toggle-btn{
            align-items: center;
        }

        .sidebar .menu {
            margin-top: 40%;
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }

        .sidebar.collapsed .menu{
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
            margin-bottom: .5rem;
            width: 100%;
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
            font-size: 20px;
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
        }

        .sidebar.collapsed .menu a i {
            margin-right: 0;
            font-size: 1.2rem;
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
        }

        .toggle-btn:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .sidebar.collapsed .toggle-btn{
            margin: auto;
        }

        .sidebar.collapsed .logo-img {
            display: none;
        }

        .sidebar.collapsed .logo-icon {
            display: block !important;
        }

        .sidebar.collapsed .menu a {
            padding: 1rem 0;
            justify-content: center;
            width: 100%;
        }

        .sidebar.collapsed .menu a span {
            display: none;
        }

        .sidebar.collapsed .menu a i {
            margin-right: 0;
            font-size: 1.5rem;
        }

        .sidebar.collapsed hr {
            margin: 0.5rem auto;
            width: 50%;
        }

        /* Dashboard content area */
        .content {
            flex: 1;
            background-color: #ffffff;
            padding: 2rem;
            margin-left: 250px;
            transition: margin-left 0.3s ease, background-color 0.3s;
        }

        body.dark-mode .content {
            background-color: #1a1a1a;
            color: #e0e0e0;
        }

        .content.expanded {
            margin-left: 90px;
        }

        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .content-header h1 {
            font-size: 2rem;
            color: #333333;
            font-family: 'Fredoka';
            margin-bottom: 0.5rem;
        }

        body.dark-mode .content-header h1 {
            color: #e0e0e0;
        }

        .content-header p {
            color: #999;
            font-size: 1rem;
            margin-top: 0.5rem;
            font-family: 'Fredoka';
            font-weight: 500;
            width: 100%;
        }

        body.dark-mode .content-header p {
            color: #b0b0b0;
        }

        .content-header .actions {
            display: flex;
            align-items: center;
            gap: 1rem;
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
        }

        .content-header .actions a:hover {
            background-color: #e5941f;
        }

        .content-header .actions .profile {
            width: 40px;
            height: 40px;
            background-color: #ffffff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #f5a623;
            font-size: 1.5rem;
            cursor: pointer;
        }

        body.dark-mode .content-header .actions .profile {
            background-color: #333;
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            padding-bottom: 2rem;
        }

        .quizzes-card {
            background-color: #ffffff;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            height: 30rem;
            z-index: 4 !important;
            transition: background-color 0.3s;
        }

        body.dark-mode .quizzes-card {
            background-color: #2d2d2d;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
        }

        .quizzes-card #quizzes-card-header {
            position: absolute;
            width: 20%;
        }

        #quizzes-cont {
            padding: 1rem;
            overflow: auto;
            height: 90%;
        }

        .quizzes-card .bolt {
            color: #e5941f;
            float: left;
            line-height: 1;
            font-size: 40px;
            margin-right: 2%;
        } 

        .quizzes-card h3 {
            font-family: 'Fredoka';
        }

        body.dark-mode .quizzes-card h3 {
            color: #e0e0e0;
        }

        .quizzes-card h5 {
            font-family: Fredoka;
            color: #999;
            font-weight: lighter;
        }

        body.dark-mode .quizzes-card h5 {
            color: #b0b0b0;
        }

        #quizzes-cont a {
            background-color: #F8B500;
            text-decoration: none;
            color: white;
            padding: 4% 5%;
            border-radius: 10px;
            display: inline-block; 
            margin-left: 5%;
            width: 80%;
            cursor: pointer;
            border: 3px solid #f8b500
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
            justify-self: center;
            font-weight: lighter;
            margin-top: 30%;
        }

        body.dark-mode .quizzes-card h4 {
            color: #b0b0b0;
        }

        /* Tooltip ng Quiz na di pa nate-take */
        .quiz-sub {
            position: relative;
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

            /* Position the tooltip */
            position: absolute;
            z-index: 5;
            bottom: 35%;
            left: 90%;
            margin-left: -90px;
            margin-bottom: 2%;
            opacity: 0;
            transition: opacity 0.7s;
        }

        .quiz-sub .tooltiptext::after{
            content: "";
            position: absolute;
            top: 35%;
            right: 100%;
            margin-left: -5px;
            border-width: 5px;
            border-style: solid;
            transform: rotate(90deg);
            border-color: #dfa200 transparent transparent transparent;
        }

        .quiz-sub:hover .tooltiptext{
            visibility: visible;
            opacity: 1;
        }

        .high-score-card {
            background-color: #ffffff;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            height: 260px;
            width: 100%;
            transition: background-color 0.3s;
        }

        body.dark-mode .high-score-card {
            background-color: #2d2d2d;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
        }

        .high-score-header {
            position: absolute;
            width: 20%;
            display: flex;
        }

        .high-score-card .star {
            color: #e5941f;
            float: left;
            line-height: 1;
            font-size: 40px;
            margin-right: 2%;
        } 
        
        .high-score-card h3 {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        body.dark-mode .high-score-card h3 {
            color: #e0e0e0;
        }

        .high-score-card h4 {
            color: #6666;
            display: flex;
            align-items: center;
            justify-self: center;
            font-weight: lighter;
            margin-top: 12%;
        }

        body.dark-mode .high-score-card h4 {
            color: #b0b0b0;
        }

        #high-score-quiz h4 {
            text-align: center;  
        }

        .quiz-title {
            width: 100%;
            height: fit-content;
            padding: 8px;
            font-size: 20px;
            justify-content: center;
            color: #333333;
            font-size: 15px;
        }

        body.dark-mode .quiz-title {
            color: #e0e0e0;
        }

        .score {
            margin-bottom: 1%;
            color: white;
            width: 50% ;
        }

        .score p {
            border-radius: 5px;
            background-color: #F8B500;
            text-align: center;
            padding: 2px;
        }

        #high-score-quiz {
            margin-left: 8%;
            padding: .5rem 1rem;
            display: grid;
            grid-template-columns: 2fr 2fr 1fr;
            align-items: center;
        }

        .difficult-question-card {
            background-color: #ffffff;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            transition: background-color 0.3s;
        }

        body.dark-mode .difficult-question-card {
            background-color: #2d2d2d;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
        }

        .difficult-question-header {
            position: absolute;
            width: 20%;
            display: flex;
        }

        .difficult-question-card h3 {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        body.dark-mode .difficult-question-card h3 {
            color: #e0e0e0;
        }

        .question {
            color: #e5941f;
            float: left;
            line-height: 1;
            font-size: 40px;
            margin-right: 2%;
        }

        table {
            border-collapse: collapse;
            margin-top: 3%;
            border-radius: 5px;
            margin: auto;
        }

        table, th, td {
            padding: .5rem;
        }

        td {
           text-align: center; 
           color: #333;
        }

        body.dark-mode td {
            color: #e0e0e0;
        }

        th {
            color: white;
            background-color: #F8B500;
            font-weight: lighter;
            text-align: center;
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
            width: 300px;
            right: 1%;
            display: none;
            position: absolute;
            background-color: #F8B500;
            border-radius: 15px;
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
            z-index: 1;
            padding: 10px 0;
            top: 15%;
        }

        .dropdown-content:before {
            content: " " ;
            position: absolute;
            background: #F8B500;
            width: 30px;
            height: 30px;
            top: 1px;
            right: 23px;
            transform: rotate(135deg);
            z-index: -1 !important;
        }

        .dropdown-content button {
            background-color: white;     
            justify-content: center;
            align-items: center;
            align-self: center;
            font-family: 'Fredoka';
            color: white;
            font-size: 18px;
            font-weight: lighter;
            border: 2px solid white !important;
            width: 86% !important;
            padding: 13px 20px !important;
            margin: 8px 20px !important;
            text-decoration: none;
            display: block;
            float: none;
            text-align: center;
            background-color: transparent;
            transition: background-color 0.3s, color 0.3s;
            border-radius: 10px;
            cursor: pointer;
            letter-spacing: 1px;
            box-sizing: border-box;
            z-index: 1 !important;  
            cursor: pointer;
        }

        .dropdown-content a:hover, .dropdown-content button:hover{
            background-color: white !important;
            color: #F8B500;
        }

        .show {
            display: block;
        }

        .modal {
            display: none; /* Hidden by default */
            position: fixed; /* Stay in place */
            z-index: 4; /* Sit on top */
            padding-top: 100px; /* Location of the box */
            left: 0;
            top: 0;
            width: 100%; /* Full width */
            height: 100%; /* Full height */
            overflow: auto; /* Enable scroll if needed */
            background-color: rgb(0,0,0); /* Fallback color */
            background-color: rgba(0,0,0,0.4); /* Black w/ opacity */
        }

        /* Modal Animation */
        @-webkit-keyframes animatetop {
        from {top:-100%; opacity:0} 
        to {top:-5%; opacity:1}
        }

        @keyframes animatetop {
        from {top:-100%; opacity:0}
        to {top:-5%; opacity:1}
        }

        @media (max-width: 768px) {
            .modal-content {
                width: 80%;
            }
        }

        .modal-body {
            overflow: auto;
            height: 100%;
            width: 100%;
        } 

        .modal-content{
            position: relative;
            background-color: #FFFFFF;
            border-radius: 20px;
            padding: 30px 40px;
            width: 50%;
            height: 70%;
            margin: auto;
            top: 5%;
            left: 30%;
            transform: translateX(-50%);
            -webkit-animation-name: animatetop;
            -webkit-animation-duration: 0.4s;
            animation-name: animatetop;
            animation-duration: 0.4s;
            z-index: 4;
        }

        body.dark-mode .modal-content {
            background-color: #2d2d2d;
            color: #e0e0e0;
        }

        #ready {
            font-size: 18px;
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
            font-size: 18px;
            width: 40%;
            background-color: #F8B500;
            padding: 10px 15px;
            border: none;
            border-radius: 10px;
            margin: auto;
            cursor: pointer;
            box-shadow: 0 6px 0 0 #BC8900;
        }

        .modal-content button:hover {
            background-color: white;
            color: #f8b500;
            border: 2px solid #f8b500;
            -ms-transform: scale(1.5); /* IE 9 */
            -webkit-transform: scale(1.5); /* Safari 3-8 */
            transform: scale(1.2); 
            transition: transform .2s;
            box-shadow: 0 4px 0 0 #BC8900;
        }

        .modal-content button:active {
            background-color: #f8b500;
            color: white;
            transform: translateY(4px);
            box-shadow: 0 4px 0 0 #BC8900;
        }
        .modal-dialog{
            background: none;
            margin-top: 1%;
            -webkit-animation-name: animatetop;
            -webkit-animation-duration: 0.6s;
            animation-name: animatetop;
            animation-duration: 0.6s;
            z-index: 2;
        }

        .modal-dialog img {
            height: 150px;
            width: 50%;
            display: flex;
            position: absolute;
            margin: auto;
            margin-top: 3%;
            margin-left: 20%;
            -webkit-animation-name: animatetop;
            -webkit-animation-duration: 0.1s;
            animation-name: animatetop;
            animation-duration: 0.1s;
            z-index: 2;
            filter: drop-shadow(6px -1px 5px black);
        }

        /* The Close Button */
        .close {
          color: black;
          float: right;
          margin-top: -4%;
          font-size: 28px;
          font-weight: bold;
          transition: 1.0s;
        }

        body.dark-mode .close {
            color: #e0e0e0;
        }

        .close:hover,
        .close:focus {
          color: #ed5e00;
          text-decoration: none;
          cursor: pointer;
        }

        .modal-body h1 {
            font-family: Fredoka;
            font-size: xx-large ;
            text-align: center;
            margin-top: 1%;
            color: #f8b500;
        }

        .modal-body h2 {
            font-family: Fredoka;
            margin-top: 4%;
            padding-bottom: 5px;
            padding-top: 1px;
            letter-spacing: 1px;
            text-align: left;
        }

        body.dark-mode .modal-body h2 {
            color: #e0e0e0;
        }

        .modal-body span {
            font-family: Fredoka;
            float: right;
            right: 5%;
            margin-top: -6.5%;
            font-size: 20px;
            position: relative;
            font-weight: lighter;
            text-align: center;
        }

        body.dark-mode .modal-body span {
            color: #e0e0e0;
        }

        .profile-pic {
            border: 2px solid #f8b500;
        }

        .modal-body .availability-span {
            line-height: 1.3;
            margin-top: -5%;
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
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 1.5rem;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            transition: background-color 0.3s;
        }

        .dark-mode-toggle:hover {
            background-color: #e5941f;
        }

        body.dark-mode .dark-mode-toggle {
            background-color: #444;
        }

    </style>
</head>
<body>
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
                        <br><br>

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
                            <i class="fa-solid fa-star star"></i>
                            <h3>Your Latest High Scores</h3>
                        </div>    
                        
                        <br> <br>
                        <div id="high-score-quiz">                               
                            <?php 
                            if ($latest_high_score_result->num_rows > 0) {
                                while ($row = $latest_high_score_result->fetch_assoc()) {
                            ?>
                                <div class="quiz-title"><h5><?php echo htmlspecialchars($row['quiz_title']); ?></h5></div>
                                <div style="color: #999; text-align: right;">Score: </div>    
                                <center>
                                <div class="score"><p><?php echo htmlspecialchars($row['highest_score']); ?></p></div>   
                                </center>  
                            <?php
                                }
                            } else {
                                echo "<h4>You don't have any high scores yet.</h4>";
                            }
                            ?>   
                        </div>
                    </div>
                    <br>
                    <div class="difficult-question-card">
                        <div class="difficult-question-header">    
                        <i class="fa-solid fa-question question"></i></i>
                            <h3>Difficult Questions</h3>
                        </div>
                        <br><br><br>
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
                                            <td><?php echo htmlspecialchars($row['question_text']); ?></td>
                                            <td><?php echo htmlspecialchars($row['total_attempts']); ?></td>
                                            <td><?php echo htmlspecialchars($row['lowest_score']); ?></td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        <?php } else { ?>
                            <h4>No difficult questions found.</h4>
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
            document.getElementById("dropdown").classList.toggle("show");
        }

        // Close the dropdown if clicked outside
        window.onclick = function(event) {
            if (!event.target.matches('.profile') && !event.target.matches('.profile-pic')) {
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
                            </div>
                            <button class="start-quiz-btn" data-quiz-id="${quizId}" data-quiz-type="${quiz.quiz_type || ''}" style="
                                font-family: Fredoka;
                                color: white;
                                font-size: 18px;
                                width: 40%;
                                background-color: #F8B500;
                                padding: 10px 15px;
                                border: none;
                                border-radius: 10px;
                                margin-top: 3%;
                                margin-left: 2%;
                                cursor: pointer;
                                box-shadow: 0 6px 0 0 #BC8900;
                            ">Start Quiz</button>
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
    </script>
</body>
</html>