<?php

function ftss_read_app_config()
{
    static $config = null;

    if ($config === null) {
        $config = require __DIR__ . '/../config/app.php';
    }

    return $config;
}

function ftss_read_sentences()
{
    $config = ftss_read_app_config();
    return $config['sentences'];
}

function ftss_normalize_sentence($sentence)
{
    $sentence = trim((string) $sentence);
    $sentence = preg_replace('/\s+/', ' ', $sentence);
    return mb_strtolower($sentence);
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
        return (int) $exclude_id;
    }

    return $eligible_ids[array_rand($eligible_ids)];
}

function ftss_read_sentence_by_id($sentence_id)
{
    $sentences = ftss_read_sentences();

    foreach ($sentences as $sentence) {
        if ((int) $sentence['id'] === (int) $sentence_id) {
            return $sentence;
        }
    }

    return null;
}

function ftss_ensure_state()
{
    if (!isset($_SESSION['ftss_state']) || !is_array($_SESSION['ftss_state'])) {
        $_SESSION['ftss_state'] = [
            'current_sentence_id' => ftss_pick_next_sentence_id(),
            'streak' => 0,
            'best_streak' => 0,
            'answered_count' => 0,
            'last_result' => null,
        ];
    }

    return $_SESSION['ftss_state'];
}

function ftss_write_state($state)
{
    $_SESSION['ftss_state'] = $state;
}

function ftss_read_view_model()
{
    $config = ftss_read_app_config();
    $state = ftss_ensure_state();
    $current_sentence = ftss_read_sentence_by_id($state['current_sentence_id']);

    if ($current_sentence === null) {
        $state['current_sentence_id'] = ftss_pick_next_sentence_id();
        ftss_write_state($state);
        $current_sentence = ftss_read_sentence_by_id($state['current_sentence_id']);
    }

    return [
        'app_name' => $config['app_name'],
        'tagline' => $config['tagline'],
        'sentence' => $current_sentence,
        'streak' => (int) $state['streak'],
        'best_streak' => (int) $state['best_streak'],
        'answered_count' => (int) $state['answered_count'],
        'last_result' => $state['last_result'],
    ];
}

function ftss_check_answer($answer)
{
    $state = ftss_ensure_state();
    $sentence = ftss_read_sentence_by_id($state['current_sentence_id']);

    if ($sentence === null) {
        return [
            'success' => false,
            'message' => 'No sentence is available.',
        ];
    }

    $normalized_answer = ftss_normalize_sentence($answer);
    $normalized_correct = ftss_normalize_sentence($sentence['correct']);
    $is_correct = ($normalized_answer !== '') && ($normalized_answer === $normalized_correct);

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
        'correct_answer' => $sentence['correct'],
        'previous_sentence_id' => (int) $sentence['id'],
    ];
    $state['current_sentence_id'] = ftss_pick_next_sentence_id($sentence['id']);

    ftss_write_state($state);

    $next_sentence = ftss_read_sentence_by_id($state['current_sentence_id']);

    return [
        'success' => true,
        'is_correct' => $is_correct,
        'message' => $is_correct ? 'Correct. Streak kept alive.' : 'Not quite. The streak resets here.',
        'correct_answer' => $sentence['correct'],
        'next_sentence' => $next_sentence,
        'streak' => (int) $state['streak'],
        'best_streak' => (int) $state['best_streak'],
        'answered_count' => (int) $state['answered_count'],
    ];
}
