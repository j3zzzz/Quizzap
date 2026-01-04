<?php
session_start();
include 'db_connect.php';

$account_number = $_POST['account_number'];
$password = $_POST['password'];

// Determine account type based on the format of the account number
if (strpos($account_number, 'T') === 0) {
    $account_type = 'teacher';
    $sql = "SELECT * FROM teachers WHERE account_number = ?";
} elseif (strpos($account_number, 'S') === 0) {
    $account_type = 'student';
    $sql = "SELECT * FROM students WHERE account_number = ?";
} else {
    // Redirect with error message
    header("Location: login.php?error=" . urlencode("Invalid account number format. Account numbers should start with 'T' for teachers or 'S' for students."));
    exit();
}

// Use prepared statement to prevent SQL injection
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $account_number);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    if (password_verify($password, $user['password'])) {
        if ($account_type == 'teacher') {
            if ($user['status'] == 'pending') {
                header("Location: login.php?error=" . urlencode("Your account is pending approval. Please wait for administrator approval."));
                exit();
            } elseif ($user['status'] == 'rejected') {
                header("Location: login.php?error=" . urlencode("Your account has been rejected. Please contact the administrator."));
                exit();
            } elseif ($user['status'] == 'approved') {
                // Proceed with login
                $_SESSION['account_type'] = $account_type;
                $_SESSION['account_number'] = $account_number;
                $_SESSION['fname'] = $user['fname'];
                setcookie("account_number", $user["account_number"], time() + (86400 * 30), "/");
                header("Location: dashboard_process.php");
                exit();
            }
        } else {
            // For students, proceed normally
            $_SESSION['account_type'] = $account_type;
            $_SESSION['account_number'] = $account_number;
            $_SESSION['fname'] = $user['fname'];
            setcookie("account_number", $user["account_number"], time() + (86400 * 30), "/");
            header("Location: dashboard_process.php");
            exit();
        }
    } else {
        // Redirect with error message
        header("Location: login.php?error=" . urlencode("Invalid password. Please try again."));
        exit();
    }
} else {
    // Redirect with error message
    header("Location: login.php?error=" . urlencode("No user found with that account number."));
    exit();
}

$stmt->close();
$conn->close();
?>