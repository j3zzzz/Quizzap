<?php
session_start();
// Database connection details
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "rawrit";

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Define the quiz ID
$quiz_id = $_GET['quiz_id']; // Set the desired quiz ID here
$subject_id = isset($_GET['subject_id']) ? $_GET['subject_id'] : null;

//para ma-access yung links na related sa subject_id
if (!$subject_id) {
    $subject_sql = "SELECT subject_id FROM quizzes WHERE quiz_id = ?";
    $subject_stmt = $conn->prepare($subject_sql);
    $subject_stmt->bind_param("i", $quiz_id);
    $subject_stmt->execute();
    $subject_result = $subject_stmt->get_result();
    
    if ($subject_result->num_rows > 0) {
        $subject_row = $subject_result->fetch_assoc();
        $subject_id = $subject_row['subject_id'];
    }
    $subject_stmt->close();
}

// Prepare and execute the query
$stmt = $conn->prepare("
    SELECT 
        CONCAT(u.fname, ' ', u.lname) AS Name,
        qa.score,
        qa.attempt_time
    FROM quiz_attempts qa
    JOIN students u ON qa.account_number = u.account_number
    JOIN (
        SELECT account_number, MAX(score) as max_score 
        FROM quiz_attempts 
        WHERE quiz_id = ?
        GROUP BY account_number
    ) max_scores ON qa.account_number = max_scores.account_number
        AND qa.score = max_scores.max_score
    WHERE qa.quiz_id = ?
    GROUP BY qa.account_number
    ORDER BY qa.score DESC, qa.attempt_time DESC");

$stmt->bind_param("ii", $quiz_id, $quiz_id);
$stmt->execute();
$result = $stmt->get_result();


// Close the statement and connection
$stmt->close();

//para ma-fetch yung title ng quiz
$quiz_sql = $conn->prepare("
    SELECT title 
    FROM quizzes 
    WHERE quiz_id = ?
    ");
$quiz_sql->bind_param("i", $quiz_id);
$quiz_sql->execute();
$quiz_result = $quiz_sql->get_result();

if ($quiz_result->num_rows > 0) {
    $row = $quiz_result->fetch_assoc();
    $title = $row['title'];
}

$quiz_sql->close();

?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" type="text/css" href="other resources/fontawesome-free-6.5.2-web/css/all.min.css">
    <title>Rankings</title>

    <style type="text/css">
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Tilt Warp, sans-serif;
        }

        body, html {
            height: 100%;
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

        hr{
            border: 1px solid #F8B500;
        }

        #hr1 {
            background-color: #F8B500; 
            height: 2px; 
            border: none;
            margin-top: 5%;
            width: 100%;
            margin-left: -5%;
            align-self: center;
        }

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
            width: 100%;
        }

        .content-header h1 {
            position: absolute;
            width: fit-content;
        }

        /* Container styles */
        #scores-cont {
            max-width: 900px;
            margin: 2rem auto;
            padding: 20px;
        }

        /* Table header styles */
        .ranking-header {
            display: grid;
            grid-template-columns: 1fr 2fr 1fr 2fr;
            padding: 1rem 2rem;
            margin-bottom: 1rem;
        }

        .ranking-header span {
            font-weight: bold;
            font-size: 2rem;
            color: #f8b500;
            text-align: center;
        }

        /* Ranking rows container */
        .ranking-rows {
            display: flex;
            flex-direction: column;
            gap: 0.8rem;
        }

        /* Individual ranking row */
        .ranking-row {
            display: grid;
            grid-template-columns: 1fr 2fr 1fr 2fr;
            padding: 1rem 2rem;
            border-radius: 15px;
            align-items: center;
            font-weight: 500;
        }

        .ranking-row div {
            cursor: pointer;
        }

        .ranking-row:hover {
            -ms-transform: scale(1.5); /* IE 9 */
            -webkit-transform: scale(1.5); /* Safari 3-8 */
            transform: scale(1.1); 
            transition: transform .2s;
            box-shadow: 0 4px 0 0 #BC8900;
        }

        /* Different background colors for each position */
        .ranking-row:nth-child(1) {
            background: #ffc62c;
        }

        .ranking-row:nth-child(2) {
            background: #ffd460;
            width: 95%;
            margin: auto;
        }

        .ranking-row:nth-child(3) {
            background: #ffe293;
            width: 90%;
            margin: auto;
        }

        .ranking-row:nth-child(n+4) {
            background: #ffe9ad;
            width: 85%;
            margin: auto;
        }

        /* Rank number styles */
        .rank {
            font-size: 1.5rem;
            font-weight: bold;
            text-align: center;

        }

        /* Name styles */
        .stud-name {
            font-size: 1.2rem;
            text-align: center;
        }

        /* Score styles */
        .score {
            font-size: 1.5rem;
            font-weight: bold;
            text-align: center;
        }

        .time {
            font-size:  1rem;
            text-align: center;
            color: #444;
        }

    </style>
</head>
<body>

<div class="sidebar">
    <header>
        <div class="logo"><img src="img/logo1.png" onclick="window.location.href='t_Profile.php'" width="200px" height="80px"></div>
    </header>
    <hr>
    <div class="menu">
        <a href="s_Classes.php"><i class="fa-solid fa-list"></i>Classes</a>
        <a href="select_quiz.php?subject_id=<?php echo $subject_id;?>"><i class="fa-regular fa-circle-question"></i>Quizzes</a>
        <a href="s_scores.php?subject_id=<?php echo $subject_id;?>" ><i class="fa-solid fa-list-ol"></i>Scores</a>
    </div>
</div>

<div class="content">
    <div class="content-header">
        <h1><?php echo $title; ?> Rankings</h1>
        <br>
        <hr id="hr1">
    </div>

    <div id="scores-cont">
        <div class="ranking-header">
            <span>Rank</span>
            <span>Name</span>
            <span>Score</span>
            <span>Time</span>
        </div>

        <div class="ranking-rows">
            <?php 
            if ($result->num_rows > 0) {
                $current_rank = 1;
                $previous_score = null;
                $rank_counter = 0;

                while ($row = $result->fetch_assoc()) {
                    $rank_counter++;

                    if ($previous_score !== $row['score']) {
                        $current_rank = $rank_counter;
                    }

                    $attempt_time = date('M d, Y g:i A', strtotime($row['attempt_time']));

                    $rankClass = '';
                    if ($current_rank == 1) {
                        $rankClass = 'first-place';
                    } else if ($current_rank == 2) {
                        $rankClass = 'second-place';
                    } else if ($current_rank == 3) {
                        $rankClass = 'third-place';
                    }

                    ?>
                    <div class="ranking-row">
                        <div class="rank"><?php echo htmlspecialchars($current_rank); ?></div>
                        <div class="stud-name"><?php echo htmlspecialchars($row['Name']); ?></div>
                        <div class="score"><?php echo htmlspecialchars($row['score']); ?></div>
                        <div class="time"><?php echo htmlspecialchars($attempt_time); ?></div>
                    </div>
            <?php
                    $previous_score = $row['score'];    
                } 
            } else { ?>    
                <div class="ranking-row" style="text-align: center; grid-column: 1 / -1;"> No Rankings Yet.
                </div>
            <?php } ?>        
        </div>
    </div>    
</div>

</body>
</html>

<?php 
$conn->close();
?>