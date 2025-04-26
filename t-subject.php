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

$subject_id = $_GET['subject_id'];

// Fetch the subject information based on the subject_id
$sql = "SELECT * FROM subjects WHERE subject_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $subject_id);
$stmt->execute();
$result = $stmt->get_result();
$subject = $result->fetch_assoc();
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <title><?php echo htmlspecialchars($subject['subject_name']); ?></title>
</head>
<style type="text/css">
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: Arial, sans-serif;
        background-color: #FFEFE4;
        color: white;
    }

    .nav {
        display: flex;
        justify-content: space-between;
        align-items: center;
        position: relative;
        width: 100%;
        padding: 20px;
        background-color: #CF5300;
        box-shadow: 0 4px 8px 0 rgba(0, 0, 0, 0.2), 0 6px 20px 0 rgba(0, 0, 0, 0.19);
        z-index: 2;
    }

    .nav .logo {
        font-size: 24px;
        font-weight: bold;
        margin-left: 30px;
        margin-top: .5%;
    }

    .nav a {
        margin: 0 15px;
        text-decoration: none;
        color: #FFC49C;
        font-size: 20px;
        font-family: Purple Smile;
        letter-spacing: .5px;
    }

    .nav a:hover{
        color: #923a00;
        transform: scale(1.1);
    }

    .side-nav{
        background-color: #D46317;
        position: fixed;
        width: 14%;
        height: 100%;
        left: 0;
        z-index: 2;
    }

    .side-nav-links {
        margin-top: 10%;
    }

    .side-nav a{
        font-size: 21px;
        font-family: Purple Smile;
        text-decoration: none;
        display: block;
        color: white;
        margin-left: 20%;
        margin-top: 18%;
    }

    .side-nav a:hover{
       color: #923a00;
       transform: scale(1.1);
    }

    .side-nav a.active {
        font-size: 25px;
        color: white;
    }

    .side-nav a:not(.active) {
        color: #FFC49C;
    }

    .active {
        font-size: 25px;
        color: white;
    }

    .subject-name{

    }

    .btn {
        float: right;
        margin-top: 3%;
        margin-right: 7%;
        width: 130px;
        padding: 10px;
        border-radius: 10px;
        background-color: #A34404;
        color: #FFEFE4;
        border: 2px solid #A34404;
        font-family: Purple Smile;
        box-shadow: 5px 6px 0 0 rgba(0, 0, 0, 0.2);
        cursor: pointer;
    }

    .btn:hover{
        background-color: #A34404;
        color: #FFEFE4;
        border: 2px solid #A34404;
    }

    h1{
        font-size: 3em;
        font-family: To Japan Regular;
        color: #CF5300;
        margin-left: 18%;
        align-items: center;
        text-align: center;
    }

    .container{
        justify-content: center;
        align-items: center;
        margin-top: 5%;
    }

    .container p{
        font-family: Purple Smile;
        color: #CF5300;
        font-size: 1.5em;
        text-align: centerx ;
        margin-left: 50%;

    }

    .cards{
        display: grid;
        justify-content: center;
        color: #DFC2F1;
    }

    .card1{
        width: 700px;
        padding: 20px;
        border-radius: 15px;
        background-color: #CF5300;
        color: #FFEFE4;
        border: 2px solid #CF5300;
        font-family: Purple Smile;
        text-align: center;
        margin-top: 5%;
        margin-left: 15%;
        box-shadow: 3px 5px 0 0 rgba(0, 0, 0, 0.2);
        text-decoration: none;
        letter-spacing: 1px;
    }

    .card2{
        width: 700px;
        padding: 20px;
        border-radius: 15px;
        background-color: #CF5300;
        color: #FFEFE4;
        border: 2px solid #CF5300;
        font-family: Purple Smile;
        text-align: center;
        margin-top: 5%;
        margin-left: 15%;
        box-shadow: 3px 5px 0 0 rgba(0, 0, 0, 0.2);
        text-decoration: none;
        letter-spacing: 1px;
    }

    .card3{
        width: 700px;
        padding: 20px;
        border-radius: 15px;
        background-color: #CF5300;
        color: #FFEFE4;
        border: 2px solid #CF5300;
        font-family: Purple Smile;
        text-align: center;
        margin-top: 5%;
        margin-left: 15%;
        box-shadow: 3px 5px 0 0 rgba(0, 0, 0, 0.2);
        text-decoration: none;
        letter-spacing: 1px;
    }

    .card1:hover, .card2:hover, .card3:hover{
        background-color: #A34404;
        color: #F7DCCB;
    }


</style>
<body>

    <div class="nav">
        <div class="logo"><img src="img/rawrit2.png" width="120px" height="40px"></div>
    </div>

    <div class="side-nav">
        <div class="side-nav-links">        
            <a href="t_Home.php">Home</a>
            <a href="t_Students.php">Students</a>
            <a class="active" href="t_SubjectsList.php">Subjects</a>
        </div>
    </div>

    <div class="container">
        <h1><?php echo htmlspecialchars($subject['subject_name']); ?></h1>
        <p class="sub_code">Subject Code: <?php echo htmlspecialchars($subject['subject_code']); ?></p>
    </div>

    <div class="cards">
        <a class="card1" href="t_quizDash.php?subject_id=<?php echo $subject_id; ?>">
            <h2>Quizzes</h2>
        </a>
    </div>
    <div class="cards">
        <a class="card2" href="teacher-subjects-rankings.php?subject_id=<?php echo $subject_id; ?>">
            <h2>Scores/Rankings</h2>
        </a>
    </div>
    <div class="cards">
        <a class="card3" href="item_analysis.php?subject_id=<?php echo $subject_id; ?>">
            <h2>Item Analysis</h2>
        </a>
    </div>


</body>
</html>