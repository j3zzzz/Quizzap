<?php
$servername = "localhost";     // MySQL Hostname (from InfinityFree)
$username = "root";          // MySQL Username
$password = "";       // Your database password
$dbname = "rawrit";   // Database name

$conn = mysqli_connect($servername, $username, $password, $dbname);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>
