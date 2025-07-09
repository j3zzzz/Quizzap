<?php
session_start();
if (!isset($_SESSION['account_number']) || strpos($_SESSION['account_number'], 'A') !== 0) {
    header("Location: login.php");
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

if (isset($_POST['add_teacher'])) {
    $account_number = $_POST['account_number'];
    $fname = $_POST['fname'];
    $lname = $_POST['lname'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $school_id = $_POST['school_id'] ?? '';
    
    // Check if account number already exists
    $checkSql = "SELECT * FROM teachers WHERE account_number = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("s", $account_number);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        $error_message = "Error: A teacher with this account number already exists.";
    } else {
        $sql = "INSERT INTO teachers (account_number, fname, lname, password, school_id) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssss", $account_number, $fname, $lname, $password, $school_id);
        
        if ($stmt->execute()) {
            $success_message = "Teacher added successfully!";
            // Redirect to avoid form resubmission
            header("Location: a_Teachers.php");
            exit();
        } else {
            $error_message = "Error adding teacher: " . $stmt->error;
        }
        $stmt->close();
    }
    $checkStmt->close();
}

// Handle Delete Action
if (isset($_GET['delete'])) {
    $account_number = $_GET['delete'];
    $sql = "DELETE FROM teachers WHERE account_number = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $account_number);
    if ($stmt->execute()) {
        $success_message = "Teacher deleted successfully!";
    } else {
        $error_message = "Error deleting teacher: " . $stmt->error;
    }
    $stmt->close();
}

// Handle Edit Form Submission
if (isset($_POST['update'])) {
    $account_number = $_POST['account_number'];
    $fname = $_POST['fname'];
    $lname = $_POST['lname'];
    $school_id = $_POST['school_id'];
    
    $sql = "UPDATE teachers SET fname=?, lname=?, school_id=? WHERE account_number=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $fname, $lname, $school_id, $account_number);
    
    if ($stmt->execute()) {
        $success_message = "Teacher updated successfully!";
        // Redirect to avoid form resubmission
        header("Location: a_Teachers.php");
        exit();
    } else {
        $error_message = "Error updating teacher: " . $stmt->error;
    }
    $stmt->close();
}

// Search functionality
$search = '';
$whereClause = '';
if (isset($_GET['search'])) {
    $search = $_GET['search'];
    $whereClause = "WHERE fname LIKE '%$search%' OR lname LIKE '%$search%' OR account_number LIKE '%$search%' OR school_id LIKE '%$search%'";
}

// Fetch all teachers
$teachersQuery = $conn->prepare("SELECT * FROM teachers $whereClause ORDER BY teacher_id DESC");
$teachersQuery->execute();
$teachersResult = $teachersQuery->get_result();

// Count total teachers
$teacherCountQuery = $conn->prepare("SELECT COUNT(*) as count FROM teachers");
$teacherCountQuery->execute();
$teacherCountResult = $teacherCountQuery->get_result();
$teacherCount = $teacherCountResult->fetch_assoc()['count'];

// Fetch single teacher for view/edit
$teacherToView = null;
if (isset($_GET['view']) || isset($_GET['edit'])) {
    $account_number = $_GET['view'] ?? $_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM teachers WHERE account_number = ?");
    $stmt->bind_param("s", $account_number);
    $stmt->execute();
    $result = $stmt->get_result();
    $teacherToView = $result->fetch_assoc();
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
    <title>Manage Teachers</title>
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
            margin-bottom: 1.5rem;
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
            display: flex;
            align-items: center;
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
            display: flex;
            align-items: center;
            gap: 5px;
        }

        h3 {
            font-family: 'Fredoka';
            font-weight: bold;
            font-size: 1.5rem;
            margin: 0;
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
            content: " ";
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

        .modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.4);
}

.modal-content {
    background-color: #fefefe;
    margin: 10% auto;
    padding: 20px;
    border: 1px solid #888;
    width: 80%;
    max-width: 600px;
    border-radius: 10px;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.close {
    color: #aaa;
    float: right;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.close:hover {
    color: black;
}

.modal-header {
    padding: 10px 0;
    border-bottom: 1px solid #eee;
    margin-bottom: 20px;
}

.modal-body {
    padding: 10px 0;
}

.modal-footer {
    padding: 10px 0;
    border-top: 1px solid #eee;
    margin-top: 20px;
    text-align: right;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
}

.form-group input, .form-group select {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-family: 'Fredoka';
}

.btn {
    padding: 8px 15px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-family: 'Fredoka';
    margin-right: 10px;
}

.btn-primary {
    background-color: #f8b500;
    color: white;
}

.btn-secondary {
    background-color: #6c757d;
    color: white;
}

.btn-danger {
    background-color: #dc3545;
    color: white;
}

.btn:hover {
    opacity: 0.9;
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
            
            .dropdown-content {
                width: 180px;
                right: 5px;
            }
            
            .dropdown-content button {
                font-size: 14px;
                padding: 8px 10px !important;
            }
        }   

        /* New improved table styles */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            font-size: 0.9rem;
        }

        .data-table th, .data-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
        }

        .data-table th {
            background-color: #f8b500;
            color: white;
            font-weight: bold;
            position: sticky;
            top: 0;
        }

        .data-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .data-table tr:hover {
            background-color: #f5f5f5;
        }

        .teacher-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #f8b500;
            margin-right: 10px;
        }

        .teacher-name {
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

        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .status-badge.active {
            background-color: #d4edda;
            color: #155724;
        }

        .status-badge.inactive {
            background-color: #f8d7da;
            color: #721c24;
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

        .table-responsive {
            overflow-x: auto;
            margin-bottom: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
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

        .teacher-name {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .teacher-name div {
            display: flex;
            flex-direction: column;
        }

        .teacher-email {
            font-size: 0.8rem;
            color: #666;
            margin-top: 2px;
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
            
            .teacher-name {
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
        
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 10% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 600px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: black;
        }

        .modal-header {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
            margin-bottom: 20px;
        }

        .modal-body {
            padding: 10px 0;
        }

        .modal-footer {
            padding: 10px 0;
            border-top: 1px solid #eee;
            margin-top: 20px;
            text-align: right;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }

        .form-group input, .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: 'Fredoka';
        }

        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-family: 'Fredoka';
            margin-right: 10px;
        }

        .btn-primary {
            background-color: #f8b500;
            color: white;
        }

        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }

        .btn-danger {
            background-color: #dc3545;
            color: white;
        }

        .btn:hover {
            opacity: 0.9;
        }

        .alert {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
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
                <a href="a_Home.php" title="Dashboard">
                    <i class="fa-solid fa-house"></i>
                    <span>Dashboard</span>
                </a>
                <a href="a_Students.php" title="Students">
                    <i class="fa-solid fa-graduation-cap"></i>
                    <span>Students</span>
                </a>
                <a href="a_Teachers.php" class="active" title="Teachers">
                    <i class="fa-solid fa-chalkboard-user"></i>
                    <span>Teachers</span>
                </a>
                <a href="a_Settings.php" title="Settings">
                    <i class="fa-solid fa-gear"></i>
                    <span>Settings</span>
                </a>
            </div>
        </div>

        <!-- Content Area -->
        <div class="content">
            <div class="content-header">
                <div>
                    <h1>Manage Teachers</h1>
                    <p>View, edit, and manage teacher accounts</p>
                </div>
                <div class="actions">
                    <div class="profile" onclick="profileDropdown()">
                        <img src="uploads/profiles/<?php echo htmlspecialchars($profile_pic); ?>" alt="Profile Picture" class="profile-pic" onerror="this.src='uploads/profiles/default-profile.jpg'" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
                        <div id="dropdown" class="dropdown-content">
                            <button onclick="window.location.href='a_Profile.php'"><i class="fa-solid fa-user"></i> Profile</button> 
                            <form action="logout.php" method="POST">
                                <button><i class="fa-solid fa-right-from-bracket"></i> Logout</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Add New Teacher Button -->
            <div style="margin-bottom: 1.5rem;">
                <button onclick="openAddTeacherModal()" class="add-new-btn"><i class="fa-solid fa-plus"></i> Add New Teacher</button>
            </div>

            <div id="addTeacherModal" class="modal" style="display: none;">
    <div class="modal-content">
        <span class="close" onclick="closeAddTeacherModal()">&times;</span>
        <div class="modal-header">
            <h2>Add New Teacher</h2>
        </div>
        <div class="modal-body">
            <form id="addTeacherForm" method="POST" action="a_Teachers.php">
                <div class="form-group">
                    <label for="add_account_number">Account Number</label>
                    <input type="text" id="add_account_number" name="account_number" placeholder="e.g., T001" required>
                </div>
                <div class="form-group">
                    <label for="add_fname">First Name</label>
                    <input type="text" id="add_fname" name="fname" required>
                </div>
                <div class="form-group">
                    <label for="add_lname">Last Name</label>
                    <input type="text" id="add_lname" name="lname" required>
                </div>
                <div class="form-group">
                    <label for="add_password">Password</label>
                    <input type="password" id="add_password" name="password" required>
                </div>
                <div class="form-group">
                    <label for="add_school_id">School ID</label>
                    <input type="text" id="add_school_id" name="school_id">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeAddTeacherModal()">Cancel</button>
                    <button type="submit" name="add_teacher" class="btn btn-primary">Add Teacher</button>
                </div>
            </form>
        </div>
    </div>
</div>

            <!-- Search Container -->
            <div class="search-container">
                <form method="GET" action="a_Teachers.php" style="display: flex; width: 100%; gap: 10px;">
                    <input type="text" name="search" placeholder="Search teachers by name, ID, or school ID..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit"><i class="fas fa-search"></i> Search</button>
                    <?php if (!empty($search)): ?>
                        <a href="a_Teachers.php" class="add-new-btn" style="background-color: #dc3545;"><i class="fas fa-times"></i> Clear</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- View/Edit Modal -->
            <?php if (isset($_GET['view']) || isset($_GET['edit'])): ?>
                <div class="modal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: flex; justify-content: center; align-items: center; z-index: 1000;">
                    <div style="background: white; padding: 2rem; border-radius: 10px; width: 80%; max-width: 600px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                            <h2><?php echo isset($_GET['edit']) ? 'Edit Teacher' : 'Teacher Details'; ?></h2>
                            <button onclick="window.location.href='a_Teachers.php'" style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
                        </div>
                        
                        <?php if ($teacherToView): ?>
                            <form method="POST" action="a_Teachers.php">
                                <input type="hidden" name="account_number" value="<?php echo htmlspecialchars($teacherToView['account_number']); ?>">
                                
                                <div style="display: flex; gap: 1rem; margin-bottom: 1rem;">
                                    <div style="flex: 1;">
                                        <label style="display: block; margin-bottom: 0.5rem;">First Name</label>
                                        <input type="text" name="fname" value="<?php echo htmlspecialchars($teacherToView['fname']); ?>" <?php echo !isset($_GET['edit']) ? 'readonly' : ''; ?> style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 5px;" required>
                                    </div>
                                    <div style="flex: 1;">
                                        <label style="display: block; margin-bottom: 0.5rem;">Last Name</label>
                                        <input type="text" name="lname" value="<?php echo htmlspecialchars($teacherToView['lname']); ?>" <?php echo !isset($_GET['edit']) ? 'readonly' : ''; ?> style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 5px;" required>
                                    </div>
                                </div>
                                
                                <div style="margin-bottom: 1rem;">
                                    <label style="display: block; margin-bottom: 0.5rem;">School ID</label>
                                    <input type="text" name="school_id" value="<?php echo htmlspecialchars($teacherToView['school_id']); ?>" <?php echo !isset($_GET['edit']) ? 'readonly' : ''; ?> style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 5px;">
                                </div>
                                
                                <div style="display: flex; justify-content: flex-end; gap: 1rem;">
                                    <?php if (isset($_GET['edit'])): ?>
                                        <button type="submit" name="update" style="background-color: #f8b500; color: white; border: none; padding: 0.5rem 1rem; border-radius: 5px; cursor: pointer;">Save Changes</button>
                                    <?php else: ?>
                                        <a href="a_Teachers.php?edit=<?php echo htmlspecialchars($teacherToView['account_number']); ?>" style="background-color: #f8b500; color: white; border: none; padding: 0.5rem 1rem; border-radius: 5px; text-decoration: none; text-align: center;">Edit</a>
                                    <?php endif; ?>
                                    <a href="a_Teachers.php" style="background-color: #dc3545; color: white; border: none; padding: 0.5rem 1rem; border-radius: 5px; text-decoration: none; text-align: center;">Close</a>
                                </div>
                            </form>
                        <?php else: ?>
                            <p>Teacher not found.</p>
                            <a href="a_Teachers.php" style="background-color: #dc3545; color: white; border: none; padding: 0.5rem 1rem; border-radius: 5px; text-decoration: none; text-align: center; display: inline-block; margin-top: 1rem;">Close</a>
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

            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Teacher</th>
                            <th>Account Number</th>
                            <th>School ID</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($teachersResult->num_rows > 0): ?>
                            <?php 
                            $counter = 1;
                            while ($teacher = $teachersResult->fetch_assoc()): 
                            ?>
                                <tr>
                                    <td data-label="#"><?php echo $counter++; ?></td>
                                    <td data-label="Teacher">
                                        <div class="teacher-name">
                                            <img src="uploads/<?php echo htmlspecialchars($teacher['profile_pic']); ?>" alt="Profile" class="teacher-avatar" onerror="this.src='uploads/default_profile.png'">
                                            <div>
                                                <strong><?php echo htmlspecialchars($teacher['fname'] . ' ' . $teacher['lname']); ?></strong>
                                            </div>
                                        </div>
                                    </td>
                                    <td data-label="Account Number"><?php echo htmlspecialchars($teacher['account_number']); ?></td>
                                    <td data-label="School ID">
                                        <?php if (!empty($teacher['school_id'])): ?>
                                            <span class="badge badge-section">
                                                <?php echo htmlspecialchars($teacher['school_id']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Status">
                                        <span class="status-badge active">
                                            Active
                                        </span>
                                    </td>
                                    <td data-label="Actions" class="action-btns">
                                        <div class="action-buttons">
                                            <a href="a_Teachers.php?view=<?php echo urlencode($teacher['account_number']); ?>" title="View" class="btn-view"><i class="fas fa-eye"></i></a>
                                            <a href="a_Teachers.php?edit=<?php echo urlencode($teacher['account_number']); ?>" title="Edit" class="btn-edit"><i class="fas fa-edit"></i></a>
                                            <a href="a_Teachers.php?delete=<?php echo urlencode($teacher['account_number']); ?>" title="Delete" class="btn-delete" onclick="return confirm('Are you sure you want to delete this teacher?');"><i class="fas fa-trash-alt"></i></a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6">
                                    <div class="empty-state">
                                        <i class="fas fa-chalkboard-teacher"></i>
                                        <h3>No teachers found</h3>
                                        <?php if (!empty($search)): ?>
                                            <p>No results for "<?php echo htmlspecialchars($search); ?>"</p>
                                            <a href="a_Teachers.php" class="add-new-btn">View All Teachers</a>
                                        <?php else: ?>
                                            <p>No teachers registered yet</p>
                                            <a href="a_addTeacher.php" class="add-new-btn">Add First Teacher</a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
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

        function openAddTeacherModal() {
            document.getElementById('addTeacherModal').style.display = 'block';
        }

        function closeAddTeacherModal() {
            document.getElementById('addTeacherModal').style.display = 'none';
        }

        // Close modal when clicking outside of it
        window.onclick = function(event) {
            var modal = document.getElementById('addTeacherModal');
            if (event.target == modal) {
                closeAddTeacherModal();
            }
            
            // Keep the existing profile dropdown functionality
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
    </script>
</body>
</html>
<?php
$teachersQuery->close();
$teacherCountQuery->close();
$conn->close();
?>