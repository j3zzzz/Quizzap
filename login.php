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
            font-family: Arial, sans-serif;
        }

        body, html {
            height: 100%;
        }

        /* Main container styling */
        .container {
            display: flex;
            height: 100vh;
            background-color: black;
        }

        /* Left side with slogan */
        .left {
            flex: 1;
            background-color: #ffffff;
            display: flex;
            justify-content: center;
            align-items: center;
            color: #B4B2B2;
            font-size: 4rem;
            font-weight: bold;
            font-family: Tilt Warp Regular;
        }

        /* Color styling for 'fun' */
        .left span {
            color: #F8B500;
        }

        .left p, span{
            font-family: Tilt Warp Regular;
        }

        /* Right side with login form */
        .right {
            flex: 1;
            background-color: #F8B500;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
        }

        /* QuizZap logo styling */
        .logo {
            font-size: 3rem;
            font-weight: bold;
            color: #ffffff;
            margin-bottom: .5rem;
            margin-top: -10%;
        }

        /* Form styling */
        .form-container {
            background-color: #ffffff;
            padding: 2rem;
            border-radius: 25px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            width: 65%;
            max-width: 1000px;
            text-align: center;
        }

        .form-container input {
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

        .form-container .btn {
            width: 95%;
            padding: 10px;
            background-color: #F8B500;
            color: #fff;
            border: 2px solid #f8b500;
            border-radius: 10px;
            font-size: 16px;
            cursor: pointer;
            font-family: Tilt Warp Regular;
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
            font-family: Tilt Warp Regular;
        }

        .form-container a{
            text-decoration: none;
            font-family: Tilt Warp Regular;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="left">
            <p>Educational <span>fun.</span></p>
        </div>

        <div class="right">
            <div class="logo"><img src="img/logo4.png" width="360px" height="130px"></div>
            <div class="form-container">
                <form method="POST" action="login_process.php"><br>
                    <input type="text" id="account_number" name="account_number" placeholder="Account number" required><br>
                    <input type="password" id="password" name="password" placeholder="Password" required><br>

                    <center>
                    <input class="btn" type="submit" value="Login">
                    <p class="register-link">Don't have a account? <a style="color: #F8B500;" href="acctype.php">Create an account!</a></p></center><br>           
            </form>
            </div>
        </div>
    </div>
</body>
</html>
