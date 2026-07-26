<?php

namespace RoomieMatch\Config;

class Env
{
    private static array $loaded = [];

    public static function load(string $path): void
    {
        if (!file_exists($path)) {
            throw new \RuntimeException(".env file not found at: $path");
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) continue;

            if (str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                $value = trim($value, '"\'');
                $_ENV[$key] = $value;
                putenv("$key=$value");
                self::$loaded[$key] = $value;
            }
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_ENV[$key] ?? getenv($key) ?: $default;
    }
}
