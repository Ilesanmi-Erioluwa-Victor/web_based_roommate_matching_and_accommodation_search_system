<?php

namespace RoomieMatch\Controllers;

use RoomieMatch\Middleware\Auth;
use RoomieMatch\Models\AuditLog;
use RoomieMatch\Models\Connection;
use RoomieMatch\Models\Message;
use RoomieMatch\Models\User;
use RoomieMatch\Services\EmailService;

class ConnectionController
{
    public static function sendRequest(): void
    {
        $user = Auth::requireAuth();
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['recipientId'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Recipient ID is required.']);
            return;
        }

        if ($data['recipientId'] === $user['_id']) {
            http_response_code(400);
            echo json_encode(['error' => 'Cannot send connection request to yourself.']);
            return;
        }

        $recipient = User::findById($data['recipientId']);
        if (!$recipient || $recipient['isSuspended']) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found.']);
            return;
        }

        if (in_array($user['_id'], $recipient['blockedUsers'] ?? [])) {
            http_response_code(403);
            echo json_encode(['error' => 'Cannot send request to this user.']);
            return;
        }

        $listingId = $data['listingId'] ?? null;
        $existing = Connection::findBetweenUsers($user['_id'], $data['recipientId'], $listingId);

        if ($existing) {
            if ($existing['status'] === 'accepted') {
                http_response_code(409);
                echo json_encode(['error' => 'Already connected.', 'connection' => $existing]);
                return;
            }
            if ($existing['status'] === 'pending') {
                if ($existing['recipient'] === $user['_id']) {
                    Connection::respond($existing['_id'], 'accepted');
                    $existing['status'] = 'accepted';

                    $emailService = new EmailService();
                    $appUrl = getenv('APP_URL') ?: 'http://localhost:8000';
                    $emailService->sendConnectionAccepted($recipient['email'], $recipient['name'], $user['name'], $appUrl . '/messages/' . $existing['_id']);

                    echo json_encode(['message' => 'Connection auto-accepted (mutual request).', 'connection' => $existing]);
                    return;
                }
                http_response_code(409);
                echo json_encode(['error' => 'Request already sent.', 'connection' => $existing]);
                return;
            }
        }

        $connection = Connection::create([
            'requester' => new \MongoDB\BSON\ObjectId($user['_id']),
            'recipient' => new \MongoDB\BSON\ObjectId($data['recipientId']),
            'listing' => $listingId ? new \MongoDB\BSON\ObjectId($listingId) : null,
        ]);

        $emailService = new EmailService();
        $appUrl = getenv('APP_URL') ?: 'http://localhost:8000';
        $emailService->sendConnectionRequest(
            $recipient['email'], $recipient['name'], $user['name'],
            $appUrl . '/connections'
        );

        AuditLog::log($user['_id'], 'connection.request.' . $connection['_id'], $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '');
        echo json_encode(['message' => 'Connection request sent.', 'connection' => $connection]);
    }

    public static function respond(string $id): void
    {
        $user = Auth::requireAuth();
        $connection = Connection::findById($id);

        if (!$connection) {
            http_response_code(404);
            echo json_encode(['error' => 'Connection not found.']);
            return;
        }

        if ($connection['recipient'] !== $user['_id']) {
            http_response_code(403);
            echo json_encode(['error' => 'Only the recipient can respond to this request.']);
            return;
        }

        if ($connection['status'] !== 'pending') {
            http_response_code(400);
            echo json_encode(['error' => 'This request has already been ' . $connection['status'] . '.']);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $status = $data['status'] ?? '';

        if (!in_array($status, ['accepted', 'declined'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Status must be "accepted" or "declined".']);
            return;
        }

        Connection::respond($id, $status);

        if ($status === 'accepted') {
            $requester = User::findById($connection['requester']);
            $recipient = User::findById($connection['recipient']);
            if ($requester && $recipient) {
                $emailService = new EmailService();
                $appUrl = getenv('APP_URL') ?: 'http://localhost:8000';
                $emailService->sendConnectionAccepted(
                    $requester['email'], $requester['name'], $recipient['name'],
                    $appUrl . '/messages/' . $id
                );
            }
        }

        $connection['status'] = $status;
        echo json_encode(['message' => 'Connection ' . $status . '.', 'connection' => $connection]);
    }

    public static function getPending(): void
    {
        $user = Auth::requireAuth();
        $pending = Connection::findPendingForUser($user['_id']);

        $result = [];
        foreach ($pending as $conn) {
            if ($conn['recipient'] === $user['_id']) {
                $other = User::findById($conn['requester']);
                $conn['direction'] = 'received';
                $conn['otherUser'] = $other ? [
                    '_id' => $other['_id'],
                    'name' => $other['name'],
                    'profilePhotoUrl' => $other['profilePhotoUrl'],
                ] : null;
            } else {
                $other = User::findById($conn['recipient']);
                $conn['direction'] = 'sent';
                $conn['otherUser'] = $other ? [
                    '_id' => $other['_id'],
                    'name' => $other['name'],
                    'profilePhotoUrl' => $other['profilePhotoUrl'],
                ] : null;
            }
            $result[] = $conn;
        }

        echo json_encode(['connections' => $result]);
    }

    public static function getAccepted(): void
    {
        $user = Auth::requireAuth();
        $accepted = Connection::findAcceptedForUser($user['_id']);

        $result = [];
        foreach ($accepted as $conn) {
            $otherId = $conn['requester'] === $user['_id'] ? $conn['recipient'] : $conn['requester'];
            $otherUser = User::findById($otherId);
            $conn['otherUser'] = $otherUser ? [
                '_id' => $otherUser['_id'],
                'name' => $otherUser['name'],
                'profilePhotoUrl' => $otherUser['profilePhotoUrl'],
            ] : null;

            $conn['unreadCount'] = Message::getUnreadCountForConnection($conn['_id'], $user['_id']);

            $lastMsg = Message::getLastMessage($conn['_id']);
            $conn['lastMessage'] = $lastMsg ? [
                'content' => mb_substr($lastMsg['content'], 0, 60),
                'sender' => $lastMsg['sender'],
                'createdAt' => $lastMsg['createdAt'],
            ] : null;

            $result[] = $conn;
        }

        echo json_encode(['connections' => $result]);
    }
}
