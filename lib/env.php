<?php

function craftcrawl_load_env($path = null) {
    $path = $path ?: dirname(__DIR__) . '/.env';

    if (!is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        if ($key === '') {
            continue;
        }

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

function craftcrawl_env($key, $default = '') {
    if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
        return $_ENV[$key];
    }

    if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
        return $_SERVER[$key];
    }

    $value = getenv($key);
    if ($value !== false && $value !== '') {
        return $value;
    }

    return $default;
}

craftcrawl_load_env();

// Check-ins compare the current time with local business hours. Pin the
// application timezone instead of inheriting Cloudways' server default, which
// may be UTC and may not match MySQL's session timezone.
$craftcrawl_timezone = craftcrawl_env('CRAFTCRAWL_TIMEZONE', 'America/New_York');

try {
    new DateTimeZone($craftcrawl_timezone);
    date_default_timezone_set($craftcrawl_timezone);
} catch (Throwable $error) {
    error_log('Invalid CRAFTCRAWL_TIMEZONE; falling back to America/New_York.');
    date_default_timezone_set('America/New_York');
}

?>
