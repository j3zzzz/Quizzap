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
        header("Location: s_Signup.php");
        exit();
    }

    if (strlen($_POST['password']) < 8) {
        $_SESSION['error_message'] = "Password must be at least 8 characters long.";
        header("Location: s_Signup.php");
        exit();
    }

    // Find the last account number and increment it
    $last_account_sql = "SELECT account_number FROM students ORDER BY account_number DESC LIMIT 1";
    $last_account_result = $conn->query($last_account_sql);
    
    if ($last_account_result->num_rows > 0) {
        $last_account_row = $last_account_result->fetch_assoc();
        $last_account_number = $last_account_row['account_number'];
        
        // Extract the numeric part and increment
        $numeric_part = intval(substr($last_account_number, 1));
        $new_numeric_part = $numeric_part + 1;
        
        // Generate new account number
        $account_number = 'S' . str_pad($new_numeric_part, 3, '0', STR_PAD_LEFT);
    } else {
        // If no students exist, start with S001
        $account_number = 'S001';
    }

    // Sanitize other form inputs
    $fname = mysqli_real_escape_string($conn, $_POST['fname']);
    $lname = mysqli_real_escape_string($conn, $_POST['lname']);
    $glevel = mysqli_real_escape_string($conn, $_POST['glevel']);
    $strand = isset($_POST['strand']) ? mysqli_real_escape_string($conn, $_POST['strand']) : '';
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT); // Hash the password

    // Prepare SQL statement
    $sql = "INSERT INTO students (account_number, fname, lname, glevel, strand, password) 
            VALUES (?, ?, ?, ?, ?, ?)";
    
    // Create a prepared statement
    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        // Bind parameters using the dynamically generated account number
        $stmt->bind_param("ssssss", $account_number, $fname, $lname, $glevel, $strand, $password);
    
        $check_user_sql = $conn->prepare ("SELECT * FROM students WHERE fname = ? and lname = ?");
        $check_user_sql->bind_param("ss", $fname, $lname);
        $check_user_sql->execute();
        $check_user_sql->store_result();
        
        if ($check_user_sql->num_rows > 0) {
            $_SESSION['error_message'] = "User with this name already exists.";
            header("Location: s_Signup.php");
            exit();
        }

        // Execute the statement
        if ($stmt->execute()) {
            ?>
                <script>
                alert("You are successfully registered!");
                window.location.href = "login.php";
                </script>
            <?php
        } else {
            // Provide more detailed error handling
            if ($stmt->errno == 1062) { // Duplicate entry error
                $_SESSION['error_message'] = "Account number already exists. Please try again.";
            } else {
                $_SESSION['error_message'] = "Registration failed: " . $stmt->error;
            }
            header("Location: s_Signup.php");
            exit();
        }
        
        $stmt->close();
    } else {
        $_SESSION['error_message'] = "Preparation error: " . $conn->error;
        header("Location: s_Signup.php");
        exit();
    }
}

$conn->close();
?>