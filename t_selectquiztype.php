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
<html>
<head>
    <title>Create Subject</title>

<style>

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: 'Fredoka';
    }
    
    body {
        font-family: Arial, Helvetica, sans-serif;
        background-color: #ffffff;
        transition: background-color 0.3s, color 0.3s;
    }

    body.dark-mode {
        background-color: #1a1a1a;
        color: #e0e0e0;
    }

    header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px;
        background-color: white;
    }

    body.dark-mode header {
        background-color: #1a1a1a;
    }

    header .logo {
        font-size: 24px;
        font-weight: bold;
        margin-left: 30px;
        margin-top: 3px;
    }

    .quiz-type {
        position: relative;
        font-family: Fredoka;
        color: #f8b500;
        text-align: center;
        font-size: 70px;
        font-weight: 600;
    }

    body.dark-mode .quiz-type {
        color: #f8b500;
    }

    hr{
        border: 1px solid #C8C8C8;
        width: 90%;
        margin-left: 4%;
        align-items: center;
    }

    body.dark-mode hr {
        border-color: #444;
    }

    .quiz-type-buttons {
        display: grid;
        grid-template-columns: 3fr 1fr; /* Two columns */
        grid-template-rows: 1fr 1fr;   /* Two rows */
        gap: 10%;                     /* Space between the items */
        padding: 30px; /* Optional: Adds padding inside the container */
        margin-top: 3%;
        margin-bottom: 8%;
        margin-right: 25%;
        margin-left: 25%;
    }

    .quiz-type-buttons a {
        display: flex;
        justify-content: center;
        align-items: center;
        background-color: white;
        color: #f8b500;
        font-family: Fredoka;
        font-weight: 600;
        font-size: 30px;
        border: 2px solid #f8b500;
        border-radius: 15px;
        padding: 20px 20px;
        width: 300px;
        text-decoration: none;
        text-align: center;
        margin: auto;
        transition: transform .3s;
        -webkit-box-shadow: 0 6px 0 0 rgba(0, 0, 0, 0.11);
        -moz-box-shadow: 0 6px 0 0 rgba(0, 0, 0, 0.11);
        box-shadow: 0 6px 0 0 #BC8900;
    }

    body.dark-mode .quiz-type-buttons a {
        background-color: #2d2d2d;
        color: #f8b500;
    }

    .quiz-type-buttons a:hover {
        background-color: #f8b500;
        color: white;  
        -ms-transform: scale(1.5); /* IE 9 */
        -webkit-transform: scale(1.5); /* Safari 3-8 */
        transform: scale(1.1); 
        transition: transform .2s;
        box-shadow: 0 4px 0 0 #BC8900;
    }

    body.dark-mode .quiz-type-buttons a:hover {
        background-color: #f8b500;
        color: white;
    }

    .quiz-type-buttons a:active {
        background-color: white;
        color: #f8b500;
        transform: translateY(3px);
        box-shadow: 0 5px 0 0 #BC8900;
    }

    body.dark-mode .quiz-type-buttons a:active {
        background-color: #2d2d2d;
        color: #f8b500;
    }

    #allZapped{
        margin-left: 60%;
    }

    /* width */
    ::-webkit-scrollbar {
      width: 10px;
      height: 10px;
    }

    /* Track */
    ::-webkit-scrollbar-track {
      box-shadow: inset 0 0 5px grey; 
      border-radius: 10px;
    }
     
    /* Handle */
    ::-webkit-scrollbar-thumb {
      background: #f8b500; 
      border-radius: 10px;
    }

    /* Handle on hover */
    ::-webkit-scrollbar-thumb:hover {
      background: #f8b500; 
    }

</style>
</head>

<body>

    <header>
        <div class="logo"><img src="img/logo1.png" width="200px" height="80px"></div>
    </header>

    <p class="quiz-type">Choose the Type of Quiz.</p>
    <hr>

<div class="quiz-type-buttons">

    <a href="t_multipleChoice.php?subject_id=<?php echo $subject_id; ?>">Multiple Choice</a>
    <a href="t_fill_in.php?subject_id=<?php echo $subject_id; ?>">Fill in the Blanks</a>
    <a href="t_T_or_F.php?subject_id=<?php echo $subject_id; ?>">True or False</a>
    <a href="t_enum.php?subject_id=<?php echo $subject_id; ?>">Enumeration</a>
    <a href="t_drag&drop.php?subject_id=<?php echo $subject_id; ?>">Drag & Drop</a>
    <a href="t_matching.php?subject_id=<?php echo $subject_id; ?>">Matching Type</a>
    <a href="t_identification.php?subject_id=<?php echo $subject_id; ?>">Identification</a>
    <a href="allZapped.php?subject_id=<?php echo $subject_id; ?>">All Zapped</a>

</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Dark Mode Functionality - Auto apply based on localStorage
        // Check for saved dark mode preference
        const isDarkMode = localStorage.getItem('darkMode') === 'true';

        // Apply dark mode on page load if enabled
        if (isDarkMode) {
            document.body.classList.add('dark-mode');
        }
    });
</script>

</body>
</html>