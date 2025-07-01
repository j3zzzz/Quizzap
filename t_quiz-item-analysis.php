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
    
    // Get student details for correct answers - using account_number instead of student_number
    $correct_students_sql = "SELECT s.account_number, s.fname, s.lname 
                            FROM student_answers sa
                            JOIN students s ON sa.student_id = s.student_id
                            WHERE sa.quiz_id = ? AND sa.question_id = ? AND sa.is_correct = 1";
    $correct_stmt = $conn->prepare($correct_students_sql);
    $correct_stmt->bind_param("ii", $quiz_id, $row['question_id']);
    $correct_stmt->execute();
    $correct_students = $correct_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $correct_stmt->close();
    
    // Get student details for wrong answers - using account_number instead of student_number
    $wrong_students_sql = "SELECT s.account_number, s.fname, s.lname 
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
      
      function toggleDetails(questionIndex) {
        const details = document.getElementById(`details-${questionIndex}`);
        const btn = document.getElementById(`toggle-btn-${questionIndex}`);
        if (details.style.display === 'none') {
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
          drawChart(); // Redraw charts when switching back to chart view
        } else {
          document.getElementById('chart-view').style.display = 'none';
          document.getElementById('table-view').style.display = 'block';
          document.getElementById('chart-btn').classList.remove('active-view');
          document.getElementById('table-btn').classList.add('active-view');
        }
      }
    </script>

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
  
  .details-btn {
    background-color: #F8B500;
    color: #000;
    border: none;
    padding: 8px 15px;
    border-radius: 5px;
    cursor: pointer;
    font-family: Fredoka;
    margin: 10px 0;
    transition: all 0.3s;
  }
  
  .details-btn:hover {
    background-color: #FCD058;
  }
  
  .details-container {
    display: none;
    margin-top: 20px;
    padding: 15px;
    background-color: #f9f9f9;
    border-radius: 8px;
    border-left: 4px solid #F8B500;
    height: 400px;
    overflow: auto;
  }

  .details-container h4{
    font-weight: 500;
  }
  
  .student-list {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    margin-top: 10px;
  }
  
  .student-group {
    flex: 1;
    min-width: 300px;
  }
  
  .student-group h4 {
    margin-bottom: 10px;
    color: #333;
    border-bottom: 2px solid #F8B500;
    padding-bottom: 5px;
  }
  
  .student-item {
    background-color: white;
    padding: 10px;
    margin-bottom: 8px;
    border-radius: 5px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
  }
  
  .student-item.correct {
    border-left: 3px solid #4CAF50;
  }
  
  .student-item.wrong {
    border-left: 3px solid #f44336;
  }
  
  .student-name {
    font-weight: bold;
  }
  
  .student-id {
    color: #666;
    font-size: 0.9em;
  }

  /* View toggle buttons */
  .view-toggle {
    display: flex;
    justify-content: center;
    margin-bottom: 20px;
  }
  
  .view-btn {
    padding: 10px 20px;
    background-color: #f1f1f1;
    border: none;
    cursor: pointer;
    font-family: Fredoka;
    font-size: 1rem;
    transition: all 0.3s;
  }
  
  .view-btn:first-child {
    border-radius: 5px 0 0 5px;
  }
  
  .view-btn:last-child {
    border-radius: 0 5px 5px 0;
  }
  
  .view-btn:hover {
    background-color: #ddd;
  }
  
  .active-view {
    background-color: #F8B500 !important;
    color: #000;
    font-weight: bold;
  }
  
  /* Table view styles */
  #table-view {
    display: none;
    width: 100%;
    overflow-x: auto;
  }
  
  .analysis-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
  }
  
  .analysis-table th, 
  .analysis-table td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid #ddd;
  }
  
  .analysis-table th {
    background-color: #F8B500;
    color: #000;
    font-weight: bold;
  }
  
  .analysis-table tr:nth-child(even) {
    background-color: #f9f9f9;
  }
  
  .analysis-table tr:hover {
    background-color: #f5f5f5;
  }
  
  .percentage-cell {
    font-weight: bold;
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
    max-width: 400px;
    word-wrap: break-word;
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
    
    .student-group {
      min-width: 100%;
    }
    
    .analysis-table {
      font-size: 0.9rem;
    }
    
    .analysis-table th, 
    .analysis-table td {
      padding: 8px 10px;
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
              <div id="piechart<?php echo $index; ?>"></div>
              
              <button id="toggle-btn-<?php echo $index; ?>" class="details-btn" onclick="toggleDetails(<?php echo $index; ?>)">
                <i class="fa-solid fa-eye"></i> Show Details
              </button>
              
              <div id="details-<?php echo $index; ?>" class="details-container" style="display: none;">
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
      <?php } else {
        echo "<div class='no-data'>No data found</div>";
      } ?>
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
      <?php } else {
        echo "<div class='no-data'>No data found</div>";
      } ?>
    </div>

  </div>  

  </body>
</html>