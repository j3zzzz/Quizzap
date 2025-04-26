 <?php

session_start();

if (!isset($_SESSION['account_number'])) {
    header("Location: login.php");
    exit;
}

$conn = mysqli_connect("localhost", "root", "", "rawrit");

$subject_id = isset($_GET['subject_id']) ? $_GET['subject_id'] : '';

if(isset($_POST['delete_quiz_btn'])) {
    
    if(isset($_POST['delete_quiz']) && is_array($_POST['delete_quiz'])) {
        $selected_quizID = $_POST['delete_quiz'];
        $extract_id = implode(',' , $selected_quizID);
        $message = "";

        $query = "DELETE FROM quizzes WHERE quiz_id IN($extract_id)";

        $query_run = mysqli_query($conn, $query);



        if($query_run) {
           $_SESSION['status'] = "Selected Item/s Deleted Successfully";
        }
        else
        { 
            $_SESSION['status'] = "Selected Item/s Not Deleted". mysqli_error($conn);
        }
    } else {
         $_SESSION['status'] = "No quiz selected for deletion.";
    }

    header("Location: t_quizDash.php?subject_id=" . $subject_id);
    exit;
}

$mysql_close($conn);
?>

