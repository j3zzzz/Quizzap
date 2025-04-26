<?php
session_start(); // Start the session

// Check if the user is logged in and is a student
if (!isset($_SESSION['account_number']) || strpos($_SESSION['account_number'], 'S') !== 0) {
    header("Location: login.php");
    exit();
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "rawrit"; 

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$loggedInUser = $_SESSION['account_number'];

$sql = "SELECT profile_pic FROM students WHERE account_number = ?";
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

// Fetch the actual student_id from the students table
$sql = "SELECT student_id FROM students WHERE account_number = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $loggedInUser);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Student not found in the database.");
}

$row = $result->fetch_assoc();
$student_id = $row['student_id']; // Use the actual student_id from the database

// Display enrolled subjects
$sql = "SELECT s.subject_id, s.subject_code, s.subject_name 
        FROM enrollments e 
        JOIN subjects s ON e.subject_id = s.subject_id 
        WHERE e.student_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $student_id);
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
    <title>QuizZap Dashboard</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body, html {
            height: 100%;
        }

        .container {
            display: flex;
            height: 100vh;
        }

        .sidebar {
            height: 100vh;
            position: fixed;
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
            margin-bottom: 20rem;
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
            margin-left: 17%;
            flex: 1;
            background-color: #ffffff;
            padding: 2rem;
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

        .content-header .actions button {
            background-color: #F8B500;
            color: #ffffff;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            font-size: 1rem;
            cursor: pointer;
            margin-right: 1rem;
            font-family: Tilt Warp Regular;
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

        .content-header hr{
            border: 1px solid #F8B500;
            width: 1150px;
        }

        .subject-cont {
            border: 3px solid #cf5200;
            border-radius: 5px;
            background-color: #ffb787;
            width: 60%;
            height: 400px;
            overflow: auto;
            box-shadow: 5px 6px 0 0 rgba(0, 0, 0, 0.2);
            border-radius: 15px;
            z-index: 5;
        }

        .subject-cont a {
            color: #CF5300;
            letter-spacing: 1px;
            font-size: 25px;
            text-decoration: none;
        }

        .subject-button {
            color: black;
            font-family: Tilt Warp Regular;
            font-size: 24px;
            background-color: white;
            display: inline-block;
            border-radius: 6px;
            border: 2px solid #f8b500;
            text-decoration: none;
            text-align: left;
            padding: 12px 30px;
            width: 30%;
            margin: auto;
            margin-top: 2%;
            margin-bottom: 2%;
            margin-right: 1%;
            transition: transform .2s;
            box-shadow: 0 6px 0 0 #BC8900;
            
        }

        .subject-button:hover {
            background-color: #F8B500;
            color: white;
        }

        .subject-button:active {
            background-color: #F8B500;
            box-shadow: 3px 4px 0 0 rgba(0, 0, 0, 0.3);
        }

        .subject-button span {
            font-size: 15px;
            font-family: Tilt Warp Regular;
            color: #f8b500;
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
          background: #CF5300; 
          border-radius: 10px;
        }

        /* Handle on hover */
        ::-webkit-scrollbar-thumb:hover {
          background: #A34404; 
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
        <!-- Sidebar --> 
        <div class="sidebar">
            <header>
                <div class="logo"><img src="img/logo4.png" width="200px" height="80px"></div>
            </header>
            <hr>
            <div class="menu">
                <a href="s_Home.php"><i class="fa-solid fa-house"></i>Dashboard</a>
                <a href="s_Classes.php" class="active"><i class="fa-regular fa-address-book"></i>Classes</a>
            </div>
        </div>

        <!-- Content Area -->
        <div class="content">
            <div class="content-header">
                <div><br>
                    <h1>Classes</h1><br>
                    <hr>
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
    <div><br><br>
        <?php
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                echo "<a class='subject-button' href='select_quiz.php?subject_id=" . $row['subject_id'] . "'>" . $row['subject_name'] ."<br><span>". $row['subject_code'] ."</span></a>";
            }
        } else {
            echo "<div class='no-quiz-con'>";
            echo "<p>No subjects created yet.</p>";
            echo "</div>";
        }
        ?>
    </div>  
   

    </center>
</div>
</div>

<script>
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