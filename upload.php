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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Check if the user is logged in
    if (!isset($_SESSION['account_number'])) {
        header("Location: login.php");
        exit();
    }

    $loggedInUser = $_SESSION['account_number']; // Get logged-in user's account number

    $target_dir = "uploads/";
    $target_file = $target_dir . basename($_FILES["profile_pic"]["name"]);
    $uploadOk = 1;
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

    // Check if image file is an actual image
    $check = getimagesize($_FILES["profile_pic"]["tmp_name"]);
    if ($check === false) {
        $_SESSION['not_an_image'] = "File is not an image";
        $uploadOk = 0;
    }

    // Check if file already exists
    if (file_exists($target_file)) {
        $_SESSION['image_exists'] = "Sorry, file already exists.";
        $uploadOk = 0;
    }

    // Allow certain file formats
    if (!in_array($imageFileType, ["jpg", "png", "jpeg", "gif"])) {
        $_SESSION['file_type'] = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
        $uploadOk = 0;
    }

    // Upload file if validation passed
    if ($uploadOk && move_uploaded_file($_FILES["profile_pic"]["tmp_name"], $target_file)) {
        // Determine table based on account number prefix
        $table = (strpos($loggedInUser, 'S') === 0) ? 'students' : 'teachers';
        
        // Update the profile picture in the correct table
        $sql = "UPDATE $table SET profile_pic = '$target_file' WHERE account_number = '$loggedInUser'";
        if ($conn->query($sql) === TRUE) {
            $_SESSION['update'] = "Profile Picture updated successfully!";
        } else {
            $_SESSION['update'] = "Error updating your profile picture." . $sql . "<br>" . $conn->error;
        }
    } else {
        $_SESSION['update'] = "Sorry, try uploading again.";
    }

    if (strpos($loggedInUser, 'S') === 0){
        header("Location: s_Profile.php");
    } else {
        header("Location: t_Profile.php");
    }
    exit;
}

$conn->close();
?>
