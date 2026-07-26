<?php

namespace RoomieMatch\Controllers;

use RoomieMatch\Middleware\Auth;
use RoomieMatch\Models\Report;

class ReportController
{
    public static function store(): void
    {
        $user = Auth::requireAuth();
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['targetType']) || empty($data['targetId'])) {
            http_response_code(400);
            echo json_encode(['error' => 'targetType and targetId are required.']);
            return;
        }

        if (!in_array($data['targetType'], ['user', 'listing'])) {
            http_response_code(400);
            echo json_encode(['error' => 'targetType must be "user" or "listing".']);
            return;
        }

        $report = Report::create([
            'reporter' => new \MongoDB\BSON\ObjectId($user['_id']),
            'targetType' => $data['targetType'],
            'targetId' => $data['targetId'],
            'reason' => $data['reason'] ?? '',
            'details' => $data['details'] ?? '',
        ]);

        echo json_encode(['message' => 'Report submitted. An admin will review it.', 'report' => $report]);
    }
}

class AdminController
{
    public static function getReports(): void
    {
        \RoomieMatch\Middleware\Auth::requireAdmin();
        $page = (int)($_GET['page'] ?? 1);
        $limit = (int)($_GET['limit'] ?? 20);
        $filters = [];
        if (!empty($_GET['status'])) $filters['status'] = $_GET['status'];
        if (!empty($_GET['targetType'])) $filters['targetType'] = $_GET['targetType'];
        $result = \RoomieMatch\Models\Report::findAll($filters, $page, $limit);
        echo json_encode($result);
    }

    public static function updateReport(string $id): void
    {
        \RoomieMatch\Middleware\Auth::requireAdmin();
        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data['status']) || !in_array($data['status'], ['reviewed', 'dismissed'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Status must be "reviewed" or "dismissed".']);
            return;
        }
        \RoomieMatch\Models\Report::updateStatus($id, $data['status']);
        echo json_encode(['message' => 'Report updated.']);
    }

    public static function suspendUser(string $id): void
    {
        \RoomieMatch\Middleware\Auth::requireAdmin();
        \RoomieMatch\Models\User::update($id, ['isSuspended' => true]);
        \RoomieMatch\Models\AuditLog::log($id, 'admin.suspendUser.' . $id, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '');
        echo json_encode(['message' => 'User suspended.']);
    }

    public static function unsuspendUser(string $id): void
    {
        \RoomieMatch\Middleware\Auth::requireAdmin();
        \RoomieMatch\Models\User::update($id, ['isSuspended' => false]);
        echo json_encode(['message' => 'User unsuspended.']);
    }

    public static function removeListing(string $id): void
    {
        \RoomieMatch\Middleware\Auth::requireAdmin();
        \RoomieMatch\Models\Listing::update($id, ['status' => 'removed_by_admin']);
        \RoomieMatch\Models\AuditLog::log($id, 'admin.removeListing.' . $id, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '');
        echo json_encode(['message' => 'Listing removed.']);
    }

    public static function getAuditLogs(): void
    {
        \RoomieMatch\Middleware\Auth::requireAdmin();
        $page = (int)($_GET['page'] ?? 1);
        $limit = (int)($_GET['limit'] ?? 50);
        $filters = [];
        if (!empty($_GET['userId'])) $filters['userId'] = $_GET['userId'];
        if (!empty($_GET['action'])) $filters['action'] = $_GET['action'];
        $result = \RoomieMatch\Models\AuditLog::findAll($filters, $page, $limit);
        echo json_encode($result);
    }
}
