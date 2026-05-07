<?php
$last_result = $view_model['last_result'];
$sentence = $view_model['sentence'];
// var_dump($sentence); // Debugging line to check the sentence data
// exit;
?>
<section class="hero">
    <div class="hero-copy">
        <p class="eyebrow">Daily Practice</p>
        <h1><?php echo htmlspecialchars($view_model['app_name'], ENT_QUOTES, 'UTF-8'); ?></h1>
        <p class="tagline"><?php echo htmlspecialchars($view_model['tagline'], ENT_QUOTES, 'UTF-8'); ?></p>
    </div>
    <div class="stats-card">
        <div class="stat">
            <span class="stat-label">Current Streak</span>
            <strong id="streak-value"><?php echo (int) $view_model['streak']; ?></strong>
        </div>
        <div class="stat">
            <span class="stat-label">Best Streak</span>
            <strong id="best-streak-value"><?php echo (int) $view_model['best_streak']; ?></strong>
        </div>
        <div class="stat">
            <span class="stat-label">Answered</span>
            <strong id="answered-count-value"><?php echo (int) $view_model['answered_count']; ?></strong>
        </div>
    </div>
</section>

<section class="trainer-card">
    <div class="prompt-panel">
        <p class="panel-label">Fix This Sentence</p>
        <blockquote id="incorrect-sentence"><?php echo htmlspecialchars($sentence['incorrect'], ENT_QUOTES, 'UTF-8'); ?></blockquote>
        <p class="hint">Hint: <span id="sentence-hint"><?php echo htmlspecialchars($sentence['hint'], ENT_QUOTES, 'UTF-8'); ?></span></p>
    </div>

    <form id="sentence-form" class="answer-panel">
        <label for="sentence-answer">Rewrite the sentence correctly</label>
        <textarea id="sentence-answer" name="answer" rows="4" placeholder="Type the corrected sentence here." required></textarea>
        <button type="submit" class="submit-button">Check Answer</button>
    </form>
</section>

<section id="feedback-card" class="feedback-card<?php echo $last_result === null ? ' hidden' : ''; ?>">
    <h2>Latest Result</h2>
    <p id="feedback-message">
        <?php
        if ($last_result !== null) {
            echo htmlspecialchars($last_result['is_correct'] ? 'Correct. Streak kept alive.' : 'Not quite. The streak resets here.', ENT_QUOTES, 'UTF-8');
        }
        ?>
    </p>
    <p id="correct-answer" class="correct-answer<?php echo ($last_result !== null && !$last_result['is_correct']) ? '' : ' hidden'; ?>">
        <?php
        if ($last_result !== null && !$last_result['is_correct']) {
            echo 'Correct answer: ' . htmlspecialchars($last_result['correct_answer'], ENT_QUOTES, 'UTF-8');
        }
        ?>
    </p>
</section>
