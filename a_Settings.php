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
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update Profile
    if (isset($_POST['fname'])) {
        $fname = $conn->real_escape_string($_POST['fname']);
        $lname = $conn->real_escape_string($_POST['lname']);
        $email = $conn->real_escape_string($_POST['email']);
        
        // Handle profile picture upload
        $profile_pic = $_SESSION['profile_pic'] ?? 'default-profile.jpg';
        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/profiles/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $fileExt = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
            $fileName = 'admin_' . $loggedInUser . '_' . time() . '.' . $fileExt;
            $targetPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $targetPath)) {
                $profile_pic = $fileName;
                // Delete old profile pic if it's not the default
                if ($_SESSION['profile_pic'] !== 'default-profile.jpg' && file_exists($uploadDir . $_SESSION['profile_pic'])) {
                    unlink($uploadDir . $_SESSION['profile_pic']);
                }
            }
        }
        
        $sql = "UPDATE admins SET fname = ?, lname = ?, email = ?, profile_pic = ? WHERE account_number = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssss", $fname, $lname, $email, $profile_pic, $loggedInUser);
        
        if ($stmt->execute()) {
            $_SESSION['fname'] = $fname;
            $_SESSION['lname'] = $lname;
            $_SESSION['email'] = $email;
            $_SESSION['profile_pic'] = $profile_pic;
            $message = "Profile updated successfully!";
        } else {
            $error = "Error updating profile: " . $conn->error;
        }
        $stmt->close();
    }
    
    // Change Password
    elseif (isset($_POST['current_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if ($new_password !== $confirm_password) {
            $error = "New passwords do not match!";
        } else {
            // Verify current password
            $sql = "SELECT password FROM admins WHERE account_number = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $loggedInUser);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                if (password_verify($current_password, $row['password'])) {
                    // Update password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $update_sql = "UPDATE admins SET password = ? WHERE account_number = ?";
                    $update_stmt = $conn->prepare($update_sql);
                    $update_stmt->bind_param("ss", $hashed_password, $loggedInUser);
                    
                    if ($update_stmt->execute()) {
                        $message = "Password changed successfully!";
                    } else {
                        $error = "Error changing password: " . $conn->error;
                    }
                    $update_stmt->close();
                } else {
                    $error = "Current password is incorrect!";
                }
            }
            $stmt->close();
        }
    }
    
    // Update System Settings
    elseif (isset($_POST['theme'])) {
        // Here you would typically save these to a database table for system settings
        // For this example, we'll just store them in session
        $_SESSION['theme'] = $_POST['theme'];
        $_SESSION['notifications'] = $_POST['notifications'];
        $message = "System preferences updated successfully!";
    }
}

// Fetch admin's profile pic and info
$sql = "SELECT profile_pic, fname, lname, email FROM admins WHERE account_number = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $loggedInUser);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $profile_pic = $row['profile_pic'] ? $row['profile_pic'] : 'default-profile.jpg';
    // Ensure session has these values
    $_SESSION['fname'] = $row['fname'];
    $_SESSION['lname'] = $row['lname'];
    $_SESSION['email'] = $row['email'];
    $_SESSION['profile_pic'] = $profile_pic;
} else {
    $profile_pic = 'default-profile.jpg';
}

$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="other resources/fontawesome-free-6.5.2-web/css/all.min.css">
    <title>Admin Settings</title>
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

        .settings-card {
            background-color: #ffffff;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.15);
            margin-bottom: 1rem;
        }

        .settings-card h2 {
            color: #f8b500;
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: bold;
            color: #555;
        }

        .form-group input, 
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-family: 'Fredoka';
            font-size: 1rem;
        }

        .form-group input:focus, 
        .form-group select:focus {
            outline: none;
            border-color: #f8b500;
        }

        .btn {
            background-color: #f8b500;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 5px;
            font-family: 'Fredoka';
            font-size: 1rem;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .btn:hover {
            background-color: #e5941f;
        }

        .tabs {
            display: flex;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid #ddd;
        }

        .tab {
            padding: 0.75rem 1.5rem;
            cursor: pointer;
            font-weight: bold;
            color: #777;
            border-bottom: 3px solid transparent;
        }

        .tab.active {
            color: #f8b500;
            border-bottom: 3px solid #f8b500;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .profile-pic-container {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .profile-pic-preview {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #f8b500;
            margin-right: 1.5rem;
        }

        .profile-pic-upload {
            display: flex;
            flex-direction: column;
        }

        .profile-pic-upload input[type="file"] {
            margin-bottom: 0.5rem;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }
        
        .alert-success {
            color: #3c763d;
            background-color: #dff0d8;
            border-color: #d6e9c6;
        }
        
        .alert-danger {
            color: #a94442;
            background-color: #f2dede;
            border-color: #ebccd1;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar (same as before) -->
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
                <a href="a_Settings.php" class="active" title="Settings">
                    <i class="fa-solid fa-gear"></i>
                    <span>Settings</span>
                </a>
            </div>
        </div>

        <!-- Content Area -->
        <div class="content">
            <div class="content-header">
                <div>
                    <h1>Admin Settings</h1>
                    <p>Manage your account and system preferences</p>
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

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="tabs">
                <div class="tab active" onclick="openTab(event, 'profile')">Profile Settings</div>
                <div class="tab" onclick="openTab(event, 'security')">Security</div>
                <div class="tab" onclick="openTab(event, 'system')">System Settings</div>
            </div>

            <div id="profile" class="tab-content active">
                <div class="settings-card">
                    <h2>Profile Information</h2>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="profile-pic-container">
                            <img src="uploads/profiles/<?php echo htmlspecialchars($profile_pic); ?>" alt="Profile Picture" class="profile-pic-preview" onerror="this.src='uploads/profiles/default-profile.jpg'">
                            <div class="profile-pic-upload">
                                <input type="file" name="profile_pic" id="profile_pic" accept="image/*">
                                <button type="button" class="btn" onclick="document.getElementById('profile_pic').click()">Change Photo</button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="fname">First Name</label>
                            <input type="text" id="fname" name="fname" value="<?php echo htmlspecialchars($_SESSION['fname']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="lname">Last Name</label>
                            <input type="text" id="lname" name="lname" value="<?php echo htmlspecialchars($_SESSION['lname']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($_SESSION['email']); ?>" required>
                        </div>
                        <button type="submit" class="btn">Save Changes</button>
                    </form>
                </div>
            </div>

            <div id="security" class="tab-content">
                <div class="settings-card">
                    <h2>Change Password</h2>
                    <form method="POST">
                        <input type="hidden" name="password_change" value="1">
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" id="current_password" name="current_password" required>
                        </div>
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password" required>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" required>
                        </div>
                        <button type="submit" class="btn">Update Password</button>
                    </form>
                </div>
            </div>

            <div id="system" class="tab-content">
                <div class="settings-card">
                    <h2>System Preferences</h2>
                    <form method="POST">
                        <div class="form-group">
                            <label for="theme">Theme</label>
                            <select id="theme" name="theme">
                                <option value="light" <?php echo ($_SESSION['theme'] ?? 'light') === 'light' ? 'selected' : ''; ?>>Light</option>
                                <option value="dark" <?php echo ($_SESSION['theme'] ?? 'light') === 'dark' ? 'selected' : ''; ?>>Dark</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="notifications">Email Notifications</label>
                            <select id="notifications" name="notifications">
                                <option value="enabled" <?php echo ($_SESSION['notifications'] ?? 'enabled') === 'enabled' ? 'selected' : ''; ?>>Enabled</option>
                                <option value="disabled" <?php echo ($_SESSION['notifications'] ?? 'enabled') === 'disabled' ? 'selected' : ''; ?>>Disabled</option>
                            </select>
                        </div>
                        <button type="submit" class="btn">Save Preferences</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Include all JavaScript from a_Home.php here
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

            // Preview profile picture when selected
            document.getElementById('profile_pic').addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(event) {
                        document.querySelector('.profile-pic-preview').src = event.target.result;
                    };
                    reader.readAsDataURL(file);
                }
            });
            
            // Auto-hide messages after 5 seconds
            setTimeout(function() {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    alert.style.display = 'none';
                });
            }, 5000);
        });

        function profileDropdown() {
            document.getElementById("dropdown").classList.toggle("show");
        }

        function openTab(evt, tabName) {
            // Hide all tab content
            const tabContents = document.getElementsByClassName("tab-content");
            for (let i = 0; i < tabContents.length; i++) {
                tabContents[i].classList.remove("active");
            }

            // Remove active class from all tabs
            const tabs = document.getElementsByClassName("tab");
            for (let i = 0; i < tabs.length; i++) {
                tabs[i].classList.remove("active");
            }

            // Show the current tab and add active class
            document.getElementById(tabName).classList.add("active");
            evt.currentTarget.classList.add("active");
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
$conn->close();
?>