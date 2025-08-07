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

$loggedInUser = $_SESSION['account_number'];

// Fetch admin's profile pic
$sql = "SELECT profile_pic FROM admins WHERE account_number = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $loggedInUser);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $profile_pic = $row['profile_pic'] ? $row['profile_pic'] : 'default-profile.png';
} else {
    $profile_pic = 'default-profile.png';
}

$stmt->close();

// Function to generate unique subject code
function generateUniqueSubjectCode($conn) {
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $digits = '0123456789';
    do {
        $code = $characters[rand(0, 25)] . $characters[rand(0, 25)] . $digits[rand(0, 9)] . $digits[rand(0, 9)];
        $sql = "SELECT * FROM subjects WHERE subject_code = '$code'";
        $result = $conn->query($sql);
    } while ($result->num_rows > 0);
    return $code;
}

// Handle Add Class Action
if (isset($_POST['add_class'])) {
    $subject_name = $_POST['subject_name'];
    $grade_level = $_POST['grade_level'];
    $section = $_POST['section'];
    $teacher_account_number = $_POST['teacher_id'];
    $subject_code = generateUniqueSubjectCode($conn);
    
    // Get teacher's school_id
    $teacher_sql = "SELECT school_id FROM teachers WHERE account_number = ?";
    $teacher_stmt = $conn->prepare($teacher_sql);
    $teacher_stmt->bind_param("s", $teacher_account_number);
    $teacher_stmt->execute();
    $teacher_result = $teacher_stmt->get_result();
    
    if ($teacher_result->num_rows > 0) {
        $teacher_row = $teacher_result->fetch_assoc();
        $school_id = $teacher_row['school_id'];
        
        // Check if class already exists for this teacher (same subject, grade, section)
        $check_sql = "SELECT * FROM subjects WHERE subject_name = ? AND grade_level = ? AND section = ? AND teacher_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ssss", $subject_name, $grade_level, $section, $teacher_account_number);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            // Store form data in session
            $_SESSION['pending_class'] = [
                'subject_name' => $subject_name,
                'grade_level' => $grade_level,
                'section' => $section,
                'teacher_id' => $teacher_account_number,
                'subject_code' => $subject_code,
                'school_id' => $school_id
            ];
            
            $error_message = "Error: This teacher already has an existing class. A teacher can not have duplicate classes.";
            header("Location: a_Classes.php?duplicate_error=1&message=" . urlencode($error_message));
            exit();
        }
        
        // Check if class exists for any teacher (same grade and section)
        $check_all_sql = "SELECT * FROM subjects WHERE grade_level = ? AND section = ?";
        $check_all_stmt = $conn->prepare($check_all_sql);
        $check_all_stmt->bind_param("ss", $grade_level, $section);
        $check_all_stmt->execute();
        $check_all_result = $check_all_stmt->get_result();
        
        if ($check_all_result->num_rows > 0) {
            // Store form data in session
            $_SESSION['pending_class'] = [
                'subject_name' => $subject_name,
                'grade_level' => $grade_level,
                'section' => $section,
                'teacher_id' => $teacher_account_number,
                'subject_code' => $subject_code,
                'school_id' => $school_id
            ];
            
            $warning_message = "Warning: A class with the same grade level and section already exists for another teacher. Are you sure you want to create another class with the same grade and section?";
            header("Location: a_Classes.php?duplicate_warning=1&message=" . urlencode($warning_message));
            exit();
        }
        
        // No duplicate found, proceed with creation
        $sql = "INSERT INTO subjects (subject_name, teacher_id, subject_code, grade_level, section, school_id, status) VALUES (?, ?, ?, ?, ?, ?, 'Active')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssss", $subject_name, $teacher_account_number, $subject_code, $grade_level, $section, $school_id);
        
        if ($stmt->execute()) {
            $success_message = "Class created successfully!";
            header("Location: a_Classes.php");
            exit();
        } else {
            $error_message = "Error adding class: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $error_message = "Error: Selected teacher not found.";
    }
    $teacher_stmt->close();
}

// Handle duplicate confirmation
if (isset($_GET['confirm_duplicate']) && $_GET['confirm_duplicate'] == 1 && isset($_SESSION['pending_class'])) {
    $pending = $_SESSION['pending_class'];
    
    $sql = "INSERT INTO subjects (subject_name, teacher_id, subject_code, grade_level, section, school_id, status) VALUES (?, ?, ?, ?, ?, ?, 'Active')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssss", $pending['subject_name'], $pending['teacher_id'], $pending['subject_code'], $pending['grade_level'], $pending['section'], $pending['school_id']);
    
    if ($stmt->execute()) {
        $success_message = "Class created successfully!";
        unset($_SESSION['pending_class']);
    } else {
        $error_message = "Error adding class: " . $stmt->error;
    }
    $stmt->close();
    
    // Redirect to avoid form resubmission
    header("Location: a_Classes.php");
    exit();
}

// Handle Delete Action
if (isset($_GET['delete'])) {
    $subject_id = $_GET['delete'];
    
    // First delete all quizzes related to this subject
    $delete_quizzes_sql = "DELETE FROM quizzes WHERE subject_id = ?";
    $stmt = $conn->prepare($delete_quizzes_sql);
    $stmt->bind_param("i", $subject_id);
    $stmt->execute();
    $stmt->close();
    
    // Then delete the subject
    $sql = "DELETE FROM subjects WHERE subject_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $subject_id);
    
    if ($stmt->execute()) {
        $success_message = "Class deleted successfully!";
    } else {
        $error_message = "Error deleting class: " . $stmt->error;
    }
    $stmt->close();
}

// Handle Deactivate Action (unenroll all students)
if (isset($_GET['deactivate'])) {
    $subject_id = $_GET['deactivate'];
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Delete all student enrollments for this subject
        $deleteEnrollmentsSql = "DELETE FROM enrollments WHERE subject_id = ?";
        $deleteStmt = $conn->prepare($deleteEnrollmentsSql);
        $deleteStmt->bind_param("i", $subject_id);
        $deleteStmt->execute();
        $deleteStmt->close();
        
        // Update the class status to 'Deactivated'
        $updateSql = "UPDATE subjects SET status = 'Deactivated' WHERE subject_id = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("i", $subject_id);
        $updateStmt->execute();
        $updateStmt->close();
        
        $conn->commit();
        $success_message = "Class deactivated successfully! All students have been unenrolled.";
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Error deactivating class: " . $e->getMessage();
    }
}

// Handle Activate Action
if (isset($_GET['activate'])) {
    $subject_id = $_GET['activate'];
    
    $sql = "UPDATE subjects SET status = 'Active' WHERE subject_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $subject_id);
    
    if ($stmt->execute()) {
        $success_message = "Class activated successfully!";
        // Redirect to avoid form resubmission
        header("Location: a_Classes.php");
        exit();
    } else {
        $error_message = "Error activating class: " . $stmt->error;
    }
    $stmt->close();
}

// Handle Edit Form Submission
if (isset($_POST['update'])) {
    $subject_id = $_POST['subject_id'];
    $subject_name = $_POST['subject_name'];
    $grade_level = $_POST['grade_level'];
    $section = $_POST['section'];
    $teacher_account_number = $_POST['teacher_id'];
    
    $sql = "UPDATE subjects SET subject_name=?, grade_level=?, section=?, teacher_id=? WHERE subject_id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssi", $subject_name, $grade_level, $section, $teacher_account_number, $subject_id);
    
    if ($stmt->execute()) {
        $success_message = "Class updated successfully!";
        // Redirect to avoid form resubmission
        header("Location: a_Classes.php");
        exit();
    } else {
        $error_message = "Error updating class: " . $stmt->error;
    }
    $stmt->close();
}

// Search functionality
$search = '';
$whereClause = '';
if (isset($_GET['search'])) {
    $search = $_GET['search'];
    $whereClause = "WHERE (s.subject_name LIKE '%$search%' OR s.section LIKE '%$search%' 
                   OR s.subject_code LIKE '%$search%' OR s.status LIKE '%$search%'
                   OR CONCAT(t.fname, ' ', t.lname) LIKE '%$search%' OR s.grade_level LIKE '%$search%')";
}

// Fetch all classes with teacher information
$classesQuery = $conn->prepare("
    SELECT s.*, t.fname as teacher_fname, t.lname as teacher_lname, t.account_number as teacher_account 
    FROM subjects s 
    LEFT JOIN teachers t ON s.teacher_id = t.account_number 
    $whereClause 
    ORDER BY s.subject_id DESC
");
$classesQuery->execute();
$classesResult = $classesQuery->get_result();

// Count total classes
$classCountQuery = $conn->prepare("SELECT COUNT(*) as count FROM subjects");
$classCountQuery->execute();
$classCountResult = $classCountQuery->get_result();
$classCount = $classCountResult->fetch_assoc()['count'];

// Fetch single class for view/edit
$classToView = null;
if (isset($_GET['view']) || isset($_GET['edit'])) {
    $subject_id = $_GET['view'] ?? $_GET['edit'];
    $stmt = $conn->prepare("
        SELECT s.*, t.fname as teacher_fname, t.lname as teacher_lname 
        FROM subjects s 
        LEFT JOIN teachers t ON s.teacher_id = t.account_number 
        WHERE s.subject_id = ?
    ");
    $stmt->bind_param("i", $subject_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $classToView = $result->fetch_assoc();
    $stmt->close();
}

// Fetch all teachers for dropdown
$teachersQuery = $conn->query("SELECT account_number, fname, lname FROM teachers ORDER BY lname, fname");
$teachers = [];
while ($row = $teachersQuery->fetch_assoc()) {
    $teachers[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="other resources/fontawesome-free-6.5.2-web/css/all.min.css">
    <title>Manage Classes</title>
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

        .btn-success {
            background-color: #F8B500;
            text-decoration: none;
            cursor: pointer;
            color: white;
        }

        .btn-primary {
            background-color: #f8b500;
            color: white;
        }

        .btn-secondary {
            background-color: #6c757d;
            color: white;
            text-decoration: none;
        }

        .btn-danger {
            background-color: #dc3545;
            color: white;
            text-decoration: none;
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
            background-color: #fff3cd;
            color: #856404;
        }

        .status-badge.deactivated {
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

        .btn-deactivate {
            color: #6c757d;
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

        .btn-deactivate:hover {
            background-color: #6c757d !important;
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

        /* Add to your existing CSS */
        #duplicateModal .modal-content {
            max-width: 500px;
            margin: 10% auto;
        }

        #duplicateModal .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        #duplicateModal p {
            margin-bottom: 20px;
            font-size: 1.1rem;
            line-height: 1.5;
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
                <a href="a_Teachers.php" title="Teachers">
                    <i class="fa-solid fa-chalkboard-user"></i>
                    <span>Teachers</span>
                </a>
                <a href="a_Classes.php" class="active" title="Classes">
                    <i class="fa-solid fa-list"></i>
                    <span>Classes</span>
                </a>
            </div>
        </div>

        <!-- Content Area -->
        <div class="content">
            <div class="content-header">
                <div>
                    <h1>Manage Classes</h1>
                    <p>View, edit, and manage all classes</p>
                </div>
                <div class="actions">
                    <div class="profile" onclick="profileDropdown()">
                        <img src="uploads/profiles/<?php echo htmlspecialchars($profile_pic); ?>" alt="Profile Picture" class="profile-pic" onerror="this.src='uploads/profiles/default-profile.png'" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
                        <div id="dropdown" class="dropdown-content">
                            <button onclick="window.location.href='a_Profile.php'"><i class="fa-solid fa-user"></i> Profile</button> 
                            <form action="logout.php" method="POST">
                                <button><i class="fa-solid fa-right-from-bracket"></i> Logout</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Add New Class -->
            <div style="margin-bottom: 1.5rem;">
                <button onclick="openAddClassModal()" class="add-new-btn"><i class="fa-solid fa-plus"></i> Add New Class</button>
            </div>

            <!-- Add Class Modal -->
            <div id="addClassModal" class="modal">
                <div class="modal-content">
                    <span class="close" onclick="closeAddClassModal()">&times;</span>
                    <div class="modal-header">
                        <h2>Add New Class</h2>
                    </div>
                    <div class="modal-body">
                        <form method="POST" action="a_Classes.php">
                            <div class="form-group">
                                <label for="subject_name">Subject Name</label>
                                <input type="text" id="subject_name" name="subject_name" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="teacher_id">Teacher</label>
                                <select id="teacher_id" name="teacher_id" required>
                                    <option value="">Select Teacher</option>
                                    <?php foreach ($teachers as $teacher): ?>
                                        <option value="<?php echo htmlspecialchars($teacher['account_number']); ?>">
                                            <?php echo htmlspecialchars($teacher['fname'] . ' ' . $teacher['lname'] . ' (' . $teacher['account_number'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="grade_level">Grade Level</label>
                                <select id="grade_level" name="grade_level" required>
                                    <option value="">Select Grade Level</option>
                                    <option value="7">Grade 7</option>
                                    <option value="8">Grade 8</option>
                                    <option value="9">Grade 9</option>
                                    <option value="10">Grade 10</option>
                                    <option value="11">Grade 11</option>
                                    <option value="12">Grade 12</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="section">Section</label>
                                <input type="text" id="section" name="section" required>
                            </div>
                            
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" onclick="closeAddClassModal()">Cancel</button>
                                <button type="submit" name="add_class" class="btn btn-primary">Add Class</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Search Container -->
            <div class="search-container">
                <form method="GET" action="a_Classes.php" style="display: flex; width: 100%; gap: 10px;">
                    <input type="text" name="search" placeholder="Search classes by name, subject code, or school ID..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit"><i class="fas fa-search"></i> Search</button>
                    <?php if (!empty($search)): ?>
                        <a href="a_Teachers.php" class="add-new-btn" style="background-color: #dc3545;"><i class="fas fa-times"></i> Clear</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- View/Edit Modal -->
            <?php if (isset($_GET['view']) || isset($_GET['edit'])): ?>
                <div class="modal" style="display: block;">
                    <div class="modal-content">
                        <span class="close" onclick="window.location.href='a_Classes.php'">&times;</span>
                        <div class="modal-header">
                            <h2><?php echo isset($_GET['edit']) ? 'Edit Class' : 'Class Details'; ?></h2>
                        </div>
                        <div class="modal-body">
                            <?php if ($classToView): ?>
                                <form method="POST" action="a_Classes.php">
                                    <input type="hidden" name="subject_id" value="<?php echo htmlspecialchars($classToView['subject_id']); ?>">
                                    
                                    <div class="form-group">
                                        <label for="subject_name">Subject Name</label>
                                        <input type="text" id="subject_name" name="subject_name" 
                                            value="<?php echo htmlspecialchars($classToView['subject_name']); ?>" 
                                            <?php echo !isset($_GET['edit']) ? 'readonly' : ''; ?> required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="subject_code">Subject Code</label>
                                        <input type="text" value="<?php echo htmlspecialchars($classToView['subject_code']); ?>" readonly>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="teacher_id">Teacher</label>
                                        <?php if (isset($_GET['edit'])): ?>
                                            <select id="teacher_id" name="teacher_id" required>
                                                <?php 
                                                $teachersQuery->data_seek(0); // Reset pointer to beginning
                                                while ($teacher = $teachersQuery->fetch_assoc()): 
                                                ?>
                                                    <option value="<?php echo htmlspecialchars($teacher['account_number']); ?>"
                                                        <?php echo $teacher['account_number'] == $classToView['teacher_id'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($teacher['fname'] . ' ' . $teacher['lname'] . ' (' . $teacher['account_number'] . ')'); ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                        <?php else: ?>
                                            <input type="text" value="<?php echo htmlspecialchars($classToView['teacher_fname'] . ' ' . $classToView['teacher_lname'] . ' (' . $classToView['teacher_id'] . ')'); ?>" readonly>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="grade_level">Grade Level</label>
                                        <?php if (isset($_GET['edit'])): ?>
                                            <select id="grade_level" name="grade_level" required>
                                                <option value="7" <?php echo $classToView['grade_level'] == '7' ? 'selected' : ''; ?>>Grade 7</option>
                                                <option value="8" <?php echo $classToView['grade_level'] == '8' ? 'selected' : ''; ?>>Grade 8</option>
                                                <option value="9" <?php echo $classToView['grade_level'] == '9' ? 'selected' : ''; ?>>Grade 9</option>
                                                <option value="10" <?php echo $classToView['grade_level'] == '10' ? 'selected' : ''; ?>>Grade 10</option>
                                                <option value="11" <?php echo $classToView['grade_level'] == '11' ? 'selected' : ''; ?>>Grade 11</option>
                                                <option value="12" <?php echo $classToView['grade_level'] == '12' ? 'selected' : ''; ?>>Grade 12</option>
                                            </select>
                                        <?php else: ?>
                                            <input type="text" value="Grade <?php echo htmlspecialchars($classToView['grade_level']); ?>" readonly>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="section">Section</label>
                                        <input type="text" id="section" name="section" 
                                            value="<?php echo htmlspecialchars($classToView['section']); ?>" 
                                            <?php echo !isset($_GET['edit']) ? 'readonly' : ''; ?> required>
                                    </div>
                                    
                                    <div class="modal-footer">
                                        <?php if (isset($_GET['edit'])): ?>
                                            <button type="submit" name="update" class="btn btn-primary">Save Changes</button>
                                        <?php else: ?>
                                            <?php if ($classToView['status'] == 'Deactivated'): ?>
                                                <a href="a_Classes.php?activate=<?php echo htmlspecialchars($classToView['subject_id']); ?>" 
                                                class="btn btn-success" 
                                                onclick="return confirm('Are you sure you want to activate this class?');">
                                                <i class="fas fa-check-circle"></i> Activate
                                                </a>
                                            <?php else: ?>
                                                <a href="a_Classes.php?deactivate=<?php echo htmlspecialchars($classToView['subject_id']); ?>" 
                                                class="btn btn-danger" 
                                                onclick="return confirm('Are you sure you want to deactivate this class? All enrolled students will be unenrolled.');">
                                                <i class="fas fa-times-circle"></i> Deactivate
                                                </a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <a href="a_Classes.php" class="btn btn-secondary">Close</a>
                                    </div>
                                </form>
                            <?php else: ?>
                                <p>Class not found.</p>
                                <div class="modal-footer">
                                    <a href="a_Classes.php" class="btn btn-secondary">Close</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Success/Error Messages -->
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Subject</th>
                            <th>Subject Code</th>
                            <th>Grade & Section</th>
                            <th>Teacher</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($classesResult->num_rows > 0): ?>
                            <?php 
                            $counter = 1;
                            while ($class = $classesResult->fetch_assoc()): 
                                // Check if class has students enrolled
                                $enrollmentCheck = $conn->prepare("SELECT COUNT(*) as count FROM subjects WHERE subject_id = ?");
                                $enrollmentCheck->bind_param("i", $class['subject_id']);
                                $enrollmentCheck->execute();
                                $enrollmentResult = $enrollmentCheck->get_result();
                                $enrollmentCount = $enrollmentResult->fetch_assoc()['count'];
                                $enrollmentCheck->close();
                            ?>
                                <tr>
                                    <td data-label="#"><?php echo $counter++; ?></td>
                                    <td data-label="Subject"><?php echo htmlspecialchars($class['subject_name']); ?></td>
                                    <td data-label="Subject Code"><?php echo htmlspecialchars($class['subject_code']); ?></td>
                                    <td data-label="Grade & Section">
                                        Grade <?php echo htmlspecialchars($class['grade_level']); ?> - <?php echo htmlspecialchars($class['section']); ?>
                                    </td>
                                    <td data-label="Teacher">
                                        <div class="teacher-name">
                                            <?php echo htmlspecialchars($class['teacher_fname'] . ' ' . $class['teacher_lname']); ?>
                                            <div class="teacher-email">
                                                <?php echo htmlspecialchars($class['teacher_account']); ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td data-label="Status">
                                        <span class="status-badge <?php 
                                            switch($class['status']) {
                                                case 'Active': echo 'active'; break;
                                                case 'Inactive': echo 'inactive'; break;
                                                case 'Deactivated': echo 'deactivated'; break;
                                                default: echo 'active';
                                            }
                                        ?>">
                                            <?php echo htmlspecialchars($class['status']); ?>
                                        </span>
                                    </td>
                                    <td data-label="Actions" class="action-btns">
                                        <div class="action-buttons">
                                            <a href="a_Classes.php?view=<?php echo urlencode($class['subject_id']); ?>" title="View" class="btn-view"><i class="fas fa-eye"></i></a>
                                            <a href="a_Classes.php?edit=<?php echo urlencode($class['subject_id']); ?>" title="Edit" class="btn-edit"><i class="fas fa-edit"></i></a>
                                            <a href="a_Classes.php?delete=<?php echo urlencode($class['subject_id']); ?>" title="Delete" class="btn-delete" onclick="return confirm('Are you sure you want to delete this class? All quizzes and student data will be permanently removed.');"><i class="fas fa-trash-alt"></i></a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">
                                        <i class="fas fa-chalkboard"></i>
                                        <h3>No classes found</h3>
                                        <?php if (!empty($search)): ?>
                                            <p>No results for "<?php echo htmlspecialchars($search); ?>"</p>
                                            <a href="a_Classes.php" class="add-new-btn">View All Classes</a>
                                        <?php else: ?>
                                            <p>No classes created yet</p>
                                            <button onclick="openAddClassModal()" class="add-new-btn">Add First Class</button>
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

        // Add to your existing JavaScript
        function showDuplicateModal(message, isError = false) {
            const modal = document.createElement('div');
            modal.id = 'duplicateModal';
            modal.className = 'modal';
            modal.style.display = 'block';
            
            modal.innerHTML = `
                <div class="modal-content" style="max-width: 500px;">
                    <span class="close" onclick="document.getElementById('duplicateModal').remove()">&times;</span>
                    <div class="modal-header">
                        <h2>${isError ? 'Error' : 'Warning'}</h2>
                    </div>
                    <div class="modal-body">
                        <p>${message}</p>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" onclick="document.getElementById('duplicateModal').remove()">OK</button>
                        ${!isError ? `<a href="a_Classes.php?confirm_duplicate=1" class="btn btn-primary">Continue Anyway</a>` : ''}
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
        }

        // Check for duplicate warning/error in URL when page loads
        window.addEventListener('load', function() {
            const urlParams = new URLSearchParams(window.location.search);
            
            if (urlParams.has('duplicate_warning')) {
                const message = decodeURIComponent(urlParams.get('message'));
                showDuplicateModal(message, false);
                window.history.replaceState({}, document.title, window.location.pathname);
            }
            
            if (urlParams.has('duplicate_error')) {
                const message = decodeURIComponent(urlParams.get('message'));
                showDuplicateModal(message, true);
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        });

        function openAddClassModal() {
            document.getElementById('addClassModal').style.display = 'block';
        }

        function closeAddClassModal() {
            document.getElementById('addClassModal').style.display = 'none';
        }

        function profileDropdown() {
            document.getElementById("dropdown").classList.toggle("show");
        }

        // Close modals and dropdowns when clicking outside
        window.onclick = function(event) {
            // Close modals
            var addClassModal = document.getElementById('addClassModal');
            if (event.target == addClassModal) {
                closeAddClassModal();
            }
            
            // Close profile dropdown
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
$conn->close();
?>