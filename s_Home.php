<?php
session_start();
if (!isset($_SESSION['account_number']) || strpos($_SESSION['account_number'], 'S') !== 0) {
    header("Location: login.php");
    exit();
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "rawrit"; // replace with your database name

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
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

//query to fetch the student_id that will be used in any of the data sa mga cards
$stud_id_sql = $conn->prepare
            ("SELECT student_id 
              FROM students
              WHERE  account_number = ?;
            ");
$stud_id_sql->bind_param("s", $loggedInUser);
$stud_id_sql->execute();
$stud_result = $stud_id_sql->get_result();

if ($stud_result->num_rows > 0) {
    $student_row = $stud_result->fetch_assoc();
    $student_id = $student_row['student_id'];

    //query para sa quiz na di pa natatake
    $not_taken_sql = $conn->prepare
                    ("SELECT DISTINCT s.subject_name, q.title, q.quiz_id
                    FROM quizzes q
                    JOIN subjects s ON q.subject_id = s.subject_id
                    JOIN enrollments e ON q.subject_id = e.subject_id
                    LEFT JOIN quiz_attempts qa ON q.quiz_id = qa.quiz_id AND qa.account_number = ?
                    WHERE e.student_id = ? AND qa.attempt_id IS NULL
                    ORDER BY q.quiz_id DESC");
    $not_taken_sql->bind_param("si", $loggedInUser, $student_id);

    // Check if execute() failed
    if (!$not_taken_sql->execute()) {
        echo "Execute failed: " . $not_taken_sql->error;
        exit;
    }

    $not_taken_result = $not_taken_sql->get_result();
    
    //query para sa latest high score
    $latest_high_score_sql = $conn->prepare
    ("SELECT s.subject_name, 
             q.title AS quiz_title, 
             MAX(qa.score) AS highest_score, 
             qa.attempt_time AS latest_attempt_date
      FROM quiz_attempts qa
      JOIN quizzes q ON qa.quiz_id = q.quiz_id
      JOIN subjects s ON q.subject_id = s.subject_id
      WHERE qa.account_number = ?
      GROUP BY s.subject_name, q.title
      HAVING highest_score > 0
      ORDER BY latest_attempt_date DESC
      LIMIT 5"); // Limit to top 5 latest high scores
    if ($latest_high_score_sql === false) {
        die("Prepare failed for latest high score query: " . $conn->error);
    }  
    $latest_high_score_sql->bind_param("s", $loggedInUser);

    // Check if execute() failed
    if (!$latest_high_score_sql->execute()) {
    echo "Execute failed: " . $latest_high_score_sql->error;
    exit;
    }

    $latest_high_score_result = $latest_high_score_sql->get_result();
} else {
    echo "No student found for this account.";
    $not_taken_result = null;
}

// Query for difficult questions
$difficult_questions_sql = $conn->prepare(
    "SELECT s.subject_name, 
            q.title AS quiz_title, 
            qq.question_text,
            COUNT(*) AS total_attempts,
            MIN(qa.score) AS lowest_score
    FROM quiz_attempts qa
    JOIN quizzes q ON qa.quiz_id = q.quiz_id
    JOIN subjects s ON q.subject_id = s.subject_id
    JOIN questions qq ON qa.quiz_id = qq.quiz_id
    WHERE qa.account_number = ?
      AND qa.score < (SELECT MAX(score) FROM quiz_attempts WHERE quiz_id = quiz_id AND account_number = ?)
    GROUP BY s.subject_name, q.title, qq.question_text
    HAVING total_attempts > 1 AND lowest_score <= 0.5 * (SELECT MAX(score) FROM quiz_attempts WHERE quiz_id = quiz_id AND account_number = ?)
    ORDER BY total_attempts DESC, lowest_score ASC
    LIMIT 5"
);
if ($difficult_questions_sql === false) {
    die("Prepare failed for difficult questions query: " . $conn->error);
}
$difficult_questions_sql->bind_param("sss", $loggedInUser, $loggedInUser, $loggedInUser);

// Check if execute() failed
if (!$difficult_questions_sql->execute()) {
    echo "Execute failed: " . $difficult_questions_sql->error;
    exit;
}

$difficult_questions_result = $difficult_questions_sql->get_result();

$conn->close();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" type="text/css" href="other resources/fontawesome-free-6.5.2-web/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <title>QuizZap Dashboard</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Tilt Warp Regular', sans-serif;
        }

        body, html {
            height: 100%;
            background-color: #f9f9f9;
        }

        .container {
            display: flex;
            height: 100vh;
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
          background: #999; 
          border-radius: 10px;
        }

        /* Handle on hover */
        ::-webkit-scrollbar-thumb:hover {
          background: #b4b4b4; 
        }

        .sidebar {
            position: fixed;
            width: 250px;
            height: 100vh !important;
            background-color: #F8B500;
            color: #ffffff;
            padding: 2rem 1rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .sidebar .logo {
            margin-bottom: 1rem;
            margin-left: 5%;
        }

        hr{
            border: 1px solid white;
        }

        .sidebar .menu {
            display: flex;
            flex-direction: column;
            margin-bottom: 20rem;
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
            font-family: Tilt Warp Regular;
            margin-bottom: .5rem;
        }

        .sidebar .menu a:hover, .sidebar .menu a.active {
            background-color: white;
            color: #F8B500;
        }

        .sidebar .menu a i {
            margin-right: 0.5rem;
        }

        /* Dashboard content area */
        .content {
            flex: 1;
            background-color: #f9f9f9;
            padding: 2rem;
            width: 100vh;
            margin-left: 16%;
        }

        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .content-header h1 {
            font-size: 2rem;
            color: #333333;
            font-family: Tilt Warp Regular;
        }

        .content-header p {
            color: #999;
            font-size: 1rem;
            margin-top: 0.5rem;
            font-family: Tilt Warp Regular;
        }

        .content-header .actions {
            display: flex;
            align-items: center;
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

        .cards {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            padding-bottom: 2rem;
        }

        .quizzes-card {
            background-color: #ffffff;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            height: 30rem;
            z-index: 4 !important;
        }

        .quizzes-card #quizzes-card-header {
            position: absolute;
            background-color: white;
            width: 20%;
        } 

        #quizzes-cont {
            padding: 1rem;
            overflow: auto;
            height: 90%;
        }

        .quizzes-card .bolt {
            color: #e5941f;
            float: left;
            line-height: 1;
            font-size: 40px;
            margin-right: 2%;
        } 

        .quizzes-card h3 {
            font-family: 'Tilt Warp Regular';
        }

        .quizzes-card h5 {
            font-family: Tilt Warp Regular;
            color: #999;
            font-weight: lighter;
        }

        #quizzes-cont a {
            background-color: #F8B500;
            text-decoration: none;
            color: white;
            padding: 4% 5%;
            border-radius: 10px;
            display: inline-block; 
            margin-left: 5%;
            width: 80%;
            cursor: pointer;
            border: 3px solid #f8b500
        }

        #quizzes-cont a:hover {
            background-color: white;
            color: #F8B500;  
        }

        .quizzes-card h4 {
            color: #6666;
            display: flex;
            align-items: center;
            justify-self: center;
            font-weight: lighter;
            margin-top: 30%;
        }

        /* Tooltip ng Quiz na di pa nate-take */
        .quiz-sub {
            position: relative;
        }

        .quiz-sub .tooltiptext {
            font-family: 'Tilt Warp Regular';
            font-size: 12px;
            visibility: hidden;
            width: 130px;
            background-color: #dfa200;
            color: white;
            text-align: center;
            border-radius: 6px;
            padding: 5px 2px;
            border: 2px solid #dfa200;

            /* Position the tooltip */
            position: absolute;
            z-index: 5;
            bottom: 35%;
            left: 90%;
            margin-left: -90px;
            margin-bottom: 2%;
            opacity: 0;
            transition: opacity 0.7s;
        }

        .quiz-sub .tooltiptext::after{
            content: "";
            position: absolute;
            top: 35%;
            right: 100%;
            margin-left: -5px;
            border-width: 5px;
            border-style: solid;
            transform: rotate(90deg);
            border-color: #dfa200 transparent transparent transparent;
        }

        .quiz-sub:hover .tooltiptext{
            visibility: visible;
            opacity: 1;
        }

        .high-score-card {
            background-color: #ffffff;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            height: 260px;
            width: 100%;
        }

        .high-score-header {
            position: absolute;
            background-color: white;
            width: 20%;
            display: flex;
        }

        .high-score-card .star {
            color: #e5941f;
            float: left;
            line-height: 1;
            font-size: 40px;
            margin-right: 2%;
        } 
        
        .high-score-card h3 {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .high-score-card h4 {
            color: #6666;
            display: flex;
            align-items: center;
            justify-self: center;
            font-weight: lighter;
            margin-top: 12%;
        }

        #high-score-quiz h4 {
            text-align: center;  
        }

        .quiz-title {
            width: 100%;
            height: fit-content;
            padding: 8px;
            font-size: 20px;
            justify-content: center;
            color: #333333;
            font-size: 15px;
        }

        .score {
            margin-bottom: 1%;
            color: white;
            width: 50% ;
        }

        .score p {
            border-radius: 5px;
            background-color: #F8B500;
            text-align: center;
            padding: 2px;
        }

        #high-score-quiz {
            margin-left: 8%;
            padding: .5rem 1rem;
            display: grid;
            grid-template-columns: 2fr 2fr 1fr;
            align-items: center;
        }

        .difficult-question-card {
            background-color: #ffffff;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .difficult-question-header {
            position: absolute;
            background-color: white;
            width: 20%;
            display: flex;
        }

        .difficult-question-card h3 {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .question {
            color: #e5941f;
            float: left;
            line-height: 1;
            font-size: 40px;
            margin-right: 2%;
        }

        table {
            border-collapse: collapse;
            margin-top: 3%;
            border-radius: 5px;
            margin: auto;
        }

        table, th, td {
            padding: .5rem;
        }

        td {
           text-align: center; 
        }

        th {
            color: white;
            background-color: #F8B500;
            font-weight: lighter;
            text-align: center;
        }

        tr:nth-child(even) {
            background-color: #f2f2f2;
        }

        table thead th:first-child {
            border-top-left-radius: 10px !important;
        }

        table thead th:last-child {
            border-top-right-radius: 10px !important;
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
            font-family: 'Tilt Warp Regular';
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

        .modal-body {
            overflow: auto;
            height: 70%;
            width: 100%;
        } 

        .modal-content{
            position: relative;
            background-color: #FFFFFF;
            border-radius: 20px;
            padding: 30px 40px;
            width: 35%;
            height: 70%;
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
            color: black;
            font-family: Tilt Warp Regular;
            text-align: left;
        }   

        .modal-content button {
            font-family: Tilt Warp Regular;
            color: white;
            font-size: 18px;
            width: 40%;
            background-color: #F8B500;
            padding: 10px 15px;
            border: none;
            border-radius: 10px;
            margin: auto;
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
          color: #ed5e00;
          text-decoration: none;
          cursor: pointer;
        }

        #quiz-details {
            overflow: auto;
        }

        #quiz-details h1 {
            font-family: Tilt Warp Regular;
            font-size: xx-large ;
            text-align: center;
            margin-top: 1%;
            color: #f8b500;
        }

        #quiz-details h2 {
            font-family: Tilt Warp Regular;
            margin-top: 4%;
            padding-bottom: 5px;
            padding-top: 1px;
            letter-spacing: 1px;
            text-align: left;
        }

        #quiz-details span {
            font-family: Tilt Warp Regular;
            float: right;
            right: 5%;
            margin-top: -6.5%;
            font-size: 20px;
            position: relative;
            font-weight: lighter;
            text-align: center;
        }


    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <header>
                <div class="logo"><img src="img/logo4.png" width="200px" height="80px"></div>
            </header>
            <hr>
            <div class="menu">
                <a href="s_Home.php" class="active"><i class="fa-solid fa-house"></i>Dashboard</a>
                <a href="s_Classes.php"><i class="fa-regular fa-address-book"></i>Classes</a>
            </div>
        </div>

        <!-- Content Area -->
        <div class="content">
            <div class="content-header">
                <div>
                    <h1>Hi, <?php echo htmlspecialchars($_SESSION['fname']); ?>!</h1>
                    <p>Are you ready to start your journey to learning and testing your knowledge here?</p>
                </div>
                <div class="actions">
                    <div class="profile">
                        <img src="<?php echo $profilePic; ?>" onclick="profileDropdown()" width="50px" height="50px" class="dropdwn-btn">
                        
                            <div id="dropdown" class="dropdown-content">
                                 <button onclick="window.location.href='s_Profile.php'"><i class="fa-solid fa-user"></i> Profile</button> 
                                <form action="logout.php" method="post">
                                    <button><i class="fa-solid fa-right-from-bracket"></i> Logout</button>
                                </form>
                            </div>                            
                    </div>
                </div>
            </div>
            <div class = "cards">
                <div class="left-card">
                    <div class = "quizzes-card">
                        <div id="quizzes-card-header">
                            <i class="fa-solid fa-bolt bolt"></i>    
                            <h3>The ZAP! Starts Now!</h3>
                            <h5>Start answering quizzes now!</h5>
                        </div>    
                        <br><br>

                        <div id = "quizzes-cont">
                            <?php
                            if ($not_taken_result->num_rows > 0) {
                                while ($row = $not_taken_result->fetch_assoc()) {
                            ?>
                            <div class="quiz-sub">
                                <span class="tooltiptext">Subject: <?php echo htmlspecialchars($row['subject_name'])?></span>
                                <a class="quiz-link" data-quiz-id="<?php echo htmlspecialchars($row['quiz_id']);?>"><?php echo htmlspecialchars($row['title']);?></a> <br><br>
                            </div>    
                            <?php 
                                }
                            } else {
                                echo "<h4>You don't have any missed quizzes.</h4>";
                            }            
                            ?>    
                        </div>        
                    </div>  
                </div>
                
                <div class="right-card">
                    <div class="high-score-card">
                        <div class="high-score-header">    
                            <i class="fa-solid fa-star star"></i>
                            <h3>Your Latest High Scores</h3>
                        </div>    
                        
                        <br> <br>
                        <div id="high-score-quiz">                               
                            <?php 
                            if ($latest_high_score_result->num_rows > 0) {
                                while ($row = $latest_high_score_result->fetch_assoc()) {
                            ?>
                                <div class="quiz-title"><h5><?php echo htmlspecialchars($row['quiz_title']); ?></h5></div>
                                <div style="color: #999; text-align: right;">Score: </div>    
                                <center>
                                <div class="score"><p><?php echo htmlspecialchars($row['highest_score']); ?></p></div>   
                                </center>  
                            <?php
                                }
                            } else {
                                echo "<h4>You don't have any high scores yet.</h4>";
                            }
                            ?>   
                        </div>
                    </div>
                    <br>
                    <div class="difficult-question-card">
                        <div class="difficult-question-header">    
                        <i class="fa-solid fa-question question"></i></i>
                            <h3>Difficult Questions</h3>
                        </div>
                        <br><br>
                        <?php 
                        if ($difficult_questions_result && $difficult_questions_result->num_rows > 0) { ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Subject</th>
                                        <th>Quiz</th>
                                        <th>Question</th>
                                        <th>Attempts</th>
                                        <th>Lowest Score</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($difficult_question = $difficult_questions_result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($difficult_question['subject_name']); ?></td>
                                            <td><?php echo htmlspecialchars($difficult_question['quiz_title']); ?></td>
                                            <td><?php echo htmlspecialchars($difficult_question['question_text']); ?></td>
                                            <td><?php echo htmlspecialchars($difficult_question['total_attempts']); ?></td>
                                            <td><?php echo htmlspecialchars($difficult_question['lowest_score']); ?></td>
                                        </tr>
                                    <?php endwhile; ?>    
                                </tbody>
                            </table>    
                            <?php 
                        } else { ?>
                            <div style="color: #9999 !important; text-align: center;">No Difficult Question Found.</div>
                        <?php } ?>     
                    </div>
                </div>    
            </div>
        </div>
    </div>

    <div id="quiz-info-modal" class="modal">
        
        <div class="modal-content">
            
            <!-- Modal content -->    
            <span class="close">&times;</span>
            <h2 id="ready">Are you Ready to Ace this Quiz?</h2>
            <hr style="height: 4px; background-color: #CCCCCC; border-radius: 5px; border: none;"><br>
            
            <div class="modal-body">    
                <div id="quiz-details"></div>
            </div> 
            <center>   
                <button id="start-quiz-button">QuizZap!</button>
            </center>            
        </div>
    </div>    

<script type="text/javascript">
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
</script>

</body>
</html>

<?php 
$stud_id_sql->close();
$stmt->close();
$not_taken_sql->close();
$latest_high_score_sql->close();
?>