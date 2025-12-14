<?php
session_start();
if (!isset($_SESSION['account_number']) || strpos($_SESSION['account_number'], 'A') !== 0) {
    header("Location: admin_login.php");
    exit();
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "rawrit";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle Delete Action
if (isset($_GET['delete'])) {
    $account_number = $_GET['delete'];
    $sql = "DELETE FROM students WHERE account_number = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $account_number);
    if ($stmt->execute()) {
        $success_message = "Student deleted successfully!";
    } else {
        $error_message = "Error deleting student: " . $stmt->error;
    }
    $stmt->close();
}

// Handle Edit Form Submission
if (isset($_POST['update'])) {
    $account_number = $_POST['account_number'];
    $fname = $_POST['fname'];
    $lname = $_POST['lname'];
    $glevel = $_POST['glevel'];
    $strand = $_POST['strand'];
    $section = $_POST['section'];
    
    $sql = "UPDATE students SET fname=?, lname=?, glevel=?, strand=?, section=? WHERE account_number=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssss", $fname, $lname, $glevel, $strand, $section, $account_number);
    
    if ($stmt->execute()) {
        $success_message = "Student updated successfully!";
        // Redirect to avoid form resubmission
        header("Location: a_Students.php");
        exit();
    } else {
        $error_message = "Error updating student: " . $stmt->error;
    }
    $stmt->close();
}

// Handle Add Student Form Submission
if (isset($_POST['add_student'])) {
    $account_number = $_POST['account_number'];
    $fname = $_POST['fname'];
    $lname = $_POST['lname'];
    $glevel = $_POST['glevel'];
    $strand = $_POST['strand'];
    $section = $_POST['section'];
    $school_id = $_POST['school_id'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT); // Hash the password
    
    // Check if account number already exists
    $check_sql = "SELECT * FROM students WHERE account_number = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $account_number);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $error_message = "Account number already exists!";
    } else {
        $sql = "INSERT INTO students (account_number, fname, lname, glevel, strand, section, password, profile_pic, school_id) VALUES (?, ?, ?, ?, ?, ?, ?, 'default_profile.png', ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssss", $account_number, $fname, $lname, $glevel, $strand, $section, $password, $school_id);
        
        if ($stmt->execute()) {
            $success_message = "Student added successfully!";
            // Redirect to avoid form resubmission
            header("Location: a_Students.php");
            exit();
        } else {
            $error_message = "Error adding student: " . $stmt->error;
        }
        $stmt->close();
    }
    $check_stmt->close();
}

$loggedInUser = $_SESSION['account_number'];

// Fetch admin's profile pic
$sql = "SELECT profile_pic FROM admins WHERE account_number = ?";
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

// Search functionality
$search = '';
$whereClause = '';
if (isset($_GET['search'])) {
    $search = $_GET['search'];
    $whereClause = "WHERE fname LIKE '%$search%' OR lname LIKE '%$search%' OR account_number LIKE '%$search%' OR glevel LIKE '%$search%' OR strand LIKE '%$search%' OR section LIKE '%$search%'";
}

// Fetch all students
$studentsQuery = $conn->prepare("SELECT * FROM students $whereClause ORDER BY student_id DESC");
$studentsQuery->execute();
$studentsResult = $studentsQuery->get_result();

// Count total students
$studentCountQuery = $conn->prepare("SELECT COUNT(*) as count FROM students");
$studentCountQuery->execute();
$studentCountResult = $studentCountQuery->get_result();
$studentCount = $studentCountResult->fetch_assoc()['count'];

// Fetch single student for view/edit
$studentToView = null;
if (isset($_GET['view']) || isset($_GET['edit'])) {
    $account_number = $_GET['view'] ?? $_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM students WHERE account_number = ?");
    $stmt->bind_param("s", $account_number);
    $stmt->execute();
    $result = $stmt->get_result();
    $studentToView = $result->fetch_assoc();
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="other resources/fontawesome-free-6.5.2-web/css/all.min.css">
    <title>Manage Students</title>
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
            transition: margin-left 0.3s ease;
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

        .content-header p {
            color: #999;
            font-size: 1rem;
            margin-top: 0.5rem;
            font-family: 'Fredoka';
            font-weight: 500;
            width: 100%;
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

        .cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1rem;
        }

        .cards p, .cards h3, .cards a, .ranking-card h3 {
            font-family: 'Fredoka' !important;
        }

        .enroll-card {
            font-family: 'Fredoka';
            background-color: #ffffff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.15);
            min-height: 200px;
            display: flex;
            flex-direction: column;
        }

        .header{
            float: left;
            display: flex ;
        }

        .enroll-card p {
            font-family: 'Fredoka';
            font-weight: 500;
            font-size: 4rem;
            text-align: center;
            margin: auto;
            color: #4d4d4d;
        }

        .enroll-card a {
            font-family: 'Fredoka';
            font-weight: 600;
            text-decoration: none;
            color: #f8b500;
            align-self: flex-end;
            margin-top: auto;
        }

        h3 {
            font-family: 'Fredoka';
            font-weight: bold;
            font-size: 1.5rem;
            margin: auto;
        }

        .success-quiz-card {
            font-family: 'Fredoka';
            background-color: #ffffff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.15);
            min-height: 200px;
            display: flex;
            flex-direction: column;
        }

        .icon {
            float: left;
            font-size: 30px;
            color: #F8B500;
            border-radius: 100%;
            border: 3px solid #F8B500;
            padding: 2rem;
            margin-right: 5%;
            flex-shrink: 0;
        }

        .ranking-card {
            background-color: #ffffff;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.15);
            min-height: 300px;
        }

        .ranking-card p {
            font-family: 'Fredoka' !important;
            font-size: 4rem;
            text-align: center;
            margin: 1rem 0;
        }

        #scores-cont {
            font-family: 'Fredoka';
            width: 100%;
            padding: 10px;
        }

        /* Table header styles */
        .ranking-header {
            font-family: 'Fredoka';
            display: grid;
            grid-template-columns: 1fr 2fr 1fr;
            padding: 0.5rem;
            margin-bottom: 1rem;
            gap: 0.5rem;
        }

        .ranking-header span {
            font-family: 'Fredoka';
            font-weight: bold;
            font-size: 1rem;
            color: #f8b500;
            text-align: center;
        }

        /* Ranking rows container */
        .ranking-rows {
            font-family: 'Fredoka';
            display: flex;
            flex-direction: column;
            gap: 0.8rem;
        }

        /* Individual ranking row */
        .ranking-row {
            font-family: 'Fredoka';
            display: grid;
            grid-template-columns: 1fr 2fr 1fr;
            padding: 0.5rem;
            border-radius: 15px;
            align-items: center;
            font-weight: 500;
            gap: 0.5rem;
        }

        .ranking-row div {
            cursor: pointer;
        }

        .ranking-row-noquiz {
            font-family: 'Fredoka';
            color: #6666;
            text-align: center;
            padding: 1rem;
        }

        /* Different background colors for each position */
        .ranking-row:nth-child(1) {
            background: #ffc62c;
        }

        .ranking-row:nth-child(1) i {
            color: #FFD700;
            border-radius: 100%;
            background-color: white;
            padding: 5px;
            text-align: center;
            margin-right: 5%;
            font-size: 0.8rem;
        }

        .ranking-row:nth-child(2) {
            background: #ffd460;
        }

        .ranking-row:nth-child(2) i {
            color: #C0C0C0;
            border-radius: 100%;
            background-color: white;
            padding: 5px;
            text-align: center;
            margin-right: 5%;
            font-size: 0.8rem;
        }

        .ranking-row:nth-child(3) {
            background: #ffe293;
        }

        .ranking-row:nth-child(3) i {
            color: #CD7F32;
            border-radius: 100%;
            background-color: white;
            padding: 5px;
            text-align: center;
            margin-right: 5%;
            font-size: 0.8rem;
        }

        .ranking-row:nth-child(n+4) {
            background: #ffe9ad;
        }

        /* Name styles */
        .stud-name {
            font-family: 'Fredoka';
            font-size: 1rem;
            text-align: center;
        }

        .subject {
            font-family: 'Fredoka';
            font-size: 0.9rem;
            text-align: center;
            color: #444;
        }

        /* Score styles */
        .score {
            font-family: 'Fredoka';
            font-size: 1.2rem;
            font-weight: bold;
            text-align: center;
        }

        .dropdown-content {
            width: 300px;
            right: -1%;
            display: none;
            position: absolute;
            background-color: #F8B500;
            border-radius: 15px;
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
            z-index: 1;
            padding: 10px 0;
            top: 135%;
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

        /* Mobile menu toggle */
        .menu-toggle {
            display: none;
            cursor: pointer;
            font-size: 1.5rem;
            color: #333;
            padding: 0.5rem;
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 1001;
            background: rgba(255,255,255,0.8);
            border-radius: 5px;
        }

        .profile {
            position: relative;
            cursor: pointer;
        }

        .profile-pic {
            border: 2px solid #f8b500;
        }

        /* Responsive adjustments */
        @media (max-width: 1200px) {
            .sidebar {
                width: 220px;
            }
            .content {
                margin-left: 220px;
                width: calc(100% - 220px);
            }
        }

        @media (max-width: 992px) {
            .cards {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .menu-toggle {
                display: block;
            }
            
            .sidebar {
                width: 250px;
                transform: translateX(-100%);
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .content {
                margin-left: 0;
                width: 100%;
                padding: 1rem;
                padding-top: 4rem;
            }
            
            .toggle-btn {
                font-size: 1.2rem;
            }

            .content-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .content-header .actions {
                width: 100%;
                justify-content: space-between;
                margin-top: 1rem;
            }
            
            .dropdown-content {
                right: 10px;
                width: 200px;
            }
            
            .enroll-card .icon {
                padding: 1rem;
                font-size: 20px;
                margin-right: 0.5rem;
            }
            
            .enroll-card p, 
            .ranking-card p {
                font-size: 3rem;
            }
            
            .ranking-header span {
                font-size: 0.9rem;
            }
            
            .stud-name {
                font-size: 0.9rem;
            }
            
            .score {
                font-size: 1rem;
            }
        }

        @media (max-width: 480px) {
            .content {
                padding: 0.5rem;
                padding-top: 4rem;
            }
            
            .content-header h1 {
                font-size: 1.5rem;
            }
            
            .content-header .actions a {
                padding: 0.5rem;
                font-size: 0.9rem;
            }
            
            .enroll-card, 
            .success-quiz-card, 
            .ranking-card {
                padding: 1rem;
            }
            
            .enroll-card h3, 
            .success-quiz-card h3, 
            .ranking-card h3 {
                font-size: 1.2rem;
            }
            
            .ranking-header {
                grid-template-columns: 0.5fr 2fr 1fr;
            }
            
            .dropdown-content {
                width: 180px;
                right: 5px;
            }
            
            .dropdown-content button {
                font-size: 14px;
                padding: 8px 10px !important;
            }
        }  

        /* Updated table styles */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            border-spacing: 0;
            margin-top: 1rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            font-size: 0.9rem;
            border-radius: 10px;
            overflow: hidden;
        }

        .data-table thead {
            background-color: #f8b500;
        }

        .data-table th {
            padding: 12px 0.75rem;
            text-align: left;
            color: white;
            font-weight: bold;
            position: sticky;
            top: 0;
            border: none !important;
            margin: 0;
        }

        .data-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
        }

        .data-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .data-table tr:hover {
            background-color: #f5f5f5;
        }

        .student-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #f8b500;
            margin-right: 10px;
        }

        .student-name {
            display: flex;
            align-items: center;
        }

        .action-btns {
            white-space: nowrap;
        }

        .action-btns a {
            color: #f8b500;
            margin-right: 0.5rem;
            text-decoration: none;
            font-size: 1rem;
            padding: 5px;
            border-radius: 4px;
            transition: all 0.3s;
        }

        .action-btns a:hover {
            background-color: #f8b500;
            color: white;
        }

        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: bold;
            text-transform: uppercase;
        }

        .badge-tvl {
            background-color: #d4edda;
            color: #155724;
        }

        .badge-abm {
            background-color: #cce5ff;
            color: #004085;
        }

        .badge-humss {
            background-color: #fff3cd;
            color: #856404;
        }

        .badge-stem {
            background-color: #f8d7da;
            color: #721c24;
        }

        .badge-g11, .badge-11 {
            background-color: #e2e3e5;
            color: #383d41;
        }

        .badge-g12, .badge-12 {
            background-color: #d1ecf1;
            color: #0c5460;
        }

        .badge-7, .badge-8, .badge-9, .badge-10 {
            background-color: #d4edda;
            color: #155724;
        }

        .badge-section {
            background-color: #e2d4f0;
            color: #4a2d7a;
        }

        .search-container {
            display: flex;
            margin-bottom: 1.5rem;
            gap: 10px;
        }

        .search-container input {
            flex: 1;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-family: 'Fredoka';
            font-size: 1rem;
        }

        .search-container button {
            background-color: #f8b500;
            color: white;
            border: none;
            padding: 0 1.5rem;
            border-radius: 5px;
            cursor: pointer;
            font-family: 'Fredoka';
            font-size: 1rem;
            transition: background 0.3s;
        }

        .search-container button:hover {
            background-color: #e5941f;
        }

        .add-new-btn {
            background-color: #f8b500;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
            margin-bottom: 1rem;
            font-family: 'Fredoka';
            font-size: 1rem;
            transition: background 0.3s;
        }

        .add-new-btn:hover {
            background-color: #e5941f;
        }

        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 1.5rem;
            gap: 5px;
        }

        .pagination a {
            color: #f8b500;
            padding: 0.5rem 1rem;
            text-decoration: none;
            border: 1px solid #ddd;
            border-radius: 5px;
            transition: all 0.3s;
        }

        .pagination a.active {
            background-color: #f8b500;
            color: white;
            border: 1px solid #f8b500;
        }

        .pagination a:hover:not(.active) {
            background-color: #f5f5f5;
        }

        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #666;
        }

        .empty-state i {
            font-size: 3rem;
            color: #ddd;
            margin-bottom: 1rem;
        }

        .student-name {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .student-name div {
            display: flex;
            flex-direction: column;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn-view {
            color: #17a2b8;
        }

        .btn-edit {
            color: #ffc107;
        }

        .btn-delete {
            color: #dc3545;
        }

        .btn-view:hover {
            background-color: #17a2b8 !important;
            color: white !important;
        }

        .btn-edit:hover {
            background-color: #ffc107 !important;
            color: white !important;
        }

        .btn-delete:hover {
            background-color: #dc3545 !important;
            color: white !important;
        }

        /* Make table rows more compact */
        .data-table td {
            padding: 0.5rem 0.75rem !important;
        }

        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0,0,0,0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        
        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        
        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            transform: translateY(-20px);
            transition: all 0.3s ease;
        }
        
        .modal-overlay.active .modal-content {
            transform: translateY(0);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .modal-header h2 {
            margin: 0;
            color: #333;
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            font-family: 'Fredoka';
        }
        
        .form-row {
            display: flex;
            gap: 1rem;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 5px;
            cursor: pointer;
            font-family: 'Fredoka';
            font-size: 1rem;
            transition: all 0.3s;
            text-decoration: none;
            text-align: center;
        }
        
        .btn-primary {
            background-color: #f8b500;
            color: white;
            border: none;
        }
        
        .btn-primary:hover {
            background-color: #e5941f;
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
            border: none;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        
        .btn-danger {
            background-color: #dc3545;
            color: white;
            border: none;
        }
        
        .btn-danger:hover {
            background-color: #c82333;
        }

        @media (max-width: 1200px) {
            .data-table {
                font-size: 0.85rem;
            }
            
            .data-table th, .data-table td {
                padding: 0.6rem;
            }
        }

        @media (max-width: 992px) {
            .data-table {
                display: block;
                width: 100%;
            }
            
            .data-table thead {
                display: none;
            }
            
            .data-table tr {
                display: block;
                margin-bottom: 1rem;
                border: 1px solid #eee;
                border-radius: 5px;
            }
            
            .data-table td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 0.75rem;
                border-bottom: 1px solid #eee;
            }
            
            .data-table td:before {
                content: attr(data-label);
                font-weight: bold;
                margin-right: 1rem;
                color: #f8b500;
            }
            
            .student-name {
                justify-content: space-between;
                width: 100%;
            }
            
            .action-btns {
                display: flex;
                justify-content: flex-end;
                width: 100%;
            }
        }

        @media (max-width: 576px) {
            .search-container {
                flex-direction: column;
            }
            
            .search-container button {
                padding: 0.75rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
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
                <a href="a_Home.php" title="Dashboard">
                    <i class="fa-solid fa-house"></i>
                    <span>Dashboard</span>
                </a>
                <a href="a_Students.php" class="active" title="Students">
                    <i class="fa-solid fa-graduation-cap"></i>
                    <span>Students</span>
                </a>
                <a href="a_Teachers.php" title="Teachers">
                    <i class="fa-solid fa-chalkboard-user"></i>
                    <span>Teachers</span>
                </a>
                <a href="a_Classes.php" title="Classes">
                    <i class="fa-solid fa-list"></i>
                    <span>Classes</span>
                </a>
                <a href="a_item-analysis.php" title="Item Analysis">
                    <i class="fa-solid fa-chart-bar"></i>
                    <span>Item Analysis</span>
                </a>
            </div>
        </div>

        <!-- Content Area -->
        <div class="content">
            <div class="content-header">
                <div>
                    <h1>Manage Students</h1>
                    <p>View, edit, and manage student accounts</p>
                </div>
            </div>

            <!-- Add New Student Button -->
            <div style="margin-bottom: 1.5rem;">
                <button id="addStudentBtn" class="add-new-btn"><i class="fa-solid fa-plus"></i> Add New Student</button>
            </div>

            <!-- Search Container -->
            <div class="search-container">
                <form method="GET" action="a_Students.php" style="display: flex; width: 100%; gap: 10px;">
                    <input type="text" name="search" placeholder="Search students by name, ID, grade, or strand..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit"><i class="fas fa-search"></i> Search</button>
                    <?php if (!empty($search)): ?>
                        <a href="a_Students.php" class="add-new-btn" style="background-color: #dc3545;"><i class="fas fa-times"></i> Clear</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Add Student Modal -->
            <div id="addStudentModal" class="modal-overlay">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Add New Student</h2>
                        <button class="close-modal">&times;</button>
                    </div>
                    <form method="POST" action="a_Students.php">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="account_number">Account Number</label>
                                <input type="text" id="account_number" name="account_number" placeholder="e.g., S001" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="fname">First Name</label>
                                <input type="text" id="fname" name="fname" required>
                            </div>
                            <div class="form-group">
                                <label for="lname">Last Name</label>
                                <input type="text" id="lname" name="lname" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="password">Password</label>
                                <input type="password" id="password" name="password" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                        <label for="school_id">School ID</label>
                        <input type="text" id="school_id" name="school_id" placeholder="e.g., IT21">
                    </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="glevel">Grade Level</label>
                                <select id="glevel" name="glevel" required>
                                    <option value="">Select Grade</option>
                                    <option value="7">Grade 7</option>
                                    <option value="8">Grade 8</option>
                                    <option value="9">Grade 9</option>
                                    <option value="10">Grade 10</option>
                                    <option value="11">Grade 11</option>
                                    <option value="12">Grade 12</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="strand">Strand</label>
                                <select id="strand" name="strand">
                                    <option value="">Select Strand</option>
                                    <option value="TVL">TVL</option>
                                    <option value="ABM">ABM</option>
                                    <option value="HUMSS">HUMSS</option>
                                    <option value="STEM">STEM</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="section">Section</label>
                            <input type="text" id="section" name="section">
                        </div>
                        
                        <div class="form-actions">
                            <button type="button" class="btn btn-secondary close-modal">Cancel</button>
                            <button type="submit" name="add_student" class="btn btn-primary">Add Student</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- View/Edit Modal -->
            <?php if (isset($_GET['view']) || isset($_GET['edit'])): ?>
                <div class="modal-overlay active" id="viewEditModal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h2><?php echo isset($_GET['edit']) ? 'Edit Student' : 'Student Details'; ?></h2>
                            <button onclick="window.location.href='a_Students.php'" class="close-modal">&times;</button>
                        </div>
                        
                        <?php if ($studentToView): ?>
                            <form method="POST" action="a_Students.php">
                                <input type="hidden" name="account_number" value="<?php echo htmlspecialchars($studentToView['account_number']); ?>">
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>First Name</label>
                                        <input type="text" name="fname" value="<?php echo htmlspecialchars($studentToView['fname']); ?>" <?php echo !isset($_GET['edit']) ? 'readonly' : ''; ?> required>
                                    </div>
                                    <div class="form-group">
                                        <label>Last Name</label>
                                        <input type="text" name="lname" value="<?php echo htmlspecialchars($studentToView['lname']); ?>" <?php echo !isset($_GET['edit']) ? 'readonly' : ''; ?> required>
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Grade Level</label>
                                        <select name="glevel" <?php echo !isset($_GET['edit']) ? 'disabled' : ''; ?> required>
                                            <option value="">Select Grade</option>
                                            <option value="7" <?php echo ($studentToView['glevel'] == '7') ? 'selected' : ''; ?>>Grade 7</option>
                                            <option value="8" <?php echo ($studentToView['glevel'] == '8') ? 'selected' : ''; ?>>Grade 8</option>
                                            <option value="9" <?php echo ($studentToView['glevel'] == '9') ? 'selected' : ''; ?>>Grade 9</option>
                                            <option value="10" <?php echo ($studentToView['glevel'] == '10') ? 'selected' : ''; ?>>Grade 10</option>
                                            <option value="11" <?php echo ($studentToView['glevel'] == '11' || $studentToView['glevel'] == 'G11') ? 'selected' : ''; ?>>Grade 11</option>
                                            <option value="12" <?php echo ($studentToView['glevel'] == '12' || $studentToView['glevel'] == 'G12') ? 'selected' : ''; ?>>Grade 12</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Strand</label>
                                        <select name="strand" <?php echo !isset($_GET['edit']) ? 'disabled' : ''; ?>>
                                            <option value="">Select Strand</option>
                                            <option value="TVL" <?php echo ($studentToView['strand'] == 'TVL') ? 'selected' : ''; ?>>TVL</option>
                                            <option value="ABM" <?php echo ($studentToView['strand'] == 'ABM') ? 'selected' : ''; ?>>ABM</option>
                                            <option value="HUMSS" <?php echo ($studentToView['strand'] == 'HUMSS') ? 'selected' : ''; ?>>HUMSS</option>
                                            <option value="STEM" <?php echo ($studentToView['strand'] == 'STEM') ? 'selected' : ''; ?>>STEM</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label>Section</label>
                                    <input type="text" name="section" value="<?php echo htmlspecialchars($studentToView['section']); ?>" <?php echo !isset($_GET['edit']) ? 'readonly' : ''; ?>>
                                </div>
                                
                                <div class="form-actions">
                                    <?php if (isset($_GET['edit'])): ?>
                                        <button type="submit" name="update" class="btn btn-primary">Save Changes</button>
                                    <?php else: ?>
                                        <a href="a_Students.php?edit=<?php echo htmlspecialchars($studentToView['account_number']); ?>" class="btn btn-primary">Edit</a>
                                    <?php endif; ?>
                                    <a href="a_Students.php" class="btn btn-danger">Close</a>
                                </div>
                            </form>
                        <?php else: ?>
                            <p>Student not found.</p>
                            <div class="form-actions">
                                <a href="a_Students.php" class="btn btn-danger">Close</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Success/Error Messages -->
            <?php if (isset($success_message)): ?>
                <div style="background-color: #d4edda; color: #155724; padding: 1rem; margin-bottom: 1rem; border-radius: 5px;">
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div style="background-color: #f8d7da; color: #721c24; padding: 1rem; margin-bottom: 1rem; border-radius: 5px;">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <!-- Updated table without table-responsive wrapper -->
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student</th>
                        <th>Account Number</th>
                        <th>Grade Level</th>
                        <th>Strand</th>
                        <th>Section</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($studentsResult->num_rows > 0): ?>
                        <?php 
                        $counter = 1;
                        while ($student = $studentsResult->fetch_assoc()): 
                        ?>
                            <tr>
                                <td data-label="#"><?php echo $counter++; ?></td>
                                <td data-label="Student">
                                    <div class="student-name">
                                        <img src="uploads/profiles/<?php echo htmlspecialchars($student['profile_pic']); ?>" 
                                            alt="Profile" 
                                            class="student-avatar" 
                                            onerror="this.src='uploads/profiles/default_profile.png'">
                                        <div>
                                            <strong><?php echo htmlspecialchars($student['fname'] . ' ' . $student['lname']); ?></strong>
                                        </div>
                                    </div>
                                </td>
                                <td data-label="Account Number"><?php echo htmlspecialchars($student['account_number']); ?></td>
                                <td data-label="Grade Level">
                                    <span class="badge badge-<?php echo strtolower($student['glevel']); ?>">
                                        <?php echo htmlspecialchars($student['glevel']); ?>
                                    </span>
                                </td>
                                <td data-label="Strand">
                                    <?php if (!empty($student['strand'])): ?>
                                        <span class="badge badge-<?php echo strtolower($student['strand']); ?>">
                                            <?php echo htmlspecialchars($student['strand']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Section">
                                    <?php if (!empty($student['section'])): ?>
                                        <span class="badge badge-section">
                                            <?php echo htmlspecialchars($student['section']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Actions" class="action-btns">
                                    <div class="action-buttons">
                                        <a href="a_Students.php?view=<?php echo urlencode($student['account_number']); ?>" title="View" class="btn-view"><i class="fas fa-eye"></i></a>
                                        <a href="a_Students.php?edit=<?php echo urlencode($student['account_number']); ?>" title="Edit" class="btn-edit"><i class="fas fa-edit"></i></a>
                                        <a href="a_Students.php?delete=<?php echo urlencode($student['account_number']); ?>" title="Delete" class="btn-delete" onclick="return confirm('Are you sure you want to delete this student?');"><i class="fas fa-trash-alt"></i></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <i class="fas fa-user-graduate"></i>
                                    <h3>No students found</h3>
                                    <?php if (!empty($search)): ?>
                                        <p>No results for "<?php echo htmlspecialchars($search); ?>"</p>
                                        <a href="a_Students.php" class="add-new-btn">View All Students</a>
                                    <?php else: ?>
                                        <p>No students registered yet</p>
                                        <button id="addFirstStudentBtn" class="add-new-btn">Add First Student</button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // JavaScript for sidebar and profile dropdown remains the same
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.querySelector('.sidebar');
            const content = document.querySelector('.content');
            const toggleBtn = document.getElementById('toggleSidebar');

            const isSidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            
            if (isSidebarCollapsed) {
                sidebar.classList.add('collapsed');
                content.classList.add('expanded');
            }

            if (toggleBtn) {
                toggleBtn.addEventListener('click', function() {
                    sidebar.classList.toggle('collapsed');
                    content.classList.toggle('expanded');
                    localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
                });
            }
        });

        function profileDropdown() {
            document.getElementById("dropdown").classList.toggle("show");
        }

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
        
        // New JavaScript for modal functionality
        const addStudentBtn = document.getElementById('addStudentBtn');
        const addFirstStudentBtn = document.getElementById('addFirstStudentBtn');
        const addStudentModal = document.getElementById('addStudentModal');
        const closeModalBtns = document.querySelectorAll('.close-modal');
        
        if (addStudentBtn) {
            addStudentBtn.addEventListener('click', function() {
                addStudentModal.classList.add('active');
            });
        }
        
        if (addFirstStudentBtn) {
            addFirstStudentBtn.addEventListener('click', function() {
                addStudentModal.classList.add('active');
            });
        }
        
        closeModalBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.modal-overlay').forEach(modal => {
                    modal.classList.remove('active');
                });
            });
        });
        
        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal-overlay')) {
                event.target.classList.remove('active');
            }
        });
    </script>
</body>
</html>
<?php
$studentsQuery->close();
$studentCountQuery->close();
$conn->close();
?>