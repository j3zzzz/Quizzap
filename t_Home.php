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
    $profile_pic = $row['profile_pic'] ? $row['profile_pic'] : 'default-profile.jpg';
} else {
    $profile_pic = 'default-profile.jpg';
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
    COUNT(DISTINCT CASE WHEN sa.is_correct = 1 THEN sa.student_id END) as correct_answers,
    COUNT(DISTINCT sa.student_id) - COUNT(DISTINCT CASE WHEN sa.is_correct = 1 THEN sa.student_id END) as incorrect_answers,
    ROUND(
        (COUNT(DISTINCT sa.student_id) - COUNT(DISTINCT CASE WHEN sa.is_correct = 1 THEN sa.student_id END)) * 100.0 / 
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
        difficulty_percentage DESC
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
            transition: background-color 0.3s, color 0.3s;
            overflow-x: hidden;
        }

        body.dark-mode {
            background-color: #1a1a1a;
            color: #e0e0e0;
        }

        .container {
            font-family: 'Fredoka';
            display: flex;
            min-height: 100vh;
            flex-direction: column;
            width: 100%;
        }

        /* Sidebar styling - Hidden on mobile */
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
            z-index: 1000;
            overflow-y: auto;
        }

        body.dark-mode .sidebar {
            background-color: #333;
        }

        .sidebar.collapsed {
            width: 70px;
            padding: 2rem 0.5rem;
        }

        .sidebar .logo {
            margin-bottom: 1rem;
            margin-left: 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
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
            min-height: 44px;
            min-width: 44px;
        }

        .toggle-btn:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .sidebar .menu {
            margin-top: 40%;
            display: flex;
            flex-direction: column;
            flex-grow: 1;
            gap: 0.5rem;
        }

        .sidebar.collapsed .menu {
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
            width: 100%;
            min-height: 50px;
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
            font-size: clamp(16px, 1.5vw, 20px);
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

        body.dark-mode .sidebar .menu a:hover,
        body.dark-mode .sidebar .menu a.active {
            background-color: #444;
            color: #f8b500;
        }

        .sidebar .menu a i {
            margin-right: 0.5rem;
            min-width: 20px;
            text-align: center;
            font-size: clamp(1rem, 1.2vw, 1.5rem);
            flex-shrink: 0;
        }

        .sidebar.collapsed .menu a i {
            margin-right: 0;
            font-size: 1.2rem;
        }

        .sidebar.collapsed .logo-img {
            display: none;
        }

        .sidebar.collapsed .logo-icon {
            display: block !important;
        }

        .sidebar.collapsed hr {
            margin: 0.5rem auto;
            width: 50%;
        }

        /* Top Navigation for ALL screen sizes */
        .top-nav {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            background-color: #f8b500;
            padding: 1rem;
            z-index: 1000;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            align-items: center;
            justify-content: space-between;
            height: 70px;
        }
        
        body.dark-mode .top-nav {
            background-color: #333;
        }
        
        .top-nav .logo {
            display: flex;
            align-items: center;
        }
        
        .top-nav .logo img {
            height: 40px;
            width: auto;
        }
        
        .top-nav .menu {
            display: flex !important;
            position: static;
            flex-direction: row;
            background: none;
            box-shadow: none;
            width: auto;
            padding: 0;
            margin: 0;
            gap: 1.5rem;
        }
        
        .top-nav .menu a {
            color: #ffffff;
            text-decoration: none;
            padding: 0.75rem;
            display: flex;
            align-items: center;
            font-size: 1rem;
            border-radius: 8px;
            transition: background 0.3s;
            min-height: 44px;
            min-width: 44px;
            justify-content: center;
            position: relative;
        }
        
        .top-nav .menu a i {
            font-size: 1.4rem;
            margin-right: 0;
        }
        
        .top-nav .menu a span {
            display: none;
        }
        
        .top-nav .menu a:hover,
        .top-nav .menu a.active {
            background-color: rgba(255, 255, 255, 0.2);
        }
        
        .top-nav .menu a::after {
            content: attr(title);
            position: absolute;
            bottom: -30px;
            left: 50%;
            transform: translateX(-50%);
            background-color: #333;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            white-space: nowrap;
            opacity: 0;
            transition: opacity 0.3s;
            pointer-events: none;
        }
        
        .top-nav .menu a:hover::after {
            opacity: 1;
        }
        
        /* Profile in top nav */
        .top-nav-profile {
            display: flex;
            align-items: center;
            position: relative;
        }
        
        .top-nav-profile .profile {
            width: 45px;
            height: 45px;
            background-color: #ffffff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #f5a623;
            font-size: 1.5rem;
            cursor: pointer;
            flex-shrink: 0;
            position: relative;
            border: 2px solid white;
        }
        
        body.dark-mode .top-nav-profile .profile {
            background-color: #333;
        }
        
        .top-nav-toggle {
            display: none !important;
        }

        /* Dashboard content area */
        .content {
            flex: 1;
            background-color: #ffffff;
            padding: 2rem;
            margin-left: 250px;
            transition: margin-left 0.3s ease, background-color 0.3s;
            width: calc(100% - 250px);
            min-height: 100vh;
        }

        body.dark-mode .content {
            background-color: #1a1a1a;
            color: #e0e0e0;
        }

        .content.expanded {
            margin-left: 70px;
            width: calc(100% - 70px);
        }

        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
            width: 100%;
        }

        .content-header h1 {
            font-size: clamp(1.5rem, 4vw, 2rem);
            color: #333333;
            font-family: 'Fredoka';
            margin-bottom: 0.5rem;
            line-height: 1.2;
        }

        body.dark-mode .content-header h1 {
            color: #e0e0e0;
        }

        .content-header p {
            color: #999;
            font-size: clamp(0.9rem, 2vw, 1rem);
            margin-top: 0.5rem;
            font-family: 'Fredoka';
            font-weight: 500;
            line-height: 1.4;
        }

        body.dark-mode .content-header p {
            color: #b0b0b0;
        }

        /* Profile in content header for larger screens */
        .content-header .actions {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-shrink: 0;
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
            min-height: 44px;
            display: flex;
            align-items: center;
        }

        .content-header .actions a:hover {
            background-color: #e5941f;
        }

        .content-header .actions .profile {
            width: 50px;
            height: 50px;
            background-color: #ffffff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #f5a623;
            font-size: 1.5rem;
            cursor: pointer;
            flex-shrink: 0;
            position: relative;
        }

        body.dark-mode .content-header .actions .profile {
            background-color: #333;
        }

        /* CARDS SECTION - UPDATED FOR RESPONSIVENESS */
        .cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(300px, 100%), 1fr));
            gap: 1.5rem;
            padding-bottom: 2rem;
            width: 100%;
        }

        .cards p, .cards h3, .cards a, .ranking-card h3 {
            font-family: 'Fredoka' !important;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .enroll-card {
            font-family: 'Fredoka';
            background-color: #ffffff;
            padding: clamp(1rem, 3vw, 1.5rem);
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.15);
            min-height: 200px;
            display: flex;
            flex-direction: column;
            transition: background-color 0.3s;
        }

        body.dark-mode .enroll-card {
            background-color: #2d2d2d;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }

        .header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }

        .enroll-card p {
            font-family: 'Fredoka';
            font-weight: 500;
            font-size: clamp(2rem, 5vw, 3.5rem);
            text-align: center;
            margin: auto;
            color: #4d4d4d;
            word-break: break-word;
            line-height: 1.2;
        }

        body.dark-mode .enroll-card p {
            color: #e0e0e0;
        }

        .enroll-card a {
            font-family: 'Fredoka';
            font-weight: 600;
            text-decoration: none;
            color: #f8b500;
            align-self: flex-end;
            margin-top: auto;
            font-size: clamp(0.9rem, 1.2vw, 1rem);
        }

        h3 {
            font-family: 'Fredoka';
            font-weight: bold;
            font-size: clamp(1.2rem, 2vw, 1.5rem);
            margin: auto 0;
        }

        body.dark-mode h3 {
            color: #e0e0e0;
        }

        .success-quiz-card {
            font-family: 'Fredoka';
            background-color: #ffffff;
            padding: clamp(1rem, 3vw, 1.5rem);
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.15);
            min-height: 200px;
            display: flex;
            flex-direction: column;
            transition: background-color 0.3s;
        }

        body.dark-mode .success-quiz-card {
            background-color: #2d2d2d;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }

        .icon {
            font-size: clamp(1.5rem, 3vw, 2rem);
            color: #F8B500;
            border-radius: 50%;
            border: 3px solid #F8B500;
            padding: clamp(1rem, 2vw, 1.5rem);
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .quiz-details {
            margin-top: clamp(1rem, 3vw, 2rem);
            margin-left: 1rem;
            margin-bottom: 1rem;
            flex: 1;
        }

        .quiz-details h4 {
            font-family: 'Fredoka';
            font-size: clamp(1rem, 2vw, 1.5rem);
            color: #4d4d4d;
            margin-bottom: 0.5rem;
            word-wrap: break-word;
            overflow-wrap: break-word;
            line-height: 1.3;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        body.dark-mode .quiz-details h4 {
            color: #e0e0e0;
        }

        .success-quiz-card p {
            font-size: clamp(1rem, 2vw, 1.5rem);
        }

        .success-quiz-card a {
            font-weight: 600;
            text-decoration: none;
            color: #f8b500;
            margin-top: auto;
            align-self: flex-end;
            font-size: clamp(0.9rem, 1.2vw, 1rem);
        }

        #item-link {
            margin-top: 1rem;
        }

        #sub-link {
            font-family: 'Fredoka';
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.5rem;
        }

        #sub-link p {
            font-family: 'Fredoka';  
            color: #4d4d4d;
            font-weight: bold;
            font-size: clamp(0.9rem, 1.2vw, 1rem);
        }

        body.dark-mode #sub-link p {
            color: #e0e0e0;
        }

        #sub-link a {
            font-family: 'Fredoka';
            font-weight: 500;
            text-decoration: none;
            color: #f8b500;
            font-size: clamp(1rem, 1.5vw, 1.5rem);
        }

        /* DIFFICULTY QUIZ CARD - UPDATED */
        .diff-quiz-card {
            background-color: #ffffff;
            padding: clamp(1rem, 3vw, 1.5rem);
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.15);
            min-height: 250px;
            transition: background-color 0.3s;
            width: 100%;
            overflow: hidden;
        }

        body.dark-mode .diff-quiz-card {
            background-color: #2d2d2d;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }

        /* Difficulty Quiz Card Styles - UPDATED */
        .difficulty-header {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
            gap: 1rem;
        }

        .difficulty-header i {
            font-size: clamp(1.5rem, 2.5vw, 1.8rem);
            color: #e74c3c;
        }

        .difficulty-header h3 {
            margin: 0;
            color: #333;
            font-size: clamp(1.2rem, 2vw, 1.5rem);
        }

        body.dark-mode .difficulty-header h3 {
            color: #e0e0e0;
        }

        .difficulty-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(min(300px, 100%), 1fr));
            gap: 1.5rem;
            width: 100%;
        }

        .difficulty-item {
            background: white;
            border-radius: 10px;
            padding: clamp(1rem, 2vw, 1.5rem);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border-left: 4px solid #e74c3c;
            min-width: 0;
        }

        body.dark-mode .difficulty-item {
            background: #2d2d2d;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }

        .difficulty-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.12);
        }

        .difficulty-title {
            margin-bottom: 1rem;
        }

        .difficulty-title h4 {
            font-size: clamp(0.9rem, 1.5vw, 1.2rem);
            color: #333;
            margin-bottom: 0.5rem;
            word-wrap: break-word;
            overflow-wrap: break-word;
            line-height: 1.3;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        body.dark-mode .difficulty-title h4 {
            color: #e0e0e0;
        }

        .difficulty-percentage {
            display: inline-block;
            background: #ffebee;
            color: #e74c3c;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: clamp(0.75rem, 1vw, 0.85rem);
            font-weight: 600;
        }

        body.dark-mode .difficulty-percentage {
            background: #3a1a1a;
        }

        .difficulty-subject {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
            color: #666;
            gap: 0.5rem;
        }

        body.dark-mode .difficulty-subject {
            color: #b0b0b0;
        }

        .difficulty-subject i {
            color: #f8b500;
        }

        .difficulty-subject a {
            color: #f8b500;
            text-decoration: none;
            font-weight: 500;
            font-size: clamp(0.9rem, 1.2vw, 1rem);
        }

        .difficulty-subject a:hover {
            color:rgb(234, 177, 22);
        }

        /* UPDATED: Difficulty stats - now responsive */
        .difficulty-stats {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .stat-item {
            text-align: center;
            flex: 1;
            min-width: 80px;
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.5rem;
            font-size: clamp(0.9rem, 1.2vw, 1rem);
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
            font-size: clamp(1rem, 1.5vw, 1.2rem);
            color: #333;
        }

        body.dark-mode .stat-value {
            color: #e0e0e0;
        }

        .stat-label {
            font-size: clamp(0.7rem, 1vw, 0.8rem);
            color: #777;
        }

        body.dark-mode .stat-label {
            color: #b0b0b0;
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
            font-size: clamp(0.9rem, 1.2vw, 1rem);
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

        body.dark-mode .no-difficulty {
            color: #b0b0b0;
        }

        .no-difficulty i {
            font-size: clamp(2rem, 4vw, 3rem);
            color: #f8b500;
            margin-bottom: 1rem;
        }

        .no-difficulty p {
            font-size: clamp(1rem, 1.5vw, 1.1rem);
        }

        /* RANKING CARD - UPDATED */
        .ranking-card {
            background-color: #ffffff;
            padding: clamp(1rem, 3vw, 1.5rem);
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.15);
            min-height: 300px;
            transition: background-color 0.3s;
            overflow: hidden;
        }

        body.dark-mode .ranking-card {
            background-color: #2d2d2d;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }

        .ranking-card p {
            font-family: 'Fredoka' !important;
            font-size: clamp(2rem, 5vw, 3.5rem);
            text-align: center;
            margin: 1rem 0;
        }

        #scores-cont {
            font-family: 'Fredoka';
            width: 100%;
            padding: 10px;
        }

        /* Table header styles - UPDATED */
        .ranking-header {
            font-family: 'Fredoka';
            display: grid;
            grid-template-columns: minmax(100px, 1fr) minmax(120px, 2fr) minmax(80px, 1fr);
            padding: 0.5rem;
            margin-bottom: 1rem;
            gap: 0.5rem;
            width: 100%;
            overflow: hidden;
        }

        .ranking-header span {
            font-family: 'Fredoka';
            font-weight: bold;
            font-size: clamp(0.7rem, 1.2vw, 1rem);
            color: #f8b500;
            text-align: center;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Ranking rows container */
        .ranking-rows {
            font-family: 'Fredoka';
            display: flex;
            flex-direction: column;
            gap: 0.8rem;
            width: 100%;
            overflow-x: auto;
        }

        /* Individual ranking row - UPDATED */
        .ranking-row {
            font-family: 'Fredoka';
            display: grid;
            grid-template-columns: minmax(100px, 1fr) minmax(120px, 2fr) minmax(80px, 1fr);
            padding: 0.5rem;
            border-radius: 15px;
            align-items: center;
            font-weight: 500;
            gap: 0.5rem;
            font-size: clamp(0.7rem, 1.2vw, 1rem);
            width: 100%;
            min-width: 0;
        }

        .ranking-row div {
            cursor: pointer;
        }

        .ranking-row-noquiz {
            font-family: 'Fredoka';
            color: #6666;
            text-align: center;
            padding: 1rem;
            grid-column: 1 / -1;
        }

        body.dark-mode .ranking-row-noquiz {
            color: #b0b0b0;
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

        /* Name styles - UPDATED */
        .stud-name {
            font-family: 'Fredoka';
            font-size: clamp(0.75rem, 1.2vw, 1rem);
            text-align: left;
            padding-left: 0.5rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: flex;
            align-items: center;
            min-width: 0;
        }

        .stud-name i {
            flex-shrink: 0;
            margin-right: 0.3rem;
        }

        .subject {
            font-family: 'Fredoka';
            font-size: clamp(0.65rem, 1vw, 0.9rem);
            text-align: center;
            color: #444;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            padding: 0 0.3rem;
        }

        body.dark-mode .subject {
            color: #e0e0e0;
        }

        /* Score styles - UPDATED */
        .score {
            font-family: 'Fredoka';
            font-size: clamp(0.9rem, 1.5vw, 1.2rem);
            font-weight: bold;
            text-align: center;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .dropdown-content {
            width: min(300px, 90vw);
            right: 0;
            display: none;
            position: absolute;
            background-color: #F8B500;
            border-radius: 15px;
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
            z-index: 1001;
            padding: 10px 0;
            top: 100%;
            margin-top: 10px;
        }

        .dropdown-content:before {
            content: " ";
            position: absolute;
            background: #F8B500;
            width: 20px;
            height: 20px;
            top: -5px;
            right: 20px;
            transform: rotate(45deg);
            z-index: -1;
        }

        .dropdown-content button {
            background-color: white;
            font-family: 'Fredoka';
            color: white;
            font-size: clamp(16px, 2vw, 18px);
            font-weight: lighter;
            border: 2px solid white !important;
            width: 90% !important;
            padding: 12px 20px !important;
            margin: 8px auto !important;
            text-decoration: none;
            display: block;
            text-align: center;
            background-color: transparent;
            transition: background-color 0.3s, color 0.3s;
            border-radius: 10px;
            cursor: pointer;
            letter-spacing: 1px;
            box-sizing: border-box;
            min-height: 44px;
        }

        .dropdown-content button i{
            margin-right: 4px;
        }

        .dropdown-content a:hover, .dropdown-content button:hover {
            background-color: white !important;
            color: #F8B500;
        }

        .show {
            display: block;
        }

        /* Dark Mode Toggle Button */
        .dark-mode-toggle {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background-color: #f8b500;
            color: white;
            border: none;
            border-radius: 50%;
            width: clamp(50px, 8vw, 60px);
            height: clamp(50px, 8vw, 60px);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: clamp(1.2rem, 2.5vw, 1.5rem);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            z-index: 999;
            transition: background-color 0.3s;
            min-height: 44px;
            min-width: 44px;
        }

        .dark-mode-toggle:hover {
            background-color: #e5941f;
            transform: scale(1.05);
        }

        body.dark-mode .dark-mode-toggle {
            background-color: #444;
        }

        .profile-pic {
            border: 2px solid #f8b500;
            object-fit: cover;
        }

        /* Mobile Responsive Styles - UPDATED */
        @media (max-width: 768px) {
            .sidebar {
                display: none;
            }
            
            .top-nav {
                display: flex;
                height: 70px;
                padding: 0.75rem;
            }
            
            .top-nav .logo {
                flex: 1;
            }
            
            .top-nav .logo img {
                height: 35px;
            }
            
            .top-nav .menu {
                gap: 1rem;
            }
            
            .top-nav .menu a {
                padding: 0.6rem;
                min-height: 44px;
                min-width: 44px;
            }
            
            .top-nav .menu a i {
                font-size: 1.3rem;
            }
            
            .top-nav-profile .profile {
                width: 40px;
                height: 40px;
            }
            
            .content {
                padding: 1rem;
                margin-left: 0;
                width: 100%;
                margin-top: 70px;
            }
            
            .content.expanded {
                margin-left: 0;
                width: 100%;
            }
            
            .cards {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .enroll-card, .success-quiz-card, .ranking-card {
                padding: 1rem;
                min-height: auto;
            }
            
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
            
            .icon {
                padding: 0.8rem;
                font-size: 1.2rem;
            }
            
            .quiz-details {
                margin-left: 0;
                margin-top: 1rem;
            }
            
            .difficulty-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .difficulty-item {
                padding: 1rem;
            }
            
            /* UPDATED: Difficulty stats for mobile - stack properly */
            .difficulty-stats {
                flex-direction: column;
                align-items: center;
                gap: 1rem;
                margin-bottom: 1.5rem;
            }
            
            .stat-item {
                width: 100%;
                display: flex;
                align-items: center;
                justify-content: space-between;
                text-align: left;
                padding: 0.8rem;
                background: rgba(0, 0, 0, 0.03);
                border-radius: 8px;
                min-width: auto;
            }
            
            body.dark-mode .stat-item {
                background: rgba(255, 255, 255, 0.05);
            }
            
            .stat-icon {
                margin: 0;
                width: 35px;
                height: 35px;
                flex-shrink: 0;
            }
            
            .stat-item-content {
                flex: 1;
                margin-left: 1rem;
                text-align: left;
            }
            
            .stat-value {
                font-size: 1rem;
                margin-bottom: 0.2rem;
            }
            
            .stat-label {
                font-size: 0.8rem;
            }
            
            .content-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            /* Hide profile in content header on mobile */
            .content-header .actions {
                display: none;
            }
            
            .dropdown-content {
                right: 0;
                width: min(250px, 80vw);
            }
            
            .ranking-header {
                grid-template-columns: 1fr 1fr 1fr;
                font-size: 0.8rem;
                gap: 0.3rem;
            }
            
            .ranking-row {
                grid-template-columns: 1fr 1fr 1fr;
                font-size: 0.8rem;
                padding: 0.4rem;
                gap: 0.3rem;
            }
            
            .stud-name {
                font-size: 0.8rem;
                padding-left: 0.3rem;
            }
            
            .subject {
                font-size: 0.75rem;
                padding: 0 0.2rem;
            }
            
            .score {
                font-size: 0.9rem;
            }
        }

        /* For screens larger than 768px - Show sidebar and content header profile */
        @media (min-width: 769px) {
            .top-nav {
                display: none;
            }
            
            .content-header .actions {
                display: flex;
            }

            .dropdown-content {
                width: min(260px, 80vw);
                right: 5px;
                margin-top: 5px;
            }
            
            .dropdown-content:before {
                right: 15px;
                width: 14px;
                height: 14px;
                top: -7px;
            }
            
            .dropdown-content button {
                font-size: 15px;
                padding: 9px 14px;
                min-height: 40px;
            }
        }

        @media (max-width: 576px) {
            .top-nav {
                height: 60px;
                padding: 0.5rem;
            }
            
            .top-nav .logo img {
                height: 30px;
            }
            
            .top-nav .menu {
                gap: 0.75rem;
            }
            
            .top-nav .menu a {
                padding: 0.5rem;
                min-height: 40px;
                min-width: 40px;
            }
            
            .top-nav .menu a i {
                font-size: 1.2rem;
            }
            
            .top-nav-profile .profile {
                width: 35px;
                height: 35px;
            }
            
            .content {
                padding: 0.75rem;
                margin-top: 60px;
            }
            
            .enroll-card p, .ranking-card p {
                font-size: 2.5rem;
            }
            
            .difficulty-stats {
                flex-direction: column;
                gap: 0.8rem;
            }
            
            .stat-item {
                padding: 0.7rem;
            }
            
            .stat-icon {
                width: 32px;
                height: 32px;
                font-size: 0.9rem;
            }
            
            .stat-item-content {
                margin-left: 0.8rem;
            }
            
            .stat-value {
                font-size: 0.95rem;
            }
            
            .stat-label {
                font-size: 0.75rem;
            }
            
            .ranking-header {
                grid-template-columns: 1.2fr 1.2fr 0.8fr;
            }
            
            .ranking-header span {
                font-size: 0.7rem;
            }
            
            .ranking-row {
                grid-template-columns: 1.2fr 1.2fr 0.8fr;
                font-size: 0.75rem;
            }
            
            .stud-name {
                font-size: 0.75rem;
            }
            
            .subject {
                font-size: 0.7rem;
            }
            
            .score {
                font-size: 0.8rem;
            }
            
            .dark-mode-toggle {
                bottom: 10px;
                right: 10px;
                width: 50px;
                height: 50px;
            }
        }

        @media (max-width: 480px) {
            .top-nav {
                padding: 0.4rem;
                height: 55px;
            }
            
            .top-nav .logo img {
                height: 25px;
            }
            
            .top-nav .menu {
                gap: 0.5rem;
            }
            
            .top-nav .menu a {
                padding: 0.4rem;
                min-height: 38px;
                min-width: 38px;
            }
            
            .top-nav .menu a i {
                font-size: 1.1rem;
            }
            
            .top-nav-profile .profile {
                width: 32px;
                height: 32px;
            }
            
            .content {
                padding: 0.5rem;
                margin-top: 55px;
            }

            .content-header h1 {
                font-size: 1.3rem;
            }
            
            .content-header p {
                font-size: 0.85rem;
            }

            .cards {
                gap: 0.75rem;
            }
            
            .enroll-card, .success-quiz-card, .ranking-card {
                padding: 0.75rem;
            }
            
            .enroll-card p, .ranking-card p {
                font-size: 2rem;
            }
            
            h3 {
                font-size: 1.1rem;
            }
            
            .icon {
                padding: 0.6rem;
                font-size: 1rem;
            }
            
            .quiz-details h4 {
                font-size: 1.1rem;
            }
            
            .difficulty-item {
                padding: 0.8rem;
            }
            
            .difficulty-stats {
                gap: 0.6rem;
            }
            
            .stat-item {
                padding: 0.6rem;
            }
            
            .stat-icon {
                width: 30px;
                height: 30px;
                font-size: 0.85rem;
            }
            
            .stat-item-content {
                margin-left: 0.6rem;
            }
            
            .stat-value {
                font-size: 0.9rem;
            }
            
            .stat-label {
                font-size: 0.7rem;
            }
            
            .ranking-header {
                grid-template-columns: 1.5fr 1fr 0.8fr;
            }
            
            .ranking-row {
                grid-template-columns: 1.5fr 1fr 0.8fr;
                font-size: 0.7rem;
                padding: 0.3rem;
            }
            
            .stud-name {
                font-size: 0.7rem;
                flex-direction: column;
                align-items: flex-start;
            }
            
            .stud-name i {
                margin-bottom: 0.2rem;
            }
            
            .subject {
                font-size: 0.65rem;
            }
            
            .score {
                font-size: 0.75rem;
            }
            
            .dropdown-content {
                width: min(220px, 75vw);
                border-radius: 10px;
            }
            
            .dropdown-content:before {
                right: 12px;
                width: 12px;
                height: 12px;
                top: -6px;
            }
            
            .dropdown-content button {
                font-size: 14px;
                padding: 8px 12px;
                min-height: 38px;
                margin: 4px auto;
            }
        }

        @media (max-width: 375px) {
            .top-nav {
                padding: 0.3rem;
                height: 50px;
            }
            
            .top-nav .logo img {
                height: 30px;
            }
            
            .top-nav .menu {
                gap: 0.4rem;
            }
            
            .top-nav .menu a {
                padding: 0.3rem;
                min-height: 36px;
                min-width: 36px;
            }
            
            .top-nav .menu a i {
                font-size: 1rem;
            }
            
            .top-nav-profile .profile {
                width: 30px;
                height: 30px;
            }
            
            .content {
                padding: 0.5rem;
                margin-top: 50px;
            }
            
            .enroll-card, .success-quiz-card, .ranking-card {
                padding: 0.5rem;
            }
            
            .enroll-card p, .ranking-card p {
                font-size: 1.8rem;
            }
            
            h3 {
                font-size: 1rem;
            }
            
            .quiz-details h4 {
                font-size: 1rem;
            }
            
            .difficulty-item {
                padding: 0.6rem;
            }
            
            .difficulty-stats {
                gap: 0.5rem;
            }
            
            .stat-item {
                padding: 0.5rem;
            }
            
            .stat-icon {
                width: 28px;
                height: 28px;
                font-size: 0.8rem;
            }
            
            .stat-item-content {
                margin-left: 0.5rem;
            }
            
            .stat-value {
                font-size: 0.85rem;
            }
            
            .stat-label {
                font-size: 0.65rem;
            }
            
            .ranking-header {
                grid-template-columns: 1fr 1fr 1fr;
                font-size: 0.65rem;
            }
            
            .ranking-row {
                font-size: 0.65rem;
            }
            
            .stud-name {
                font-size: 0.65rem;
                flex-direction: row;
                align-items: center;
            }
            
            .subject, .score {
                font-size: 0.6rem;
            }
            
            .score {
                font-size: 0.7rem;
            }
            
            .dropdown-content {
                width: min(200px, 70vw);
            }
            
            .dropdown-content button {
                font-size: 13px;
                padding: 7px 10px;
                min-height: 36px;
            }
        }

        /* Utility classes */
        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        /* Improve focus accessibility */
        button:focus-visible,
        a:focus-visible {
            outline: 2px solid #f8b500;
            outline-offset: 2px;
        }

        /* Smooth scrolling */
        html {
            scroll-behavior: smooth;
        }

        /* Prevent horizontal scroll */
        body {
            overflow-x: hidden;
        }
    </style>
</head>
<body>
    <!-- Top Navigation for Mobile -->
    <nav class="top-nav" id="topNav">
        <div class="logo">
            <img src="img/logo 6.png" alt="QuizZap Logo">
        </div>
        <div class="menu" id="topNavMenu">
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
        <div class="top-nav-profile">
            <div class="profile" onclick="profileDropdown()">
                <img src="uploads/profiles/<?php echo htmlspecialchars($profile_pic); ?>" alt="Profile Picture" class="profile-pic" onerror="this.src='uploads/profiles/default-profile.jpg'" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
                <div id="dropdown" class="dropdown-content">
                    <button onclick="window.location.href='t_Profile.php'"><i class="fa-solid fa-user"></i> Profile</button> 
                    <form action="logout.php" method="POST">
                        <button><i class="fa-solid fa-right-from-bracket"></i> Logout</button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    <!-- Dark Mode Toggle Button -->
    <button class="dark-mode-toggle" id="darkModeToggle">
        <i class="fas fa-moon"></i>
    </button>

    <div class="container">
        <!-- Sidebar - Hidden on mobile -->
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
                <!-- Profile in content header for larger screens -->
                <div class="actions">
                    <div class="profile" onclick="profileDropdown()">
                        <img src="uploads/profiles/<?php echo htmlspecialchars($profile_pic); ?>" alt="Profile Picture" class="profile-pic" onerror="this.src='uploads/profiles/default-profile.jpg'" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
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
                        <i class="fa-solid fa-users icon"></i> 
                        <h3>Total Students Enrolled: </h3>
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
                                            <div class="stat-item-content">
                                                <div class="stat-value"><?php echo $quiz['correct_answers']; ?></div>
                                                <div class="stat-label">Correct Answers</div>
                                            </div>
                                        </div>
                                        
                                        <div class="stat-item">
                                            <div class="stat-icon incorrect">
                                                <i class="fa-solid fa-circle-xmark"></i>
                                            </div>
                                            <div class="stat-item-content">
                                                <div class="stat-value"><?php echo $quiz['incorrect_answers']; ?></div>
                                                <div class="stat-label">Incorrect Answers</div>
                                            </div>
                                        </div>
                                        
                                        <div class="stat-item">
                                            <div class="stat-icon total">
                                                <i class="fa-solid fa-users"></i>
                                            </div>
                                            <div class="stat-item-content">
                                                <div class="stat-value"><?php echo $quiz['total_students']; ?></div>
                                                <div class="stat-label">Total Students</div>
                                            </div>
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
    const darkModeToggle = document.getElementById('darkModeToggle');
    const body = document.body;

    // Check if sidebar state is saved in localStorage
    const isSidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
    
    // Set initial state based on localStorage
    if (isSidebarCollapsed) {
        sidebar.classList.add('collapsed');
        content.classList.add('expanded');
    }

    // Toggle sidebar when button is clicked (for desktop)
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            content.classList.toggle('expanded');
            
            // Save state to localStorage
            localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
        });
    }

    // Check for saved dark mode preference
    const isDarkMode = localStorage.getItem('darkMode') === 'true';
    
    // Apply dark mode if previously enabled
    if (isDarkMode) {
        body.classList.add('dark-mode');
        darkModeToggle.innerHTML = '<i class="fas fa-sun"></i>';
    }

    // Dark mode toggle functionality
    darkModeToggle.addEventListener('click', () => {
        body.classList.toggle('dark-mode');
        
        // Update button icon and save preference
        if (body.classList.contains('dark-mode')) {
            darkModeToggle.innerHTML = '<i class="fas fa-sun"></i>';
            localStorage.setItem('darkMode', 'true');
        } else {
            darkModeToggle.innerHTML = '<i class="fas fa-moon"></i>';
            localStorage.setItem('darkMode', 'false');
        }
    });

    // Profile dropdown functionality
    function profileDropdown() {
        // Close all dropdowns first
        const allDropdowns = document.querySelectorAll('.dropdown-content.show');
        allDropdowns.forEach(drop => {
            drop.classList.remove('show');
        });
        
        // Toggle the clicked dropdown
        const dropdowns = document.querySelectorAll('.dropdown-content');
        dropdowns.forEach(dropdown => {
            dropdown.classList.toggle('show');
        });
    }

    // Close the dropdown if clicked outside
    window.onclick = function(event) {
        if (!event.target.matches('.profile') && !event.target.matches('.profile-pic') && !event.target.closest('.profile')) {
            var dropdowns = document.getElementsByClassName("dropdown-content");
            for (var i = 0; i < dropdowns.length; i++) {
                var openDropdown = dropdowns[i];
                if (openDropdown.classList.contains('show')) {
                    openDropdown.classList.remove('show');
                }
            }
        }
    }

    // Handle text truncation for responsive design
    function handleTextTruncation() {
        const rankingRows = document.querySelectorAll('.ranking-row');
        const mediaQuery = window.matchMedia('(max-width: 480px)');
        
        rankingRows.forEach(row => {
            const nameCell = row.querySelector('.stud-name');
            const subjectCell = row.querySelector('.subject');
            
            if (mediaQuery.matches) {
                // On very small screens, add truncation
                nameCell.style.whiteSpace = 'nowrap';
                nameCell.style.overflow = 'hidden';
                nameCell.style.textOverflow = 'ellipsis';
                
                subjectCell.style.whiteSpace = 'nowrap';
                subjectCell.style.overflow = 'hidden';
                subjectCell.style.textOverflow = 'ellipsis';
            } else {
                // Reset for larger screens
                nameCell.style.whiteSpace = 'normal';
                nameCell.style.overflow = 'visible';
                nameCell.style.textOverflow = 'clip';
                
                subjectCell.style.whiteSpace = 'normal';
                subjectCell.style.overflow = 'visible';
                subjectCell.style.textOverflow = 'clip';
            }
        });
    }

    // Call on load and resize
    handleTextTruncation();
    window.addEventListener('resize', handleTextTruncation);

    // Handle window resize for responsive behavior
    window.addEventListener('resize', function() {
        // Auto-hide sidebar on mobile when resizing to larger screen
        if (window.innerWidth >= 769) {
            // If we're on desktop and sidebar was hidden (mobile state), reset it
            if (sidebar.style.display === 'none') {
                sidebar.style.display = 'flex';
            }
        }
        
        // Re-apply text truncation
        handleTextTruncation();
    });

    // Improve touch targets for mobile
    const touchElements = document.querySelectorAll('a, button, .dropdown-content button');
    touchElements.forEach(element => {
        element.style.minHeight = '44px';
        element.style.minWidth = '44px';
        element.style.display = 'flex';
        element.style.alignItems = 'center';
        element.style.justifyContent = 'center';
    });
});

// Make profileDropdown function global
function profileDropdown() {
    // Close all dropdowns first
    const allDropdowns = document.querySelectorAll('.dropdown-content.show');
    allDropdowns.forEach(drop => {
        drop.classList.remove('show');
    });
    
    // Toggle the clicked dropdown
    const dropdowns = document.querySelectorAll('.dropdown-content');
    dropdowns.forEach(dropdown => {
        dropdown.classList.toggle('show');
    });
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