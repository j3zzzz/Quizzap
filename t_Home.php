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
$studCount = $conn->prepare("SELECT COUNT(DISTINCT e.student_id) as count 
              FROM enrollments e
              JOIN subjects s ON e.subject_id = s.subject_id
              WHERE s.teacher_id = ?");
$studCount->bind_param("s", $loggedInUser);
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
    <link rel="stylesheet" href="other resources\fontawesome-free-6.5.2-web\css\all.min.css">
    <title>QuizZap Dashboard</title>
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

        /* Sidebar styling */
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
            margin-top: 40%;
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

        .content-header .actions {
            display: flex;
            align-items: center;
            gap: 1rem;
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
            font-family: 'Fredoka';
            white-space: nowrap;
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
            cursor: pointer;
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1rem;
        }

        .cards p, .cards h3, .cards a, .ranking-card h3 {
            font-family: 'Fredoka' !important;
        }

        .enroll-card {
            font-family: 'Fredoka';
            background-color: #ffffff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.15);
            min-height: 200px;
            display: flex;
            flex-direction: column;
        }

        .header{
            float: left;
            display: flex ;
        }

        .enroll-card p {
            font-family: 'Fredoka';
            font-weight: 500;
            font-size: 4rem;
            text-align: center;
            margin: auto;
            color: #4d4d4d;
        }

        .enroll-card a {
            font-family: 'Fredoka';
            font-weight: 600;
            text-decoration: none;
            color: #f8b500;
            align-self: flex-end;
            margin-top: auto;
        }

        h3 {
            font-family: 'Fredoka';
            font-weight: bold;
            font-size: 1.5rem;
            margin: auto;
        }

        .success-quiz-card {
            font-family: 'Fredoka';
            background-color: #ffffff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.15);
            min-height: 200px;
            display: flex;
            flex-direction: column;
        }

        .icon {
            float: left;
            font-size: 30px;
            color: #F8B500;
            border-radius: 100%;
            border: 3px solid #F8B500;
            padding: 2rem;
            margin-right: 5%;
            flex-shrink: 0;
        }

        .quiz-details {
            margin-top: 3rem;
            margin-left: 5%;
            margin-bottom: 2rem;
            flex: 1;
            min-width: 200px;
        }

        .quiz-details h4 {
            font-family: 'Fredoka';
            font-size: 1.5rem;
            color: #4d4d4d;
        }

        .success-quiz-card p {
            font-size: 1.5rem;
        }

        .success-quiz-card a {
            font-weight: 600;
            text-decoration: none;
            color: #f8b500;
            margin-top: auto;
            align-self: flex-end;
        }

        #item-link {
            margin-top: 1rem;
        }

        #sub-link {
            font-family: 'Fredoka';
            display: flex;
            flex-wrap: wrap;
            align-items: center;
        }

        #sub-link p {
            font-family: 'Fredoka';  
            color: #4d4d4d;
            font-weight: bold;
        }

        #sub-link a {
            font-family: 'Fredoka';
            font-weight: 500;
            text-decoration: none;
            color: #f8b500;
            font-size: 1.5rem;
            margin-left: 2%;
        }

        .diff-quiz-card {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.15);
            min-height: 250px;
        }

        .ranking-card {
            background-color: #ffffff;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.15);
            min-height: 300px;
        }

        .ranking-card p {
            font-family: 'Fredoka' !important;
            font-size: 4rem;
            text-align: center;
            margin: 1rem 0;
        }

        #scores-cont {
            font-family: 'Fredoka';
            width: 100%;
            padding: 10px;
        }

        /* Table header styles */
        .ranking-header {
            font-family: 'Fredoka';
            display: grid;
            grid-template-columns: 1fr 2fr 1fr;
            padding: 0.5rem;
            margin-bottom: 1rem;
            gap: 0.5rem;
        }

        .ranking-header span {
            font-family: 'Fredoka';
            font-weight: bold;
            font-size: 1rem;
            color: #f8b500;
            text-align: center;
        }

        /* Ranking rows container */
        .ranking-rows {
            font-family: 'Fredoka';
            display: flex;
            flex-direction: column;
            gap: 0.8rem;
        }

        /* Individual ranking row */
        .ranking-row {
            font-family: 'Fredoka';
            display: grid;
            grid-template-columns: 1fr 2fr 1fr;
            padding: 0.5rem;
            border-radius: 15px;
            align-items: center;
            font-weight: 500;
            gap: 0.5rem;
        }

        .ranking-row div {
            cursor: pointer;
        }

        .ranking-row-noquiz {
            font-family: 'Fredoka';
            color: #6666;
            text-align: center;
            padding: 1rem;
        }

        /* Different background colors for each position */
        .ranking-row:nth-child(1) {
            background: #ffc62c;
        }

        .ranking-row:nth-child(1) i {
            color: #FFD700;
            border-radius: 100%;
            background-color: white;
            padding: 5px;
            text-align: center;
            margin-right: 5%;
            font-size: 0.8rem;
        }

        .ranking-row:nth-child(2) {
            background: #ffd460;
        }

        .ranking-row:nth-child(2) i {
            color: #C0C0C0;
            border-radius: 100%;
            background-color: white;
            padding: 5px;
            text-align: center;
            margin-right: 5%;
            font-size: 0.8rem;
        }

        .ranking-row:nth-child(3) {
            background: #ffe293;
        }

        .ranking-row:nth-child(3) i {
            color: #CD7F32;
            border-radius: 100%;
            background-color: white;
            padding: 5px;
            text-align: center;
            margin-right: 5%;
            font-size: 0.8rem;
        }

        .ranking-row:nth-child(n+4) {
            background: #ffe9ad;
        }

        /* Name styles */
        .stud-name {
            font-family: 'Fredoka';
            font-size: 1rem;
            text-align: center;
        }

        .subject {
            font-family: 'Fredoka';
            font-size: 0.9rem;
            text-align: center;
            color: #444;
        }

        /* Score styles */
        .score {
            font-family: 'Fredoka';
            font-size: 1.2rem;
            font-weight: bold;
            text-align: center;
        }

        .dropdown-content {
            width: 250px;
            right: 1%;
            display: none;
            position: absolute;
            background-color: #F8B500;
            border-radius: 15px;
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
            z-index: 1;
            padding: 10px 0;
            top: 100px;
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
            font-family: 'Fredoka';
            font-size: 16px;
            font-weight: lighter;
            border: 2px solid white !important;
            color: white;
            width: 86% !important;
            padding: 10px 15px !important;
            margin: 8px 20px !important;
            text-decoration: none;
            display: block;
            text-align: center;
            background-color: transparent;
            transition: background-color 0.3s, color 0.3s;
            border-radius: 10px;
            cursor: pointer;
            letter-spacing: 1px;
            box-sizing: border-box;
        }

        .dropdown-content a:hover, .dropdown-content button:hover {
            background-color: white !important;
            color: #F8B500;
        }

        .show {
            display: block;
        }

        /* Mobile menu toggle */
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

        /* Difficulty Quiz Card Styles */
        .difficulty-header {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .difficulty-header i {
            font-size: 1.8rem;
            color: #e74c3c;
            margin-right: 1rem;
        }

        .difficulty-header h3 {
            margin: 0;
            color: #333;
        }

        .difficulty-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .difficulty-item {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border-left: 4px solid #e74c3c;
        }

        .difficulty-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.12);
        }

        .difficulty-title {
            margin-bottom: 1rem;
        }

        .difficulty-title h4 {
            font-size: 1.2rem;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .difficulty-percentage {
            display: inline-block;
            background: #ffebee;
            color: #e74c3c;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .difficulty-subject {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
            color: #666;
        }

        .difficulty-subject i {
            margin-right: 0.5rem;
            color: #f8b500;
        }

        .difficulty-subject a {
            color: #f8b500;
            text-decoration: none;
            font-weight: 500;
        }

        .difficulty-subject a:hover {
            color:rgb(234, 177, 22);
        }

        .difficulty-stats {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            gap: 0.8rem;
        }

        .stat-item {
            text-align: center;
            flex: 1;
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.5rem;
            font-size: 1rem;
        }

        .stat-icon.correct {
            background: rgba(46, 204, 113, 0.1);
            color: #2ecc71;
        }

        .stat-icon.incorrect {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }

        .stat-icon.total {
            background: rgba(52, 152, 219, 0.1);
            color: #3498db;
        }

        .stat-value {
            font-weight: bold;
            font-size: 1.2rem;
            color: #333;
        }

        .stat-label {
            font-size: 0.8rem;
            color: #777;
        }

        .analyze-btn {
            display: block;
            text-align: center;
            background: #f8b500;
            color: white;
            padding: 0.6rem;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            transition: background 0.3s;
        }

        .analyze-btn:hover {
            background: #e5941f;
            color: white;
        }

        .no-difficulty {
            text-align: center;
            padding: 2rem;
            color: #666;
        }

        .no-difficulty i {
            font-size: 3rem;
            color: #f8b500;
            margin-bottom: 1rem;
        }

        .no-difficulty p {
            font-size: 1.1rem;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .difficulty-grid {
                grid-template-columns: 1fr;
            }
            
            .difficulty-stats {
                flex-wrap: wrap;
            }
            
            .stat-item {
                flex: 0 0 calc(33.333% - 0.8rem);
            }
        }

        @media (max-width: 480px) {
            .stat-item {
                flex: 0 0 100%;
                margin-bottom: 1rem;
            }
            
            .stat-item:last-child {
                margin-bottom: 0;
            }
        }

        /* Responsive adjustments */
        @media (max-width: 1200px) {
            .sidebar {
                width: 220px;
            }
            .content {
                margin-left: 220px;
                width: calc(100% - 220px);
            }
        }

        @media (max-width: 992px) {
            .cards {
                grid-template-columns: 1fr;
            }
            .quiz-title {
                flex-direction: column;
                gap: 0.5rem;
            }
            .quiz-title h4 {
                margin-right: 0;
            }
            #sub {
                margin-left: 0;
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
            
            .content-header .actions {
                width: 100%;
                justify-content: space-between;
                margin-top: 1rem;
            }
            
            .dropdown-content {
                right: 10px;
                width: 200px;
            }
            
            .enroll-card .icon, 
            .success-quiz-card .icon, 
            .diff-quiz-card .icon {
                padding: 1rem;
                font-size: 20px;
                margin-right: 0.5rem;
            }
            
            .enroll-card p, 
            .ranking-card p {
                font-size: 3rem;
            }
            
            .ranking-header span {
                font-size: 0.9rem;
            }
            
            .stud-name {
                font-size: 0.9rem;
            }
            
            .score {
                font-size: 1rem;
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
            
            .content-header .actions a {
                padding: 0.5rem;
                font-size: 0.9rem;
            }
            
            .enroll-card, 
            .success-quiz-card, 
            .diff-quiz-card, 
            .card {
                padding: 1rem;
            }
            
            .enroll-card h3, 
            .success-quiz-card h3, 
            .diff-quiz-card h3 {
                font-size: 1.2rem;
            }
            
            .quiz-details h4 {
                font-size: 1.2rem;
            }
            
            .ranking-header {
                grid-template-columns: 0.5fr 2fr 1fr;
            }
            
            .dropdown-content {
                width: 180px;
                right: 5px;
            }
            
            .dropdown-content button {
                font-size: 14px;
                padding: 8px 10px !important;
            }
        }    
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
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
                <a href="t_Home.php" class="active" title="Dashboard">
                    <i class="fa-solid fa-house"></i>
                    <span>Dashboard</span>
                </a>
                <a href="t_Students.php" title="Students">
                    <i class="fa-regular fa-address-book"></i>
                    <span>Students</span>
                </a>
                <a href="t_SubjectsList.php" title="Subjects">
                    <i class="fa-solid fa-list"></i>
                    <span>Subjects</span>
                </a>
            </div>
        </div>

        <!-- Content Area -->
        <div class="content">
            <div class="content-header">
                <div>
                    <h1>Hi, <?php echo htmlspecialchars($_SESSION['fname']); ?>!</h1>
                    <p>Create quizzes. Get their scores. Assess their knowledge.</p>
                </div>
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
            <br>        
            <div class="cards">
                <div class="enroll-card">
                    <div class="header">
                        <i class="fa-solid fa-users icon"></i> <h3>Total Students Enrolled: </h3>
                    </div>
        
                            <?php
                            if ($studResult) {
                                $row = $studResult->fetch_assoc();
                            }
                            ?>

                            <p><?php echo $row['count']; ?></p>  
                        <br>
                        <hr style="border-color: #cccc; margin-bottom: 2%; margin-top: 1%;">
                        <a href="t_Students.php">Enroll Students <i class="fa-solid fa-angles-right"></i></a>
                        
                </div>
                <div class="success-quiz-card">
                    <div class="header">
                        <i class="fa-solid fa-thumbs-up icon"></i> 
                        <h3 style="margin: auto;">Quiz with the Most High Scores</h3>   
                    </div>                      
                        <div class="quiz-details">
                            <?php if ($topQuizResult && $topQuizResult->num_rows > 0 ): ?>
                                <?php $topQuiz = $topQuizResult->fetch_assoc(); ?>

                                    <h4 style="margin-bottom: 2%;">Quiz: <?php echo htmlspecialchars($topQuiz['title']); ?></h4>    

                                <div id="sub-link">
                                    <p>Subject: </p>      
                                    <a href="t_quizDash.php?subject_id=<?php echo $subject_id; ?>"><?php echo htmlspecialchars($topQuiz['subject_name']); ?></a>
                                </div>    
                            <?php else: ?>
                                <p style="color: #6666;" >No quiz data available</p>
                            <?php endif;?>    
                            </div>
                        
                    <hr style="border-color: #cccc; margin-bottom: 2%; margin-top: 1%;">
                    <a id="item-link" href="t_quiz-item-analysis.php?quiz_id=<?php echo htmlspecialchars($topQuiz['quiz_id']); ?>">See Item Analysis <i class="fa-solid fa-angles-right"></i></a>
                </div>
            </div>

            <br>

            <div class="diff-quiz-card">
                <div class="difficulty-header">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <h3>Quizzes with Low Scores</h3>
                </div>
                
                <div class="difficulty-container">        
                    <?php if ($result->num_rows > 0): ?>
                        <div class="difficulty-grid">
                            <?php while ($quiz = $result->fetch_assoc()): ?>
                                <div class="difficulty-item">
                                    <div class="difficulty-title">
                                        <h4><?php echo htmlspecialchars($quiz['title']); ?></h4>
                                    </div>
                                    
                                    <div class="difficulty-subject">
                                        <i class="fa-solid fa-book"></i>
                                        <a href="t_quizDash.php?subject_id=<?php echo $subject_id; ?>">
                                            <?php echo htmlspecialchars($quiz['subject_name']); ?>
                                        </a>
                                    </div>
                                    
                                    <div class="difficulty-stats">
                                        <div class="stat-item">
                                            <div class="stat-icon correct">
                                                <i class="fa-solid fa-circle-check"></i>
                                            </div>
                                            <div class="stat-value"><?php echo $quiz['correct_answers']; ?></div>
                                            <div class="stat-label">Correct</div>
                                        </div>
                                        
                                        <div class="stat-item">
                                            <div class="stat-icon incorrect">
                                                <i class="fa-solid fa-circle-xmark"></i>
                                            </div>
                                            <div class="stat-value"><?php echo $quiz['incorrect_answers']; ?></div>
                                            <div class="stat-label">Incorrect</div>
                                        </div>
                                        
                                        <div class="stat-item">
                                            <div class="stat-icon total">
                                                <i class="fa-solid fa-users"></i>
                                            </div>
                                            <div class="stat-value"><?php echo $quiz['total_students']; ?></div>
                                            <div class="stat-label">Students</div>
                                        </div>
                                    </div>
                                    
                                    <a href="t_quiz-item-analysis.php?quiz_id=<?php echo htmlspecialchars($quiz['quiz_id']); ?>" class="analyze-btn">
                                        Analyze Questions <i class="fa-solid fa-arrow-right"></i>
                                    </a>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-difficulty">
                            <i class="fa-solid fa-face-smile-beam"></i>
                            <p>No challenging quizzes found. Great job!</p>
                        </div>
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
                                <div class="ranking-row-noquiz" style="text-align: center; grid-column: 1 / -1;">No Rankings Yet
                                </div>        
                            <?php } ?>
                        </div>
                    </div>
                </div>    
        </div>
    </div>

<script type="text/javascript">

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
<?php
// Close the statement and connection
$studCount->close();
$topQuizQuery->close();
$difficultQuizQuery->close();
$topStudentsQuery->close();
$conn->close();
?>