<?php
session_start();

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "rawrit";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get user data
$account_number = $_SESSION['account_number'];
$fname = $_SESSION['fname'];

// Fetch teacher data
$sql = "SELECT fname, lname, account_number, profile_pic FROM teachers WHERE account_number = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $account_number);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $name = $row['fname'];
    $lname = $row['lname'];
    $profile_pic = $row['profile_pic'] ? $row['profile_pic'] : 'default-profile.jpg';
} else {
    $name = "Unknown";
    $lname = "Unknown";
    $profile_pic = 'default-profile.jpg';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_fname = $_POST['fname'];
    $new_lname = $_POST['lname'];
    
    // Handle profile picture upload
    if ($_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $target_dir = "uploads/profiles/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
        $new_filename = "teacher_" . $account_number . "_" . time() . "." . $file_extension;
        $target_file = $target_dir . $new_filename;
        
        // Check if image file is a actual image
        $check = getimagesize($_FILES['profile_pic']['tmp_name']);
        if ($check !== false) {
            if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $target_file)) {
                $profile_pic = $new_filename;
            }
        }
    }
    
    // Update database
    $update_sql = "UPDATE teachers SET fname = ?, lname = ?, profile_pic = ? WHERE account_number = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("ssss", $new_fname, $new_lname, $profile_pic, $account_number);
    $update_stmt->execute();
    
    // Update session variables
    $_SESSION['fname'] = $new_fname;
    $name = $new_fname;
    $lname = $new_lname;
    
    $update_stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Profile | RAWRIT</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary: #f8b500;
            --primary-light: #ffc740;
            --primary-dark: #e0a100;
            --accent: #FFF3D6;
            --text: #333;
            --light-bg: #fff9ee;
            --card-bg: #fff;
            --error: #e74c3c;
            --success: #2ecc71;
        }
        
                * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Fredoka;
        }

        body {
            background-color: var(--light-bg);
            color: var(--text);
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 5%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .logo img {
            height: 40px;
            transition: transform 0.3s;
        }

        nav {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        nav a {
            color: rgba(0, 0, 0, 0.8);
            text-decoration: none;
            font-weight: 500;
            position: relative;
            padding: 5px 0;
            transition: all 0.3s;
        }
        
        nav a:hover {
            color: #000;
            text-shadow: 0 0 5px rgba(255, 255, 255, 0.5);
        }

        nav a.active {
            color: #000;
            font-weight: 600;
            border-bottom: 2px solid #000;
        }

        .logout-btn {
            background-color: rgba(0, 0, 0, 0.1);
            color: #000;
            border: none;
            padding: 8px 15px;
            border-radius: 30px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .logout-btn:hover {
            background-color: rgba(0, 0, 0, 0.2);
        }

        
        main {
            padding: 40px 5%;
            min-height: calc(100vh - 70px);
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .profile-container {
            width: 100%;
            max-width: 600px;
            background: var(--card-bg);
            border-radius: 16px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            border: 1px solid rgba(248, 181, 0, 0.2);
        }

        .profile-header {
            height: 100px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 30px;
        }

        .profile-title {
            color: #000;
            font-size: 24px;
            font-weight: 600;
            text-shadow: 0 1px 2px rgba(255, 255, 255, 0.3);
        }

        .profile-content {
            padding: 30px;
        }
        
        .profile-preview {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
        }
        
        .profile-pic-container {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            overflow: hidden;
            border: 5px solid var(--accent);
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(248, 181, 0, 0.3);
        }
        
        .profile-pic {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .profile-info {
            text-align: center;
            width: 100%;
        }
        
        .account-number {
            font-size: 18px;
            color: var(--primary-dark);
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .full-name {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 20px;
        }
        
        .profile-form {
            display: grid;
            gap: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .form-label {
            font-size: 14px;
            color: var(--primary);
            font-weight: 600;
        }
        
        .form-input {
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s;
        }
        .form-input:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(248, 181, 0, 0.1);
        }

        .profile-pic-edit {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .profile-pic-preview {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            overflow: hidden;
            border: 5px solid var(--accent);
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 30px;
        }
        
        .preview-actions {
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }
        
        .btn {
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: #000;
            box-shadow: 0 4px 15px rgba(248, 181, 0, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(248, 181, 0, 0.4);
            background: linear-gradient(135deg, var(--primary-light) 0%, var(--primary) 100%);
        }
        
        .btn-secondary {
            background-color: #f1f1f1;
            color: var(--text);
            border: 1px solid #ddd;
        }

        .btn-secondary:hover {
            background-color: #e1e1e1;
        }
                
        .edit-btn {
            background-color: rgba(0, 0, 0, 0.1);
            color: #000;
            border: none;
            padding: 8px 15px;
            border-radius: 30px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .edit-btn:hover {
            background-color: rgba(0, 0, 0, 0.2);
        }
        
        .hidden {
            display: none;
        }
        
        @media (max-width: 768px) {
            header {
                padding: 15px 20px;
            }
            
            nav {
                gap: 12px;
            }
            
            .profile-content {
                padding: 20px;
            }
            
            .profile-header {
                padding: 0 20px;
            }
        }

        /* Add some subtle animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .profile-container {
            animation: fadeIn 0.5s ease-out;
        }

        /* Add some decorative elements */
        .profile-header::after {
            content: "";
            position: absolute;
            bottom: -10px;
            left: 0;
            width: 100%;
            height: 20px;
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 120" preserveAspectRatio="none"><path d="M1200 0L0 0 892.25 114.72 1200 0z" fill="%23fff9ee"></path></svg>');
            background-size: cover;
        }
    </style>
</head>
<body>
    <header>                   
        <div class="logo"> <img src="img/logo4.png" width="110px" height="80px" class="logo-img"></div>
        <nav>
            <a href="t_Home.php">Home</a>
            <a href="t_Students.php">Students</a>
            <a href="t_Subjects.php">Subjects</a>
            <a class="active" href="t_Profile.php">Profile</a>
            <form action="logout.php" method="POST">
                <button type="submit" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </button>
            </form>
        </nav>
    </header>
    <main>
        <div class="profile-container">
            <div class="profile-header">
                <h1 class="profile-title">Personal Information</h1>
            </div>
            <div class="profile-content">
                <!-- Preview Mode -->
                <div id="previewMode" class="profile-preview">
                    <div class="profile-pic-container">
                        <img src="uploads/profiles/<?php echo htmlspecialchars($profile_pic); ?>" alt="Profile Picture" class="profile-pic" onerror="this.src='uploads/profiles/default-profile.jpg'">
                    </div>
                    
                    <div class="profile-info">
                        <div class="account-number">Account #: <?php echo htmlspecialchars($account_number); ?></div>
                        <div class="full-name"><?php echo htmlspecialchars($name . ' ' . $lname); ?></div>
                    </div>
                    
                    <div class="preview-actions">
                        <button id="startEdit" class="btn btn-primary">
                            <i class="fas fa-edit"></i> Edit Profile
                        </button>
                    </div>
                </div>
                
                <!-- Edit Mode -->
                <form id="editMode" method="POST" enctype="multipart/form-data" class="profile-form hidden">
                    <div class="profile-pic-edit">
                        <div class="profile-pic-preview">
                            <img id="profilePicPreview" src="uploads/profiles/<?php echo htmlspecialchars($profile_pic); ?>" alt="Profile Picture" class="profile-pic" onerror="this.src='uploads/profiles/default-profile.jpg'">
                        </div>
                        <input type="file" id="profile_pic" name="profile_pic" accept="image/*" style="display: none;">
                        <button type="button" id="changeProfilePic" class="btn btn-secondary">
                            <i class="fas fa-camera"></i> Change Photo
                        </button>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Account Number</label>
                        <input type="text" class="form-input" value="<?php echo htmlspecialchars($account_number); ?>" disabled>
                    </div>

                    <div class="form-group">
                        <label for="fname" class="form-label">First Name</label>
                        <input type="text" id="fname" name="fname" class="form-input" value="<?php echo htmlspecialchars($name); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="lname" class="form-label">Last Name</label>
                        <input type="text" id="lname" name="lname" class="form-input" value="<?php echo htmlspecialchars($lname); ?>" required>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" id="cancelEdit" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const startEdit = document.getElementById('startEdit');
            const cancelEdit = document.getElementById('cancelEdit');
            const previewMode = document.getElementById('previewMode');
            const editMode = document.getElementById('editMode');
            const changeProfilePic = document.getElementById('changeProfilePic');
            const profilePicInput = document.getElementById('profile_pic');
            const profilePicPreview = document.getElementById('profilePicPreview');
            
            // Toggle between preview and edit modes
            function toggleEditMode() {
                previewMode.classList.toggle('hidden');
                editMode.classList.toggle('hidden');
            }
            
            // Event listeners
            startEdit.addEventListener('click', toggleEditMode);
            cancelEdit.addEventListener('click', toggleEditMode);
            
            // Profile picture change handler
            changeProfilePic.addEventListener('click', function() {
                profilePicInput.click();
            });
            
            profilePicInput.addEventListener('change', function(e) {
                if (e.target.files && e.target.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function(event) {
                        profilePicPreview.src = event.target.result;
                    };
                    reader.readAsDataURL(e.target.files[0]);
                }
            });
            
            // If form was submitted with errors, show edit mode
            if (window.location.search.includes('error')) {
                toggleEditMode();
            }
        });
    </script>
</body>
</html>