<?php
session_start();
if (strpos($_SESSION['account_number'], 'T') !== 0) {
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

$stmt->close();

$subject_id = $_GET['subject_id'];
$teacher_id = $_SESSION['account_number'];

$sql = "SELECT * FROM subjects WHERE subject_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $subject_id);
$stmt->execute();
$result = $stmt->get_result();
$subject = $result->fetch_assoc();
$stmt->close();

if (!$subject) {
    header("Location: t_SubjectsList.php");
    exit();
}

// Fetch the quizzes related to the subject
$sql = "SELECT * FROM quizzes WHERE subject_id = ? ORDER BY quiz_id DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $subject_id);
$stmt->execute();
$result = $stmt->get_result();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" type="text/css" href="other resources/fontawesome-free-6.5.2-web/css/all.min.css">
    <title><?php echo htmlspecialchars($subject['subject_name']); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Fredoka';
        }

        body, html {
            height: 100%;
            overflow-x: hidden;
            transition: background-color 0.3s, color 0.3s;
        }

        body.dark-mode {
            background-color: #1a1a1a;
            color: #e0e0e0;
        }

        .container {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            position: relative;
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

        /* Sidebar styling - Hidden on mobile */
        .sidebar {
            position: fixed;
            width: 250px;
            height: 100vh;
            background-color: white;
            color: #f8b500;
            padding: 2rem 1rem;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            transition: all 0.3s ease;
            z-index: 999;
            box-shadow: 0 2px 10px rgba(0,0,0,0.15);
        }

        body.dark-mode .sidebar {
            background-color: #333;
            color: #f8b500;
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
            color: #f8b500;
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

        body.dark-mode .toggle-btn {
            color: #f8b500;
        }

        .toggle-btn:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .mobile-toggle {
            display: none;
            position: fixed;
            top: 1rem;
            left: 1rem;
            background: #f8b500;
            color: white;
            border: none;
            padding: 0.5rem;
            border-radius: 5px;
            z-index: 1000;
            font-size: 1.2rem;
            min-height: 44px;
            min-width: 44px;
        }

        .sidebar .menu {
            margin-top: 30%;
            display: flex;
            flex-direction: column;
            flex-grow: 1;
            gap: 0.5rem;
        }

        .sidebar.collapsed .menu{
            align-items: center;
            margin-top: 45%;
        }

        .sidebar .menu a {
            color: #f8b500;
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
            background-color: #f8b500;
            color: white;
        }

        body.dark-mode .sidebar .menu a:hover,
        body.dark-mode .sidebar .menu a.active {
            background-color: #f8b500;
            color: white;
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

        .sidebar.collapsed .toggle-btn{
            margin: auto;
        }

        .sidebar.collapsed .logo-img {
            display: none;
        }

        .sidebar.collapsed .logo-icon {
            display: block !important;
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
            margin-left: 90px;
            width: calc(100% - 90px);
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
            font-size: clamp(1.5rem, 3vw, 2rem);
            color: #333333;
            font-family: Fredoka;
            padding: 10px;
            border-bottom: 1.5px solid #F8B500;
            line-height: 1.3;
        }

        body.dark-mode .content-header h1 {
            color: #e0e0e0;
        }

        .content-header p {
            color: #999;
            font-size: clamp(0.9rem, 1.5vw, 1rem);
            margin-top: 0.5rem;
            font-family: Fredoka;
            font-weight: 500;
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

        .content-header .actions button {
            background-color: #F8B500;
            color: #ffffff;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            font-size: 1rem;
            cursor: pointer;
            font-family: Fredoka;
            min-height: 44px;
            white-space: nowrap;
        }

        .content-header .actions button:hover {
            background-color: #e5941f;
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

        .create-q-button {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 2rem;
            width: 100%;
        }

        .create-q-button a {
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            background-color: #F8B500;
            color: white;
            border: 2px solid #F8B500;
            font-family: Fredoka;
            font-weight: 500;
            font-size: clamp(14px, 1.5vw, 16px);
            box-shadow: 0 6px 0 0 #BC8900;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-block;
            text-align: center;
            min-height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            white-space: nowrap;
        }

        .create-q-button a:hover {
            background-color: white;
            color: #f8b500;
        }

        body.dark-mode .create-q-button a:hover {
            background-color: #2d2d2d;
            color: #f8b500;
        }

        .create-q-button a:active {
            background-color: #f8b500;
            color: white;
            box-shadow: 0 3px 0 -0.5px #BC8900;
        }

        .quiz-container {
            background-color: white;
            border: 3px solid #DCDCDC;
            border-radius: 15px;
            padding: 1rem;
            width: 100%;
            max-height: 70vh;
            overflow-y: auto;
            box-shadow: 2px 4px 2px 0 rgba(0, 0, 0, 0.2);
            position: relative;
        }

        body.dark-mode .quiz-container {
            background-color: #2d2d2d;
            border-color: #444;
        }

        .edit-quiz {
            border-bottom: 3px solid #DCDCDC;
            font-family: Fredoka !important;
            background: white;
            width: 100%;
            padding: 1rem;
            margin-bottom: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        body.dark-mode .edit-quiz {
            background-color: #2d2d2d;
            border-color: #444;
            color: #e0e0e0;
        }

        #select {
            font-family: Fredoka;
            color: black;
            font-size: clamp(16px, 2.5vw, 25px);
            font-weight: 500;
            line-height: 1.5;
            animation-name: checkbox_fade;
            animation-duration: 1s;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        body.dark-mode #select {
            color: #e0e0e0;
        }

        #selectQuiz {
            background-color: #F8B500;
            border-radius: 8px;
            padding: 0.75rem;
            color: black;
            font-size: clamp(14px, 2vw, 20px);
            font-weight: bold;
            cursor: pointer;
            border: none;
            transition: transform 0.2s;
            min-height: 44px;
            min-width: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #selectQuiz:hover {
            transform: scale(1.1);
        }

        #deleteBtn {
            font-family: 'Fredoka' !important;
            background-color: #F8B500;
            border-radius: 8px;
            border: none;
            padding: 0.5rem 1rem;
            color: white;
            font-size: clamp(13px, 1.5vw, 15px);
            cursor: pointer;
            animation-name: checkbox_fade;
            animation-duration: 1s;
            box-shadow: 0 6px 0 0 #BC8900;
            transition: all 0.3s ease;
            min-height: 44px;
            white-space: nowrap;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        #deleteBtn:hover {
            background-color: #BC8900;
        }

        .quiz-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(min(280px, 100%), 1fr));
            gap: 1rem;
            padding: 1rem 0;
        }

        .quiz-items {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
        }

        .quiz-btn {
            background-color: white;
            width: 100%;
            padding: 1rem;
            border: 2px solid #f8b500;
            border-radius: 8px;
            box-shadow: 0 4px 0 0 #BC8900;
            text-decoration: none;
            text-align: center;
            font-family: Fredoka;
            font-size: clamp(14px, 2vw, 22px);
            color: black;
            cursor: pointer;
            transition: all 0.3s ease;
            display: block;
            word-wrap: break-word;
            min-height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            word-break: break-word;
            line-height: 1.3;
        }

        body.dark-mode .quiz-btn {
            background-color: #2d2d2d;
            color: #e0e0e0;
        }

        .quiz-btn:hover {
            background-color: #f8b500;
            color: white;
        }

        body.dark-mode .quiz-btn:hover {
            background-color: #f8b500;
            color: white;
        }

        .quiz-btn:active {
            background-color: #f8b500;
            color: white;
            box-shadow: 0 2px 0 0 #BC8900;
        }

        input[type="checkbox"] {
            width: 20px;
            height: 20px;
            border-color: #7D3200;
            cursor: pointer;
            accent-color: #F8B500;
            animation-name: checkbox_fade;
            animation-duration: 1s;
            margin-top: 0.5rem;
            min-height: 20px;
            min-width: 20px;
        }

        .no-quiz-container {
            text-align: center;
            padding: 3rem 1rem;
            grid-column: 1 / -1;
        }

        .img-no-quiz {
            width: 130px;
            height: 120px;
            margin: 0 auto 2rem;
            border-radius: 50%;
        }

        .no-quiz-btn {
            font-size: clamp(18px, 2.5vw, 22px);
            line-height: 1.2;
            color: #666;
        }

        body.dark-mode .no-quiz-btn {
            color: #b0b0b0;
        }

        #status {
            position: relative;
            text-align: center;
            background-color: #F8B500;
            border-radius: 10px;
            width: 100%;
            max-width: 400px;
            font-family: Fredoka;
            margin: 0 auto 2rem;
            padding: 1rem;
            color: white;
            word-wrap: break-word;
        }

        #close-btn {
            font-family: Fredoka;
            font-size: 24px;
            position: absolute;
            top: 0.5rem;
            right: 1rem;
            color: white;
            cursor: pointer;
            background: none;
            border: none;
            min-height: 30px;
            min-width: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #close-btn:hover {
            color: #CF5300;
            transition: 0.3s;
        }

        .dropdown-content {
            width: min(250px, 80vw);
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
            top: -10px;
            right: 20px;
            transform: rotate(45deg);
            z-index: -1;
        }

        .dropdown-content button {
            background-color: transparent;
            font-family: 'Fredoka';
            font-size: clamp(14px, 1.5vw, 16px);
            border: 2px solid white;
            color: white;
            width: 90%;
            padding: 12px;
            margin: 8px 5%;
            text-decoration: none;
            display: block;
            text-align: center;
            transition: all 0.3s;
            border-radius: 10px;
            cursor: pointer;
            letter-spacing: 1px;
            min-height: 44px;
        }

        .dropdown-content button:hover {
            background-color: white;
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

        .profile {
            position: relative;
            cursor: pointer;
        }

        .profile-pic {
            border: 2px solid #f8b500;
            object-fit: cover;
        }

        /* Scrollbar styles */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            box-shadow: inset 0 0 5px grey;
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb {
            background: #f8b500;
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #A34404;
        }

        @keyframes checkbox_fade {
            from {opacity: 0}
            to {opacity: 1}
        }

        /* Mobile Responsive Design */
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
            
            .create-q-button {
                justify-content: center;
            }
            
            .create-q-button a {
                width: 100%;
                max-width: 200px;
            }
            
            .quiz-container {
                max-height: 60vh;
                padding: 0.75rem;
            }
            
            .edit-quiz {
                flex-direction: column;
                align-items: stretch;
                padding: 0.75rem;
                gap: 0.75rem;
            }
            
            .edit-quiz > div {
                display: flex;
                justify-content: space-between;
                align-items: center;
                width: 100%;
            }
            
            #select {
                font-size: clamp(16px, 4vw, 20px);
            }
            
            .quiz-grid {
                grid-template-columns: 1fr;
                gap: 0.75rem;
            }
            
            .quiz-btn {
                padding: 0.75rem;
                min-height: 50px;
                font-size: clamp(14px, 3vw, 18px);
            }
            
            #status {
                width: 95%;
                margin: 0 auto 1rem;
                padding: 0.75rem;
                font-size: clamp(14px, 3vw, 16px);
            }
            
            .dropdown-content {
                width: min(220px, 75vw);
                right: 0.5rem;
            }
            
            .dropdown-content:before {
                right: 15px;
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
            
            .mobile-toggle {
                display: none;
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
            
            .content-header h1 {
                font-size: 1.3rem;
                padding: 0.5rem;
            }
            
            .create-q-button {
                margin-bottom: 1.5rem;
            }
            
            .create-q-button a {
                padding: 0.6rem 1.2rem;
                font-size: 14px;
            }
            
            .edit-quiz {
                padding: 0.5rem;
                gap: 0.5rem;
            }
            
            #selectQuiz, #deleteBtn {
                padding: 0.5rem;
                font-size: 13px;
            }
            
            #select {
                font-size: 16px;
            }
            
            .quiz-grid {
                gap: 0.5rem;
                padding: 0.5rem 0;
            }
            
            .quiz-btn {
                padding: 0.6rem;
                min-height: 45px;
                font-size: 14px;
            }
            
            .dark-mode-toggle {
                bottom: 10px;
                right: 10px;
                width: 50px;
                height: 50px;
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
                font-size: 1.2rem;
            }
            
            .quiz-container {
                padding: 0.5rem;
                max-height: 55vh;
            }
            
            .edit-quiz {
                padding: 0.4rem;
            }
            
            #select {
                font-size: 14px;
            }
            
            .dropdown-content {
                width: min(200px, 70vw);
            }
            
            .dropdown-content button {
                font-size: 13px;
                padding: 8px 10px;
                min-height: 36px;
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
            
            .create-q-button a {
                padding: 0.5rem 1rem;
                font-size: 13px;
            }
            
            .dropdown-content {
                width: min(180px, 65vw);
            }
            
            .dropdown-content button {
                font-size: 12px;
                padding: 7px 8px;
                min-height: 34px;
            }
        }

        /* Tablet responsive */
        @media (min-width: 769px) and (max-width: 1024px) {
            .quiz-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            }
            
            .content {
                padding: 1.5rem;
            }
        }

        /* Large screen adjustments */
        @media (min-width: 1400px) {
            .quiz-grid {
                grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            }
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

<div class="container">
    <!-- Top Navigation for Mobile -->
    <nav class="top-nav" id="topNav">
        <div class="logo">
            <img src="img/logo 6.png" alt="QuizZap Logo">
        </div>
        <div class="menu" id="topNavMenu">
            <a href="t_Home.php" title="Dashboard">
                <i class="fa-solid fa-house"></i>
                <span>Dashboard</span>
            </a>
            <a href="t_SubjectsList.php" title="Subjects">
                <i class="fa-solid fa-list"></i>
                <span>Subjects</span>
            </a>
            <a href="t_quizDash.php?subject_id=<?php echo $subject_id; ?>" class="active" title="Quizzes">
                <i class="fa-regular fa-circle-question"></i>
                <span>Quizzes</span>
            </a>
        </div>
        <div class="top-nav-profile">
            <div class="profile" onclick="profileDropdown()">
                <img src="uploads/profiles/<?php echo htmlspecialchars($profile_pic); ?>" alt="Profile Picture" class="profile-pic" onerror="this.src='uploads/profiles/default-profile.jpg'" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
                <div id="dropdown" class="dropdown-content">
                    <button onclick="window.location.href='s_Profile.php'"><i class="fa-solid fa-user"></i> Profile</button> 
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

    <!-- Sidebar - Hidden on mobile -->
    <div class="sidebar" id="sidebar">
        <header>
            <button id="toggleSidebar" class="toggle-btn">
                <i class="fas fa-bars"></i>
            </button>
            <div class="logo">
                <img src="img/logo1.png" width="200px" height="80px" class="logo-img">
                <img src="img/logo 2.png" width="50px" height="50px" class="logo-icon" style="display: none; margin-top: 10%;">
            </div>
        </header>
        <hr style="border: 1px solid #f8b500;">
        <div class="menu">
            <a href="t_SubjectsList.php" title="Subject List">
                <i class="fa-solid fa-list"></i>
                <span>Subjects</span>
            </a>
            <a href="t_quizDash.php?subject_id=<?php echo $subject_id; ?>" class="active" title="Quiz Dash">
                <i class="fa-regular fa-circle-question"></i>
                <span>Quizzes</span>
            </a>
            <a href="t_rankings.php?subject_id=<?php echo $subject_id; ?>" title="Rankings">
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
    <div class="content" id="content">
        <div class="content-header">
            <div>
                <h1><?php echo htmlspecialchars($subject['subject_name']); ?> - Grade <?php echo htmlspecialchars($subject['grade_level']); ?> - <?php echo htmlspecialchars($subject['section']); ?></h1>
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

        <div class="create-q-button">
            <a href="t_selectquiztype.php?subject_id=<?php echo $subject_id; ?>">Create Quiz</a>
        </div>

        <?php 
        if(isset($_SESSION['status'])) {
            ?>
            <div id="status">
                <button id="close-btn" onclick="document.getElementById('status').style.display = 'none';">&times;</button>
                <?php echo $_SESSION['status']; ?>
            </div>
            <?php
            unset($_SESSION['status']);
        }
        ?>

        <div class="quiz-container">
            <form action="delete_quiz.php?subject_id=<?php echo $subject_id; ?>" method="POST">
                <div class="edit-quiz">
                    <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                        <p id="select" style="display: none;">Select Quizzes to Delete</p>
                        <div style="display: flex; gap: 0.5rem;">
                            <button type="button" onclick="quizCheckbox()" id="selectQuiz">
                                <i class="fa-regular fa-pen-to-square"></i>
                            </button>
                            <button type="submit" name="delete_quiz_btn" id="deleteBtn" 
                                    onclick="return confirm('Are you sure you want to proceed on deleting the selected item/s?');" 
                                    style="display: none;">
                                <i class="fa-solid fa-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                </div>

                <div class="quiz-grid">
                    <?php
                    if ($result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            echo "<div class='quiz-items'>";
                            echo "<div class='quiz-btn'>" . htmlspecialchars($row['title']) . "</div>";
                            echo "<input type='checkbox' name='delete_quiz[]' value='" . $row['quiz_id'] . "' class='quiz-checkbox' style='display: none;'>";
                            echo "</div>";
                        }
                    } else {
                        echo "<div class='no-quiz-container'>";
                        echo "<div class='no-quiz-btn'>No quizzes created yet.</div>";
                        echo "</div>";
                    }
                    ?>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const content = document.getElementById('content');
    const toggleBtn = document.getElementById('toggleSidebar');
    const darkModeToggle = document.getElementById('darkModeToggle');
    const body = document.body;

    // Check for saved dark mode preference
    const isDarkMode = localStorage.getItem('darkMode') === 'true';

    // Apply dark mode on page load if enabled
    if (isDarkMode) {
        document.body.classList.add('dark-mode');
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

    // Check if sidebar state is saved in localStorage
    const isSidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
    
    function updateLayout() {
        if (window.innerWidth <= 768) {
            // Mobile layout
            sidebar.style.display = 'none';
            content.classList.add('mobile-full');
            content.style.marginLeft = '0';
            content.style.width = '100%';
        } else {
            // Desktop layout
            sidebar.style.display = 'flex';
            content.classList.remove('mobile-full');
            
            if (isSidebarCollapsed) {
                sidebar.classList.add('collapsed');
                content.classList.add('expanded');
                content.style.marginLeft = '90px';
                content.style.width = 'calc(100% - 90px)';
            } else {
                sidebar.classList.remove('collapsed');
                content.classList.remove('expanded');
                content.style.marginLeft = '250px';
                content.style.width = 'calc(100% - 250px)';
            }
        }
    }

    // Initialize layout
    updateLayout();

    // Update layout on resize
    window.addEventListener('resize', updateLayout);

    // Desktop sidebar toggle
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function() {
            if (window.innerWidth > 768) {
                sidebar.classList.toggle('collapsed');
                content.classList.toggle('expanded');
                
                if (sidebar.classList.contains('collapsed')) {
                    content.style.marginLeft = '90px';
                    content.style.width = 'calc(100% - 90px)';
                } else {
                    content.style.marginLeft = '250px';
                    content.style.width = 'calc(100% - 250px)';
                }
                
                localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
            }
        });
    }

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

    // Improve touch targets for mobile
    const touchElements = document.querySelectorAll('a, button, .dropdown-content button, .quiz-btn');
    touchElements.forEach(element => {
        element.style.minHeight = '44px';
        element.style.minWidth = '44px';
        element.style.display = 'flex';
        element.style.alignItems = 'center';
        element.style.justifyContent = 'center';
    });

    // Handle text truncation for responsive design
    function handleTextTruncation() {
        const quizButtons = document.querySelectorAll('.quiz-btn');
        const selectText = document.getElementById('select');
        
        quizButtons.forEach(button => {
            if (window.innerWidth <= 480) {
                button.style.whiteSpace = 'normal';
                button.style.overflow = 'hidden';
                button.style.textOverflow = 'ellipsis';
                button.style.display = '-webkit-box';
                button.style.webkitLineClamp = '2';
                button.style.webkitBoxOrient = 'vertical';
            } else {
                button.style.whiteSpace = 'normal';
                button.style.overflow = 'visible';
                button.style.textOverflow = 'clip';
                button.style.display = 'flex';
                button.style.webkitLineClamp = 'none';
            }
        });
        
        if (selectText && window.innerWidth <= 480) {
            selectText.style.whiteSpace = 'nowrap';
            selectText.style.overflow = 'hidden';
            selectText.style.textOverflow = 'ellipsis';
            selectText.style.maxWidth = '150px';
        }
    }

    // Call on load and resize
    handleTextTruncation();
    window.addEventListener('resize', handleTextTruncation);
});

// Make functions global
function quizCheckbox() {
    var checkboxes = document.querySelectorAll('.quiz-checkbox');
    var deleteBtn = document.getElementById("deleteBtn");
    var select = document.getElementById("select");
    
    checkboxes.forEach(function(checkbox) {
        if (checkbox.style.display === "none") {
            checkbox.style.display = "block";
        } else {
            checkbox.style.display = "none";
        }
    });

    if (deleteBtn.style.display === "none" && select.style.display === "none") {
        deleteBtn.style.display = "inline-flex";
        select.style.display = "block";
    } else {
        deleteBtn.style.display = "none";
        select.style.display = "none";
    }
}

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