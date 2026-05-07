<?php

function ftss_database_connection()
{
    static $connection = false;
    static $attempted = false;

    if ($attempted) {
        return $connection instanceof PDO ? $connection : null;
    }

    $attempted = true;

    if (!class_exists('PDO') || !defined('PDO::ATTR_ERRMODE')) {
        return null;
    }

    $config = ftss_read_app_config();
    $database = isset($config['database']) && is_array($config['database']) ? $config['database'] : [];
    $host = isset($database['host']) ? trim((string) $database['host']) : '';
    $port = isset($database['port']) ? (int) $database['port'] : 3306;
    $name = isset($database['name']) ? trim((string) $database['name']) : '';
    $user = isset($database['user']) ? trim((string) $database['user']) : '';
    $pass = isset($database['pass']) ? (string) $database['pass'] : '';
    $charset = isset($database['charset']) ? trim((string) $database['charset']) : 'utf8mb4';
    $timeout = isset($database['connect_timeout_seconds']) ? (int) $database['connect_timeout_seconds'] : 3;

    if ($host === '' || $name === '' || $user === '') {
        return null;
    }

    try {
        $connection = new PDO(
            sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $name, $charset),
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT => max(1, $timeout),
            ]
        );
    } catch (Throwable $exception) {
        $connection = null;
    }

    return $connection instanceof PDO ? $connection : null;
}

function ftss_database_is_available()
{
    return ftss_database_connection() instanceof PDO;
}

function ftss_database_fetch_enabled_templates()
{
    $connection = ftss_database_connection();

    if (!$connection instanceof PDO) {
        return [];
    }

    try {
        $statement = $connection->query(
            'SELECT id, slug, grammar_topic, incorrect_template, correct_template, hint_template, slots_json, answer_variants_json, difficulty, weight
            FROM challenge_templates
            WHERE enabled = 1'
        );
        $rows = $statement->fetchAll();
    } catch (Throwable $exception) {
        return [];
    }

    if (!is_array($rows)) {
        return [];
    }

    $templates = [];

    foreach ($rows as $row) {
        $template = ftss_database_prepare_template($row);

        if ($template !== null) {
            $templates[] = $template;
        }
    }

    return $templates;
}

function ftss_database_prepare_template($row)
{
    if (!is_array($row)) {
        return null;
    }

    $incorrect_template = isset($row['incorrect_template']) ? trim((string) $row['incorrect_template']) : '';
    $correct_template = isset($row['correct_template']) ? trim((string) $row['correct_template']) : '';
    $hint_template = isset($row['hint_template']) ? trim((string) $row['hint_template']) : '';

    if ($incorrect_template === '' || $correct_template === '' || $hint_template === '') {
        return null;
    }

    $slots = ftss_database_decode_slots(isset($row['slots_json']) ? $row['slots_json'] : '');

    $answer_variants = ftss_database_decode_variants(isset($row['answer_variants_json']) ? $row['answer_variants_json'] : null);
    $placeholders = array_unique(array_merge(
        ftss_database_extract_placeholders($incorrect_template),
        ftss_database_extract_placeholders($correct_template),
        ftss_database_extract_placeholders($hint_template),
        ftss_database_extract_placeholders_from_variants($answer_variants)
    ));

    foreach ($placeholders as $placeholder) {
        if (!isset($slots[$placeholder]) || !is_array($slots[$placeholder]) || !$slots[$placeholder]) {
            return null;
        }
    }

    return [
        'id' => (int) (isset($row['id']) ? $row['id'] : 0),
        'slug' => isset($row['slug']) ? trim((string) $row['slug']) : '',
        'grammar_topic' => isset($row['grammar_topic']) ? trim((string) $row['grammar_topic']) : '',
        'incorrect_template' => $incorrect_template,
        'correct_template' => $correct_template,
        'hint_template' => $hint_template,
        'slots' => $slots,
        'answer_variants' => $answer_variants,
        'difficulty' => max(1, (int) (isset($row['difficulty']) ? $row['difficulty'] : 1)),
        'weight' => max(1, (int) (isset($row['weight']) ? $row['weight'] : 1)),
    ];
}

function ftss_database_decode_slots($json)
{
    if (!is_string($json) || trim($json) === '') {
        return [];
    }

    $decoded = json_decode($json, true);

    if (!is_array($decoded)) {
        return [];
    }

    $slots = [];

    foreach ($decoded as $key => $values) {
        $slot_name = trim((string) $key);

        if ($slot_name === '' || !is_array($values)) {
            continue;
        }

        $prepared_values = [];

        foreach ($values as $value) {
            $prepared_value = trim((string) $value);

            if ($prepared_value !== '') {
                $prepared_values[] = $prepared_value;
            }
        }

        $prepared_values = array_values(array_unique($prepared_values));

        if ($prepared_values) {
            $slots[$slot_name] = $prepared_values;
        }
    }

    return $slots;
}

function ftss_database_decode_variants($json)
{
    if (!is_string($json) || trim($json) === '') {
        return [];
    }

    $decoded = json_decode($json, true);

    if (!is_array($decoded)) {
        return [];
    }

    $variants = [];

    foreach ($decoded as $variant) {
        $prepared_variant = trim((string) $variant);

        if ($prepared_variant !== '') {
            $variants[] = $prepared_variant;
        }
    }

    return array_values(array_unique($variants));
}

function ftss_database_extract_placeholders($template)
{
    if (!is_string($template) || $template === '') {
        return [];
    }

    preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $template, $matches);

    return isset($matches[1]) && is_array($matches[1]) ? array_values(array_unique($matches[1])) : [];
}

function ftss_database_extract_placeholders_from_variants($variants)
{
    $placeholders = [];

    foreach ($variants as $variant) {
        $placeholders = array_merge($placeholders, ftss_database_extract_placeholders($variant));
    }

    return array_values(array_unique($placeholders));
}

function ftss_database_pick_weighted_template($templates, $excluded_template_id = null)
{
    if (!is_array($templates) || !$templates) {
        return null;
    }

    $eligible_templates = [];

    foreach ($templates as $template) {
        if ($excluded_template_id !== null && count($templates) > 1 && (int) $template['id'] === (int) $excluded_template_id) {
            continue;
        }

        $eligible_templates[] = $template;
    }

    if (!$eligible_templates) {
        $eligible_templates = $templates;
    }

    $total_weight = 0;

    foreach ($eligible_templates as $template) {
        $total_weight += max(1, (int) $template['weight']);
    }

    if ($total_weight <= 0) {
        return $eligible_templates[array_rand($eligible_templates)];
    }

    $target = random_int(1, $total_weight);
    $running_weight = 0;

    foreach ($eligible_templates as $template) {
        $running_weight += max(1, (int) $template['weight']);

        if ($target <= $running_weight) {
            return $template;
        }
    }

    return $eligible_templates[array_rand($eligible_templates)];
}

function ftss_database_render_template_text($template, $values)
{
    $replacements = [];

    foreach ($values as $key => $value) {
        $replacements['{' . $key . '}'] = $value;
    }

    return trim(strtr((string) $template, $replacements));
}

function ftss_database_normalize_variants($answers)
{
    $prepared_answers = [];
    $seen = [];

    foreach ($answers as $answer) {
        $prepared_answer = trim((string) $answer);

        if ($prepared_answer === '') {
            continue;
        }

        $normalized = ftss_normalize_database_answer($prepared_answer);

        if ($normalized === '' || isset($seen[$normalized])) {
            continue;
        }

        $seen[$normalized] = true;
        $prepared_answers[] = $prepared_answer;
    }

    return $prepared_answers;
}

function ftss_database_render_challenge_from_template($template)
{
    if (!is_array($template) || !isset($template['slots']) || !is_array($template['slots'])) {
        return null;
    }

    $values = [];

    foreach ($template['slots'] as $slot_name => $slot_values) {
        if (!is_array($slot_values) || !$slot_values) {
            return null;
        }

        $values[$slot_name] = $slot_values[array_rand($slot_values)];
    }

    $incorrect = ftss_database_render_template_text($template['incorrect_template'], $values);
    $correct = ftss_database_render_template_text($template['correct_template'], $values);
    $hint = ftss_database_render_template_text($template['hint_template'], $values);

    if ($incorrect === '' || $correct === '' || $hint === '') {
        return null;
    }

    $accepted_answers = [$correct];

    foreach ($template['answer_variants'] as $variant) {
        $accepted_answers[] = ftss_database_render_template_text($variant, $values);
    }

    $accepted_answers = ftss_database_normalize_variants($accepted_answers);

    if (!$accepted_answers) {
        return null;
    }

    return ftss_prepare_challenge([
        'id' => uniqid('db_', true),
        'source' => 'database',
        'template_id' => (int) $template['id'],
        'slug' => $template['slug'],
        'grammar_topic' => $template['grammar_topic'],
        'incorrect' => $incorrect,
        'correct' => $correct,
        'hint' => $hint,
        'accepted_answers' => $accepted_answers,
    ]);
}

function ftss_generate_database_challenge($previous_challenge = null)
{
    $templates = ftss_database_fetch_enabled_templates();

    if (!$templates) {
        return null;
    }

    $excluded_template_id = null;
    $previous_incorrect = '';

    if (is_array($previous_challenge)) {
        if (isset($previous_challenge['source']) && $previous_challenge['source'] === 'database' && isset($previous_challenge['template_id'])) {
            $excluded_template_id = (int) $previous_challenge['template_id'];
        }

        if (isset($previous_challenge['incorrect'])) {
            $previous_incorrect = trim((string) $previous_challenge['incorrect']);
        }
    }

    $attempts = max(5, min(20, count($templates) * 3));
    $fallback_challenge = null;

    for ($attempt = 0; $attempt < $attempts; $attempt++) {
        $template = ftss_database_pick_weighted_template($templates, $excluded_template_id);

        if ($template === null) {
            break;
        }

        $challenge = ftss_database_render_challenge_from_template($template);

        if ($challenge === null) {
            continue;
        }

        if ($fallback_challenge === null) {
            $fallback_challenge = $challenge;
        }

        if ($previous_incorrect !== '' && count($templates) > 1 && $challenge['incorrect'] === $previous_incorrect) {
            continue;
        }

        return $challenge;
    }

    return $fallback_challenge;
}
