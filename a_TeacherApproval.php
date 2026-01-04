<?php
session_start();
if (!isset($_SESSION['account_number']) || strpos($_SESSION['account_number'], 'A') !== 0) {
    header("Location: admin_login.php");
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

// Handle approval/rejection actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action']) && isset($_POST['teacher_id'])) {
        $teacher_id = $_POST['teacher_id'];
        $admin_account = $_SESSION['account_number'];
        
        if ($_POST['action'] == 'approve') {
            $sql = "UPDATE teachers SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE account_number = ?";
            $message = "Teacher account approved successfully!";
        } elseif ($_POST['action'] == 'reject') {
            $sql = "UPDATE teachers SET status = 'rejected', approved_by = ?, approved_at = NOW() WHERE account_number = ?";
            $message = "Teacher account rejected!";
        }
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $admin_account, $teacher_id);
        if ($stmt->execute()) {
            $_SESSION['approval_message'] = $message;
            header("Location: a_TeacherApproval.php");
            exit();
        }
    }
}

// Fetch pending teachers
$pending_query = "SELECT * FROM teachers WHERE status = 'pending' ORDER BY teacher_id DESC";
$pending_result = $conn->query($pending_query);

// Fetch approved/rejected teachers (for history)
$history_query = "SELECT * FROM teachers WHERE status != 'pending' ORDER BY approved_at DESC";
$history_result = $conn->query($history_query);

// Fetch admin's profile pic
$loggedInUser = $_SESSION['account_number'];
$sql = "SELECT profile_pic FROM admins WHERE account_number = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $loggedInUser);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $profile_pic = $row['profile_pic'] ? $row['profile_pic'] : 'default-profile.png';
} else {
    $profile_pic = 'default-profile.png';
}

$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="other resources\fontawesome-free-6.5.2-web\css\all.min.css">
    <title>Teacher Account Approval</title>
    <style>
        /* Base styles from a_Home.php */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Fredoka';
        }

        body, html {
            font-family: 'Fredoka';
            height: 100%;
        }

        .container {
            font-family: 'Fredoka';
            display: flex;
            min-height: 100vh;
            flex-direction: column;
        }

        /* Sidebar styling - Exactly like a_Home.php */
        .sidebar {
            position: fixed;
            width: 250px;
            height: 100vh;
            background-color: #f8b500;
            color: #ffffff;
            padding: 2rem 1rem;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            transition: all 0.3s ease;
            z-index: 999;
        }

        .sidebar.collapsed {
            width: 90px;
            padding: 2rem 0.5rem;
        }

        .sidebar .logo {
            margin-bottom: 1rem;
            margin-left: 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .sidebar.collapsed .logo {
            margin-left: 0;
            justify-content: center;
        }

        .toggle-btn {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 5px;
            border-radius: 4px;
            transition: background 0.2s;
        }

        .toggle-btn:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .toggle-btn{
            align-items: center;
        }

        .sidebar .menu {
            margin-top: 15%;
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }

        .sidebar.collapsed .menu{
            align-items: center;
            margin-top: 45%;
        }

        .sidebar .menu a {
            color: #ffffff;
            text-decoration: none;
            padding: 1rem;
            display: flex;
            align-items: center;
            font-size: 1rem;
            border-radius: 5px;
            transition: background 0.3s;
            font-family: 'Fredoka';
            letter-spacing: 1px;
            margin-bottom: .5rem;
            width: 100%;
        }

        .sidebar.collapsed .menu a {
            justify-content: center;
            padding: 1rem 0;
            width: 90%;
        }

        .sidebar .menu a span {
            margin-left: 0.5rem;
            transition: opacity 0.2s;
            font-family: 'Fredoka';
            font-weight: bold;
            font-size: 20px;
        }

        .sidebar.collapsed .menu a span {
            opacity: 0;
            width: 0;
            height: 0;
            overflow: hidden;
            display: none;
        }

        .sidebar .menu a:hover,
        .sidebar .menu a.active {
            background-color: white;
            color: #f8b500;
        }

        .sidebar .menu a i {
            margin-right: 0.5rem;
            min-width: 20px;
            text-align: center;
            font-size: clamp(1rem, 1.2vw, 1.5rem);
        }

        .sidebar.collapsed .menu a i {
            margin-right: 0;
            font-size: 1.2rem;
        }

        .toggle-btn {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 5px;
            border-radius: 4px;
            transition: background 0.2s;
        }

        .toggle-btn:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .sidebar.collapsed .toggle-btn{
            margin: auto;
        }

        .sidebar.collapsed .logo-img {
            display: none;
        }

        .sidebar.collapsed .logo-icon {
            display: block !important;
        }

        .sidebar.collapsed .menu a {
            padding: 1rem 0;
            justify-content: center;
            width: 100%;
        }

        .sidebar.collapsed .menu a span {
            display: none;
        }

        .sidebar.collapsed .menu a i {
            margin-right: 0;
            font-size: 1.5rem;
        }

        .sidebar.collapsed hr {
            margin: 0.5rem auto;
            width: 50%;
        }
        
        /* Logout button in sidebar */
        .logout-container {
            margin-top: auto;
            padding-top: 1rem;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
        }

        .logout-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #e74c3c;
            color: white;
            border: none;
            padding: 0.8rem 1rem;
            border-radius: 5px;
            font-size: 1rem;
            cursor: pointer;
            font-family: 'Fredoka';
            letter-spacing: 1px;
            width: 100%;
            transition: background-color 0.3s;
            text-decoration: none;
        }

        .logout-btn:hover {
            background-color: #c0392b;
        }

        .logout-btn i {
            margin-right: 0.5rem;
            font-size: 1.2rem;
        }

        .sidebar.collapsed .logout-btn span {
            display: none;
        }

        .sidebar.collapsed .logout-btn i {
            margin-right: 0;
            font-size: 1.5rem;
        }

        /* Dashboard content area */
        .content {
            flex: 1;
            background-color: #ffffff;
            padding: 2rem;
            margin-left: 250px;
            transition: margin-left 0.3s ease;
        }

        .content.expanded {
            margin-left: 90px;
        }

        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .content-header h1 {
            font-size: 2rem;
            color: #333333;
            font-family: 'Fredoka';
            margin-bottom: 0.5rem;
        }

        .content-header p {
            color: #999;
            font-size: 1rem;
            margin-top: 0.5rem;
            font-family: 'Fredoka';
            font-weight: 500;
            width: 100%;
        }

        .content-header .actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .content-header .actions a {
            background-color: #F8B500;
            color: #ffffff;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            font-size: 1rem;
            text-decoration: none;
            cursor: pointer;
            font-family: 'Fredoka';
            white-space: nowrap;
        }

        .content-header .actions a:hover {
            background-color: #e5941f;
        }

        .content-header .actions .profile {
            width: 40px;
            height: 40px;
            background-color: #ffffff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #f5a623;
            font-size: 1.5rem;
            cursor: pointer;
        }

        .profile {
            position: relative;
            cursor: pointer;
        }

        .profile-pic {
            border: 2px solid #f8b500;
        }

        /* Mobile menu toggle */
        .menu-toggle {
            display: none;
            cursor: pointer;
            font-size: 1.5rem;
            color: #333;
            padding: 0.5rem;
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 1001;
            background: rgba(255,255,255,0.8);
            border-radius: 5px;
        }

        /* Responsive adjustments */
        @media (max-width: 1200px) {
            .sidebar {
                width: 220px;
            }
            .content {
                margin-left: 220px;
                width: calc(100% - 220px);
            }
        }

        @media (max-width: 768px) {
            .menu-toggle {
                display: block;
            }
            
            .sidebar {
                width: 250px;
                transform: translateX(-100%);
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .content {
                margin-left: 0;
                width: 100%;
                padding: 1rem;
                padding-top: 4rem;
            }
            
            .toggle-btn {
                font-size: 1.2rem;
            }

            .content-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .content-header .actions {
                width: 100%;
                justify-content: space-between;
                margin-top: 1rem;
            }
        }

        @media (max-width: 480px) {
            .content {
                padding: 0.5rem;
                padding-top: 4rem;
            }
            
            .content-header h1 {
                font-size: 1.5rem;
            }
            
            .content-header .actions a {
                padding: 0.5rem;
                font-size: 0.9rem;
            }
        }

        /* Specific styles for Teacher Approval page */
        .message {
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 5px;
            text-align: center;
        }

        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .section h2 {
            color: #f8b500;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f8b500;
        }

        .teacher-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .teacher-table th,
        .teacher-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .teacher-table th {
            background-color: #f8b500;
            color: white;
            font-weight: bold;
        }

        .teacher-table tr:hover {
            background-color: #f9f9f9;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #856404;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.9em;
        }

        .status-approved {
            background-color: #d4edda;
            color: #155724;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.9em;
        }

        .status-rejected {
            background-color: #f8d7da;
            color: #721c24;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.9em;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
        }

        .btn-approve {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-family: 'Fredoka';
            transition: background-color 0.3s;
        }

        .btn-approve:hover {
            background-color: #218838;
        }

        .btn-reject {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-family: 'Fredoka';
            transition: background-color 0.3s;
        }

        .btn-reject:hover {
            background-color: #c82333;
        }

        .btn-view {
            background-color: #17a2b8;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-family: 'Fredoka';
            text-decoration: none;
            display: inline-block;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        .empty-state i {
            font-size: 48px;
            color: #ddd;
            margin-bottom: 20px;
        }

        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #f8b500;
            text-decoration: none;
            font-weight: bold;
        }

        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar - Exactly like a_Home.php -->
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
                <a href="a_TeacherApproval.php" class="active" title="Teacher Approvals">
                    <i class="fa-solid fa-user-check"></i>
                    <span>Teacher Approvals</span>
                </a>
                <a href="a_Classes.php" title="Classes">
                    <i class="fa-solid fa-list"></i>
                    <span>Classes</span>
                </a>
                <a href="a_item-analysis.php" title="Item Analysis">
                    <i class="fa-solid fa-chart-bar"></i>
                    <span>Item Analysis</span>
                </a>
            </div>

            <div class="logout-container">
                <a href="admin_logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>

        <!-- Content Area -->
        <div class="content">
            <div class="content-header">
                <div>
                    <h1><i class="fas fa-user-check"></i> Teacher Account Approval</h1>
                    <p>Review and approve teacher registration requests</p>
                </div>
            </div>

            <?php if (isset($_SESSION['approval_message'])): ?>
                <div class="message success">
                    <?php echo $_SESSION['approval_message']; unset($_SESSION['approval_message']); ?>
                </div>
            <?php endif; ?>

            <!-- Pending Approvals Section -->
            <div class="section">
                <h2><i class="fas fa-clock"></i> Pending Approvals</h2>
                
                <?php if ($pending_result->num_rows > 0): ?>
                    <table class="teacher-table">
                        <thead>
                            <tr>
                                <th>Account Number</th>
                                <th>Name</th>
                                <th>School ID</th>
                                <th>Registration Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($teacher = $pending_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($teacher['account_number']); ?></td>
                                <td><?php echo htmlspecialchars($teacher['fname'] . ' ' . $teacher['lname']); ?></td>
                                <td><?php echo htmlspecialchars($teacher['school_id']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($teacher['created_at'] ?? 'now')); ?></td>
                                <td><span class="status-pending">Pending Review</span></td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="teacher_id" value="<?php echo $teacher['account_number']; ?>">
                                        <div class="action-buttons">
                                            <button type="submit" name="action" value="approve" class="btn-approve">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                            <button type="submit" name="action" value="reject" class="btn-reject">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-check-circle"></i>
                        <h3>No pending approvals</h3>
                        <p>All teacher accounts have been reviewed.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Approval History Section -->
            <div class="section">
                <h2><i class="fas fa-history"></i> Approval History</h2>
                
                <?php if ($history_result->num_rows > 0): ?>
                    <table class="teacher-table">
                        <thead>
                            <tr>
                                <th>Account Number</th>
                                <th>Name</th>
                                <th>School ID</th>
                                <th>Status</th>
                                <th>Approved/Rejected By</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($teacher = $history_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($teacher['account_number']); ?></td>
                                <td><?php echo htmlspecialchars($teacher['fname'] . ' ' . $teacher['lname']); ?></td>
                                <td><?php echo htmlspecialchars($teacher['school_id']); ?></td>
                                <td>
                                    <?php if ($teacher['status'] == 'approved'): ?>
                                        <span class="status-approved">Approved</span>
                                    <?php else: ?>
                                        <span class="status-rejected">Rejected</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($teacher['approved_by'] ?? 'N/A'); ?></td>
                                <td><?php echo $teacher['approved_at'] ? date('M d, Y H:i', strtotime($teacher['approved_at'])) : 'N/A'; ?></td>
                                <td>
                                    <?php if ($teacher['status'] == 'approved'): ?>
                                        <a href="a_Teachers.php?view=<?php echo $teacher['account_number']; ?>" class="btn-view">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-history"></i>
                        <h3>No approval history</h3>
                        <p>No teacher accounts have been approved or rejected yet.</p>
                    </div>
                <?php endif; ?>
            </div>

            <a href="a_Home.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <!-- JavaScript for sidebar toggle - Exactly like a_Home.php -->
    <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.querySelector('.sidebar');
        const content = document.querySelector('.content');
        const toggleBtn = document.getElementById('toggleSidebar');

        // Check if sidebar state is saved in localStorage
        const isSidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        
        // Set initial state based on localStorage
        if (isSidebarCollapsed) {
            sidebar.classList.add('collapsed');
            content.classList.add('expanded');
        }

        // Toggle sidebar when button is clicked
        if (toggleBtn) {
            toggleBtn.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                content.classList.toggle('expanded');
                
                // Save state to localStorage
                localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
            });
        }
    });

    function profileDropdown() {
        document.getElementById("dropdown").classList.toggle("show");
    }

    // Close the dropdown if clicked outside
    window.onclick = function(event) {
        if (!event.target.matches('.profile') && !event.target.matches('.profile-pic') && !event.target.matches('.dropdwn-btn')) {
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