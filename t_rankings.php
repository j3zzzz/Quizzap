<?php
session_start();
if (strpos($_SESSION['account_number'], 'T') !== 0) {
    header("Location: login.php");
    exit();
}

// Database connection details remain the same
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "rawrit";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$loggedInUser = $_SESSION['account_number'];

//query to fetch the teacher's profile pic
$sql = "SELECT profile_pic FROM teachers WHERE account_number = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $loggedInUser);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $profile_pic = $row['profile_pic'] ? $row['profile_pic'] : 'default-profile.jpg';
} else {
    $profile_pic = 'default-profile.jpg';
}

$subject_id = isset($_GET['subject_id']) ? $_GET['subject_id'] : null;

// Fetch quizzes for the subject
$quiz_sql = $conn->prepare("
    SELECT quiz_id, title 
    FROM quizzes 
    WHERE subject_id = ?
    ORDER BY quiz_id DESC");
$quiz_sql->bind_param("i", $subject_id);
$quiz_sql->execute();
$quiz_result = $quiz_sql->get_result();

$quiz_sql->close();

//para ma-fetch yung subject name
$sub_sql = $conn->prepare("
    SELECT subject_name 
    FROM subjects 
    WHERE subject_id = ?
    ");
$sub_sql->bind_param("i", $subject_id);
$sub_sql->execute();
$result = $sub_sql->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $subject_name = $row['subject_name'];
}

$sub_sql->close();


// Function to get rankings for a specific quiz
function getRankings($conn, $quiz_id) {
    $stmt = $conn->prepare("
        SELECT 
            CONCAT(u.fname, ' ', u.lname) AS Name,
            qa.score,
            qa.attempt_time
        FROM quiz_attempts qa
        JOIN students u ON qa.account_number = u.account_number
        JOIN (
            SELECT account_number, MAX(score) as max_score 
            FROM quiz_attempts 
            WHERE quiz_id = ?
            GROUP BY account_number
        ) max_scores ON qa.account_number = max_scores.account_number
            AND qa.score = max_scores.max_score
        WHERE qa.quiz_id = ?
        GROUP BY qa.account_number
        ORDER BY qa.score DESC, qa.attempt_time DESC");
    
    $stmt->bind_param("ii", $quiz_id, $quiz_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $rankings = [];
    while ($row = $result->fetch_assoc()) {
        $rankings[] = $row;
    }
    $stmt->close();
    return $rankings;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="other resources/fontawesome-free-6.5.2-web/css/all.min.css">
    <title>Rankings - QuizZap</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Fredoka', sans-serif;
        }

        body, html {
            height: 100%;
            transition: background-color 0.3s, color 0.3s;
            overflow-x: hidden;
        }

        body.dark-mode {
            background-color: #1a1a1a;
            color: #e0e0e0;
        }

        .container {
            display: flex;
            min-height: 100vh;
            flex-direction: column;
            width: 100%;
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
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #f8b500;
            width: 100%;
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

        /* Profile in content header for larger screens */
        .content-header .actions {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-shrink: 0;
        }

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

        /* Main content area */
        .quizzes-container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 1rem;
        }

        .no-quizzes {
            text-align: center;
            padding: 3rem;
            color: #666;
            font-size: 1.2rem;
        }

        body.dark-mode .no-quizzes {
            color: #b0b0b0;
        }

        /* Quiz dropdown styles */
        .quiz-dropdown {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            margin-bottom: 1.5rem;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        body.dark-mode .quiz-dropdown {
            background: #2d2d2d;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .quiz-dropdown:last-child {
            margin-bottom: 0;
        }

        .quiz-header {
            padding: 1.25rem 1.5rem;
            background: linear-gradient(135deg, #f8b500, #ffcc33);
            color: #fff;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
            border: none;
            width: 100%;
            text-align: left;
        }

        body.dark-mode .quiz-header {
            background: linear-gradient(135deg, #333, #555);
        }

        .quiz-header:hover {
            background: linear-gradient(135deg, #e6a600, #ffb300);
        }

        .quiz-header h2 {
            margin: 0;
            font-size: clamp(1.1rem, 2vw, 1.3rem);
            font-weight: 600;
            flex: 1;
            color: #fff;
        }

        .quiz-header i {
            font-size: 1.2rem;
            color: #fff;
            transition: transform 0.3s ease;
        }

        .quiz-content.active .quiz-header i {
            transform: rotate(180deg);
        }

        .quiz-content {
            display: none;
            padding: 0;
            overflow: hidden;
        }

        .quiz-content.active {
            display: block;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Rankings table styles */
        .rankings-table {
            width: 100%;
            padding: 1.5rem;
        }

        .ranking-header {
            display: grid;
            grid-template-columns: minmax(80px, 0.5fr) minmax(150px, 2fr) minmax(100px, 1fr) minmax(150px, 1.5fr);
            padding: 1rem;
            background: #fff6df;
            border-radius: 8px;
            margin-bottom: 1rem;
            gap: 0.5rem;
        }

        body.dark-mode .ranking-header {
            background: #3a3a3a;
        }

        .ranking-header span {
            font-weight: 600;
            font-size: clamp(0.9rem, 1.2vw, 1.1rem);
            color: #f8b500;
            text-align: center;
        }

        body.dark-mode .ranking-header span {
            color: #ffcc33;
        }

        .ranking-rows {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            max-height: 400px;
            overflow-y: auto;
        }

        .ranking-row {
            display: grid;
            grid-template-columns: minmax(80px, 0.5fr) minmax(150px, 2fr) minmax(100px, 1fr) minmax(150px, 1.5fr);
            padding: 1rem;
            border-radius: 10px;
            align-items: center;
            font-weight: 500;
            gap: 0.5rem;
            transition: all 0.3s ease;
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(5px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .ranking-row:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        /* Different background colors for each position */
        .ranking-row:nth-child(1) {
            background: #ffc62c;
            border: 2px solid #ffd700;
        }

        .ranking-row:nth-child(1) i {
            color: #FFD700;
            background-color: white;
            padding: 8px;
            border-radius: 50%;
            text-align: center;
        }

        .ranking-row:nth-child(2) {
            background: #ffd460;
            border: 2px solid #c0c0c0;
        }

        .ranking-row:nth-child(2) i {
            color: #C0C0C0;
            background-color: white;
            padding: 8px;
            border-radius: 50%;
            text-align: center;
        }

        .ranking-row:nth-child(3) {
            background: #ffe293;
            border: 2px solid #cd7f32;
        }

        .ranking-row:nth-child(3) i {
            color: #CD7F32;
            background-color: white;
            padding: 8px;
            border-radius: 50%;
            text-align: center;
        }

        .ranking-row:nth-child(n+4) {
            background: #ffe9ad;
        }

        body.dark-mode .ranking-row:nth-child(n+4) {
            background: #3a3a3a;
        }

        /* Cell styles */
        .rank {
            font-size: clamp(1.1rem, 1.5vw, 1.3rem);
            font-weight: bold;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .stud-name {
            font-size: clamp(1rem, 1.2vw, 1.1rem);
            text-align: left;
            padding-left: 0.5rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .score {
            font-size: clamp(1.2rem, 1.5vw, 1.4rem);
            font-weight: bold;
            text-align: center;
            color: #333;
        }

        body.dark-mode .score {
            color: #e0e0e0;
        }

        .time {
            font-size: clamp(0.9rem, 1vw, 1rem);
            text-align: center;
            color: #666;
        }

        body.dark-mode .time {
            color: #b0b0b0;
        }

        .no-rankings {
            text-align: center;
            padding: 3rem;
            color: #666;
            font-style: italic;
            grid-column: 1 / -1;
        }

        body.dark-mode .no-rankings {
            color: #888;
        }

        /* Dropdown menu styles */
        .dropdown-content {
            display: none;
            position: absolute;
            background-color: #F8B500;
            border-radius: 15px;
            box-shadow: 0px 8px 16px rgba(0,0,0,0.2);
            z-index: 1001;
            padding: 10px 0;
            top: 100%;
            right: 0;
            margin-top: 10px;
            min-width: 200px;
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
            background: transparent;
            font-family: 'Fredoka';
            color: white;
            font-size: clamp(14px, 1.2vw, 16px);
            font-weight: 500;
            border: 2px solid white;
            width: 90%;
            padding: 12px 20px;
            margin: 6px auto;
            text-decoration: none;
            display: block;
            text-align: center;
            transition: background-color 0.3s, color 0.3s;
            border-radius: 10px;
            cursor: pointer;
            letter-spacing: 0.5px;
            box-sizing: border-box;
            min-height: 44px;
        }

        .dropdown-content button i {
            margin-right: 8px;
        }

        .dropdown-content button:hover {
            background-color: white !important;
            color: #F8B500;
        }

        .show {
            display: block;
        }

        .profile {
            position: relative;
            cursor: pointer;
        }

        .profile-pic {
            border: 2px solid #f8b500;
            object-fit: cover;
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

        /* Mobile Responsive Styles */
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
            
            .content-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .content-header .actions {
                display: none;
            }
            
            .quizzes-container {
                padding: 0.5rem;
            }
            
            .ranking-header {
                grid-template-columns: minmax(60px, 0.5fr) minmax(100px, 1fr) minmax(80px, 0.8fr) minmax(100px, 1.2fr);
                padding: 0.75rem;
                font-size: 0.9rem;
                gap: 0.3rem;
            }
            
            .ranking-row {
                grid-template-columns: minmax(60px, 0.5fr) minmax(100px, 1fr) minmax(80px, 0.8fr) minmax(100px, 1.2fr);
                padding: 0.75rem;
                font-size: 0.9rem;
                gap: 0.3rem;
            }
            
            .rank {
                font-size: 1rem;
                flex-direction: column;
                gap: 0.2rem;
            }
            
            .stud-name {
                font-size: 0.9rem;
                padding-left: 0.2rem;
            }
            
            .score {
                font-size: 1.1rem;
            }
            
            .time {
                font-size: 0.8rem;
            }
            
            .quiz-header {
                padding: 1rem;
            }
            
            .quiz-header h2 {
                font-size: 1rem;
            }
            
            .dropdown-content {
                width: min(250px, 80vw);
                right: 0;
            }
            
            .dark-mode-toggle {
                bottom: 15px;
                right: 15px;
                width: 50px;
                height: 50px;
            }
        }

        @media (min-width: 769px) {
            .top-nav {
                display: none;
            }
            
            .content-header .actions {
                display: flex;
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
            
            .ranking-header {
                grid-template-columns: 0.8fr 1.5fr 0.8fr 1.2fr;
                font-size: 0.8rem;
            }
            
            .ranking-row {
                grid-template-columns: 0.8fr 1.5fr 0.8fr 1.2fr;
                font-size: 0.8rem;
                padding: 0.6rem;
            }
            
            .rank {
                font-size: 0.9rem;
                flex-direction: column;
            }
            
            .rank i {
                padding: 4px;
                font-size: 0.7rem;
            }
            
            .stud-name {
                font-size: 0.85rem;
            }
            
            .score {
                font-size: 1rem;
            }
            
            .time {
                font-size: 0.75rem;
            }
            
            .no-rankings {
                padding: 2rem;
                font-size: 0.9rem;
            }
        }

        @media (max-width: 480px) {
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
            
            .content-header h1 {
                font-size: 1.3rem;
            }
            
            .ranking-header {
                grid-template-columns: 0.7fr 1.8fr 0.7fr 1.3fr;
                padding: 0.5rem;
                font-size: 0.7rem;
            }
            
            .ranking-row {
                grid-template-columns: 0.7fr 1.8fr 0.7fr 1.3fr;
                padding: 0.5rem;
                font-size: 0.75rem;
            }
            
            .rank {
                font-size: 0.85rem;
                flex-direction: column;
            }
            
            .stud-name {
                font-size: 0.8rem;
            }
            
            .score {
                font-size: 0.9rem;
            }
            
            .time {
                font-size: 0.7rem;
            }
            
            .quiz-header {
                padding: 0.75rem;
            }
            
            .quiz-header h2 {
                font-size: 0.9rem;
            }
            
            .dropdown-content {
                width: min(220px, 75vw);
            }
            
            .dropdown-content:before {
                right: 15px;
                width: 15px;
                height: 15px;
            }
            
            .dropdown-content button {
                font-size: 14px;
                padding: 10px;
                min-height: 40px;
            }
        }

        @media (max-width: 375px) {
            .ranking-header {
                grid-template-columns: 0.6fr 2fr 0.6fr 1.5fr;
            }
            
            .ranking-row {
                grid-template-columns: 0.6fr 2fr 0.6fr 1.5fr;
            }
            
            .stud-name {
                font-size: 0.75rem;
            }
            
            .time {
                font-size: 0.65rem;
            }
            
            .no-rankings {
                padding: 1.5rem;
                font-size: 0.8rem;
            }
        }

        /* Scrollbar styling */
        .ranking-rows::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        .ranking-rows::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .ranking-rows::-webkit-scrollbar-thumb {
            background: #f8b500;
            border-radius: 10px;
        }

        body.dark-mode .ranking-rows::-webkit-scrollbar-track {
            background: #2d2d2d;
        }

        body.dark-mode .ranking-rows::-webkit-scrollbar-thumb {
            background: #555;
        }

        /* Loading animation */
        .loading {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 2rem;
            color: #f8b500;
        }

        .loading i {
            animation: spin 1s linear infinite;
            font-size: 2rem;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Utility classes */
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
        a:focus-visible {
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
    <!-- Top Navigation for Mobile -->
    <nav class="top-nav" id="topNav">
        <div class="logo">
            <img src="img/logo 6.png" alt="QuizZap Logo">
        </div>
        <div class="menu" id="topNavMenu">
            <a href="t_SubjectsList.php" title="Subject List">
                <i class="fa-solid fa-list"></i>
                <span>Subjects</span>
            </a>
            <a href="t_quizDash.php" title="Quiz Dash">
                <i class="fa-regular fa-circle-question"></i>
                <span>Quizzes</span>
            </a>
            <a href="t_rankings.php?subject_id=<?php echo $subject_id; ?>" class="active" title="Rankings">
                <i class="fa-solid fa-ranking-star"></i>
                <span>Rankings</span>
            </a>
            <a href="t_item-analysis.php?subject_id=<?php echo $subject_id; ?>" title="Item Analysis">
                <i class="fa-solid fa-chart-line"></i>
                <span>Item Analysis</span>
            </a>
        </div>
        <div class="top-nav-profile">
            <div class="profile" onclick="profileDropdown()">
                <img src="uploads/profiles/<?php echo htmlspecialchars($profile_pic); ?>" alt="Profile Picture" class="profile-pic" onerror="this.src='uploads/profiles/default-profile.jpg'" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
                <div id="dropdown" class="dropdown-content">
                    <button onclick="window.location.href='t_Profile.php'"><i class="fa-solid fa-user"></i> Profile</button> 
                    <form action="logout.php" method="POST">
                        <button><i class="fa-solid fa-right-from-bracket"></i> Logout</button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    <!-- Dark Mode Toggle Button -->
    <button class="dark-mode-toggle" id="darkModeToggle">
        <i class="fas fa-moon"></i>
    </button>

    <div class="container">
        <!-- Sidebar - Hidden on mobile -->
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
                <a href="t_SubjectsList.php" title="Subject List">
                    <i class="fa-solid fa-list"></i>
                    <span>Subjects</span>
                </a>
                <a href="t_quizDash.php" title="Quiz Dash">
                    <i class="fa-regular fa-circle-question"></i>
                    <span>Quizzes</span>
                </a>
                <a href="t_rankings.php?subject_id=<?php echo $subject_id; ?>" class="active" title="Rankings">
                    <i class="fa-solid fa-ranking-star"></i>
                    <span>Rankings</span>
                </a>
                <a href="t_item-analysis.php?subject_id=<?php echo $subject_id; ?>" title="Item Analysis">
                    <i class="fa-solid fa-chart-line"></i>
                    <span>Item Analysis</span>
                </a>
            </div>
        </div>

        <!-- Content Area -->
        <div class="content">
            <div class="content-header">
                <div>
                    <h1>Rankings: <?php echo htmlspecialchars($subject_name); ?></h1>
                    <p>View student rankings for all quizzes in this subject</p>
                </div>
                <!-- Profile in content header for larger screens -->
                <div class="actions">
                    <div class="profile" onclick="profileDropdown()">
                        <img src="uploads/profiles/<?php echo htmlspecialchars($profile_pic); ?>" alt="Profile Picture" class="profile-pic" onerror="this.src='uploads/profiles/default-profile.jpg'" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
                        <div id="dropdown" class="dropdown-content">
                            <button onclick="window.location.href='t_Profile.php'"><i class="fa-solid fa-user"></i> Profile</button> 
                            <form action="logout.php" method="POST">
                                <button><i class="fa-solid fa-right-from-bracket"></i> Logout</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="quizzes-container">
                <?php if ($quiz_result->num_rows == 0): ?>
                    <div class="no-quizzes">
                        <i class="fas fa-clipboard-list fa-3x" style="color: #f8b500; margin-bottom: 1rem;"></i>
                        <p>No quizzes found for this subject.</p>
                    </div>
                <?php else: ?>
                    <?php while($quiz = $quiz_result->fetch_assoc()): ?>
                        <div class="quiz-dropdown">
                            <button class="quiz-header" onclick="toggleRankings(<?php echo $quiz['quiz_id']; ?>)">
                                <h2><?php echo htmlspecialchars($quiz['title']); ?></h2>
                                <i class="fas fa-chevron-down"></i>
                            </button>
                            <div id="rankings-<?php echo $quiz['quiz_id']; ?>" class="quiz-content">
                                <div class="rankings-table">
                                    <div class="ranking-header">
                                        <span>Rank</span>
                                        <span>Student</span>
                                        <span>Score</span>
                                        <span>Time</span>
                                    </div>
                                    <div class="ranking-rows" id="ranking-rows-<?php echo $quiz['quiz_id']; ?>">
                                        <div class="loading">
                                            <i class="fas fa-spinner"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.querySelector('.sidebar');
    const content = document.querySelector('.content');
    const toggleBtn = document.getElementById('toggleSidebar');
    const darkModeToggle = document.getElementById('darkModeToggle');
    const body = document.body;

    // Check if sidebar state is saved in localStorage
    const isSidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
    
    // Set initial state based on localStorage
    if (isSidebarCollapsed) {
        sidebar.classList.add('collapsed');
        content.classList.add('expanded');
    }

    // Toggle sidebar when button is clicked (for desktop)
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            content.classList.toggle('expanded');
            
            // Save state to localStorage
            localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
        });
    }

    // Check for saved dark mode preference
    const isDarkMode = localStorage.getItem('darkMode') === 'true';
    
    // Apply dark mode if previously enabled
    if (isDarkMode) {
        body.classList.add('dark-mode');
        darkModeToggle.innerHTML = '<i class="fas fa-sun"></i>';
    }

    // Dark mode toggle functionality
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

    // Load rankings for first quiz by default on mobile
    if (window.innerWidth <= 768) {
        const firstQuiz = document.querySelector('.quiz-dropdown');
        if (firstQuiz) {
            const quizId = firstQuiz.querySelector('.quiz-header').onclick.toString().match(/\((\d+)\)/)[1];
            setTimeout(() => loadRankings(quizId), 500);
        }
    }

    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth >= 769) {
            // Reset sidebar display on desktop
            if (sidebar.style.display === 'none') {
                sidebar.style.display = 'flex';
            }
        }
    });

    // Improve touch targets for mobile
    const touchElements = document.querySelectorAll('a, button, .dropdown-content button');
    touchElements.forEach(element => {
        element.style.minHeight = '44px';
        element.style.minWidth = '44px';
        element.style.display = 'flex';
        element.style.alignItems = 'center';
        element.style.justifyContent = 'center';
    });
});

function toggleRankings(quizId) {
    const content = document.getElementById(`rankings-${quizId}`);
    const allContents = document.querySelectorAll('.quiz-content');
    const icon = content.previousElementSibling.querySelector('i');
    
    // Toggle all dropdowns
    allContents.forEach(item => {
        if (item.id !== `rankings-${quizId}`) {
            item.classList.remove('active');
            const otherIcon = item.previousElementSibling.querySelector('i');
            if (otherIcon) otherIcon.style.transform = 'rotate(0deg)';
        }
    });
    
    // Toggle the clicked dropdown
    content.classList.toggle('active');
    if (content.classList.contains('active')) {
        icon.style.transform = 'rotate(180deg)';
    } else {
        icon.style.transform = 'rotate(0deg)';
    }
    
    // Load rankings if not already loaded
    if (!content.dataset.loaded) {
        loadRankings(quizId);
        content.dataset.loaded = true;
    }
}

function loadRankings(quizId) {
    const container = document.getElementById(`ranking-rows-${quizId}`);
    
    // Show loading state
    container.innerHTML = '<div class="loading"><i class="fas fa-spinner"></i></div>';
    
    fetch(`get_rankings.php?quiz_id=${quizId}`)
        .then(response => response.json())
        .then(data => {
            if (data.length === 0) {
                container.innerHTML = '<div class="no-rankings">No rankings available for this quiz yet.</div>';
                return;
            }
            
            let rankingsHTML = '';
            data.forEach((item, index) => {
                const rank = index + 1;
                const medalIcon = rank === 1 ? '<i class="fas fa-medal"></i>' : 
                                 rank === 2 ? '<i class="fas fa-medal"></i>' : 
                                 rank === 3 ? '<i class="fas fa-medal"></i>' : 
                                 `<span>${rank}</span>`;
                
                rankingsHTML += `
                    <div class="ranking-row">
                        <div class="rank">${medalIcon}</div>
                        <div class="stud-name">${escapeHTML(item.Name)}</div>
                        <div class="score">${item.score}</div>
                        <div class="time">${formatDate(item.attempt_time)}</div>
                    </div>
                `;
            });
            
            container.innerHTML = rankingsHTML;
        })
        .catch(error => {
            console.error('Error loading rankings:', error);
            container.innerHTML = '<div class="no-rankings">Error loading rankings. Please try again.</div>';
        });
}

function formatDate(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / (1000 * 60));
    const diffHours = Math.floor(diffMs / (1000 * 60 * 60));
    const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));
    
    if (diffMins < 1) return 'Just now';
    if (diffMins < 60) return `${diffMins}m ago`;
    if (diffHours < 24) return `${diffHours}h ago`;
    if (diffDays < 7) return `${diffDays}d ago`;
    
    // For older dates, show formatted date
    return date.toLocaleDateString('en-US', { 
        month: 'short', 
        day: 'numeric',
        year: date.getFullYear() !== now.getFullYear() ? 'numeric' : undefined
    });
}

function escapeHTML(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Make profileDropdown function global
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
</script>
</body>
</html>