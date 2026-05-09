<?php
/**
 * Minimal .env loader (no Composer). Loads KEY=value pairs into $_ENV and getenv().
 */

if (!function_exists('school_manager_load_env')) {
    function school_manager_load_env(string $path): void
    {
        if (!is_readable($path)) {
            return;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return;
        }
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }
            if (strpos($line, '=') === false) {
                continue;
            }
            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            if ($name === '') {
                continue;
            }
            $len = strlen($value);
            if ($len >= 2) {
                $q = $value[0];
                if (($q === '"' || $q === "'") && $value[$len - 1] === $q) {
                    $value = substr($value, 1, -1);
                }
            }
            $_ENV[$name] = $value;
            putenv($name . '=' . $value);
        }
    }

    $envRoot = dirname(__DIR__);
    school_manager_load_env($envRoot . '/.env');
}
