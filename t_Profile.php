<?php
// Start the session
session_start();

// Database connection details
$servername = "localhost";
$username = "root";  // Database username
$password = "";      // Database password
$dbname = "rawrit";  // Database name

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
$sql = "SELECT fname, lname, account_number FROM teachers WHERE account_number = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $account_number);
$stmt->execute();
$result = $stmt->get_result();

// Fetch the result
if ($result->num_rows > 0) {
    // Fetch the data into an associative array
    $row = $result->fetch_assoc();
    $name = $row['fname'];
    $lname = $row['lname'];
} else {
    // If no data is found, set default values
    $name = "Unknown";
    $grade_level = "Unknown";
}

// Close the database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Page</title>
    <style>
        {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: Arial, sans-serif;
    background-color: #ed5e00;
    color: white;
}

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
    font-family: Purple Smile;
    letter-spacing: .5px;
}

header nav a:hover{
    color: #923a00;
    transform: scale(1.1);
}

.active{
    font-size: 25px;
    color: white;
}

main {
    padding: 20px;
    display: flex;
    justify-content: center;
    align-items: center;
    flex-direction: column;
}

.profile-card {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 50%;
    padding: 50px;
    background-color: #FFEFE4;
    border-radius: 10px;
    box-shadow: 0 4px 8px 0 rgba(0, 0, 0, 0.2), 0 6px 20px 0 rgba(0, 0, 0, 0.19);
}

.profile-icon {
    width: 30%;
    height: 170px;
    background-color: #ff7a3b;
    border-radius: 50%;
    margin-right: 45px;
}

.vertical-line {
    width: 1px;
    height: 200px;
    background-color: #ffffff;
    margin-right: 20px;
    border-left: 2px solid white;
}

.profile-details {
    display: flex;
    flex-direction: column;
    color: #ffc49c;
    font-family: Purple Smile;
}

p {
  text-indent: 50px;
  color: #cf5300;
}
    </style>
</head>
<body>
    <header>
        <div class="logo"><img src="rawrit2.png" width="120px" height="40px"></div>
        <nav>
            <a href="t_Home.php">Home</a>
            <a href="t_Students.php">Students</a>
            <a href="t_Subjects.php">Subjects</a>
            <a class="active" style="margin-right: 50px;" href="t_Profile.php">Profile</a>
            <form action="logout.php" method="POST">
            <input type="submit" value="Logout">
            </form>

        </nav>
    </header>
    <main><br><br>
    <div class="profile-card">
        <img src="default.png" class="profile-icon">
        <div class="vertical-line"></div>
        <div class="profile-details">
            <span>Account Number:</span>
            <p><?php echo htmlspecialchars($account_number); ?></p>
            <span>Name:</span>
            <p><?php echo htmlspecialchars($fname); ?></p>
            <p><?php echo htmlspecialchars($lname); ?></p>
        </div>
    </div>
    </main>
</body>
</html>
