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

// Generate account number for teacher
$sql = "SELECT MAX(CAST(SUBSTRING(account_number, 2) AS UNSIGNED)) AS max_account FROM teachers";
$result = $conn->query($sql);
$row = $result->fetch_assoc();
$max_account_number = $row['max_account'];

if ($max_account_number) {
    $teacher_account_number = 'T' . str_pad($max_account_number + 1, 3, '0', STR_PAD_LEFT);
} else {
    $teacher_account_number = 'T001';
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QuizZap Sign Up</title>
    <style>
        /* Reset some default styling */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body, html {
            height: 100%;
        }

        /* Main container styling */
        .container {
            display: flex;
            height: 100vh;
            background-color: #f2f2f2;
        }

        /* Left side with sign-up form */
        .left {
            flex: 1;
            background-color: #F8B500;
            text-align: left;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
        }

        /* Right side with text */
        .right {
            flex: 1;
            background-color: #ffffff;
            display: flex;
            justify-content: center;
            align-items: center;
            color: #999;
            font-size: 6rem;
            font-weight: bold;
            flex-direction: column;
        }

        /* Color styling for 'Sign Up' */
        .right span {
            text-align: left;
            color: #F8B500;
            font-size: 4.5rem;
            font-family: Tilt Warp Regular;
        }

        .right p {
            color: #B4B2B2;
            font-size: 1rem;
            font-weight: normal;
            text-align: center;
            max-width: 300px;
            margin-top: 0.5rem;
            font-family: Tilt Warp Regular;
        }

        /* QuizZap logo styling */
        .logo {
            font-size: 3rem;
            font-weight: bold;
            color: #ffffff;
            margin-bottom: .5rem;
            margin-top: -6%;
        }

        /* Form styling */
        .signup-form {
            background-color: #ffffff;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            width: 80%;
            max-width: 1000px;
            text-align: center;
        }

        .signup-form input[type="text"], .signup-form input[type="password"] {
            width: 100%;
            padding: 10px;
            border: 3px solid #B9B6B6;
            border-radius: 10px;
            box-sizing: border-box;
            font-family: Tilt Warp Regular;
            font-size: 18px;
            margin-top: 3%;
            text-align: center;
        }

        .signup-form .btn {
            width: 95%;
            padding: 10px;
            background-color: #F8B500;
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            cursor: pointer;
            font-family: Tilt Warp Regular;
            letter-spacing: 1px;
            box-shadow: 0 4px 0 #BC8900 ;
            margin-top: 1rem;
            border: 2px solid #f8b500;
        }

        .signup-form .btn:hover {
            background-color: white;
            color: #f8b500;
        }

        .signup-form p {
            margin-top: 1rem;
            font-size: 0.9rem;
            color: #555;
            font-family: Tilt Warp Regular;
        }

        .signup-form a {
            color: #F8B500;
            text-decoration: none;
            font-weight: bold;
            font-family: Tilt Warp Regular;
        }

        .signup-form a:hover {
            text-decoration: underline;
        }

        .error-message {
            font-family: 'Tilt Warp Regular';
            color: red;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Left Side -->
        <div class="left">
            <div class="logo"><img src="img/logo4.png" width="360px" height="130px"></div>
            <div class="signup-form">
            <?php 
            if (isset($_SESSION['error_message'])) {
                echo '<div class="error-message">' . $_SESSION['error_message'] . '</div>';
                unset($_SESSION['error_message']);
            }
            ?>
                <form method="POST" action="t_Signup_process.php">
                    <br>
                    <input type="text" name="account_number" value="<?php echo $teacher_account_number; ?>" readonly>
                    <br><input style="width: 49%;" type="text" id="fname" name="fname" placeholder="First name" required> <input style="width: 50%;" type="text" id="lname" name="lname" placeholder="Last name" required>
                    <input style="width: 49%;" type="password" id="password" name="password" placeholder="Password" required> <input style="width: 50%;" type="password" id="password2" name="password2" placeholder="Confirm password" required><br><br>

                    <center>
                    <input class="btn" type="submit" value="Register Account">
                    <p class="login-link">Already have a account? <a style="color: #F8B500;" href="login.php">Login!</a></p></center>           
                </form>
            </div>
        </div>

        <!-- Right Side -->
        <div class="right">
            <p><span>Sign Up.</span></p>
            <p>Create your own quizzes and share them with your students.</p>
        </div>
    </div>
</body>
</html>
