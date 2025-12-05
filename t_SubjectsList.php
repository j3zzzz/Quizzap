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

//query para sa profile pic
$sql = "SELECT profile_pic, school_id FROM teachers WHERE account_number = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $loggedInUser);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $school_id = $row['school_id'];
    $profile_pic = $row['profile_pic'] ? $row['profile_pic'] : 'default-profile.jpg';
} else {
    $profile_pic = 'default-profile.jpg';
}

$stmt->close();


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

// Handle subject deletion
if (isset($_POST['delete_subjects'])) {
    if (!empty($_POST['selected_subjects'])) {
        $deleted_count = count($_POST['selected_subjects']);
        foreach ($_POST['selected_subjects'] as $subject_id) {
            // First delete quizzes related to this subject
            $delete_quizzes_sql = "DELETE FROM quizzes WHERE subject_id = ?";
            $stmt = $conn->prepare($delete_quizzes_sql);
            $stmt->bind_param("i", $subject_id);
            $stmt->execute();
            $stmt->close();
            
            // Then delete the subject
            $delete_subject_sql = "DELETE FROM subjects WHERE subject_id = ? AND teacher_id = ?";
            $stmt = $conn->prepare($delete_subject_sql);
            $stmt->bind_param("is", $subject_id, $loggedInUser);
            $stmt->execute();
            $stmt->close();
        }
        // Store success message in session for modal display
        $_SESSION['success_message'] = "Successfully deleted $deleted_count subject(s) and their associated quizzes.";
        // Redirect to avoid form resubmission
        header("Location: t_SubjectsList.php?deleted=true");
        exit();
    } else {
        $_SESSION['error_message'] = 'No subjects selected for deletion.';
    }
}

$success_message = '';
$error_message = '';

// Check for success/error messages in session
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Check if we have a subject code from successful creation
$new_subject_code = '';
if (isset($_SESSION['new_subject_code'])) {
    $new_subject_code = $_SESSION['new_subject_code'];
    unset($_SESSION['new_subject_code']);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['subject_name'])) {
    $subject_name = $_POST['subject_name'] ?? null;
    $grade_level = $_POST['grade_level'] ?? null;
    $section = $_POST['section'] ?? null;
    $strand = $_POST['strand'] ?? null;
    $teacher_account_number = $_SESSION['account_number'];
    $school_id = $row['school_id'];
    $subject_code = generateUniqueSubjectCode($conn);
    $section_display = $section;

    // Check if the same class already exists for this teacher
    $check_sql = "SELECT * FROM subjects WHERE subject_name = ? AND grade_level = ? AND strand = ? AND section = ? AND teacher_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("sssss", $subject_name, $grade_level, $strand, $section_display, $teacher_account_number);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $_SESSION['error_message'] = 'A class with the same subject, grade level, and section already exists.';
    } else {
        $stmt = $conn->prepare("INSERT INTO subjects (subject_name, teacher_id, subject_code, grade_level, section, school_id, strand) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssss", $subject_name, $teacher_account_number, $subject_code, $grade_level, $section_display, $school_id, $strand);
        
        if ($stmt->execute()) {
            // Store success message and subject code in session
            $_SESSION['success_message'] = "Subject created successfully!";
            $_SESSION['new_subject_code'] = $subject_code;
            // Redirect to avoid form resubmission
            header("Location: t_SubjectsList.php?created=true");
            exit();
        } else {
            $_SESSION['error_message'] = "Error: " . $stmt->error;
        }
        $stmt->close();
    }
    $check_stmt->close();
}

$sql = "SELECT * FROM subjects WHERE teacher_id = '" . $_SESSION['account_number'] . "' ORDER BY subject_id DESC";
$result = $conn->query($sql);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="other resources\fontawesome-free-6.5.2-web\css\all.min.css">
    <title>Subjects</title>
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

        /* Sidebar styling - Hidden on mobile */
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
            width: 100%;
            padding-bottom: 10px;
            border-bottom: 1.5px solid #F8B500;
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

        /* School info box */
        .school-info {
            margin-top: 10px;
            background-color: #f8b50052;
            padding: clamp(12px, 2vw, 15px);
            border-radius: 8px;
            width: 100%;
        }

        .school-id-display {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: clamp(8px, 1.5vw, 12px);
        }

        .school-id-display strong {
            font-size: clamp(14px, 1.8vw, 16px);
        }

        .school-tip {
            font-size: clamp(12px, 1.5vw, 14px);
            font-style: italic;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }

        .school-tip i {
            color: #f8b500;
            background-color: white;
            padding: clamp(8px, 1.5vw, 10px);
            border-radius: 50%;
            flex-shrink: 0;
            font-size: clamp(14px, 1.8vw, 16px);
        }

        body.dark-mode .school-tip i {
            background-color: #333;
        }

        /* Subject actions */
        .subject-actions-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: clamp(20px, 3vw, 30px) 0;
            flex-wrap: wrap;
            gap: 15px;
            width: 100%;
        }

        /* Button styles - Smaller for mobile */
        .add-subject-btn {
            background-color: #F8B500;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            font-family: 'Fredoka';
            font-weight: 500;
            white-space: nowrap;
            min-height: 44px;
            box-shadow: 0 4px 0 0 #d89e00;
            transition: all 0.3s;
        }

        @media (max-width: 768px) {
            .add-subject-btn {
                padding: 10px 20px;
                font-size: 15px;
                min-height: 42px;
            }
        }

        @media (max-width: 480px) {
            .add-subject-btn {
                padding: 8px 16px;
                font-size: 14px;
                min-height: 40px;
            }
        }

        .add-subject-btn:hover {
            background-color: #e6a500;
            transform: translateY(-2px);
            box-shadow: 0 6px 0 0 #d89e00;
        }

        .add-subject-btn:active {
            transform: translateY(1px);
            box-shadow: 0 3px 0 0 #d89e00;
        }

        /* Edit button styling */
        .edit-btn {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            font-family: Fredoka;
            box-shadow: 0 4px 0 0 #1e7e34;
            min-height: 44px;
            transition: all 0.3s;
        }

        @media (max-width: 768px) {
            .edit-btn {
                padding: 10px 20px;
                font-size: 15px;
                min-height: 42px;
            }
        }

        @media (max-width: 480px) {
            .edit-btn {
                padding: 8px 16px;
                font-size: 14px;
                min-height: 40px;
            }
        }

        .edit-btn:hover {
            background-color: #218838;
            transform: translateY(-2px);
            box-shadow: 0 6px 0 0 #1e7e34;
        }

        .edit-btn:active {
            transform: translateY(1px);
            box-shadow: 0 3px 0 0 #1e7e34;
        }

        /* Delete button styling - FIXED */
        .delete-btn {
            background-color: #ff4444;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            font-family: Fredoka;
            box-shadow: 0 4px 0 0 #cc0000;
            min-height: 44px;
            transition: all 0.3s;
            display: none; /* Hidden by default */
        }

        @media (max-width: 768px) {
            .delete-btn {
                padding: 10px 20px;
                font-size: 15px;
                min-height: 42px;
            }
        }

        @media (max-width: 480px) {
            .delete-btn {
                padding: 8px 16px;
                font-size: 14px;
                min-height: 40px;
            }
        }

        .delete-btn.show {
            display: block; /* Show when subjects are selected */
        }

        @media (max-width: 768px) {
            .delete-btn.show {
                display: block;
            }
        }

        .delete-btn:hover {
            background-color: #cc0000;
            transform: translateY(-2px);
            box-shadow: 0 6px 0 0 #cc0000;
        }

        .delete-btn:active {
            transform: translateY(1px);
            box-shadow: 0 3px 0 0 #cc0000;
        }

        /* Action buttons container */
        .action-buttons {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        /* Select all container - Hidden by default */
        .select-all-container {
            margin-bottom: clamp(15px, 2vw, 20px);
            display: flex;
            align-items: center;
            gap: 10px;
            width: 100%;
            display: none; /* Hidden by default */
        }

        .select-all-container.show {
            display: flex;
        }

        .select-all-checkbox {
            transform: scale(1.3);
            accent-color: #f8b500;
            cursor: pointer;
        }

        .select-all-label {
            font-family: Fredoka;
            font-size: clamp(14px, 1.5vw, 16px);
            color: #555;
            cursor: pointer;
        }

        body.dark-mode .select-all-label {
            color: #b0b0b0;
        }

        /* Subjects grid */
        .subjects-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: clamp(15px, 2vw, 20px);
            width: 100%;
            margin-bottom: 30px;
        }

        .subject-item {
            display: flex;
            align-items: flex-start;
            gap: clamp(10px, 1.5vw, 15px);
            margin-bottom: 0;
        }

        /* Subject checkbox - Hidden by default */
        .subject-checkbox {
            transform: scale(1.3);
            margin-top: 20px;
            accent-color: #f8b500;
            cursor: pointer;
            flex-shrink: 0;
            display: none; /* Hidden by default */
        }

        .subject-checkbox.show {
            display: block; /* Show when in edit mode */
        }

        .subject-button {
            color: black;
            font-family: Fredoka;
            font-size: clamp(18px, 2.5vw, 24px);
            font-weight: 600;
            background-color: white;
            display: block;
            border-radius: 6px;
            border: 2px solid #f8b500;
            text-decoration: none;
            text-align: left;
            padding: clamp(12px, 2vw, 16px) clamp(20px, 2.5vw, 30px);
            width: 100%;
            transition: all 0.2s;
            box-shadow: 0 4px 0 0 rgba(0, 0, 0, 0.15);
            min-height: 120px;
            display: flex;
            flex-direction: column;
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
            box-shadow: 0 6px 0 0 rgba(0, 0, 0, 0.2);
        }

        body.dark-mode .subject-button:hover {
            background-color: #F8B500;
            color: white;
        }

        .subject-button:active {
            transform: translateY(1px);
            box-shadow: 0 3px 0 0 rgba(0, 0, 0, 0.2);
        }

        .subject-button span {
            font-size: clamp(13px, 1.5vw, 15px);
            font-family: Fredoka;
            color: #666;
            margin-top: 5px;
            display: block;
        }

        body.dark-mode .subject-button span {
            color: #b0b0b0;
        }

        /* Disabled subject button */
        .subject-button-disabled {
            color: #999;
            font-family: Fredoka;
            font-size: clamp(18px, 2.5vw, 24px);
            font-weight: 600;
            background-color: #f5f5f5;
            display: block;
            border-radius: 6px;
            border: 2px solid #ddd;
            text-decoration: none;
            text-align: left;
            padding: clamp(12px, 2vw, 16px) clamp(20px, 2.5vw, 30px);
            width: 100%;
            cursor: not-allowed;
            opacity: 0.7;
            position: relative;
            box-shadow: 0 4px 0 0 rgba(0, 0, 0, 0.1);
            min-height: 120px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        body.dark-mode .subject-button-disabled {
            background-color: #3d3d3d;
            color: #999;
            border: 2px solid #555;
        }

        /* Deactivated badge */
        .deactivated-badge {
            display: inline-block;
            margin-top: 8px;
            padding: clamp(4px, 0.8vw, 6px) clamp(8px, 1.2vw, 12px);
            background-color: #dc3545;
            color: white;
            border-radius: 12px;
            font-size: clamp(12px, 1.3vw, 14px);
            font-weight: 500;
        }

        .deactivated-badge i {
            margin-right: 5px;
        }

        /* No subjects message */
        .no-subjects-message {
            text-align: center;
            padding: clamp(30px, 5vw, 60px) 20px;
            grid-column: 1 / -1;
            color: #999;
        }

        .no-subjects-message p {
            font-family: Fredoka;
            font-size: clamp(18px, 2.5vw, 24px);
            margin-top: 20px;
        }

        body.dark-mode .no-subjects-message {
            color: #b0b0b0;
        }

        /* The Modal (background) */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow-x: hidden;
            overflow-y: auto;
            background-color: rgba(0,0,0,0.4);
            padding: 20px;
        }

        /* Modal Content */
        .modal-content {
            background-color: white;
            margin: auto;
            padding: 0;
            border: none;
            border-radius: 12px;
            width: min(90%, 600px);
            font-family: Fredoka;
            animation: modalFadeIn 0.3s ease-out;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            max-height: 90vh;
            overflow-y: auto;
        }

        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        body.dark-mode .modal-content {
            background-color: #2d2d2d;
            color: #e0e0e0;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: clamp(20px, 3vw, 25px) clamp(20px, 3vw, 30px);
            border-bottom: 2px solid #f8b500;
        }

        .modal-header h2 {
            color: #f8b500;
            font-family: 'Fredoka';
            font-size: clamp(20px, 2.5vw, 28px);
            font-weight: 600;
            margin: 0;
        }

        .modal-body {
            padding: clamp(20px, 3vw, 30px);
        }

        .form-group {
            position: relative;
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-family: 'Fredoka';
            font-size: clamp(14px, 1.5vw, 16px);
            font-weight: 500;
            text-align: left;
        }

        body.dark-mode .form-group label {
            color: #b0b0b0;
        }
        
        .form-group input {
            width: 100%;
            padding: 15px 15px 15px 45px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: clamp(14px, 1.5vw, 16px);
            font-family: 'Fredoka';
            transition: all 0.3s;
            background-color: #f9f9f9;
        }

        body.dark-mode .form-group input {
            background-color: #3d3d3d;
            border-color: #555;
            color: #e0e0e0;
        }
        
        .form-group input:focus {
            border-color: #f8b500;
            background-color: #fff;
            box-shadow: 0 0 0 3px rgba(248, 181, 0, 0.2);
            outline: none;
        }

        body.dark-mode .form-group input:focus {
            background-color: #4d4d4d;
        }

        .select-wrapper {
            position: relative;
        }
        
        .select-wrapper select {
            width: 100%;
            padding: 15px 15px 15px 45px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: clamp(14px, 1.5vw, 16px);
            font-family: 'Fredoka';
            appearance: none;
            background-color: #f9f9f9;
            cursor: pointer;
        }

        body.dark-mode .select-wrapper select {
            background-color: #3d3d3d;
            border-color: #555;
            color: #e0e0e0;
        }
        
        .select-wrapper select:focus {
            border-color: #f8b500;
            background-color: #fff;
            box-shadow: 0 0 0 3px rgba(248, 181, 0, 0.2);
            outline: none;
        }

        body.dark-mode .select-wrapper select:focus {
            background-color: #4d4d4d;
        }
        
        .icon {
            position: absolute;
            left: 15px;
            top: 42px;
            color: #f8b500;
            font-size: clamp(16px, 1.8vw, 18px);
            pointer-events: none;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 30px;
            flex-wrap: wrap;
        }   

        .cancel-btn {
            padding: clamp(10px, 1.2vw, 12px) clamp(20px, 2vw, 25px);
            border: 2px solid #f8b500;
            border-radius: 8px;
            background-color: transparent;
            color: #f8b500;
            font-family: 'Fredoka';
            font-size: clamp(14px, 1.5vw, 16px);
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            min-height: 44px;
            min-width: 100px;
        }
        
        .cancel-btn:hover {
            background-color: #f8b500;
            color: white;
        }

        .submit-btn {
            padding: clamp(10px, 1.2vw, 12px) clamp(20px, 2vw, 25px);
            border: none;
            border-radius: 8px;
            background-color: #f8b500;
            color: white;
            font-family: 'Fredoka';
            font-size: clamp(14px, 1.5vw, 16px);
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 0 0 #d89e00;
            min-height: 44px;
            min-width: 120px;
        }
        
        .submit-btn:hover {
            background-color: #e6a500;
            transform: translateY(-2px);
            box-shadow: 0 6px 0 0 #d89e00;
        }
        
        .submit-btn:active {
            transform: translateY(1px);
            box-shadow: 0 3px 0 0 #d89e00;
        }

        /* Strand field styling */
        .strand-field {
            display: none;
        }

        .strand-field.show {
            display: block;
        }

        /* The Close Button */
        .close {
            color: #f8b500;
            float: right;
            font-size: clamp(24px, 2.5vw, 28px);
            font-weight: bold;
            transition: all 0.3s;
            cursor: pointer;
            line-height: 1;
            padding: 5px;
            border-radius: 4px;
        }

        .close:hover,
        .close:focus {
          color: rgb(176, 132, 11);
          background-color: rgba(248, 181, 0, 0.1);
          text-decoration: none;
        }

        /* Dropdown content */
        .dropdown-content {
            width: min(280px, 90vw);
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
            font-size: clamp(15px, 1.8vw, 18px);
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
            
            .subject-actions-container {
                flex-direction: column;
                align-items: stretch;
                gap: 10px;
            }
            
            .action-buttons {
                width: 100%;
                display: flex;
                flex-direction: column;
                gap: 10px;
            }
            
            .add-subject-btn, .edit-btn, .delete-btn {
                width: 100%;
                text-align: center;
            }
            
            .subjects-container {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .subject-button, .subject-button-disabled {
                padding: 1rem;
                min-height: 100px;
            }
            
            .modal-content {
                width: 95%;
                margin: 20px auto;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .cancel-btn, .submit-btn {
                width: 100%;
            }
            
            /* Hide profile in content header on mobile */
            .content-header .actions {
                display: none;
            }
            
            .dropdown-content {
                right: 0;
                width: min(250px, 80vw);
            }
            
            /* Adjust subject checkbox on mobile */
            .subject-checkbox {
                margin-top: 15px;
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
            
            /* Action buttons layout for desktop */
            .action-buttons {
                flex-direction: row;
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
            }
            
            .subject-button, .subject-button-disabled {
                font-size: 1.1rem;
                min-height: 90px;
            }
            
            .subject-button span {
                font-size: 0.9rem;
            }
            
            .modal-content {
                width: 98%;
                margin: 10px auto;
            }
            
            .modal-header {
                padding: 15px 20px;
            }
            
            .modal-body {
                padding: 15px 20px;
            }
            
            .form-group input, .select-wrapper select {
                padding: 12px 12px 12px 40px;
            }
            
            .icon {
                left: 12px;
                top: 37px;
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
            
            .school-tip {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
            
            .subject-checkbox {
                margin-top: 15px;
            }
            
            .subject-button, .subject-button-disabled {
                min-height: 80px;
                padding: 12px;
            }
            
            .deactivated-badge {
                font-size: 11px;
                padding: 3px 8px;
            }
            
            .dropdown-content {
                width: min(220px, 75vw);
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
            
            .subjects-container {
                gap: 0.75rem;
            }
            
            .subject-button, .subject-button-disabled {
                padding: 10px;
                min-height: 75px;
            }
            
            .modal-header h2 {
                font-size: 1.1rem;
            }
            
            .close {
                font-size: 22px;
            }
            
            .dropdown-content {
                width: min(200px, 70vw);
            }
            
            .dropdown-content button {
                font-size: 13px;
                padding: 7px 10px;
                min-height: 36px;
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

        /* Scrollbar styling */
        ::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }

        ::-webkit-scrollbar-track {
            box-shadow: inset 0 0 5px grey;
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb {
            background: #d3d3d3ff;
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #d3d3d3ff;
        }

        /* Landscape orientation adjustments */
        @media (max-height: 500px) and (orientation: landscape) {
            .sidebar .menu {
                margin-top: 10%;
            }
            
            .modal-content {
                margin-top: 5%;
            }
            
            .subject-button, .subject-button-disabled {
                min-height: 100px;
            }
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
            <a href="t_Home.php" title="Dashboard">
                <i class="fa-solid fa-house"></i>
                <span>Dashboard</span>
            </a>
            <a href="t_Students.php" title="Students">
                <i class="fa-regular fa-address-book"></i>
                <span>Students</span>
            </a>
            <a href="t_SubjectsList.php" class="active" title="Subjects">
                <i class="fa-solid fa-list"></i>
                <span>Subjects</span>
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
                <a href="t_Home.php" title="Dashboard">
                    <i class="fa-solid fa-house"></i>
                    <span>Dashboard</span>
                </a>
                <a href="t_Students.php" title="Students">
                    <i class="fa-regular fa-address-book"></i>
                    <span>Students</span>
                </a>
                <a href="t_SubjectsList.php" class="active" title="Subjects">
                    <i class="fa-solid fa-list"></i>
                    <span>Subjects</span>
                </a>
            </div>
        </div>

        <!-- Content Area -->
        <div class="content">
            <div class="content-header">
                <div>
                    <h1>Subjects</h1>
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
            
            <div class="school-info">
                <div class="school-id-display">
                    <strong>School ID: </strong>
                    <p><?php echo htmlspecialchars($school_id); ?></p>
                </div>
                <div class="school-tip">
                    <i class="fas fa-lightbulb"></i>
                    <p>School ID is used to distinguish between different teachers and prevents mismatching of students. Please provide this school id to your students upon registration.</p>
                </div>
            </div>
            
            <div class="subject-actions-container">
                <button class="add-subject-btn" id="modalbtn">Add Subject</button>
                
                <div class="action-buttons">
                    <button type="button" class="edit-btn" id="editBtn">Edit</button>
                    <button type="button" class="delete-btn" id="deleteBtn">Delete Selected</button>
                </div>
            </div>
            
            <div class="select-all-container" id="selectAllContainer">
                <input type="checkbox" id="select-all" class="select-all-checkbox">
                <label for="select-all" class="select-all-label">Select All</label>
            </div>
            
            <form method="post" action="" id="deleteForm">
                <div class="subjects-container">
                    <?php
                    if ($result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            // Build the display text for grade, section, and strand
                            $display_section = "Grade " . $row['grade_level'] . " - " . $row['section'];
                            
                            // Add strand if it exists (for Grade 11-12)
                            if (!empty($row['strand'])) {
                                $display_section .= " - " . $row['strand'];
                            }

                            // Check if subject is deactivated
                            $isDeactivated = (isset($row['status']) && $row['status'] === 'Deactivated');
                            
                            echo '<div class="subject-item">';
                            echo '<input type="checkbox" name="selected_subjects[]" value="' . $row['subject_id'] . '" class="subject-checkbox" id="subject_' . $row['subject_id'] . '">';

                            if ($isDeactivated) {
                                // Deactivated subject - disabled and with tooltip
                                echo "<div class='subject-button subject-button-disabled' data-tooltip='This class is deactivated. Please contact your administrator to request reactivation.'>" 
                                    . htmlspecialchars($row['subject_name']) 
                                    . "<span>" . htmlspecialchars($row['subject_code']) . " (" . htmlspecialchars($display_section) . ")</span>"
                                    . "<span class='deactivated-badge'><i class='fas fa-ban'></i> Deactivated</span></div>";
                            } else {
                                // Active subject - clickable
                                echo "<a class='subject-button' href='t_quizDash.php?subject_id=" . $row['subject_id'] . "' id='subject_link_" . $row['subject_id'] . "'>" 
                                    . htmlspecialchars($row['subject_name']) 
                                    . "<span>" . htmlspecialchars($row['subject_code']) . " (" . htmlspecialchars($display_section) . ")</span></a>";
                            }   
                                echo '</div>';
                        }
                    } else {
                        echo '<div class="no-subjects-message">';
                        echo '<i class="fas fa-book-open" style="font-size: 3rem; color: #f8b500; margin-bottom: 15px;"></i>';
                        echo "<p>No subjects created yet.</p>";
                        echo '</div>';
                    }
                    ?>
                </div>
                <input type="hidden" name="delete_subjects" value="1">
            </form>
        </div>
    </div>

    <!-- Modal for creating new subject -->
    <div id="myModal" class="modal">
        <!-- Modal content -->
        <div class="modal-content">
            <div class="modal-header">
                <h2>Create New Class</h2>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <form method="post" action="" id="subjectForm">
                    <div class="form-group">
                        <label for="subject_name">Subject Name</label>
                        <input type="text" name="subject_name" placeholder="e.g. Mathematics, Science" required>
                        <i class="fas fa-book icon"></i>
                    </div>
                    
                    <div class="form-group">
                        <label for="grade_level">Grade Level</label>
                        <div class="select-wrapper">
                            <select name="grade_level" id="grade_level_select" required onchange="toggleStrandField()">
                                <option value="" disabled selected>Select grade level</option>
                                <option value="7">Grade 7</option>
                                <option value="8">Grade 8</option>
                                <option value="9">Grade 9</option>
                                <option value="10">Grade 10</option>
                                <option value="11">Grade 11</option>
                                <option value="12">Grade 12</option>
                            </select>
                            <i class="fas fa-chevron-down icon"></i>
                        </div>
                    </div>
                    
                    <div class="form-group strand-field" id="strand_field">
                        <label for="strand">Strand</label>
                        <div class="select-wrapper">
                            <select name="strand" id="strand_select">
                                <option value="" selected>Select strand (Optional)</option>
                                <option value="TVL">TVL (Technical-Vocational-Livelihood)</option>
                                <option value="ABM">ABM (Accountancy, Business and Management)</option>
                                <option value="STEM">STEM (Science, Technology, Engineering and Mathematics)</option>
                                <option value="HUMSS">HUMSS (Humanities and Social Sciences)</option>
                            </select>
                            <i class="fas fa-chevron-down icon"></i>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="section">Section</label>
                        <input type="text" name="section" placeholder="e.g. A, B, Einstein" required>
                        <i class="fas fa-users icon"></i>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="cancel-btn">Cancel</button>
                        <button type="submit" class="submit-btn">Create Subject</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal for deletion confirmation -->
    <div id="deleteConfirmationModal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h2>Confirm Deletion</h2>
                <span class="close delete-close">&times;</span>
            </div>
            <div class="modal-body">
                <div style="text-align: center; padding: 20px 0;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 3rem; color: #ff4444; margin-bottom: 20px;"></i>
                    <h3 style="margin-bottom: 15px; color: #333;">Are you sure?</h3>
                    <p style="margin-bottom: 10px;">You are about to delete <span id="selectedCount">0</span> subject(s).</p>
                    <p style="font-weight: bold; color: #ff4444;">This action cannot be undone!</p>
                    <p style="margin-top: 15px; font-size: 14px; color: #666;">
                        <i class="fas fa-info-circle"></i> This will also delete all quizzes under these subjects.
                    </p>
                </div>
                <div class="form-actions" style="justify-content: center; margin-top: 30px;">
                    <button type="button" class="cancel-btn" id="cancelDeleteBtn">Cancel</button>
                    <button type="button" class="submit-btn" id="confirmDeleteBtn" style="background-color: #ff4444; box-shadow: 0 4px 0 0 #cc0000;">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div id="successModal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header" style="border-bottom-color: #28a745;">
                <h2 style="color: #28a745;">Success!</h2>
                <span class="close success-close">&times;</span>
            </div>
            <div class="modal-body">
                <div style="text-align: center; padding: 20px 0;">
                    <i class="fas fa-check-circle" style="font-size: 3rem; color: #28a745; margin-bottom: 20px;"></i>
                    <h3 style="margin-bottom: 15px; color: #333;" id="successTitle"></h3>
                    <p style="margin-bottom: 10px;" id="successMessage"></p>
                    <p id="subjectCodeInfo" style="display: none; margin-top: 15px; font-size: 14px; color: #666;">
                        <i class="fas fa-info-circle"></i> Share this code with your students: <strong id="subjectCode"></strong>
                    </p>
                </div>
                <div class="form-actions" style="justify-content: center; margin-top: 30px;">
                    <button type="button" class="submit-btn" id="successOkBtn" style="background-color: #28a745; box-shadow: 0 4px 0 0 #1e7e34;">OK</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Error Modal -->
    <div id="errorModal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header" style="border-bottom-color: #ff4444;">
                <h2 style="color: #ff4444;">Error</h2>
                <span class="close error-close">&times;</span>
            </div>
            <div class="modal-body">
                <div style="text-align: center; padding: 20px 0;">
                    <i class="fas fa-exclamation-circle" style="font-size: 3rem; color: #ff4444; margin-bottom: 20px;"></i>
                    <h3 style="margin-bottom: 15px; color: #333;">Something went wrong</h3>
                    <p style="margin-bottom: 10px;" id="errorMessage"></p>
                </div>
                <div class="form-actions" style="justify-content: center; margin-top: 30px;">
                    <button type="button" class="cancel-btn" id="errorOkBtn">OK</button>
                </div>
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
    const editBtn = document.getElementById('editBtn');
    const deleteBtn = document.getElementById('deleteBtn');
    const selectAllContainer = document.getElementById('selectAllContainer');
    const selectAllCheckbox = document.getElementById('select-all');
    const subjectCheckboxes = document.querySelectorAll('.subject-checkbox');
    const subjectLinks = document.querySelectorAll('.subject-button[href]');
    const deleteForm = document.getElementById('deleteForm');
    let isEditMode = false;

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

    // Handle window resize for responsive behavior
    window.addEventListener('resize', function() {
        // Auto-hide sidebar on mobile when resizing to larger screen
        if (window.innerWidth >= 769) {
            // If we're on desktop and sidebar was hidden (mobile state), reset it
            if (sidebar.style.display === 'none') {
                sidebar.style.display = 'flex';
            }
        }
    });

    // Make profileDropdown function global
    window.profileDropdown = profileDropdown;

    // Modal functionality
    var modal = document.getElementById("myModal");
    var btn = document.getElementById("modalbtn");
    var span = document.getElementsByClassName("close")[0];
    var cancelBtn = document.querySelector(".cancel-btn");

    // Modal functionality for deletion confirmation
    var deleteModal = document.getElementById("deleteConfirmationModal");
    var deleteCloseBtn = document.querySelector(".delete-close");
    var cancelDeleteBtn = document.getElementById("cancelDeleteBtn");
    var confirmDeleteBtn = document.getElementById("confirmDeleteBtn");

    // Success and Error modals
    var successModal = document.getElementById("successModal");
    var successCloseBtn = document.querySelector(".success-close");
    var successOkBtn = document.getElementById("successOkBtn");
    var errorModal = document.getElementById("errorModal");
    var errorCloseBtn = document.querySelector(".error-close");
    var errorOkBtn = document.getElementById("errorOkBtn");

    // Check for success/error messages on page load
    <?php if (!empty($success_message)): ?>
        showSuccessModal("<?php echo addslashes($success_message); ?>", "<?php echo addslashes($new_subject_code); ?>");
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
        showErrorModal("<?php echo addslashes($error_message); ?>");
    <?php endif; ?>

    // When the user clicks the button, open the modal
    btn.onclick = function() {
        modal.style.display = "block";
        document.body.style.overflow = "hidden";
    }

    // When the user clicks on <span> (x), close the modal
    span.onclick = function() {
        modal.style.display = "none";
        document.body.style.overflow = "auto";
        resetForm();
    }

    // When the user clicks on cancel button, close the modal
    cancelBtn.onclick = function() {
        modal.style.display = "none";
        document.body.style.overflow = "auto";
        resetForm();
    }

    // When user clicks the delete button, show the confirmation modal
    deleteBtn.addEventListener('click', function(e) {
        e.preventDefault();
        
        const checkedCheckboxes = document.querySelectorAll('.subject-checkbox:checked');
        
        if (checkedCheckboxes.length === 0) {
            showErrorModal('Please select at least one subject to delete.');
            return;
        }
        
        // Update the count in the modal
        document.getElementById('selectedCount').textContent = checkedCheckboxes.length;
        
        // Show the confirmation modal
        deleteModal.style.display = "block";
        document.body.style.overflow = "hidden";
    });

    // When user clicks the close button (X) on delete modal
    deleteCloseBtn.onclick = function() {
        deleteModal.style.display = "none";
        document.body.style.overflow = "auto";
    }

    // When user clicks cancel button on delete modal
    cancelDeleteBtn.onclick = function() {
        deleteModal.style.display = "none";
        document.body.style.overflow = "auto";
    }

    // When user confirms deletion
    confirmDeleteBtn.onclick = function() {
        // Submit the form
        deleteForm.submit();
    }

    // Success modal functionality
    successCloseBtn.onclick = function() {
        successModal.style.display = "none";
        document.body.style.overflow = "auto";
    }

    successOkBtn.onclick = function() {
        successModal.style.display = "none";
        document.body.style.overflow = "auto";
    }

    // Error modal functionality
    errorCloseBtn.onclick = function() {
        errorModal.style.display = "none";
        document.body.style.overflow = "auto";
    }

    errorOkBtn.onclick = function() {
        errorModal.style.display = "none";
        document.body.style.overflow = "auto";
    }

    // When the user clicks anywhere outside of the modals, close them
    window.onclick = function(event) {
        if (event.target == deleteModal) {
            deleteModal.style.display = "none";
            document.body.style.overflow = "auto";
        }
        if (event.target == modal) {
            modal.style.display = "none";
            document.body.style.overflow = "auto";
            resetForm();
        }
        if (event.target == successModal) {
            successModal.style.display = "none";
            document.body.style.overflow = "auto";
        }
        if (event.target == errorModal) {
            errorModal.style.display = "none";
            document.body.style.overflow = "auto";
        }
    }

    // Function to reset the form
    function resetForm() {
        document.getElementById('subjectForm').reset();
        document.getElementById('strand_field').classList.remove('show');
    }

    // Function to toggle strand field visibility
    window.toggleStrandField = function() {
        const gradeLevel = document.getElementById('grade_level_select').value;
        const strandField = document.getElementById('strand_field');
        
        if (gradeLevel === '11' || gradeLevel === '12') {
            strandField.classList.add('show');
        } else {
            strandField.classList.remove('show');
            // Clear strand selection when hidden
            document.getElementById('strand_select').value = '';
        }
    }

    // Function to show success modal
    function showSuccessModal(message, subjectCode = '') {
        document.getElementById('successTitle').textContent = 'Success!';
        document.getElementById('successMessage').textContent = message;
        
        if (subjectCode) {
            document.getElementById('subjectCode').textContent = subjectCode;
            document.getElementById('subjectCodeInfo').style.display = 'block';
        } else {
            document.getElementById('subjectCodeInfo').style.display = 'none';
        }
        
        successModal.style.display = "block";
        document.body.style.overflow = "hidden";
    }

    // Function to show error modal
    function showErrorModal(message) {
        document.getElementById('errorMessage').textContent = message;
        errorModal.style.display = "block";
        document.body.style.overflow = "hidden";
    }

    // Edit button functionality
    editBtn.addEventListener('click', function() {
        isEditMode = !isEditMode;
        
        if (isEditMode) {
            // Enter edit mode
            editBtn.textContent = 'Cancel';
            editBtn.style.backgroundColor = '#6c757d';
            editBtn.style.boxShadow = '0 4px 0 0 #545b62';
            
            // Show select all container
            selectAllContainer.classList.add('show');
            
            // Show checkboxes
            subjectCheckboxes.forEach(checkbox => {
                checkbox.classList.add('show');
            });
            
            // Disable subject links
            subjectLinks.forEach(link => {
                link.style.pointerEvents = 'none';
                link.style.opacity = '0.8';
            });
            
            // Hide delete button initially (no subjects selected yet)
            deleteBtn.classList.remove('show');
            deleteBtn.style.display = 'none';
        } else {
            // Exit edit mode
            editBtn.textContent = 'Edit';
            editBtn.style.backgroundColor = '';
            editBtn.style.boxShadow = '';
            
            // Hide delete button and select all container
            deleteBtn.classList.remove('show');
            deleteBtn.style.display = 'none';
            selectAllContainer.classList.remove('show');
            
            // Hide checkboxes
            subjectCheckboxes.forEach(checkbox => {
                checkbox.classList.remove('show');
                checkbox.checked = false;
            });
            
            // Enable subject links
            subjectLinks.forEach(link => {
                link.style.pointerEvents = '';
                link.style.opacity = '';
            });
            
            // Reset select all checkbox
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = false;
        }
    });

    // Select all checkbox functionality
    selectAllCheckbox.addEventListener('change', function() {
        subjectCheckboxes.forEach(checkbox => {
            checkbox.checked = selectAllCheckbox.checked;
        });
        // Show/hide delete button based on selection
        updateDeleteButtonVisibility();
    });

    // Individual checkbox behavior
    subjectCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updateSelectAllCheckbox();
            // Show/hide delete button based on selection
            updateDeleteButtonVisibility();
        });
    });

    // Update select all checkbox based on individual checkboxes
    function updateSelectAllCheckbox() {
        const checkboxes = document.querySelectorAll('.subject-checkbox');
        const checkedCheckboxes = document.querySelectorAll('.subject-checkbox:checked');
        
        if (checkedCheckboxes.length === 0) {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = false;
        } else if (checkedCheckboxes.length === checkboxes.length) {
            selectAllCheckbox.checked = true;
            selectAllCheckbox.indeterminate = false;
        } else {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = true;
        }
    }

    // Show/hide delete button based on whether subjects are selected
    function updateDeleteButtonVisibility() {
        const checkedCheckboxes = document.querySelectorAll('.subject-checkbox:checked');
        
        if (isEditMode && checkedCheckboxes.length > 0) {
            deleteBtn.classList.add('show');
            deleteBtn.style.display = 'block';
        } else {
            deleteBtn.classList.remove('show');
            deleteBtn.style.display = 'none';
        }
    }

    // Prevent clicks on deactivated subject buttons
    const deactivatedButtons = document.querySelectorAll('.subject-button-disabled');
    deactivatedButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            return false;
        });
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

    // Initialize delete button as hidden on page load
    deleteBtn.style.display = 'none';
    deleteBtn.classList.remove('show');
});

// Function to toggle strand field - already declared globally above
</script>
</body>
</html>