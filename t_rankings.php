<?php
// Database connection details remain the same
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "rawrit";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$subject_id = isset($_GET['subject_id']) ? $_GET['subject_id'] : null;

// Fetch quizzes for the subject
$quiz_sql = $conn->prepare("
    SELECT quiz_id, title 
    FROM quizzes 
    WHERE subject_id = ?
    ORDER BY quiz_id DESC");
$quiz_sql->bind_param("i", $subject_id);
$quiz_sql->execute();
$quiz_result = $quiz_sql->get_result();

$quiz_sql->close();

//para ma-fetch yung subject name
$sub_sql = $conn->prepare("
    SELECT subject_name 
    FROM subjects 
    WHERE subject_id = ?
    ");
$sub_sql->bind_param("i", $subject_id);
$sub_sql->execute();
$result = $sub_sql->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $subject_name = $row['subject_name'];
}

$sub_sql->close();


// Function to get rankings for a specific quiz
function getRankings($conn, $quiz_id) {
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
    $rankings = [];
    while ($row = $result->fetch_assoc()) {
        $rankings[] = $row;
    }
    $stmt->close();
    return $rankings;
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" type="text/css" href="other resources/fontawesome-free-6.5.2-web/css/all.min.css">
    <title>Rankings</title>
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
            height: 80%;
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
            font-weight: 500;
        }

        .ranking-row div {
            cursor: pointer;
        }

        /* Different background colors for each position */
        .ranking-row:nth-child(1) {
            background: #ffc62c;
        }

        .ranking-row:nth-child(1) i {
            color: #FFD700;
            border-radius: 100%;
            background-color: white;
            padding: 10px;
            text-align: center;
            margin-right: 5%;
        }

        .ranking-row:nth-child(2) {
            background: #ffd460;
            width: 95%;
            margin: auto;
        }

        .ranking-row:nth-child(2) i {
            color: #C0C0C0;
            border-radius: 100%;
            background-color: white;
            padding: 10px;
            text-align: center;
            margin-right: 5%;
        }

        .ranking-row:nth-child(3) {
            background: #ffe293;
            width: 90%;
            margin: auto;
        }

        .ranking-row:nth-child(3) i {
            color: #CD7F32;
            border-radius: 100%;
            background-color: white;
            padding: 10px;
            text-align: center;
            margin-right: 5%;
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
        
        .quiz-dropdown {
            width: 85%;
            margin: 1rem auto;
            background: #fff6df;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            
        }

        .quiz-header {
            padding: 1rem 2rem;
            background: #f8b500;
            color: #fff;
            border-radius: 10px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
            margin-bottom: 3%;
        }

        .quiz-header:hover {
            background: #e6a600;
        }

        .quiz-header h2 {
            margin: 0;
            font-size: 1.2rem;
            font-weight: normal;
        }

        .fa-chevron-down {
            color: black;
        }

        .quiz-content {
            display: none;
            padding: 1rem;
        }

        .quiz-content.active {
            display: block;
        }

        .no-rankings {
            text-align: center;
            padding: 2rem;
            color: #6666;
            font-style: italic;
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
                <a href="t_SubjectsList.php" title="Subject List">
                    <i class="fa-solid fa-list"></i>
                    <span>Subjects</span>
                </a>
                <a href="t_quizDash.php" title="Quiz Dash">
                    <i class="fa-regular fa-circle-question"></i>
                    <span>Quizzes</span>
                </a>
                <a href="t_rankings.php?subject_id=<?php echo $subject_id; ?>" class="active" title="Rankings">
                    <i class="fa-solid fa-ranking-star"></i>
                    <span>Rankings</span>
                </a>
                <a href="t_item-analysis.php?subject_id=<?php echo $subject_id; ?>" title="Item Analysis">
                    <i class="fa-solid fa-chart-line"></i>
                    <span>Item Analysis</span>
                </a>
            </div>
        </div>

        <div class="content">
            <div class="content-header">
                <div><br>
                    <h1>Rankings of all Quizzes in <?php echo $subject_name; ?></h1><br>
                </div>
                <div class="actions">
                    <div class="profile"><img src="img/default.png" onclick="profileDropdown()" width="50px" height="50px" class="dropdwn-btn">

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

    <div class="quizzes">
        <?php 
        if ($quiz_result->num_rows == 0) {
            echo "<div class='no-rankings'>No rankings because there is no quizzes created.</div>";
        }
        while($quiz = $quiz_result->fetch_assoc()): ?>
            <div class="quiz-dropdown">
                <div class="quiz-header" onclick="toggleRankings(<?php echo $quiz['quiz_id']; ?>)">
                    <h2><?php echo htmlspecialchars($quiz['title']); ?></h2>
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div id="rankings-<?php echo $quiz['quiz_id']; ?>" class="quiz-content">
                    <div id="scores-cont">
                        <div class="ranking-header">
                            <span>Rank</span>
                            <span>Name</span>
                            <span>Score</span>
                            <span>Time</span>
                        </div>
                        <div class="ranking-rows" id="ranking-rows-<?php echo $quiz['quiz_id']; ?>">
                            <!-- Rankings will be loaded here -->
                        </div>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
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


function toggleRankings(quizId) {
    const content = document.getElementById(`rankings-${quizId}`);
    const allContents = document.querySelectorAll('.quiz-content');
    
    // Close all other dropdowns
    allContents.forEach(item => {
        if (item.id !== `rankings-${quizId}`) {
            item.classList.remove('active');
        }
    });
    
    // Toggle the clicked dropdown
    content.classList.toggle('active');
    
    // Load rankings if not already loaded
    if (!content.dataset.loaded) {
        loadRankings(quizId);
        content.dataset.loaded = true;
    }
}

function loadRankings(quizId) {
    fetch(`get_rankings.php?quiz_id=${quizId}`)
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById(`ranking-rows-${quizId}`);
            if (data.length === 0) {
                container.innerHTML = '<div class="no-rankings">No rankings available for this quiz yet.</div>';
                return;
            }
            
            container.innerHTML = data.map((item, index) => `
                <div class="ranking-row">
                    <div class="rank"><i class="fa-solid fa-medal"></i>${index + 1}</div>
                    <div class="stud-name">${item.Name}</div>
                    <div class="score">${item.score}</div>
                    <div class="time">${formatDate(item.attempt_time)}</div>
                </div>
            `).join('');
        })
        .catch(error => console.error('Error loading rankings:', error));
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleString();
}

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