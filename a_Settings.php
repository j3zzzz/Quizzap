<?php
session_start();
if (!isset($_SESSION['account_number']) || strpos($_SESSION['account_number'], 'A') !== 0) {
    header("Location: login.php");
    exit();
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "rawrit";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$loggedInUser = $_SESSION['account_number'];

// Fetch admin's profile pic
$sql = "SELECT profile_pic FROM admins WHERE account_number = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $loggedInUser);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $profile_pic = $row['profile_pic'] ? $row['profile_pic'] : 'default-profile.jpg';
} else {
    $profile_pic = 'default-profile.jpg';
}

$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="other resources/fontawesome-free-6.5.2-web/css/all.min.css">
    <title>Admin Settings</title>
    <style>
        /* Include all the CSS from a_Home.php here */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Fredoka';
        }

        /* ... (copy all CSS styles from a_Home.php) ... */

        /* Additional styles for settings page */
        .settings-card {
            background-color: #ffffff;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.15);
            margin-bottom: 1rem;
        }

        .settings-card h2 {
            color: #f8b500;
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: bold;
            color: #555;
        }

        .form-group input, 
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-family: 'Fredoka';
            font-size: 1rem;
        }

        .form-group input:focus, 
        .form-group select:focus {
            outline: none;
            border-color: #f8b500;
        }

        .btn {
            background-color: #f8b500;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 5px;
            font-family: 'Fredoka';
            font-size: 1rem;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .btn:hover {
            background-color: #e5941f;
        }

        .tabs {
            display: flex;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid #ddd;
        }

        .tab {
            padding: 0.75rem 1.5rem;
            cursor: pointer;
            font-weight: bold;
            color: #777;
            border-bottom: 3px solid transparent;
        }

        .tab.active {
            color: #f8b500;
            border-bottom: 3px solid #f8b500;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .profile-pic-container {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .profile-pic-preview {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #f8b500;
            margin-right: 1.5rem;
        }

        .profile-pic-upload {
            display: flex;
            flex-direction: column;
        }

        .profile-pic-upload input[type="file"] {
            margin-bottom: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar (same as a_Home.php) -->
        <div class="sidebar" id="sidebar">
            <header>
                <button id="toggleSidebar" class="toggle-btn">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="logo">
                    <img src="img/logo4.png" width="200px" height="80px" class="logo-img">
                    <img src="img/logo 6.png" width="50px" height="50px" class="logo-icon" style="display: none; margin-top: 10%;">
                </div>
            </header>
            <hr style="border: 1px solid white;">
            <div class="menu">
                <a href="a_Home.php" title="Dashboard">
                    <i class="fa-solid fa-house"></i>
                    <span>Dashboard</span>
                </a>
                <a href="a_Students.php" title="Students">
                    <i class="fa-solid fa-graduation-cap"></i>
                    <span>Students</span>
                </a>
                <a href="a_Teachers.php" title="Teachers">
                    <i class="fa-solid fa-chalkboard-user"></i>
                    <span>Teachers</span>
                </a>
                <a href="a_Settings.php" class="active" title="Settings">
                    <i class="fa-solid fa-gear"></i>
                    <span>Settings</span>
                </a>
            </div>
        </div>

        <!-- Content Area -->
        <div class="content">
            <div class="content-header">
                <div>
                    <h1>Admin Settings</h1>
                    <p>Manage your account and system preferences</p>
                </div>
                <div class="actions">
                    <div class="profile" onclick="profileDropdown()">
                        <img src="uploads/profiles/<?php echo htmlspecialchars($profile_pic); ?>" alt="Profile Picture" class="profile-pic" onerror="this.src='uploads/profiles/default-profile.jpg'" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
                        <div id="dropdown" class="dropdown-content">
                            <button onclick="window.location.href='a_Profile.php'"><i class="fa-solid fa-user"></i> Profile</button> 
                            <form action="logout.php" method="POST">
                                <button><i class="fa-solid fa-right-from-bracket"></i> Logout</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tabs">
                <div class="tab active" onclick="openTab(event, 'profile')">Profile Settings</div>
                <div class="tab" onclick="openTab(event, 'security')">Security</div>
                <div class="tab" onclick="openTab(event, 'system')">System Settings</div>
            </div>

            <div id="profile" class="tab-content active">
                <div class="settings-card">
                    <h2>Profile Information</h2>
                    <form action="a_updateProfile.php" method="POST" enctype="multipart/form-data">
                        <div class="profile-pic-container">
                            <img src="uploads/profiles/<?php echo htmlspecialchars($profile_pic); ?>" alt="Profile Picture" class="profile-pic-preview" onerror="this.src='uploads/profiles/default-profile.jpg'">
                            <div class="profile-pic-upload">
                                <input type="file" name="profile_pic" id="profile_pic" accept="image/*">
                                <button type="button" class="btn" onclick="document.getElementById('profile_pic').click()">Change Photo</button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="fname">First Name</label>
                            <input type="text" id="fname" name="fname" value="<?php echo htmlspecialchars($_SESSION['fname']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="lname">Last Name</label>
                            <input type="text" id="lname" name="lname" value="<?php echo htmlspecialchars($_SESSION['lname']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($_SESSION['email']); ?>" required>
                        </div>
                        <button type="submit" class="btn">Save Changes</button>
                    </form>
                </div>
            </div>

            <div id="security" class="tab-content">
                <div class="settings-card">
                    <h2>Change Password</h2>
                    <form action="a_changePassword.php" method="POST">
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" id="current_password" name="current_password" required>
                        </div>
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password" required>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" required>
                        </div>
                        <button type="submit" class="btn">Update Password</button>
                    </form>
                </div>
            </div>

            <div id="system" class="tab-content">
                <div class="settings-card">
                    <h2>System Preferences</h2>
                    <form action="a_updateSystemSettings.php" method="POST">
                        <div class="form-group">
                            <label for="theme">Theme</label>
                            <select id="theme" name="theme">
                                <option value="light">Light</option>
                                <option value="dark">Dark</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="notifications">Email Notifications</label>
                            <select id="notifications" name="notifications">
                                <option value="enabled">Enabled</option>
                                <option value="disabled">Disabled</option>
                            </select>
                        </div>
                        <button type="submit" class="btn">Save Preferences</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Include all JavaScript from a_Home.php here
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.querySelector('.sidebar');
            const content = document.querySelector('.content');
            const toggleBtn = document.getElementById('toggleSidebar');

            const isSidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            
            if (isSidebarCollapsed) {
                sidebar.classList.add('collapsed');
                content.classList.add('expanded');
            }

            if (toggleBtn) {
                toggleBtn.addEventListener('click', function() {
                    sidebar.classList.toggle('collapsed');
                    content.classList.toggle('expanded');
                    localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
                });
            }

            // Preview profile picture when selected
            document.getElementById('profile_pic').addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(event) {
                        document.querySelector('.profile-pic-preview').src = event.target.result;
                    };
                    reader.readAsDataURL(file);
                }
            });
        });

        function profileDropdown() {
            document.getElementById("dropdown").classList.toggle("show");
        }

        function openTab(evt, tabName) {
            // Hide all tab content
            const tabContents = document.getElementsByClassName("tab-content");
            for (let i = 0; i < tabContents.length; i++) {
                tabContents[i].classList.remove("active");
            }

            // Remove active class from all tabs
            const tabs = document.getElementsByClassName("tab");
            for (let i = 0; i < tabs.length; i++) {
                tabs[i].classList.remove("active");
            }

            // Show the current tab and add active class
            document.getElementById(tabName).classList.add("active");
            evt.currentTarget.classList.add("active");
        }

        window.onclick = function(event) {
            if (!event.target.matches('.profile') && !event.target.matches('.profile-pic')) {
                var dropdowns = document.getElementsByClassName("dropdown-content");
                for (var i = 0; i < dropdowns.length; i++) {
                    var openDropdown = dropdowns[i];
                    if (openDropdown.classList.contains('show')) {
                        openDropdown.classList.remove('show');
                    }
                }
            }
        }
    </script>
</body>
</html>
<?php
$conn->close();
?>