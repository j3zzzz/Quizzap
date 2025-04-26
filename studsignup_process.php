<!-- register_student_process.php -->
<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "rawrit";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$account_number = $_POST['account_number'];
$fname = $_POST['fname'];
$lname = $_POST['lname'];
$glevel = $_POST['glevel'];
$strand = $_POST['strand'];
$password = password_hash($_POST['password'], PASSWORD_BCRYPT);
$password2 = $_POST['password2'];

$sql = "INSERT INTO students (account_number, fname, lname, glevel, strand, password) VALUES ('$account_number', '$fname', '$lname', '$glevel', '$strand', '$password')";

if ($conn->query($sql) === TRUE) {
    header("Location: login.php");
} else {
    echo "Error: " . $sql . "<br>" . $conn->error;
}

$conn->close();
?>
