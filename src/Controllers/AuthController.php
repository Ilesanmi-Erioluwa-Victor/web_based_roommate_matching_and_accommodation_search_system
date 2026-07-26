<?php

namespace RoomieMatch\Controllers;

use RoomieMatch\Config\Env;
use RoomieMatch\Middleware\Auth;
use RoomieMatch\Middleware\RateLimit;
use RoomieMatch\Models\AuditLog;
use RoomieMatch\Models\User;
use RoomieMatch\Services\EmailService;

class AuthController
{
    public static function register(): void
    {
        RateLimit::middleware('register');
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['email']) || empty($data['password']) || empty($data['name'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Name, email, and password are required.']);
            return;
        }

        if (strlen($data['password']) < 6) {
            http_response_code(400);
            echo json_encode(['error' => 'Password must be at least 6 characters.']);
            return;
        }

        $existing = User::findByEmail($data['email']);
        if ($existing) {
            http_response_code(409);
            echo json_encode(['error' => 'Email already registered.']);
            return;
        }

        $role = $data['role'] ?? 'both';
        if (!in_array($role, ['seeker', 'lister', 'both'])) {
            $role = 'both';
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'password' => $data['password'],
            'role' => $role,
        ]);

        if (!empty($data['phone'])) {
            User::update($user['_id'], ['phone' => $data['phone']]);
        }

        $token = Auth::generateToken($user);
        $verificationToken = Auth::generateResetToken($user['_id']);

        $appUrl = Env::get('APP_URL', 'http://localhost:8000');
        $verificationLink = $appUrl . '/verify-email?token=' . $verificationToken . '&userId=' . $user['_id'];

        $emailService = new EmailService();
        $emailService->sendEmailVerification($data['email'], $data['name'], $verificationLink);

        AuditLog::log($user['_id'], 'user.register', $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '');

        echo json_encode([
            'message' => 'Registration successful. Please check your email to verify your account.',
            'token' => $token,
            'user' => $user,
        ]);
    }

    public static function verifyEmail(): void
    {
        $token = $_GET['token'] ?? '';
        $userId = $_GET['userId'] ?? '';

        $payload = Auth::verifyToken($token);
        if (!$payload || ($payload['sub'] ?? '') !== $userId) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid or expired verification link.']);
            return;
        }

        User::update($userId, ['isEmailVerified' => true, 'isVerified' => true]);
        echo json_encode(['message' => 'Email verified successfully. You can now login.']);
    }

    public static function login(): void
    {
        RateLimit::middleware('login');
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['email']) || empty($data['password'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Email and password are required.']);
            return;
        }

        $user = User::findByEmail($data['email']);
        if (!$user || !password_verify($data['password'], $user['passwordHash'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid email or password.']);
            return;
        }

        if ($user['isSuspended']) {
            http_response_code(403);
            echo json_encode(['error' => 'Your account has been suspended.']);
            return;
        }

        $token = Auth::generateToken($user);
        AuditLog::log($user['_id'], 'user.login', $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '');

        echo json_encode([
            'message' => 'Login successful.',
            'token' => $token,
            'user' => $user,
        ]);
    }

    public static function logout(): void
    {
        $user = Auth::requireAuth();
        AuditLog::log($user['_id'], 'user.logout', $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '');
        echo json_encode(['message' => 'Logged out successfully.']);
    }

    public static function forgotPassword(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data['email'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Email is required.']);
            return;
        }

        $user = User::findByEmail($data['email']);
        if ($user) {
            $resetToken = Auth::generateResetToken($user['_id']);
            $appUrl = Env::get('APP_URL', 'http://localhost:8000');
            $resetLink = $appUrl . '/reset-password?token=' . $resetToken . '&userId=' . $user['_id'];

            $emailService = new EmailService();
            $emailService->sendPasswordReset($data['email'], $user['name'], $resetLink);
        }

        echo json_encode(['message' => 'If the email exists, a password reset link has been sent.']);
    }

    public static function resetPassword(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data['token']) || empty($data['userId']) || empty($data['password'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Token, userId, and new password are required.']);
            return;
        }

        $payload = Auth::verifyToken($data['token']);
        if (!$payload || ($payload['sub'] ?? '') !== $data['userId'] || ($payload['purpose'] ?? '') !== 'password_reset') {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid or expired reset token.']);
            return;
        }

        User::update($data['userId'], ['passwordHash' => password_hash($data['password'], PASSWORD_BCRYPT)]);
        echo json_encode(['message' => 'Password reset successful. You can now login.']);
    }
}
