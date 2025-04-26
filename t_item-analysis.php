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
           fontName: 'Tilt Warp',
           fontSize: 15,
           height: 500,
           width: 1050,
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
                fontName: 'Tilt Warp',
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
          fontName: 'Tilt Warp',
          fontSize: 15,
           height: 500,
           width: 1050,
          chart: {
              titleTextStyle: {
                fontName: 'Tilt Warp',
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
            fontName: 'Tilt Warp'
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

  body {
    overflow: auto;
    font-family: Tilt Warp;
  }
    
  .buttons {
    margin-left: 30%;
  }

    /* Scroll Bar */
  /* width */
  ::-webkit-scrollbar {
    width: 10px;
    height: 10px;
  }

  /* Track */
  ::-webkit-scrollbar-track {
    box-shadow: inset 0 0 5px grey; 
    border-radius: 5px;
  }
    
  /* Handle */
  ::-webkit-scrollbar-thumb {
    background: #F8B500; 
    border-radius: 5px;
  }

  /* Handle on hover */
  ::-webkit-scrollbar-thumb:hover {
    background: #FCD058; 
  }

  #filter {
    margin-left: 5%;
    width: fit-content;
  }

  select {
    border-radius: 5px;
    font-family: Tilt Warp;
    padding: 2px 5px;
    cursor: pointer;
  }

  .quiz-btn {
    background-color: #F8B500;
    display: block;
    margin: auto;
    margin-block-end: 10%;
    width: 60%;
    padding: 12px 15px;
    padding-bottom: 8px;
    border-radius: 8px;
    box-shadow: 5px 6px 0 0 rgba(0, 0, 0, 0.2);
    text-decoration: none;
    text-align: center;
    color: white;
    cursor: pointer;
  }

  .quiz-btn:hover {
      background-color: #E5D098;
      color: white;
  }

  .quiz-btn:active {
      background-color: #A34404;
      box-shadow: 5px 6px 0 0 rgba(0, 0, 0, 0.3);
  } 

  .no-quiz-btn {
      position: relative;
      text-align: center;
      margin: auto;
      margin-top: 3px;
      padding: 3px 0;
  }

  #columnchart_material, #columnchart {
    margin-top: 0%; 
  }

  #main {
    transition: margin-left .5s;
    margin-left: 0;
  }

  #main.open {
    margin-left: 60%;
  }

  .side-nav {
    align-content: center;
    justify-content: center;
    align-items: center;
    display: inline-block;
    background-color: #FAFAFA;
    border-top-color: transparent;
    position: fixed;
    border: 1px solid #ccc;
    float: left;
    top: 0;
    left: -20%;
    width: 20%;
    height: 100%;
    z-index: 4;
    transition: 0.5s;
  }

  @media screen and (max-width: 768px) {
    .side-nav {
      width: 0;
      position: fixed;
    }
  }

  #closeMenu {
    position: absolute;
    background-color: #F8B500;
    padding: 8px 8px;
    margin-top: 5%;
    right: 25px;
    float: right;
    border-radius: 5px;
    z-index: 3;
    cursor: pointer;
    transition: 0.3s;
  }

  .side-nav.open {
    width: 60%;
  }

  #closeMenu:hover, #openMenu:hover {
    background-color: black;
    color: #F8B500;
  }

  #openMenu {
    z-index: 1;
    position: absolute;
    top: 2%;
    background-color: #F8B500;
    float: left;
    padding: 8px 8px;
    border-radius: 5px;
    transition: 0.3s;
    cursor: pointer;
  }

  .sum {
    width: 90%;
    position: relative;
    margin-top: 2%;
    margin-bottom: 2%;
    left: 5%;
    overflow: auto;
  }

  #hr1 {
    background-color: #F8B500; 
    height: 2px; 
    border: none;
    margin-top: -2%;
    width: 90%;
    margin-left: 5%;
    align-self: center;
  }

  #hr2 {
    background-color: #F8B500; 
    height: 2px; 
    border: none;
    width: 90%;
  }

  #back {
    cursor: pointer; 
    width: fit-content;
    margin: auto;
    margin-bottom: -5%;
  }

  span {
    font-size: 20px;
  }

  .side-nav img {
    padding: -1px 10px;
    cursor: pointer;
    width: 85%;
    height: 15%;
    z-index: 2;
    margin-top: 10%;
    margin-bottom: -1%;
  }

  #logo {
    position: relative;
    z-index: 2;
  }

  .side-nav p{
    text-align: center;
    font-size: 30px;
    margin-bottom: -25%;
    padding: 5px 10px;
  }


  .quiz-items {
    position: relative;
    overflow: auto;
    margin-top: 25%;
    left: 0;
    width: 92%;
    height: 70%;
    float: left;
    padding: 0px 10px;
  }

  #no-data {
    position: relative;
    width: fit-content;
    color:  black;
    background-color: #F8B500;
    padding: 20px 20px;
    border: 2px solid black;
    border-radius: 20px;
    margin-top: 2%;
    margin-left: auto;
    margin-right: auto;
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
      <label for="filters">Filter:</label>
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
