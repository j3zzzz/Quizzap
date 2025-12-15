<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QuizZap Login</title>
    <style>
        /* Reset some default styling */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Fredoka;
        }

        body, html {
            height: 100%;
        }

        /* Main container styling */
        .container {
            display: flex;
            flex-direction: column;
            height: 100vh;
            background-color: black;
        }

        /* Left side with slogan */
        .left {
            flex: 0 0 auto;
            background-color: #ffffff;
            display: flex;
            justify-content: center;
            align-items: center;
            color: #B4B2B2;
            font-size: 2rem;
            font-weight: bold;
            font-family: Fredoka;
            padding: 1rem;
            text-align: center;
        }

        /* Color styling for 'fun' */
        .left span {
            color: #F8B500;
        }

        .left p, span {
            font-family: Fredoka;
        }

        /* Right side with login form */
        .right {
            flex: 1;
            background-color: #F8B500;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            padding: 1rem;
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
            max-width: 360px;
            width: 100%;
            height: auto;
        }

        /* Form styling */
        .form-container {
            background-color: #ffffff;
            padding: 2rem;
            border-radius: 25px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 500px;
            text-align: center;
        }

        .form-container input {
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

        /* Password container with eye icon */
        .password-container {
            position: relative;
            width: 100%;
            margin-top: 3%;
        }

        .password-container input {
            padding-right: 40px;
            text-align: center;
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

        .form-container .btn {
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
        }

        .form-container .btn:hover {
            background-color: white;
            color: #f8b500;
        }

        .form-container .register-link {
            text-decoration: none;
            display: block;
            text-align: center;
            margin-top: 10px;
            font-size: 12px;
            color: black;
            font-family: Fredoka;
        }

        .form-container a {
            text-decoration: none;
            font-family: Fredoka;
        }

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background-color: white;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 400px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }

        .modal-content h3 {
            color: #333;
            margin-bottom: 15px;
            font-size: 24px;
        }

        .modal-content p {
            color: #666;
            margin-bottom: 20px;
            font-size: 16px;
        }

        .modal-btn {
            background-color: #F8B500;
            color: white;
            border: none;
            padding: 10px 30px;
            border-radius: 10px;
            font-size: 16px;
            cursor: pointer;
            font-family: Fredoka;
        }

        .modal-btn:hover {
            background-color: #e6a400;
        }

        /* Media queries for responsiveness */
        @media (min-width: 768px) {
            .container {
                flex-direction: row;
            }
            .left {
                flex: 1;
                font-size: 3rem;
            }
            .right {
                flex: 1;
            }
            .logo {
                margin-top: -10%;
            }
        }

        @media (min-width: 992px) {
            .left {
                font-size: 4rem;
            }
        }
    </style>
</head>
<body>
    <!-- Modal for error messages -->
    <div id="errorModal" class="modal">
        <div class="modal-content">
            <h3>Login Error</h3>
            <p id="errorMessage"></p>
            <button class="modal-btn" onclick="closeModal()">OK</button>
        </div>
    </div>

    <div class="container">
        <div class="left">
            <p>Educational <span>fun.</span></p>
        </div>

        <div class="right">
            <div class="logo"><img src="img/logo4.png" width="360px" height="130px"></div>
            <div class="form-container">
                <form method="POST" action="login_process.php" id="loginForm"><br>
                    <input type="text" id="account_number" name="account_number" placeholder="Account number" required><br>
                    
                    <div class="password-container">
                        <input type="password" id="password" name="password" placeholder="Password" required>
                        <button type="button" class="toggle-password" onclick="togglePassword()">
                            <!-- Simple eye icon SVG -->
                            <svg viewBox="0 0 24 24">
                                <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                            </svg>
                        </button>
                    </div>

                    <center>
                    <input class="btn" type="submit" value="Login">
                    <p class="register-link">Don't have a account? <a style="color: #F8B500;" href="acctype.php">Create an account!</a></p></center><br>           
                </form>
            </div>
        </div>
    </div>

    <script>
        // Password toggle function
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleButton = document.querySelector('.toggle-password svg');
            
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

        // Modal functions
        function showError(message) {
            document.getElementById('errorMessage').textContent = message;
            document.getElementById('errorModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('errorModal').style.display = 'none';
        }

        // Check for URL error parameters
        window.onload = function() {
            const urlParams = new URLSearchParams(window.location.search);
            const error = urlParams.get('error');
            
            if (error) {
                showError(decodeURIComponent(error));
                // Clear the error from URL
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('errorModal');
            if (event.target === modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>