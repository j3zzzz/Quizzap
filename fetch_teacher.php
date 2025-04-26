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

$sql = "SELECT account_number, fname, lname FROM teachers";
$result = $conn->query($sql);

echo '<table data-type="teachers">';
echo '<tr><th>Account Number</th><th>First Name</th><th>Last Name</th><th></th></tr>';

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        echo '<tr data-account-number="' . $row["account_number"] . '">';
        echo '<td>' . $row["account_number"] . '</td>';
        echo '<td>' . $row["fname"] . '</td>';
        echo '<td>' . $row["lname"] . '</td>';
        echo '<td><button class="delete-button"><i class="fas fa-trash-alt"></i></button></td>';
        echo '</tr>';
    }
} else {
    echo '<tr><td colspan="4">No teachers found</td></tr>';
}

echo '</table>';

$conn->close();
?>