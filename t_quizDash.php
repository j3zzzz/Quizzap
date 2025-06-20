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

//query to fetch the teacher's profile pic
$sql = "SELECT profile_pic FROM teachers WHERE account_number = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $profilePic = $row['profile_pic'] ?: "uploads/default_profile.png"; // Pang display ng default profile pic pag wala pang profile pic na nakaset
} else {
    $profilePic = "uploads/default_profile.png"; // Default picture path if no custom picture found
}

$stmt->close();

$subject_id = $_GET['subject_id'];
$teacher_id = $_SESSION['account_number'];

$sql = "SELECT * FROM subjects WHERE subject_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $subject_id);
$stmt->execute();
$result = $stmt->get_result();
$subject = $result->fetch_assoc();
$stmt->close();

if (!$subject) {
    header("Location: t_SubjectsList.php");
    exit();
}

// Fetch the quizzes related to the subject
$sql = "SELECT * FROM quizzes WHERE subject_id = ? ORDER BY quiz_id DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $subject_id);
$stmt->execute();
$result = $stmt->get_result();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" type="text/css" href="other resources/fontawesome-free-6.5.2-web/css/all.min.css">
    <title><?php echo htmlspecialchars($subject['subject_name']); ?></title>
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

        .create-q-button {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 2rem;
        }

        .create-q-button a {
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            background-color: #F8B500;
            color: white;
            border: 2px solid #F8B500;
            font-family: Fredoka;
            font-weight: 500;
            font-size: 16px;
            box-shadow: 0 6px 0 0 #BC8900;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-block;
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

        .quiz-container {
            background-color: white;
            border: 3px solid #DCDCDC;
            border-radius: 15px;
            padding: 1rem;
            width: 100%;
            max-height: 70vh;
            overflow-y: auto;
            box-shadow: 2px 4px 2px 0 rgba(0, 0, 0, 0.2);
            position: relative;
        }

        .edit-quiz {
            border-bottom: 3px solid #DCDCDC;
            font-family: Fredoka !important;
            background: white;
            width: 100%;
            padding: 1rem;
            margin-bottom: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        #select {
            font-family: Fredoka;
            color: black;
            font-size: clamp(18px, 3vw, 25px);
            font-weight: 500;
            line-height: 1.5;
            animation-name: checkbox_fade;
            animation-duration: 1s;
        }

        #selectQuiz {
            background-color: #F8B500;
            border-radius: 8px;
            padding: 0.75rem;
            color: black;
            font-size: clamp(16px, 2.5vw, 20px);
            font-weight: bold;
            cursor: pointer;
            border: none;
            transition: transform 0.2s;
        }

        #selectQuiz:hover {
            transform: scale(1.1);
        }

        #deleteBtn {
            font-family: 'Fredoka' !important;
            background-color: #F8B500;
            border-radius: 8px;
            border: none;
            padding: 0.5rem 1rem;
            color: white;
            font-size: clamp(13px, 2vw, 15px);
            cursor: pointer;
            animation-name: checkbox_fade;
            animation-duration: 1s;
            box-shadow: 0 6px 0 0 #BC8900;
            transition: all 0.3s ease;
        }

        #deleteBtn:hover {
            background-color: #BC8900;
        }

        .quiz-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1rem;
            padding: 1rem 0;
        }

        .quiz-items {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
        }

        .quiz-btn {
            background-color: white;
            width: 100%;
            padding: 1rem;
            border: 2px solid #f8b500;
            border-radius: 8px;
            box-shadow: 0 4px 0 0 #BC8900;
            text-decoration: none;
            text-align: center;
            font-family: Fredoka;
            font-size: clamp(16px, 2.5vw, 22px);
            color: black;
            cursor: pointer;
            transition: all 0.3s ease;
            display: block;
            word-wrap: break-word;
            min-height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .quiz-btn:hover {
            background-color: #f8b500;
            color: white;
        }

        .quiz-btn:active {
            background-color: #f8b500;
            color: white;
            box-shadow: 0 2px 0 0 #BC8900;
        }

        input[type="checkbox"] {
            width: 20px;
            height: 20px;
            border-color: #7D3200;
            cursor: pointer;
            accent-color: #F8B500;
            animation-name: checkbox_fade;
            animation-duration: 1s;
            margin-top: 0.5rem;
        }

        .no-quiz-container {
            text-align: center;
            padding: 3rem 1rem;
        }

        .img-no-quiz {
            width: 130px;
            height: 120px;
            margin: 0 auto 2rem;
            border-radius: 50%;
        }

        .no-quiz-btn {
            font-size: 22px;
            line-height: 1.2;
            color: #666;
        }

        #status {
            position: relative;
            text-align: center;
            background-color: #F8B500;
            border-radius: 10px;
            width: 100%;
            max-width: 400px;
            font-family: Fredoka;
            margin: 0 auto 2rem;
            padding: 1rem;
            color: white;
        }

        #close-btn {
            font-family: Fredoka;
            font-size: 24px;
            position: absolute;
            top: 0.5rem;
            right: 1rem;
            color: white;
            cursor: pointer;
            background: none;
            border: none;
        }

        #close-btn:hover {
            color: #CF5300;
            transition: 0.3s;
        }

        .dropdown-content {
            width: 250px;
            right: 0;
            display: none;
            position: absolute;
            background-color: #F8B500;
            border-radius: 15px;
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
            z-index: 1001;
            padding: 10px 0;
            top: 60px;
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
            background-color: transparent;
            font-family: 'Fredoka';
            font-size: 16px;
            border: 2px solid white;
            color: white;
            width: 90%;
            padding: 12px;
            margin: 8px 5%;
            text-decoration: none;
            display: block;
            text-align: center;
            transition: all 0.3s;
            border-radius: 10px;
            cursor: pointer;
            letter-spacing: 1px;
        }

        .dropdown-content button:hover {
            background-color: white;
            color: #F8B500;
        }

        .show {
            display: block;
        }

        /* Scrollbar styles */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            box-shadow: inset 0 0 5px grey;
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb {
            background: #f8b500;
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #A34404;
        }

        @keyframes checkbox_fade {
            from {opacity: 0}
            to {opacity: 1}
        }

        /* Mobile Responsive Design */
        @media screen and (max-width: 768px) {
            .sidebar {
                width: 280px;
                transform: translateX(-100%);
            }

            .sidebar.mobile-open {
                transform: translateX(0);
            }

            .sidebar.collapsed {
                width: 280px;
            }

            .mobile-toggle {
                display: block;
            }

            .content {
                margin-left: 0;
                width: 100%;
                padding: 1rem 0.5rem;
                padding-top: 4rem;
            }

            .content.expanded {
                margin-left: 0;
                width: 100%;
            }

            .content-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .content-header .actions {
                align-self: flex-end;
            }

            .create-q-button {
                justify-content: center;
                margin-bottom: 1rem;
            }

            .create-q-button a {
                width: 100%;
                max-width: 200px;
                text-align: center;
            }

            .quiz-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .edit-quiz {
                flex-direction: column;
                align-items: stretch;
                gap: 1rem;
            }

            .edit-quiz > div {
                display: flex;
                justify-content: space-between;
                align-items: center;
                width: 100%;
            }

            #status {
                width: 95%;
                margin: 0 auto 1rem;
            }

            .dropdown-content {
                width: 200px;
                right: 0.5rem;
            }
        }

        @media screen and (max-width: 480px) {
            .content {
                padding: 0.5rem 0.25rem;
                padding-top: 4rem;
            }

            .quiz-grid {
                grid-template-columns: 1fr;
                gap: 0.75rem;
                padding: 0.5rem 0;
            }

            .quiz-btn {
                padding: 0.75rem;
                min-height: 50px;
                font-size: 16px;
            }

            .content-header h1 {
                font-size: 1.5rem;
            }

            .create-q-button a {
                padding: 0.5rem 1rem;
                font-size: 14px;
            }

            .edit-quiz {
                padding: 0.75rem;
            }

            #select {
                font-size: 16px;
            }

            #selectQuiz, #deleteBtn {
                font-size: 14px;
                padding: 0.5rem;
            }
        }

        /* Tablet responsive */
        @media screen and (min-width: 769px) and (max-width: 1024px) {
            .quiz-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            }
            
            .content {
                padding: 1.5rem;
            }
        }

        /* Large screen adjustments */
        @media screen and (min-width: 1400px) {
            .quiz-grid {
                grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            }
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
                <a href="t_SubjectsList.php" title="Subject List">
                    <i class="fa-solid fa-list"></i>
                    <span>Subjects</span>
                </a>
                <a href="t_quizDash.php" class="active" title="Quiz Dash">
                    <i class="fa-regular fa-circle-question"></i>
                    <span>Quizzes</span>
                </a>
                <a href="t_rankings.php?subject_id=<?php echo $subject_id; ?>" title="Rankings">
                    <i class="fa-solid fa-ranking-star"></i>
                    <span>Rankings</span>
                </a>
                <a href="t_item-analysis.php?subject_id=<?php echo $subject_id; ?>" title="Item Analysis">
                    <i class="fa-solid fa-chart-line"></i>
                    <span>Item Analysis</span>
                </a>
            </div>
        </div>

        <!-- Content Area -->
        <div class="content" id="content">
            <div class="content-header">
                <h1><?php echo htmlspecialchars($subject['subject_name']); ?></h1><br>
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

            <div class="create-q-button">
                <a href="t_selectquiztype.php?subject_id=<?php echo $subject_id; ?>">Create Quiz</a>
            </div>

            <?php 
            if(isset($_SESSION['status'])) {
                ?>
                <div id="status">
                    <button id="close-btn" onclick="document.getElementById('status').style.display = 'none';">&times;</button>
                    <?php echo $_SESSION['status']; ?>
                </div>
                <?php
                unset($_SESSION['status']);
            }
            ?>

            <div class="quiz-container">
                <form action="delete_quiz.php?subject_id=<?php echo $subject_id; ?>" method="POST">
                    <div class="edit-quiz">
                        <div>
                            <p id="select" style="display: none;">Select Quizzes to Delete</p>
                            <div>
                                <button type="button" onclick="quizCheckbox()" id="selectQuiz">
                                    <i class="fa-regular fa-pen-to-square"></i>
                                </button>
                                <button type="submit" name="delete_quiz_btn" id="deleteBtn" 
                                        onclick="return confirm('Are you sure you want to proceed on deleting the selected item/s?');" 
                                        style="display: none;">
                                    <i class="fa-solid fa-trash"></i> Delete
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="quiz-grid">
                        <?php
                        if ($result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                echo "<div class='quiz-items'>";
                                echo "<div class='quiz-btn'>" . htmlspecialchars($row['title']) . "</div>";
                                echo "<input type='checkbox' name='delete_quiz[]' value='" . $row['quiz_id'] . "' class='quiz-checkbox' style='display: none;'>";
                                echo "</div>";
                            }
                        } else {
                            echo "<div class='no-quiz-container'>";
                            echo "<div class='no-quiz-btn'>No quizzes created yet.</div>";
                            echo "</div>";
                        }
                        ?>
                    </div>
                </form>
            </div>
        </div>
    </div><br>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const content = document.getElementById('content');
            const toggleBtn = document.getElementById('toggleSidebar');

            function updateLayout() {
                if (window.innerWidth <= 768) {
                    // Mobile layout
                    content.classList.add('mobile-full');
                    sidebar.classList.remove('collapsed');
                } else {
                    // Desktop layout
                    content.classList.remove('mobile-full');
                    const isSidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
                    
                    if (isSidebarCollapsed) {
                        sidebar.classList.add('collapsed');
                        content.classList.add('expanded');
                    } else {
                        sidebar.classList.remove('collapsed');
                        content.classList.remove('expanded');
                    }
                }
            }

            // Initialize layout
            updateLayout();

            // Update layout on resize
            window.addEventListener('resize', updateLayout);

            // Desktop sidebar toggle
            if (toggleBtn) {
                toggleBtn.addEventListener('click', function() {
                    if (window.innerWidth > 768) {
                        sidebar.classList.toggle('collapsed');
                        content.classList.toggle('expanded');
                        localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
                    }
                });
            }
        });

        function toggleMobileSidebar() {
            const sidebar = document.getElementById('sidebar');
            if (window.innerWidth <= 768) {
                sidebar.classList.toggle('mobile-open');
            }
        }

        // Close mobile sidebar when clicking outside
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const mobileToggle = document.querySelector('.mobile-toggle');
            
            if (window.innerWidth <= 768) {
                if (!sidebar.contains(event.target) && !mobileToggle.contains(event.target)) {
                    sidebar.classList.remove('mobile-open');
                }
            }
        });

        function quizCheckbox() {
            var checkboxes = document.querySelectorAll('.quiz-checkbox');
            var deleteBtn = document.getElementById("deleteBtn");
            var select = document.getElementById("select");
            
            checkboxes.forEach(function(checkbox) {
                if (checkbox.style.display === "none") {
                    checkbox.style.display = "block";
                } else {
                    checkbox.style.display = "none";
                }
            });

            if (deleteBtn.style.display === "none" && select.style.display === "none") {
                deleteBtn.style.display = "inline-block";
                select.style.display = "block";
            } else {
                deleteBtn.style.display = "none";
                select.style.display = "none";
            }
        }

        function profileDropdown() {
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