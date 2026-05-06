<?php

return [
    'app_name' => 'Fix These Sentences Streak App',
    'tagline' => 'Repair the sentence. Protect the streak.',
    'challenge_provider' => getenv('FTSS_CHALLENGE_PROVIDER') ?: 'openai',
    'grading_provider' => getenv('FTSS_GRADING_PROVIDER') ?: 'openai',
    'openai' => [
        'api_key_env' => getenv('FTSS_OPENAI_API_KEY_ENV') ?: 'OPENAI_API_KEY',
        'model' => getenv('FTSS_OPENAI_MODEL') ?: 'gpt-4o-mini',
        'base_url' => rtrim(getenv('FTSS_OPENAI_BASE_URL') ?: 'https://api.openai.com/v1', '/'),
        'timeout_seconds' => (int) (getenv('FTSS_OPENAI_TIMEOUT') ?: 20),
        'generation_temperature' => (float) (getenv('FTSS_OPENAI_GENERATION_TEMPERATURE') ?: 0.9),
        'grading_temperature' => (float) (getenv('FTSS_OPENAI_GRADING_TEMPERATURE') ?: 0),
    ],
    'sentences' => [
        [
            'id' => 1,
            'incorrect' => 'She go to school every day.',
            'correct' => 'She goes to school every day.',
            'hint' => 'Check the verb agreement.',
        ],
        [
            'id' => 2,
            'incorrect' => 'I have ate my lunch already.',
            'correct' => 'I have eaten my lunch already.',
            'hint' => 'Check the past participle.',
        ],
        [
            'id' => 3,
            'incorrect' => 'There is many books on the table.',
            'correct' => 'There are many books on the table.',
            'hint' => 'Match the verb to the plural noun.',
        ],
        [
            'id' => 4,
            'incorrect' => 'He do not like cold weather.',
            'correct' => 'He does not like cold weather.',
            'hint' => 'Use the correct auxiliary verb.',
        ],
        [
            'id' => 5,
            'incorrect' => 'The team were ready for its match.',
            'correct' => 'The team was ready for its match.',
            'hint' => 'Keep the subject and verb consistent.',
        ],
    ],
];
