<?php
session_start(); // Start the session

// Check if the user is logged in and is a student
if (!isset($_SESSION['account_number']) || strpos($_SESSION['account_number'], 'S') !== 0) {
    header("Location: login.php");
    exit();
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "rawrit";

$conn = new mysqli($servername, $username, $password, $dbname);

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
    $profile_pic = $row['profile_pic'] ? $row['profile_pic'] : 'default-profile.jpg';
} else {
    $profile_pic = 'default-profile.jpg';
}

// Get the subject_id from the URL
$subject_id = isset($_GET['subject_id']) ? $_GET['subject_id'] : null;
$account_number = $_SESSION['account_number'];


if (!$subject_id) {
    ?>
    <script type="text/javascript">
    alert("Subject ID not provided.");
    window.location.href="s_Classes.php";
    </script>
    <?php
    exit();   
}

// Fetch the subject name for the given subject_id
$sql = "SELECT subject_name FROM subjects WHERE subject_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $subject_id);
$stmt->execute();
$result_subject = $stmt->get_result();
$subject_name = "Unknown Subject"; // Default value in case subject is not found

if ($result_subject->num_rows > 0) {
    $row_subject = $result_subject->fetch_assoc();
    $subject_name = $row_subject['subject_name'];
}
$stmt->close();

if (isset($_GET['quiz_id'])) {
    $quiz_id = $_GET['quiz_id'];
}


// Fetch quizzes for the selected subject
$sql = "SELECT q.*, 
        CASE WHEN qa.latest_attempt_id IS NOT NULL THEN 1 ELSE 0 END as is_taken,
        qa.score as last_score,
        qa.attempt_time as last_attempt
        FROM quizzes q 
        LEFT JOIN (
            SELECT quiz_id, MAX(attempt_id) as latest_attempt_id, 
                   score,
                   attempt_time
            FROM quiz_attempts
            WHERE account_number = ?
            GROUP BY quiz_id
        ) qa ON q.quiz_id = qa.quiz_id 
        WHERE q.subject_id = ? 
        ORDER BY q.quiz_id DESC";

$stmt = $conn->prepare($sql);

// Check if prepare failed
if ($stmt === false) {
    die("Error preparing quiz statement: " . $conn->error);
}

if (!$stmt->bind_param("si", $account_number, $subject_id)) {
    die("Error binding quiz parameters: " . $stmt->error);
}

if (!$stmt->execute()) {
    die("Error executing quiz statement: " . $stmt->error);
}

$result = $stmt->get_result();
$stmt->close();;

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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

        /* Top Navigation for ALL screen sizes - UPDATED */
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

        .content span {
            font-family: Fredoka;
            font-size: larger;
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
            width: 100%;
            font-size: clamp(1.5rem, 4vw, 2rem);
            color: #333333;
            font-family: Fredoka;
            padding: 10px;
            border-bottom: 1.5px solid #F8B500;
        }

        body.dark-mode .content-header h1 {
            color: #e0e0e0;
        }

        .content-header p {
            color: #999;
            font-size: 1rem;
            margin-top: 0.5rem;
            font-family: Fredoka;
            font-weight: 500;
        }

        body.dark-mode .content-header p {
            color: #b0b0b0;
        }

        .content-header .actions {
            display: flex;
            align-items: center;
            flex-shrink: 0;
        }

        .content-header .actions button {
            background-color: #F8B500;
            color: #ffffff;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            font-size: 1rem;
            cursor: pointer;
            margin-right: 1rem;
            font-family: Fredoka;
        }

        .content-header .actions button:hover {
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

        .profile-pic {
            border: 2px solid #f8b500;
            object-fit: cover;
        }

        .quiz-container {
            background-color: white;
            border: 3px solid #DCDCDC;
            border-radius: 15px;
            padding: clamp(20px, 3vw, 50px);
            width: 100%;
            max-width: 1000px;
            margin: 2% auto 0 auto;
            overflow: auto;
            box-shadow: 2px 4px 2px 0 rgba(0, 0, 0, 0.2);
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
        }

        body.dark-mode .quiz-container {
            background-color: #2d2d2d;
            border-color: #444;
        }

        .quiz-btn {
            background-color: white;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0;
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #f8b500;
            border-radius: 8px;
            box-shadow: 0 4px 0 0 #BC8900;
            text-decoration: none;
            text-align: center;
            font-family: Fredoka;
            font-weight: 500;
            font-size: clamp(16px, 2vw, 22px);
            color: black;
            cursor: pointer;
            min-height: 60px;
            transition: all 0.3s ease;
            position: relative;
        }

        body.dark-mode .quiz-btn {
            background-color: #2d2d2d;
            color: #e0e0e0;
        }

        .quiz-btn.taken {
            background-color: #e0e0e0;
            border-color: #999999;
            box-shadow: 0 4px 0 0 #666666;
        }

        body.dark-mode .quiz-btn.taken {
            background-color: #444;
            border-color: #666;
        }

        .quiz-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 0 0 #BC8900;
        }

        .quiz-btn:active {
            transform: translateY(2px);
            box-shadow: 0 2px 0 0 #BC8900;
        }

        .quiz-btn .tooltiptext {
            font-family: 'Fredoka';
            font-size: 12px;
            visibility: hidden;
            width: 180px;
            background-color: white;
            color: black;
            text-align: center;
            border-radius: 6px;
            padding: 5px 0;
            border: 2px solid #f8b500;
            position: absolute;
            z-index: 5;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            margin-bottom: 5px;
            opacity: 0;
            transition: opacity 0.7s;
        }

        body.dark-mode .quiz-btn .tooltiptext {
            background-color: #333;
            color: #e0e0e0;
            border-color: #f8b500;
        }

        .quiz-btn .tooltiptext::after {
            content: "";
            position: absolute;
            top: 100%;
            left: 50%;
            margin-left: -5px;
            border-width: 5px;
            border-style: solid;
            border-color: #F8B500 transparent transparent transparent;
        }

        .quiz-btn:hover .tooltiptext {
            visibility: visible;
            opacity: 1;
        }

        .no-quiz-btn {
            position: relative;
            text-align: center;
            margin: auto;
            margin-top: 3px;
            padding: 3px 0;
            grid-column: 1 / -1;
        }

        .img-no-quiz {
            width: 130px;
            height: 120px;
            margin-left: auto;
            margin-right: auto;
            display: flex;
            border-radius: 100%;
        }

        .no-quiz-con {
            font-family: To Japan;
            width: 60%;
            margin: auto;
            padding: 10px 3px;
            margin-top: 100px;
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
            padding:10px;
            border-bottom: 1.5px solid #f8b500;
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

        .modal-body .availability-span {
            line-height: 1.3;
            margin-top: 0.5rem;
            float: none;
            display: block;
            text-align: left;
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

        .dropdown-content a:hover, .dropdown-content button:hover {
            background-color: white !important;
            color: #F8B500;
        }

        .show {
            display: block;
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
            
            .quiz-container {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
                padding: 15px;
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
            
            .quiz-btn .tooltiptext {
                display: none;
            }
            
            table {
                display: block;
                overflow-x: auto;
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
            
            .quiz-container {
                grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
                padding: 10px;
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
            
            .dark-mode-toggle {
                bottom: 10px;
                right: 10px;
                width: 50px;
                height: 50px;
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
            
            .quiz-container{
                align-items: flex-start;
            }
            
            .quiz-container {
                grid-template-columns: 1fr;
                gap: 0.75rem;
            }
            
            .modal-content {
                width: 95%;
                padding: 15px;
                margin: 2vh auto;
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
            
            .quiz-container {
                padding: 0.5rem;
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
        .quiz-btn:focus-visible {
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
            <a href="s_Classes.php" title="Classes">
                <i class="fa-solid fa-list"></i>
                <span>Classes</span>
            </a>
            <a href="s_quiz.php" class="active" title="Quizzes">
                <i class="fa-regular fa-circle-question"></i>
                <span>Quizzes</span>
            </a>
            <a href="s_scores.php?subject_id=<?php echo $subject_id;?>" title="Scores">
                <i class="fa-solid fa-list-ol"></i>
                <span>Scores</span>
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
                <a href="s_Classes.php" title="Classes">
                    <i class="fa-solid fa-list"></i>
                    <span>Classes</span>
                </a>
                <a href="s_quiz.php" class="active" title="Quizzes">
                    <i class="fa-regular fa-circle-question"></i>
                    <span>Quizzes</span>
                </a>
                <a href="s_scores.php?subject_id=<?php echo $subject_id;?>" title="Scores">
                    <i class="fa-solid fa-list-ol"></i>
                    <span>Scores</span>
                </a>
            </div>
        </div>

        <!-- Content Area -->
        <div class="content">
            <div class="content-header">
                <div>
                    <h1><?php echo htmlspecialchars($subject_name); ?></h1><br>
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
            
            <div class="quiz-container">
                <?php 
                    if ($result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            $takenClass = $row['is_taken'] ? 'taken' : '';
                            $tooltip = '';
                            if ($row['is_taken']) {
                                $attemptDate = date('M d, Y', strtotime($row['last_attempt']));
                                $tooltip = "<span class='tooltiptext'>Quiz Taken<br>Score: {$row['last_score']}<br>Date Taken: {$attemptDate}</span>";
                            }
                            echo "<a class='quiz-btn quiz-link {$takenClass}' data-quiz-id='" . $row['quiz_id'] . "'>" . $row['title'] . $tooltip . "</a>";
                        }
                    } else {
                        echo "<div class='no-quiz-btn'>";
                        echo "<p>No quizzes available for this subject.</p>";
                        echo "</div>";
                    } 
                ?>
            </div>
        </div>
    </div>

    <div id="quiz-info-modal" class="modal">
        <div class="modal-content">
            <!-- Modal content -->    
            <span class="close">&times;</span>
            <h2 id="ready">Are you Ready to Ace this Quiz?</h2><br>
            
            <div class="modal-body">    
                <div id="quiz-details"></div>
            </div>    
            <button id="start-quiz-button">QuizZap!</button>
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
            
            // Save state to localStorage
            localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
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
        document.addEventListener("DOMContentLoaded", function() {
            // Get the modal and elements inside it
            var modal = document.getElementById("quiz-info-modal");
            var closeModal = document.getElementsByClassName("close")[0];
            var quizDetails = document.getElementById("quiz-details");
            var startQuizButton = document.getElementById("start-quiz-button");

            function formatDateRange(startDate, endDate) {
                if (!startDate || !endDate) {
                    return "Always available";
                }
                
                const start = new Date(startDate);
                const end = new Date(endDate);
                
                const options = { 
                    year: 'numeric', 
                    month: 'short', 
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                };
                
                return `${start.toLocaleDateString('en-US', options)} to ${end.toLocaleDateString('en-US', options)}`;
            }
            
            // Add event listener to all quiz links
            document.querySelectorAll(".quiz-link").forEach(function(link) {
                link.addEventListener("click", function() {
                    var quizId = this.getAttribute("data-quiz-id");
                    // Fetch quiz details (replace with actual PHP script to fetch quiz data)
                    fetch(`s_quiz_details.php?quiz_id=${quizId}`)
                        .then(response => response.json())
                        .then(data => {
                            // Populate the modal with quiz details
                            quizDetails.innerHTML = `
                                <h1>${data.title}</h1>
                                <div class="detail-row">
                                <h2>Number of Questions: </h2> <span> ${data.num_of_questions} </span> 
                                <h2>Quiz Type: </h2> <span> ${data.quiz_type} </span> 
                                <h2>Time Limit: </h2> <span> ${data.timer} minute/s</span> 
                                <h2>Availability: </h2> <span class="availability-span"> ${formatDateRange(data.start_date, data.end_date)} </span>
                            `;
                            
                            // Update the start quiz button link with conditional routing
                            startQuizButton.onclick = function() {
                                // Conditional routing based on quiz type
                                if (data.quiz_type === "All Zapped") {
                                    window.location.href = `allZapped_quiz.php?quiz_id=${quizId}`;
                                } else if (["Multiple Choice", "True or False", "Fill in the Blanks", "Enumeration", "Identification", "Drag and Drop", "Matching Type"].includes(data.quiz_type)) {
                                    window.location.href = `s_quiz.php?quiz_id=${quizId}`;
                                } else {
                                    // Fallback for any unexpected quiz types
                                    window.location.href = `s_quiz.php?quiz_id=${quizId}`;
                                }
                            };
                            
                            // Show the modal
                            modal.style.display = "block";
                        })
                        .catch(error => {
                            console.error("Error fetching quiz details:", error);
                        });
                });
            });
            
            // Close the modal when the close button is clicked
            closeModal.onclick = function() {
                modal.style.display = "none";
            }
            
            // Close the modal when clicking outside of it
            window.onclick = function(event) {
                if (event.target == modal) {
                    modal.style.display = "none";
                }
            }
        });

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
            const touchElements = document.querySelectorAll('a, button, .quiz-btn');
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

        // Check if sidebar state is saved in localStorage
        const isSidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        
        // Set initial state based on localStorage
        if (isSidebarCollapsed) {
            sidebar.classList.add('collapsed');
            content.classList.add('expanded');
        }
    </script>
</body>
</html>