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

// Get the subject_id from the URL
$subject_id = isset($_GET['subject_id']) ? $_GET['subject_id'] : null;
$account_number = $_SESSION['account_number'];


if (!$subject_id) {
    ?>
    <script type="text/javascript">
    alert("Subject ID not provided.");
    window.location.href="s_Classes.php";
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
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" type="text/css" href="other resources/fontawesome-free-6.5.2-web/css/all.min.css">
    <title>QuizZap Dashboard</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Tilt Warp, sans-serif;
        }

        body, html {
            height: 100%;
        }

        .container {
            display: flex;
            height: 100vh;
        }

        .sidebar {
            height: 100vh;
            position: fixed;
            width: 250px;
            background-color: #ffffff;
            color: #f8b500;
            padding: 2rem 1rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-shadow: 2px 0 4px 0 rgba(0, 0, 0, 0.2);
        }

        .sidebar .logo {
            margin-bottom: 1rem;
            margin-left: 5%;
        }

        hr{
            border: 1px solid #F8B500;
        }

        #hr1 {
            background-color: #F8B500; 
            height: 2px; 
            border: none;
            margin-top: 5%;
            width: 100%;
            margin-left: -2%;
            align-self: center;
        }
            
        .sidebar .menu {
            display: flex;
            flex-direction: column;
            margin-bottom: 14rem;
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
            font-family: Tilt Warp Regular;
            margin-bottom: .5rem;
        }

        .sidebar .menu a:hover, .sidebar .menu a.active {
            background-color: #f8b500;
            color: #ffffff;
        }

        .sidebar .menu a i {
            margin-right: 0.5rem;
        }

        /* Dashboard content area */
        .content {
            margin-left: 17%;
            flex: 1;
            background-color: #ffffff;
            padding: 2rem;
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

        .content-header .actions button {
            background-color: #F8B500;
            color: #ffffff;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            font-size: 1rem;
            cursor: pointer;
            margin-right: 1rem;
            font-family: Tilt Warp Regular;
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

        .content-header hr{
            border: 1px solid #F8B500;
            width: 1150px;
        }

        .subject-cont {
            border: 3px solid #cf5200;
            border-radius: 5px;
            background-color: #ffb787;
            width: 60%;
            height: 400px;
            overflow: auto;
            box-shadow: 5px 6px 0 0 rgba(0, 0, 0, 0.2);
            border-radius: 15px;
            z-index: 5;
        }

        .subject-cont a {
            color: #CF5300;
            letter-spacing: 1px;
            font-size: 25px;
            text-decoration: none;
        }

        .subject-button {
            color: black;
            font-family: Tilt Warp Regular;
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
            font-family: Tilt Warp Regular;
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
          background: #CF5300; 
          border-radius: 10px;
        }

        /* Handle on hover */
        ::-webkit-scrollbar-thumb:hover {
          background: #A34404; 
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
            font-family: Tilt Warp Regular;
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
            width: 180%;
            max-width: 1000px;
            margin-left: 5%;
            margin-right: auto;
            margin-top: 2%;
            overflow: auto;
            box-shadow: 2px 4px 2px 0 rgba(0, 0, 0, 0.2);
            z-index: -5 !important;
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
            font-family: Tilt Warp Regular;
            font-size: 22px;
            color: black;
            position: relative;
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
            font-family: Tilt Warp Regular;
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
            border-color: #F8b500 transparent transparent transparent;
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
            font-family: Tilt Warp Regular;
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
          background: #ff6d0d; 
          border-radius: 10px;
        }

        /* Handle on hover */
        ::-webkit-scrollbar-thumb:hover {
          background: #A34404; 
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
            font-weight: bolder;
            text-align: center;
        }

    </style>
</head>
<body>
        <!-- Sidebar -->
        <div class="sidebar">
            <header>
                <div class="logo"><img src="img/logo1.png" width="200px" height="80px"></div>
            </header>
            <hr>
            <div class="menu">
                <a href="s_Classes.php"><i class="fa-solid fa-list"></i>Classes</a>
                <a class="active"><i class="fa-solid fa-house"></i>Quizzes</a>
                <a href="s_scores.php?subject_id=<?php echo $subject_id;?>"><i class="fa-regular fa-address-book"></i>Scores</a>
            </div>
        </div>    
        <!-- Content Area -->
<div class="content">
    <div class="content-header">
        <br>
            <h1><?php echo htmlspecialchars($subject_name); ?></h1>
            <br>
            <hr id="hr1">
    </div>
        <div class="actions">
            <div class="profile"> <!-- dropdown content-->
                <img src="img/default.png" onclick="window.location.href='s_Profile.php'" width="50px" height="50px">
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
                <h2 id="ready">Are you Ready to Ace this Quiz?</h2>
                <hr style="height: 4px; background-color: #CCCCCC; border-radius: 5px; border: none;"><br>
                
                <div class="modal-body">    
                    <div id="quiz-details"></div>
                </div>    
                    <button id="start-quiz-button">QuizZap!</button>
            
            </div>
    </div>
</div>            

    <script>
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

                            // Update the start quiz button link (replace with your quiz start page)
                            startQuizButton.onclick = function() {
                                window.location.href = `s_quiz.php?quiz_id=${quizId}`;
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
