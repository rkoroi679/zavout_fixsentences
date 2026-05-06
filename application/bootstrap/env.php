<?php

function ftss_load_dotenv($path)
{
    static $loaded_paths = [];

    $path = (string) $path;

    if ($path === '' || isset($loaded_paths[$path]) || !is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if (!is_array($lines)) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim((string) $line);

        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }

        if (strpos($line, 'export ') === 0) {
            $line = substr($line, 7);
        }

        $separator_pos = strpos($line, '=');

        if ($separator_pos === false) {
            continue;
        }

        $name = trim(substr($line, 0, $separator_pos));
        $value = trim(substr($line, $separator_pos + 1));

        if ($name === '' || getenv($name) !== false) {
            continue;
        }

        $length = strlen($value);

        if ($length >= 2) {
            $first_char = $value[0];
            $last_char = $value[$length - 1];

            if (($first_char === '"' && $last_char === '"') || ($first_char === '\'' && $last_char === '\'')) {
                $value = substr($value, 1, -1);
            }
        }

        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }

    $loaded_paths[$path] = true;
}

ftss_load_dotenv(dirname(__DIR__, 2) . '/.env');
