<?php
session_start();
if (strpos($_SESSION['account_number'], 'T') !== 0) {
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

$subject_id = $_GET['subject_id'];

// Fetch the subject information based on the subject_id
$sql = "SELECT * FROM subjects WHERE subject_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $subject_id);
$stmt->execute();
$result = $stmt->get_result();
$subject = $result->fetch_assoc();
$stmt->close();

$conn->close();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Quiz Type</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Fredoka', sans-serif;
        }

        body {
            font-family: 'Fredoka', sans-serif;
            background-color: #ffffff;
            color: #333;
            transition: background-color 0.3s, color 0.3s;
            min-height: 100vh;
            overflow-x: hidden;
        }

        body.dark-mode {
            background-color: #1a1a1a;
            color: #e0e0e0;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
            width: 100%;
        }

        /* Header Styling */
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 1.5rem;
            background-color: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        body.dark-mode header {
            background-color: #2d2d2d;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }

        .logo img {
            height: clamp(40px, 6vw, 60px);
            width: auto;
        }

        .back-button {
            background-color: #f8b500;
            color: white;
            border: none;
            padding: 0.6rem 1.2rem;
            border-radius: 8px;
            font-size: clamp(0.9rem, 1.2vw, 1rem);
            cursor: pointer;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: background-color 0.3s;
            min-height: 44px;
            min-width: 44px;
            font-weight: 600;
        }

        .back-button:hover {
            background-color: #e5941f;
        }

        body.dark-mode .back-button {
            background-color: #f8b500;
        }

        /* Main Content */
        .main-content {
            padding: 2rem 1rem;
            max-width: 1000px;
            margin: 0 auto;
            width: 100%;
        }

        .page-title {
            text-align: center;
            color: #f8b500;
            font-size: clamp(2rem, 5vw, 3.5rem);
            font-weight: 600;
            margin-bottom: 1rem;
            line-height: 1.2;
        }

        body.dark-mode .page-title {
            color: #f8b500;
        }

        .page-divider {
            border: 1px solid #C8C8C8;
            width: 90%;
            margin: 1rem auto 2rem;
            max-width: 1000px;
        }

        body.dark-mode .page-divider {
            border-color: #444;
        }

        /* Quiz Type Grid - 2 COLUMNS, 4 ROWS LIKE ORIGINAL */
        .quiz-type-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            grid-template-rows: repeat(4, auto);
            gap: 1.5rem;
            padding: 1.5rem;
            margin: 0 auto;
            max-width: 800px;
        }

        /* Quiz Type Card - ALL CARDS SAME SIZE */
        .quiz-type-card {
            background-color: white;
            color: #f8b500;
            border: 2px solid #f8b500;
            border-radius: 15px;
            padding: 1.5rem;
            text-decoration: none;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            transition: all 0.3s ease;
            min-height: 160px;
            box-shadow: 0 6px 0 0 #BC8900;
            position: relative;
            overflow: hidden;
        }

        body.dark-mode .quiz-type-card {
            background-color: #2d2d2d;
            border-color: #f8b500;
            color: #f8b500;
        }

        .quiz-type-card:hover {
            background-color: #f8b500;
            color: white;
            transform: translateY(-5px);
            box-shadow: 0 8px 0 0 #BC8900;
        }

        body.dark-mode .quiz-type-card:hover {
            background-color: #f8b500;
            color: white;
        }

        .quiz-type-card:active {
            transform: translateY(2px);
            box-shadow: 0 4px 0 0 #BC8900;
        }

        .quiz-icon {
            font-size: clamp(1.8rem, 3vw, 2.2rem);
            margin-bottom: 0.8rem;
            color: inherit;
        }

        .quiz-name {
            font-size: clamp(1.2rem, 2vw, 1.5rem);
            font-weight: 600;
            line-height: 1.3;
        }

        /* All Zapped - Regular card, not special */
        .all-zapped-card {
            background-color: white;
            color: #f8b500;
            border: 2px solid #f8b500;
            /* No special styling - just a regular card */
        }

        .all-zapped-card:hover {
            background-color: #f8b500;
            color: white;
        }

        /* Subject Info */
        .subject-info {
            background-color: #fff9e6;
            border-radius: 12px;
            padding: 1.2rem;
            margin-bottom: 2rem;
            border-left: 5px solid #f8b500;
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
            max-width: 800px;
            margin: 0 auto 2rem;
        }

        body.dark-mode .subject-info {
            background-color: #333;
            border-left-color: #f8b500;
        }

        .subject-icon {
            font-size: 1.8rem;
            color: #f8b500;
            flex-shrink: 0;
        }

        .subject-text {
            flex: 1;
        }

        .subject-text h3 {
            font-size: 1.3rem;
            color: #333;
            margin-bottom: 0.3rem;
        }

        body.dark-mode .subject-text h3 {
            color: #e0e0e0;
        }

        .subject-text p {
            color: #666;
            font-size: 1rem;
        }

        body.dark-mode .subject-text p {
            color: #b0b0b0;
        }

        /* Dark Mode Toggle */
        .dark-mode-toggle {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background-color: #f8b500;
            color: white;
            border: none;
            border-radius: 50%;
            width: clamp(50px, 8vw, 60px);
            height: clamp(50px, 8vw, 60px);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: clamp(1.2rem, 2.5vw, 1.5rem);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            z-index: 999;
            transition: background-color 0.3s;
            min-height: 44px;
            min-width: 44px;
        }

        .dark-mode-toggle:hover {
            background-color: #e5941f;
            transform: scale(1.05);
        }

        body.dark-mode .dark-mode-toggle {
            background-color: #444;
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            header {
                padding: 0.8rem 1rem;
            }
            
            .main-content {
                padding: 1.5rem 0.5rem;
            }
            
            .quiz-type-grid {
                gap: 1.2rem;
                padding: 1rem;
            }
            
            .quiz-type-card {
                min-height: 140px;
                padding: 1.2rem;
            }
            
            .page-title {
                font-size: 2.5rem;
            }
        }

        @media (max-width: 576px) {
            /* Switch to 1 column on mobile */
            .quiz-type-grid {
                grid-template-columns: 1fr;
                grid-template-rows: auto;
                gap: 1rem;
                max-width: 400px;
            }
            
            .quiz-type-card {
                min-height: 130px;
                padding: 1rem;
            }
            
            .page-title {
                font-size: 2rem;
            }
            
            .subject-info {
                flex-direction: column;
                text-align: center;
                padding: 1rem;
                margin: 0 auto 1.5rem;
            }
            
            .subject-icon {
                margin-bottom: 0.5rem;
            }
            
            .dark-mode-toggle {
                bottom: 15px;
                right: 15px;
                width: 50px;
                height: 50px;
                font-size: 1.2rem;
            }
        }

        @media (max-width: 480px) {
            header {
                flex-direction: column;
                gap: 0.8rem;
                padding: 0.8rem;
            }
            
            .logo img {
                height: 35px;
            }
            
            .back-button {
                width: 100%;
                justify-content: center;
            }
            
            .main-content {
                padding: 1rem 0.5rem;
            }
            
            .quiz-type-card {
                min-height: 120px;
                padding: 1rem;
            }
            
            .quiz-name {
                font-size: 1.3rem;
            }
            
            .page-title {
                font-size: 1.8rem;
            }
        }

        @media (max-width: 375px) {
            .quiz-type-card {
                min-height: 110px;
                padding: 0.8rem;
            }
            
            .quiz-name {
                font-size: 1.2rem;
            }
            
            .quiz-icon {
                font-size: 1.6rem;
            }
            
            .page-title {
                font-size: 1.6rem;
            }
            
            .dark-mode-toggle {
                bottom: 10px;
                right: 10px;
                width: 45px;
                height: 45px;
            }
        }

        /* Accessibility improvements */
        button:focus-visible,
        a:focus-visible {
            outline: 2px solid #f8b500;
            outline-offset: 2px;
        }

        /* Ensure touch targets are large enough */
        .quiz-type-card,
        .back-button,
        .dark-mode-toggle {
            min-height: 44px;
            min-width: 44px;
        }

        /* Scrollbar styling */
        ::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        body.dark-mode ::-webkit-scrollbar-track {
            background: #2d2d2d;
        }

        ::-webkit-scrollbar-thumb {
            background: #f8b500;
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #e5941f;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="logo">
            <img src="img/logo1.png" alt="QuizZap Logo">
        </div>
        <a href="t_quizDash.php?subject_id=<?php echo $subject_id; ?>" class="back-button">
            <i class="fas fa-arrow-left"></i>
            <span>Back to Dashboard</span>
        </a>
    </header>

    <!-- Main Content -->
    <div class="container">
        <div class="main-content">
            <!-- Page Title -->
            <h1 class="page-title">Choose the Type of Quiz.</h1>
            <hr class="page-divider">
            
            <!-- Subject Info -->
            <?php if (isset($subject)): ?>
            <div class="subject-info">
                <div class="subject-icon">
                    <i class="fas fa-book-open"></i>
                </div>
                <div class="subject-text">
                    <h3>Subject: <?php echo htmlspecialchars($subject['subject_name']); ?></h3>
                    <p>Create a new quiz for this subject</p>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Quiz Type Grid - EXACTLY LIKE ORIGINAL: 2 columns, 4 rows -->
            <div class="quiz-type-grid">
                <!-- Row 1 -->
                <a href="t_multipleChoice.php?subject_id=<?php echo $subject_id; ?>" class="quiz-type-card">
                    <div class="quiz-icon">
                        <i class="fas fa-list-ul"></i>
                    </div>
                    <div class="quiz-name">Multiple Choice</div>
                </a>
                
                <a href="t_fill_in.php?subject_id=<?php echo $subject_id; ?>" class="quiz-type-card">
                    <div class="quiz-icon">
                        <i class="fas fa-edit"></i>
                    </div>
                    <div class="quiz-name">Fill in the Blanks</div>
                </a>
                
                <!-- Row 2 -->
                <a href="t_T_or_F.php?subject_id=<?php echo $subject_id; ?>" class="quiz-type-card">
                    <div class="quiz-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="quiz-name">True or False</div>
                </a>
                
                <a href="t_enum.php?subject_id=<?php echo $subject_id; ?>" class="quiz-type-card">
                    <div class="quiz-icon">
                        <i class="fas fa-list-ol"></i>
                    </div>
                    <div class="quiz-name">Enumeration</div>
                </a>
                
                <!-- Row 3 -->
                <a href="t_drag&drop.php?subject_id=<?php echo $subject_id; ?>" class="quiz-type-card">
                    <div class="quiz-icon">
                        <i class="fas fa-hand-pointer"></i>
                    </div>
                    <div class="quiz-name">Drag & Drop</div>
                </a>
                
                <a href="t_matching.php?subject_id=<?php echo $subject_id; ?>" class="quiz-type-card">
                    <div class="quiz-icon">
                        <i class="fas fa-exchange-alt"></i>
                    </div>
                    <div class="quiz-name">Matching Type</div>
                </a>
                
                <!-- Row 4 -->
                <a href="t_identification.php?subject_id=<?php echo $subject_id; ?>" class="quiz-type-card">
                    <div class="quiz-icon">
                        <i class="fas fa-question-circle"></i>
                    </div>
                    <div class="quiz-name">Identification</div>
                </a>
                
                <!-- All Zapped - Regular card in 2-column grid -->
                <a href="allZapped.php?subject_id=<?php echo $subject_id; ?>" class="quiz-type-card all-zapped-card">
                    <div class="quiz-icon">
                        <i class="fas fa-bolt"></i>
                    </div>
                    <div class="quiz-name">All Zapped</div>
                </a>
            </div>
        </div>
    </div>

    <!-- Dark Mode Toggle Button -->
    <button class="dark-mode-toggle" id="darkModeToggle">
        <i class="fas fa-moon"></i>
    </button>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const darkModeToggle = document.getElementById('darkModeToggle');
            const body = document.body;
            
            // Check for saved dark mode preference
            const isDarkMode = localStorage.getItem('darkMode') === 'true';
            
            // Apply dark mode if previously enabled
            if (isDarkMode) {
                body.classList.add('dark-mode');
                darkModeToggle.innerHTML = '<i class="fas fa-sun"></i>';
            }
            
            // Dark mode toggle functionality
            darkModeToggle.addEventListener('click', () => {
                body.classList.toggle('dark-mode');
                
                // Update button icon and save preference
                if (body.classList.contains('dark-mode')) {
                    darkModeToggle.innerHTML = '<i class="fas fa-sun"></i>';
                    localStorage.setItem('darkMode', 'true');
                } else {
                    darkModeToggle.innerHTML = '<i class="fas fa-moon"></i>';
                    localStorage.setItem('darkMode', 'false');
                }
            });
            
            // Handle responsive layout
            function handleResponsiveLayout() {
                const quizGrid = document.querySelector('.quiz-type-grid');
                
                if (window.innerWidth <= 576) {
                    // Mobile: 1 column
                    quizGrid.style.gridTemplateColumns = '1fr';
                } else {
                    // Tablet/Desktop: 2 columns
                    quizGrid.style.gridTemplateColumns = 'repeat(2, 1fr)';
                }
            }
            
            // Call on load and resize
            handleResponsiveLayout();
            window.addEventListener('resize', handleResponsiveLayout);
            
            // Add hover effect for touch devices
            const quizCards = document.querySelectorAll('.quiz-type-card');
            quizCards.forEach(card => {
                let touchTimer;
                
                card.addEventListener('touchstart', function(e) {
                    e.preventDefault();
                    this.classList.add('touch-active');
                    touchTimer = setTimeout(() => {
                        this.classList.remove('touch-active');
                    }, 300);
                });
                
                card.addEventListener('touchend', function(e) {
                    e.preventDefault();
                    clearTimeout(touchTimer);
                    this.classList.remove('touch-active');
                    
                    // Simulate click after touch
                    setTimeout(() => {
                        window.location.href = this.href;
                    }, 100);
                });
                
                card.addEventListener('touchmove', function() {
                    clearTimeout(touchTimer);
                    this.classList.remove('touch-active');
                });
            });
        });
    </script>
</body>
</html>