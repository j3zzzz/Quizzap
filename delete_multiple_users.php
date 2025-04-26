<?php
session_start();

// Verify teacher is logged in
if (strpos($_SESSION['account_number'], 'T') !== 0) {
    echo "Unauthorized access";
    exit();
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "rawrit";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get account numbers to delete
if (!isset($_POST['account_numbers'])) {
    echo "No students specified";
    exit();
}

$account_numbers = explode(',', $_POST['account_numbers']);

// Sanitize and prepare account numbers
$placeholders = implode(',', array_fill(0, count($account_numbers), '?'));
$types = str_repeat('s', count($account_numbers));

// Begin transaction for data integrity
$conn->begin_transaction();

try {
    // Delete from enrollments first (foreign key constraint)
    $delete_enrollments_sql = "DELETE FROM enrollments WHERE student_id IN (SELECT student_id FROM students WHERE account_number IN ($placeholders))";
    $stmt_enrollments = $conn->prepare($delete_enrollments_sql);
    $stmt_enrollments->bind_param($types, ...$account_numbers);
    $stmt_enrollments->execute();

    // Delete from students
    //$delete_students_sql = "DELETE FROM students WHERE account_number IN ($placeholders)";
    //$stmt_students = $conn->prepare($delete_students_sql);
    //$stmt_students->bind_param($types, ...$account_numbers);
    //$stmt_students->execute();

    $conn->commit();
    echo "success";
} catch (Exception $e) {
    $conn->rollback();
    echo "Error: " . $e->getMessage();
} finally {
    $stmt_enrollments->close();
    //$stmt_students->close();
    $conn->close();
}
?>