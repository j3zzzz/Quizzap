<?php
session_start(); // Start the session

// Check if the user is logged in and is a student
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

$stmt->close();

// Fetch the actual student_id from the students table
$sql = "SELECT student_id FROM students WHERE account_number = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $loggedInUser);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Student not found in the database.");
}

$row = $result->fetch_assoc();
$student_id = $row['student_id']; // Use the actual student_id from the database

// Display enrolled subjects
$sql = "SELECT s.subject_id, s.subject_code, s.subject_name 
        FROM enrollments e 
        JOIN subjects s ON e.subject_id = s.subject_id 
        WHERE e.student_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $student_id);
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
    <title>QuizZap Dashboard</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Fredoka';
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
            padding-bottom: 10px;
            border-bottom: 1.5px solid #F8B500;
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

        .subjects-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            padding-bottom: 2rem;
            width: 100%;
        }

        .subject-button {
            color: black;
            font-family: Fredoka;
            font-weight: 500;
            font-size: clamp(18px, 2vw, 24px);
            background-color: white;
            display: flex;
            flex-direction: column;
            border-radius: 6px;
            border: 2px solid #f8b500;
            text-decoration: none;
            text-align: left;
            padding: 20px;
            width: 100%;
            transition: transform .2s, background-color 0.3s;
            box-shadow: 0 6px 0 0 #BC8900;
            min-height: 120px;
            justify-content: center;
        }

        body.dark-mode .subject-button {
            background-color: #2d2d2d;
            color: #e0e0e0;
        }

        .subject-button:hover {
            background-color: #F8B500;
            color: white;
            transform: translateY(-2px);
        }

        body.dark-mode .subject-button:hover {
            background-color: #F8B500;
            color: white;
        }

        .subject-button:active {
            background-color: #F8B500;
            box-shadow: 3px 4px 0 0 rgba(0, 0, 0, 0.3);
            transform: translateY(2px);
        }

        .subject-button span {
            font-size: clamp(14px, 1.5vw, 15px);
            font-family: Fredoka;
            font-weight: 500;
            color: #f8b500;
            margin-top: 8px;
        }

        body.dark-mode .subject-button span {
            color: #f8b500;
        }

        .subject-button:hover span {
            color: white;
        }

        .no-quiz-con {
            text-align: center;
            padding: 2rem;
            color: #999;
            font-size: 1.2rem;
        }

        body.dark-mode .no-quiz-con {
            color: #b0b0b0;
        }

        /* width */
        ::-webkit-scrollbar {
          width: 10px;
          height: 10px;
        }

        /* Track */
        ::-webkit-scrollbar-track {
          box-shadow: inset 0 0 5px grey; 
          border-radius: 10px;
        }
         
        /* Handle */
        ::-webkit-scrollbar-thumb {
          background: #F8B500; 
          border-radius: 10px;
        }

        /* Handle on hover */
        ::-webkit-scrollbar-thumb:hover {
          background: #F8B500; 
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

        .dropdown-content a:hover, .dropdown-content button:hover{
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

        /* Mobile Menu Toggle Button */
        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1001;
            background: #f8b500;
            color: white;
            border: none;
            border-radius: 5px;
            padding: 12px;
            font-size: 1.2rem;
            cursor: pointer;
            min-height: 44px;
            min-width: 44px;
            align-items: center;
            justify-content: center;
        }

        /* Enhanced Responsive Design */
        @media (max-width: 1200px) {
            .subjects-container {
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
                gap: 1.5rem;
            }
            
            .sidebar {
                width: 220px;
            }
            
            .content {
                margin-left: 220px;
                width: calc(100% - 220px);
            }
            
            .content.expanded {
                margin-left: 70px;
                width: calc(100% - 70px);
            }
        }

        @media (max-width: 992px) {
            .sidebar {
                width: 200px;
            }
            
            .content {
                margin-left: 200px;
                width: calc(100% - 200px);
                padding: 1.5rem;
            }
            
            .subjects-container {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            }
        }

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
            
            .subjects-container {
                grid-template-columns: repeat(auto-fill, minmax(100%, 1fr));
                gap: 1rem;
            }
            
            .subject-button {
                padding: 15px;
                min-height: 100px;
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
            
            .subjects-container {
                grid-template-columns: 1fr;
            }
            
            .subject-button {
                padding: 15px;
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

            .subjects-container{
                align-items: flex-start;
            }
            
            .subject-button {
                padding: 15px;
                min-height: auto;
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
            
            .subject-button {
                padding: 12px;
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
        .subject-button:focus-visible {
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
            <a href="s_Home.php" title="Dashboard">
                <i class="fa-solid fa-house"></i>
                <span>Dashboard</span>
            </a>
            <a href="s_Classes.php" class="active" title="Classes">
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
                <a href="s_Home.php" title="Dashboard">
                    <i class="fa-solid fa-house"></i>
                    <span>Dashboard</span>
                </a>
                <a href="s_Classes.php" class="active" title="Classes">
                <i class="fa-regular fa-address-book"></i>
                    <span>Classes</span>
                </a>
            </div>
        </div>

        <!-- Content Area -->
        <div class="content">
            <div class="content-header">
                <div>
                    <h1>Classes</h1>
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
            
            <div class="subjects-container">
                <?php
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        echo "<a class='subject-button' href='select_quiz.php?subject_id=" . $row['subject_id'] . "'>" . $row['subject_name'] ."<span>". $row['subject_code'] ."</span></a>";
                    }
                } else {
                    echo "<div class='no-quiz-con'>";
                    echo "<p>No subjects enrolled yet.</p>";
                    echo "</div>";
                }
                ?>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
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

            // Toggle sidebar when button is clicked
            if (toggleBtn) {
                toggleBtn.addEventListener('click', function() {
                    sidebar.classList.toggle('collapsed');
                    content.classList.toggle('expanded');
                    
                    // Save state to localStorage
                    localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
                });
            }

            // Dark Mode Functionality
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
            // Improve touch targets for mobile
            const touchElements = document.querySelectorAll('a, button, .subject-button');
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
    </script>
</body>
</html>