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

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$teacher_id = $_SESSION['teacher_id'];

$sql = "
    SELECT s.account_number, s.fname, s.lname, s.glevel, s.strand 
    FROM students AS students
    JOIN enrollments AS e ON s.student_id = e.student_id
    WHERE sub.teacher_id = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();

echo '<table data-type="students">';
echo '<tr><th>Account Number</th><th>First Name</th><th>Last Name</th><th>Grade Level</th><th>Strand</th><th></th></tr>';

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        echo '<tr data-account-number="' . $row["account_number"] . '">';
        echo '<td>' . $row["account_number"] . '</td>';
        echo '<td>' . $row["fname"] . '</td>';
        echo '<td>' . $row["lname"] . '</td>';
        echo '<td>' . $row["glevel"] . '</td>';
        echo '<td>' . $row["strand"] . '</td>';
        echo '<td><button class="delete-button"><i class="fas fa-trash-alt"></i></button></td>';
        echo '</tr>';
    }
} else {
    echo '<tr><td colspan="6">No students found</td></tr>';
}

echo '</table>';

$stmt->close();
$conn->close();
?>