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
            flex-direction: column;
            min-height: 100vh;
            background-color: #f2f2f2;
        }

        /* Left side with sign-up form */
        .left {
            flex: 1;
            background-color: #F8B500;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            padding: 20px;
            order: 2;
        }

        /* Right side with text */
        .right {
            flex: 1;
            background-color: #ffffff;
            display: flex;
            justify-content: center;
            align-items: center;
            color: #999;
            font-weight: bold;
            flex-direction: column;
            padding: 40px 20px;
            text-align: center;
            order: 1;
        }

        /* Color styling for 'Sign Up' */
        .right span {
            color: #F8B500;
            font-family: Fredoka;
            font-size: 3rem;
        }

        .right p {
            color: #B4B2B2;
            font-size: 1rem;
            font-weight: normal;
            text-align: center;
            max-width: 300px;
            margin-top: 0.5rem;
            font-family: Fredoka;
        }

        /* QuizZap logo styling */
        .logo {
            font-size: 3rem;
            font-weight: bold;
            color: #ffffff;
            margin-bottom: .5rem;
            margin-top: 0;
        }

        .logo img {
            width: 360px;
            height: 130px;
            max-width: 100%;
            height: auto;
        }

        /* Form styling */
        .signup-form {
            background-color: #ffffff;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 500px;
            text-align: center;
            margin: 20px 0;
        }

        .signup-form input[type="text"], 
        .signup-form input[type="password"] {
            width: 100%;
            padding: 10px;
            border: 3px solid #B9B6B6;
            border-radius: 10px;
            box-sizing: border-box;
            font-family: Fredoka;
            font-size: 18px;
            margin-top: 3%;
            text-align: center;
        }

        .name-inputs {
            display: flex;
            gap: 1%;
            width: 100%;
        }

        .name-inputs input {
            width: 49.5%;
        }

        .password-inputs {
            display: flex;
            gap: 1%;
            width: 100%;
        }

        .password-inputs input {
            width: 49.5%;
        }

        .signup-form .btn {
            width: 95%;
            padding: 10px;
            background-color: #F8B500;
            color: #fff;
            border: 2px solid #f8b500;
            border-radius: 10px;
            font-size: 16px;
            cursor: pointer;
            font-family: Fredoka;
            letter-spacing: 1px;
            box-shadow: 0 4px 0 #BC8900;
            margin-top: 1rem;
        }

        .signup-form .btn:hover {
            background-color: white;
            color: #f8b500;
        }

        .signup-form p {
            margin-top: 1rem;
            font-size: 0.9rem;
            color: #555;
            font-family: Fredoka;
        }

        .signup-form a {
            color: #F8B500;
            text-decoration: none;
            font-weight: bold;
            font-family: Fredoka;
        }

        .signup-form a:hover {
            text-decoration: underline;
        }

        .error-message {
            font-family: Fredoka;
            color: red;
            margin-bottom: 10px;
        }

        /* Media queries for responsiveness */
        @media (min-width: 768px) {
            .container {
                flex-direction: row;
            }
            
            .left {
                order: 1;
                padding: 40px;
            }
            
            .right {
                order: 2;
                padding: 40px;
            }
            
            .right span {
                font-size: 4rem;
            }
            
            .logo {
                margin-top: -6%;
            }
        }

        @media (min-width: 992px) {
            .right span {
                font-size: 4.5rem;
            }
            
            .right p {
                font-size: 1.2rem;
                max-width: 400px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Left Side -->
        <div class="left">
            <div class="logo"><img src="img/logo4.png" width="360px" height="130px" alt="QuizZap Logo"></div>
            <div class="signup-form">
            <?php 
            if (isset($_SESSION['error_message'])) {
                echo '<div class="error-message">' . $_SESSION['error_message'] . '</div>';
                unset($_SESSION['error_message']);
            }
            ?>
                <form method="POST" action="t_Signup_process.php">
                    <input type="text" name="account_number" value="<?php echo $teacher_account_number; ?>" readonly>
                    
                    <div class="name-inputs">
                        <input type="text" id="fname" name="fname" placeholder="First name" required>
                        <input type="text" id="lname" name="lname" placeholder="Last name" required>
                    </div>
                    
                    <div class="password-inputs">
                        <input type="password" id="password" name="password" placeholder="Password" required>
                        <input type="password" id="password2" name="password2" placeholder="Confirm password" required>
                    </div>
                    
                    <center>
                        <input class="btn" type="submit" value="Register Account">
                        <p class="login-link">Already have an account? <a href="login.php">Login!</a></p>
                    </center>           
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