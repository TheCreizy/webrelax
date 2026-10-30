<?php
/**
 * Relax Tours - Cargador de variables de entorno (.env)
 * Carga admin/.env de forma idempotente en $_ENV y putenv().
 */

if (!function_exists('loadRelaxEnv')) {
    function loadRelaxEnv(?string $envPath = null): bool
    {
        static $loadedPaths = [];

        if ($envPath === null) {
            $envPath = __DIR__ . '/../admin/.env';
        }

        $realPath = realpath($envPath) ?: $envPath;
        if (isset($loadedPaths[$realPath])) {
            return true;
        }

        if (!is_file($envPath) || !is_readable($envPath)) {
            return false;
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return false;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
                continue;
            }

            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            if ($name === '') {
                continue;
            }

            // Quitar comillas envolventes dobles o simples
            $len = strlen($value);
            if ($len >= 2) {
                $first = $value[0];
                $last = $value[$len - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
            if (function_exists('putenv')) {
                @putenv("{$name}={$value}");
            }
        }

        $loadedPaths[$realPath] = true;
        return true;
    }
}

if (!function_exists('env')) {
    function env(string $key, $default = '')
    {
        if (array_key_exists($key, $_ENV) && $_ENV[$key] !== '') {
            return $_ENV[$key];
        }
        if (array_key_exists($key, $_SERVER) && $_SERVER[$key] !== '') {
            return $_SERVER[$key];
        }
        $val = getenv($key);
        if ($val !== false && $val !== '') {
            return $val;
        }
        return $default;
    }
}

// Autoejecutar al incluir el archivo
loadRelaxEnv();
