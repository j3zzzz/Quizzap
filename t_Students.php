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

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$teacher_id = $_SESSION['account_number'];
$subject_id = isset($_GET['subject']) ? intval($_GET['subject']) : null;

// Fetch profile pic
$loggedInUser = $_SESSION['account_number'];
$sql = "SELECT profile_pic FROM teachers WHERE account_number = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $loggedInUser);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $profilePic = $row['profile_pic'] ?: "uploads/default_profile.png";
} else {
    $profilePic = "uploads/default_profile.png";
}
$stmt->close();

// Handle enrollment removal
if (isset($_POST['remove_student'])) {
    $student_account = $_POST['student_account'];
    $subject_id = $_POST['subject_id'];
    
    // Verify the subject belongs to the current teacher
    $verify_subject_sql = "SELECT 1 FROM subjects WHERE subject_id = ? AND teacher_id = ?";
    $verify_stmt = $conn->prepare($verify_subject_sql);
    $verify_stmt->bind_param("is", $subject_id, $teacher_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    
    if ($verify_result->num_rows > 0) {
        // Get student_id from account_number
        $student_sql = "SELECT student_id FROM students WHERE account_number = ?";
        $student_stmt = $conn->prepare($student_sql);
        $student_stmt->bind_param("s", $student_account);
        $student_stmt->execute();
        $student_result = $student_stmt->get_result();
        
        if ($student_result->num_rows > 0) {
            $student_id = $student_result->fetch_assoc()['student_id'];
            
            // Delete the enrollment
            $delete_sql = "DELETE FROM enrollments WHERE student_id = ? AND subject_id = ?";
            $delete_stmt = $conn->prepare($delete_sql);
            $delete_stmt->bind_param("ii", $student_id, $subject_id);
            
            if ($delete_stmt->execute()) {
                // Get subject name for success message
                $subject_name_sql = "SELECT subject_name FROM subjects WHERE subject_id = ?";
                $subject_name_stmt = $conn->prepare($subject_name_sql);
                $subject_name_stmt->bind_param("i", $subject_id);
                $subject_name_stmt->execute();
                $subject_name_result = $subject_name_stmt->get_result();
                $subject_name = $subject_name_result->fetch_assoc()['subject_name'] ?? 'the subject';
                
                $_SESSION['enroll_message'] = "Student successfully removed from $subject_name.";
                header("Location: t_Students.php?subject=$subject_id");
                exit();
            } else {
                $message = "Error removing student: " . $conn->error;
            }
        } else {
            $message = "Student not found.";
        }
    } else {
        $message = "Invalid subject selection.";
    }
}

// Fetch subjects taught by the teacher
$subjects_query = "SELECT subject_id, subject_name FROM subjects WHERE teacher_id = ?";
$subjects_stmt = $conn->prepare($subjects_query);
$subjects_stmt->bind_param("s", $teacher_id);
$subjects_stmt->execute();
$subjects_result = $subjects_stmt->get_result();

// Get selected subject filter (if any)
$selected_subject = isset($_GET['subject']) ? intval($_GET['subject']) : null;

// Handle enrollment form submission
if (isset($_POST['enroll_students'])) {
    if (!empty($selected_subject)) {
        $student_accounts = $_POST['students'] ?? [];
        $enrolled_count = 0;
        
        // Verify the subject belongs to the current teacher
        $verify_subject_sql = "SELECT 1 FROM subjects WHERE subject_id = ? AND teacher_id = ?";
        $verify_stmt = $conn->prepare($verify_subject_sql);
        $verify_stmt->bind_param("is", $selected_subject, $teacher_id);
        $verify_stmt->execute();
        $verify_result = $verify_stmt->get_result();
        
        if ($verify_result->num_rows > 0) {
            $conn->begin_transaction();
            try {
                foreach ($student_accounts as $account_number) {
                    // Check if student exists
                    $check_student_sql = "SELECT student_id FROM students WHERE account_number = ?";
                    $check_student_stmt = $conn->prepare($check_student_sql);
                    $check_student_stmt->bind_param("s", $account_number);
                    $check_student_stmt->execute();
                    $check_student_result = $check_student_stmt->get_result();
                    
                    if ($check_student_result->num_rows > 0) {
                        $student_id = $check_student_result->fetch_assoc()['student_id'];
                        
                        // Check if already enrolled to prevent duplicates
                        $check_enrollment_sql = "SELECT 1 FROM enrollments WHERE student_id = ? AND subject_id = ?";
                        $check_enrollment_stmt = $conn->prepare($check_enrollment_sql);
                        $check_enrollment_stmt->bind_param("ii", $student_id, $selected_subject);
                        $check_enrollment_stmt->execute();
                        $check_enrollment_result = $check_enrollment_stmt->get_result();
                        
                        if ($check_enrollment_result->num_rows == 0) {
                            // Insert enrollment
                            $enrollment_sql = "INSERT INTO enrollments (student_id, subject_id) VALUES (?, ?)";
                            $enrollment_stmt = $conn->prepare($enrollment_sql);
                            $enrollment_stmt->bind_param("ii", $student_id, $selected_subject);
                            $enrollment_stmt->execute();
                            $enrolled_count++;
                        }
                    }
                }
                
                $conn->commit();
                
                // Get subject name for success message
                $subject_name_sql = "SELECT subject_name FROM subjects WHERE subject_id = ?";
                $subject_name_stmt = $conn->prepare($subject_name_sql);
                $subject_name_stmt->bind_param("i", $selected_subject);
                $subject_name_stmt->execute();
                $subject_name_result = $subject_name_stmt->get_result();
                $subject_name = $subject_name_result->fetch_assoc()['subject_name'] ?? 'the subject';
                
                $_SESSION['enroll_message'] = "Successfully enrolled $enrolled_count students to $subject_name.";
                header("Location: t_Students.php?subject=$selected_subject");
                exit();
            } catch (Exception $e) {
                $conn->rollback();
                $message = "Error enrolling students: " . $e->getMessage();
            }
        } else {
            $message = "Invalid subject selection.";
        }
    } else {
        $message = "Please select a subject first.";
    }
}

// Fetch all registered students (not yet enrolled in the selected subject)
if ($selected_subject) {
    $all_students_query = "SELECT s.account_number, s.fname, s.lname, s.glevel, s.strand 
                          FROM students s
                          WHERE NOT EXISTS (
                              SELECT 1 FROM enrollments e 
                              JOIN subjects sub ON e.subject_id = sub.subject_id
                              WHERE e.student_id = s.student_id 
                              AND sub.subject_id = ? 
                              AND sub.teacher_id = ?
                          )";
    $all_students_stmt = $conn->prepare($all_students_query);
    $all_students_stmt->bind_param("is", $selected_subject, $teacher_id);
    $all_students_stmt->execute();
    $all_students_result = $all_students_stmt->get_result();
}

// Fetch enrolled students - different query based on whether subject is selected
if ($selected_subject) {
    // Query for specific subject (no subject column needed)
    $enrolled_students_query = "
        SELECT s.account_number, s.fname, s.lname, s.glevel, s.strand
        FROM students s
        JOIN enrollments e ON s.student_id = e.student_id
        JOIN subjects sub ON sub.subject_id = e.subject_id
        WHERE sub.subject_id = ? AND sub.teacher_id = ?
        ORDER BY s.lname, s.fname
    ";
    $enrolled_students_stmt = $conn->prepare($enrolled_students_query);
    $enrolled_students_stmt->bind_param("is", $selected_subject, $teacher_id);
} else {
    // Query for all subjects (include subject column)
    $enrolled_students_query = "
        SELECT s.account_number, s.fname, s.lname, s.glevel, s.strand, sub.subject_name
        FROM students s
        JOIN enrollments e ON s.student_id = e.student_id
        JOIN subjects sub ON sub.subject_id = e.subject_id
        WHERE sub.teacher_id = ?
        ORDER BY sub.subject_name, s.lname, s.fname
    ";
    $enrolled_students_stmt = $conn->prepare($enrolled_students_query);
    $enrolled_students_stmt->bind_param("s", $teacher_id);
}

$enrolled_students_stmt->execute();
$enrolled_students_result = $enrolled_students_stmt->get_result();

// Display success message if exists
if (isset($_SESSION['enroll_message'])) {
    $message = $_SESSION['enroll_message'];
    unset($_SESSION['enroll_message']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <title>Students List</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Fredoka';
        }

        body, html {
            height: 100%;
        }

        .container {
            display: flex;
            height: 100vh;
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

        .content span {
            font-family: Fredoka;
            font-size: larger;
        }

        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .content-header h1 {
            width: 95%;
            font-size: 2rem;
            color: #333333;
            font-family: Fredoka;
            padding: 10px;
            border-bottom: 1.5px solid #F8B500;
        }

        .content-header p {
            color: #999;
            font-size: 1rem;
            margin-top: 0.5rem;
            font-family: Fredoka;
            font-weight: 500;
        }

        .content-header .actions {
            display: flex;
            align-items: center;
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
        }

        .enrollment-container {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            flex-direction: column;
        }
        
        .student-table-container {
            flex: 1;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            background-color: #f9f9f9;
            margin-top: 10px;
            margin-bottom: 20px;
            overflow-x: auto;
        }
        
        .student-table-container h3 {
            margin-top: 0;
            color: #333;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }
        
        .student-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 25px;
        }
        
        .student-table th {
            background-color: #f8b500;
            color: white;
            padding: 10px;
            text-align: left;
            font-weight: 500;
        }
        
        .student-table td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }
        
        .student-table tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        
        .student-table tr:hover {
            background-color: #e9e9e9;
        }
        
        .enroll-actions {
            text-align: center;
            margin-top: 15px;
        }
        
        .enroll-btn {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }
        
        .enroll-btn:hover {
            background-color: #45a049;
        }
        
        .enroll-btn:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
        }
        
        .tables-container {
            display: flex;
            gap: 20px;
        }
        
        @media (max-width: 768px) {
            .tables-container {
                flex-direction: column;
            }
        }

        .message {
            font-family: Fredoka;
            font-weight: 500;
        }

        #subject-filter{
            font-size: 15px;
            padding: 5px;
        }

        .enroll-new-btn {
            float: right;
            width: 20%;
            background-color: #f8b500;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            margin-top: 20px;
            margin-bottom: 8px;
            font-family: 'Fredoka';
        }
        
        .enroll-new-btn:hover {
            background-color: #e5941f;
        }
        
        .available-students-container {
            display: none; /* Hidden by default */
            margin-bottom: 20px;
        }
        
        .available-students-container.show {
            display: block; /* Show when toggled */
        }
        
        .tables-container {
            display: flex;
            gap: 20px;
        }

        .remove-btn {
            background-color: #e74c3c;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s;
            font-family: 'Fredoka';
        }

        .remove-btn:hover {
            background-color: #c0392b;
        }

        .remove-btn i {
            margin-right: 5px;
        }
        
        @media (max-width: 768px) {
            .tables-container {
                flex-direction: column;
            }
        }

        .dropdown-content {
            width: 250px;
            right: 1%;
            display: none;
            position: absolute;
            background-color: #F8B500;
            border-radius: 15px;
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
            z-index: 1;
            padding: 10px 0;
            top: 100px;
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
            font-family: 'Fredoka';
            font-size: 16px;
            font-weight: lighter;
            border: 2px solid white !important;
            color: white;
            width: 86% !important;
            padding: 10px 15px !important;
            margin: 8px 20px !important;
            text-decoration: none;
            display: block;
            text-align: center;
            background-color: transparent;
            transition: background-color 0.3s, color 0.3s;
            border-radius: 10px;
            cursor: pointer;
            letter-spacing: 1px;
            box-sizing: border-box;
        }

        .dropdown-content a:hover, .dropdown-content button:hover {
            background-color: white !important;
            color: #F8B500;
        }

        .show {
            display: block;
        }

        @media (max-width: 992px) {
        .sidebar {
            width: 90px;
            padding: 2rem 0.5rem;
        }
        .sidebar .menu a span {
            display: none;
        }
        .sidebar .logo-img {
            display: none;
        }
        .sidebar .logo-icon {
            display: block !important;
        }
        .sidebar .menu a i {
            margin-right: 0;
            font-size: 1.5rem;
        }
        .content {
            margin-left: 90px;
        }
    }

    @media (max-width: 768px) {
        .sidebar {
            transform: translateX(-100%);
            width: 250px;
        }
        .sidebar.active {
            transform: translateX(0);
        }
        .content {
            margin-left: 0;
        }
        .content.expanded {
            margin-left: 0;
        }
        #toggleSidebar {
            display: block;
            position: fixed;
            left: 10px;
            top: 10px;
            z-index: 1000;
            background: #f8b500;
            color: white;
            border: none;
            padding: 10px;
            border-radius: 5px;
        }
    }

    /* Improved Table Styling */
    table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        font-family: 'Fredoka', sans-serif;
        margin: 20px 0;
        box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        border-radius: 10px;
        overflow: hidden;
    }

    th {
        background-color: #f8b500;
        color: white;
        padding: 15px;
        text-align: center;
        font-weight: 600;
        position: sticky;
        top: 0;
    }

    td {
        padding: 15px;
        text-align: left;
        border-bottom: 1px solid #e0e0e0;
    }

    tr:nth-child(even) {
        background-color: #f9f9f9;
    }

    tr:hover {
        background-color: #f1f1f1;
        transition: background-color 0.2s ease;
    }

    tr:last-child td {
        border-bottom: none;
    }

    /* Table header rounded corners */
    thead tr:first-child th:first-child {
        border-top-left-radius: 10px;
    }

    thead tr:first-child th:last-child {
        border-top-right-radius: 10px;
    }

    /* Table body rounded corners */
    tbody tr:last-child td:first-child {
        border-bottom-left-radius: 10px;
    }

    tbody tr:last-child td:last-child {
        border-bottom-right-radius: 10px;
    }

    /* Responsive table */
    @media (max-width: 768px) {
        table {
            display: block;
            overflow-x: auto;
            white-space: nowrap;
        }
        
        th, td {
            min-width: 120px;
        }
    }

    /* Checkbox styling */
    input[type="checkbox"] {
        transform: scale(1.3);
        cursor: pointer;
    }

    /* Empty table message */
    .empty-message {
        color: #666;
        font-style: italic;
        padding: 20px;
        text-align: center;
        font-family: 'Fredoka';
    }

    @media (max-width: 576px) {
        #csv-cont {
            flex-direction: column;
            gap: 15px;
        }
        #bulk {
            width: 100%;
        }
    }

    /* Profile dropdown responsive */
    @media (max-width: 576px) {
        .dropdown-content {
            width: 250px;
            right: 0;
        }
    }

    /* Filter container responsive */
    .subject-filter-container {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 10px;
    }

    @media (max-width: 576px) {
        .filter-container {
            flex-direction: column;
            align-items: flex-start;
        }
        .filter-container select {
            margin-left: 0;
            width: 100%;
        }
    }

    /* Message styling */
    .message {
        padding: 15px;
        margin: 15px 0;
        border-radius: 5px;
        background-color: #f8f8f8;
        border-left: 4px solid #f8b500;
        font-weight: 500;
    }

    /* File container responsive */
    .file-container {
        display: flex;
        align-items: center;
        padding: 20px 20px;
    }

    @media (max-width: 576px) {
        .file-container {
            width: 100%;
            justify-content: space-between;
        }
    }

    /* Search input styling */
    #available-search, #enrolled-search {
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-family: 'Fredoka';
        font-size: 14px;
        width: 250px;
        margin-bottom: 10px;
    }

    #available-search:focus, #enrolled-search:focus{
        outline: none;
        border-color: #f8b500;
        box-shadow: 0 0 5px rgba(248, 181, 0, 0.5);
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
                <a href="t_Home.php" title="Dashboard">
                    <i class="fa-solid fa-house"></i>
                    <span>Dashboard</span>
                </a>
                <a href="t_Students.php" class="active" title="Students">
                    <i class="fa-regular fa-address-book"></i>
                    <span>Students</span>
                </a>
                <a href="t_SubjectsList.php" title="Subjects">
                    <i class="fa-solid fa-list"></i>
                    <span>Subjects</span>
                </a>
            </div>
        </div>

        <!-- Content Area -->
        <div class="content">
            <div class="content-header">
                <h1>Students</h1><br>
                <div class="actions">
                    <div class="profile">
                        <img src="<?php echo $profilePic; ?>" style="cursor: pointer;" onclick="profileDropdown()" width="50px" height="50px" class="dropdwn-btn">
                        <div id="dropdown" class="dropdown-content">
                            <button onclick="window.location.href='t_Profile.php'"><i class="fa-solid fa-user"></i> Profile</button> 
                            <form action="logout.php" method="POST">
                                <button><i class="fa-solid fa-right-from-bracket"></i> Logout</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!empty($message)): ?>
                <div class="message">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="filter-container">
                <label for="subject-filter">Select Subject:</label>
                <select id="subject-filter" onchange="filterSubject()">
                    <option value="">All Subjects</option>
                    <?php 
                    $subjects_result->data_seek(0); // Reset pointer to beginning
                    while ($subject = $subjects_result->fetch_assoc()): ?>
                        <option value="<?php echo htmlspecialchars($subject['subject_id']); ?>" 
                            <?php echo ($selected_subject == $subject['subject_id'] ? 'selected' : ''); ?>>
                            <?php echo htmlspecialchars($subject['subject_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="enrollment-container">
                <?php if ($selected_subject): ?>
                    <button id="enroll-new-btn" class="enroll-new-btn">
                        <i class="fas fa-user-plus"></i> Enroll New Students
                    </button>
                    
                    <!-- Available Students Table (hidden by default) -->
                    <div id="available-students-container" class="available-students-container">
                        <form id="enroll-form" method="POST">
                            <input type="hidden" name="subject" value="<?php echo $selected_subject; ?>">
                            
                            <div class="student-table-container">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                    <h3 style="margin: 0;">Available Students</h3>
                                    <div>
                                        <input type="text" id="available-search" placeholder="Search students..." style="padding: 8px 12px; border-radius: 4px; border: 1px solid #ddd; width: 250px;">
                                    </div>
                                </div>
                                <?php if ($all_students_result->num_rows > 0): ?>
                                    <table class="student-table" id="available-students-table">
    <thead>
        <tr>
            <th>Select</th>
            <th>Name</th>
            <th>Account Number</th>
            <th>Grade Level</th>
            <th>Strand</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($all_students_result->num_rows > 0): ?>
            <?php 
            $all_students_result->data_seek(0); // Reset pointer
            while ($student = $all_students_result->fetch_assoc()): ?>
                <tr>
                    <td><input type="checkbox" name="students[]" value="<?php echo htmlspecialchars($student['account_number']); ?>"></td>
                    <td><?php echo htmlspecialchars($student['lname'] . ', ' . $student['fname']); ?></td>
                    <td><?php echo htmlspecialchars($student['account_number']); ?></td>
                    <td><?php echo htmlspecialchars($student['glevel']); ?></td>
                    <td><?php echo htmlspecialchars($student['strand'] ?? '-'); ?></td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr class="no-results-row">
                <td colspan="5" class="empty-message">No students available to enroll.</td>
            </tr>
        <?php endif; ?>
        <tr class="search-no-results" style="display: none;">
            <td colspan="5" class="empty-message">No matching students found.</td>
        </tr>
    </tbody>
</table>
                                <?php else: ?>
                                    <p>No students available to enroll.</p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="enroll-actions">
                                <button type="submit" name="enroll_students" class="enroll-btn" 
                                    <?php echo ($all_students_result->num_rows == 0 ? 'disabled' : ''); ?>>
                                    <i class="fas fa-save"></i> Confirm Enrollment
                                </button>
                                <button type="button" id="cancel-enroll-btn" class="enroll-btn" style="background-color: #ccc; margin-left: 10px;">
                                    <i class="fas fa-times"></i> Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- Enrolled Students Table -->
                <div class="student-table-container">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <h3 style="margin: 0;"><?php echo $selected_subject ? 'Currently Enrolled Students' : 'All Enrolled Students'; ?></h3>
                    <div>
                        <input type="text" id="enrolled-search" placeholder="Search enrolled students..." style="padding: 8px 12px; border-radius: 4px; border: 1px solid #ddd; width: 250px;">
                    </div>
                </div>
                <?php if ($enrolled_students_result->num_rows > 0): ?>
                    
                        <table class="student-table" id="enrolled-students-table">
    <thead>
        <tr>
            <th>Name</th>
            <th>Account Number</th>
            <th>Grade Level</th>
            <th>Strand</th>
            <?php if (!$selected_subject): ?>
                <th>Subject</th>
            <?php endif; ?>
            <?php if ($selected_subject): ?>
                <th>Action</th>
            <?php endif; ?>
        </tr>
    </thead>
    <tbody>
        <?php if ($enrolled_students_result->num_rows > 0): ?>
            <?php 
            $enrolled_students_result->data_seek(0);
            while ($student = $enrolled_students_result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($student['lname'] . ', ' . $student['fname']); ?></td>
                    <td><?php echo htmlspecialchars($student['account_number']); ?></td>
                    <td><?php echo htmlspecialchars($student['glevel']); ?></td>
                    <td><?php echo htmlspecialchars($student['strand'] ?? '-'); ?></td>
                    <?php if (!$selected_subject): ?>
                        <td><?php echo htmlspecialchars($student['subject_name']); ?></td>
                    <?php endif; ?>
                    <?php if ($selected_subject): ?>
                        <td>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="student_account" value="<?php echo htmlspecialchars($student['account_number']); ?>">
                                <input type="hidden" name="subject_id" value="<?php echo $selected_subject; ?>">
                                <button type="submit" name="remove_student" class="remove-btn" 
                                    onclick="return confirm('Are you sure you want to remove this student from the subject?')">
                                    <i class="fas fa-user-minus"></i> Remove
                                </button>
                            </form>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr class="no-results-row">
                <td colspan="<?php echo $selected_subject ? '5' : '6'; ?>" class="empty-message">
                    <?php echo $selected_subject ? 'No students enrolled in this subject yet.' : 'No students enrolled in any of your subjects yet.'; ?>
                </td>
            </tr>
        <?php endif; ?>
        <tr class="search-no-results" style="display: none;">
            <td colspan="<?php echo $selected_subject ? '5' : '6'; ?>" class="empty-message">No matching students found.</td>
        </tr>
    </tbody>
</table>
                    <?php else: ?>
                        <p><?php echo $selected_subject ? 'No students enrolled in this subject yet.' : 'No students enrolled in any of your subjects yet.'; ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.querySelector('.sidebar');
        const content = document.querySelector('.content');
        const toggleBtn = document.getElementById('toggleSidebar');

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

        // Search functionality for available students
        const availableSearch = document.getElementById('available-search');
if (availableSearch) {
    availableSearch.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        const rows = document.querySelectorAll('#available-students-table tbody tr:not(.no-results-row):not(.search-no-results)');
        const noResultsRow = document.querySelector('#available-students-table .search-no-results');
        let hasVisibleRows = false;
        
        rows.forEach(row => {
            const name = row.cells[1].textContent.toLowerCase();
            const accountNumber = row.cells[2].textContent.toLowerCase();
            const gradeLevel = row.cells[3].textContent.toLowerCase();
            const strand = row.cells[4].textContent.toLowerCase();
            
            if (name.includes(searchTerm) || 
                accountNumber.includes(searchTerm) || 
                gradeLevel.includes(searchTerm) || 
                strand.includes(searchTerm)) {
                row.style.display = '';
                hasVisibleRows = true;
            } else {
                row.style.display = 'none';
            }
        });
        
        // Show/hide no results message
        if (hasVisibleRows || searchTerm === '') {
            noResultsRow.style.display = 'none';
        } else {
            noResultsRow.style.display = '';
        }
    });
}

// Search functionality for enrolled students
const enrolledSearch = document.getElementById('enrolled-search');
if (enrolledSearch) {
    enrolledSearch.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        const rows = document.querySelectorAll('#enrolled-students-table tbody tr:not(.no-results-row):not(.search-no-results)');
        const noResultsRow = document.querySelector('#enrolled-students-table .search-no-results');
        let hasVisibleRows = false;
        
        rows.forEach(row => {
            let found = false;
            // Check each cell in the row (except the action cell if it exists)
            for (let i = 0; i < row.cells.length; i++) {
                // Skip the action column if it exists
                if (row.cells[i].querySelector('.remove-btn')) continue;
                
                const cellText = row.cells[i].textContent.toLowerCase();
                if (cellText.includes(searchTerm)) {
                    found = true;
                    break;
                }
            }
            
            if (found) {
                row.style.display = '';
                hasVisibleRows = true;
            } else {
                row.style.display = 'none';
            }
        });
        
        // Show/hide no results message
        if (hasVisibleRows || searchTerm === '') {
            noResultsRow.style.display = 'none';
        } else {
            noResultsRow.style.display = '';
        }
    });
}

        // Toggle available students table
        const enrollNewBtn = document.getElementById('enroll-new-btn');
        const availableStudentsContainer = document.getElementById('available-students-container');
        const cancelEnrollBtn = document.getElementById('cancel-enroll-btn');
        
        if (enrollNewBtn && availableStudentsContainer) {
            enrollNewBtn.addEventListener('click', function() {
                availableStudentsContainer.classList.add('show');
                enrollNewBtn.style.display = 'none';
            });
            
            if (cancelEnrollBtn) {
                cancelEnrollBtn.addEventListener('click', function() {
                    availableStudentsContainer.classList.remove('show');
                    enrollNewBtn.style.display = 'block';
                    // Uncheck all checkboxes when canceling
                    document.querySelectorAll('#enroll-form input[type="checkbox"]').forEach(checkbox => {
                        checkbox.checked = false;
                    });
                    // Clear search
                    document.getElementById('available-search').value = '';
                    // Show all rows again
                    document.querySelectorAll('#available-students-table tbody tr').forEach(row => {
                        row.style.display = '';
                    });
                });
            }
        }
    });

    function profileDropdown() {
        document.getElementById("dropdown").classList.toggle("show");
    }

    window.onclick = function(event) {
        if (!event.target.matches('.dropdwn-btn')) {
            var dropdowns = document.getElementsByClassName("dropdown-content");
            for (var i = 0; i < dropdowns.length; i++) {
                var openDropdown = dropdowns[i];
                if (openDropdown.classList.contains('show')) {
                    openDropdown.classList.remove('show');
                }
            }
        }
    }

    // Subject filter function
    function filterSubject() {
        const selectedSubject = document.getElementById('subject-filter').value;
        window.location.href = `t_Students.php?subject=${selectedSubject}`;
    }
</script>
</body>
</html>

<?php 
$subjects_stmt->close();
if (isset($all_students_stmt)) {
    $all_students_stmt->close();
}
$enrolled_students_stmt->close();
$conn->close();
?>