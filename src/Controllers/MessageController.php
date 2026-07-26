<?php

namespace RoomieMatch\Controllers;

use RoomieMatch\Middleware\Auth;
use RoomieMatch\Models\Connection;
use RoomieMatch\Models\Message;
use RoomieMatch\Models\User;
use RoomieMatch\Services\EmailService;

class MessageController
{
    public static function index(string $connectionId): void
    {
        $user = Auth::requireAuth();
        $connection = Connection::findById($connectionId);

        if (!$connection) {
            http_response_code(404);
            echo json_encode(['error' => 'Connection not found.']);
            return;
        }

        if ($connection['requester'] !== $user['_id'] && $connection['recipient'] !== $user['_id']) {
            http_response_code(403);
            echo json_encode(['error' => 'You are not part of this conversation.']);
            return;
        }

        if ($connection['status'] !== 'accepted') {
            http_response_code(403);
            echo json_encode(['error' => 'Connection not yet accepted. Messaging is locked.']);
            return;
        }

        Message::markAsRead($connectionId, $user['_id']);

        $limit = (int)($_GET['limit'] ?? 50);
        $skip = (int)($_GET['skip'] ?? 0);
        $messages = Message::findByConnection($connectionId, $limit, $skip);

        echo json_encode(['messages' => array_reverse($messages)]);
    }

    public static function store(string $connectionId): void
    {
        $user = Auth::requireAuth();
        $connection = Connection::findById($connectionId);

        if (!$connection) {
            http_response_code(404);
            echo json_encode(['error' => 'Connection not found.']);
            return;
        }

        if ($connection['requester'] !== $user['_id'] && $connection['recipient'] !== $user['_id']) {
            http_response_code(403);
            echo json_encode(['error' => 'You are not part of this conversation.']);
            return;
        }

        if ($connection['status'] !== 'accepted') {
            http_response_code(403);
            echo json_encode(['error' => 'Cannot send messages. Connection not yet accepted.']);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data['content'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Message content is required.']);
            return;
        }

        $message = Message::create([
            'connection' => new \MongoDB\BSON\ObjectId($connectionId),
            'sender' => new \MongoDB\BSON\ObjectId($user['_id']),
            'content' => $data['content'],
        ]);

        $otherId = $connection['requester'] === $user['_id'] ? $connection['recipient'] : $connection['requester'];
        $otherUser = User::findById($otherId);
        if ($otherUser) {
            $emailService = new EmailService();
            $appUrl = getenv('APP_URL') ?: 'http://localhost:8000';
            $preview = mb_substr($data['content'], 0, 100);
            $emailService->sendMessageNotification(
                $otherUser['email'], $otherUser['name'], $user['name'],
                $preview, $appUrl . '/messages/' . $connectionId
            );
        }

        echo json_encode(['message' => $message]);
    }
}
