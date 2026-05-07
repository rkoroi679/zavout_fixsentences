CREATE TABLE IF NOT EXISTS challenge_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(100) NOT NULL UNIQUE,
    grammar_topic VARCHAR(100) NOT NULL,
    incorrect_template TEXT NOT NULL,
    correct_template TEXT NOT NULL,
    hint_template VARCHAR(255) NOT NULL,
    slots_json JSON NOT NULL,
    answer_variants_json JSON NULL,
    difficulty TINYINT NOT NULL DEFAULT 1,
    weight INT NOT NULL DEFAULT 1,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
);
