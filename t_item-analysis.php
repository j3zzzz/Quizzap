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

if (!isset($_GET['subject_id']) || !isset($_SESSION['account_number'])) {
  header("Location: login.php");
  exit();
}

$subject_id = $_GET['subject_id'];
$teacher_account_number = $_SESSION['account_number'];

$sql = "SELECT * FROM subjects WHERE subject_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $subject_id);
$stmt->execute();
$result = $stmt->get_result();
$subject = $result->fetch_assoc();
$stmt->close();

if (!$subject) {
    echo "Subject not found";
    exit();
}

// Fetch quizzes for the sidebar
$sql_quizzes = "SELECT * FROM quizzes WHERE subject_id = ? ORDER BY quiz_id DESC";
$stmt_quizzes = $conn->prepare($sql_quizzes);
$stmt_quizzes->bind_param("i", $subject_id);
$stmt_quizzes->execute();
$quiz_result = $stmt_quizzes->get_result();
$quizzes = [];
while ($row = $quiz_result->fetch_assoc()) {
    $quizzes[] = $row;
}
$stmt_quizzes->close();

//Computes the average score for the subject
if ($subject_id) {
  $avgScoreQry = "
      SELECT q.title, 
            AVG(qa.score) AS average_score,
            MAX(qa.score) AS high_score,
            MIN(qa.score) AS low_score
    FROM quizzes q
    LEFT JOIN quiz_attempts qa ON q.quiz_id = qa.quiz_id
    WHERE q.subject_id = ?
    GROUP BY q.quiz_id, q.title";

$stmt = $conn->prepare($avgScoreQry);
if ($stmt === false) {
  die("Error preparing the statement: " . $conn->error);
}

$stmt->bind_param("i", $subject_id);
$stmt->execute();
$result = $stmt->get_result();

$quizResults = [];

if ($result->num_rows > 0) {
  while ($row = $result->fetch_assoc()) {
    $quizResults[] = [
      'title' => $row['title'], 
      'avg_score' => $row['average_score'], 
      'high_score' => $row['high_score'], 
      'low_score' => $row['low_score']
    ];
  }
} 
}

$qType = "
    SELECT 
        CASE 
            WHEN q.quiz_type = 'All Zapped' THEN 
                CASE 
                    WHEN qq.question_type = 'multiple_choice' THEN 'Multiple Choice'
                    WHEN qq.question_type = 'true_or_false' THEN 'True or False'
                    WHEN qq.question_type = 'enumeration' THEN 'Enumeration'
                    WHEN qq.question_type = 'fill_in_the_blanks' THEN 'Fill in the Blanks'
                    WHEN qq.question_type = 'drag_and_drop' THEN 'Drag and Drop'
                    WHEN qq.question_type = 'identification' THEN 'Identification'
                    WHEN qq.question_type = 'matching_type' THEN 'Matching Type'
                    ELSE qq.question_type
                END
            ELSE 
                CASE 
                    WHEN q.quiz_type = 'multiple_choice' THEN 'Multiple Choice'
                    WHEN q.quiz_type = 'true_false' THEN 'True or False'
                    WHEN q.quiz_type = 'enumeration' THEN 'Enumeration'
                    WHEN q.quiz_type = 'fill_in_the_blanks' THEN 'Fill in the Blanks'
                    WHEN q.quiz_type = 'drag_and_drop' THEN 'Drag and Drop'
                    WHEN q.quiz_type = 'identification' THEN 'Identification'
                    WHEN q.quiz_type = 'matching_type' THEN 'Matching Type'
                    ELSE q.quiz_type
                END
        END AS formatted_type,
        COUNT(qa.account_number) AS total_attempts,
        AVG(qa.score) AS average_score,
        MAX(qa.score) AS highest_score,
        MIN(qa.score) AS lowest_score
    FROM quizzes q
    LEFT JOIN quiz_attempts qa ON q.quiz_id = qa.quiz_id
    LEFT JOIN questions qq ON q.quiz_id = qq.quiz_id AND q.quiz_type = 'All Zapped'
    WHERE q.subject_id = ?
    GROUP BY formatted_type
    ORDER BY formatted_type";

$stmt = $conn->prepare($qType);

if ($stmt === false) {
    die("Error preparing the statement: " . $conn->error);
}

$stmt->bind_param("i", $subject_id);
$stmt->execute();
$result = $stmt->get_result();

$quiz_type_data = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $quiz_type_data[] = [
            'quiz_type' => $row['formatted_type'],
            'total_attempts' => $row['total_attempts'],
            'average_score' => $row['average_score'],
            'highest_score' => $row['highest_score'],
            'lowest_score' => $row['lowest_score']
        ];
    }
}

$stmt->close();
$conn->close();
?>


<!DOCTYPE html>
  <head>
  <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" type="text/css" href="other resources/fontawesome-free-6.5.2-web/css/all.min.css">
    <title>Subject Summary</title>

    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <script type="text/javascript">
      google.charts.load('current', {'packages':['corechart', 'bar']});
      google.charts.setOnLoadCallback(drawCharts);
          
      function drawCharts() {
        studPerfChart();
        quizTypeChart();
      }  

      function studPerfChart() {
        var container = document.getElementById('columnchart_material');
        <?php if (empty($quizResults)): ?>
          container.innerHTML = '<div class="no-data-message"><i class="fas fa-chart-bar fa-3x"></i><p>No average score data available for this Subject</p></div>';
          return;
        <?php endif; ?>
        
        var googleData = google.visualization.arrayToDataTable([
          ['Quiz Title', 'Average Score', 'High Score', 'Low Score'],
            <?php 
              foreach ($quizResults as $quiz) {
                echo "['" . ($quiz['title'] ?? 'Unknown') . "', " .
                          ($quiz['avg_score'] ?? 0) .", " .
                          ($quiz['high_score'] ?? 0) .", " .
                          ($quiz['low_score'] ?? 0) ."],";
              }
            ?>
          ]);

        var options = {
          colors: ['#4CAF50', '#2196F3', '#F44336'],
          fontName: 'Fredoka',
          fontSize: 14,
          height: 'auto',
          width: '100%',
          chartArea: {
            left: '12%',
            top: '15%',
            width: '85%',
            height: '70%',
            backgroundColor: {
              fill: 'transparent'
            }
          },
          backgroundColor: 'transparent',
          chart: {
            title: 'Your Students Performance for this Subject',
            subtitle: 'Computed based on their Scores for all of the Quiz Taken in this Subject',
            titleTextStyle: {
              color: '#000',
              italic: true,
              bold: true,
              fontSize: 18
            },
            subtitleTextStyle: {
              color: '#666',
              fontSize: 14,
              italic: true
            }
          },
          bars: 'vertical',
          isStacked: false,
          legend: {
            position: 'top',
            alignment: 'center',
            textStyle: {
              color: '#000',
              fontSize: 13,
              bold: true
            }
          },
          tooltip: {
            textStyle: {
              fontName: 'Fredoka',
              fontSize: 13,
              color: '#333'
            },
            showColorCode: true,
            isHtml: true
          },
          hAxis: {
            textStyle: { 
              color: '#000', 
              fontSize: 12,
              bold: true 
            },
            slantedText: true,
            slantedTextAngle: 45,
            maxAlternation: 1,
            maxTextLines: 2,
            gridlines: {
              color: '#e0e0e0',
              count: 5
            },
            baselineColor: '#ccc',
            titleTextStyle: {
              color: '#666',
              italic: true
            }
          },
          vAxis: { 
            textStyle: { 
              color: '#000', 
              fontSize: 12,
              bold: true 
            },
            baselineColor: '#ccc',
            gridlines: {
              color: '#e0e0e0',
              count: 5
            },
            minValue: 0,
            format: '#',
            title: 'Score',
            titleTextStyle: {
              color: '#666',
              italic: true
            }
          },
          animation: {
            duration: 1000,
            easing: 'out',
            startup: true
          },
          bar: {
            groupWidth: '60%'
          },
          focusTarget: 'category'
        };

        adjustChartOptions(options);
        adjustChartHeight('columnchart_material');

        // Apply dark mode colors if enabled
        if (document.body.classList.contains('dark-mode')) {
          options.backgroundColor = '#1a1a1a';
          options.chartArea.backgroundColor.fill = '#1a1a1a';
          options.chart.titleTextStyle.color = '#e0e0e0';
          options.chart.subtitleTextStyle.color = '#ccc';
          options.legend.textStyle.color = '#e0e0e0';
          options.hAxis.textStyle.color = '#e0e0e0';
          options.hAxis.gridlines.color = '#333';
          options.hAxis.baselineColor = '#666';
          options.vAxis.textStyle.color = '#e0e0e0';
          options.vAxis.gridlines.color = '#333';
          options.vAxis.baselineColor = '#666';
          options.vAxis.titleTextStyle.color = '#ccc';
          options.hAxis.titleTextStyle.color = '#ccc';
        }

        var chart = new google.visualization.ColumnChart(container);
        chart.draw(googleData, options);
      }

      function quizTypeChart() {
        var container = document.getElementById('columnchart');
        <?php if (empty($quiz_type_data)): ?>
          container.innerHTML = '<div class="no-data-message"><i class="fas fa-chart-pie fa-3x"></i><p>No quiz types data available for this Subject</p></div>';
          return;
        <?php endif; ?>
        
        var data = google.visualization.arrayToDataTable([
          ['Quiz Type', 'Total Attempts', 'Average Scores', 'Highest Scores', 'Lowest Scores'],
          <?php foreach ($quiz_type_data as $data) { ?>
            ['<?php echo $data['quiz_type']; ?>', 
            <?php echo (int)$data['total_attempts']; ?>, 
            <?php echo (float)$data['average_score']; ?>, 
            <?php echo (float)$data['highest_score']; ?>, 
            <?php echo (float)$data['lowest_score']; ?>],
          <?php } ?>
        ]);

        var options = {
          colors: ['#9C27B0', '#FF9800', '#4CAF50', '#2196F3'],
          fontName: 'Fredoka',
          fontSize: 14,
          height: 'auto',
          width: '100%',
          chartArea: {
            left: '12%',
            top: '15%',
            width: '85%',
            height: '70%',
            backgroundColor: {
              fill: 'transparent'
            }
          },
          backgroundColor: 'transparent',
          chart: {
            titleTextStyle: {
              fontName: 'Fredoka',
              color: '#666',
              fontSize: 18,
              bold: true
            }  
          },
          legend: {
            position: 'top',
            alignment: 'center',
            textStyle: {
              color: '#000',
              fontSize: 13,
              bold: true
            }
          },          
          hAxis: {
            textStyle: { 
              color: '#000', 
              fontSize: 12,
              bold: true 
            },
            slantedText: true,
            slantedTextAngle: 45,
            maxAlternation: 1,
            maxTextLines: 2,
            gridlines: {
              color: '#e0e0e0',
              count: 5
            },
            baselineColor: '#ccc',
            titleTextStyle: {
              color: '#666',
              italic: true
            }
          },
          vAxis: { 
            textStyle: { 
              color: '#000', 
              fontSize: 12,
              bold: true 
            },
            baselineColor: '#ccc',
            gridlines: {
              color: '#e0e0e0',
              count: 5
            },
            minValue: 0,
            format: '#',
            title: 'Score / Attempts',
            titleTextStyle: {
              color: '#666',
              italic: true
            }
          },
          tooltip: {
            textStyle: {
              fontName: 'Fredoka',
              fontSize: 13,
              color: '#333'
            },
            showColorCode: true,
            isHtml: true
          },          
          animation: {
            duration: 1000,
            easing: 'out',
            startup: true
          },
          bar: {
            groupWidth: '60%'
          },
          focusTarget: 'category'
        };

        adjustChartOptions(options);
        adjustChartHeight('columnchart');

        // Apply dark mode colors if enabled
        if (document.body.classList.contains('dark-mode')) {
          options.backgroundColor = '#1a1a1a';
          options.chartArea.backgroundColor.fill = '#1a1a1a';
          options.chart.titleTextStyle.color = '#e0e0e0';
          options.legend.textStyle.color = '#e0e0e0';
          options.hAxis.textStyle.color = '#e0e0e0';
          options.hAxis.gridlines.color = '#333';
          options.hAxis.baselineColor = '#666';
          options.vAxis.textStyle.color = '#e0e0e0';
          options.vAxis.gridlines.color = '#333';
          options.vAxis.baselineColor = '#666';
          options.vAxis.titleTextStyle.color = '#ccc';
          options.hAxis.titleTextStyle.color = '#ccc';
        }

        var chart = new google.visualization.ColumnChart(container);
        chart.draw(data, options);
      }

      function adjustChartOptions(options) {
        const width = window.innerWidth;
        
        if (width <= 768) {
          options.chartArea.left = '15%';
          options.chartArea.top = '18%';
          options.chartArea.width = '80%';
          options.chartArea.height = '68%';
          options.hAxis.slantedTextAngle = width <= 480 ? 90 : 60;
          options.hAxis.textStyle.fontSize = 11;
          options.vAxis.textStyle.fontSize = 11;
          options.legend.textStyle.fontSize = 12;
          if (options.chart.titleTextStyle) {
            options.chart.titleTextStyle.fontSize = 16;
          }
          if (options.chart.subtitleTextStyle) {
            options.chart.subtitleTextStyle.fontSize = 12;
          }
          options.fontSize = 12;
        }

        if (width <= 576) {
          options.chartArea.left = '18%';
          options.chartArea.top = '22%';
          options.chartArea.width = '78%';
          options.chartArea.height = '65%';
          options.hAxis.textStyle.fontSize = 10;
          options.vAxis.textStyle.fontSize = 10;
          options.legend.textStyle.fontSize = 11;
          if (options.chart.titleTextStyle) {
            options.chart.titleTextStyle.fontSize = 15;
          }
          if (options.chart.subtitleTextStyle) {
            options.chart.subtitleTextStyle.fontSize = 11;
          }
          options.fontSize = 11;
          options.bar.groupWidth = '50%';
        }

        if (width <= 480) {
          options.chartArea.left = '20%';
          options.chartArea.top = '25%';
          options.chartArea.width = '75%';
          options.chartArea.height = '62%';
          options.hAxis.slantedTextAngle = 90;
          options.hAxis.textStyle.fontSize = 9;
          options.vAxis.textStyle.fontSize = 9;
          options.legend.textStyle.fontSize = 10;
          if (options.chart.titleTextStyle) {
            options.chart.titleTextStyle.fontSize = 14;
          }
          if (options.chart.subtitleTextStyle) {
            options.chart.subtitleTextStyle.fontSize = 10;
          }
          options.fontSize = 10;
          options.bar.groupWidth = '45%';
        }

        if (width <= 375) {
          options.chartArea.left = '22%';
          options.chartArea.top = '28%';
          options.chartArea.width = '73%';
          options.chartArea.height = '60%';
          options.hAxis.textStyle.fontSize = 8;
          options.vAxis.textStyle.fontSize = 8;
          options.legend.textStyle.fontSize = 9;
          if (options.chart.titleTextStyle) {
            options.chart.titleTextStyle.fontSize = 13;
          }
          if (options.chart.subtitleTextStyle) {
            options.chart.subtitleTextStyle.fontSize = 9;
          }
          options.fontSize = 9;
          options.bar.groupWidth = '40%';
        }
      }

      function adjustChartHeight(chartId) {
        const container = document.getElementById(chartId);
        if (!container) return;
        
        const width = window.innerWidth;
        const graphArea = document.getElementById('graph-area');
        
        if (width <= 375) {
          container.style.height = '320px';
          graphArea.style.minHeight = '320px';
        } else if (width <= 480) {
          container.style.height = '350px';
          graphArea.style.minHeight = '350px';
        } else if (width <= 576) {
          container.style.height = '380px';
          graphArea.style.minHeight = '380px';
        } else if (width <= 768) {
          container.style.height = '420px';
          graphArea.style.minHeight = '420px';
        } else {
          container.style.height = '500px';
          graphArea.style.minHeight = '500px';
        }
      }

      function displayedFilters() {
        const selectedFilters = document.getElementById('filters').value;

        if (selectedFilters === 'Quiz Type') {
          document.getElementById('graph-area').innerHTML = '<div id="columnchart"></div>';
          setTimeout(function() {
            quizTypeChart();
          }, 100);
        
        } else if (selectedFilters === 'Student Performance') {
          document.getElementById('graph-area').innerHTML = '<div id="columnchart_material"></div>';
          setTimeout(function() {
            studPerfChart();
          }, 100);
        }
      }

      // Handle window resize with debouncing
      let resizeTimer;
      window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
          adjustChartHeight('columnchart_material');
          adjustChartHeight('columnchart');
          if (document.getElementById('columnchart_material')) {
            studPerfChart();
          }
          if (document.getElementById('columnchart')) {
            quizTypeChart();
          }
        }, 200);
      });
    </script>

<style>
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

  .sum h1 {
    color: black;
    margin: 20px 0;
    font-size: clamp(1.5rem, 2.5vw, 2.2rem);
    text-align: center;
    font-weight: 600;
    padding: 10px;
  }

  body.dark-mode .sum h1 {
    color: #e0e0e0;
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
  #hr1, #hr2 {
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

  /* Filter Section */
  #filter {
    margin: 20px 0;
    padding: 0 5%;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
  }

  #filter label {
    font-weight: 500;
    color: #2c3e50;
    font-size: 1.1rem;
  }

  body.dark-mode #filter label {
    color: #e0e0e0;
  }

  #filter select {
    padding: 10px 20px;
    border-radius: 8px;
    border: 2px solid #ddd;
    background-color: white;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 1rem;
    min-width: 200px;
  }

  body.dark-mode #filter select {
    background-color: #333;
    color: #e0e0e0;
    border-color: #555;
  }

  #filter select:focus {
    outline: none;
    border-color: #F8B500;
    box-shadow: 0 0 0 3px rgba(248, 181, 0, 0.2);
  }

  /* Charts */
  #graph-area {
    width: 100%;
    padding: 20px;
    margin: 20px 0;
    background: white;
    border-radius: 12px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    overflow: hidden;
    min-height: 500px;
  }

  body.dark-mode #graph-area {
    background: #2d2d2d;
    box-shadow: 0 5px 15px rgba(0,0,0,0.3);
  }

  #columnchart_material, #columnchart {
    width: 100% !important;
    height: 500px;
    margin: 0 auto;
  }

  /* Chart info badges */
  .chart-info {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    justify-content: center;
    margin-top: 25px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 12px;
  }

  body.dark-mode .chart-info {
    background: #3d3d3d;
  }

  .info-badge {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 20px;
    background: white;
    border-radius: 8px;
    font-size: 0.9rem;
    font-weight: 500;
    color: #333;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  }

  body.dark-mode .info-badge {
    background: #4a4a4a;
    color: #e0e0e0;
  }

  .info-badge i {
    font-size: 1.2rem;
  }

  /* Color badges matching your chart colors */
  .info-badge.avg i { color: #4CAF50; }
  .info-badge.high i { color: #2196F3; }
  .info-badge.low i { color: #F44336; }
  .info-badge.attempts i { color: #9C27B0; }
  .info-badge.avg-score i { color: #FF9800; }

  /* No data message */
  .no-data-message {
    text-align: center;
    padding: 60px 20px;
    color: #666;
    font-style: italic;
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 300px;
  }

  .no-data-message i {
    margin-bottom: 20px;
    color: #999;
  }

  .no-data-message p {
    font-size: 1.3rem;
    max-width: 400px;
    line-height: 1.6;
  }

  body.dark-mode .no-data-message {
    color: #999;
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

  /* Responsive Adjustments */
  @media (max-width: 1200px) {
    #columnchart_material, #columnchart {
      height: 450px;
    }
    #graph-area {
      min-height: 450px;
    }
  }

  @media (max-width: 992px) {
    #columnchart_material, #columnchart {
      height: 420px;
    }
    #graph-area {
      min-height: 420px;
    }
  }

  @media (max-width: 768px) {
    #main {
      padding: 15px;
    }
    
    .sum h1 {
      font-size: 1.8rem;
      margin: 15px 0;
    }
    
    #filter {
      padding: 0 10px;
      flex-direction: column;
      align-items: flex-start;
      gap: 15px;
    }
    
    #filter select {
      width: 100%;
      min-width: unset;
    }
    
    #graph-area {
      padding: 15px;
      margin: 15px 0;
      min-height: 420px;
    }
    
    #columnchart_material, #columnchart {
      height: 420px !important;
    }
    
    .side-nav {
      width: 300px;
      left: -300px;
    }
    
    #main.with-sidebar {
      margin-left: 0;
      width: 100%;
      transform: translateX(0);
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
    
    .chart-info {
      padding: 15px;
      gap: 12px;
    }
    
    .info-badge {
      padding: 8px 15px;
      font-size: 0.85rem;
    }
  }

  @media (max-width: 576px) {
    .sum h1 {
      font-size: 1.6rem;
      padding: 8px;
    }
    
    #filter label {
      font-size: 1rem;
    }
    
    #filter select {
      padding: 8px 15px;
      font-size: 0.95rem;
    }
    
    #graph-area {
      padding: 12px;
      border-radius: 10px;
      min-height: 380px;
    }
    
    #columnchart_material, #columnchart {
      height: 380px !important;
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
    
    .no-data-message {
      padding: 40px 20px;
    }
    
    .no-data-message p {
      font-size: 1.1rem;
    }
  }

  @media (max-width: 480px) {
    .sum h1 {
      font-size: 1.4rem;
      line-height: 1.4;
    }
    
    #main {
      padding: 12px;
    }
    
    #filter {
      margin: 15px 0;
      gap: 12px;
    }
    
    #graph-area {
      padding: 10px;
      margin: 12px 0;
      min-height: 350px;
    }
    
    #columnchart_material, #columnchart {
      height: 350px !important;
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
    
    .chart-info {
      flex-direction: column;
      align-items: stretch;
      gap: 10px;
    }
    
    .info-badge {
      justify-content: center;
    }
  }

  @media (max-width: 375px) {
    .sum h1 {
      font-size: 1.3rem;
      margin: 10px 0;
    }
    
    #main {
      padding: 10px;
    }
    
    #filter {
      padding: 0;
    }
    
    #filter select {
      padding: 8px 12px;
      font-size: 0.9rem;
    }
    
    #graph-area {
      padding: 8px;
      border-radius: 8px;
      min-height: 320px;
    }
    
    #columnchart_material, #columnchart {
      height: 320px !important;
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

  /* Loading Animation */
  .loading-chart {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 300px;
  }

  .loading-spinner {
    width: 40px;
    height: 40px;
    border: 3px solid #f3f3f3;
    border-top: 3px solid #F8B500;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-bottom: 20px;
  }

  @keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
  }
</style>

  </head>
  <body>

  <i class="fa-solid fa-bars" id="openMenu" onclick="openNav()"></i>

  <div id="main">
    <div class="sum">
      <h1>Subject Summary for <?php echo htmlspecialchars($subject['subject_name']); ?></h1>
    </div>

    <hr id="hr1">

    <div id="filter">
      <label for="filters">Filter by:</label>
      <select name="filters" id="filters" onchange="displayedFilters()">
        <option value="Student Performance">Student Performance</option>
        <option value="Quiz Type">Quiz Type</option>
      </select>
    </div>

    <div id="graph-area">
      <div id="columnchart_material"></div>
    </div>

    <!-- Chart Information Badges -->
    <div class="chart-info">
      <div class="info-badge avg">
        <i class="fas fa-chart-line"></i>
        <span>Average Score (Green)</span>
      </div>
      <div class="info-badge high">
        <i class="fas fa-trophy"></i>
        <span>Highest Score (Blue)</span>
      </div>
      <div class="info-badge low">
        <i class="fas fa-chart-bar"></i>
        <span>Lowest Score (Red)</span>
      </div>
      <div class="info-badge attempts">
        <i class="fas fa-users"></i>
        <span>Total Attempts (Purple)</span>
      </div>
      <div class="info-badge avg-score">
        <i class="fas fa-chart-pie"></i>
        <span>Average Score (Orange)</span>
      </div>
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

    <div id="back" onclick="window.location.href='t_SubjectsList.php?subject_id=<?php echo $subject_id; ?>'">
      <span><i class="fa-solid fa-chevron-left"></i> Back to Your Subject/s</span>
    </div>

    <p>Quizzes Overview</p> 
    
    <div class="quiz-items">
      <?php if (!empty($quizzes)): ?>
        <?php foreach ($quizzes as $quiz): ?>
          <a style='color: white;' class='quiz-btn' href='t_quiz-item-analysis.php?quiz_id=<?php echo $quiz['quiz_id']; ?>'>
            <?php echo htmlspecialchars($quiz['title']); ?>
          </a>
        <?php endforeach; ?>
      <?php else: ?>
        <div class='no-quiz-btn'>
          <p>No quizzes created yet.</p>
        </div>
      <?php endif; ?>
    </div>
  </div> 

<script type="text/javascript">
  
  let sidebarOpen = false;

  function openNav() {
    document.querySelector('.side-nav').style.left = '0';
    document.getElementById('main').classList.add('with-sidebar');
    sidebarOpen = true;
    document.body.style.overflow = 'hidden';
  }

  function closeNav() {
    document.querySelector('.side-nav').style.left = '-100%';
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
    
    // Dark Mode Functionality
    const isDarkMode = localStorage.getItem('darkMode') === 'true';
    if (isDarkMode) {
      document.body.classList.add('dark-mode');
    }
    
    // Initialize charts
    setTimeout(function() {
      if (typeof drawCharts === 'function') {
        drawCharts();
      }
    }, 100);
    
    // Add keyboard shortcut (ESC to close sidebar)
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape' && sidebarOpen) {
        closeNav();
      }
    });
  });

  // Handle screen resize
  window.addEventListener('resize', function() {
    checkScreenSize();
    
    clearTimeout(this.resizeTimer);
    this.resizeTimer = setTimeout(function() {
      adjustChartHeight('columnchart_material');
      adjustChartHeight('columnchart');
      if (document.getElementById('columnchart_material')) {
        studPerfChart();
      }
      if (document.getElementById('columnchart')) {
        quizTypeChart();
      }
    }, 200);
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

  // Add smooth transition for chart switching
  document.getElementById('filters').addEventListener('change', function(e) {
    const graphArea = document.getElementById('graph-area');
    graphArea.style.opacity = '0.7';
    setTimeout(() => {
      graphArea.style.opacity = '1';
    }, 300);
  });
</script>

  </body>
</html>