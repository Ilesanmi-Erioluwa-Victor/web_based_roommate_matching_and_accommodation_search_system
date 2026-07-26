<?php

namespace RoomieMatch\Models;

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use RoomieMatch\Config\Database;

class Message
{
    public static function getCollection(): \MongoDB\Collection
    {
        return Database::getConnection()->messages;
    }

    public static function create(array $data): array
    {
        $doc = array_merge([
            'connection' => $data['connection'],
            'sender' => $data['sender'],
            'content' => '',
            'createdAt' => new UTCDateTime(),
            'readAt' => null,
        ], $data);

        $result = self::getCollection()->insertOne($doc);
        $doc['_id'] = (string)$result->getInsertedId();
        return self::formatDoc($doc);
    }

    public static function findByConnection(string|ObjectId $connectionId, int $limit = 50, int $skip = 0): array
    {
        if (is_string($connectionId)) $connectionId = new ObjectId($connectionId);
        $docs = self::getCollection()->find(
            ['connection' => $connectionId],
            ['sort' => ['createdAt' => -1], 'skip' => $skip, 'limit' => $limit]
        )->toArray();
        return array_map([self::class, 'formatDoc'], $docs);
    }

    public static function markAsRead(string|ObjectId $connectionId, string|ObjectId $userId): bool
    {
        if (is_string($connectionId)) $connectionId = new ObjectId($connectionId);
        if (is_string($userId)) $userId = new ObjectId($userId);
        $result = self::getCollection()->updateMany(
            ['connection' => $connectionId, 'sender' => ['$ne' => $userId], 'readAt' => null],
            ['$set' => ['readAt' => new UTCDateTime()]]
        );
        return $result->getModifiedCount() > 0;
    }

    public static function getUnreadCount(string|ObjectId $userId): int
    {
        if (is_string($userId)) $userId = new ObjectId($userId);
        $connections = Connection::findAcceptedForUser($userId);
        if (empty($connections)) return 0;

        $connIds = array_map(fn($c) => new ObjectId($c['_id']), $connections);
        return self::getCollection()->countDocuments([
            'connection' => ['$in' => $connIds],
            'sender' => ['$ne' => $userId],
            'readAt' => null,
        ]);
    }

    public static function anonymizeUserMessages(string|ObjectId $userId): int
    {
        if (is_string($userId)) $userId = new ObjectId($userId);
        $result = self::getCollection()->updateMany(
            ['sender' => $userId],
            ['$set' => ['content' => '[This message has been deleted]']]
        );
        return $result->getModifiedCount();
    }

    public static function formatDoc($doc): array
    {
        if ($doc === null) return [];
        $arr = $doc instanceof \MongoDB\Model\BSONDocument ? json_decode(json_encode($doc), true) : (array)$doc;
        if (isset($arr['_id'])) $arr['_id'] = (string)$arr['_id'];
        if (isset($arr['connection']) && $arr['connection'] instanceof ObjectId) $arr['connection'] = (string)$arr['connection'];
        if (isset($arr['sender']) && $arr['sender'] instanceof ObjectId) $arr['sender'] = (string)$arr['sender'];
        return $arr;
    }
}
