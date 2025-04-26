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
            font-family: 'Tilt Warp', sans-serif;
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
            background-color: #ffffff;
            color: #f8b500;
            padding: 2rem 1rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-shadow: 2px 0 4px 0 rgba(0, 0, 0, 0.2);
        }

        .sidebar .logo {
            margin-bottom: 1rem;
            margin-left: 5%;
        }

        hr{
            border: 1px solid #F8B500;
        }

        .sidebar .menu {
            display: flex;
            flex-direction: column;
            margin-bottom: 12rem;
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
            font-family: Tilt Warp Regular;
            margin-bottom: .5rem;
        }

        .sidebar .menu a:hover, .sidebar .menu a.active {
            background-color: #f8b500;
            color: #ffffff;
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
            box-shadow: 0 6px 0 0 rgba(0, 0, 0, 0.2);
            
        }

        .subject-button a:hover{
            background-color: #F8B500;
            color: white;
        }

        .subject-button span:hover{
            color: white;
        }

        .subject-button:hover {
            background-color: #F8B500;
            color: white;
        }

        .subject-button:active {
            background-color: #F8B500;
            box-shadow: 3px 4px 0 0 rgba(0, 0, 0, 0.3);
        }

        .subject-button a:active {
            background-color: #A34404;
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
          background: #f8b500; 
          border-radius: 10px;
        }

        /* Handle on hover */
        ::-webkit-scrollbar-thumb:hover {
          background: #A34404; 
        }

        .btn{
            float: left;
            margin-top: 2%;
            margin-left: 7%;
            width: 130px;
            padding: 10px;
            border-radius: 10px;
            background-color: #FFEFE4;
            color: #A34404;
            border: 2px solid #FFEFE4;
            font-family: Purple Smile;
            box-shadow: 5px 6px 0 0 rgba(0, 0, 0, 0.2);
            cursor: pointer;
        }

        .btn:hover{
            background-color: #A34404;
            color: #FFEFE4;
            border: 2px solid #A34404;
        }

        .create-q-button a {
            float: right;
            margin-top: 2%;
            margin-right: 10%;
            width: 15%;
            padding: 10px;
            border-radius: 10px;
            background-color: #F8B500;
            color: white;
            border: 2px solid #F8B500;
            font-family: Tilt Warp Regular;
            font-size: 18px;
            box-shadow: 0 6px 0 0 #BC8900;
            cursor: pointer;
            text-decoration: none;
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

        .quiz {
            background-color: white;
            border: 3px solid #DCDCDC;
            border-radius: 15px;
            padding: 10px 20px;
            width: 200%;
            height: 360px;
            max-width: 1000px;
            margin-left: -20%;
            margin-right: auto;
            margin-top: 10%;
            overflow: auto;
            box-shadow: 2px 4px 2px 0 rgba(0, 0, 0, 0.2);
        }

        .quiz-items {
            width: 30%;
            padding: 10px 0;
            margin-bottom: 3%;
            display: inline-block;
        }

        .quiz-btn {
            background-color: white;
            align-items: center;
            display: inline-grid;
            justify-content: center;
            align-items: center;
            margin-top: 2%;
            margin-right: 4%;
            margin-bottom: 1.5%;
            width: 50%;
            padding: 8px 15px;
            border: 2px solid #f8b500;
            border-radius: 8px;
            box-shadow: 0 4px 0 0 #BC8900;
            text-decoration: none;
            text-align: center;
            font-family: Tilt Warp Regular;
            font-size: 22px;
            color: black;
            cursor: pointer;
        }

        .quiz-btn:hover {
            background-color: #f8b500;
            color: white;
        }

        .quiz-btn:active {
            background-color: #f8b500;
            color: white;
            box-shadow: 0 4px 0 0 #BC8900;
        } 

        .img-no-quiz {
            width: 130px;
            height: 120px;
            margin-left: auto;
            margin-right: auto;
            display: flex;
            border-radius: 100%;
        }

        .no-quiz-btn {
            width: 60%;
            margin: auto;
            padding: 10px 3px;
            margin-top: 100px;
            text-align: center;
            font-size: xx-large;
            line-height: 1;
        }

        .edit-quiz {
            border-top: 3px solid #DCDCDC;
            font-family: Tilt Warp !important;
            position: absolute;
            background: white;
            margin-top: -0.8%;
            width: 60%;
            height: 10%;
            margin-bottom: 1%;
            margin-left: 2%;
            padding: 20px;
            z-index: 5;
        }

        #select {
            font-family: Tilt Warp;
            color: black;
            font-size: 25px;
            float: left;
            line-height: 1.5;
            margin-left: 5%;
            width: fit-content;
            animation-name: checkbox_fade;
            animation-duration: 1s;
        }

        #selectQuiz {
            background-color: #F8B500;
            border-radius: 8px;
            padding: 1.5%;
            color: black;
            float: right;
            margin-right: 2%;
            font-size: 20px;
            font-weight: bold;
            cursor: pointer;
        }

        #selectQuiz:hover {
            -ms-transform: scale(1.5); /* IE 9 */
            -webkit-transform: scale(1.5); /* Safari 3-8 */
            transform: scale(1.1); 
            transition: transform .2s;
        }

        #selectQuiz:active {
            color: black;
        }

        #deleteBtn {
            font-family: 'Purple Smile' !important;
            background-color: #F8B500;
            border-radius: 8px;
            border: none;
            padding: 1%;
            color: white; 
            position: relative;
            margin-top: -0% !important;
            margin-left: 78%;
            font-size: 15px;
            cursor: pointer;
            animation-name: checkbox_fade;
            animation-duration: 0.1s;
            box-shadow: 0 6px 0 0 #BC8900;
            animation-duration: 1s;
        }

        input[type="checkbox"] {
            left: -4%;
            width: 15px;
            height: 15px;
            top: 25%;
            border-color: #7D3200;
            position: relative;
            cursor: pointer;
            accent-color: #F8B500;
            animation-name: checkbox_fade;
            animation-duration: 1s;
            z-index: 1;
        }

        @keyframes checkbox_fade {
            from {opacity: 0}
            to {opacity: 1}
        }

        #close-btn {
            font-family: Tilt Warp;
            font-size: 30px;
            float: right;
            color: white;
            margin-top: -5%;
            cursor: pointer;
        }

        #close-btn:hover {
            color: #CF5300;
            transition: 0.3s;
        }


        #status {
            position: relative;
            text-align: center;
            background-color: #F8B500;
            border-radius: 10px;
            width: 30%;
            font-family: Tilt Warp;
            margin-left: -8%;
            left: 5%;
            display: block;
            padding: 20px 20px;
            color: white;
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
                <div class="logo"><img src="img/logo1.png" width="200px" height="80px"></div>
            </header>
            <hr>
            <div class="menu">
                <a href="t_SubjectsList.php"><i class="fa-solid fa-list"></i>Subjects</a>
                <a href="t_quizDash.php" class="active"><i class="fa-regular fa-circle-question"></i>Quizzes</a>
                <!--<a href="t_scores.php"><i class="fa-solid fa-list-ol"></i>Scores</a>-->
                <a href="t_rankings.php?subject_id=<?php echo $subject_id; ?>"><i class="fa-solid fa-ranking-star"></i>Rankings</a>
                <a href="t_item-analysis.php?subject_id=<?php echo $subject_id; ?>"><i class="fa-solid fa-chart-line"></i>Item Analysis</a>
            </div>
        </div>

    <!-- Content Area -->
    <div class="content">
            <div class="content-header">
                <div><br>
                    <h1><?php echo htmlspecialchars($subject['subject_name']); ?></h1><br>
                    <?php echo htmlspecialchars($subject['class_name']); ?>
                    <hr>
                </div>
                <div class="actions">
                    <div class="profile"><img src="<?php echo $profilePic; ?>"  onclick = "profileDropdown()" class="dropdwn-btn" width="50px" height="50px"></div>
                
                    <div id="dropdown" class="dropdown-content">
                                 <button onclick="window.location.href='t_Profile.php'"><i class="fa-solid fa-user"></i> Profile</button> 
                                <form action="logout.php" method="POST">
                                    <button><i class="fa-solid fa-right-from-bracket"></i> Logout</button>
                                </form>
                    </div>
                </div>
            </div>
    <center>

    <div class="create-q-button">       
        <a href="t_selectquiztype.php?subject_id=<?php echo $subject_id; ?>">Create Quiz</a>
    </div><br>

<?php 
    if(isset($_SESSION['status']))
    {
        ?>
            <div id="status">
                <span id="close-btn" onclick="document.getElementById('status').style.display = 'none';">&times;</span> 
                <?php echo $_SESSION['status']; ?>
                  
            </div>
        <?php
        unset($_SESSION['status']);
    }
?>

    <div class="content">
    
        <div class="quiz">
            <form action="delete_quiz.php?subject_id=<?php echo $subject_id; ?>" method="POST">    
                <div class="edit-quiz">   
                    <p id="select" style="display: none;">Select Quizzes to Delete</p>     
                    <i class="fa-regular fa-pen-to-square" onclick="quizCheckbox()" id="selectQuiz"></i>
                    <button type="submit" name="delete_quiz_btn"  id="deleteBtn" onclick="confirm('Are you sure you want to proceed on deleting the selected item/s?');" style="display: none;"><span><i class="fa-solid fa-trash"></i> Delete</span></button> 
                </div>
        <br><br><br>      
            <?php
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    echo "<div class='quiz-items'>";
                    echo "<a class='quiz-btn'>" . $row['title'] . "</a> <span><input type='checkbox' name='delete_quiz[]' value='" . $row['quiz_id'] . "' class='quiz-checkbox' style='display: none;'></span>";
                    echo "</div>";
                }
            } else {
                echo "  <div class='no-quiz-btn'>
                            No quizzes created yet.
                        </div>";
            }
            ?>

            </form>

        </div>
    </div><br><br>
    </div>

<script>
    function quizCheckbox() {
    var checkbox = document.querySelectorAll('.quiz-checkbox');
    var deleteBtn = document.getElementById("deleteBtn");
    var select = document.getElementById("select")
    
    checkbox.forEach(function(checkbox) {
        if (checkbox.style.display === "none") {
            checkbox.style.display = "inline-block"; // Show checkbox
        } else {
            checkbox.style.display = "none"; // Hide checkbox
        }
    });

        if (deleteBtn.style.display === "none" && select.style.display === "none") {
            deleteBtn.style.display = "block";
            select.style.display = "block";
        } else {
            deleteBtn.style.display = "none";
            select.style.display = "none";
        }
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
