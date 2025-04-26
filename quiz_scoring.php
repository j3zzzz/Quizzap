<?php
function scoreEnumerationQuestion($answersJson, $expectedCount, $userAnswers) {
    $expectedAnswers = json_decode($answersJson, true);
    
    // Trim and convert to lowercase for case-insensitive matching
    $normalizedExpectedAnswers = array_map(function($answer) {
        return trim(strtolower($answer));
    }, $expectedAnswers);
    
    $correctCount = 0;
    
    // Normalize user answers
    $normalizedUserAnswers = array_map(function($answer) {
        return trim(strtolower($answer));
    }, $userAnswers);
    
    // Count correct answers
    foreach ($normalizedUserAnswers as $userAnswer) {
        if (in_array($userAnswer, $normalizedExpectedAnswers)) {
            $correctCount++;
        }
    }
    
    // Ensure score doesn't exceed total expected answers
    return min($correctCount, $expectedCount);
}

// You might also include other scoring functions for different question types
function scoreMultipleChoiceQuestion($questionId, $userAnswer) {
    // Logic for scoring multiple choice questions
}

// Add more scoring functions as needed
?>