<?php
/**
 * Load application environment variables once for web, API, and CLI entrypoints.
 */
if (!defined('DGR_ENV_LOADED')) {
    define('DGR_ENV_LOADED', true);

    $dgrProjectRoot = dirname(__DIR__);
    $dgrAutoloadFile = $dgrProjectRoot . '/vendor/autoload.php';
    if (!is_file($dgrAutoloadFile)) {
        throw new RuntimeException('Composer dependencies are not installed.');
    }

    require_once $dgrAutoloadFile;
    Dotenv\Dotenv::createImmutable($dgrProjectRoot)->safeLoad();
}

if (!function_exists('envValue')) {
    function envValue(string $name, $default = null)
    {
        if (array_key_exists($name, $_ENV) && $_ENV[$name] !== '') {
            return $_ENV[$name];
        }
        if (array_key_exists($name, $_SERVER) && $_SERVER[$name] !== '') {
            return $_SERVER[$name];
        }

        $value = getenv($name);
        return is_string($value) && $value !== '' ? $value : $default;
    }
}

if (!function_exists('envBool')) {
    function envBool(string $name, bool $default = false): bool
    {
        $value = envValue($name);
        if ($value === null) {
            return $default;
        }

        $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        return $parsed === null ? $default : $parsed;
    }
}

if (!function_exists('envRequired')) {
    function envRequired(string $name): string
    {
        $value = envValue($name);
        if (!is_string($value) || trim($value) === '') {
            throw new RuntimeException('Required environment variable is not configured: ' . $name);
        }

        return $value;
    }
}
