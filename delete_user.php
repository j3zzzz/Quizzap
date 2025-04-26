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

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $account_number = $_POST['account_number'];

    // Check if the student has any related enrollment records
    $sql = "
        SELECT COUNT(*) AS enrollment_count
        FROM enrollments
        WHERE student_id = (
            SELECT student_id
            FROM students
            WHERE account_number = ?
        )
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo "Failed to prepare statement: " . $conn->error;
        exit();
    }

    $stmt->bind_param("s", $account_number);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if ($row['enrollment_count'] > 0) {
        // Delete the enrollment records first
        $sql = "
            DELETE FROM enrollments
            WHERE student_id = (
                SELECT student_id
                FROM students
                WHERE account_number = ?
            )
        ";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            echo "Failed to prepare statement: " . $conn->error;
            exit();
        }

        $stmt->bind_param("s", $account_number);
        if ($stmt->execute()) {
           echo "success";
        } else {    
            ?>
            <script type="text/javascript">
                alert("Failed to prepare statement");
                window.location.href = "t_Students.php";
            </script>
            <?php
        }
    }    
    $stmt->close();
}

$conn->close();
?>