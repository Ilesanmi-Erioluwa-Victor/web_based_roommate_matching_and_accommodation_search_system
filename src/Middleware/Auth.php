<?php

namespace RoomieMatch\Middleware;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use RoomieMatch\Config\Env;
use RoomieMatch\Models\User;

class Auth
{
    private static string $algorithm = 'HS256';

    public static function generateToken(array $user): string
    {
        $secret = Env::get('JWT_SECRET');
        $now = time();
        $payload = [
            'iss' => Env::get('APP_URL', 'http://localhost:8000'),
            'iat' => $now,
            'exp' => $now + (24 * 60 * 60),
            'sub' => $user['_id'],
            'email' => $user['email'],
            'role' => $user['role'] ?? 'both',
        ];
        return JWT::encode($payload, $secret, self::$algorithm);
    }

    public static function generateResetToken(string $userId): string
    {
        $secret = Env::get('JWT_SECRET');
        $now = time();
        $payload = [
            'sub' => $userId,
            'purpose' => 'password_reset',
            'exp' => $now + 3600,
        ];
        return JWT::encode($payload, $secret, self::$algorithm);
    }

    public static function verifyToken(string $token): ?array
    {
        try {
            $secret = Env::get('JWT_SECRET');
            $decoded = JWT::decode($token, new Key($secret, self::$algorithm));
            return (array)$decoded;
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function authenticate(): ?array
    {
        $headers = self::getAuthorizationHeader();
        if (!$headers) return null;

        $token = str_replace('Bearer ', '', $headers);
        $payload = self::verifyToken($token);
        if (!$payload) return null;

        $user = User::findById($payload['sub']);
        if (!$user || $user['isSuspended']) return null;

        return $user;
    }

    public static function requireAuth(): array
    {
        $user = self::authenticate();
        if (!$user) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized. Please login.']);
            exit;
        }
        return $user;
    }

    public static function requireAdmin(): array
    {
        $user = self::requireAuth();
        if ($user['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden. Admin access required.']);
            exit;
        }
        return $user;
    }

    private static function getAuthorizationHeader(): ?string
    {
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            return $_SERVER['HTTP_AUTHORIZATION'];
        }
        if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            return $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }
        if (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            if (isset($headers['Authorization'])) {
                return $headers['Authorization'];
            }
        }
        return null;
    }
}
