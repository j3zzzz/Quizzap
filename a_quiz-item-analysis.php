<?php
session_start();
if (!isset($_SESSION['account_number']) || strpos($_SESSION['account_number'], 'A') !== 0) {
    header("Location: admin_login.php");
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

$quiz_id = $_GET['quiz_id'];
$subject_id = $_GET['subject_id'] ?? null;
$teacher_id = $_GET['teacher_id'] ?? null;

if (empty($quiz_id)) {
    header("Location: a_item-analysis.php");
    exit();
}

// Fetch quiz details
$quiz_sql = "SELECT q.*, s.subject_name, t.fname as teacher_fname, t.lname as teacher_lname 
             FROM quizzes q 
             LEFT JOIN subjects s ON q.subject_id = s.subject_id 
             LEFT JOIN teachers t ON s.teacher_id = t.account_number 
             WHERE q.quiz_id = ?";
$stmt = $conn->prepare($quiz_sql);
$stmt->bind_param("i", $quiz_id);
$stmt->execute();
$quiz_result = $stmt->get_result();
$quiz_info = $quiz_result->fetch_assoc();
$stmt->close();

// Fetch item analysis data
$analysis_sql = "SELECT qs.question_id, qs.question_text, qs.question_type,
                 COUNT(DISTINCT CASE WHEN sa.is_correct = 1 THEN sa.student_id END) AS correct_count,
                 COUNT(DISTINCT CASE WHEN sa.is_correct = 0 THEN sa.student_id END) AS wrong_count
                 FROM questions qs
                 LEFT JOIN student_answers sa ON qs.question_id = sa.question_id AND sa.quiz_id = ?
                 WHERE qs.quiz_id = ?
                 GROUP BY qs.question_id, qs.question_text, qs.question_type
                 ORDER BY qs.question_id";
$stmt = $conn->prepare($analysis_sql);
$stmt->bind_param("ii", $quiz_id, $quiz_id);
$stmt->execute();
$analysis_result = $stmt->get_result();

$analysis_data = [];
while ($row = $analysis_result->fetch_assoc()) {
    $total = $row['correct_count'] + $row['wrong_count'];
    $percentage = ($total > 0) ? round(($row['correct_count'] / $total) * 100, 2) : 0;
    
    $analysis_data[] = [
        'question_id' => $row['question_id'],
        'question_text' => $row['question_text'],
        'question_type' => $row['question_type'],
        'correct_count' => $row['correct_count'],
        'wrong_count' => $row['wrong_count'],
        'total' => $total,
        'percentage' => $percentage
    ];
}
$stmt->close();

// Fetch student performance data
$students_sql = "SELECT s.account_number, s.fname, s.lname, 
                 AVG(sa.score) as avg_score,
                 COUNT(DISTINCT sa.attempt_id) as attempts,
                 MAX(sa.score) as best_score
                 FROM students s
                 JOIN quiz_attempts sa ON s.account_number = sa.account_number
                 WHERE sa.quiz_id = ?
                 GROUP BY s.account_number, s.fname, s.lname
                 ORDER BY avg_score DESC";
$stmt = $conn->prepare($students_sql);
$stmt->bind_param("i", $quiz_id);
$stmt->execute();
$students_result = $stmt->get_result();
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Quiz Item Analysis</title>
    <link rel="stylesheet" type="text/css" href="other resources/fontawesome-free-6.5.2-web/css/all.min.css">
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <style>
        * {
            font-family: 'Fredoka', sans-serif;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: #f5f5f5;
            color: #333;
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #f8b500;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9rem;
            margin-bottom: 15px;
            text-decoration: none;
        }

        .back-btn:hover {
            background: #e6a400;
        }

        .quiz-info {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .quiz-info h2 {
            color: #333;
            margin-bottom: 10px;
        }

        .quiz-meta {
            display: flex;
            gap: 20px;
            color: #666;
            font-size: 0.9rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .stat-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            border-left: 4px solid #f8b500;
        }

        .stat-box h3 {
            color: #333;
            font-size: 1rem;
            margin-bottom: 5px;
        }

        .stat-box .value {
            font-size: 1.8rem;
            font-weight: bold;
            color: #f8b500;
        }

        .chart-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .chart-container h3 {
            color: #333;
            margin-bottom: 15px;
            border-bottom: 2px solid #f8b500;
            padding-bottom: 8px;
        }

        #questionChart {
            width: 100%;
            height: 400px;
        }

        .tables-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .table-box {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .table-box h3 {
            color: #333;
            margin-bottom: 15px;
            border-bottom: 2px solid #f8b500;
            padding-bottom: 8px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f8b500;
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: 500;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #eee;
        }

        tr:hover {
            background: #f9f9f9;
        }

        .percentage {
            font-weight: bold;
        }

        .percentage.high {
            color: #4CAF50;
        }

        .percentage.medium {
            color: #FF9800;
        }

        .percentage.low {
            color: #F44336;
        }

        .question-text {
            max-width: 300px;
            word-wrap: break-word;
        }

        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            .tables-container {
                grid-template-columns: 1fr;
            }
            
            .quiz-meta {
                flex-direction: column;
                gap: 10px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            #questionChart {
                height: 300px;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .table-box {
                overflow-x: auto;
            }
            
            table {
                font-size: 0.85rem;
            }
            
            th, td {
                padding: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="a_item-analysis.php?teacher_id=<?php echo $teacher_id; ?>&subject_id=<?php echo $subject_id; ?>" class="back-btn">
            <i class="fa-solid fa-arrow-left"></i> Back to Item Analysis
        </a>
        
        <div class="header">
            <h1>Quiz Item Analysis</h1>
            <p>Detailed analysis of quiz questions and student performance</p>
        </div>
        
        <div class="quiz-info">
            <h2><?php echo htmlspecialchars($quiz_info['title']); ?></h2>
            <div class="quiz-meta">
                <span><i class="fa-solid fa-book"></i> Subject: <?php echo htmlspecialchars($quiz_info['subject_name']); ?></span>
                <span><i class="fa-solid fa-chalkboard-teacher"></i> Teacher: <?php echo htmlspecialchars($quiz_info['teacher_fname'] . ' ' . $quiz_info['teacher_lname']); ?></span>
                <span><i class="fa-solid fa-list-check"></i> Type: <?php echo htmlspecialchars($quiz_info['quiz_type']); ?></span>
            </div>
            
            <div class="stats-grid">
                <div class="stat-box">
                    <h3>Total Questions</h3>
                    <div class="value"><?php echo count($analysis_data); ?></div>
                </div>
                <div class="stat-box">
                    <h3>Total Students Attempted</h3>
                    <div class="value">
                        <?php 
                            $total_students = 0;
                            foreach ($analysis_data as $data) {
                                $total_students = max($total_students, $data['total']);
                            }
                            echo $total_students;
                        ?>
                    </div>
                </div>
                <div class="stat-box">
                    <h3>Average Correct Rate</h3>
                    <div class="value">
                        <?php 
                            $total_percentage = 0;
                            foreach ($analysis_data as $data) {
                                $total_percentage += $data['percentage'];
                            }
                            echo count($analysis_data) > 0 ? round($total_percentage / count($analysis_data), 1) . '%' : '0%';
                        ?>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if (!empty($analysis_data)): ?>
            <div class="chart-container">
                <h3>Question Performance Overview</h3>
                <div id="questionChart"></div>
            </div>
            
            <div class="tables-container">
                <div class="table-box">
                    <h3>Question-by-Question Analysis</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Question Type</th>
                                <th>Correct</th>
                                <th>Wrong</th>
                                <th>Total</th>
                                <th>% Correct</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($analysis_data as $index => $data): 
                                $percentage_class = '';
                                if ($data['percentage'] >= 70) $percentage_class = 'high';
                                elseif ($data['percentage'] >= 30) $percentage_class = 'medium';
                                else $percentage_class = 'low';
                            ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td class="question-text" title="<?php echo htmlspecialchars($data['question_text']); ?>">
                                        <?php echo htmlspecialchars(substr($data['question_text'], 0, 50)); ?>...
                                        <br><small><?php echo htmlspecialchars($data['question_type']); ?></small>
                                    </td>
                                    <td><?php echo $data['correct_count']; ?></td>
                                    <td><?php echo $data['wrong_count']; ?></td>
                                    <td><?php echo $data['total']; ?></td>
                                    <td class="percentage <?php echo $percentage_class; ?>">
                                        <?php echo $data['percentage']; ?>%
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="table-box">
                    <h3>Student Performance Ranking</h3>
                    <?php if ($students_result->num_rows > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>Student</th>
                                    <th>Account #</th>
                                    <th>Avg Score</th>
                                    <th>Best Score</th>
                                    <th>Attempts</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $rank = 1;
                                while ($student = $students_result->fetch_assoc()): 
                                ?>
                                    <tr>
                                        <td><?php echo $rank++; ?></td>
                                        <td><?php echo htmlspecialchars($student['fname'] . ' ' . $student['lname']); ?></td>
                                        <td><?php echo htmlspecialchars($student['account_number']); ?></td>
                                        <td><?php echo round($student['avg_score'], 1); ?></td>
                                        <td><?php echo $student['best_score']; ?></td>
                                        <td><?php echo $student['attempts']; ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p style="text-align: center; color: #666; padding: 20px;">
                            No student attempts recorded for this quiz yet.
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div style="background: white; padding: 40px; text-align: center; border-radius: 10px;">
                <i class="fas fa-chart-pie fa-3x" style="color: #ccc; margin-bottom: 15px;"></i>
                <h3 style="color: #666;">No analysis data available</h3>
                <p style="color: #999;">This quiz has no student attempts yet.</p>
            </div>
        <?php endif; ?>
    </div>

    <script type="text/javascript">
        google.charts.load('current', {'packages':['corechart']});
        google.charts.setOnLoadCallback(drawChart);
        
        function drawChart() {
            var data = new google.visualization.DataTable();
            data.addColumn('string', 'Question');
            data.addColumn('number', 'Correct Answers');
            data.addColumn('number', 'Wrong Answers');
            data.addColumn('number', '% Correct');
            
            data.addRows([
                <?php foreach ($analysis_data as $index => $data): ?>
                    ['Q<?php echo $index + 1; ?>', 
                     <?php echo $data['correct_count']; ?>, 
                     <?php echo $data['wrong_count']; ?>,
                     <?php echo $data['percentage']; ?>],
                <?php endforeach; ?>
            ]);
            
            var options = {
                title: 'Question Performance',
                titleTextStyle: {
                    fontSize: 16,
                    bold: true,
                    color: '#333'
                },
                height: 400,
                chartArea: {
                    width: '80%',
                    height: '70%'
                },
                series: {
                    0: {color: '#4CAF50'},
                    1: {color: '#F44336'},
                    2: {
                        type: 'line',
                        color: '#2196F3',
                        targetAxisIndex: 1,
                        lineWidth: 3,
                        pointSize: 6
                    }
                },
                hAxis: {
                    title: 'Questions',
                    slantedText: true,
                    slantedTextAngle: 45
                },
                vAxes: {
                    0: {title: 'Number of Answers', minValue: 0},
                    1: {title: '% Correct', minValue: 0, maxValue: 100}
                },
                legend: {
                    position: 'top'
                }
            };
            
            var chart = new google.visualization.ComboChart(document.getElementById('questionChart'));
            chart.draw(data, options);
        }
        
        window.addEventListener('resize', drawChart);
    </script>
</body>
</html>