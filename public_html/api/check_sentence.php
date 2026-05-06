<?php
session_start();

header('Content-Type: application/json');

require_once __DIR__ . '/../../application/service/service.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.',
    ]);
    exit;
}

$answer = isset($_POST['answer']) ? $_POST['answer'] : '';
$result = ftss_check_answer($answer);

echo json_encode($result);
