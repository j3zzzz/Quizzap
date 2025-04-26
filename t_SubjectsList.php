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

$loggedInUser = $_SESSION['account_number'];

//query para sa profile pic
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


function generateUniqueSubjectCode($conn) {
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $digits = '0123456789';
    do {
        $code = $characters[rand(0, 25)] . $characters[rand(0, 25)] . $digits[rand(0, 9)] . $digits[rand(0, 9)];
        $sql = "SELECT * FROM subjects WHERE subject_code = '$code'";
        $result = $conn->query($sql);
    } while ($result->num_rows > 0);
    return $code;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $subject_name = $_POST['subject_name'];
    $class_name = $_POST['class_name'];
    $teacher_account_number = $_SESSION['account_number'];
    $subject_code = generateUniqueSubjectCode($conn);

    $stmt = $conn->prepare("INSERT INTO subjects (subject_name, class_name, teacher_id, subject_code) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $subject_name, $class_name, $teacher_account_number, $subject_code);
    
    if ($stmt->execute()) {
        ?>
        <script type="text/javascript">
        console.log("Subject created successfully with code: $subject_code.");
        </script>
        <?php
    } else {
        echo "Error: " . $stmt->error;
    }
    $stmt->close();
}

$sql = "SELECT * FROM subjects WHERE teacher_id = '" . $_SESSION['account_number'] . "' ORDER BY subject_id DESC";
$result = $conn->query($sql);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <title>Subjects</title>
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
            position: absolute;
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
            transition: 0.2s;
            box-shadow: 0 6px 0 0 rgba(0, 0, 0, 0.2);
            
        }

        .subject-button:hover{
            background-color: #F8B500;
            color: white;
        }

        /*.subject-button span:hover{
            color: white;
        }

        .subject-button:hover {
            background-color: #F8B500;
            color: white;
        } */

        .subject-button:active {
            background-color: #F8B500;
            box-shadow: 3px 4px 0 0 rgba(0, 0, 0, 0.3);
        }

        /*.subject-button a:active {
            background-color: #A34404;
        } */

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

        #modalbtn {
            float: right;
            margin-top: -40%;
            margin-right: -90%;
            width: 120%;
            padding: 10px;
            border-radius: 10px;
            background-color: #F8B500;
            color: white;
            border: 2px solid #F8B500;
            font-family: Tilt Warp Regular;
            font-size: 18px;
            box-shadow: 0 6px 0 0 #BC8900;
            cursor: pointer;
        }

        #modalbtn:hover {
            background-color: white;
            color: #F8B500;
        }

        #modalbtn:active {
            background-color: #F8B500;
            color: white;
             box-shadow: 3px 2px 3.5px -0.5px rgba(30, 29, 29, 0.69);
        }

        .add-sub {
            float: right;
            margin-right: 200px;
            margin-top: 65px;

        }

        /* The Modal (background) */
        .modal {
          display: none; /* Hidden by default */
          position: fixed; /* Stay in place */
          z-index: 1; /* Sit on top */
          padding-top: 100px; /* Location of the box */
          left: 0;
          top: 0;
          width: 100%; /* Full width */
          height: 100%; /* Full height */
          overflow: auto; /* Enable scroll if needed */
          background-color: rgb(0,0,0); /* Fallback color */
          background-color: rgba(0,0,0,0.4); /* Black w/ opacity */
        }

        /* Modal Content */
        .modal-content {
          background-color: white;
          margin: auto;
          margin-top: 10%;
          padding: 15px;
          border: none;
          border-radius: 8px;
          width: 30%;
          font-family: Basic Bitch _ Regular Regular;
          font-size: 25px;
          color: #CF5300;
          -webkit-animation-name: zoom;
          -webkit-animation-duration: 0.6s;
          animation-name: zoom;
          animation-duration: 0.6s;
        }

        @-webkit-keyframes zoom {
          from {-webkit-transform:scale(0)} 
          to {-webkit-transform:scale(1)}
        }

        @keyframes zoom {
          from {transform:scale(0)} 
          to {transform:scale(1)}
        }

        .modal-body, .modal-dialog, .modal-content{
            background-color: white;
            border-radius: 20px;
        }

        .modal-content{
            padding: 30px;
        }

        .modal-dialog{
            margin-top: 13%;
        }

        form label{
            margin-top: 5%;
            color: #A34404;
            font-size: 25px;
            font-family: Purple Smile;
        }

        form input{
            padding: 10px;
            width: 100%;
            border-radius: 15px;
            border: 3px solid #B9B6B6;
            font-size: 22px;
            font-family: Tilt Warp Regular;
        }

        #class_name {
            width: 45%;
            font-size: 10px;
            margin-top: 3%;
            text-align: center;
        }

        label[for="class_name"] {
            font-family: 'Tilt Warp Regular';
            font-size: 15px;
            color: black;
        }

        .addBtn{
            margin-top: 5%;
            margin-left: 5%;
            width: 50%;
            padding: 10px;
            border-radius: 15px;
            background-color: #F8B500;
            color: white;
            border: none;
            font-size: 18px;
            font-family: 'Tilt Warp Regular';
            cursor: pointer;
            box-shadow: 0 6px 0 0 #BC8900;
        }


        /* The Close Button */
        .close {
            font-family: Tilt Warp Regular;
          color: #A34404;
          float: right;
          font-size: 28px;
          font-weight: bold;
          transition: 1.0s;
        }

        .close:hover,
        .close:focus {
          color: #CF5300;
          text-decoration: none;
          cursor: pointer;
        }

        .img-no-quiz {
            width: 130px;
            height: 120px;
            margin-left: auto;
            margin-right: auto;
            display: flex;
            border-radius: 100%;
        }

        .no-quiz-con {
            font-family: To Japan;
            width: 60%;
            margin: auto;
            padding: 10px 3px;
            margin-top: 100px;
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
                <a href="t_Home.php"><i class="fa-solid fa-house"></i>Dashboard</a>
                <a href="t_Students.php"><i class="fa-regular fa-address-book"></i>Students</a>
                <a href="t_SubjectsList.php" class="active"><i class="fa-solid fa-list"></i>Subjects</a>
            </div>
        </div>

        <!-- Content Area -->
        <div class="content">
                <div class="content-header">
                    <div><br>
                        <h1>Subjects</h1><br>
                        <hr>
                    </div>
                    <div class="actions">
                        <div class="profile"><img src="<?php echo $profilePic; ?>" onclick="profileDropdown()" width="50px" height="50px"></div>
                    </div>
                </div>
            
            <center>

            <div class="add-sub">
                <button id="modalbtn">Add Subject</button>
            </div>    
        
            <br><br><br>
            
            <center>

            <div><br><br>
                <?php
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        echo "<a class='subject-button' href='t_quizDash.php?subject_id=" . $row['subject_id'] . "'>" . $row['subject_name'] ."<br><span>". $row['subject_code'] ."</span></a>";
                    }
                } else {
                    echo "<div class='no-quiz-con'>";
                    echo "<p style='font-family: Tilt Warp Regular; font-size: 30px; margin-top: 10%; color: #999;'>No subjects created yet.</p>";
                    echo "</div>";
                }
                ?>
            </div>
       

            </center>

            <div id="myModal" class="modal">

              <!-- Modal content -->
              <div class="modal-content">
                <span class="close">&times;</span>
                <br>
                <form method="post" action="">
                    <input type="text" name="subject_name" placeholder="Enter Subject" required>
                    <br>
                    <label for="class_name">Enter Class Name (Optional):</label>
                    <input type="text" id="class_name" name="class_name">
                    <br>
                    <center>
                    <button class="addBtn" type="submit">Create Subject</button>
                    </center>
                </form>
              </div>
            </div>
        </center>
        
        </div>
    </div>        


<script>
    // Get the modal
    var modal = document.getElementById("myModal");

    // Get the button that opens the modal
    var btn = document.getElementById("modalbtn");

    // Get the <span> element that closes the modal
    var span = document.getElementsByClassName("close")[0];

    // When the user clicks the button, open the modal 
    btn.onclick = function() {
      modal.style.display = "block";
    }

    // When the user clicks on <span> (x), close the modal
    span.onclick = function() {
      modal.style.display = "none";
    }

    // When the user clicks anywhere outside of the modal, close it
    window.onclick = function(event) {
      if (event.target == modal) {
        modal.style.display = "none";
      }
    }
</script>

</body>
</html>