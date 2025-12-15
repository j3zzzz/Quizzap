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

// Generate school ID (2 uppercase letters + 2 digits)
$letters = substr(str_shuffle("ABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 2);
$numbers = str_pad(rand(0, 99), 2, '0', STR_PAD_LEFT);
$school_id = $letters . $numbers;

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

        .password-field-container {
            position: relative;
            width: 49.5%;
        }

        .password-field-container input {
            width: 100%;
            padding-right: 40px;
        }

        .toggle-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #888;
            font-size: 16px;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .toggle-password svg {
            width: 18px;
            height: 18px;
            fill: #666;
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

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background-color: white;
            padding: 2rem;
            border-radius: 15px;
            text-align: center;
            max-width: 400px;
            width: 90%;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            animation: modalAppear 0.3s ease-out;
        }

        @keyframes modalAppear {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }

        .error-modal .modal-icon {
            color: #f44336;
        }

        .success-modal .modal-icon {
            color: #4CAF50;
        }

        .modal-title {
            color: #333;
            font-family: Fredoka;
            font-size: 1.8rem;
            margin-bottom: 1rem;
        }

        .modal-message {
            color: #666;
            font-family: Fredoka;
            font-size: 1.1rem;
            margin-bottom: 1.5rem;
            line-height: 1.5;
        }

        .modal-button {
            background-color: #F8B500;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
            font-family: Fredoka;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 0 #BC8900;
        }

        .modal-button:hover {
            background-color: #e6a300;
            transform: translateY(-2px);
        }

        .modal-button:active {
            transform: translateY(1px);
            box-shadow: 0 2px 0 #BC8900;
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
                <form method="POST" action="t_Signup_process.php" onsubmit="return validateForm()">
                    <input type="text" name="account_number" value="<?php echo $teacher_account_number; ?>" readonly>

                    <input type="text" name="school_id" value="<?php echo $school_id; ?>" readonly>
                    <small style="display: block; margin-bottom: 10px; color: #555; font-family: Fredoka;">Your School ID: <?php echo $school_id; ?></small>

                    <div class="name-inputs">
                        <input type="text" id="fname" name="fname" placeholder="First name" required>
                        <input type="text" id="lname" name="lname" placeholder="Last name" required>
                    </div>
                    
                    <div class="password-inputs">
                        <div class="password-field-container">
                            <input type="password" id="password" name="password" placeholder="Password" required>
                            <button type="button" class="toggle-password" onclick="togglePassword('password')">
                                <svg viewBox="0 0 24 24">
                                    <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                                </svg>
                            </button>
                        </div>
                        <div class="password-field-container">
                            <input type="password" id="password2" name="password2" placeholder="Confirm password" required>
                            <button type="button" class="toggle-password" onclick="togglePassword('password2')">
                                <svg viewBox="0 0 24 24">
                                    <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                                </svg>
                            </button>
                        </div>
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

    <!-- Success Modal -->
    <div id="successModal" class="modal success-modal">
        <div class="modal-content">
            <div class="modal-icon">✓</div>
            <h2 class="modal-title">Registration Successful!</h2>
            <p class="modal-message">Your teacher account has been created successfully. You can now log in to create and manage quizzes.</p>
            <button class="modal-button" onclick="redirectToLogin()">Go to Login</button>
        </div>
    </div>

    <!-- Error Modal -->
    <div id="errorModal" class="modal error-modal">
        <div class="modal-content">
            <div class="modal-icon">✗</div>
            <h2 class="modal-title">Validation Error</h2>
            <p id="errorModalMessage" class="modal-message"></p>
            <button class="modal-button" onclick="closeErrorModal()">OK</button>
        </div>
    </div>

    <script>
        function togglePassword(fieldId) {
            const passwordInput = document.getElementById(fieldId);
            const toggleButton = passwordInput.nextElementSibling.querySelector('svg');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                // Change to eye-slash icon
                toggleButton.innerHTML = '<path d="M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.83l2.92 2.92c1.51-1.26 2.7-2.89 3.43-4.75-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-3.98.7l2.16 2.16C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46C3.08 8.3 1.78 10.02 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27zM7.53 9.8l1.55 1.55c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3 .22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53-2.76 0-5-2.24-5-5 0-.79.2-1.53.53-2.2zm4.31-.78l3.15 3.15.02-.16c0-1.66-1.34-3-3-3l-.17.01z"/>';
            } else {
                passwordInput.type = 'password';
                // Change back to eye icon
                toggleButton.innerHTML = '<path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>';
            }
        }

        function showErrorModal(message) {
            const modal = document.getElementById('errorModal');
            const messageElement = document.getElementById('errorModalMessage');
            messageElement.textContent = message;
            modal.style.display = 'flex';
        }

        function closeErrorModal() {
            const modal = document.getElementById('errorModal');
            modal.style.display = 'none';
        }

        function validateForm() {
            // Password validation
            var password = document.getElementById("password").value;
            var password2 = document.getElementById("password2").value;

            if (password !== password2) {
                showErrorModal("Passwords do not match!");
                return false;
            }

            if (password.length < 8) {
                showErrorModal("Password must be at least 8 characters long!");
                return false;
            }

            return true;
        }

        // Check if registration was successful and show modal
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('success') === '1') {
                showSuccessModal();
            }
        });

        function showSuccessModal() {
            const modal = document.getElementById('successModal');
            modal.style.display = 'flex';
            
            // Clear the success parameter from URL
            if (window.history.replaceState) {
                const newUrl = window.location.pathname;
                window.history.replaceState({}, document.title, newUrl);
            }
        }

        function redirectToLogin() {
            window.location.href = "login.php";
        }

        // Close modal when clicking outside of it
        window.onclick = function(event) {
            const errorModal = document.getElementById('errorModal');
            const successModal = document.getElementById('successModal');
            
            if (event.target === errorModal) {
                closeErrorModal();
            }
            if (event.target === successModal) {
                successModal.style.display = 'none';
            }
        }
    </script>
</body>
</html>