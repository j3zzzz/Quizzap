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

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    if (($_POST['password']) !== ($_POST['password2'])) {
        $_SESSION['error_message'] = "Passwords do not match.";
        header("Location: t_Signup.php");
        exit();
    }

    if (strlen($_POST['password']) < 8) {
        $_SESSION['error_message'] = "Password must be at least 8 characters long.";
        header("Location: t_Signup.php");
        exit();
    }

    // Find the last account number and increment it
    $last_account_sql = "SELECT account_number FROM teachers ORDER BY account_number DESC LIMIT 1";
    $last_account_result = $conn->query($last_account_sql);

    if ($last_account_result->num_rows > 0) {
        $last_account_row = $last_account_result->fetch_assoc();
        $last_account_number = $last_account_row['account_number'];
        
        // Extract the numeric part and increment
        $numeric_part = intval(substr($last_account_number, 1));
        $new_numeric_part = $numeric_part + 1;
        
        // Generate new account number
        $account_number = 'T' . str_pad($new_numeric_part, 3, '0', STR_PAD_LEFT);
    } else {
        // If no teachers exist, start with T001
        $account_number = 'T001';
    }

    $fname = mysqli_real_escape_string($conn, $_POST['fname']);
    $lname = mysqli_real_escape_string($conn, $_POST['lname']);
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);

    $sql = "INSERT INTO teachers (account_number, fname, lname, password) VALUES (?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("ssss", $account_number, $fname, $lname, $password);

        $check_user_sql = $conn->prepare ("SELECT * FROM teachers WHERE fname = ? and lname = ?");
        $check_user_sql->bind_param("ss", $fname, $lname);
        $check_user_sql->execute();
        $check_user_sql->store_result();

        if ($check_user_sql->num_rows > 0) {
            $_SESSION['error_message'] = "Teacher with this name already exists.";
            header("Location: t_Signup.php");
            exit();
        }

        if ($stmt->execute()) {
            ?>
            <script> 
            alert("Registration successful.");
            window.location.href = "login.php";;
            </script>
            <?php
        } else {
            $_SESSION['error_message'] = "Error: " . $sql . "<br>" . $conn->error;
            header("Location: t_Signup.php");
            exit();
        }
    }
}
$conn->close();
?>
 