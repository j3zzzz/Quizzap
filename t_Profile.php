<?php
session_start();

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "rawrit";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get user data
$account_number = $_SESSION['account_number'];
$fname = $_SESSION['fname'];

// Fetch teacher data
$sql = "SELECT fname, lname, account_number, school_id, profile_pic FROM teachers WHERE account_number = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $account_number);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $name = $row['fname'];
    $lname = $row['lname'];
    $school_id = $row['school_id'];
    $profile_pic = $row['profile_pic'] ? $row['profile_pic'] : 'default-profile.jpg';
} else {
    $name = "Unknown";
    $lname = "Unknown";
    $profile_pic = 'default-profile.jpg';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_fname = $_POST['fname'];
    $new_lname = $_POST['lname'];
    
    // Handle profile picture removal
    if (isset($_POST['remove_profile_pic'])) {
        // Delete the old profile picture if it's not the default
        if ($profile_pic !== 'default-profile.jpg' && file_exists("uploads/profiles/$profile_pic")) {
            unlink("uploads/profiles/$profile_pic");
        }
        $profile_pic = 'default-profile.jpg';
    }
    // Handle profile picture upload
    elseif ($_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        // Delete the old profile picture if it's not the default
        if ($profile_pic !== 'default-profile.jpg' && file_exists("uploads/profiles/$profile_pic")) {
            unlink("uploads/profiles/$profile_pic");
        }
        
        $target_dir = "uploads/profiles/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
        $new_filename = "teacher_" . $account_number . "_" . time() . "." . $file_extension;
        $target_file = $target_dir . $new_filename;
        
        // Check if image file is a actual image
        $check = getimagesize($_FILES['profile_pic']['tmp_name']);
        if ($check !== false) {
            if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $target_file)) {
                $profile_pic = $new_filename;
            }
        }
    }
    
    // Update database
    $update_sql = "UPDATE teachers SET fname = ?, lname = ?, profile_pic = ? WHERE account_number = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("ssss", $new_fname, $new_lname, $profile_pic, $account_number);
    $update_stmt->execute();
    
    // Update session variables
    $_SESSION['fname'] = $new_fname;
    $name = $new_fname;
    $lname = $new_lname;
    
    $update_stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Profile | RAWRIT</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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
            font-family: Fredoka;
        }

        body {
            background-color: var(--light-bg);
            color: var(--text);
            line-height: 1.6;
            transition: background-color 0.3s, color 0.3s;
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
          background: #CF5300; 
          border-radius: 10px;
        }

        /* Handle on hover */
        ::-webkit-scrollbar-thumb:hover {
          background: #A34404; 
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
            height: 80px;
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
            font-size: 24px;
            font-weight: 600;
            position: relative;
            z-index: 1;
            text-shadow: 0 1px 2px rgba(255, 255, 255, 0.3);
        }

        .profile-content {
            padding: 30px;
        }
        
        .profile-preview {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
        }
        
        .profile-pic-container {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            overflow: hidden;
            border: 5px solid var(--accent);
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(248, 181, 0, 0.3);
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
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 20px;
            position: relative;
            display: inline-block;
        }
        
        .profile-form {
            display: grid;
            gap: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .form-label {
            font-size: 14px;
            color: var(--primary);
            font-weight: 600;
        }
        
        .form-input {
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
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
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .profile-pic-preview {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            overflow: hidden;
            border: 5px solid var(--accent);
            transition: var(--transition);
        }
        
        .profile-pic-preview:hover {
            transform: scale(1.05);
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 30px;
        }
        
        .preview-actions {
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }
        
        .btn {
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            border: none;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 16px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: #000;
            box-shadow: var(--shadow-md);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(248, 181, 0, 0.4);
            background: linear-gradient(135deg, var(--primary-light) 0%, var(--primary) 100%);
        }
        
        .btn-secondary {
            background-color: #f1f1f1;
            color: var(--text);
            border: 1px solid #ddd;
        }

        .btn-secondary:hover {
            background-color: #e1e1e1;
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }
        
        .hidden {
            display: none;
        }
        
        /* Success message */
        .success-message {
            position: fixed;
            top: 100px;
            right: 30px;
            background-color: var(--success);
            color: white;
            padding: 15px 25px;
            border-radius: 8px;
            box-shadow: var(--shadow-lg);
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 1000;
            transform: translateX(150%);
            transition: transform 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }
        
        .success-message.show {
            transform: translateX(0);
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

        .remove-photo-btn {
            background-color: #ff6b6b;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            margin-top: 10px;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: var(--transition);
        }
        
        .remove-photo-btn:hover {
            background-color: #ff5252;
            transform: translateY(-2px);
        }
        
        .remove-photo-btn i {
            font-size: 12px;
        }

        /* NEW: Dark Mode Toggle Button */
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

        /* Enhanced Mobile Responsive Styles - Added from s_Profile.php */
        @media (max-width: 1200px) {
            .profile-container {
                width: 70%;
            }
        }

        @media (max-width: 992px) {
            .profile-container {
                width: 80%;
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
            
            .btn {
                padding: 12px 20px;
                font-size: 14px;
            }
            
            /* Add icons to navigation */
            nav a {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 4px;
                font-size: 14px;
                padding: 8px 0;
                min-height: 44px;
                justify-content: center;
            }
            
            nav a i {
                font-size: 16px;
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
            
            .form-group {
                gap: 8px;
            }
            
            .form-label {
                font-size: 14px;
            }
            
            .form-input {
                padding: 12px 15px;
                font-size: 14px;
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
        <div class="logo"> <img src="img/logo4.png" width="110px" height="80px" class="logo-img"></div>
        <nav>
            <a href="t_Home.php">
                <i class="fas fa-home"></i>
                <span>Home</span>
            </a>
            <a href="t_Students.php">
                <i class="fas fa-users"></i>
                <span>Students</span>
            </a>
            <a href="t_SubjectsList.php">
                <i class="fas fa-book"></i>
                <span>Subjects</span>
            </a>
            <a class="active" href="t_Profile.php">
                <i class="fas fa-user"></i>
                <span>Profile</span>
            </a>
            <form action="logout.php" method="POST">
                <button type="submit" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
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
                <h1 class="profile-title">Personal Information</h1>
            </div>
            <div class="profile-content">
                <!-- Preview Mode -->
                <div id="previewMode" class="profile-preview">
                    <div class="profile-pic-container">
                        <img src="uploads/profiles/<?php echo htmlspecialchars($profile_pic); ?>" alt="Profile Picture" class="profile-pic" onerror="this.src='uploads/profiles/default-profile.jpg'">
                    </div>
                    
                    <div class="profile-info">
                        <div class="account-number">Account #: <?php echo htmlspecialchars($account_number); ?></div>
                        <div class="account-number">School ID: <?php echo htmlspecialchars($school_id); ?></div>
                        <div class="full-name"><?php echo htmlspecialchars($name . ' ' . $lname); ?></div>
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
                        <?php if ($profile_pic !== 'default-profile.jpg'): ?>
                        <button type="submit" name="remove_profile_pic" class="remove-photo-btn">
                            <i class="fas fa-trash-alt"></i> Remove Photo
                        </button>
                        <?php endif; ?>
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
                    
                    <div class="form-actions">
                        <button type="button" id="cancelEdit" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
        <div id="successMessage" class="success-message">
            <i class="fas fa-check-circle"></i>
            <span>Profile updated successfully!</span>
        </div>
        <?php endif; ?>
    </main>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const startEdit = document.getElementById('startEdit');
            const cancelEdit = document.getElementById('cancelEdit');
            const previewMode = document.getElementById('previewMode');
            const editMode = document.getElementById('editMode');
            const changeProfilePic = document.getElementById('changeProfilePic');
            const profilePicInput = document.getElementById('profile_pic');
            const profilePicPreview = document.getElementById('profilePicPreview');
            const successMessage = document.getElementById('successMessage');
            
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
            cancelEdit.addEventListener('click', toggleEditMode);
            
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
            
            // Show success message if form was submitted
            if (successMessage) {
                setTimeout(() => {
                    successMessage.classList.add('show');
                    
                    // Hide after 5 seconds
                    setTimeout(() => {
                        successMessage.classList.remove('show');
                    }, 5000);
                }, 300);
            }
            
            // If form was submitted with errors, show edit mode
            if (window.location.search.includes('error')) {
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

            // Update the preview image when changing or removing profile picture
            const form = document.getElementById('editMode');
            form.addEventListener('submit', function() {
                // This ensures the preview updates after form submission
                setTimeout(() => {
                    const previewImg = document.querySelector('#previewMode .profile-pic');
                    previewImg.src = 'uploads/profiles/' + '<?php echo $profile_pic; ?>?' + new Date().getTime();
                }, 100);
            });

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
        });
    </script>
</body>
</html>