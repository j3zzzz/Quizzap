<?php
session_start(); // Start the session

// Check if the user is logged in and is a student
if (!isset($_SESSION['account_number']) || strpos($_SESSION['account_number'], 'S') !== 0) {
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
$sql = "SELECT profile_pic FROM students WHERE account_number = ?";
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

// Get the subject_id from the URL
$subject_id = isset($_GET['subject_id']) ? $_GET['subject_id'] : null;
$account_number = $_SESSION['account_number'];


if (!$subject_id) {
    ?>
    <script type="text/javascript">
    alert("Subject ID not provided.");
    window.location.href="studClasses.php";
    </script>
    <?php
    exit();   
}

// Fetch the subject name for the given subject_id
$sql = "SELECT subject_name FROM subjects WHERE subject_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $subject_id);
$stmt->execute();
$result_subject = $stmt->get_result();
$subject_name = "Unknown Subject"; // Default value in case subject is not found

if ($result_subject->num_rows > 0) {
    $row_subject = $result_subject->fetch_assoc();
    $subject_name = $row_subject['subject_name'];
}
$stmt->close();

if (isset($_GET['quiz_id'])) {
    $quiz_id = $_GET['quiz_id'];
}


// Fetch quizzes for the selected subject
$sql = "SELECT q.*, 
        CASE WHEN qa.latest_attempt_id IS NOT NULL THEN 1 ELSE 0 END as is_taken,
        qa.score as last_score,
        qa.attempt_time as last_attempt
        FROM quizzes q 
        LEFT JOIN (
            SELECT quiz_id, MAX(attempt_id) as latest_attempt_id, 
                   score,
                   attempt_time
            FROM quiz_attempts
            WHERE account_number = ?
            GROUP BY quiz_id
        ) qa ON q.quiz_id = qa.quiz_id 
        WHERE q.subject_id = ? 
        ORDER BY q.quiz_id DESC";

$stmt = $conn->prepare($sql);

// Check if prepare failed
if ($stmt === false) {
    die("Error preparing quiz statement: " . $conn->error);
}

if (!$stmt->bind_param("si", $account_number, $subject_id)) {
    die("Error binding quiz parameters: " . $stmt->error);
}

if (!$stmt->execute()) {
    die("Error executing quiz statement: " . $stmt->error);
}

$result = $stmt->get_result();
$stmt->close();;

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <title>QuizZap Dashboard</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Fredoka';
        }

        body, html {
            height: 100%;
            overflow-x: hidden;
        }

        .container {
            display: flex;
            min-height: 100vh;
            position: relative;
        }

        /* Sidebar styling */
        .sidebar {
            position: fixed;
            width: 250px;
            height: 100vh;
            background-color: white;
            color: #f8b500;
            padding: 2rem 1rem;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            transition: all 0.3s ease;
            z-index: 999;
            box-shadow: 0 2px 10px rgba(0,0,0,0.15);
            transform: translateX(0);
        }

        .sidebar.collapsed {
            width: 90px;
            padding: 2rem 0.5rem;
        }

        .sidebar.mobile-hidden {
            transform: translateX(-100%);
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
            color: #f8b500;
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

        .mobile-toggle {
            display: none;
            position: fixed;
            top: 1rem;
            left: 1rem;
            background: #f8b500;
            color: white;
            border: none;
            padding: 0.5rem;
            border-radius: 5px;
            z-index: 1000;
            font-size: 1.2rem;
        }

        .sidebar .menu {
            margin-top: 30%;
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }

        .sidebar.collapsed .menu{
            align-items: center;
            margin-top: 45%;
        }

        .sidebar .menu a {
            color: #f8b500;
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
            background-color: #f8b500;
            color: white;
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

        .sidebar.collapsed .toggle-btn{
            margin: auto;
        }

        .sidebar.collapsed .logo-img {
            display: none;
        }

        .sidebar.collapsed .logo-icon {
            display: block !important;
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
            width: 100%;
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
            transition: transform .2s;
            box-shadow: 0 6px 0 0 rgba(0, 0, 0, 0.2);
            
        }

        .subject-button a:hover{
            background-color: #F8B500;
            color: white;
        }

        .subject-button span:hover{
            color: white;
        }

        .subject-button:hover {
            background-color: #F8B500;
            color: white;
        }

        .subject-button:active {
            background-color: #F8B500;
            box-shadow: 3px 4px 0 0 rgba(0, 0, 0, 0.3);
        }

        .subject-button a:active {
            background-color: #A34404;
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
          border-radius: 5px;
        }
         
        /* Handle */
        ::-webkit-scrollbar-thumb {
          background: #ccc; 
          border-radius: 10px;
        }

        /* Handle on hover */
        ::-webkit-scrollbar-thumb:hover {
          background: #ccc; 
        }

        .btn{
            float: left;
            margin-top: 2%;
            margin-left: 7%;
            width: 130px;
            padding: 10px;
            border-radius: 10px;
            background-color: #FFEFE4;
            color: #A34404;
            border: 2px solid #FFEFE4;
            font-family: Purple Smile;
            box-shadow: 5px 6px 0 0 rgba(0, 0, 0, 0.2);
            cursor: pointer;
        }

        .btn:hover{
            background-color: #A34404;
            color: #FFEFE4;
            border: 2px solid #A34404;
        }

        .create-q-button a {
            float: right;
            margin-top: 2%;
            margin-right: 4%;
            width: 15%;
            padding: 10px;
            border-radius: 10px;
            background-color: #F8B500;
            color: white;
            border: 2px solid #F8B500;
            font-family: Fredoka;
            font-size: 18px;
            box-shadow: 0 6px 0 0 #BC8900;
            cursor: pointer;
            text-decoration: none;
        }

        .create-q-button a:hover {
            background-color: white;
            color: #f8b500;
        }

        .create-q-button a:active {
            background-color: #f8b500;
            color: white;
            box-shadow: 0 3px 0 -0.5px #BC8900;
        }

        .quiz {
            background-color: white;
            border: 3px solid #DCDCDC;
            border-radius: 15px;
            padding: 50px;
            width: 100%; /* Changed from 200% to 100% */
            max-width: 1000px;
            margin: 2% auto 0 auto; /* Centered horizontally, removed negative margin */
            overflow: auto;
            box-shadow: 2px 4px 2px 0 rgba(0, 0, 0, 0.2);
        }

        .quiz-btn {
            background-color: white;
            align-items: center;
            display: inline-grid;
            justify-content: center;
            align-items: center;
            margin-top: 2%;
            margin-right: 4%;
            margin-bottom: 1.5%;
            width: 21%;
            padding: 8px 15px;
            padding-bottom: 8px;
            border: 2px solid #f8b500;
            border-radius: 8px;
            box-shadow: 0 4px 0 0 #BC8900;
            text-decoration: none;
            text-align: center;
            font-family: Fredoka;
            font-weight: 500;
            font-size: 22px;
            color: black;
            cursor: pointer;
        }

        .quiz-btn.taken {
            background-color: #e0e0e0;
            border-color: #999999;
            box-shadow: 0 4px 0 0 #666666;
        }

        .quiz-btn:hover {
          -ms-transform: scale(1.5); /* IE 9 */
          -webkit-transform: scale(1.5); /* Safari 3-8 */
          transform: scale(1.2); 
          transition: transform .2s;
        }

        .quiz-btn .tooltiptext {
            font-family: 'Fredoka';
            font-size: 12px;
            visibility: hidden;
            width: 180px;
            background-color: white;
            color: black;
            text-align: center;
            border-radius: 6px;
            padding: 5px 0;
            border: 2px solid #f8b500;

            /* Position the tooltip */
            position: absolute;
            z-index: 5;
            bottom: 105%;
            left: 50%;
            margin-left: -90px;
            margin-bottom: 2%;
            opacity: 0;
            transition: opacity 0.7s;
        }

        .quiz-btn .tooltiptext::after {
            content: "";
            position: absolute;
            top: 100%;
            left: 50%;
            margin-left: -5px;
            border-width: 5px;
            border-style: solid;
            border-color: #F8B500 transparent transparent transparent;
        }

        .quiz-btn:hover .tooltiptext{
            visibility: visible;
            opacity: 1;
        }

        .quiz-btn:active {
            background-color: #f8b500;
            color: white;
            box-shadow: 0 4px 0 0 #BC8900;
        } 

        .no-quiz-btn {
            position: relative;
            text-align: center;
            margin: auto;
            margin-top: 3px;
            padding: 3px 0;
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
            font-family: To Japan;
            width: 60%;
            margin: auto;
            padding: 10px 3px;
            margin-top: 100px;
        }

        .modal {
            display: none; /* Hidden by default */
            position: fixed; /* Stay in place */
            z-index: 4; /* Sit on top */
            padding-top: 100px; /* Location of the box */
            left: 0;
            top: 0;
            width: 100%; /* Full width */
            height: 100%; /* Full height */
            overflow: auto; /* Enable scroll if needed */
            background-color: rgb(0,0,0); /* Fallback color */
            background-color: rgba(0,0,0,0.4); /* Black w/ opacity */
        }

        /* Modal Animation */
        @-webkit-keyframes animatetop {
        from {top:-100%; opacity:0} 
        to {top:-5%; opacity:1}
        }

        @keyframes animatetop {
        from {top:-100%; opacity:0}
        to {top:-5%; opacity:1}
        }

        @media (max-width: 768px) {
            .modal-content {
                width: 80%;
            }
        }

         /* Scroll Bar */
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

        .modal-body {
            overflow: auto;
            height: 65%;
            width: 100%;
        }   

        .modal-content{
            position: relative;
            background-color: #FFFFFF;
            border-radius: 20px;
            padding: 30px 40px;
            width: 35%;
            height: 80%;
            margin: auto;
            top: 5%;
            left: 15%;
            transform: translateX(-50%);
            -webkit-animation-name: animatetop;
            -webkit-animation-duration: 0.4s;
            animation-name: animatetop;
            animation-duration: 0.4s;
            z-index: 4;
        }

        #ready {
            font-size: 18px;
            font-weight: 500;
            color: black;
            font-family: Fredoka;
            text-align: left;
            padding:10px;
            border-bottom: 1.5px solid #f8b500;
        }   

        .modal-content button {
            font-family: Fredoka;
            color: white;
            font-size: 18px;
            width: 40%;
            background-color: #F8B500;
            padding: 10px 15px;
            border: none;
            border-radius: 10px;
            margin-top: 3%;
            margin-left: 2%;
            cursor: pointer;
            box-shadow: 0 6px 0 0 #BC8900;
        }

        .modal-content button:hover {
            background-color: white;
            color: #f8b500;
            border: 2px solid #f8b500;
            -ms-transform: scale(1.5); /* IE 9 */
            -webkit-transform: scale(1.5); /* Safari 3-8 */
            transform: scale(1.2); 
            transition: transform .2s;
            box-shadow: 0 4px 0 0 #BC8900;
        }

        .modal-content button:active {
            background-color: #f8b500;
            color: white;
            transform: translateY(4px);
            box-shadow: 0 4px 0 0 #BC8900;
        }
        .modal-dialog{
            background: none;
            margin-top: 1%;
            -webkit-animation-name: animatetop;
            -webkit-animation-duration: 0.6s;
            animation-name: animatetop;
            animation-duration: 0.6s;
            z-index: 2;
        }

        .modal-dialog img {
            height: 150px;
            width: 50%;
            display: flex;
            position: absolute;
            margin: auto;
            margin-top: 3%;
            margin-left: 20%;
            -webkit-animation-name: animatetop;
            -webkit-animation-duration: 0.1s;
            animation-name: animatetop;
            animation-duration: 0.1s;
            z-index: 2;
            filter: drop-shadow(6px -1px 5px black);
        }

        /* The Close Button */
        .close {
          color: black;
          float: right;
          margin-top: -4%;
          font-size: 28px;
          font-weight: bold;
          transition: 1.0s;
        }

        .close:hover,
        .close:focus {
          color: #f8b500;
          text-decoration: none;
          cursor: pointer;
        }

        #quiz-details {
            overflow: auto;
        }

        #quiz-details h1 {
            font-family: Fredoka;
            font-size: xx-large ;
            text-align: center;
            margin-top: 1%;
            color: #f8b500;
        }

        #quiz-details h2 {
            font-family: Fredoka;
            margin-top: 4%;
            padding-bottom: 5px;
            padding-top: 1px;
            letter-spacing: 1px;
            text-align: left;
        }

        #quiz-details span {
            font-family: Fredoka;
            float: right;
            right: 5%;
            margin-top: -6.5%;
            font-size: 20px;
            position: relative;
            font-weight: bolder;
            text-align: center;
        }

        .dropdown-content {
            width: 300px;
            right: 1%;
            display: none;
            position: absolute;
            background-color: #F8B500;
            border-radius: 15px;
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
            z-index: 1;
            padding: 10px 0;
            top: 15%;
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
            font-family: Purple Smile;
            font-size: 18px;
            font-weight: lighter;
            border: 2px solid white !important;
            color: black;
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
        }

    .dropdown-content a:hover, .dropdown-content button:hover{
        background-color: white !important;
        color: #F8B500;
    }

    .show {
         display: block;
    }

    </style>
</head>
<body>
    <div class="container">
        <!-- Mobile Toggle Button -->
        <button class="mobile-toggle" onclick="toggleMobileSidebar()">
            <i class="fas fa-bars"></i>
        </button>

        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <header>
                <button id="toggleSidebar" class="toggle-btn">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="logo">
                    <img src="img/logo1.png" width="200px" height="80px" class="logo-img">
                    <img src="img/logo 2.png" width="50px" height="50px" class="logo-icon" style="display: none; margin-top: 10%;">
                </div>
            </header>
            <hr style="border: 1px solid #f8b500;">
            <div class="menu">
                <a href="s_Classes.php" title="Classes">
                    <i class="fa-solid fa-list"></i>
                    <span>Classes</span>
                </a>
                <a href="s_quiz.php" class="active" title="Quizzes">
                    <i class="fa-regular fa-circle-question"></i>
                    <span>Quizzes</span>
                </a>
                <a href="s_scores.php?subject_id=<?php echo $subject_id;?>" title="Scores">
                    <i class="fa-solid fa-list-ol"></i>
                    <span>Scores</span>
                </a>
            </div>
        </div>

        <!-- Content Area -->
        <div class="content">
            <div class="content-header">
                <div><br>
                    <h1><?php echo htmlspecialchars($subject_name); ?></h1><br>
                </div>
                <div class="actions">
                    <div class="profile"><img src="<?php echo $profilePic; ?>" onclick="profileDropdown()" width="50px" height="50px" class="dropdwn-btn">
                        
                            <div id="dropdown" class="dropdown-content">
                                 <button onclick="window.location.href='s_Profile.php'"><i class="fa-solid fa-user"></i> Profile</button> 
                                <form action="logout.php" method="post">
                                    <button><i class="fa-solid fa-right-from-bracket"></i> Logout</button>
                                </form>
                            </div>
                    </div>
                </div>
            </div>
    <center>

        <div class="quiz">
            <?php 
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $takenClass = $row['is_taken'] ? 'taken' : '';
                        $tooltip = '';
                        if ($row['is_taken']) {
                            $attemptDate = date('M d, Y', strtotime($row['last_attempt']));
                            $tooltip = "<span class='tooltiptext'Quiz Taken<br>Score: {$row['last_score']}<br>Date Taken: {$attemptDate}</span>";
                        }
                        echo "<a class='quiz-btn quiz-link {$takenClass}' data-quiz-id='" . $row['quiz_id'] . "'>" . $row['title'] . $tooltip . "</a>";
                    }
                } else {
                    echo "<div class='no-quiz-btn'>";
                    echo "<p>No quizzes available for this subject.</p>";
                } 
            ?>
        </div>

    <div id="quiz-info-modal" class="modal">
        
            <div class="modal-content">
                
                <!-- Modal content -->    
                <span class="close">&times;</span>
                <h2 id="ready">Are you Ready to Ace this Quiz?</h2><br>
                
                <div class="modal-body">    
                    <div id="quiz-details"></div>
                </div>    
                    <button id="start-quiz-button">QuizZap!</button>
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
        
        document.addEventListener("DOMContentLoaded", function() {
    // Get the modal and elements inside it
    var modal = document.getElementById("quiz-info-modal");
    var closeModal = document.getElementsByClassName("close")[0];
    var quizDetails = document.getElementById("quiz-details");
    var startQuizButton = document.getElementById("start-quiz-button");
    
    // Add event listener to all quiz links
    document.querySelectorAll(".quiz-link").forEach(function(link) {
        link.addEventListener("click", function() {
            var quizId = this.getAttribute("data-quiz-id");
            // Fetch quiz details (replace with actual PHP script to fetch quiz data)
            fetch(`s_quiz_details.php?quiz_id=${quizId}`)
                .then(response => response.json())
                .then(data => {
                    // Populate the modal with quiz details
                    quizDetails.innerHTML = `
                        <h1>${data.title}</h1>
                        <h2>Number of Questions: </h2> <span> ${data.num_of_questions} </span> 
                        <h2>Quiz Type: </h2> <span> ${data.quiz_type} </span> 
                        <h2>Time Limit: </h2> <span> ${data.timer} minute/s</span> 
                    `;
                    
                    // Update the start quiz button link with conditional routing
                    startQuizButton.onclick = function() {
                        // Conditional routing based on quiz type
                        if (data.quiz_type === "All Zapped") {
                            window.location.href = `allZapped_quiz.php?quiz_id=${quizId}`;
                        } else if (["Multiple Choice", "True or False", "Fill in the Blanks", "Enumeration", "Identification", "Drag and Drop", "Matching Type"].includes(data.quiz_type)) {
                            window.location.href = `s_quiz.php?quiz_id=${quizId}`;
                        } else {
                            // Fallback for any unexpected quiz types
                            window.location.href = `s_quiz.php?quiz_id=${quizId}`;
                        }
                    };
                    
                    // Show the modal
                    modal.style.display = "block";
                })
                .catch(error => {
                    console.error("Error fetching quiz details:", error);
                });
        });
    });
    
    // Close the modal when the close button is clicked
    closeModal.onclick = function() {
        modal.style.display = "none";
    };
    
    // Close the modal when the user clicks outside of it
    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = "none";
        }
    };
});
</script>
</body>
</html>
