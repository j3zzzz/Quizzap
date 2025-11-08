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
        echo "<script>alert('Selected subjects and their quizzes have been deleted successfully.'); window.location.href='t_SubjectsList.php';</script>";
    } else {
        echo "<script>alert('No subjects selected for deletion.');</script>";
    }
}


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
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
        echo "<script>alert('A class with the same subject, grade level, and section already exists.');</script>";
    } else {
        $stmt = $conn->prepare("INSERT INTO subjects (subject_name, teacher_id, subject_code, grade_level, section, school_id, strand) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssss", $subject_name, $teacher_account_number, $subject_code, $grade_level, $section_display, $school_id, $strand);
        
        if ($stmt->execute()) {
            ?>
            <script type="text/javascript">
            console.log("Subject created successfully with code: $subject_code.");
            </script>
            <?php
        } else {
            echo "Error: " . $stmt->error;
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

        .subjects-container {
            padding: auto;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .subject-button {
            color: black;
            font-family: Fredoka;
            font-size: 24px;
            font-weight: 600;
            background-color: white;
            display: inline-block;
            border-radius: 6px;
            border: 2px solid #f8b500;
            text-decoration: none;
            text-align: left;
            padding: 12px 30px;
            width: 100%;
            margin-top: 2%;
            margin-bottom: 2%;
            margin-right: 1%;
            transition: 0.2s;
            box-shadow: 0 6px 0 0 rgba(0, 0, 0, 0.2);
            
        }

        .subject-button:hover{
            background-color: #F8B500;
            color: white;
        }

        .subject-button:active {
            background-color: #F8B500;
            box-shadow: 3px 4px 0 0 rgba(0, 0, 0, 0.3);
        }

        .subject-button span {
            font-size: 15px;
            font-family: Fredoka;
            color: #000000ff;
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
          background: #f8b500; 
          border-radius: 10px;
        }

        /* Handle on hover */
        ::-webkit-scrollbar-thumb:hover {
          background: #f8b500; 
        }

        .delete-btn {
            background-color: #ff4444;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            font-size: 1rem;
            cursor: pointer;
            margin-right: 1rem;
            font-family: Fredoka;
            box-shadow: 0 4px 0 0 #cc0000;
        }

        .delete-btn:hover {
            background-color: #cc0000;
        }

        .delete-btn:active {
            transform: translateY(1px);
            box-shadow: 0 2px 0 0 #cc0000;
        }

        .subject-item {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            gap: 10px;
        }

        .subject-checkbox {
            transform: scale(1.5);
            margin-right: 10px;
            accent-color: #f8b500;
        }

        .subject-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .subject-actions {
            display: flex;
            gap: 10px;
        }

        .select-all-container {
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }

        .select-all-checkbox {
            transform: scale(1.3);
            margin-right: 10px;
            accent-color: #f8b500;
        }

        .select-all-label {
            font-family: Fredoka;
            font-size: 16px;
            color: #555;
        }

        /* Disabled subject button */
        .subject-button-disabled {
            color: #999;
            font-family: Fredoka;
            font-size: 24px;
            font-weight: 600;
            background-color: #f5f5f5;
            display: inline-block;
            border-radius: 6px;
            border: 2px solid #ddd;
            text-decoration: none;
            text-align: left;
            padding: 12px 30px;
            width: 100%;
            margin-top: 2%;
            margin-bottom: 2%;
            margin-right: 1%;
            cursor: not-allowed;
            opacity: 0.6;
            position: relative;
            box-shadow: 0 4px 0 0 rgba(0, 0, 0, 0.1);
        }

        .subject-button-disabled:hover {
            background-color: #f5f5f5;
            color: #999;
            transform: none;
        }

        /* Deactivated badge inside the button */
        .deactivated-badge {
            display: inline-block;
            margin-top: 8px;
            padding: 4px 12px;
            background-color: #dc3545;
            color: white;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 500;
        }

        .deactivated-badge i {
            margin-right: 5px;
        }

        /* Tooltip styling */
        .subject-button-disabled[data-tooltip] {
            position: relative;
        }

        .subject-button-disabled[data-tooltip]::before {
            content: attr(data-tooltip);
            position: absolute;
            bottom: 110%;
            left: 50%;
            transform: translateX(-50%);
            background-color: #333;
            color: white;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 400;
            white-space: normal;
            width: 280px;
            text-align: center;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s, visibility 0.3s;
            z-index: 1000;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            line-height: 1.4;
        }

        .subject-button-disabled[data-tooltip]::after {
            content: "";
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            border: 8px solid transparent;
            border-top-color: #333;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s, visibility 0.3s;
            z-index: 1000;
        }

        .subject-button-disabled[data-tooltip]:hover::before,
        .subject-button-disabled[data-tooltip]:hover::after {
            opacity: 1;
            visibility: visible;
        }

        /* Prevent selection of deactivated subjects */
        .subject-item:has(.subject-button-disabled) .subject-checkbox {
            opacity: 0.3;
            pointer-events: none;
        }

        /* Mobile responsive tooltip */
        @media (max-width: 768px) {
            .subject-button-disabled[data-tooltip]::before {
                width: 220px;
                font-size: 12px;
                padding: 10px 12px;
            }
            
            .deactivated-badge {
                font-size: 12px;
                padding: 3px 10px;
            }
        }

        @media (max-width: 480px) {
            .subject-button-disabled[data-tooltip]::before {
                width: 180px;
                font-size: 11px;
                padding: 8px 10px;
                left: 10px;
                right: 10px;
                transform: none;
            }
            
            .subject-button-disabled[data-tooltip]::after {
                left: 20%;
            }
        }

        .btn {
            float: left;
            margin-top: 2%;
            margin-left: 7%;
            width: 130px;
            padding: 10px;
            border-radius: 10px;
            background-color: #FFEFE4;
            color: #f8b500;
            border: 2px solid #FFEFE4;
            font-family: Fredoka;
            box-shadow: 5px 6px 0 0 rgba(0, 0, 0, 0.2);
            cursor: pointer;
        }

        .btn:hover{
            background-color: #f8b500;
            color: #FFEFE4;
            border: 2px solid #f8b500;
        }

        #modalbtn {
            float: right;
            margin-top: -40%;
            margin-right: -90%;
            width: 120%;
            padding: 10px;
            border-radius: 10px;
            background-color: #F8B500;
            color: white;
            border: 2px solid #F8B500;
            font-family: Fredoka;
            font-weight: 500;
            font-size: 15px;
            box-shadow: 0 6px 0 0 #BC8900;
            cursor: pointer;
        }

        #modalbtn:hover {
            background-color: white;
            color: #F8B500;
        }

        #modalbtn:active {
            background-color: #F8B500;
            color: white;
             box-shadow: 3px 2px 3.5px -0.5px rgba(30, 29, 29, 0.69);
        }

        .add-sub {
            float: right;
            margin-left: -80px;
            margin-top: 65px;
        }

        /* The Modal (background) */
        .modal {
          display: none;
          position: fixed;
          z-index: 100;
          padding-top: 100px;
          left: 0;
          top: 0;
          width: 100%;
          height: 100%;
          overflow-x: hidden;
          overflow-y: scroll;
          background-color: rgb(0,0,0);
          background-color: rgba(0,0,0,0.4);
        }

        /* Modal Content */
        .modal-content {
          background-color: white;
          margin: auto;
          padding: 15px;
          border: none;
          border-radius: 8px;
          width: 50%;
          font-family: Fredoka;
          font-size: 25px;
          -webkit-animation-name: zoom;
          -webkit-animation-duration: 0.6s;
          animation-name: zoom;
          animation-duration: 0.6s;
        }

        @-webkit-keyframes zoom {
          from {-webkit-transform:scale(0)} 
          to {-webkit-transform:scale(1)}
        }

        @keyframes zoom {
          from {transform:scale(0)} 
          to {transform:scale(1)}
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 30px 10px 30px;
            border-bottom: 2px solid #f8b500;
        }

        .modal-body, .modal-dialog, .modal-content{
            background-color: white;
            border-radius: 20px;
            padding: 20px 30px;
        }

        .modal-content{
            padding: 30px;
        }

        .modal-dialog{
            margin-top: 13%;
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
            font-size: 16px;
            font-weight: 500;
            text-align: left;
        }
        
        .form-group input {
            width: 100%;
            padding: 15px 15px 15px 45px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 16px;
            font-family: 'Fredoka';
            transition: all 0.3s;
            background-color: #f9f9f9;
        }
        
        .form-group input:focus {
            border-color: #f8b500;
            background-color: #fff;
            box-shadow: 0 0 0 3px rgba(248, 181, 0, 0.2);
            outline: none;
        }

        .select-wrapper {
            position: relative;
        }
        
        .select-wrapper select {
            width: 100%;
            padding: 15px 15px 15px 45px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 16px;
            font-family: 'Fredoka';
            appearance: none;
            background-color: #f9f9f9;
            cursor: pointer;
        }
        
        .select-wrapper select:focus {
            border-color: #f8b500;
            background-color: #fff;
            box-shadow: 0 0 0 3px rgba(248, 181, 0, 0.2);
            outline: none;
        }
        
        .icon {
            position: absolute;
            left: 15px;
            top: 42px;
            color: #f8b500;
            font-size: 18px;
        }

        #grade_level_icon {
            top: 20px;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 30px;
        }   

        .cancel-btn {
            padding: 12px 25px;
            border: 2px solid #f8b500;
            border-radius: 8px;
            background-color: transparent;
            color: #f8b500;
            font-family: 'Fredoka';
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .cancel-btn:hover {
            background-color: #f8b500;
            color: white;
        }

        .submit-btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            background-color: #f8b500;
            color: white;
            font-family: 'Fredoka';
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 0 0 #d89e00;
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
        
        /* Responsive adjustments */
        @media (max-width: 767px) {
            .modal-content {
                width: 90%;
                padding: 15px;
            }
            
            .modal-header {
                padding: 15px 20px 10px 20px;
            }
            
            .modal-body {
                padding: 15px 20px;
            }
            
            .form-actions {
                flex-direction: column;
                gap: 10px;
            }
            
            .cancel-btn, .submit-btn {
                width: 100%;
            }
        }

        #class_name {
            width: 45%;
            font-size: 10px;
            margin-top: 3%;
            text-align: center;
        }

        label[for="class_name"] {
            font-family: 'Fredoka';
            font-weight: 500;
            font-size: 15px;
            color: black;
        }

        .addBtn{
            margin-top: 7%;
            margin-left: 5%;
            width: 50%;
            padding: 10px;
            border-radius: 15px;
            background-color: #F8B500;
            color: white;
            border: none;
            font-size: 15px;
            font-family: 'Fredoka';
            font-weight: 500;
            cursor: pointer;
            box-shadow: 0 6px 0 0 #BC8900;
        }

        /* The Close Button */
        .close {
            font-family: Fredoka;
            color: #f8b500;
            float: right;
            font-size: 28px;
            font-weight: bold;
            transition: 1.0s;
        }

        .close:hover,
        .close:focus {
          color:rgb(176, 132, 11);
          text-decoration: none;
          cursor: pointer;
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
            font-family: Fredoka;
            width: 60%;
            margin: auto;
            padding: 10px 3px;
            margin-top: 100px;
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

        /* Extra Large Screens (1400px and above) */
        @media (min-width: 1400px) {
            .content-header hr {
                width: calc(100vw - 300px);
            }
            
            .modal-content {
                width: 50%;
            }
        }

        /* Large Screens (1200px to 1399px) */
        @media (max-width: 1399px) {
            .content-header hr {
                width: calc(100vw - 300px);
            }
            
            .subject-cont {
                width: 70%;
            }
        }

        /* Medium Screens - Tablets (768px to 1199px) */
        @media (max-width: 1199px) {
            .content {
                padding: 1.5rem;
            }
            
            .content-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .content-header .actions {
                width: 100%;
                justify-content: flex-end;
            }
            
            .content-header hr {
                width: 100%;
            }
            
            .subject-cont {
                width: 80%;
                height: 350px;
            }
            
            .subject-button {
                width: 45%;
                font-size: 20px;
                padding: 10px 20px;
            }

            .subject-container {
                padding: 0;
                margin: 0;
            }
            
            .modal-content {
                width: 50%;
            }
            
            .add-sub {
                margin-right: 100px;
                margin-top: 50px;
            }
        }

        /* Small Tablets and Large Mobile (576px to 767px) */
        @media (max-width: 767px) {
            .sidebar {
                width: 80px;
                padding: 1rem 0.5rem;
            }
            
            .sidebar .menu a span {
                display: none;
            }
            
            .sidebar .menu a {
                justify-content: center;
                padding: 1rem 0;
            }
            
            .sidebar .menu a i {
                margin-right: 0;
                font-size: 1.2rem;
            }
            
            .sidebar .logo {
                justify-content: center;
                margin-left: 0;
            }
            
            .content {
                margin-left: 80px;
                padding: 1rem;
            }
            
            .content.expanded {
                margin-left: 80px;
            }
            
            .content-header h1 {
                font-size: 1.5rem;
            }
            
            .content-header .actions {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .content-header .actions button {
                width: 100%;
                margin-right: 0;
                margin-bottom: 0.5rem;
            }
            
            .subject-cont {
                width: 95%;
                height: 300px;
            }
            
            .subject-button {
                width: 90%;
                font-size: 18px;
                margin-right: 0;
                text-align: center;
            }
            
            .modal-content {
                width: 80%;
                margin-top: 20%;
                padding: 20px;
            }
            
            .modal-dialog {
                margin-top: 20%;
            }
            
            form label {
                font-size: 20px;
            }
            
            form input {
                font-size: 18px;
                padding: 12px;
            }
            
            .add-sub {
                float: none;
                margin: 20px auto;
                display: block;
                text-align: center;
            }
            
            .btn {
                width: 100px;
                margin-left: 2%;
            }
            
            #modalbtn {
                width: 100%;
                margin: 10px 0;
                float: none;
            }
            
            .no-quiz-con {
                width: 90%;
                margin-top: 50px;
            }
            
            .img-no-quiz {
                width: 100px;
                height: 90px;
            }
        }

        /* Extra Small Mobile (up to 575px) */
        @media (max-width: 575px) {
            .sidebar {
                width: 70px;
                padding: 1rem 0.25rem;
            }
            
            .sidebar .menu {
                margin-top: 30%;
            }
            
            .content {
                margin-left: 70px;
                padding: 0.5rem;
            }
            
            .content.expanded {
                margin-left: 70px;
            }
            
            .content-header {
                margin-bottom: 1rem;
            }
            
            .content-header h1 {
                font-size: 1.25rem;
            }
            
            .content-header p {
                font-size: 0.875rem;
            }
            
            .subject-cont {
                width: 100%;
                height: 250px;
                margin: 0;
            }
            
            .subject-button {
                width: 95%;
                font-size: 16px;
                padding: 8px 15px;
                margin: 2% auto;
                display: block;
            }
            
            .modal-content {
                width: 95%;
                margin-top: 30%;
                padding: 15px;
            }
            
            form label {
                font-size: 18px;
            }
            
            form input {
                font-size: 16px;
                padding: 10px;
            }
            
            .addBtn {
                width: 80%;
                margin-left: 10%;
            }
            
            .btn {
                width: 80px;
                padding: 8px;
                font-size: 14px;
            }
            
            .close {
                font-size: 24px;
            }
        }

        /* Landscape Mobile Orientation */
        @media (max-height: 500px) and (orientation: landscape) {
            .sidebar .menu {
                margin-top: 10%;
            }
            
            .modal-content {
                margin-top: 5%;
            }
            
            .modal-dialog {
                margin-top: 5%;
            }
            
            .subject-cont {
                height: 200px;
            }
        }

        /* High DPI / Retina Displays */
        @media (-webkit-min-device-pixel-ratio: 2), (min-resolution: 192dpi) {
            .subject-button {
                border-width: 1px;
            }
            
            .btn {
                border-width: 1px;
            }
        }
        
        .profile {
            position: relative;
            cursor: pointer;
        }

        .profile-pic {
            border: 2px solid #f8b500;
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
                <h1>Subjects</h1><br><br>
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
            
            <div style="margin-top: 1px; background-color:#f8b50052; padding: 15px; border-radius: 8px; width: 100%;">
            <div style="display: flex; align-items: center; gap: 4px;"><strong>School ID: </strong><p><?php echo htmlspecialchars($school_id); ?></p></div><br>
                <div><p style="font-size: small; font-style: italic;"><i class="fas fa-lightbulb" style="color: #f8b500; background-color: white; padding: 10px; border-radius: 50%;"></i> School ID is used to distinguish between different teachers and prevents mismatching of students. Please provide this school id to your students upon registration.</p></div>
            </div>
            <br><br>
            <center>

            <div class="subject-header">
                <div class="add-sub">
                    <button id="modalbtn">Add Subject</button>
                </div>
                
                <form method="post" action="" onsubmit="return confirm('Are you sure you want to delete the selected subjects? This will also delete all quizzes under these subjects.');">
                    <div class="subject-actions">
                        <button type="submit" name="delete_subjects" class="delete-btn">Delete Selected</button>
                    </div>
            </div>
            
            <div class="select-all-container">
                <input type="checkbox" id="select-all" class="select-all-checkbox">
                <label for="select-all" class="select-all-label">Select All</label>
            </div>
            
            <center>
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
                            echo '<input type="checkbox" name="selected_subjects[]" value="' . $row['subject_id'] . '" class="subject-checkbox">';

                            if ($isDeactivated) {
                                // Deactivated subject - disabled and with tooltip
                                echo "<div class='subject-button subject-button-disabled' data-tooltip='This class is deactivated. Please contact your administrator to request reactivation.'>" 
                                    . htmlspecialchars($row['subject_name']) 
                                    . "<br><span>" . htmlspecialchars($row['subject_code']) . " (" . htmlspecialchars($display_section) . ")</span>"
                                    . "<br><span class='deactivated-badge'><i class='fas fa-ban'></i> Deactivated</span></div>";
                            } else {
                                // Active subject - clickable
                                echo "<a class='subject-button' href='t_quizDash.php?subject_id=" . $row['subject_id'] . "'>" 
                                    . htmlspecialchars($row['subject_name']) 
                                    . "<br><span>" . htmlspecialchars($row['subject_code']) . " (" . htmlspecialchars($display_section) . ")</span></a>";
                            }   
                                echo '</div>';
                        }
                    } else {
                        echo "<div class='no-quiz-con'>";
                        echo "<p style='font-family: Fredoka; font-size: 22px; margin-top: 10%; color: #999;'>No subjects created yet.</p>";
                        echo "</div>";
                    }
                    ?>
                    </form>
                </div>

            </center>

            <div id="myModal" class="modal">
                <!-- Modal content -->
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 style="color: #f8b500; font-family: 'Fredoka'; font-size: 28px; font-weight: 600;">Create New Class</h2>
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
                                    <i class="fas fa-chevron-down icon" id="grade_level_icon"></i>
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
                                    <i class="fas fa-chevron-down icon" id="grade_level_icon"></i>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="section">Section</label>
                                <input type="text" name="section" placeholder="e.g. A, B, Einstein" required>
                                <i class="fas fa-users icon"></i>
                            </div>
                            
                            <div class="form-actions">
                                <button type="button" class="cancel-btn" onclick="modal.style.display='none'">Cancel</button>
                                <button type="submit" class="submit-btn">Create Subject</button>
                            </div>
                        </form>
                    </div>
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

        // Update select all checkbox to skip deactivated subjects
        const selectAllCheckbox = document.getElementById('select-all');
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                const checkboxes = document.querySelectorAll('.subject-checkbox:not([disabled])');
                checkboxes.forEach(checkbox => {
                    // Only select non-deactivated subjects
                    const subjectItem = checkbox.closest('.subject-item');
                    const isDeactivated = subjectItem.querySelector('.subject-button-disabled');
                    if (!isDeactivated) {
                        checkbox.checked = selectAllCheckbox.checked;
                    }
                });
            });
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
    });

    function profileDropdown() {
        document.getElementById("dropdown").classList.toggle("show");
    }

    // Function to toggle strand field visibility
    function toggleStrandField() {
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

    // Get the modal
    var modal = document.getElementById("myModal");

    // Get the button that opens the modal
    var btn = document.getElementById("modalbtn");

    // Get the <span> element that closes the modal
    var span = document.getElementsByClassName("close")[0];

    // When the user clicks the button, open the modal 
    btn.onclick = function() {
      modal.style.display = "block";
    }

    // When the user clicks on <span> (x), close the modal
    span.onclick = function() {
      modal.style.display = "none";
      // Reset form when modal is closed
      document.getElementById('subjectForm').reset();
      document.getElementById('strand_field').classList.remove('show');
    }

    // When the user clicks anywhere outside of the modal, close it
    window.onclick = function(event) {
        if (event.target == document.getElementById("myModal")) {
            document.getElementById("myModal").style.display = "none";
            document.querySelector(".modal-content").classList.remove("modal-open");
            // Reset form when modal is closed
            document.getElementById('subjectForm').reset();
            document.getElementById('strand_field').classList.remove('show');
        }
    }

    document.querySelectorAll('select').forEach(select => {
        select.addEventListener('focus', function() {
            this.nextElementSibling.style.transform = 'rotate(180deg)';
        });
        select.addEventListener('blur', function() {
            this.nextElementSibling.style.transform = 'rotate(0deg)';
        });
    });
</script>

</body>
</html>