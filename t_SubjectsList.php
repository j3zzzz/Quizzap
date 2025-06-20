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
$sql = "SELECT profile_pic FROM teachers WHERE account_number = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $loggedInUser);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $profilePic = $row['profile_pic'] ?: "uploads/default_profile.png"; // Pang display ng default profile pic pag wala pang profile pic na nakaset
} else {
    $profilePic = "uploads/default_profile.png"; // Default picture path if no custom picture found
}


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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $subject_name = $_POST['subject_name'];
    $class_name = $_POST['class_name'];
    $teacher_account_number = $_SESSION['account_number'];
    $subject_code = generateUniqueSubjectCode($conn);

    $stmt = $conn->prepare("INSERT INTO subjects (subject_name, class_name, teacher_id, subject_code) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $subject_name, $class_name, $teacher_account_number, $subject_code);
    
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
            width: 30%;
            margin: auto;
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

        /*.subject-button span:hover{
            color: white;
        }

        .subject-button:hover {
            background-color: #F8B500;
            color: white;
        } */

        .subject-button:active {
            background-color: #F8B500;
            box-shadow: 3px 4px 0 0 rgba(0, 0, 0, 0.3);
        }

        .subject-button span {
            font-size: 15px;
            font-family: Fredoka;
            color: #f8b500;
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

        .btn{
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
            font-size: 18px;
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
            margin-right: 200px;
            margin-top: 65px;
        }

        /* The Modal (background) */
        .modal {
          display: none; /* Hidden by default */
          position: fixed; /* Stay in place */
          z-index: 100; /* Sit on top */
          padding-top: 100px; /* Location of the box */
          left: 0;
          top: 0;
          width: 100%; /* Full width */
          height: 100%; /* Full height */
          overflow-x: hidden;
          overflow-y: scroll;
          background-color: rgb(0,0,0); /* Fallback color */
          background-color: rgba(0,0,0,0.4); /* Black w/ opacity */
        }

        /* Modal Content */
        .modal-content {
          background-color: white;
          margin: auto;
          padding: 15px;
          border: none;
          border-radius: 8px;
          width: 40%;
          font-family: Fredoka;
          font-size: 25px;
          color: ;
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

        .modal-body, .modal-dialog, .modal-content{
            background-color: white;
            border-radius: 20px;
        }

        .modal-content{
            padding: 30px;
        }

        .modal-dialog{
            margin-top: 13%;
        }

        form label{
            margin-top: 5%;
            color: #f8b500;
            font-size: 25px;
            font-family: Fredoka;
        }

        form input{
            margin-top: 2%;
            padding: 15px;
            width: 100%;
            border-radius: 15px;
            border: 3px solid #B9B6B6;
            font-size: 18px;
            font-family: Fredoka;
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

        /* Extra Large Screens (1400px and above) */
        @media (min-width: 1400px) {
            .content-header hr {
                width: calc(100vw - 300px);
            }
            
            .modal-content {
                width: 25%;
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
                <h1>Subjects</h1><br>
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
            
            <center>

            <div class="add-sub">
                <button id="modalbtn">Add Subject</button>
            </div>    
        
            <br><br><br>
            
            <center>

            <div><br><br>
                <?php
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        echo "<a class='subject-button' href='t_quizDash.php?subject_id=" . $row['subject_id'] . "'>" . $row['subject_name'] ."<br><span>". $row['subject_code'] ."</span></a>";
                    }
                } else {
                    echo "<div class='no-quiz-con'>";
                    echo "<p style='font-family: Fredoka; font-size: 22px; margin-top: 10%; color: #999;'>No subjects created yet.</p>";
                    echo "</div>";
                }
                ?>
            </div>

            </center>

            <div id="myModal" class="modal">

              <!-- Modal content -->
              <div class="modal-content">
                <span class="close">&times;</span>
                <br>
                <form method="post" action="">
                    <input type="text" name="subject_name" placeholder="Enter Subject" required>
                    <br>
                    <label for="class_name">Enter Class Name (Optional):</label>
                    <input type="text" id="class_name" name="class_name">
                    <br>
                    <center>
                    <button class="addBtn" type="submit">Create Subject</button>
                    </center>
                </form><br>
              </div>
            </div>
        </center>
        
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
    });

    function profileDropdown() { // Dropdown funtion
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
    }

    // When the user clicks anywhere outside of the modal, close it
    window.onclick = function(event) {
      if (event.target == modal) {
        modal.style.display = "none";
      }
    }
</script>

</body>
</html>