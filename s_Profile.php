<?php
// Start the session
session_start();
if (strpos($_SESSION['account_number'], 'S') !== 0) {
    header("Location: login.php");
    exit();
}

// Database connection details
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "rawrit";

// Create a connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check if the connection is successful
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Assuming the user is logged in and their account number is stored in a session
$account_number = $_SESSION['account_number'];
$fname = $_SESSION['fname'];

// SQL query to fetch the student's profile data
$sql = "SELECT fname, lname, account_number, glevel, strand FROM students WHERE account_number = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $account_number);
$stmt->execute();
$result = $stmt->get_result();

// Fetch the result
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $name = $row['fname'];
    $lname = $row['lname'];
    $glevel = $row['glevel'];
    $strand = $row['strand'];
} else {
    $name = "Unknown";
    $grade_level = "Unknown";
}

$loggedInUser = $_SESSION['account_number'];

$sql = "SELECT profile_pic FROM students WHERE account_number = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $loggedInUser);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $profilePic = $row['profile_pic'] ?: "uploads/default_pic.png";
} else {
    $profilePic = "uploads/default_pic.png";
}

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Page</title>
    <style>
        /* Resetting margins, padding, and setting box-sizing */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

/* General body styling */
body {
    font-family: Arial, sans-serif;
    background-color: #ed5e00;
    color: white;
}

/* Header styling */
header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    background-color: #CF5300;
    box-shadow: 0 3px 5px rgba(0, 0, 0, 0.3);
}

header nav a {
    margin: 0 15px;
    text-decoration: none;
    color: #FFC49C;
    font-size: 20px;
    font-family: "Purple Smile", cursive;
    letter-spacing: .5px;
}

header nav a:hover {
    color: #923a00;
    transform: scale(1.1);
}

.active {
    font-size: 25px;
    color: white;
}

/* Main content styling */
main {
    padding: 20px;
    display: flex;
    justify-content: center;
    align-items: center;
    flex-direction: column;
}

/* Profile card styling */
.profile-card {
    display: flex;
    width: 50%;
    padding: 50px;
    background-color: #FFEFE4;
    border-radius: 10px;
    box-shadow: 0 4px 8px 0 rgba(0, 0, 0, 0.2), 0 6px 20px 0 rgba(0, 0, 0, 0.19);
    margin: 0 auto;
    margin-top: 20px;
}

/* Left and right sections within the profile card */
.profile-card .left,
.profile-card .right {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
}

/* Left side content styling */
.profile-card .left img {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    margin-bottom: 20px;
}

.profile-card .edit-button {
    margin-top: 20px;
}

/* Right side profile details styling */
.profile-details {
    color: #cf5300;
    font-size: 18px;
    text-align: left;
}

.profile-details span {
    font-weight: bold;
    color: #ff7a3b;
}

.profile-details p {
    color: #cf5300;
    margin-bottom: 15px;
}

/* Vertical line styling between two sections */
.vertical-line {
    width: 1px;
    background-color: #f8b500;
    margin: 0 20px;
    border-left: 2px solid white;
}

/* Profile icon styling */
.profile-icon {
    float: left;
    width: 27%;
    height: 220px;
    background-color: #ff7a3b;
    border-radius: 50%;
    margin-bottom: 8%;
}

/* Paragraph styling */
p {
    text-indent: 50px;
    color: #cf5300;
}

/* Edit button styling */
.edit-button {
    float: left;
    margin-top: 40%;
}

/* Update Profile Picture button styling */
#updateProfilePicBtn {
    width: 300px;
    float: left;
    font-family: "Baskerville Old Face Regular", sans-serif;
    font-weight: bold;
    padding: 10px 20px;
    background-color: #ecd094;
    color: black;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    display: block;
    margin: 0 auto;
}

/* Upload button styling */
#upload-btn {
    width: 76%;
    margin-left: 20px;
}

input[type="file"],
#upload-btn {
    border: none;
    background-color: #ecd094;
    font-family: "Baskerville Old Face Regular", sans-serif;
    padding: 5px 10px;
    border-radius: 5px;
    font-size: 20px;
    align-items: center;
    text-align: center;
}

button:hover {
    background-color: #444;
}

.btn {
    background-color: #4CAF50;
    border: none;
    color: white;
    padding: 15px 32px;
    text-align: center;
    text-decoration: none;
    display: inline-block;
    font-size: 16px;
    margin: 4px 2px;
    cursor: pointer;
}

.btn-update {
    background-color: #008CBA;
}

/* Modal styling */
.modal {
    display: none;
    position: fixed;
    z-index: 1;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0, 0, 0, 0.4);
    padding-top: 60px;
    transition: all 0.1s ease;
}

.modal-content {
    background: rgba(255, 255, 255, 0.4);
    backdrop-filter: blur(4px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 20px;
    margin: 5% auto;
    padding: 5px 10px;
    width: 190px;
    height: 110px;
}

.close {
    color: #aaa;
    float: right;
    font-size: 28px;
    font-weight: bold;
}

.close:hover,
.close:focus {
    color: black;
    text-decoration: none;
    cursor: pointer;
}

/* Navbar profile picture styling */
.navbar-profile-pic {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    object-fit: cover;
    margin-left: 10px;
}

/* Profile container styling */
.profile-container {
    position: relative;
    display: inline-block;
}

.profile-pic {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    cursor: pointer;
}

/* Dropdown content styling */
.dropdown-content {
    display: none;
    position: absolute;
    background-color: #f9f9f9;
    min-width: 160px;
    box-shadow: 0px 8px 16px 0px rgba(0, 0, 0, 0.2);
    z-index: 1;
}

.dropdown-content a {
    color: black;
    padding: 12px 16px;
    text-decoration: none;
    display: block;
}

.dropdown-content a:hover {
    background-color: #f1f1f1;
}

/* Custom file upload styling */
#profile_pic {
    display: none;
}

.custom-file-upload {
    font-family: "Baskerville Old Face Regular", sans-serif;
    margin: 10px 0;
    padding: 10px 20px;
    background-color: #ecd094;
    color: black;
    border: 2px solid #cf5300;
    border-radius: 5px;
    cursor: pointer;
    text-align: center;
    display: inline-block;
    font-size: 16px;
}

.custom-file-upload:hover {
    background-color: #ff7a3b;
    color: white;
}

    </style>
</head>
<body>
    <header>
        <div class="logo"><img src="rawrit2.png" width="120px" height="40px"></div>
        <nav>
            <a href="s_Home.php">Home</a>
            <a href="studClasses.php">Classes</a>
            <a href="studQuizzes.php">Quizzes</a>
            <div class="profile-container">
                <img src="<?php echo htmlspecialchars($profilePic); ?>" alt="Profile Picture" class="profile-pic" onclick="toggleDropdown()" />
                <div id="dropdown-menu" class="dropdown-content">
                    <a href="#">Profile</a>
                    <a href="#">Settings</a>
                    <a href="login.php">Logout</a>
                </div>
            </div>
        </nav>
    </header>

    <main>
        <div class="profile-card">
            <div class="left">
                <img src="<?php echo htmlspecialchars($profilePic); ?>" alt="Profile Picture" class="profile-icon">
                <button id="updateProfilePicBtn" class="btn btn-update">Update Profile Picture</button>
                <div class="modal" id="profilePicModal">
                    <div class="modal-content">
                        <span class="close">&times;</span>
                        <form id="uploadForm" action="upload.php" method="POST" enctype="multipart/form-data">
                            <label for="profile_pic" class="custom-file-upload">Choose a File</label>
                            <input type="file" name="profile_pic" id="profile_pic" onchange="document.getElementById('uploadForm').submit();">
                            <button type="submit" id="upload-btn">Upload</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="vertical-line"></div>
            <div class="right profile-details">
                <span>Account Number:</span><p><?php echo htmlspecialchars($account_number); ?></p>
                <span>Name:</span><p><?php echo htmlspecialchars($fname); ?></p><p><?php echo htmlspecialchars($lname); ?></p>
                <span>Grade Level:</span><p><?php echo htmlspecialchars($glevel); ?></p>
                <span>Strand:</span><p><?php echo htmlspecialchars($strand); ?></p>
            </div>
        </div>
    </main>

    <script>
        var modal = document.getElementById("profilePicModal");
        var btn = document.getElementById("updateProfilePicBtn");
        var span = document.getElementsByClassName("close")[0];

        btn.onclick = function() {
            modal.style.display = "block";
        };

        span.onclick = function() {
            modal.style.display = "none";
        };

        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = "none";
            }
        };

        function toggleDropdown() {
            var dropdown = document.getElementById("dropdown-menu");
            dropdown.style.display = dropdown.style.display === "block" ? "none" : "block";
        }

        window.onclick = function(event) {
            if (!event.target.matches('.profile-pic')) {
                var dropdowns = document.getElementsByClassName("dropdown-content");
                for (var i = 0; i < dropdowns.length; i++) {
                    var openDropdown = dropdowns[i];
                    if (openDropdown.style.display === "block") {
                        openDropdown.style.display = "none";
                    }
                }
            }
        }
    </script>
</body>
</html>
