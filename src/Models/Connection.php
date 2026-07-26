<?php

namespace RoomieMatch\Models;

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use RoomieMatch\Config\Database;

class Connection
{
    public static function getCollection(): \MongoDB\Collection
    {
        return Database::getConnection()->connections;
    }

    public static function create(array $data): array
    {
        $doc = array_merge([
            'requester' => $data['requester'],
            'recipient' => $data['recipient'],
            'listing' => null,
            'status' => 'pending',
            'createdAt' => new UTCDateTime(),
        ], $data);

        $result = self::getCollection()->insertOne($doc);
        $doc['_id'] = (string)$result->getInsertedId();
        return self::formatDoc($doc);
    }

    public static function findById(string|ObjectId $id): ?array
    {
        if (is_string($id)) $id = new ObjectId($id);
        $doc = self::getCollection()->findOne(['_id' => $id]);
        return $doc ? self::formatDoc($doc) : null;
    }

    public static function findBetweenUsers(string|ObjectId $userA, string|ObjectId $userB, ?string $listingId = null): ?array
    {
        if (is_string($userA)) $userA = new ObjectId($userA);
        if (is_string($userB)) $userB = new ObjectId($userB);

        $filter = [
            '$or' => [
                ['requester' => $userA, 'recipient' => $userB],
                ['requester' => $userB, 'recipient' => $userA],
            ]
        ];
        if ($listingId) {
            $filter['listing'] = new ObjectId($listingId);
        }

        $doc = self::getCollection()->findOne($filter);
        return $doc ? self::formatDoc($doc) : null;
    }

    public static function findPendingForUser(string|ObjectId $userId): array
    {
        if (is_string($userId)) $userId = new ObjectId($userId);
        $docs = self::getCollection()->find([
            'recipient' => $userId,
            'status' => 'pending'
        ])->toArray();
        return array_map([self::class, 'formatDoc'], $docs);
    }

    public static function findAcceptedForUser(string|ObjectId $userId): array
    {
        if (is_string($userId)) $userId = new ObjectId($userId);
        $docs = self::getCollection()->find([
            '$or' => [
                ['requester' => $userId, 'status' => 'accepted'],
                ['recipient' => $userId, 'status' => 'accepted'],
            ]
        ])->toArray();
        return array_map([self::class, 'formatDoc'], $docs);
    }

    public static function respond(string|ObjectId $id, string $status): bool
    {
        if (is_string($id)) $id = new ObjectId($id);
        $result = self::getCollection()->updateOne(
            ['_id' => $id],
            ['$set' => ['status' => $status]]
        );
        return $result->getModifiedCount() > 0;
    }

    public static function formatDoc($doc): array
    {
        if ($doc === null) return [];
        $arr = $doc instanceof \MongoDB\Model\BSONDocument ? $doc->toArray() : (array)$doc;
        if (isset($arr['_id'])) $arr['_id'] = (string)$arr['_id'];
        if (isset($arr['requester']) && $arr['requester'] instanceof ObjectId) $arr['requester'] = (string)$arr['requester'];
        if (isset($arr['recipient']) && $arr['recipient'] instanceof ObjectId) $arr['recipient'] = (string)$arr['recipient'];
        if (isset($arr['listing']) && $arr['listing'] instanceof ObjectId) $arr['listing'] = (string)$arr['listing'];
        return $arr;
    }
}
