<?php
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$conn = mysqli_connect("localhost","root","","rawrit");
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

$quiz_id = $_GET['quiz_id'];
$subject_id = isset($_GET['subject_id']) ? $_GET['subject_id'] : null;

if (empty($quiz_id)) {
  header("Location: login.php");
}

// If subject_id is not passed via the URL, fetch it from the database
if (!$subject_id) {
    $subject_sql = "SELECT subject_id FROM quizzes WHERE quiz_id = ?";
    $subject_stmt = $conn->prepare($subject_sql);
    $subject_stmt->bind_param("i", $quiz_id);
    $subject_stmt->execute();
    $subject_result = $subject_stmt->get_result();
    
    if ($subject_result->num_rows > 0) {
        $subject_row = $subject_result->fetch_assoc();
        $subject_id = $subject_row['subject_id'];
    }
    $subject_stmt->close();
}

//to fetch the number of students who answered correct/wrong
$correctWrongCNT = "SELECT q.title, qs.question_id, qs.question_text,
    COUNT(DISTINCT CASE WHEN sa.is_correct = 1 THEN sa.student_id END) AS correct_count,
    COUNT(DISTINCT CASE WHEN sa.is_correct = 0 THEN sa.student_id END) AS wrong_count
FROM student_answers sa
JOIN questions qs ON sa.question_id = qs.question_id
JOIN quizzes q ON qs.quiz_id = q.quiz_id
WHERE sa.quiz_id = ?
GROUP BY qs.question_id, q.title, qs.question_text";

$stmt = $conn->prepare($correctWrongCNT);

if ($stmt === false) {
    die("Error preparing the statement: " . $conn->error);
}

$stmt->bind_param("i", $quiz_id);
$stmt->execute();
$result = $stmt->get_result();

$analysis_data = [];
$quiz_title = '';

if ($result->num_rows > 0) {
  while ($row = $result->fetch_assoc()) {
    if (empty($quiz_title)) {
        $quiz_title = $row['title'];
    }
    
    // Get student details for correct answers - using account_number instead of student_number
    $correct_students_sql = "SELECT DISTINCT s.account_number, s.fname, s.lname 
                            FROM student_answers sa
                            JOIN students s ON sa.student_id = s.student_id
                            WHERE sa.quiz_id = ? AND sa.question_id = ? AND sa.is_correct = 1";
    $correct_stmt = $conn->prepare($correct_students_sql);
    $correct_stmt->bind_param("ii", $quiz_id, $row['question_id']);
    $correct_stmt->execute();
    $correct_students = $correct_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $correct_stmt->close();
    
    // Get student details for wrong answers - using account_number instead of student_number
    $wrong_students_sql = "SELECT DISTINCT s.account_number, s.fname, s.lname 
                          FROM student_answers sa
                          JOIN students s ON sa.student_id = s.student_id
                          WHERE sa.quiz_id = ? AND sa.question_id = ? AND sa.is_correct = 0";
    $wrong_stmt = $conn->prepare($wrong_students_sql);
    $wrong_stmt->bind_param("ii", $quiz_id, $row['question_id']);
    $wrong_stmt->execute();
    $wrong_students = $wrong_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $wrong_stmt->close();
    
    // Calculate percentage correct
    $total_answers = $row['correct_count'] + $row['wrong_count'];
    $percentage_correct = ($total_answers > 0) ? round(($row['correct_count'] / $total_answers) * 100, 2) : 0;
    
    $row['correct_students'] = $correct_students;
    $row['wrong_students'] = $wrong_students;
    $row['percentage_correct'] = $percentage_correct;
    
    $analysis_data[] = $row;
  }
}

$stmt->close(); 

// Fetch the quizzes related to the subject
$sql = "SELECT * FROM quizzes WHERE subject_id = ? ORDER BY quiz_id DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $subject_id);
$stmt->execute();
$quiz_result = $stmt->get_result();

$conn->close();

?>

<!DOCTYPE html>
  <head>
    <title>Quiz Item Analysis</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" type="text/css" href="other resources/fontawesome-free-6.5.2-web/css/all.min.css">
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <script type="text/javascript">
      google.charts.load('current', {'packages':['corechart']});
      google.charts.setOnLoadCallback(drawCharts);
      
      // Store chart data globally for resize handling
      window.chartData = {};
      window.chartInstances = {};
      window.chartOptions = {};

      function drawCharts() {
        <?php if (!empty($analysis_data)) { 
            foreach ($analysis_data as $index => $data) { ?>
            
            // Create data table
            window.chartData[<?php echo $index; ?>] = google.visualization.arrayToDataTable([
              ['Answer Type', 'Count'],
              ['Correct Answers', <?php echo (int)$data['correct_count']; ?>],
              ['Incorrect Answers', <?php echo (int)$data['wrong_count']; ?>]
            ]);

            // Create options
            window.chartOptions[<?php echo $index; ?>] = {
              title: 'Question <?php echo ($index + 1);?>: <?php echo addslashes($data['question_text']); ?>',
              titleTextStyle: {
                fontSize: 18,
                bold: false,
              },
              fontName: 'Fredoka',
              colors: ['#F8B500', '#f74400'],
              width: '100%',
              height: '100%',
              chartArea: {
                width: '85%',
                height: '70%',
                top: '15%'
              },
              animation: {
                startup: true,
                duration: 1000,
                easing: 'out'
              },
              legend: {
                position: 'top',
                textStyle: {
                  fontSize: 14
                }
              }
            };

            // Apply responsive adjustments
            adjustChartOptions(<?php echo $index; ?>);
            adjustChartHeight('piechart<?php echo $index; ?>');

            // Draw chart
            var container = document.getElementById('piechart<?php echo $index; ?>');
            if (container) {
              window.chartInstances[<?php echo $index; ?>] = new google.visualization.PieChart(container);
              window.chartInstances[<?php echo $index; ?>].draw(window.chartData[<?php echo $index; ?>], window.chartOptions[<?php echo $index; ?>]);
            }
          <?php }
          } ?>
      }
      
      function adjustChartHeight(chartId) {
        const container = document.getElementById(chartId);
        if (!container) return;
        
        const width = window.innerWidth;
        
        if (width <= 375) {
          container.style.height = '280px';
        } else if (width <= 480) {
          container.style.height = '320px';
        } else if (width <= 576) {
          container.style.height = '350px';
        } else if (width <= 768) {
          container.style.height = '380px';
        } else {
          container.style.height = '450px';
        }
      }

      function adjustChartOptions(chartIndex) {
        const options = window.chartOptions[chartIndex];
        const width = window.innerWidth;
        
        if (!options) return;
        
        if (width <= 768) {
          options.chartArea.width = '90%';
          options.chartArea.height = '75%';
          options.chartArea.top = '18%';
          options.legend.textStyle.fontSize = 12;
          options.titleTextStyle.fontSize = 16;
        }

        if (width <= 576) {
          options.chartArea.width = '92%';
          options.chartArea.height = '78%';
          options.chartArea.top = '20%';
          options.legend.textStyle.fontSize = 11;
          options.titleTextStyle.fontSize = 14;
        }

        if (width <= 480) {
          options.chartArea.width = '94%';
          options.chartArea.height = '80%';
          options.chartArea.top = '22%';
          options.legend.position = 'top';
          options.titleTextStyle.fontSize = 13;
        }

        if (width <= 375) {
          options.chartArea.width = '96%';
          options.chartArea.height = '82%';
          options.chartArea.top = '25%';
          options.legend.textStyle.fontSize = 10;
          options.titleTextStyle.fontSize = 12;
        }
      }
      
      function toggleDetails(questionIndex) {
        const details = document.getElementById(`details-${questionIndex}`);
        const btn = document.getElementById(`toggle-btn-${questionIndex}`);
        if (details.style.display === 'none' || !details.style.display) {
          details.style.display = 'block';
          btn.innerHTML = '<i class="fa-solid fa-eye-slash"></i> Hide Details';
        } else {
          details.style.display = 'none';
          btn.innerHTML = '<i class="fa-solid fa-eye"></i> Show Details';
        }
      }
      
      function toggleView(viewType) {
        if (viewType === 'chart') {
          document.getElementById('chart-view').style.display = 'block';
          document.getElementById('table-view').style.display = 'none';
          document.getElementById('chart-btn').classList.add('active-view');
          document.getElementById('table-btn').classList.remove('active-view');
          // Redraw charts when switching to chart view
          setTimeout(drawCharts, 100);
        } else {
          document.getElementById('chart-view').style.display = 'none';
          document.getElementById('table-view').style.display = 'block';
          document.getElementById('chart-btn').classList.remove('active-view');
          document.getElementById('table-btn').classList.add('active-view');
        }
      }

      // Handle window resize with debouncing
      let resizeTimer;
      window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
          // Adjust all chart heights
          <?php if (!empty($analysis_data)) { 
            foreach ($analysis_data as $index => $data) { ?>
              adjustChartHeight('piechart<?php echo $index; ?>');
              adjustChartOptions(<?php echo $index; ?>);
              // Redraw chart if it exists
              if (window.chartInstances[<?php echo $index; ?>] && window.chartData[<?php echo $index; ?>]) {
                try {
                  window.chartInstances[<?php echo $index; ?>].draw(window.chartData[<?php echo $index; ?>], window.chartOptions[<?php echo $index; ?>]);
                } catch(e) {
                  console.error('Error redrawing chart:', e);
                }
              }
            <?php }
          } ?>
        }, 200);
      });

      // Dark Mode Functionality - Auto apply based on localStorage
      document.addEventListener('DOMContentLoaded', function() {
        const isDarkMode = localStorage.getItem('darkMode') === 'true';
        if (isDarkMode) {
          document.body.classList.add('dark-mode');
        }
        
        // Initialize charts after a short delay
        setTimeout(drawCharts, 500);
      });
    </script>

<style type="text/css">
  * {
    font-family: 'Fredoka', sans-serif;
    margin: 0;
    padding: 0;
    box-sizing: border-box;
  }

  body {
    background-color: white;
    color: #333;
    line-height: 1.6;
    overflow-x: hidden;
    transition: background-color 0.3s, color 0.3s;
  }

  body.dark-mode {
    background-color: #1a1a1a;
    color: #e0e0e0;
  }

  /* Header and Main Content */
  #main {
    transition: all 0.3s ease;
    padding: 20px;
    margin-left: 0;
    min-height: 100vh;
    max-width: 1200px;
    margin: 0 auto;
    width: 100%;
  }

  #main.with-sidebar {
    margin-left: 20%;
    width: 80%;
  }

  #title {
    padding: 20px;
    margin: 20px 0;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
  }

  body.dark-mode #title {
    background: #2d2d2d;
  }

  #item-analysis {
    color: black;
    margin: 10px 0;
    font-size: clamp(1.5rem, 2.5vw, 2.2rem);
    font-weight: 600;
  }

  body.dark-mode #item-analysis {
    color: #e0e0e0;
  }

  #hr1 {
    background-color: #F8B500;
    height: 2px;
    border: none;
    margin: 15px 0;
  }

  #quiz-title {
    font-size: clamp(1.2rem, 1.8vw, 1.5rem);
    color: #555;
    margin: 10px 0;
  }

  body.dark-mode #quiz-title {
    color: #b0b0b0;
  }

  /* Side Navigation */
  .side-nav {
    position: fixed;
    top: 0;
    left: -100%;
    width: 20%;
    min-width: 250px;
    height: 100vh;
    background-color: white;
    color: black;
    transition: all 0.3s ease;
    z-index: 1001;
    overflow-y: auto;
    box-shadow: 2px 0 10px rgba(0,0,0,0.1);
  }

  body.dark-mode .side-nav {
    background-color: #333;
    color: #e0e0e0;
  }

  .side-nav.open {
    left: 0;
  }

  /* Menu buttons */
  #openMenu {
    color: white;
    background-color: #F8B500;
    border: none;
    border-radius: 50%;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 1.2rem;
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
    position: fixed;
    top: 20px;
    left: 20px;
    z-index: 1000;
  }

  #closeMenu {
    color: white;
    background-color: #F8B500;
    border: none;
    border-radius: 50%;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 1.2rem;
    width: 45px;
    height: 45px;
    display: flex;
    align-items: center;
    justify-content: center;
    position: absolute;
    top: 20px;
    right: 20px;
  }

  #openMenu:hover, #closeMenu:hover {
    background-color: #e67e22;
    transform: scale(1.05);
  }

  /* Logo */
  #logo img {
    width: 85%;
    max-width: 200px;
    margin: 30px auto 20px;
    display: block;
    cursor: pointer;
    transition: transform 0.3s ease;
  }

  #logo img:hover {
    transform: scale(1.05);
  }

  /* Horizontal Rules */
  #hr2 {
    background-color: #F8B500;
    height: 2px;
    border: none;
    margin: 20px auto;
    width: 90%;
  }

  /* Back Button */
  #back {
    cursor: pointer;
    width: fit-content;
    margin: 20px auto;
    padding: 12px 20px;
    text-align: center;
    transition: all 0.3s ease;
    color: #555;
    background-color: #f8f9fa;
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 8px;
  }

  #back:hover {
    transform: translateX(-5px);
    color: #F8B500;
    background-color: #e9ecef;
  }

  body.dark-mode #back {
    color: #aaa;
    background-color: #2d2d2d;
  }

  body.dark-mode #back:hover {
    color: #F8B500;
    background-color: #3d3d3d;
  }

  /* Quiz Overview Title in Sidebar */
  .side-nav p {
    text-align: center;
    color: black;
    font-weight: 600;
    padding: 0 20px;
    margin-bottom: 15px;
    font-size: 1.2rem;
  }

  body.dark-mode .side-nav p {
    color: #e0e0e0;
  }

  /* Quiz Items */
  .quiz-items {
    padding: 15px;
    margin-top: 20px;
    height: calc(100vh - 300px);
    overflow-y: auto;
  }

  .quiz-btn {
    background-color: #F8B500;
    color: white;
    border: none;
    padding: 12px 15px;
    margin: 10px auto;
    width: 100%;
    border-radius: 8px;
    text-align: left;
    text-decoration: none;
    display: block;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 0.9rem;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    position: relative;
  }

  .quiz-btn:hover {
    background-color: #e4a600;
    transform: translateY(-2px);
    padding-left: 20px;
  }

  .quiz-btn.selected {
    background-color: white;
    color: #F8B500 !important;
    border: 2px solid #F8B500;
    font-weight: bold;
  }

  body.dark-mode .quiz-btn.selected {
    background-color: #2d2d2d;
    color: #F8B500 !important;
  }

  .quiz-btn::after {
    content: '→';
    position: absolute;
    right: 15px;
    opacity: 0;
    transition: all 0.3s ease;
  }

  .quiz-btn:hover::after {
    opacity: 1;
    transform: translateX(3px);
  }

  .no-quiz-btn {
    text-align: center;
    padding: 30px 20px;
    color: #666;
    font-style: italic;
    font-size: 1.1rem;
  }

  body.dark-mode .no-quiz-btn {
    color: #999;
  }

  /* View toggle buttons */
  .view-toggle {
    display: flex;
    justify-content: center;
    margin: 20px 0;
    gap: 10px;
    flex-wrap: wrap;
  }
  
  .view-btn {
    padding: 12px 24px;
    background-color: #f1f1f1;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-family: Fredoka;
    font-size: 1rem;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 8px;
  }

  body.dark-mode .view-btn {
    background-color: #444;
    color: #e0e0e0;
  }
  
  .view-btn:hover {
    background-color: #ddd;
    transform: translateY(-2px);
  }

  body.dark-mode .view-btn:hover {
    background-color: #555;
  }
  
  .active-view {
    background-color: #F8B500 !important;
    color: #000 !important;
    font-weight: bold;
  }

  /* Charts Container */
  #graph-area {
    width: 100%;
    padding: 20px;
    margin: 20px 0;
    background: white;
    border-radius: 12px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    overflow: hidden;
  }

  body.dark-mode #graph-area {
    background: #2d2d2d;
    box-shadow: 0 5px 15px rgba(0,0,0,0.3);
  }

  .piechart-container {
    margin-bottom: 40px;
    padding: 20px;
    border-bottom: 1px solid #eee;
  }

  body.dark-mode .piechart-container {
    border-bottom: 1px solid #444;
  }

  .piechart-container:last-child {
    border-bottom: none;
  }

  .piechart-container div[id^="piechart"] {
    width: 100% !important;
    margin: 0 auto;
  }

  /* Details Button */
  .details-btn {
    background-color: #F8B500;
    color: #000;
    border: none;
    padding: 12px 20px;
    border-radius: 8px;
    cursor: pointer;
    font-family: Fredoka;
    margin: 15px 0;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.9rem;
  }
  
  .details-btn:hover {
    background-color: #FCD058;
    transform: translateY(-2px);
  }
  
  /* Details Container */
  .details-container {
    display: none;
    margin-top: 20px;
    padding: 20px;
    background-color: #f9f9f9;
    border-radius: 8px;
    border-left: 4px solid #F8B500;
    max-height: 400px;
    overflow: auto;
    transition: background-color 0.3s, color 0.3s;
  }

  body.dark-mode .details-container {
    background-color: #3a3a3a;
    color: #e0e0e0;
  }

  .details-container h3 {
    margin-bottom: 20px;
    font-size: 1.3rem;
  }
  
  .student-list {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    margin-top: 10px;
  }
  
  .student-group {
    flex: 1;
    min-width: 300px;
  }
  
  .student-group h4 {
    margin-bottom: 15px;
    color: #333;
    border-bottom: 2px solid #F8B500;
    padding-bottom: 8px;
    font-size: 1.1rem;
  }

  body.dark-mode .student-group h4 {
    color: #e0e0e0;
  }
  
  .student-item {
    background-color: white;
    padding: 12px;
    margin-bottom: 10px;
    border-radius: 6px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    transition: background-color 0.3s, color 0.3s;
  }

  body.dark-mode .student-item {
    background-color: #444;
    color: #e0e0e0;
  }
  
  .student-item.correct {
    border-left: 4px solid #4CAF50;
  }
  
  .student-item.wrong {
    border-left: 4px solid #f44336;
  }
  
  .student-name {
    font-weight: bold;
    font-size: 0.95rem;
  }
  
  .student-id {
    color: #666;
    font-size: 0.85rem;
    margin-top: 4px;
  }

  body.dark-mode .student-id {
    color: #b0b0b0;
  }

  /* Table view styles */
  #table-view {
    display: none;
    width: 100%;
    overflow-x: auto;
    margin-top: 20px;
  }
  
  .analysis-table {
    width: 100%;
    border-collapse: collapse;
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
  }

  body.dark-mode .analysis-table {
    background: #2d2d2d;
  }
  
  .analysis-table th, 
  .analysis-table td {
    padding: 15px 20px;
    text-align: left;
    border-bottom: 1px solid #eee;
  }

  body.dark-mode .analysis-table th,
  body.dark-mode .analysis-table td {
    border-bottom: 1px solid #444;
  }
  
  .analysis-table th {
    background-color: #F8B500;
    color: #000;
    font-weight: bold;
    font-size: 0.95rem;
  }
  
  .analysis-table tr:nth-child(even) {
    background-color: #f9f9f9;
  }

  body.dark-mode .analysis-table tr:nth-child(even) {
    background-color: #3a3a3a;
  }
  
  .analysis-table tr:hover {
    background-color: #f5f5f5;
  }

  body.dark-mode .analysis-table tr:hover {
    background-color: #4a4a4a;
  }
  
  .percentage-cell {
    font-weight: bold;
    font-size: 1rem;
  }
  
  .high-percentage {
    color: #4CAF50;
  }
  
  .medium-percentage {
    color: #FFC107;
  }
  
  .low-percentage {
    color: #f44336;
  }
  
  .question-text {
    max-width: 300px;
    word-wrap: break-word;
    font-size: 0.9rem;
  }

  .no-data {
    text-align: center;
    padding: 60px 20px;
    color: #666;
    font-style: italic;
    background: #f8f9fa;
    border-radius: 12px;
    margin: 20px 0;
  }

  body.dark-mode .no-data {
    color: #999;
    background: #2d2d2d;
  }

  .no-data i {
    font-size: 3rem;
    margin-bottom: 20px;
    color: #999;
  }

  /* Scrollbar */
  ::-webkit-scrollbar {
    width: 8px;
  }

  ::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
  }

  body.dark-mode ::-webkit-scrollbar-track {
    background: #333;
  }

  ::-webkit-scrollbar-thumb {
    background: #F8B500;
    border-radius: 4px;
  }

  ::-webkit-scrollbar-thumb:hover {
    background: #e4a600;
  }

  /* Sidebar scrollbar */
  .side-nav::-webkit-scrollbar-track {
    background: #f8f9fa;
  }

  body.dark-mode .side-nav::-webkit-scrollbar-track {
    background: #2d2d2d;
  }

  .side-nav::-webkit-scrollbar-thumb {
    background: #F8B500;
  }

  /* Responsive Adjustments */
  @media (max-width: 1200px) {
    .piechart-container div[id^="piechart"] {
      height: 400px;
    }
  }

  @media (max-width: 992px) {
    .piechart-container div[id^="piechart"] {
      height: 380px;
    }
  }

  @media (max-width: 768px) {
    #main {
      padding: 15px;
    }
    
    #title {
      padding: 15px;
      margin: 15px 0;
    }
    
    #item-analysis {
      font-size: 1.8rem;
    }
    
    #quiz-title {
      font-size: 1.3rem;
    }
    
    .view-toggle {
      gap: 8px;
    }
    
    .view-btn {
      padding: 10px 20px;
      font-size: 0.9rem;
    }
    
    #graph-area {
      padding: 15px;
      margin: 15px 0;
    }
    
    .piechart-container {
      padding: 15px;
      margin-bottom: 30px;
    }
    
    .piechart-container div[id^="piechart"] {
      height: 380px !important;
    }
    
    .side-nav {
      width: 300px;
      left: -300px;
    }
    
    #main.with-sidebar {
      margin-left: 0;
      width: 100%;
    }
    
    #openMenu {
      top: 15px;
      left: 15px;
      width: 45px;
      height: 45px;
      font-size: 1.1rem;
    }
    
    #closeMenu {
      top: 15px;
      right: 15px;
      width: 40px;
      height: 40px;
    }
    
    .quiz-items {
      padding: 10px;
      height: calc(100vh - 280px);
    }
    
    .quiz-btn {
      padding: 10px 12px;
      font-size: 0.85rem;
    }
    
    .details-btn {
      padding: 10px 18px;
      font-size: 0.85rem;
    }
    
    .student-group {
      min-width: 100%;
    }
    
    .analysis-table {
      font-size: 0.9rem;
    }
    
    .analysis-table th, 
    .analysis-table td {
      padding: 12px 15px;
    }
  }

  @media (max-width: 576px) {
    #item-analysis {
      font-size: 1.6rem;
    }
    
    #quiz-title {
      font-size: 1.1rem;
    }
    
    .view-btn {
      padding: 8px 16px;
      font-size: 0.85rem;
    }
    
    #graph-area {
      padding: 12px;
      border-radius: 10px;
    }
    
    .piechart-container {
      padding: 12px;
      margin-bottom: 25px;
    }
    
    .piechart-container div[id^="piechart"] {
      height: 350px !important;
    }
    
    .side-nav {
      width: 85%;
      max-width: 320px;
      left: -100%;
    }
    
    #logo img {
      width: 70%;
      margin: 25px auto 15px;
    }
    
    #back {
      padding: 10px 15px;
      font-size: 0.9rem;
    }
    
    .side-nav p {
      font-size: 1.1rem;
      margin-bottom: 10px;
    }
    
    .quiz-btn {
      padding: 10px;
      font-size: 0.8rem;
      margin: 8px auto;
    }
    
    .details-container {
      padding: 15px;
      max-height: 350px;
    }
    
    .details-container h3 {
      font-size: 1.1rem;
    }
    
    .student-group h4 {
      font-size: 1rem;
    }
    
    .student-item {
      padding: 10px;
    }
    
    .analysis-table th, 
    .analysis-table td {
      padding: 10px 12px;
      font-size: 0.85rem;
    }
    
    .question-text {
      max-width: 200px;
    }
  }

  @media (max-width: 480px) {
    #item-analysis {
      font-size: 1.4rem;
      line-height: 1.4;
    }
    
    #main {
      padding: 12px;
    }
    
    #title {
      padding: 12px;
      margin: 12px 0;
    }
    
    #graph-area {
      padding: 10px;
      margin: 12px 0;
    }
    
    .piechart-container {
      padding: 10px;
      margin-bottom: 20px;
    }
    
    .piechart-container div[id^="piechart"] {
      height: 320px !important;
    }
    
    .side-nav {
      width: 90%;
      max-width: 300px;
      left: -100%;
    }
    
    #openMenu {
      top: 10px;
      left: 10px;
      width: 40px;
      height: 40px;
      font-size: 1rem;
    }
    
    #closeMenu {
      top: 10px;
      right: 10px;
      width: 35px;
      height: 35px;
      font-size: 1rem;
    }
    
    #back {
      margin: 15px auto;
      padding: 8px 12px;
      font-size: 0.85rem;
    }
    
    .quiz-items {
      padding: 8px;
      height: calc(100vh - 250px);
    }
    
    .view-toggle {
      flex-direction: column;
      align-items: stretch;
    }
    
    .view-btn {
      width: 100%;
      justify-content: center;
    }
    
    .student-list {
      flex-direction: column;
      gap: 15px;
    }
    
    .analysis-table {
      font-size: 0.8rem;
    }
    
    .analysis-table th, 
    .analysis-table td {
      padding: 8px 10px;
    }
  }

  @media (max-width: 375px) {
    #item-analysis {
      font-size: 1.3rem;
      margin: 10px 0;
    }
    
    #main {
      padding: 10px;
    }
    
    #title {
      padding: 10px;
    }
    
    #graph-area {
      padding: 8px;
      border-radius: 8px;
    }
    
    .piechart-container {
      padding: 8px;
      margin-bottom: 15px;
    }
    
    .piechart-container div[id^="piechart"] {
      height: 280px !important;
    }
    
    .side-nav {
      width: 95%;
      max-width: 280px;
      left: -100%;
    }
    
    #openMenu {
      top: 8px;
      left: 8px;
      width: 38px;
      height: 38px;
      font-size: 0.9rem;
    }
    
    #closeMenu {
      top: 8px;
      right: 8px;
      width: 32px;
      height: 32px;
      font-size: 0.9rem;
    }
    
    #logo img {
      width: 60%;
      margin: 20px auto 10px;
    }
    
    .side-nav p {
      font-size: 1rem;
      padding: 0 15px;
      margin-bottom: 8px;
    }
    
    .quiz-items {
      padding: 6px;
      height: calc(100vh - 230px);
    }
    
    .quiz-btn {
      padding: 8px 10px;
      font-size: 0.75rem;
      margin: 6px auto;
    }
    
    #back {
      padding: 6px 10px;
      font-size: 0.8rem;
    }
    
    .details-btn {
      padding: 8px 15px;
      font-size: 0.8rem;
    }
    
    .analysis-table th, 
    .analysis-table td {
      padding: 6px 8px;
      font-size: 0.75rem;
    }
  }
</style>

  </head>
  <body>

  <i class="fa-solid fa-bars" id="openMenu" onclick="openNav()"></i>

  <div id="main">
    <div id="title">
      <h1 id="item-analysis">Item Analysis</h1> 
      <hr id="hr1"> 
      <h3 id="quiz-title"><?php echo htmlspecialchars($quiz_title); ?></h3> 
    </div>
   
    <div class="view-toggle">
      <button id="chart-btn" class="view-btn active-view" onclick="toggleView('chart')">
        <i class="fa-solid fa-chart-pie"></i> Chart View
      </button>
      <button id="table-btn" class="view-btn" onclick="toggleView('table')">
        <i class="fa-solid fa-table"></i> Table View
      </button>
    </div>

    <div id="chart-view">
      <?php if (!empty($analysis_data)) { ?>
        <div id="graph-area">
          <?php foreach ($analysis_data as $index => $data) { ?>
            <div class="piechart-container">
              <div id="piechart<?php echo $index; ?>" style="width: 100%;"></div>
              
              <button id="toggle-btn-<?php echo $index; ?>" class="details-btn" onclick="toggleDetails(<?php echo $index; ?>)">
                <i class="fa-solid fa-eye"></i> Show Details
              </button>
              
              <div id="details-<?php echo $index; ?>" class="details-container">
                <h3>Detailed Results for Question <?php echo ($index + 1); ?></h3>
                
                <div class="student-list">
                  <div class="student-group">
                    <h4>Correct Answers (<?php echo $data['correct_count']; ?> students)</h4>
                    <?php if (!empty($data['correct_students'])): ?>
                      <?php foreach ($data['correct_students'] as $student): ?>
                        <div class="student-item correct">
                          <div class="student-name"><?php echo htmlspecialchars($student['fname'] . ' ' . $student['lname']); ?></div>
                          <div class="student-id">Account #: <?php echo htmlspecialchars($student['account_number']); ?></div>
                        </div>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <p>No students answered this question correctly.</p>
                    <?php endif; ?>
                  </div>
                  
                  <div class="student-group">
                    <h4>Incorrect Answers (<?php echo $data['wrong_count']; ?> students)</h4>
                    <?php if (!empty($data['wrong_students'])): ?>
                      <?php foreach ($data['wrong_students'] as $student): ?>
                        <div class="student-item wrong">
                          <div class="student-name"><?php echo htmlspecialchars($student['fname'] . ' ' . $student['lname']); ?></div>
                          <div class="student-id">Account #: <?php echo htmlspecialchars($student['account_number']); ?></div>
                        </div>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <p>No students answered this question wrong.</p>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
          <?php } ?>
        </div>
      <?php } else { ?>
        <div class='no-data'>
          <i class="fas fa-chart-pie fa-3x"></i>
          <p>No quiz data available for analysis</p>
        </div>
      <?php } ?>
    </div>

    <div id="table-view">
      <?php if (!empty($analysis_data)) { ?>
        <table class="analysis-table">
          <thead>
            <tr>
              <th>Question #</th>
              <th>Question Text</th>
              <th>Correct Answers</th>
              <th>Incorrect Answers</th>
              <th>Total Answers</th>
              <th>% Correct</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($analysis_data as $index => $data): 
              $total_answers = $data['correct_count'] + $data['wrong_count'];
              $percentage_class = '';
              
              if ($data['percentage_correct'] >= 70) {
                $percentage_class = 'high-percentage';
              } elseif ($data['percentage_correct'] >= 30) {
                $percentage_class = 'medium-percentage';
              } else {
                $percentage_class = 'low-percentage';
              }
            ?>
              <tr>
                <td><?php echo $index + 1; ?></td>
                <td class="question-text"><?php echo htmlspecialchars($data['question_text']); ?></td>
                <td><?php echo $data['correct_count']; ?></td>
                <td><?php echo $data['wrong_count']; ?></td>
                <td><?php echo $total_answers; ?></td>
                <td class="percentage-cell <?php echo $percentage_class; ?>">
                  <?php echo $data['percentage_correct']; ?>%
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php } else { ?>
        <div class='no-data'>
          <i class="fas fa-table fa-3x"></i>
          <p>No quiz data available for analysis</p>
        </div>
      <?php } ?>
    </div>

  </div>  

  <!-- Sidebar Navigation -->
  <div class="side-nav" id="sideNav">
    <center>
      <i class="fa-solid fa-times" id="closeMenu" onclick="closeNav()"></i>
      <div id="logo">
        <img src="img/logo1.png" onclick="window.location.href='t_Home.php'">
      </div>  
    </center>
    
    <hr id="hr2">

    <div id="back" onclick="window.location.href='t_item-analysis.php?subject_id=<?php echo $subject_id; ?>'">
      <span><i class="fa-solid fa-chevron-left"></i> Back to Subject Summary</span>
    </div>

    <p>Quizzes Overview</p> 
    
    <div class="quiz-items">
      <?php
        if ($quiz_result->num_rows > 0) {
            while ($row = $quiz_result->fetch_assoc()) {
                $is_selected = ($row['quiz_id'] == $quiz_id) ? 'selected' : '';
                echo "<a style='color: white;' class='quiz-btn {$is_selected}' href='t_quiz-item-analysis.php?quiz_id=" . $row['quiz_id'] . "&subject_id=" . $subject_id . "'>" . htmlspecialchars($row['title']) . "</a>";
          }
        } else {
            echo "<div class='no-quiz-btn'>";
            echo "<p>No quizzes created yet.</p>";
            echo "</div>";
        }
        ?>
    </div>
  </div> 

<script type="text/javascript">
  
  let sidebarOpen = false;

  function openNav() {
    document.querySelector('.side-nav').classList.add('open');
    document.getElementById('main').classList.add('with-sidebar');
    sidebarOpen = true;
    document.body.style.overflow = 'hidden';
  }

  function closeNav() {
    document.querySelector('.side-nav').classList.remove('open');
    document.getElementById('main').classList.remove('with-sidebar');
    sidebarOpen = false;
    document.body.style.overflow = 'auto';
  }

  function checkScreenSize() {
    if (window.innerWidth <= 768) {
      closeNav();
    }
  }

  document.addEventListener('DOMContentLoaded', function() {
    closeNav();
    checkScreenSize();
    
    // Add keyboard shortcut (ESC to close sidebar)
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape' && sidebarOpen) {
        closeNav();
      }
    });
    
    // Check for console errors
    window.addEventListener('error', function(e) {
      console.error('JavaScript Error:', e.message, 'at', e.filename, ':', e.lineno);
    });
  });

  // Handle screen resize
  window.addEventListener('resize', function() {
    checkScreenSize();
  });

  // Close sidebar when clicking outside on mobile
  document.addEventListener('click', function(event) {
    const sideNav = document.querySelector('.side-nav');
    const openMenu = document.getElementById('openMenu');
    
    if (window.innerWidth <= 768 && sidebarOpen && 
        !sideNav.contains(event.target) && 
        !openMenu.contains(event.target)) {
      closeNav();
    }
  });

  // Add smooth transition for view switching
  document.getElementById('chart-btn').addEventListener('click', function(e) {
    document.getElementById('chart-view').style.opacity = '0.7';
    setTimeout(() => {
      document.getElementById('chart-view').style.opacity = '1';
    }, 300);
  });
  
  document.getElementById('table-btn').addEventListener('click', function(e) {
    document.getElementById('table-view').style.opacity = '0.7';
    setTimeout(() => {
      document.getElementById('table-view').style.opacity = '1';
    }, 300);
  });
</script>

  </body>
</html>