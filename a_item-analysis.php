<?php
session_start();
if (!isset($_SESSION['account_number']) || strpos($_SESSION['account_number'], 'A') !== 0) {
    header("Location: admin_login.php");
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

// Fetch teachers for dropdown
$teachers_sql = "SELECT t.account_number, t.fname, t.lname, COUNT(s.subject_id) as subject_count 
                 FROM teachers t 
                 LEFT JOIN subjects s ON t.account_number = s.teacher_id 
                 GROUP BY t.account_number, t.fname, t.lname 
                 ORDER BY t.lname, t.fname";
$teachers_result = $conn->query($teachers_sql);

// Initialize variables
$selected_teacher = '';
$subjects = [];
$quizzes = [];
$analysis_data = [];
$quiz_type_data = [];

// If teacher is selected
if (isset($_GET['teacher_id']) && !empty($_GET['teacher_id'])) {
    $selected_teacher = $_GET['teacher_id'];
    
    // Fetch teacher info
    $teacher_sql = "SELECT fname, lname FROM teachers WHERE account_number = ?";
    $stmt = $conn->prepare($teacher_sql);
    $stmt->bind_param("s", $selected_teacher);
    $stmt->execute();
    $teacher_result = $stmt->get_result();
    $teacher_info = $teacher_result->fetch_assoc();
    $stmt->close();
    
    // Fetch subjects for this teacher
    $subjects_sql = "SELECT * FROM subjects WHERE teacher_id = ? ORDER BY subject_name";
    $stmt = $conn->prepare($subjects_sql);
    $stmt->bind_param("s", $selected_teacher);
    $stmt->execute();
    $subjects_result = $stmt->get_result();
    
    while ($row = $subjects_result->fetch_assoc()) {
        $subjects[] = $row;
    }
    $stmt->close();
    
    // If subject is selected
    if (isset($_GET['subject_id']) && !empty($_GET['subject_id'])) {
        $selected_subject = $_GET['subject_id'];
        
        // Fetch subject info
        $subject_sql = "SELECT subject_name FROM subjects WHERE subject_id = ?";
        $stmt = $conn->prepare($subject_sql);
        $stmt->bind_param("i", $selected_subject);
        $stmt->execute();
        $subject_result = $stmt->get_result();
        $subject_info = $subject_result->fetch_assoc();
        $stmt->close();
        
        // Fetch quizzes for this subject
        $quizzes_sql = "SELECT * FROM quizzes WHERE subject_id = ? ORDER BY title";
        $stmt = $conn->prepare($quizzes_sql);
        $stmt->bind_param("i", $selected_subject);
        $stmt->execute();
        $quizzes_result = $stmt->get_result();
        
        while ($row = $quizzes_result->fetch_assoc()) {
            $quizzes[] = $row;
        }
        $stmt->close();
        
        // Fetch subject performance data (similar to t_item-analysis.php)
        $avgScoreQry = "
            SELECT q.title, 
                   AVG(qa.score) AS average_score,
                   MAX(qa.score) AS high_score,
                   MIN(qa.score) AS low_score,
                   COUNT(DISTINCT qa.attempt_id) as total_attempts
            FROM quizzes q
            LEFT JOIN quiz_attempts qa ON q.quiz_id = qa.quiz_id
            WHERE q.subject_id = ?
            GROUP BY q.quiz_id, q.title";
        
        $stmt = $conn->prepare($avgScoreQry);
        $stmt->bind_param("i", $selected_subject);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $analysis_data[] = [
                'title' => $row['title'],
                'avg_score' => $row['average_score'],
                'high_score' => $row['high_score'],
                'low_score' => $row['low_score'],
                'total_attempts' => $row['total_attempts']
            ];
        }
        $stmt->close();
        
        // Fetch quiz type data
        $qType = "
            SELECT 
                CASE 
                    WHEN q.quiz_type = 'All Zapped' THEN 
                        CASE 
                            WHEN qq.question_type = 'multiple_choice' THEN 'Multiple Choice'
                            WHEN qq.question_type = 'true_or_false' THEN 'True or False'
                            WHEN qq.question_type = 'enumeration' THEN 'Enumeration'
                            WHEN qq.question_type = 'fill_in_the_blanks' THEN 'Fill in the Blanks'
                            WHEN qq.question_type = 'drag_and_drop' THEN 'Drag and Drop'
                            WHEN qq.question_type = 'identification' THEN 'Identification'
                            WHEN qq.question_type = 'matching_type' THEN 'Matching Type'
                            ELSE qq.question_type
                        END
                    ELSE 
                        CASE 
                            WHEN q.quiz_type = 'multiple_choice' THEN 'Multiple Choice'
                            WHEN q.quiz_type = 'true_false' THEN 'True or False'
                            WHEN q.quiz_type = 'enumeration' THEN 'Enumeration'
                            WHEN q.quiz_type = 'fill_in_the_blanks' THEN 'Fill in the Blanks'
                            WHEN q.quiz_type = 'drag_and_drop' THEN 'Drag and Drop'
                            WHEN q.quiz_type = 'identification' THEN 'Identification'
                            WHEN q.quiz_type = 'matching_type' THEN 'Matching Type'
                            ELSE q.quiz_type
                        END
                END AS formatted_type,
                COUNT(qa.account_number) AS total_attempts,
                AVG(qa.score) AS average_score,
                MAX(qa.score) AS highest_score,
                MIN(qa.score) AS lowest_score
            FROM quizzes q
            LEFT JOIN quiz_attempts qa ON q.quiz_id = qa.quiz_id
            LEFT JOIN questions qq ON q.quiz_id = qq.quiz_id AND q.quiz_type = 'All Zapped'
            WHERE q.subject_id = ?
            GROUP BY formatted_type
            ORDER BY formatted_type";
        
        $stmt = $conn->prepare($qType);
        $stmt->bind_param("i", $selected_subject);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $quiz_type_data[] = [
                'quiz_type' => $row['formatted_type'],
                'total_attempts' => $row['total_attempts'],
                'average_score' => $row['average_score'],
                'highest_score' => $row['highest_score'],
                'lowest_score' => $row['lowest_score']
            ];
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="other resources\fontawesome-free-6.5.2-web\css\all.min.css">
    <title>Admin - Item Analysis</title>
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
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

        /* Sidebar styling - COPIED FROM a_Home.php */
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
            margin-top: 15%;
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

        /* Logout button in sidebar */
        .logout-container {
            margin-top: auto;
            padding-top: 1rem;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
        }

        .logout-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #e74c3c;
            color: white;
            border: none;
            padding: 0.8rem 1rem;
            border-radius: 5px;
            font-size: 1rem;
            cursor: pointer;
            font-family: 'Fredoka';
            letter-spacing: 1px;
            width: 100%;
            transition: background-color 0.3s;
            text-decoration: none;
        }

        .logout-btn:hover {
            background-color: #c0392b;
        }

        .logout-btn i {
            margin-right: 0.5rem;
            font-size: 1.2rem;
        }

        .sidebar.collapsed .logout-btn span {
            display: none;
        }

        .sidebar.collapsed .logout-btn i {
            margin-right: 0;
            font-size: 1.5rem;
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

        /* Header Styling */
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

        /* Filter Section */
        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .filter-row {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }

        .filter-group {
            flex: 1;
            min-width: 200px;
        }

        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #555;
        }

        .filter-group select {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background: white;
            font-size: 0.9rem;
            cursor: pointer;
        }

        .filter-group select:focus {
            outline: none;
            border-color: #f8b500;
        }

        /* Charts Container */
        .charts-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .chart-box {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .chart-box h3 {
            color: #333;
            margin-bottom: 15px;
            font-size: 1.2rem;
            border-bottom: 2px solid #f8b500;
            padding-bottom: 8px;
        }

        .chart-container {
            width: 100%;
            height: 400px;
        }

        /* Quizzes List */
        .quizzes-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .quizzes-section h3 {
            color: #333;
            margin-bottom: 15px;
            font-size: 1.2rem;
            border-bottom: 2px solid #f8b500;
            padding-bottom: 8px;
        }

        .quiz-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 15px;
        }

        .quiz-card {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #f8b500;
            transition: all 0.3s;
        }

        .quiz-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .quiz-card h4 {
            color: #333;
            margin-bottom: 10px;
            font-size: 1rem;
        }

        .quiz-stats {
            display: flex;
            justify-content: space-between;
            font-size: 0.85rem;
            color: #666;
        }

        .quiz-link {
            display: inline-block;
            margin-top: 10px;
            color: #f8b500;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .quiz-link:hover {
            text-decoration: underline;
        }

        /* No Data Message */
        .no-data {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }

        .no-data i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #ccc;
        }

        .no-data p {
            font-size: 1.1rem;
        }

        /* Mobile menu toggle - COPIED FROM a_Home.php */
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

        /* Responsive adjustments - COPIED FROM a_Home.php with modifications */
        @media (max-width: 1200px) {
            .sidebar {
                width: 220px;
            }
            .content {
                margin-left: 220px;
                width: calc(100% - 220px);
            }
            
            .charts-container {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 992px) {
            .charts-container {
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
            
            .filter-row {
                flex-direction: column;
            }
            
            .filter-group {
                min-width: 100%;
            }
            
            .chart-container {
                height: 300px;
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
            
            .chart-container {
                height: 250px;
            }
            
            .quiz-list {
                grid-template-columns: 1fr;
            }
        }   
    </style>
</head>
<body>
    <div class="container">
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
                <a href="a_TeacherApproval.php" title="Teacher Approvals">
                    <i class="fa-solid fa-user-check"></i>
                    <span>Teacher Approvals</span>
                </a>
                <a href="a_Classes.php" title="Classes">
                    <i class="fa-solid fa-list"></i>
                    <span>Classes</span>
                </a>
                <a href="a_item-analysis.php" class="active" title="Item Analysis">
                    <i class="fa-solid fa-chart-bar"></i>
                    <span>Item Analysis</span>
                </a>
            </div>
            
            <!-- Logout Button at the bottom of sidebar -->
            <div class="logout-container">
                <a href="admin_logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>

        <!-- Content Area -->
        <div class="content">
            <div class="content-header">
                <div>
                    <h1>Item Analysis Dashboard</h1>
                    <p>Track teacher performance and quiz analytics</p>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <form method="GET" action="">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="teacher_id">Select Teacher</label>
                            <select name="teacher_id" id="teacher_id" onchange="this.form.submit()">
                                <option value="">-- Select Teacher --</option>
                                <?php 
                                // Reset pointer to beginning
                                $teachers_result->data_seek(0);
                                while ($teacher = $teachers_result->fetch_assoc()): ?>
                                    <option value="<?php echo $teacher['account_number']; ?>" 
                                        <?php echo ($selected_teacher == $teacher['account_number']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($teacher['lname'] . ', ' . $teacher['fname']); ?>
                                        (<?php echo $teacher['subject_count']; ?> subjects)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <?php if ($selected_teacher): ?>
                        <div class="filter-group">
                            <label for="subject_id">Select Subject</label>
                            <select name="subject_id" id="subject_id" onchange="this.form.submit()">
                                <option value="">-- Select Subject --</option>
                                <?php foreach ($subjects as $subject): ?>
                                    <option value="<?php echo $subject['subject_id']; ?>" 
                                        <?php echo (isset($_GET['subject_id']) && $_GET['subject_id'] == $subject['subject_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($subject['subject_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <?php if (isset($teacher_info) && $teacher_info): ?>
                <div style="background: white; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                    <h3 style="margin-bottom: 10px; color: #333;">
                        Teacher: <?php echo htmlspecialchars($teacher_info['fname'] . ' ' . $teacher_info['lname']); ?>
                        <?php if (isset($subject_info)): ?>
                            <span style="color: #666; font-weight: normal;"> → Subject: <?php echo htmlspecialchars($subject_info['subject_name']); ?></span>
                        <?php endif; ?>
                    </h3>
                </div>
            <?php endif; ?>

            <?php if (isset($selected_subject)): ?>
                <!-- Charts Section -->
                <div class="charts-container">
                    <!-- Student Performance Chart -->
                    <div class="chart-box">
                        <h3>Student Performance by Quiz</h3>
                        <div id="performanceChart" class="chart-container"></div>
                    </div>

                    <!-- Quiz Type Performance -->
                    <div class="chart-box">
                        <h3>Performance by Question Type</h3>
                        <div id="typeChart" class="chart-container"></div>
                    </div>
                </div>

                <!-- Quizzes List -->
                <div class="quizzes-section">
                    <h3>Available Quizzes</h3>
                    <?php if (!empty($quizzes)): ?>
                        <div class="quiz-list">
                            <?php foreach ($quizzes as $quiz): ?>
                                <div class="quiz-card">
                                    <h4><?php echo htmlspecialchars($quiz['title']); ?></h4>
                                    <div class="quiz-stats">
                                        <span>Type: <?php echo htmlspecialchars($quiz['quiz_type']); ?></span>
                                        <span>Questions: <?php 
                                            // Count questions for this quiz
                                            $q_count_sql = "SELECT COUNT(*) as q_count FROM questions WHERE quiz_id = ?";
                                            $q_stmt = $conn->prepare($q_count_sql);
                                            $q_stmt->bind_param("i", $quiz['quiz_id']);
                                            $q_stmt->execute();
                                            $q_result = $q_stmt->get_result();
                                            $q_count = $q_result->fetch_assoc()['q_count'];
                                            $q_stmt->close();
                                            echo $q_count;
                                        ?></span>
                                    </div>
                                    <a href="a_quiz-item-analysis.php?quiz_id=<?php echo $quiz['quiz_id']; ?>&subject_id=<?php echo $selected_subject; ?>&teacher_id=<?php echo $selected_teacher; ?>" 
                                       class="quiz-link">
                                        View Detailed Analysis <i class="fa-solid fa-arrow-right"></i>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-data">
                            <i class="fas fa-clipboard-list"></i>
                            <p>No quizzes available for this subject</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php elseif ($selected_teacher && empty($subjects)): ?>
                <div class="no-data">
                    <i class="fas fa-book"></i>
                    <p>This teacher has no subjects assigned yet.</p>
                </div>
            <?php elseif (!$selected_teacher): ?>
                <div class="no-data">
                    <i class="fas fa-chart-bar"></i>
                    <p>Please select a teacher to view item analysis</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script type="text/javascript">
        // Sidebar toggle script - COPIED FROM a_Home.php
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

            // Add JavaScript to handle teacher change more robustly
            const teacherSelect = document.getElementById('teacher_id');
            if (teacherSelect) {
                teacherSelect.addEventListener('change', function() {
                    // Clear the subject selection when teacher changes
                    const subjectSelect = document.getElementById('subject_id');
                    if (subjectSelect) {
                        subjectSelect.value = '';
                    }
                    // Submit the form
                    this.form.submit();
                });
            }
        });

        // Google Charts
        google.charts.load('current', {'packages':['corechart']});
        google.charts.setOnLoadCallback(drawCharts);
        
        function drawCharts() {
            <?php if (isset($selected_subject) && !empty($analysis_data)): ?>
                drawPerformanceChart();
            <?php endif; ?>
            
            <?php if (isset($selected_subject) && !empty($quiz_type_data)): ?>
                drawTypeChart();
            <?php endif; ?>
        }
        
        function drawPerformanceChart() {
            var data = new google.visualization.DataTable();
            data.addColumn('string', 'Quiz Title');
            data.addColumn('number', 'Average Score');
            data.addColumn('number', 'High Score');
            data.addColumn('number', 'Low Score');
            data.addColumn('number', 'Total Attempts');
            
            data.addRows([
                <?php foreach ($analysis_data as $data): ?>
                    ['<?php echo addslashes(substr($data['title'], 0, 30)); ?>', 
                     <?php echo (float)$data['avg_score']; ?>, 
                     <?php echo (float)$data['high_score']; ?>, 
                     <?php echo (float)$data['low_score']; ?>,
                     <?php echo (int)$data['total_attempts']; ?>],
                <?php endforeach; ?>
            ]);
            
            var options = {
                title: 'Student Performance by Quiz',
                titleTextStyle: {
                    fontSize: 16,
                    bold: true,
                    color: '#333'
                },
                height: 350,
                chartArea: {
                    width: '80%',
                    height: '70%'
                },
                series: {
                    0: {color: '#4CAF50'},
                    1: {color: '#2196F3'},
                    2: {color: '#F44336'},
                    3: {color: '#FF9800'}
                },
                hAxis: {
                    title: 'Quizzes',
                    slantedText: true,
                    slantedTextAngle: 45
                },
                vAxis: {
                    title: 'Scores / Attempts',
                    minValue: 0
                },
                legend: {
                    position: 'top'
                }
            };
            
            var chart = new google.visualization.ColumnChart(document.getElementById('performanceChart'));
            chart.draw(data, options);
        }
        
        function drawTypeChart() {
            var data = new google.visualization.DataTable();
            data.addColumn('string', 'Question Type');
            data.addColumn('number', 'Average Score');
            data.addColumn('number', 'Total Attempts');
            
            data.addRows([
                <?php foreach ($quiz_type_data as $data): ?>
                    ['<?php echo addslashes($data['quiz_type']); ?>', 
                     <?php echo (float)$data['average_score']; ?>, 
                     <?php echo (int)$data['total_attempts']; ?>],
                <?php endforeach; ?>
            ]);
            
            var options = {
                title: 'Performance by Question Type',
                titleTextStyle: {
                    fontSize: 16,
                    bold: true,
                    color: '#333'
                },
                height: 350,
                chartArea: {
                    width: '80%',
                    height: '70%'
                },
                colors: ['#9C27B0', '#FF9800'],
                hAxis: {
                    title: 'Question Type',
                    slantedText: true,
                    slantedTextAngle: 45
                },
                vAxis: {
                    title: 'Scores / Attempts',
                    minValue: 0
                },
                legend: {
                    position: 'top'
                }
            };
            
            var chart = new google.visualization.ColumnChart(document.getElementById('typeChart'));
            chart.draw(data, options);
        }
        
        // Handle window resize
        window.addEventListener('resize', function() {
            drawCharts();
        });
    </script>
</body>
</html>

<?php
$conn->close();
?>