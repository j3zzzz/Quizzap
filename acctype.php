<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RawrIT-Choose Account Type</title>
    <style type="text/css">
        body {
            background-color: white;
            margin: 0;
            font-family: Fredoka;
        }

        header .logo {
            font-size: 24px;
            font-weight: bold;
            margin-left: 2%;
            margin-top: .5%;
        }

        header .logo img {
            width: 120px;
            height: 50px;
        }

        h1 {
            font-size: 40px; 
            margin-top: 5%;
            text-align: center; 
            color: #F8B500;
            font-family: Fredoka;
        }

        .container {
            padding: 0 20px;
        }

        .cards {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 30px;
            margin-top: 5%;
            padding-bottom: 30px;
        }

        .card {
            background-color: white;
            padding: 30px;
            border-radius: 20px;
            border: 1px solid #D9D9D9;
            color: #FF6F26;
            width: 100%;
            max-width: 300px;
            height: 200px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            box-shadow: 0 0 6px rgba(0, 0, 0, 0.4);
            cursor: pointer;
            text-decoration: none;
            color: #FF6F26;
            transition: transform .2s;
        }

        .card:hover {
            transform: scale(1.05);
        }

        .card img {
            width: 150px;
            height: 150px;
            margin-bottom: 10px;
        }

        .card span {
            color: #F8B500;
            font-family: Fredoka;
            font-size: 24px;
        }

        /* Media queries for responsiveness */
        @media (min-width: 768px) {
            h1 {
                font-size: 60px;
            }
            
            .cards {
                flex-direction: row;
                justify-content: center;
                margin-top: 3%;
            }
            
            .card {
                width: 300px;
            }
        }

        @media (min-width: 992px) {
            h1 {
                font-size: 70px;
                margin-top: 3%;
            }
            
            .card:hover {
                transform: scale(1.1);
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="logo"><img src="img/logo1.png" width="120px" height="50px"></div>
    </header>
    <div class="container">
        <h1>Choose your account type.</h1>
        <div class="cards">
            <a href="t_Signup.php" class="card">
                <img src="img/prof.png" alt="Professor">
                <span>Teacher</span>
            </a>
            <a href="s_Signup.php" class="card">
                <img src="img/stud.png" alt="Student">
                <span>Student</span>
            </a>
        </div>
    </div>
</body>
</html>