<?php
session_start();
if (strpos($_SESSION['account_number'], 'S') !== 0) {
    header("Location: login.php");
    exit();
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "rawrit";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$account_number = $_SESSION['account_number'];
$fname = $_SESSION['fname'];

// Initialize variables
$name = "Unknown";
$lname = "Unknown";
$glevel = "Unknown";
$strand = "Unknown";
$section = "Unknown";
$profile_pic = 'default-profile.jpg';

// Fetch student data
$sql = "SELECT fname, lname, account_number, glevel, strand, section, profile_pic FROM students WHERE account_number = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $account_number);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $name = $row['fname'];
    $lname = $row['lname'];
    $glevel = $row['glevel'];
    $strand = $row['strand'];
    $section = $row['section'];
    $profile_pic = $row['profile_pic'] ? $row['profile_pic'] : 'default-profile.jpg';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_fname = $_POST['fname'];
    $new_lname = $_POST['lname'];
    // Note: glevel, strand, and section are no longer editable, so we don't update them
    
    // Handle profile picture upload
    if ($_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $target_dir = "uploads/profiles/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
        $new_filename = "student_" . $account_number . "_" . time() . "." . $file_extension;
        $target_file = $target_dir . $new_filename;
        
        $check = getimagesize($_FILES['profile_pic']['tmp_name']);
        if ($check !== false) {
            if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $target_file)) {
                $profile_pic = $new_filename;
            }
        }
    }
    
    // Update database - only update editable fields
    $update_sql = "UPDATE students SET fname = ?, lname = ?, profile_pic = ? WHERE account_number = ?";
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
    <title>Student Profile | RAWRIT</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.12);
            --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Fredoka', sans-serif;
        }

        body {
            background-color: var(--light-bg);
            color: var(--text);
            line-height: 1.6;
            transition: background-color 0.3s, color 0.3s;
        }

        /* Dark Mode Styles */
        body.dark-mode {
            background-color: #1a1a1a;
            color: #e0e0e0;
        }

        body.dark-mode header {
            background: linear-gradient(135deg, #333 0%, #444 100%);
        }

        body.dark-mode .profile-container {
            background-color: #2d2d2d;
            color: #e0e0e0;
        }

        body.dark-mode .form-input {
            background-color: #333;
            color: #e0e0e0;
            border-color: #444;
        }

        body.dark-mode .form-input:focus {
            border-color: var(--primary);
            background-color: #3a3a3a;
        }

        body.dark-mode .form-input:disabled {
            background-color: #444;
            color: #b0b0b0;
        }

        body.dark-mode .btn-secondary {
            background-color: #444;
            color: #e0e0e0;
            border-color: #555;
        }

        body.dark-mode .btn-secondary:hover {
            background-color: #555;
        }

        body.dark-mode .detail-group {
            background-color: #333;
        }

        body.dark-mode .detail-value {
            color: #e0e0e0;
        }

        body.dark-mode nav a {
            color: rgba(255, 255, 255, 0.8);
        }

        body.dark-mode nav a:hover,
        body.dark-mode nav a.active {
            color: #fff;
        }

        body.dark-mode nav a::after {
            background-color: #fff;
        }

        body.dark-mode .logout-btn {
            background-color: rgba(255, 255, 255, 0.1);
            color: #fff;
        }

        body.dark-mode .logout-btn:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }

        /* Scrollbar Styles */
        ::-webkit-scrollbar {
          width: 10px;
          height: 10px;
        }

        /* Track */
        ::-webkit-scrollbar-track {
          box-shadow: inset 0 0 5px grey; 
          border-radius: 10px;
        }
         
        /* Handle */
        ::-webkit-scrollbar-thumb {
          background: #CF5300; 
          border-radius: 10px;
        }

        /* Handle on hover */
        ::-webkit-scrollbar-thumb:hover {
          background: #A34404; 
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 5%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            box-shadow: var(--shadow-md);
            position: sticky;
            top: 0;
            z-index: 100;
            transition: var(--transition);
        }
        
        .logo img {
            height: 40px;
            transition: transform 0.3s;
        }

        .logo img:hover {
            transform: scale(1.05);
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
            transition: var(--transition);
        }
        
        nav a::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background-color: #000;
            transition: var(--transition);
        }
        
        nav a:hover {
            color: #000;
        }
        
        nav a:hover::after {
            width: 100%;
        }

        nav a.active {
            color: #000;
            font-weight: 600;
        }
        
        nav a.active::after {
            width: 100%;
        }

        .logout-btn, .edit-btn {
            background-color: rgba(0, 0, 0, 0.1);
            color: #000;
            border: none;
            padding: 8px 15px;
            border-radius: 30px;
            cursor: pointer;
            font-weight: 500;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .logout-btn:hover, .edit-btn:hover {
            background-color: rgba(0, 0, 0, 0.2);
            transform: translateY(-2px);
        }
        
        main {
            padding: 40px 5%;
            min-height: calc(100vh - 70px);
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .profile-container {
            width: 60%;
            max-width: 900px;
            background: var(--card-bg);
            border-radius: 16px;
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            border: 1px solid rgba(248, 181, 0, 0.2);
            transition: var(--transition);
        }
        
        .profile-container:hover {
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
            transform: translateY(-5px);
        }

        .profile-header {
            height: 80px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 40px;
            position: relative;
            overflow: hidden;
        }
        
        .profile-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.2) 0%, rgba(255,255,255,0) 70%);
            transform: rotate(30deg);
        }

        .profile-title {
            color: #000;
            font-size: 28px;
            font-weight: 600;
            position: relative;
            z-index: 1;
        }
        
        .profile-subtitle {
            font-size: 14px;
            color: rgba(0, 0, 0, 0.7);
            margin-top: 5px;
            font-weight: 400;
        }

        .profile-content {
            padding: 40px;
        }
        
        .profile-preview {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 25px;
        }
        
        .profile-pic-container {
            width: 180px;
            height: 180px;
            border-radius: 50%;
            overflow: hidden;
            border: 6px solid var(--accent);
            margin-bottom: 20px;
            box-shadow: var(--shadow-lg);
            transition: var(--transition);
            position: relative;
        }
        
        .profile-pic-container:hover {
            transform: scale(1.05);
        }
        
        .profile-pic {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: var(--transition);
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
            display: inline-block;
            padding: 5px 15px;
            background-color: var(--accent);
            border-radius: 20px;
        }
        
        .full-name {
            font-size: 28px;
            font-weight: 600;
            margin: 15px 0;
            position: relative;
            display: inline-block;
        }

        .student-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
            gap: 20px;
            width: 100%;
            margin-top: 30px;
        }
        
        .detail-group {
            background-color: var(--accent);
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            transition: var(--transition);
            box-shadow: var(--shadow-sm);
        }
        
        .detail-group:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-md);
        }
        
        .detail-label {
            font-size: 14px;
            color: var(--primary-dark);
            font-weight: 600;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .detail-value {
            font-size: 18px;
            color: var(--text);
            font-weight: 500;
        }
        
        .profile-form {
            display: grid;
            gap: 25px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .form-label {
            font-size: 15px;
            color: var(--primary);
            font-weight: 600;
        }
        
        .form-input {
            padding: 14px 18px;
            border: 1px solid #ddd;
            border-radius: 10px;
            font-size: 16px;
            transition: var(--transition);
            background-color: #f9f9f9;
        }
        
        .form-input:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(248, 181, 0, 0.1);
            background-color: #fff;
        }
        
        .form-input:disabled {
            background-color: #e9ecef;
            opacity: 1;
            cursor: not-allowed;
        }

        .profile-pic-edit {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .profile-pic-preview {
            width: 180px;
            height: 180px;
            border-radius: 50%;
            overflow: hidden;
            border: 6px solid var(--accent);
            box-shadow: var(--shadow-md);
            transition: var(--transition);
        }
        
        .profile-pic-preview:hover {
            transform: scale(1.05);
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 20px;
            margin-top: 40px;
        }
        
        .preview-actions {
            display: flex;
            justify-content: center;
            margin-top: 30px;
        }
        
        .btn {
            padding: 14px 30px;
            border-radius: 10px;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            border: none;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 16px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: #000;
            box-shadow: var(--shadow-md);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(248, 181, 0, 0.4);
            background: linear-gradient(135deg, var(--primary-light) 0%, var(--primary) 100%);
        }
        
        .btn-secondary {
            background-color: #f1f1f1;
            color: var(--text);
            border: 1px solid #ddd;
        }

        .btn-secondary:hover {
            background-color: #e1e1e1;
            transform: translateY(-3px);
            box-shadow: var(--shadow-sm);
        }
        
        .hidden {
            display: none;
        }
        
        /* Success message */
        .success-message {
            position: fixed;
            top: 100px;
            right: 30px;
            background-color: var(--success);
            color: white;
            padding: 15px 25px;
            border-radius: 8px;
            box-shadow: var(--shadow-lg);
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 1000;
            transform: translateX(150%);
            transition: transform 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }
        
        .success-message.show {
            transform: translateX(0);
        }
        
        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }

        .profile-container {
            animation: fadeIn 0.6s ease-out;
        }
        
        .profile-pic-container {
            animation: float 4s ease-in-out infinite;
        }
        
        /* Responsive styles */
        @media (max-width: 768px) {
            header {
                padding: 15px 20px;
            }
            
            nav {
                gap: 12px;
            }
            
            .profile-content {
                padding: 25px;
            }
            
            .profile-header {
                padding: 0 25px;
                height: 100px;
            }
            
            .profile-title {
                font-size: 24px;
            }
            
            .profile-pic-container, .profile-pic-preview {
                width: 150px;
                height: 150px;
            }
            
            .full-name {
                font-size: 24px;
            }
            
            .student-details {
                grid-template-columns: 1fr;
            }
            
            .btn {
                padding: 12px 20px;
                font-size: 14px;
            }
        }
        
        @media (max-width: 480px) {
            .form-actions {
                flex-direction: column;
                gap: 10px;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .profile-header {
                flex-direction: column;
                justify-content: center;
                text-align: center;
                padding: 20px;
                height: auto;
            }
        }
    </style>
</head>
<body>
    <header>                   
        <div class="logo">
            <img src="img/logo4.png" width="110px" height="80px" class="logo-img" alt="RAWRIT Logo">
        </div>
        <nav>
            <a href="s_Home.php">Home</a>
            <a href="s_Classes.php">Classes</a>
            <a href="studQuizzes.php">Quizzes</a>
            <a class="active" href="s_Profile.php">Profile</a>
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
                <div>
                    <h1 class="profile-title">Student Profile</h1>
                </div>
            </div>
            <div class="profile-content">
                <!-- Preview Mode -->
                <div id="previewMode" class="profile-preview">
                    <div class="profile-pic-container">
                        <img src="uploads/profiles/<?php echo htmlspecialchars($profile_pic); ?>" alt="Profile Picture" class="profile-pic" onerror="this.src='uploads/profiles/default-profile.jpg'">
                    </div>
                    
                    <div class="profile-info">
                        <div class="account-number"><?php echo htmlspecialchars($account_number); ?></div>
                        <h2 class="full-name"><?php echo htmlspecialchars($name . ' ' . $lname); ?></h2>
                        
                        <div class="student-details">
                            <div class="detail-group">
                                <div class="detail-label">Grade Level</div>
                                <div class="detail-value"><?php echo htmlspecialchars($glevel); ?></div>
                            </div>
                            <div class="detail-group">
                                <div class="detail-label">Strand</div>
                                <div class="detail-value"><?php echo htmlspecialchars($strand); ?></div>
                            </div>
                            <div class="detail-group">
                                <div class="detail-label">Section</div>
                                <div class="detail-value"><?php echo htmlspecialchars($section); ?></div>
                            </div>
                        </div>
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
                    
                    <div class="form-group">
                        <label for="glevel" class="form-label">Grade Level</label>
                        <input type="text" id="glevel" name="glevel" class="form-input" value="<?php echo htmlspecialchars($glevel); ?>" disabled>
                    </div>
                    
                    <div class="form-group">
                        <label for="strand" class="form-label">Strand</label>
                        <input type="text" id="strand" name="strand" class="form-input" value="<?php echo htmlspecialchars($strand); ?>" disabled>
                    </div>

                    <div class="form-group">
                        <label for="section" class="form-label">Section</label>
                        <input type="text" id="section" name="section" class="form-input" value="<?php echo htmlspecialchars($section); ?>" disabled>
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
        
        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
        <div id="successMessage" class="success-message">
            <i class="fas fa-check-circle"></i>
            <span>Profile updated successfully!</span>
        </div>
        <?php endif; ?>
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
            const successMessage = document.getElementById('successMessage');
            
            // Dark Mode Functionality - Auto apply based on localStorage
            // Check for saved dark mode preference
            const isDarkMode = localStorage.getItem('darkMode') === 'true';

            // Apply dark mode on page load if enabled
            if (isDarkMode) {
                document.body.classList.add('dark-mode');
            }
            
            // Toggle between preview and edit modes
            function toggleEditMode() {
                previewMode.classList.toggle('hidden');
                editMode.classList.toggle('hidden');
                
                // Smooth scroll to top when editing
                if (!editMode.classList.contains('hidden')) {
                    window.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                }
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
            
            // Show success message if form was submitted
            if (successMessage) {
                setTimeout(() => {
                    successMessage.classList.add('show');
                    
                    // Hide after 5 seconds
                    setTimeout(() => {
                        successMessage.classList.remove('show');
                    }, 5000);
                }, 300);
            }
            
            // If form was submitted with errors, show edit mode
            if (window.location.search.includes('error')) {
                toggleEditMode();
            }
            
            // Add hover effect to profile picture in preview mode
            const profilePicContainer = document.querySelector('.profile-pic-container');
            if (profilePicContainer) {
                profilePicContainer.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.05) rotate(5deg)';
                });
                
                profilePicContainer.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1) rotate(0)';
                });
            }
        });
    </script>
</body>
</html>