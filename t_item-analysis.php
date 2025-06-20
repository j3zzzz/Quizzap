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

//Computes the averaage score for the subject

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

if (empty($quizResults)) {
  echo "<div id='no-data'>No avarage score data available for this Subject</div>";
}
}

$qType = "
  SELECT q.quiz_type, q.title AS quiz_title,
    COUNT(qa.account_number) AS total_attempts,
    AVG(qa.score) AS average_score,
    MAX(qa.score) AS highest_score,
    MIN(qa.score) AS lowest_score
    FROM quizzes q
    INNER JOIN quiz_attempts qa ON q.quiz_id = qa.quiz_id
    WHERE q.subject_id = ?
    GROUP BY q.quiz_type
    ORDER BY q.quiz_id";

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
    $quiz_type_data[] = $row;
  }
} else {
  echo "<div id='no-data'> No quiz types data available for this Subject.</div>";
}


$stmt->close();


// Fetch the quizzes related to the subject
$sql = "SELECT * FROM quizzes WHERE subject_id = ? ORDER BY quiz_id DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $subject_id);
$stmt->execute();
$result = $stmt->get_result();

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
           colors: ['#e4a600', '#F8B500', '#FCD058'],
           fontName: 'Fredoka',
           fontSize: 15,
           height: 500,
           width: '100%',
          chart: {
            title: 'Your Students Performance for this Subject',
            subtitle: 'Computed based on their Scores for all of the Quiz Taken in this Subject',
              titleTextStyle: {
                color: '#000',
                italic: true,
                bold: true
              }  
            },
            bars: 'vertical',
            legend: {
              textStyle: {
                color: '#000'
              }
            },

            tooltip: {
              textStyle: {
                fontName: 'Fredoka',
                bold: false,
              }
            },

            hAxis: {
              textStyle: { color: '#000', fontSize: 15 },
            },
            vAxis: { 
              textStyle: { color: '#000' },
              baselineColor: '#000',
              gridlines: {color: '#ccc'}
            },

            animation: {
              duration: 1000,  // Animation duration in milliseconds
              easing: 'inAndOut',   // Easing function: 'linear', 'in', 'out', 'inAndOut'
              startup: true    // Whether the animation should start when the chart is drawn
            } 
        };

        var chart = new google.visualization.ColumnChart(document.getElementById('columnchart_material'));

        chart.draw(googleData, options);
      }

      function quizTypeChart() {
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
          colors: ['#e4a600', '#F8B500', '#FCD058'],
          fontName: 'Fredoka',
          fontSize: 15,
           height: 500,
           width: '100%',
          chart: {
              titleTextStyle: {
                fontName: 'Fredoka',
                color: '#666',
                fontSize: 18,
                bold: true
              }  
            },
          legend: {
            textStyle: {
              color: '#000'
            }
          },          
          hAxis: {
            textStyle: { color: '#000', fontSize: 15 }
          },

          vAxis: { 
            textStyle: { color: '#000' },
            baselineColor: '#000',
            gridlines: {color: '#ccc'}
          },

          tooltip: {
          textStyle: {
            fontName: 'Fredoka'
          }
          },          
          
          animation: {
            duration: 1000,  // Animation duration in milliseconds
            easing: 'inAndOut',   // Easing function: 'linear', 'in', 'out', 'inAndOut'
            startup: true    // Whether the animation should start when the chart is drawn
          }

        };

        var chart = new google.visualization.ColumnChart(document.getElementById('columnchart'));
        chart.draw(data, options);
      }

      function displayedFilters() {
        const selectedFilters = document.getElementById('filters').value;

        if (selectedFilters === 'Quiz Type') {
          document.getElementById('graph-area').innerHTML = '<div id="columnchart"></div>';
          quizTypeChart(); // Draw column chart for quiz types
        
        } else if (selectedFilters === 'Student Performance') {
          document.getElementById('graph-area').innerHTML = '<div id="columnchart_material"></div>';
          studPerfChart();
        }
      }

      window.addEventListener('resize', function() {
        if (document.getElementById('columnchart_material')){
          studPerfChart();
        }
        if (document.getElementById('columnchart')) {
          quizTypeChart();
        }    
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
  }

  /* Header and Main Content */
  #main {
    transition: all 0.3s ease;
    padding: 20px;
    margin-left: 0;
    min-height: 100vh;
    max-width: 1200px;
    margin: 0 auto;
  }

  #main.open {
    margin-left: 20%;
    width: 80%;
    max-width: none;
  }

  .sum h1 {
    color: black;
    margin: 20px 0;
    font-size: clamp(1.5rem, 2.5vw, 2.2rem);
    text-align: center;
    font-weight: 600;
    padding: 10px;
  }

  /* Side Navigation */
  .side-nav {
    position: fixed;
    top: 0;
    left: -20%;
    width: 20%;
    min-width: 250px;
    height: 100vh;
    background-color: white;
    color: black;
    transition: all 0.3s ease;
    z-index: 1000;
    overflow-y: auto;
    box-shadow: 2px 0 10px rgba(0,0,0,0.1);
  }

  .side-nav.open {
    left: 0;
  }

  /* Menu buttons */
  #closeMenu, #openMenu {
    color: white;
    background-color: #F8B500;
    border: none;
    padding: 10px;
    border-radius: 5px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 1rem;
  }

  #openMenu {
    position: fixed;
    top: 20px;
    left: 20px;
    z-index: 100;
  }

  #closeMenu {
    position: absolute;
    top: 20px;
    right: 20px;
  }

  #closeMenu:hover, #openMenu:hover {
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
    padding: 10px;
    text-align: center;
    transition: all 0.3s ease;
    color: #ccc;
  }

  #back:hover {
    transform: translateX(-5px);
    color: #F8B500;
  }

  /* Quiz Items */
  .quiz-items {
    padding: 20px;
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
    text-align: center;
    text-decoration: none;
    display: block;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 0.9rem;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
  }

  .quiz-btn:hover {
    background-color: #e4a600;
    transform: translateY(-2px);
  }

  /* Filter Section */
  #filter {
    margin: 20px 0;
    padding: 0 5%;
    display: flex;
    align-items: center;
    gap: 10px;
  }

  #filter label {
    font-weight: 500;
    color: #2c3e50;
  }

  #filter select {
    padding: 8px 15px;
    border-radius: 5px;
    border: 1px solid #ddd;
    background-color: white;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 1rem;
  }

  #filter select:focus {
    outline: none;
    border-color: #F8B500;
    box-shadow: 0 0 0 2px rgba(248, 181, 0, 0.2);
  }

  /* Charts */
  #graph-area {
    width: 100%;
    padding: 20px;
    margin: 20px 0;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
  }

  #columnchart_material, #columnchart {
    width: 100%;
    height: 500px;
    margin: 0 auto;
  }

  /* Sidebar content */
  .side-nav p {
    text-align: center;
    color: black;
    font-weight: 500;
    padding: 0 20px;
    margin-bottom: 10px;
    font-size: 1.1rem;
  }

  /* No data message */
  #no-data {
    text-align: center;
    padding: 20px;
    color: #666;
    font-style: italic;
  }

  /* Responsive Adjustments */
  @media (max-width: 1200px) {
    .side-nav {
      width: 25%;
      left: -25%;
    }
    #main.open {
      margin-left: 25%;
      width: 75%;
    }
  }

  @media (max-width: 992px) {
    .side-nav {
      width: 30%;
      left: -30%;
    }
    #main.open {
      margin-left: 30%;
      width: 70%;
    }
  }

  @media (max-width: 768px) {
    .side-nav {
      width: 40%;
      left: -40%;
    }
    #main.open {
      margin-left: 40%;
      width: 60%;
    }
    #columnchart_material, #columnchart {
      height: 400px;
    }
  }

  @media (max-width: 576px) {
    .side-nav {
      width: 80%;
      left: -80%;
    }
    #main.open {
      margin-left: 80%;
      width: 100%;
    }
    #graph-area {
      padding: 10px;
    }
    #filter {
      padding: 0 10px;
    }
    .quiz-items {
      height: calc(100vh - 250px);
    }
    #columnchart_material, #columnchart {
      height: 350px;
    }
  }

  @media (max-width: 400px) {
    .side-nav {
      width: 90%;
      left: -90%;
    }
    #main.open {
      margin-left: 90%;
    }
    #openMenu {
      top: 10px;
      left: 10px;
    }
    #filter {
      flex-direction: column;
      align-items: flex-start;
    }
  }

  /* Scrollbar */
  ::-webkit-scrollbar {
    width: 8px;
  }

  ::-webkit-scrollbar-track {
    background: #f1f1f1;
  }

  ::-webkit-scrollbar-thumb {
    background: #F8B500;
    border-radius: 4px;
  }

  /* Sidebar scrollbar */
  .side-nav::-webkit-scrollbar-track {
    background: #34495e;
  }

  .side-nav::-webkit-scrollbar-thumb {
    background: #F8B500;
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

  </div>    

  <div class="side-nav" id="sideNav">
    <center>
      <i class="fa-solid fa-bars" id="closeMenu" onclick="closeNav()"></i>
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
      <?php
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                echo "<input type='hidden' value='" . $row['quiz_id'] . "'>";
                echo "<a style='color: black;'class='quiz-btn' href='t_quiz-item-analysis.php?quiz_id=" . $row['quiz_id'] . "'>" . $row['title'] . "</a>";
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
  
  function openNav() {
    document.querySelector('.side-nav').style.left = '0';
    document.querySelector('#main').style.marginLeft = '20%';
  }

  function closeNav() {
    document.querySelector('.side-nav').style.left = '-20%';
    document.getElementById('main').style.marginLeft = '0';
  }   

  document.addEventListener('DOMContentLoaded', function() {
    closeNav();
  });
</script>


  </body>
</html>