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
                <a href="s_quiz.php" title="Quizzes">
                    <i class="fa-regular fa-circle-question"></i>
                    <span>Quizzes</span>
                </a>
                <a href="s_scores.php?subject_id=<?php echo $subject_id;?>" title="Scores">
                    <i class="fa-solid fa-list-ol"></i>
                    <span>Scores</span>
                </a>
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
</script>

</body>
</html>

<?php 
$conn->close();
?>