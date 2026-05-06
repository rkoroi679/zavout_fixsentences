<?php

require_once __DIR__ . '/../bootstrap/env.php';
require_once __DIR__ . '/openai.php';

function ftss_read_app_config()
{
    static $config = null;

    if ($config === null) {
        $config = require __DIR__ . '/../config/app.php';
    }

    return $config;
}

function ftss_read_env($name, $default = '')
{
    $value = getenv((string) $name);

    return $value === false ? $default : (string) $value;
}

function ftss_read_sentences()
{
    $config = ftss_read_app_config();

    return isset($config['sentences']) && is_array($config['sentences']) ? $config['sentences'] : [];
}

function ftss_read_provider($key, $default = 'static')
{
    $config = ftss_read_app_config();

    if (!isset($config[$key])) {
        return $default;
    }

    return strtolower(trim((string) $config[$key]));
}

function ftss_normalize_sentence($sentence)
{
    $sentence = trim((string) $sentence);
    $sentence = preg_replace('/\s+/', ' ', $sentence);

    return mb_strtolower($sentence);
}

function ftss_prepare_challenge($challenge)
{
    if (!is_array($challenge)) {
        return null;
    }

    $incorrect = isset($challenge['incorrect']) ? trim((string) $challenge['incorrect']) : '';
    $correct = isset($challenge['correct']) ? trim((string) $challenge['correct']) : '';
    $hint = isset($challenge['hint']) ? trim((string) $challenge['hint']) : '';

    if ($incorrect === '' || $correct === '' || $hint === '') {
        return null;
    }

    return [
        'id' => isset($challenge['id']) ? (string) $challenge['id'] : uniqid('challenge_', true),
        'source' => isset($challenge['source']) ? trim((string) $challenge['source']) : 'static',
        'incorrect' => $incorrect,
        'correct' => $correct,
        'hint' => $hint,
    ];
}

function ftss_pick_next_sentence_id($exclude_id = null)
{
    $sentences = ftss_read_sentences();
    $eligible_ids = [];

    foreach ($sentences as $sentence) {
        if ($exclude_id !== null && (int) $sentence['id'] === (int) $exclude_id) {
            continue;
        }

        $eligible_ids[] = (int) $sentence['id'];
    }

    if (!$eligible_ids) {
        return $exclude_id === null ? null : (int) $exclude_id;
    }

    return $eligible_ids[array_rand($eligible_ids)];
}

function ftss_read_static_sentence_by_id($sentence_id)
{
    $sentences = ftss_read_sentences();

    foreach ($sentences as $sentence) {
        if ((int) $sentence['id'] === (int) $sentence_id) {
            return $sentence;
        }
    }

    return null;
}

function ftss_build_static_challenge($sentence)
{
    return ftss_prepare_challenge([
        'id' => isset($sentence['id']) ? (string) $sentence['id'] : uniqid('static_', true),
        'source' => 'static',
        'incorrect' => isset($sentence['incorrect']) ? $sentence['incorrect'] : '',
        'correct' => isset($sentence['correct']) ? $sentence['correct'] : '',
        'hint' => isset($sentence['hint']) ? $sentence['hint'] : '',
    ]);
}

function ftss_generate_static_challenge($exclude_challenge = null)
{
    $exclude_id = null;

    if (is_array($exclude_challenge) && isset($exclude_challenge['source'], $exclude_challenge['id']) && $exclude_challenge['source'] === 'static') {
        $exclude_id = (int) $exclude_challenge['id'];
    }

    $next_id = ftss_pick_next_sentence_id($exclude_id);

    if ($next_id === null) {
        return null;
    }

    $sentence = ftss_read_static_sentence_by_id($next_id);

    return $sentence === null ? null : ftss_build_static_challenge($sentence);
}

function ftss_should_generate_with_openai()
{
    return ftss_read_provider('challenge_provider') === 'openai' && ftss_openai_is_available();
}

function ftss_should_grade_with_openai()
{
    return ftss_read_provider('grading_provider') === 'openai' && ftss_openai_is_available();
}

function ftss_generate_next_challenge($previous_challenge = null)
{
    if (ftss_should_generate_with_openai()) {
        $previous_incorrect = is_array($previous_challenge) && isset($previous_challenge['incorrect'])
            ? (string) $previous_challenge['incorrect']
            : '';

        $challenge = ftss_openai_generate_challenge($previous_incorrect);

        if ($challenge !== null) {
            return $challenge;
        }
    }

    return ftss_generate_static_challenge($previous_challenge);
}

function ftss_ensure_current_challenge($state)
{
    if (!isset($state['current_challenge']) || !is_array($state['current_challenge'])) {
        $state['current_challenge'] = ftss_generate_next_challenge();
    } else {
        $state['current_challenge'] = ftss_prepare_challenge($state['current_challenge']);
    }

    return $state;
}

function ftss_ensure_state()
{
    if (!isset($_SESSION['ftss_state']) || !is_array($_SESSION['ftss_state'])) {
        $_SESSION['ftss_state'] = [
            'current_challenge' => null,
            'streak' => 0,
            'best_streak' => 0,
            'answered_count' => 0,
            'last_result' => null,
        ];
    }

    $state = ftss_ensure_current_challenge($_SESSION['ftss_state']);
    $_SESSION['ftss_state'] = $state;

    return $state;
}

function ftss_write_state($state)
{
    $_SESSION['ftss_state'] = $state;
}

function ftss_read_view_model()
{
    $config = ftss_read_app_config();
    $state = ftss_ensure_state();

    return [
        'app_name' => $config['app_name'],
        'tagline' => $config['tagline'],
        'sentence' => $state['current_challenge'],
        'streak' => (int) $state['streak'],
        'best_streak' => (int) $state['best_streak'],
        'answered_count' => (int) $state['answered_count'],
        'last_result' => $state['last_result'],
    ];
}

function ftss_grade_answer_locally($challenge, $answer)
{
    $normalized_answer = ftss_normalize_sentence($answer);
    $normalized_correct = ftss_normalize_sentence($challenge['correct']);
    $is_correct = ($normalized_answer !== '') && ($normalized_answer === $normalized_correct);

    return [
        'is_correct' => $is_correct,
        'correct_answer' => $challenge['correct'],
        'feedback' => $is_correct ? 'Your correction matches the expected answer.' : 'Match the expected corrected sentence more closely.',
    ];
}

function ftss_grade_answer($challenge, $answer)
{
    $answer = (string) $answer;

    if (ftss_normalize_sentence($answer) === '') {
        return [
            'is_correct' => false,
            'correct_answer' => $challenge['correct'],
            'feedback' => 'Submit a corrected sentence to keep the streak going.',
        ];
    }

    if (ftss_should_grade_with_openai()) {
        $grade = ftss_openai_grade_challenge_answer($challenge, $answer);

        if ($grade !== null) {
            if ($grade['correct_answer'] === '') {
                $grade['correct_answer'] = $challenge['correct'];
            }

            return $grade;
        }
    }

    return ftss_grade_answer_locally($challenge, $answer);
}

function ftss_build_feedback_message($is_correct, $feedback = '')
{
    $base_message = $is_correct ? 'Correct. Streak kept alive.' : 'Not quite. The streak resets here.';
    $feedback = trim((string) $feedback);

    if ($feedback === '') {
        return $base_message;
    }

    return $base_message . ' ' . $feedback;
}

function ftss_check_answer($answer)
{
    $state = ftss_ensure_state();
    $challenge = isset($state['current_challenge']) ? ftss_prepare_challenge($state['current_challenge']) : null;

    if ($challenge === null) {
        return [
            'success' => false,
            'message' => 'No sentence is available.',
        ];
    }

    $grade = ftss_grade_answer($challenge, $answer);
    $is_correct = (bool) $grade['is_correct'];

    if ($is_correct) {
        $state['streak']++;
        $state['best_streak'] = max($state['best_streak'], $state['streak']);
    } else {
        $state['streak'] = 0;
    }

    $state['answered_count']++;
    $state['last_result'] = [
        'is_correct' => $is_correct,
        'submitted_answer' => (string) $answer,
        'correct_answer' => $grade['correct_answer'],
        'previous_sentence_id' => $challenge['id'],
        'source' => $challenge['source'],
    ];
    $state['current_challenge'] = ftss_generate_next_challenge($challenge);

    ftss_write_state($state);

    return [
        'success' => true,
        'is_correct' => $is_correct,
        'message' => ftss_build_feedback_message($is_correct, $grade['feedback']),
        'correct_answer' => $grade['correct_answer'],
        'next_sentence' => $state['current_challenge'],
        'streak' => (int) $state['streak'],
        'best_streak' => (int) $state['best_streak'],
        'answered_count' => (int) $state['answered_count'],
    ];
}

function ftss_read_runtime_status()
{
    return [
        'challenge_provider' => ftss_read_provider('challenge_provider'),
        'grading_provider' => ftss_read_provider('grading_provider'),
        'openai_available' => ftss_openai_is_available(),
    ];
}
