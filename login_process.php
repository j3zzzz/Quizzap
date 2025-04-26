<?php
session_start();
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
$password = $_POST['password'];

// Determine account type based on the format of the account number
if (strpos($account_number, 'T') === 0) {
    $account_type = 'teacher';
    $sql = "SELECT * FROM teachers WHERE account_number = '$account_number'";
} elseif (strpos($account_number, 'S') === 0) {
    $account_type = 'student';
    $sql = "SELECT * FROM students WHERE account_number = '$account_number'";
} else {
    ?>
    <script type="text/javascript">
    alert("Invalid account number format.");
    window.location.href="login.php";
    </script>
    <?php
    exit();
}

$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    if (password_verify($password, $user['password'])) {
        $_SESSION['username'] = $user['username'];
        $_SESSION['fname'] = $user['fname']; // Store first name in session
        $_SESSION['account_type'] = $account_type;
        $_SESSION['account_number'] = $account_number;
        setcookie("username", $row["username"], time() + (86400 * 30), "/");
        header("Location: dashboard_process.php");
        exit;
    } else {
        ?>
        <script type="text/javascript">
        alert("Invalid Credentials");
        window.location.href="login.php";
        </script>
        <?php
    }
} else {
        ?>
        <script type="text/javascript">
        alert("No user found with that account number.");
        window.location.href="login.php";
        </script>
        <?php
}

$conn->close();
?>
