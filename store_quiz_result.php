<?php
session_start();

$data = json_decode(file_get_contents('php://input'), true);
if ($data) {
    $_SESSION['quiz_result'] = $data;
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false]);
}