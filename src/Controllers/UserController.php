<?php

namespace RoomieMatch\Controllers;

use RoomieMatch\Middleware\Auth;
use RoomieMatch\Models\AuditLog;
use RoomieMatch\Models\User;
use RoomieMatch\Services\CloudinaryService;

class UserController
{
    public static function getMe(): void
    {
        $user = Auth::requireAuth();
        echo json_encode(['user' => $user]);
    }

    public static function updateProfile(): void
    {
        $user = Auth::requireAuth();
        $data = json_decode(file_get_contents('php://input'), true);

        $allowed = ['name', 'phone', 'gender', 'dateOfBirth'];
        $update = [];
        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                $update[$field] = $data[$field];
            }
        }

        if (!empty($update)) {
            User::update($user['_id'], $update);
        }

        AuditLog::log($user['_id'], 'user.updateProfile', $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '');
        echo json_encode(['message' => 'Profile updated.', 'user' => User::findById($user['_id'])]);
    }

    public static function updateLifestyle(): void
    {
        $user = Auth::requireAuth();
        $data = json_decode(file_get_contents('php://input'), true);

        $allowed = [
            'budgetMin', 'budgetMax', 'currency', 'cleanliness', 'sleepSchedule',
            'smoker', 'toleratesSmoking', 'hasPets', 'toleratesPets',
            'noiseLevel', 'guestFrequency', 'workSchedule',
            'dietaryPreference', 'religionPreference', 'genderPreference',
            'preferredLocations', 'additionalNotes',
        ];

        $update = [];
        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                $update["lifestyle.{$field}"] = $data[$field];
            }
        }

        if (isset($data['dealBreakers']) && is_array($data['dealBreakers'])) {
            foreach ($data['dealBreakers'] as $key => $value) {
                $update["dealBreakers.{$key}"] = $value;
            }
        }

        if (!empty($update)) {
            User::update($user['_id'], $update);
        }

        AuditLog::log($user['_id'], 'user.updateLifestyle', $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '');
        echo json_encode(['message' => 'Lifestyle profile updated.', 'user' => User::findById($user['_id'])]);
    }

    public static function updateMatchingStatus(): void
    {
        $user = Auth::requireAuth();
        $data = json_decode(file_get_contents('php://input'), true);

        $status = $data['status'] ?? '';
        if (!in_array($status, ['actively_looking', 'paused', 'found_roommate'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid status. Use: actively_looking, paused, or found_roommate.']);
            return;
        }

        User::update($user['_id'], ['matchingStatus' => $status]);
        AuditLog::log($user['_id'], 'user.updateMatchingStatus', $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '');
        echo json_encode(['message' => 'Matching status updated.', 'status' => $status]);
    }

    public static function uploadProfilePhoto(): void
    {
        $user = Auth::requireAuth();

        if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['error' => 'Photo upload failed.']);
            return;
        }

        $cloudinary = new CloudinaryService();
        $validation = $cloudinary->validateUploadedFile($_FILES['photo']);
        if (!$validation['valid']) {
            http_response_code(400);
            echo json_encode(['error' => $validation['error']]);
            return;
        }

        if ($user['profilePhotoPublicId']) {
            $cloudinary->deleteImage($user['profilePhotoPublicId']);
        }

        try {
            $result = $cloudinary->uploadProfilePhoto($_FILES['photo']['tmp_name'], $user['_id']);
            User::update($user['_id'], [
                'profilePhotoUrl' => $result['url'],
                'profilePhotoPublicId' => $result['publicId'],
            ]);

            AuditLog::log($user['_id'], 'user.uploadPhoto', $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '');
            echo json_encode(['message' => 'Profile photo updated.', 'url' => $result['url']]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Upload failed. Please try again.']);
        }
    }

    public static function blockUser(string $targetId): void
    {
        $user = Auth::requireAuth();
        if ($targetId === $user['_id']) {
            http_response_code(400);
            echo json_encode(['error' => 'Cannot block yourself.']);
            return;
        }

        User::addBlockedUser($user['_id'], $targetId);
        AuditLog::log($user['_id'], "user.blockUser.{$targetId}", $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '');
        echo json_encode(['message' => 'User blocked.']);
    }

    public static function unblockUser(string $targetId): void
    {
        $user = Auth::requireAuth();
        User::removeBlockedUser($user['_id'], $targetId);
        echo json_encode(['message' => 'User unblocked.']);
    }

    public static function deleteAccount(): void
    {
        $user = Auth::requireAuth();
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['password']) || !password_verify($data['password'], $user['passwordHash'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Password is required to delete account.']);
            return;
        }

        User::anonymize($user['_id']);

        AuditLog::log($user['_id'], 'user.deleteAccount', $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '');
        echo json_encode(['message' => 'Account deleted. Your data has been anonymized.']);
    }
}
