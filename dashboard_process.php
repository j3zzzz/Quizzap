<!-- dashboard.php -->
<?php
session_start();

if (!isset($_SESSION['account_type']) || !isset($_SESSION['account_number'])) {
    header("Location: login.php");
    exit();
}

$account_type = $_SESSION['account_type'];
$account_number = $_SESSION['account_number'];

if ($account_type == 'teacher') {
    header("Location: t_Home.php");
} else {
    header("Location: s_Home.php");
}
?>
