<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QuizZap Admin Login</title>
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

        /* Color styling for 'Admin' */
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

        .form-container .warning {
            color: red;
            font-size: 14px;
            margin-top: 10px;
            display: none;
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
    <div class="container">
        <div class="left">
            <p>Admin <span>Portal.</span></p>
        </div>

        <div class="right">
            <div class="logo"><img src="img/logo4.png" width="360px" height="130px"></div>
            <div class="form-container">
                <form method="POST" action="admin_login_process.php" id="loginForm">
                    <input type="text" id="admin_id" name="admin_id" placeholder="Admin ID" required><br>
                    <input type="password" id="password" name="password" placeholder="Password" required><br>
                    <div class="warning" id="errorMessage"></div>
                    <center>
                    <input class="btn" type="submit" value="Login">
                    </center><br>           
                </form>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const adminId = document.getElementById('admin_id').value;
            const password = document.getElementById('password').value;
            const errorMessage = document.getElementById('errorMessage');
            
            // Basic validation
            if (!adminId.startsWith('A')) {
                errorMessage.textContent = "Admin ID must start with 'A'";
                errorMessage.style.display = 'block';
                return;
            }
            
            // If validation passes, submit the form
            this.submit();
        });
    </script>
</body>
</html>