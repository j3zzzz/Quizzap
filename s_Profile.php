<?php
session_start();
if (strpos($_SESSION['account_number'], 'S') !== 0) {
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

$account_number = $_SESSION['account_number'];
$fname = $_SESSION['fname'];

// Initialize variables
$name = "Unknown";
$lname = "Unknown";
$glevel = "Unknown";
$strand = "Unknown";
$section = "Unknown";
$profile_pic = 'default-profile.jpg';
$password_error = '';
$password_success = '';
$profile_updated = false;
$password_changed = false;
$form_error = false;

// Fetch student data
$sql = "SELECT fname, lname, account_number, glevel, strand, section, profile_pic FROM students WHERE account_number = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $account_number);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $name = $row['fname'];
    $lname = $row['lname'];
    $glevel = $row['glevel'];
    $strand = $row['strand'];
    $section = $row['section'];
    $profile_pic = $row['profile_pic'] ? $row['profile_pic'] : 'default-profile.jpg';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_fname = $_POST['fname'];
    $new_lname = $_POST['lname'];
    
    // Handle profile picture upload
    if ($_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $target_dir = "uploads/profiles/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
        $new_filename = "student_" . $account_number . "_" . time() . "." . $file_extension;
        $target_file = $target_dir . $new_filename;
        
        $check = getimagesize($_FILES['profile_pic']['tmp_name']);
        if ($check !== false) {
            if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $target_file)) {
                $profile_pic = $new_filename;
            }
        }
    }
    
    // Check if password change is attempted
    $password_change_attempted = !empty($_POST['current_password']) || !empty($_POST['new_password']) || !empty($_POST['confirm_password']);
    
    // If password change is attempted, validate it first
    if ($password_change_attempted) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validate password change
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $password_error = "Please fill all password fields to change your password.";
            $form_error = true;
        } elseif ($new_password !== $confirm_password) {
            $password_error = "New passwords do not match!";
            $form_error = true;
        } elseif (strlen($new_password) < 6) {
            $password_error = "New password must be at least 6 characters long!";
            $form_error = true;
        } else {
            // Get current password from database
            $password_sql = "SELECT password FROM students WHERE account_number = ?";
            $password_stmt = $conn->prepare($password_sql);
            $password_stmt->bind_param("s", $account_number);
            $password_stmt->execute();
            $password_result = $password_stmt->get_result();
            
            if ($password_result->num_rows > 0) {
                $password_row = $password_result->fetch_assoc();
                $hashed_password = $password_row['password'];
                
                // Verify current password
                if (password_verify($current_password, $hashed_password)) {
                    // Hash new password
                    $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    
                    // Update password in database
                    $update_password_sql = "UPDATE students SET password = ? WHERE account_number = ?";
                    $update_password_stmt = $conn->prepare($update_password_sql);
                    $update_password_stmt->bind_param("ss", $new_hashed_password, $account_number);
                    
                    if ($update_password_stmt->execute()) {
                        $password_changed = true;
                        $password_success = "Password changed successfully!";
                    } else {
                        $password_error = "Failed to update password. Please try again.";
                        $form_error = true;
                    }
                    
                    $update_password_stmt->close();
                } else {
                    $password_error = "Current password is incorrect!";
                    $form_error = true;
                }
            }
            
            $password_stmt->close();
        }
    }
    
    // Only update profile if no password error or if password change was successful
    if (!$form_error) {
        // Update database - only update editable fields
        $update_sql = "UPDATE students SET fname = ?, lname = ?, profile_pic = ? WHERE account_number = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ssss", $new_fname, $new_lname, $profile_pic, $account_number);
        
        if ($update_stmt->execute()) {
            $profile_updated = true;
            
            // Update session variables
            $_SESSION['fname'] = $new_fname;
            $name = $new_fname;
            $lname = $new_lname;
        }
        
        $update_stmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <title>Student Profile | RAWRIT</title>
    <style>
        :root {
            --primary: #f8b500;
            --primary-light: #ffc740;
            --primary-dark: #e0a100;
            --accent: #FFF3D6;
            --text: #333;
            --light-bg: #fff9ee;
            --card-bg: #fff;
            --error: #e74c3c;
            --success: #2ecc71;
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.12);
            --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Fredoka', sans-serif;
        }

        body {
            background-color: var(--light-bg);
            color: var(--text);
            line-height: 1.6;
            transition: background-color 0.3s, color 0.3s;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.3s ease;
        }
        
        .modal.show {
            display: flex;
        }
        
        .modal-content {
            background-color: white;
            border-radius: 16px;
            width: 90%;
            max-width: 500px;
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            transform: translateY(-50px);
            animation: slideDown 0.3s ease forwards;
        }
        
        @keyframes slideDown {
            to {
                transform: translateY(0);
            }
        }
        
        .modal-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header.success {
            background: linear-gradient(135deg, var(--success) 0%, #4cd964 100%);
        }
        
        .modal-header.error {
            background: linear-gradient(135deg, var(--error) 0%, #ff6b6b 100%);
        }
        
        .modal-title {
            color: white;
            font-size: 22px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            transition: var(--transition);
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }
        
        .modal-close:hover {
            background-color: rgba(255, 255, 255, 0.2);
            transform: rotate(90deg);
        }
        
        .modal-body {
            padding: 30px;
            text-align: center;
        }
        
        .modal-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        
        .modal-icon.success {
            color: var(--success);
        }
        
        .modal-icon.error {
            color: var(--error);
        }
        
        .modal-message {
            font-size: 18px;
            margin-bottom: 25px;
            line-height: 1.6;
        }
        
        .modal-actions {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 20px;
        }
        
        .modal-btn {
            padding: 12px 30px;
            border-radius: 10px;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            border: none;
            font-size: 16px;
            min-width: 120px;
        }
        
        .modal-btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: #000;
        }
        
        .modal-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(248, 181, 0, 0.3);
        }
        
        .modal-btn-secondary {
            background-color: #f1f1f1;
            color: var(--text);
            border: 1px solid #ddd;
        }
        
        .modal-btn-secondary:hover {
            background-color: #e1e1e1;
            transform: translateY(-2px);
        }
        
        /* Inline error message for password fields */
        .field-error {
            color: var(--error);
            font-size: 14px;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
            animation: fadeIn 0.3s ease;
        }
        
        .field-error i {
            font-size: 16px;
        }

        /* Dark Mode Styles */
        body.dark-mode {
            background-color: #1a1a1a;
            color: #e0e0e0;
        }

        body.dark-mode header {
            background: linear-gradient(135deg, #333 0%, #444 100%);
        }

        body.dark-mode .profile-container {
            background-color: #2d2d2d;
            color: #e0e0e0;
        }

        body.dark-mode .form-input {
            background-color: #333;
            color: #e0e0e0;
            border-color: #444;
        }

        body.dark-mode .form-input:focus {
            border-color: var(--primary);
            background-color: #3a3a3a;
        }

        body.dark-mode .form-input:disabled {
            background-color: #444;
            color: #b0b0b0;
        }

        body.dark-mode .btn-secondary {
            background-color: #444;
            color: #e0e0e0;
            border-color: #555;
        }

        body.dark-mode .btn-secondary:hover {
            background-color: #555;
        }

        body.dark-mode .detail-group {
            background-color: #333;
        }

        body.dark-mode .detail-value {
            color: #e0e0e0;
        }

        body.dark-mode nav a {
            color: rgba(255, 255, 255, 0.8);
        }

        body.dark-mode nav a:hover,
        body.dark-mode nav a.active {
            color: #fff;
        }

        body.dark-mode nav a::after {
            background-color: #fff;
        }

        body.dark-mode .logout-btn {
            background-color: rgba(255, 255, 255, 0.1);
            color: #fff;
        }

        body.dark-mode .logout-btn:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }

        /* Dark mode modal */
        body.dark-mode .modal-content {
            background-color: #2d2d2d;
            color: #e0e0e0;
        }
        
        body.dark-mode .modal-btn-secondary {
            background-color: #444;
            color: #e0e0e0;
            border-color: #555;
        }
        
        body.dark-mode .modal-btn-secondary:hover {
            background-color: #555;
        }

        /* Scrollbar Styles */
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
          background: #898981ff; 
          border-radius: 10px;
        }

        /* Handle on hover */
        ::-webkit-scrollbar-thumb:hover {
          background: #898981ff; 
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 5%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            box-shadow: var(--shadow-md);
            position: sticky;
            top: 0;
            z-index: 100;
            transition: var(--transition);
        }
        
        .logo img {
            height: 40px;
            transition: transform 0.3s;
        }

        .logo img:hover {
            transform: scale(1.05);
        }

        nav {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        nav a {
            color: rgba(0, 0, 0, 0.8);
            text-decoration: none;
            font-weight: 500;
            position: relative;
            padding: 5px 0;
            transition: var(--transition);
        }
        
        nav a::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background-color: #000;
            transition: var(--transition);
        }
        
        nav a:hover {
            color: #000;
        }
        
        nav a:hover::after {
            width: 100%;
        }

        nav a.active {
            color: #000;
            font-weight: 600;
        }
        
        nav a.active::after {
            width: 100%;
        }

        .logout-btn, .edit-btn {
            background-color: rgba(0, 0, 0, 0.1);
            color: #000;
            border: none;
            padding: 8px 15px;
            border-radius: 30px;
            cursor: pointer;
            font-weight: 500;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .logout-btn:hover, .edit-btn:hover {
            background-color: rgba(0, 0, 0, 0.2);
            transform: translateY(-2px);
        }
        
        main {
            padding: 40px 5%;
            min-height: calc(100vh - 70px);
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .profile-container {
            width: 50%;
            max-width: 900px;
            background: var(--card-bg);
            border-radius: 16px;
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            border: 1px solid rgba(248, 181, 0, 0.2);
            transition: var(--transition);
        }
        
        .profile-container:hover {
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
            transform: translateY(-5px);
        }

        .profile-header {
            height: 60px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 40px;
            position: relative;
            overflow: hidden;
        }
        
        .profile-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.2) 0%, rgba(255,255,255,0) 70%);
            transform: rotate(30deg);
        }

        .profile-title {
            color: #000;
            font-size: 25px;
            font-weight: 600;
            position: relative;
            z-index: 1;
        }
        
        .profile-subtitle {
            font-size: 14px;
            color: rgba(0, 0, 0, 0.7);
            margin-top: 5px;
            font-weight: 400;
        }

        .profile-content {
            padding: 25px;
        }
        
        .profile-preview {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 25px;
        }
        
        .profile-pic-container {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            overflow: hidden;
            border: 6px solid var(--accent);
            box-shadow: var(--shadow-lg);
            transition: var(--transition);
            position: relative;
        }
        
        .profile-pic-container:hover {
            transform: scale(1.05);
        }
        
        .profile-pic {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: var(--transition);
        }
        
        .profile-info {
            text-align: center;
            width: 100%;
        }
        
        .account-number {
            font-size: 18px;
            color: var(--primary-dark);
            font-weight: 600;
            margin-bottom: 5px;
            display: inline-block;
            padding: 5px 15px;
            background-color: var(--accent);
            border-radius: 20px;
        }
        
        .full-name {
            font-size: 28px;
            font-weight: 600;
            margin: 15px 0;
            position: relative;
            display: inline-block;
        }

        .student-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
            gap: 20px;
            width: 100%;
            margin-top: 30px;
        }
        
        .detail-group {
            background-color: var(--accent);
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            transition: var(--transition);
            box-shadow: var(--shadow-sm);
        }
        
        .detail-group:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-md);
        }
        
        .detail-label {
            font-size: 14px;
            color: var(--primary-dark);
            font-weight: 600;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .detail-value {
            font-size: 18px;
            color: var(--text);
            font-weight: 500;
        }
        
        .profile-form {
            display: grid;
            gap: 25px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .form-label {
            font-size: 15px;
            color: var(--primary);
            font-weight: 600;
        }
        
        .form-input {
            padding: 14px 18px;
            border: 1px solid #ddd;
            border-radius: 10px;
            font-size: 16px;
            transition: var(--transition);
            background-color: #f9f9f9;
        }
        
        .form-input:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(248, 181, 0, 0.1);
            background-color: #fff;
        }
        
        .form-input:disabled {
            background-color: #e9ecef;
            opacity: 1;
            cursor: not-allowed;
        }

        .profile-pic-edit {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .profile-pic-preview {
            width: 180px;
            height: 180px;
            border-radius: 50%;
            overflow: hidden;
            border: 6px solid var(--accent);
            box-shadow: var(--shadow-md);
            transition: var(--transition);
        }
        
        .profile-pic-preview:hover {
            transform: scale(1.05);
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 20px;
            margin-top: 40px;
        }
        
        .preview-actions {
            display: flex;
            justify-content: center;
            margin-top: 30px;
        }
        
        .btn {
            padding: 14px 30px;
            border-radius: 10px;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            border: none;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 16px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: #000;
            box-shadow: var(--shadow-md);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(248, 181, 0, 0.4);
            background: linear-gradient(135deg, var(--primary-light) 0%, var(--primary) 100%);
        }
        
        .btn-secondary {
            background-color: #f1f1f1;
            color: var(--text);
            border: 1px solid #ddd;
        }

        .btn-secondary:hover {
            background-color: #e1e1e1;
            transform: translateY(-3px);
            box-shadow: var(--shadow-sm);
        }
        
        .hidden {
            display: none;
        }
        
        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }

        .profile-container {
            animation: fadeIn 0.6s ease-out;
        }
        
        .profile-pic-container {
            animation: float 4s ease-in-out infinite;
        }

        /* Password Section Styles */
        .password-section {
            margin-top: 30px;
            padding-top: 25px;
            border-top: 2px solid #eee;
        }
        
        .password-section h3 {
            color: var(--primary);
            font-size: 18px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .password-section h3 i {
            font-size: 20px;
        }
        
        .password-input-container {
            position: relative;
        }
        
        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
            font-size: 16px;
            transition: var(--transition);
        }
        
        .toggle-password:hover {
            color: var(--primary);
        }
        
        .password-strength {
            height: 4px;
            margin-top: 5px;
            border-radius: 2px;
            background-color: #eee;
            overflow: hidden;
        }
        
        .password-strength-meter {
            height: 100%;
            width: 0%;
            transition: width 0.3s ease;
        }
        
        .strength-weak { background-color: #e74c3c; }
        .strength-medium { background-color: #f39c12; }
        .strength-strong { background-color: #2ecc71; }
        
        .password-hint {
            font-size: 13px;
            color: #666;
            margin-top: 5px;
        }
        
        .error-message {
            color: var(--error);
            background-color: rgba(231, 76, 60, 0.1);
            padding: 10px 15px;
            border-radius: 8px;
            margin-top: 10px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: fadeIn 0.3s ease;
        }
        
        .error-message i {
            font-size: 16px;
        }
        
        /* Enhanced Responsive Styles */
        
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

        /* Enhanced Mobile Responsive Styles */
        @media (max-width: 1200px) {
            .profile-container {
                width: 70%;
            }
        }

        @media (max-width: 992px) {
            .profile-container {
                width: 80%;
            }
            
            .student-details {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        /* 768px Responsive Styles */
        @media (max-width: 768px) {
            header {
                padding: 15px 20px;
                flex-wrap: wrap;
            }
            
            nav {
                gap: 12px;
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .profile-container {
                width: 90%;
            }
            
            .profile-content {
                padding: 25px;
            }
            
            .profile-header {
                padding: 0 25px;
                height: auto;
                min-height: 80px;
                flex-direction: column;
                justify-content: center;
                text-align: center;
            }
            
            .profile-title {
                font-size: 24px;
            }
            
            .profile-pic-container, .profile-pic-preview {
                width: 150px;
                height: 150px;
            }
            
            .full-name {
                font-size: 24px;
            }
            
            .student-details {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .btn {
                padding: 12px 20px;
                font-size: 14px;
            }
            
            .modal-content {
                width: 95%;
            }
        }
        
        /* 576px Responsive Styles */
        @media (max-width: 576px) {
            header {
                padding: 12px 15px;
                flex-direction: column;
                gap: 15px;
            }
            
            nav {
                gap: 8px;
                width: 100%;
                justify-content: space-between;
            }
            
            nav a {
                font-size: 14px;
                padding: 8px 0;
                flex: 1;
                text-align: center;
                min-height: 44px;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
            }
            
            .logout-btn {
                padding: 6px 12px;
                font-size: 14px;
            }
            
            .logo img {
                height: 35px;
            }
            
            main {
                padding: 20px 15px;
            }
            
            .profile-container {
                width: 100%;
            }
            
            .profile-header {
                padding: 20px;
                height: auto;
                gap: 10px;
            }
            
            .profile-title {
                font-size: 22px;
            }
            
            .profile-content {
                padding: 20px;
            }
            
            .profile-pic-container, .profile-pic-preview {
                width: 120px;
                height: 120px;
            }
            
            .full-name {
                font-size: 22px;
            }
            
            .account-number {
                font-size: 16px;
            }
            
            .student-details {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .detail-group {
                padding: 15px;
            }
            
            .detail-label {
                font-size: 13px;
            }
            
            .detail-value {
                font-size: 16px;
            }
            
            .form-actions {
                flex-direction: column;
                gap: 10px;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .dark-mode-toggle {
                bottom: 15px;
                right: 15px;
                width: 50px;
                height: 50px;
            }
            
            .modal-body {
                padding: 20px;
            }
            
            .modal-actions {
                flex-direction: column;
            }
            
            .modal-btn {
                width: 100%;
            }
        }

        /* 480px Responsive Styles */
        @media (max-width: 480px) {
            header {
                padding: 10px;
            }
            
            nav {
                gap: 6px;
            }
            
            nav a {
                font-size: 13px;
                padding: 8px;
                min-height: 44px;
                min-width: 44px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .logout-btn {
                padding: 5px 10px;
                font-size: 13px;
            }
            
            .logo img {
                height: 30px;
            }
            
            main {
                padding: 15px 10px;
            }
            
            .profile-header {
                padding: 15px;
            }
            
            .profile-title {
                font-size: 20px;
            }
            
            .profile-content {
                padding: 15px;
            }
            
            .profile-pic-container, .profile-pic-preview {
                width: 100px;
                height: 100px;
            }
            
            .full-name {
                font-size: 20px;
            }
            
            .account-number {
                font-size: 14px;
                padding: 4px 12px;
            }
            
            .student-details {
                gap: 12px;
            }
            
            .detail-group {
                padding: 12px;
            }
            
            .detail-label {
                font-size: 12px;
            }
            
            .detail-value {
                font-size: 15px;
            }
            
            .dark-mode-toggle {
                bottom: 10px;
                right: 10px;
                width: 45px;
                height: 45px;
                font-size: 1.1rem;
            }
        }

        /* 375px Responsive Styles */
        @media (max-width: 375px) {
            nav a {
                font-size: 12px;
                padding: 6px;
            }
            
            .logout-btn {
                padding: 4px 8px;
                font-size: 12px;
            }
            
            .profile-title {
                font-size: 18px;
            }
            
            .full-name {
                font-size: 18px;
            }
            
            .account-number {
                font-size: 13px;
            }
            
            .profile-pic-container, .profile-pic-preview {
                width: 90px;
                height: 90px;
            }
            
            .student-details {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            
            .detail-group {
                padding: 10px;
            }
            
            .detail-label {
                font-size: 11px;
            }
            
            .detail-value {
                font-size: 14px;
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
    <header>                   
        <div class="logo">
            <img src="img/logo4.png" width="110px" height="80px" class="logo-img" alt="RAWRIT Logo">
        </div>
        <nav>
            <a href="s_Home.php" title="Home">
                <i class="fa-solid fa-house"></i>
                <span>Home</span>
            </a>
            <a href="s_Classes.php" title="Classes">
                <i class="fa-regular fa-address-book"></i>
                <span>Classes</span>
            </a>
            <a href="studQuizzes.php" title="Quizzes">
                <i class="fa-solid fa-file-lines"></i>
                <span>Quizzes</span>
            </a>
            <a class="active" href="s_Profile.php" title="Profile">
                <i class="fa-solid fa-user"></i>
                <span>Profile</span>
            </a>
            <form action="logout.php" method="POST">
                <button type="submit" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
                </button>
            </form>
        </nav>
    </header>

    <!-- Dark Mode Toggle Button -->
    <button class="dark-mode-toggle" id="darkModeToggle">
        <i class="fas fa-moon"></i>
    </button>

    <main>
        <div class="profile-container">
            <div class="profile-header">
                <div>
                    <h1 class="profile-title">Student Profile</h1>
                </div>
            </div>
            <div class="profile-content">
                <!-- Preview Mode -->
                <div id="previewMode" class="profile-preview">
                    <div class="profile-pic-container">
                        <img src="uploads/profiles/<?php echo htmlspecialchars($profile_pic); ?>" alt="Profile Picture" class="profile-pic" onerror="this.src='uploads/profiles/default-profile.jpg'">
                    </div>
                    
                    <div class="profile-info">
                        <div class="account-number"><?php echo htmlspecialchars($account_number); ?></div>
                        <h2 class="full-name"><?php echo htmlspecialchars($name . ' ' . $lname); ?></h2>
                        
                        <div class="student-details">
                            <div class="detail-group">
                                <div class="detail-label">Grade Level</div>
                                <div class="detail-value"><?php echo htmlspecialchars($glevel); ?></div>
                            </div>
                            <div class="detail-group">
                                <div class="detail-label">Strand</div>
                                <div class="detail-value"><?php echo htmlspecialchars($strand); ?></div>
                            </div>
                            <div class="detail-group">
                                <div class="detail-label">Section</div>
                                <div class="detail-value"><?php echo htmlspecialchars($section); ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="preview-actions">
                        <button id="startEdit" class="btn btn-primary">
                            <i class="fas fa-edit"></i> Edit Profile
                        </button>
                    </div>
                </div>
                
                <!-- Edit Mode -->
                <form id="editMode" method="POST" enctype="multipart/form-data" class="profile-form hidden">
                    <div class="profile-pic-edit">
                        <div class="profile-pic-preview">
                            <img id="profilePicPreview" src="uploads/profiles/<?php echo htmlspecialchars($profile_pic); ?>" alt="Profile Picture" class="profile-pic" onerror="this.src='uploads/profiles/default-profile.jpg'">
                        </div>
                        <input type="file" id="profile_pic" name="profile_pic" accept="image/*" style="display: none;">
                        <button type="button" id="changeProfilePic" class="btn btn-secondary">
                            <i class="fas fa-camera"></i> Change Photo
                        </button>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Account Number</label>
                        <input type="text" class="form-input" value="<?php echo htmlspecialchars($account_number); ?>" disabled>
                    </div>

                    <div class="form-group">
                        <label for="fname" class="form-label">First Name</label>
                        <input type="text" id="fname" name="fname" class="form-input" value="<?php echo htmlspecialchars($name); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="lname" class="form-label">Last Name</label>
                        <input type="text" id="lname" name="lname" class="form-input" value="<?php echo htmlspecialchars($lname); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="glevel" class="form-label">Grade Level</label>
                        <input type="text" id="glevel" name="glevel" class="form-input" value="<?php echo htmlspecialchars($glevel); ?>" disabled>
                    </div>
                    
                    <div class="form-group">
                        <label for="strand" class="form-label">Strand</label>
                        <input type="text" id="strand" name="strand" class="form-input" value="<?php echo htmlspecialchars($strand); ?>" disabled>
                    </div>

                    <div class="form-group">
                        <label for="section" class="form-label">Section</label>
                        <input type="text" id="section" name="section" class="form-input" value="<?php echo htmlspecialchars($section); ?>" disabled>
                    </div>
                    
                    <!-- Password Change Section -->
                    <div class="password-section">
                        <h3><i class="fas fa-lock"></i> Change Password</h3>
                        
                        <div class="form-group">
                            <label for="current_password" class="form-label">Current Password</label>
                            <div class="password-input-container">
                                <input type="password" id="current_password" name="current_password" class="form-input" placeholder="Enter current password">
                                <button type="button" class="toggle-password" data-target="current_password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <?php if ($password_error && strpos($password_error, 'Current password') !== false): ?>
                            <div class="field-error">
                                <i class="fas fa-exclamation-circle"></i>
                                <span><?php echo htmlspecialchars($password_error); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password" class="form-label">New Password</label>
                            <div class="password-input-container">
                                <input type="password" id="new_password" name="new_password" class="form-input" placeholder="Enter new password (min. 6 characters)">
                                <button type="button" class="toggle-password" data-target="new_password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="password-strength">
                                <div id="passwordStrengthMeter" class="password-strength-meter"></div>
                            </div>
                            <div class="password-hint">Password strength: <span id="passwordStrengthText">None</span></div>
                            <?php if ($password_error && (strpos($password_error, '6 characters') !== false || strpos($password_error, 'fill all password fields') !== false)): ?>
                            <div class="field-error">
                                <i class="fas fa-exclamation-circle"></i>
                                <span><?php echo htmlspecialchars($password_error); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <div class="password-input-container">
                                <input type="password" id="confirm_password" name="confirm_password" class="form-input" placeholder="Re-enter new password">
                                <button type="button" class="toggle-password" data-target="confirm_password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <?php if ($password_error && strpos($password_error, 'do not match') !== false): ?>
                            <div class="field-error">
                                <i class="fas fa-exclamation-circle"></i>
                                <span><?php echo htmlspecialchars($password_error); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" id="cancelEdit" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save All Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>
    
    <!-- Success Modal -->
    <div id="successModal" class="modal">
        <div class="modal-content">
            <div class="modal-header success">
                <h3 class="modal-title">
                    <i class="fas fa-check-circle"></i> Success!
                </h3>
                <button type="button" class="modal-close" id="closeSuccessModal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="modal-icon success">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="modal-message" id="successMessageText">
                    Profile updated successfully!
                </div>
                <div class="modal-actions">
                    <button type="button" class="modal-btn modal-btn-primary" id="okSuccessBtn">OK</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Error Modal -->
    <div id="errorModal" class="modal">
        <div class="modal-content">
            <div class="modal-header error">
                <h3 class="modal-title">
                    <i class="fas fa-exclamation-circle"></i> Error!
                </h3>
                <button type="button" class="modal-close" id="closeErrorModal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="modal-icon error">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <div class="modal-message" id="errorMessageText">
                    <?php echo htmlspecialchars($password_error); ?>
                </div>
                <div class="modal-actions">
                    <button type="button" class="modal-btn modal-btn-primary" id="okErrorBtn">OK</button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const startEdit = document.getElementById('startEdit');
            const cancelEdit = document.getElementById('cancelEdit');
            const previewMode = document.getElementById('previewMode');
            const editMode = document.getElementById('editMode');
            const changeProfilePic = document.getElementById('changeProfilePic');
            const profilePicInput = document.getElementById('profile_pic');
            const profilePicPreview = document.getElementById('profilePicPreview');
            
            // Modal elements
            const successModal = document.getElementById('successModal');
            const errorModal = document.getElementById('errorModal');
            const closeSuccessModal = document.getElementById('closeSuccessModal');
            const closeErrorModal = document.getElementById('closeErrorModal');
            const okSuccessBtn = document.getElementById('okSuccessBtn');
            const okErrorBtn = document.getElementById('okErrorBtn');
            const successMessageText = document.getElementById('successMessageText');
            const errorMessageText = document.getElementById('errorMessageText');
            
            // Dark Mode Toggle Functionality
            const darkModeToggle = document.getElementById('darkModeToggle');
            const body = document.body;

            // Check for saved dark mode preference
            const isDarkMode = localStorage.getItem('darkMode') === 'true';

            // Apply dark mode on page load if enabled
            if (isDarkMode) {
                document.body.classList.add('dark-mode');
                darkModeToggle.innerHTML = '<i class="fas fa-sun"></i>';
            }
            
            // Toggle between preview and edit modes
            function toggleEditMode() {
                previewMode.classList.toggle('hidden');
                editMode.classList.toggle('hidden');
                
                // Smooth scroll to top when editing
                if (!editMode.classList.contains('hidden')) {
                    window.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                }
            }
            
            // Event listeners
            startEdit.addEventListener('click', toggleEditMode);
            cancelEdit.addEventListener('click', function() {
                // Clear password fields
                document.getElementById('current_password').value = '';
                document.getElementById('new_password').value = '';
                document.getElementById('confirm_password').value = '';
                
                // Reset strength meter
                if (strengthMeter) {
                    strengthMeter.style.width = '0%';
                }
                if (strengthText) {
                    strengthText.textContent = 'None';
                }
                
                toggleEditMode();
            });
            
            // Profile picture change handler
            changeProfilePic.addEventListener('click', function() {
                profilePicInput.click();
            });
            
            profilePicInput.addEventListener('change', function(e) {
                if (e.target.files && e.target.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function(event) {
                        profilePicPreview.src = event.target.result;
                    };
                    reader.readAsDataURL(e.target.files[0]);
                }
            });
            
            // If form was submitted with errors, show edit mode
            if (window.location.search.includes('error') || '<?php echo $form_error ? 'true' : 'false'; ?>' === 'true') {
                toggleEditMode();
            }
            
            // Add hover effect to profile picture in preview mode
            const profilePicContainer = document.querySelector('.profile-pic-container');
            if (profilePicContainer) {
                profilePicContainer.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.05) rotate(5deg)';
                });
                
                profilePicContainer.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1) rotate(0)';
                });
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

            // Password toggle functionality
            const toggleButtons = document.querySelectorAll('.toggle-password');
            toggleButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const targetId = this.getAttribute('data-target');
                    const input = document.getElementById(targetId);
                    const icon = this.querySelector('i');
                    
                    if (input.type === 'password') {
                        input.type = 'text';
                        icon.classList.remove('fa-eye');
                        icon.classList.add('fa-eye-slash');
                    } else {
                        input.type = 'password';
                        icon.classList.remove('fa-eye-slash');
                        icon.classList.add('fa-eye');
                    }
                });
            });
            
            // Password strength checker
            const newPasswordInput = document.getElementById('new_password');
            const strengthMeter = document.getElementById('passwordStrengthMeter');
            const strengthText = document.getElementById('passwordStrengthText');
            
            if (newPasswordInput && strengthMeter && strengthText) {
                newPasswordInput.addEventListener('input', function() {
                    const password = this.value;
                    let strength = 0;
                    
                    // Length check
                    if (password.length >= 6) strength += 25;
                    if (password.length >= 8) strength += 25;
                    
                    // Character variety checks
                    if (/[a-z]/.test(password)) strength += 15;
                    if (/[A-Z]/.test(password)) strength += 15;
                    if (/[0-9]/.test(password)) strength += 10;
                    if (/[^a-zA-Z0-9]/.test(password)) strength += 10;
                    
                    // Cap at 100
                    strength = Math.min(strength, 100);
                    
                    // Update meter
                    strengthMeter.style.width = strength + '%';
                    
                    // Update text and color
                    if (strength < 30) {
                        strengthMeter.className = 'password-strength-meter strength-weak';
                        strengthText.textContent = 'Weak';
                    } else if (strength < 70) {
                        strengthMeter.className = 'password-strength-meter strength-medium';
                        strengthText.textContent = 'Medium';
                    } else {
                        strengthMeter.className = 'password-strength-meter strength-strong';
                        strengthText.textContent = 'Strong';
                    }
                    
                    // Reset if empty
                    if (password.length === 0) {
                        strengthMeter.style.width = '0%';
                        strengthText.textContent = 'None';
                    }
                });
            }
            
            // Modal functionality
            function showSuccessModal(message) {
                if (message) {
                    successMessageText.textContent = message;
                }
                successModal.classList.add('show');
                document.body.style.overflow = 'hidden';
            }
            
            function showErrorModal(message) {
                if (message) {
                    errorMessageText.textContent = message;
                }
                errorModal.classList.add('show');
                document.body.style.overflow = 'hidden';
            }
            
            function hideModals() {
                successModal.classList.remove('show');
                errorModal.classList.remove('show');
                document.body.style.overflow = 'auto';
            }
            
            // Event listeners for modals
            closeSuccessModal.addEventListener('click', hideModals);
            closeErrorModal.addEventListener('click', hideModals);
            okSuccessBtn.addEventListener('click', hideModals);
            okErrorBtn.addEventListener('click', hideModals);
            
            // Close modal when clicking outside
            window.addEventListener('click', function(event) {
                if (event.target === successModal) {
                    hideModals();
                }
                if (event.target === errorModal) {
                    hideModals();
                }
            });
            
            // Close modal with Escape key
            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape') {
                    hideModals();
                }
            });
            
            // Show modals based on PHP response
            <?php if ($profile_updated || $password_changed): ?>
                <?php
                $message = "Profile updated successfully!";
                if ($password_changed) {
                    $message = "Profile and password updated successfully!";
                } elseif ($profile_updated) {
                    $message = "Profile updated successfully!";
                }
                ?>
                setTimeout(function() {
                    showSuccessModal("<?php echo $message; ?>");
                }, 300);
            <?php endif; ?>
            
            <?php if ($form_error && $password_error): ?>
                setTimeout(function() {
                    showErrorModal("<?php echo htmlspecialchars($password_error); ?>");
                }, 300);
            <?php endif; ?>
            
            // Form validation for password change
            const editForm = document.getElementById('editMode');
            if (editForm) {
                editForm.addEventListener('submit', function(e) {
                    const currentPassword = document.getElementById('current_password').value;
                    const newPassword = document.getElementById('new_password').value;
                    const confirmPassword = document.getElementById('confirm_password').value;
                    
                    // Check if any password field is filled
                    if (currentPassword || newPassword || confirmPassword) {
                        // All three fields must be filled
                        if (!currentPassword || !newPassword || !confirmPassword) {
                            showErrorModal('Please fill all password fields to change your password.');
                            e.preventDefault();
                            return;
                        }
                        
                        // Check minimum length
                        if (newPassword.length < 6) {
                            showErrorModal('New password must be at least 6 characters long.');
                            e.preventDefault();
                            return;
                        }
                        
                        // Check if passwords match
                        if (newPassword !== confirmPassword) {
                            showErrorModal('New passwords do not match.');
                            e.preventDefault();
                            return;
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>