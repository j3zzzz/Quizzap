<?php
session_start();
$account_number = $_SESSION['account_number'];

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

//to fetch profile pic
$loggedInUser = $_SESSION['account_number'];

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

// Define the quiz ID
$subject_id = $_GET['subject_id']; // Set the desired quiz ID here

$subject_sql = $conn->prepare("
    SELECT subject_name 
    FROM subjects 
    WHERE subject_id = ? 
    ");
$subject_sql->bind_param("i", $subject_id);
$subject_sql->execute();
$result_subject = $subject_sql->get_result();

if ($result_subject->num_rows > 0) {
    $row = $result_subject->fetch_assoc();
    $subject_name = $row['subject_name'];
}

$subject_sql->close();

if (isset($_GET['quiz_id'])) {
    $quiz_id = $_GET['quiz_id'];
}

// table for quizzes scores 
$stmt = $conn->prepare("
    SELECT quizzes.title, quiz_attempts.score
    FROM quizzes
    INNER JOIN quiz_attempts ON quizzes.quiz_id = quiz_attempts.quiz_id
    WHERE quiz_attempts.account_number = ?
    AND quizzes.subject_id = ?
    ORDER BY quizzes.quiz_id DESC");
$stmt->bind_param("si", $account_number, $subject_id); // Bind the quiz_id as an integer
$stmt->execute();
$result = $stmt->get_result();

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" type="text/css" href="font/fontawesome-free-6.5.2-web/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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

        table {
            width: 100%;
            border-collapse: collapse;
            font-family: Fredoka;
        }

        th {
            background-color: #f8b500;
            color: white;
            font-weight: bold;
            padding: 20px;
            text-align: center;
            font-family: Fredoka;
        }

        td {
            padding: 20px;
            text-align: center;
            font-family: Fredoka;
        }

        tr:nth-child(even) {
            background-color: #f2f2f2;
        }

        table tr:first-child th:first-child {
            border-top-left-radius: 10px;
        }

        table tr:first-child th:last-child {
            border-top-right-radius: 10px;
        }

        table tr:last-child td:first-child {
            border-bottom-left-radius: 10px;
        }

        table tr:last-child td:last-child {
            border-bottom-right-radius: 10px;
        }

        .progress-bar-container {
            width: 100%;
            height: 20px;
            background-color: #e6e6e6;
            position: relative;
        }

        .progress-bar {
            height: 100%;
            background-color: #4CAF50;
        }

        .due-date {
            text-align: right;
            padding-right: 10px;
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
                <a href="select_quiz.php?subject_id=<?php echo $subject_id; ?>" title="Quizzes">
                    <i class="fa-regular fa-circle-question"></i>
                    <span>Quizzes</span>
                </a>
                <a href="s_scores.php?subject_id=<?php echo $subject_id;?>" class="active" title="Scores">
                    <i class="fa-solid fa-list-ol"></i>
                    <span>Scores</span>
                </a>
            </div>
        </div>

    <!-- Content Area -->
    <div class="content">
            <div class="content-header">
                <div><br>
                    <h1><?php echo $subject_name; ?></h1><br>
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

        <table>
            <tr>
                <th>QUIZZES</th>
                <th>SCORE</th>
            </tr>

            <?php
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) { ?>
            <tr>
                <td><?php echo htmlspecialchars($row['title']); ?></td>
                <td><?php echo htmlspecialchars($row['score']); ?></td>
            </tr>

                <?php }
            } else { ?>    
                    <tr>
                        <td colspan="2">You don't have any taken quizzes yet.</td>
                    </tr>    
            <?php } ?>

        </table>
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

</script>

</body>
</html>