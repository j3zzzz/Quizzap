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
              fontName: 'Tilt Warp',
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

  body {
    font-family: 'Tilt Warp';
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
    left: 0;
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

  #back {
    cursor: pointer; 
    width: fit-content;
    margin: auto;
    margin-bottom: -5%;
  }

  span {
    font-size: 20px;
  }

  #hr1 {
    background-color: #F8B500; 
    height: 2px; 
    border: none;
    top: 80%;
    width: 76%;
    margin-left: 2%;
    align-self: center;
    position: absolute;
  }

  #hr2 {
    background-color: #F8B500; 
    height: 2px; 
    border: none;
    width: 90%;
  }

  .quiz-items {
    position: relative;
    overflow: auto;
    margin-top: 25%;
    left: 0;
    width: 92%;
    height: 60%;
    float: left;
    padding: 0px 10px;
  }

  .quiz-btn {
    background-color: #F8B500;
    color: black !important;
    display: block;
    margin: auto;
    margin-block-end: 10%;
    width: 60%;
    padding: 12px 15px;
    padding-bottom: 8px;
    border-radius: 8px;
    text-decoration: none;
    text-align: center;
    line-height: 1;
    cursor: pointer;
    box-shadow: 5px 6px 0 0 rgba(0, 0, 0, 0.2);
    border: 2px solid #F8B500;
  }

  .quiz-btn:hover {
      background-color: white !important;
      color: #F8B500 !important;
  }

  .quiz-btn:active {
      background-color: #F8B500;
      color: white !important;
  } 

  .quiz-btn.selected {
    background-color: white;
    color: #F8B500 !important;
  }

  .no-quiz-btn {
      position: relative;
      text-align: center;
      margin: auto;
      margin-top: 3px;
      padding: 3px 0;
  }

  #main {
    margin-left: 20%;
    top: 0;
    
  }

  #title {
    position: fixed;
    background-color: white;
    width: 100%;
    z-index: 3;
    top: 0 !important;
  }

  #item-analysis {
    float: left;
    font-size: 40px;
    margin-left: 2%;
  }  

  #quiz-title {
    position: absolute;
    background-color: white;
    font-size: 35px;
    top: 65%;
    float: left;
    left: 4%;
    padding: 5px 0;
    line-height: 1;
    width: 100%;
    height: fit-content;
  }

  .filters {
    float: right;
  }

  #filters {
    border: 1px solid black;
    width: fit-content;

  }

  #graph-area {
    margin-top: 10%;
    padding: 10px;
    width: 100px;
    border-radius: 10px !important;
  }

  #piechart {
    margin: auto;
  }

  .no-data {
    border: 5px solid red;
    border-radius: 10px;
    padding: 20px 20px;
    text-align: center;
    position: relative;
    top: 200px !important;
    left: 150%;
    width: fit-content;
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