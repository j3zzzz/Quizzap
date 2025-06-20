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

//to fetch the number of students na nag answer nang correct
$correctWrongCNT = "SELECT q.title, qs.question_text,
    SUM(sa.is_correct = 1) AS correct_count,
    SUM(sa.is_correct = 0) AS wrong_count
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
    $analysis_data[] = $row;
  }
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
    <title>Quiz Item Analysis</title>
    <link rel="stylesheet" type="text/css" href="other resources/fontawesome-free-6.5.2-web/css/all.min.css">
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <script type="text/javascript">
      google.charts.load('current', {'packages':['corechart']});
      google.charts.setOnLoadCallback(drawChart);

      function drawChart() {
        <?php if (!empty($analysis_data)) { 
            foreach ($analysis_data as $index => $data) { ?>
            
            var data<?php echo $index; ?> = google.visualization.arrayToDataTable([
              ['Answer Type', 'Count'],
              ['Correct Answers', <?php echo (int)$data['correct_count']; ?>],
              ['Incorrect Answers', <?php echo (int)$data['wrong_count']; ?>]
            ]);

            var options<?php echo $index; ?> = {
              title: 'Question <?php echo ($index + 1);?>: <?php echo $data['question_text']; ?>',
                titleTextStyle: {
                  fontSize: 25,   
                  bold: false,
                },
              fontName: 'Fredoka',
              colors: ['#F8B500', '#f74400'],
              width: 900,
              height: 500,
              animation: {
                startup: true,  // Animates on chart load
                duration: 2500, // Animation duration in milliseconds
                easing: 'inAndOut',  // Easing function: 'linear', 'in', 'out', 'inAndOut'
              }  
            };

            var chart<?php echo $index; ?> = new google.visualization.PieChart(document.getElementById('piechart<?php echo $index; ?>'));
            chart<?php echo $index; ?>.draw(data<?php echo $index; ?>, options<?php echo $index; ?>);
          <?php }
          } ?>

      }
    </script>

<style type="text/css">

<style type="text/css">

*{
  font-family:Fredoka;
}
  body {
    font-family: Fredoka;
    margin: 0;
    padding: 0;
    background-color: white;
  }

  /* Scroll Bar */
  ::-webkit-scrollbar {
    width: 10px;
    height: 10px;
  }

  ::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 5px;
  }
    
  ::-webkit-scrollbar-thumb {
    background: #F8B500; 
    border-radius: 5px;
  }

  ::-webkit-scrollbar-thumb:hover {
    background: #FCD058; 
  }

  .side-nav {
    position: fixed;
    top: 0;
    left: 0;
    width: 20%;
    height: 100vh;
    background-color: white;
    border-right: 1px solid #ddd;
    box-shadow: 2px 0 5px rgba(0,0,0,0.1);
    z-index: 100;
    padding: 20px;
    box-sizing: border-box;
  }

  @media screen and (max-width: 768px) {
    .side-nav {
      width: 0;
      padding: 0;
      overflow: hidden;
    }
  }

  .side-nav img {
    display: block;
    max-width: 100%;
    height: auto;
    margin: 0 auto 20px;
    cursor: pointer;
  }

  .side-nav p {
    text-align: center;
    font-size: 1.5rem;
    margin: 20px 0;
    color: #333;
  }

  #back {
    display: block;
    text-align: center;
    margin: 20px auto;
    padding: 10px;
    cursor: pointer;
    color: #555;
    transition: color 0.3s;
  }

  #back:hover {
    color: #F8B500;
  }

  #back span {
    font-size: 1rem;
  }

  #hr2 {
    border: none;
    height: 1px;
    background-color: #ddd;
    margin: 20px 0;
  }

  .quiz-items {
    max-height: 60vh;
    overflow-y: auto;
    padding: 10px;
  }

  .quiz-btn {
    display: block;
    background-color: #F8B500;
    color: #000 !important;
    margin: 15px auto;
    padding: 12px;
    border-radius: 8px;
    text-decoration: none;
    text-align: center;
    font-size: 0.9rem;
    cursor: pointer;
    border: 2px solid #F8B500;
    transition: all 0.3s;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    width: 90%;
  }

  .quiz-btn:hover {
    background-color: white !important;
    color: #F8B500 !important;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
  }

  .quiz-btn:active {
    transform: translateY(0);
    box-shadow: 0 2px 3px rgba(0,0,0,0.1);
  }

  .quiz-btn.selected {
    background-color: white !important;
    color: #F8B500 !important;
    font-weight: bold;
    border: 2px solid #F8B500;
  }

  .no-quiz-btn {
    text-align: center;
    padding: 20px;
    color: #666;
  }

  #main {
    margin-left: 20%;
    padding: 20px;
    box-sizing: border-box;
  }

  #title {
    background: white;
    padding: 20px;
    margin-bottom: 20px;
    position: sticky;
    top: 0;
    z-index: 10;
  }

  #item-analysis {
    font-size: 2rem;
    margin: 0 0 10px 0;
    color: #333;
  }

  #hr1 {
    border: none;
    height: 2px;
    background-color: #F8B500;
    margin: 10px 0;
  }

  #quiz-title {
    font-size: 1.5rem;
    color: #555;
    margin: 10px 0;
  }

  #graph-area {
    background-color: white;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-top: 20px;
  }

  .piechart-container {
    margin-bottom: 40px;
    padding: 20px;
    border-bottom: 1px solid #eee;
  }

  .piechart-container:last-child {
    border-bottom: none;
  }

  .no-data {
    background-color: #fff3f3;
    border: 1px solid #ffcccc;
    border-radius: 8px;
    padding: 30px;
    text-align: center;
    color: #d32f2f;
    font-size: 1.2rem;
    margin: 50px auto;
    max-width: 600px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
  }

  /* Responsive adjustments */
  @media (max-width: 1200px) {
    .side-nav {
      width: 25%;
    }
    #main {
      margin-left: 25%;
    }
  }

  @media (max-width: 992px) {
    .side-nav {
      width: 30%;
    }
    #main {
      margin-left: 30%;
    }
  }

  @media (max-width: 768px) {
    .side-nav {
      width: 0;
    }
    #main {
      margin-left: 0;
    }
  }
</style>

  </head>
  <body>

  <div class="side-nav" id="sideNav">
    <center>
      
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
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $is_selected = ($row['quiz_id'] == $quiz_id) ? 'selected' : '';
                echo "<input type='hidden' value='" . $row['quiz_id'] . "'>";
                echo "<a style='color: black;' class='quiz-btn {$is_selected}' href='t_quiz-item-analysis.php?quiz_id=" . $row['quiz_id'] . "'>" . $row['title'] . "</a>";
          }
        } else {
            echo "<div class='no-quiz-btn'>";
            echo "<p>No quizzes created yet.</p>";
            echo "</div>";
        }
        ?>
    </div>
  </div>  

   

  <div id="main">
    <div id="title">
      <h1 id="item-analysis">Item Analysis </h1> 
       <hr id="hr1"> 
      <h3 id="quiz-title"><?php echo $quiz_title; ?></h3> 
    </div>
   
    <br><br><br>

    <div id="graph-area">
      <?php if (!empty($analysis_data)) {
        foreach ($analysis_data as $index => $data) { ?>
          <div id="piechart<?php echo $index; ?>"></div>
        <?php } 
      } else 
          echo "<div class='no-data'>No data found</div>"; ?>
    </div>

  </div>  

  </body>
</html>