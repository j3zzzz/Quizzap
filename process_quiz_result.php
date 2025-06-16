<?php
session_start();

// Check if we have quiz result data in POST
if (!isset($_POST['quiz_result'])) {
    // Try to get from sessionStorage via JavaScript
    echo <<<HTML
    <!DOCTYPE html>
    <html>
    <head>
        <title>Processing Quiz Results</title>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const quizResult = sessionStorage.getItem('quizResult');
                if (quizResult) {
                    // Submit the data to this page via POST
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'process_quiz_result.php';
                    
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'quiz_result';
                    input.value = quizResult;
                    
                    form.appendChild(input);
                    document.body.appendChild(form);
                    form.submit();
                } else {
                    // No quiz result found, redirect to select quiz
                    window.location.href = 'select_quiz.php';
                }
            });
        </script>
    </head>
    <body>
        <p>Processing your quiz results...</p>
    </body>
    </html>
    HTML;
    exit();
}

// We have the data via POST
$result_data = json_decode($_POST['quiz_result'], true);

if ($result_data) {
    // Store in PHP session
    $_SESSION['quiz_result'] = $result_data;
    // Clear the sessionStorage
    echo <<<HTML
    <script>
        sessionStorage.removeItem('quizResult');
        window.location.href = 'quiz_result.php';
    </script>
    HTML;
    exit();
} else {
    // Invalid data, redirect to select quiz
    header("Location: select_quiz.php");
    exit();
}