<?php
header('Content-Type: application/json');

echo json_encode([
    'success' => true,
    'app' => 'Fix These Sentences Streak App',
    'status' => 'ok',
]);
