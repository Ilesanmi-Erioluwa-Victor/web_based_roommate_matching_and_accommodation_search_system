<?php

namespace RoomieMatch\Middleware;

class RateLimit
{
    private static array $storage = [];
    private static int $window = 900;
    private static int $maxRequests = 20;

    public static function setLimits(int $windowSeconds, int $maxRequestsPerWindow): void
    {
        self::$window = $windowSeconds;
        self::$maxRequests = $maxRequestsPerWindow;
    }

    public static function check(string $key): bool
    {
        $now = time();
        $windowStart = $now - self::$window;

        if (!isset(self::$storage[$key])) {
            self::$storage[$key] = [];
        }

        self::$storage[$key] = array_values(
            array_filter(self::$storage[$key], fn($ts) => $ts > $windowStart)
        );

        if (count(self::$storage[$key]) >= self::$maxRequests) {
            return false;
        }

        self::$storage[$key][] = $now;
        return true;
    }

    public static function middleware(string $identifier): void
    {
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = "{$identifier}:{$clientIp}";

        if (!self::check($key)) {
            http_response_code(429);
            echo json_encode(['error' => 'Too many requests. Please try again later.']);
            exit;
        }
    }
}
