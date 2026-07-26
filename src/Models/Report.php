<?php

namespace RoomieMatch\Models;

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use RoomieMatch\Config\Database;

class Report
{
    public static function getCollection(): \MongoDB\Collection
    {
        return Database::getConnection()->reports;
    }

    public static function create(array $data): array
    {
        $doc = array_merge([
            'reporter' => $data['reporter'],
            'targetType' => $data['targetType'],
            'targetId' => $data['targetId'],
            'reason' => '',
            'details' => '',
            'status' => 'pending',
            'createdAt' => new UTCDateTime(),
        ], $data);

        $result = self::getCollection()->insertOne($doc);
        $doc['_id'] = (string)$result->getInsertedId();
        return self::formatDoc($doc);
    }

    public static function findPending(): array
    {
        $docs = self::getCollection()->find(['status' => 'pending'], ['sort' => ['createdAt' => -1]])->toArray();
        return array_map([self::class, 'formatDoc'], $docs);
    }

    public static function findAll(array $filters = [], int $page = 1, int $limit = 20): array
    {
        $match = [];
        if (!empty($filters['status'])) $match['status'] = $filters['status'];
        if (!empty($filters['targetType'])) $match['targetType'] = $filters['targetType'];

        $total = self::getCollection()->countDocuments($match);
        $docs = self::getCollection()->find(
            $match,
            ['sort' => ['createdAt' => -1], 'skip' => ($page - 1) * $limit, 'limit' => $limit]
        )->toArray();

        return [
            'reports' => array_map([self::class, 'formatDoc'], $docs),
            'total' => $total,
            'page' => $page,
            'pages' => ceil($total / $limit),
        ];
    }

    public static function updateStatus(string|ObjectId $id, string $status): bool
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
        $arr = $doc instanceof \MongoDB\Model\BSONDocument ? $doc->getArrayCopy() : (array)$doc;
        if (isset($arr['_id'])) $arr['_id'] = (string)$arr['_id'];
        if (isset($arr['reporter']) && $arr['reporter'] instanceof ObjectId) $arr['reporter'] = (string)$arr['reporter'];
        return $arr;
    }
}
