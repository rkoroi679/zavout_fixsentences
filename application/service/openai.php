<?php

function ftss_openai_is_available()
{
    $config = ftss_read_app_config();
    $api_key_env = isset($config['openai']['api_key_env']) ? trim((string) $config['openai']['api_key_env']) : 'OPENAI_API_KEY';

    return ftss_read_env($api_key_env) !== '';
}

function ftss_openai_generate_challenge($previous_incorrect = '')
{
    $config = ftss_read_app_config();
    $messages = [
        [
            'role' => 'system',
            'content' => 'You create short English grammar correction exercises. Return only JSON that matches the provided schema.',
        ],
        [
            'role' => 'user',
            'content' => implode("\n", [
                'Generate one sentence-fixing exercise for an English learner.',
                'Requirements:',
                '- The incorrect sentence must contain exactly one or two realistic grammar mistakes.',
                '- The corrected sentence must preserve the original meaning.',
                '- The hint must be short and specific.',
                '- Keep both sentences under 120 characters.',
                '- Avoid profanity, politics, and copyrighted text.',
                $previous_incorrect !== '' ? 'Do not reuse this incorrect sentence: ' . $previous_incorrect : 'Make the exercise distinct from common textbook examples.',
            ]),
        ],
    ];

    $schema = [
        'type' => 'json_schema',
        'json_schema' => [
            'name' => 'grammar_challenge',
            'strict' => true,
            'schema' => [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['incorrect', 'correct', 'hint'],
                'properties' => [
                    'incorrect' => [
                        'type' => 'string',
                    ],
                    'correct' => [
                        'type' => 'string',
                    ],
                    'hint' => [
                        'type' => 'string',
                    ],
                ],
            ],
        ],
    ];

    $result = ftss_openai_chat_completion($messages, $schema, (float) $config['openai']['generation_temperature']);

    if ($result === null) {
        return null;
    }

    $challenge = ftss_prepare_challenge([
        'id' => uniqid('oa_', true),
        'source' => 'openai',
        'incorrect' => isset($result['incorrect']) ? $result['incorrect'] : '',
        'correct' => isset($result['correct']) ? $result['correct'] : '',
        'hint' => isset($result['hint']) ? $result['hint'] : '',
    ]);

    if ($challenge === null) {
        return null;
    }

    return $challenge;
}

function ftss_openai_grade_challenge_answer($challenge, $answer)
{
    $config = ftss_read_app_config();
    $messages = [
        [
            'role' => 'system',
            'content' => 'You evaluate English grammar correction answers. Return only JSON that matches the provided schema.',
        ],
        [
            'role' => 'user',
            'content' => implode("\n", [
                'Evaluate whether the submitted answer correctly fixes the sentence.',
                'Rules:',
                '- Accept minor punctuation or capitalization differences.',
                '- Require the grammar to be correct.',
                '- Preserve the original meaning as closely as possible.',
                '- Reject empty answers.',
                '- Reject answers that introduce new facts or materially rewrite the sentence.',
                'Incorrect sentence: ' . (string) $challenge['incorrect'],
                'Ideal corrected sentence: ' . (string) $challenge['correct'],
                'Submitted answer: ' . (string) $answer,
            ]),
        ],
    ];

    $schema = [
        'type' => 'json_schema',
        'json_schema' => [
            'name' => 'grammar_grade',
            'strict' => true,
            'schema' => [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['is_correct', 'correct_answer', 'feedback'],
                'properties' => [
                    'is_correct' => [
                        'type' => 'boolean',
                    ],
                    'correct_answer' => [
                        'type' => 'string',
                    ],
                    'feedback' => [
                        'type' => 'string',
                    ],
                ],
            ],
        ],
    ];

    $result = ftss_openai_chat_completion($messages, $schema, (float) $config['openai']['grading_temperature']);

    if ($result === null) {
        return null;
    }

    if (!isset($result['is_correct']) || !is_bool($result['is_correct'])) {
        return null;
    }

    return [
        'is_correct' => $result['is_correct'],
        'correct_answer' => isset($result['correct_answer']) ? trim((string) $result['correct_answer']) : '',
        'feedback' => isset($result['feedback']) ? trim((string) $result['feedback']) : '',
    ];
}

function ftss_openai_chat_completion($messages, $response_format, $temperature)
{
    if (!ftss_openai_is_available()) {
        return null;
    }

    $config = ftss_read_app_config();
    $api_key = ftss_read_env($config['openai']['api_key_env']);

    if ($api_key === '') {
        return null;
    }

    $payload = [
        'model' => (string) $config['openai']['model'],
        'messages' => $messages,
        'temperature' => $temperature,
        'response_format' => $response_format,
    ];

    $response = ftss_http_post_json(
        $config['openai']['base_url'] . '/chat/completions',
        $payload,
        [
            'Authorization: Bearer ' . $api_key,
            'Content-Type: application/json',
        ],
        (int) $config['openai']['timeout_seconds']
    );

    if ($response === null || !isset($response['choices'][0]['message']['content'])) {
        return null;
    }

    $content = $response['choices'][0]['message']['content'];

    if (is_array($content)) {
        $parts = [];

        foreach ($content as $item) {
            if (isset($item['text']) && is_string($item['text'])) {
                $parts[] = $item['text'];
            }
        }

        $content = implode('', $parts);
    }

    if (!is_string($content) || trim($content) === '') {
        return null;
    }

    $decoded = json_decode($content, true);

    if (!is_array($decoded)) {
        return null;
    }

    return $decoded;
}

function ftss_http_post_json($url, $payload, $headers, $timeout_seconds)
{
    $body = json_encode($payload);

    if ($body === false) {
        return null;
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout_seconds);

        $raw_response = curl_exec($ch);
        $status_code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $curl_error = curl_error($ch);

        curl_close($ch);

        if ($raw_response === false || $status_code < 200 || $status_code >= 300 || $curl_error !== '') {
            return null;
        }

        $decoded = json_decode($raw_response, true);

        return is_array($decoded) ? $decoded : null;
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headers),
            'content' => $body,
            'timeout' => $timeout_seconds,
            'ignore_errors' => true,
        ],
    ]);

    $raw_response = @file_get_contents($url, false, $context);

    if ($raw_response === false) {
        return null;
    }

    if (!isset($http_response_header) || !is_array($http_response_header)) {
        return null;
    }

    $status_line = isset($http_response_header[0]) ? (string) $http_response_header[0] : '';

    if (strpos($status_line, ' 2') === false) {
        return null;
    }

    $decoded = json_decode($raw_response, true);

    return is_array($decoded) ? $decoded : null;
}
