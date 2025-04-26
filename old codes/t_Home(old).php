<?php
session_start();
if (!isset($_SESSION['account_number']) || strpos($_SESSION['account_number'], 'T') !== 0) {
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

//query to fetch the teacher's profile pic
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

$stmt->close();

//query to fetch the subject_id that will be used in any of the data sa mga cards
$sub_sql = $conn->prepare
            ("SELECT subject_id 
              FROM subjects
              WHERE teacher_id = ?;
            ");
$sub_sql->bind_param("s", $loggedInUser);
$sub_sql->execute();
$sub_result = $sub_sql->get_result();

if ($sub_result->num_rows > 0) {
    $subject_row = $sub_result->fetch_assoc();
    $subject_id = $subject_row['subject_id'];
}
$sub_sql->close();            

//query para ma-count yung overall enrolled students
$studCount = $conn->prepare("SELECT COUNT(*) as count 
              FROM enrollments 
              WHERE subject_id = ?");
$studCount->bind_param("i", $subject_id);
$studCount->execute();
$studResult = $studCount->get_result();

//query para sa top 3 ranking student
$topStudentsQuery = $conn->prepare("
    SELECT 
        st.fname, 
        st.lname, 
        sub.subject_name, 
        MAX(qa.score) as highest_score
    FROM 
        quiz_attempts qa
    JOIN 
        students st ON qa.account_number = st.account_number
    JOIN 
        quizzes q ON qa.quiz_id = q.quiz_id
    JOIN 
        subjects sub ON q.subject_id = sub.subject_id
    WHERE 
        sub.teacher_id = ?     
    GROUP BY 
        st.account_number, 
        sub.subject_id
    ORDER BY 
        highest_score DESC
    LIMIT 3
");

$topStudentsQuery->bind_param("s", $loggedInUser);

if ($topStudentsQuery === false) {
    echo "Prepare failed: " . $conn->error;
    $topStudentsResult = null;
} else {
    $executeResult = $topStudentsQuery->execute();
    
    // Check if execute() failed
    if ($executeResult === false) {
        echo "Execute failed: " . $topStudentsQuery->error;
        $topStudentsResult = null;
    } else {
        $topStudentsResult = $topStudentsQuery->get_result();
    }
}

// Query para sa quiz na may pinakamaraming nag answer nang correct
$topQuizQuery = $conn->prepare("SELECT q.quiz_id, q.title, sub.subject_name, 
    COUNT(DISTINCT sa.student_id) as students_answered_correctly, 
    COUNT(DISTINCT sa.quiz_id) as total_attempts 
FROM student_answers sa 
JOIN quizzes q ON sa.quiz_id = q.quiz_id 
JOIN subjects sub ON q.subject_id = sub.subject_id 
WHERE sa.is_correct = 1 AND sub.teacher_id = ? 
GROUP BY q.quiz_id, q.title, sub.subject_name 
ORDER BY students_answered_correctly DESC 
LIMIT 1");

$topQuizQuery->bind_param("s", $loggedInUser);

if ($topQuizQuery === false) {
    echo "Prepare failed: " . $conn->error;
    $topQuizResult = null;
} else {
    $executeResult = $topQuizQuery->execute();
    
    if ($executeResult === false) {
        echo "Execute failed: " . $topQuizQuery->error;
        $topQuizResult = null;
    } else {
        $topQuizResult = $topQuizQuery->get_result();
    }
}

//query para sa difficult quiz 
$difficultQuizQuery = $conn->prepare("SELECT 
    q.quiz_id, 
    q.title, 
    sub.subject_name,
    COUNT(DISTINCT sa.student_id) as total_students,
    SUM(CASE WHEN sa.is_correct = 1 THEN 1 ELSE 0 END) as correct_answers,
    COUNT(DISTINCT sa.student_id) - SUM(CASE WHEN sa.is_correct = 1 THEN 1 ELSE 0 END) as incorrect_answers,
    ROUND(
        (COUNT(DISTINCT sa.student_id) - SUM(CASE WHEN sa.is_correct = 1 THEN 1 ELSE 0 END)) * 100.0 / 
        COUNT(DISTINCT sa.student_id), 
        2
    ) as difficulty_percentage
    FROM 
        quizzes q
    JOIN 
        subjects sub ON q.subject_id = sub.subject_id
    JOIN 
        student_answers sa ON q.quiz_id = sa.quiz_id
    WHERE 
        sub.teacher_id = ?
    GROUP BY 
        q.quiz_id, 
        q.title, 
        sub.subject_name
       
    ORDER BY 
        difficulty_percentage DESC  -- Sort by the highest percentage of incorrect answers
    LIMIT 3");  // Fetch top 3 most difficult quizzes

$difficultQuizQuery->bind_param("s", $loggedInUser);

if ($difficultQuizQuery === false) {
    echo "Prepare failed: " . $conn->error;
    $result = null;
} else {
    $result = $difficultQuizQuery->execute();
    
    if ($result === false) {
        echo "Execute failed: " . $difficultQuizQuery->error;
        $result = null;
    } else {
        $result = $difficultQuizQuery->get_result();
    }
}

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
            font-family: Arial, sans-serif;
        }

        body, html {
            font-family: 'Tilt Warp Regular' !important;
            height: 100%;
        }

        .container {
            font-family: Tilt Warp Regular;
            display: flex;
            height: 100vh;
        }

        .sidebar {
            position: fixed;
            height: 100vh !important;
            width: 250px;
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
            margin-bottom: 18rem;
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
            margin-left: 15%;
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

        .content-header .actions a {
            background-color: #F8B500;
            color: #ffffff;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            font-size: 1rem;
            text-decoration: none;
            cursor: pointer;
            margin-right: 1rem;
            font-family: Tilt Warp Regular;
        }

        .content-header .actions a:hover {
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
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
        }

        .cards p, .cards h3, .cards a, .ranking-card h3{
            font-family: 'Tilt Warp Regular' !important;
        }

        .card {
            background-color: #ffffff;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            height: 200px;
        }

        .enroll-card {
            background-color: #ffffff;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            height: 200px;
        }

        .enroll-card p {
            font-size: 4rem;
            text-align: center;
            margin-top: 5%;
            color: #4d4d4d;
        }

        .enroll-card a {
            text-decoration: none;
            color: #f8b500;
            float: right;
        }

        .enroll-card .user{
            float: left;
            width: fit-content;
            font-size: 30px;
            color: #F8B500;
            border-radius: 100%;
            border: 3px solid #F8B500;
            padding: 2rem;
            line-height: 1;
            margin-top: 3%;
        }

        .enroll-card h3 {
            float: right;
        }

        #enroll-card-head{
            display: flex;
            width: fit-content;
            vertical-align: middle;
            border: 1px solid black;
        }

        .success-quiz-card {
            background-color: #ffffff;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            height: 200px;
        }

        .success-quiz-card .thumb {
            float: left;
            width: fit-content;
            font-size: 30px;
            color: #F8B500;
            border-radius: 100%;
            border: 3px solid #F8B500;
            padding: 2rem;
            line-height: 1;
            margin-top: 3%;
        }

        .success-quiz-card h3 {
            float: right;
            letter-spacing: .5px;
        }

        .quiz-details {
            float: right;
            width: 50%;
            background: transparent;
            margin-bottom: 0.5%;
            text-align: center !important;
        }
        
        .quiz-details h4 {
            font-family: 'Tilt Warp Regular';
            text-align: center;
            font-size: 20px;
            margin-top: 5%;
            color: #4d4d4d;
        }

        .success-quiz-card p {
            font-size: 1rem;
            text-align: left;
            margin-left: 5%;
            margin-top: 5%;
        }

        .success-quiz-card  a{
            text-decoration: none;
            color: #f8b500;
            float: right;
        }

        #item-link {
            margin-top: 2%;
        }

        #sub-link {
            font-family: 'Tilt Warp Regular';
            display: flex !important;
            margin-bottom: -1%;

        }

        #sub-link a{
            display: flex !important;
        }


        .diff-quiz-card {
            background-color: #ffffff;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            height: 200px;
        }

        .diff-quiz-card .thumb-down {
            float: left;
            width: fit-content;
            font-size: 30px;
            color: #F8B500;
            border-radius: 100%;
            border: 3px solid #F8B500;
            padding: 2rem;
            line-height: 1;
            margin-top: 3%;
        }

        .diff-quiz-card h3 {
            float: right;
        }

        .difficult-quizzes {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .quiz-card {
            background-color: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .quiz-title {
            margin-top: 4%;
            font-family: 'Tilt Warp Regular';
            font-weight: bold;
            color: #4d4d4d;
        }
        .diff-quiz-details {
            display: flex;
            justify-content: space-between;
        }

        .difficulty-warning {
            color: red;
            font-family: 'Tilt Warp Regular';
            float: right;
            font-weight: lighter;
            font-size: 15px;
        }

        #sub strong, #studs strong,  #correct strong, #incorrect strong{
            font-family: 'Tilt Warp Regular';
            font-size: small;
        }

        #sub {
            font-family: 'Tilt Warp Regular';
            margin-left: 10px;
            font-size: 15px;
            margin-right: 5%;
            float: right;
            
        }

        #studs {
            font-family: 'Tilt Warp Regular';
            font-size: 15px;
            margin-top: 50%;
            margin-left: 10%;
            margin-right: 30%;
            text-align: right;

        }

        #correct {
            font-family: 'Tilt Warp Regular';
            margin-top: 50%;
            margin-right: 30%;
            font-size: 15px;
            text-align: right;
        }

        #incorrect {
            font-family: 'Tilt Warp Regular';
            margin-top: 50%;
            font-size: 15px;
            text-align: right;
        }

        .ranking-card {
            background-color: #ffffff;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            height: 20rem;
            width: 100%;
            height: 60%;
            margin: auto !important;
        }

        .ranking-card p {
            font-family: 'Tilt Warp Regular' !important;
            font-size: 4rem;
            text-align: center;
            margin-top: 5%;
        }

        #scores-cont {
            font-family: 'Tilt Warp Regular';
            max-width: 90%;
            height: 80%;
            padding: 10px;
            margin: auto;
        }

        /* Table header styles */
        .ranking-header {
            font-family: 'Tilt Warp Regular';
            display: grid;
            grid-template-columns: 1fr 2fr 1fr;
            padding: 0.5rem 2rem;
            margin-bottom: 1rem;
        }

        .ranking-header span {
            font-family: 'Tilt Warp Regular';
            font-weight: bold;
            font-size: 20px;
            color: #f8b500;
            text-align: center;
        }

        /* Ranking rows container */
        .ranking-rows {
            font-family: 'Tilt Warp Regular';
            display: flex;
            flex-direction: column;
            gap: 0.8rem;
        }

        /* Individual ranking row */
        .ranking-row {
            font-family: 'Tilt Warp Regular';
            display: grid;
            grid-template-columns: 1fr 2fr 1fr;
            padding: 0.5rem 1rem;
            border-radius: 15px;
            align-items: center;
            font-weight: 500;

        }

        .ranking-row div {
            cursor: pointer;
        }

        .ranking-row-noquiz {
            font-family: 'Tilt Warp Regular';
            color: #666;
            font-style: italic;
            margin-top: 5%;
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

        /* Name styles */
        .stud-name {
            font-family: 'Tilt Warp Regular';
            font-size: 1.2rem;
            text-align: center;
        }

        .subject {
            font-family: 'Tilt Warp Regular';
            font-size:  1rem;
            text-align: center;
            color: #444;
        }
        
        /* Score styles */
        .score {
            font-family: 'Tilt Warp Regular';
            font-size: 1.5rem;
            font-weight: bold;
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
            font-family: 'Tilt Warp Regular';
            font-size: 18px;
            font-weight: lighter;
            border: 2px solid white !important;
            color: white;
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
        <!-- Sidebar -->
        <div class="sidebar">
            <header>
                <div class="logo"><img src="img/logo4.png" width="200px" height="80px"></div>
            </header>
            <hr>
            <div class="menu">
                <a href="t_Home.php" class="active"><i class="fa-solid fa-house"></i>Dashboard</a>
                <a href="t_Students.php"><i class="fa-regular fa-address-book"></i>Students</a>
                <a href="t_SubjectsList.php"><i class="fa-solid fa-list"></i>Subjects</a>
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
                    <a href="t_selectquiztype.php">Create a Quiz?</a>
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
            <div class="cards">
                <div class="enroll-card">
                    <i class="fa-solid fa-users user"></i>
                    <h3>Total Students Enrolled: </h3>
                    
                    <?php
                    if ($studResult) {
                        $row = $studResult->fetch_assoc();
                    }
                    ?>
                    <br>
                    <p><?php echo $row['count']; ?></p>

                    <hr style="border-color: #cccc; margin-bottom: 4%;">
                    <a href="t_Students.php">Enroll Students <i class="fa-solid fa-angles-right"></i></a>
                    
                </div>

                <div class="success-quiz-card">
                    <i class="fa-solid fa-thumbs-up thumb"></i>
                        <h3>Most Successful Quiz</h3> 
                        
                        <br>
                        
                        <div class="quiz-details">
                            <?php if ($topQuizResult && $topQuizResult->num_rows > 0 ): ?>
                                <?php $topQuiz = $topQuizResult->fetch_assoc(); ?>
                                    <h4 style="margin-bottom: 2%;"><?php echo htmlspecialchars($topQuiz['title']); ?></h4>    

                                <div id="sub-link">
                                    <p style="font-family: 'Tilt Warp Regular'; margin:auto; float: left; color:#444;">Subject:      
                                        <a href="t_quizDash.php?subject_id=<?php echo $subject_id; ?>"><?php echo htmlspecialchars($topQuiz['subject_name']); ?></a></p> 
                                </div>    
                            <?php else: ?>
                                <p style="color: #6666;" >No quiz data available</p>
                            <?php endif;?>    
                        </div>
                    <br><br><br><br><br>
                        
                    <hr style="border-color: #cccc; align-self:baseline; margin-top: 2%;">
                    <a id="item-link" href="t_quiz-item-analysis.php?quiz_id=<?php echo htmlspecialchars($topQuiz['quiz_id']); ?>">See Item Analysis <i class="fa-solid fa-angles-right"></i></a>
                </div>

                <div class="diff-quiz-card">
                    <h3>Difficult Quizzes</h3>
                        <i class="fa-solid fa-thumbs-down thumb-down"></i>
                        <br>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($quiz = $result->fetch_assoc()): ?>
                                    <div class="quiz-title">
                                        <h3><?php echo htmlspecialchars($quiz['title']); ?></h3>
                                        <span class="difficulty-warning">
                                            (Difficulty: <?php echo number_format($quiz['difficulty_percentage'], 2); ?>%)
                                        </span>
                                        <br>
                                        <div id="sub">
                                            <strong>Subject:</strong>
                                            <?php echo htmlspecialchars($quiz['subject_name']); ?>
                                        </div>
                                    </div>

                                    <div class="diff-quiz-details">
                                        <div id="studs">
                                            <strong>Total Students:</strong> 
                                            <?php echo $quiz['total_students']; ?>
                                        </div>

                                        <div id="correct">
                                            <strong>Correct Answers:</strong>
                                            <?php echo $quiz['correct_answers']; ?>
                                        </div>

                                        <div id="incorrect">
                                            <strong>Incorrect Answers:</strong> 
                                            <?php echo $quiz['incorrect_answers']; ?>
                                        </div>   
                                    </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p style="color: #666; text-align:center; margin-top: 15%;">No challenging quizzes found.</p>
                        <?php endif; ?>
                </div>
            </div>

            <br>

                <div class="ranking-card">
                    <h3>Top Ranking Students: </h3>
                    
                    <div id="scores-cont">
                        <div class="ranking-header">
                            <span>Student's Name</span>
                            <span>Subject</span>
                            <span>Highest Score</span>
                        </div>
                    
                        <div class="ranking-rows">
                            <?php 
                            if ($topStudentsResult && $topStudentsResult->num_rows > 0) {
                                while ($student = $topStudentsResult->fetch_assoc()) { ?>

                                <div class="ranking-row">
                                    <div class="stud-name"><i class="fa-solid fa-medal"></i>  <?php echo htmlspecialchars($student['fname']); ?>
                                    <?php echo htmlspecialchars($student['lname']); ?>
                                    </div>
                                    <div class="subject"><?php echo htmlspecialchars($student['subject_name']); ?></div>
                                    <div class="score"><?php echo htmlspecialchars($student['highest_score']); ?></div>
                                </div>

                            <?php }
                            } else { ?>
                                <div class="ranking-row-noquiz" style="text-align: center; grid-column: : 1 / -1;">No Rankings Yet
                                </div>        
                            <?php } ?>
                        </div>
                    </div>
                    
                    <hr>
                </div>    
                <br><br><br>
        </div>
    </div>

<script type="text/javascript">
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
// Close the statement and connection
$studCount->close();
$topQuizQuery->close();
$difficultQuizQuery->close();
$topStudentsQuery->close();
$conn->close();
?>