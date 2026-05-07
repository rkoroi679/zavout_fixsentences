<?php
require_once __DIR__ . '/../../application/service/service.php';

header('Content-Type: application/json');

$runtime = ftss_read_runtime_status();

echo json_encode([
    'success' => true,
    'app' => 'Fix These Sentences Streak App',
    'status' => 'ok',
    'challenge_provider' => $runtime['challenge_provider'],
    'grading_provider' => $runtime['grading_provider'],
    'openai_available' => $runtime['openai_available'],
    'database_available' => $runtime['database_available'],
    'active_fallback_order' => $runtime['active_fallback_order'],
]);
