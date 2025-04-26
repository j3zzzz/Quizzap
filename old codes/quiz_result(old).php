<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "rawrit";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$quiz_id = $_GET['quiz_id'];
$score = $_GET['score'];
$total = $_GET['total'];
$wrong_answers = json_decode($_GET['wrong_answers'], true);
$subject_id = isset($_GET['subject_id']) ? $_GET['subject_id'] : null;

$sql = "SELECT * FROM questions WHERE quiz_id = $quiz_id";
$result = $conn->query($sql);

$questions = [];
while ($row = $result->fetch_assoc()) {
    $question_id = $row['question_id'];
    $answers_sql = "SELECT * FROM answers WHERE question_id = $question_id";
    $answers_result = $conn->query($answers_sql);

    $answers = [];
    while ($answer_row = $answers_result->fetch_assoc()) {
        // Clean answer text by removing square brackets and quotes
        $cleaned_answer = preg_replace('/^[\[\]"\']+|[\[\]"\']+$/', '', $answer_row['answer_text']);
        
        // Splitting multiple answers if they exist
        $split_answers = preg_split('/\s*,\s*/', $cleaned_answer);
        foreach ($split_answers as $individual_answer) {
            $clean_individual_answer = preg_replace('/^[\[\]"\']+|[\[\]"\']+$/', '', trim($individual_answer));
            $answer_row['individual_answer'] = $clean_individual_answer;
            $answers[] = $answer_row;
        }
    }

    $row['answers'] = $answers;
    $questions[] = $row;
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

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" type="text/css" href="other resources/fontawesome-free-6.5.2-web/css/all.min.css">
    <title>Quiz Result</title>
    <style type="text/css">
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, Helvetica, sans-serif;
            background-color: #ffffff;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            background-color: white;
        }

        header .logo {
            font-size: 24px;
            font-weight: bold;
            margin-left: 30px;
            margin-top: 3px;
        }

        nav p{
            font-family: Purple Smile;
            color: white;
            font-size: 30px;
            margin-right: 30px;
        }

        .options {
            height: fit-content;
            width: 90%;
            margin: auto;
        }

        #quizzes {
            float: left;
        }

        #rankings {
            float: right;
            margin-right: 5%;
        }

        .container{
            width: 80%;
            background-color: white;
            border-radius: 15px;
            border: 3px solid #E3E2E2;
            box-shadow: 0 4px 8px 0 rgba(0, 0, 0, 0.2), 0 6px 20px 0 rgba(0, 0, 0, 0.19);
            padding: 5%;
            margin-left: 10%;
            margin-top: 5%;
        }

        h1{
            font-family: Purple Smile;
            font-size: 30px;
            color: white;
            letter-spacing: 1px;    
        }

        h2{
            font-family: Tilt Warp Regular;
        }

        a{
            float: left;
            margin-top: 3%;
            margin-left: 5%;
            text-decoration: none;
            font-size: 20px;
            font-family: Tilt Warp Regular;
            color: #605F5F;
        }

        .score{
            float: right;
            color: #f8b500;
            font-family: Tilt Warp Regular;
            font-size: 22px;
            margin-top: -2%;
        }

        .question{
            font-family: Tilt Warp Regular;
        }

        .question p {
            margin-left: -2%;
        }

        .qstn{
            font-size: 22px;
        }

        .qstn-con{
            width: 100%;
            border-radius: 15px;
            border: 2px solid #f8b500;
            padding: 30px;
            margin-bottom: 10px;
        }

        .individual-answer {
            padding: 10px;
            border-radius: 5px;
        }
    </style>
</head>
<body>

    <header>
        <div class="logo"><img src="img/logo1.png" width="200px" height="80px"></div>
        <div class="actions">
            <div class="profile"><img src="img/default.png" width="50px" height="50px"></div>
        </div>
    </header>

    <div class="options">
        <a id="quizzes" href="select_quiz.php?subject_id=<?php echo $subject_id; ?>"><span><i class="fa-solid fa-angle-left"></i> Back to Quizzes</span></a>
        <a id="rankings" href="s_rankings.php?quiz_id=<?php echo $quiz_id; ?>"><span> See Rankings <i class="fa-solid fa-angle-right"></i></span></a>   
    </div>
        
    <br>

    <div class="container">
    <p class="score">Your score: <?php echo $score . " / " . $total; ?></p>

    <h2>Review Questions</h2><br>
    <div id="questions">
        <?php $question_no = 1; ?>
        <?php foreach ($questions as $question): ?>
            <div class="qstn-con">
            <div class="question">
                <p class="qstn"><?php echo $question_no . '.' . ' ' . $question['question_text']; ?></p>
            
            <div class="answers">   
                <?php foreach ($question['answers'] as $answer): ?>
                    <div class="individual-answer">
                    <li>
                        <?php
                        $style = "";
                        if ($answer['is_correct'] == 1) {
                            $style = 'color: green;';
                        } 
                        
                        if (isset($wrong_answers[$question['question_id']]) && $wrong_answers[$question['question_id']] == $answer['answer_id']) {
                            $style = "color: red;";
                        }
                        ?>
                         <span style="<?php echo $style; ?>">
                            <?php echo $answer['individual_answer']; ?>
                        </span>
                        <?php
                        if (isset($wrong_answers[$question['question_id']]) && $wrong_answers[$question['question_id']] == $answer['answer_id']) {
                            echo "<span style='color: red;'> (Your answer)</span>";
                        }
                        ?>
                    </div>
                <?php endforeach; ?>
            </div>    
         </div>
    </div>
    <?php $question_no++; ?>
    <?php endforeach; ?>
</div>
</div>

<br>

</body>
</html>