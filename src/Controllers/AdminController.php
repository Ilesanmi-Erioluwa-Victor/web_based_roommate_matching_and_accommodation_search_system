<?php

namespace RoomieMatch\Controllers;

use MongoDB\BSON\ObjectId;
use RoomieMatch\Middleware\Auth;
use RoomieMatch\Models\User;
use RoomieMatch\Models\Listing;
use RoomieMatch\Models\Report;
use RoomieMatch\Models\AuditLog;

class AdminController
{
    public static function getReports(): void
    {
        Auth::requireAdmin();
        $reports = Report::findAll();
        echo json_encode(['reports' => $reports]);
    }

    public static function updateReport(string $id): void
    {
        Auth::requireAdmin();
        $data = json_decode(file_get_contents('php://input'), true);
        $status = $data['status'] ?? null;
        if (!in_array($status, ['resolved', 'dismissed'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Status must be "resolved" or "dismissed".']);
            return;
        }
        Report::updateStatus($id, $status);
        echo json_encode(['message' => 'Report updated.']);
    }

    public static function suspendUser(string $id): void
    {
        $admin = Auth::requireAdmin();
        User::update($id, ['isSuspended' => true]);
        AuditLog::log($admin['_id'], 'admin.suspend.' . $id, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '');
        echo json_encode(['message' => 'User suspended.']);
    }

    public static function unsuspendUser(string $id): void
    {
        $admin = Auth::requireAdmin();
        User::update($id, ['isSuspended' => false]);
        AuditLog::log($admin['_id'], 'admin.unsuspend.' . $id, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '');
        echo json_encode(['message' => 'User unsuspended.']);
    }

    public static function removeListing(string $id): void
    {
        $admin = Auth::requireAdmin();
        $listing = Listing::findById($id);
        if (!$listing) {
            http_response_code(404);
            echo json_encode(['error' => 'Listing not found.']);
            return;
        }
        Listing::update($id, ['status' => 'removed']);
        AuditLog::log($admin['_id'], 'admin.remove_listing.' . $id, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '');
        echo json_encode(['message' => 'Listing removed.']);
    }

    public static function verifyUser(string $id): void
    {
        $admin = Auth::requireAdmin();
        $user = User::findById($id);
        if (!$user) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found.']);
            return;
        }
        $newStatus = !($user['isVerified'] ?? false);
        User::update($id, ['isVerified' => $newStatus]);
        AuditLog::log($admin['_id'], 'admin.verify.' . $id, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '');
        echo json_encode(['message' => $newStatus ? 'User verified.' : 'User unverified.', 'isVerified' => $newStatus]);
    }

    public static function getAuditLogs(): void
    {
        Auth::requireAdmin();
        $logs = AuditLog::findAll();
        echo json_encode(['logs' => $logs]);
    }

    public static function getUsers(): void
    {
        Auth::requireAdmin();
        $search = $_GET['search'] ?? '';
        $users = User::searchAdmin($search);
        echo json_encode(['users' => $users]);
    }
}
