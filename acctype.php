<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RawrIT-Choose Account Type</title>
</head>
<style type="text/css">
    body{
        background-color: white;
        margin: 0;
    }

    header .logo {
        font-size: 24px;
        font-weight: bold;
        margin-left: 2%;
        margin-top: .5%;
    }

    h1{
        font-size: 70px; 
        margin-top: 5%;
        text-align:center; 
        color: #F8B500;
        font-family: Tilt Warp Regular;
    }

    .cards {
        display: flex;
        justify-content: center;
        gap: 30px;
        margin-top: 2%;
    }

    .card {
        background-color: white;
        padding: 30px;
        border-radius: 20px;
        border: 1px solid #D9D9D9;
        color: #FF6F26;
        width: 300px;
        height: 200px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        box-shadow: 0 0 6px rgba(0, 0, 0, 0.4);
        cursor: pointer;
        text-decoration: none;
        color: #FF6F26;
    }

    .card .zoom {
        transition: transform .2s; /* Animation */
        width: 350px;
        height: 250px;
        margin: 0 auto;
    }

    .card .zoom:hover {
        transform: scale(1.1); /* (150% zoom - Note: if the zoom is too large, it will go outside of the viewport) */
    }

    .card img {
        width: 150px;
        height: 150px;
        margin-bottom: 10px;
    }

    .card span{
        color: #F8B500;
        font-family: Tilt Warp Regular;
    }
</style>
<body>

    <header>
        <div class="logo"><img src="img/logo1.png" width="120px" height="50px"></div>
    </header>
    <div class="container">
        <h1>Choose your account type.</h1>
        <div class="cards">
            <div class="zoom">
            <a href="t_Signup.php" class="card">
                <img src="img/prof.png" alt="Professor">
                <span>Teacher</span>
            </a></div>
            <div class="zoom">
            <a href="s_Signup.php" class="card">
                <img src="img/stud.png" alt="Student">
                <span>Student</span>
            </a>
            </div>
        </div>
    </div>
</body>
</html>