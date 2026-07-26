<?php

namespace RoomieMatch\Models;

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use RoomieMatch\Config\Database;

class AuditLog
{
    public static function getCollection(): \MongoDB\Collection
    {
        return Database::getConnection()->auditLogs;
    }

    public static function log(string|ObjectId $userId, string $action, string $ipAddress = '', string $userAgent = ''): void
    {
        if (is_string($userId)) $userId = new ObjectId($userId);
        self::getCollection()->insertOne([
            'user' => $userId,
            'action' => $action,
            'ipAddress' => $ipAddress,
            'userAgent' => $userAgent,
            'timestamp' => new UTCDateTime(),
        ]);
    }

    public static function findAll(array $filters = [], int $page = 1, int $limit = 50): array
    {
        $match = [];
        if (!empty($filters['userId'])) $match['user'] = new ObjectId($filters['userId']);
        if (!empty($filters['action'])) $match['action'] = $filters['action'];

        $total = self::getCollection()->countDocuments($match);
        $docs = self::getCollection()->find(
            $match,
            ['sort' => ['timestamp' => -1], 'skip' => ($page - 1) * $limit, 'limit' => $limit]
        )->toArray();

        return [
            'logs' => array_map(function($doc) {
                $arr = $doc instanceof \MongoDB\Model\BSONDocument ? $doc->toArray() : (array)$doc;
                if (isset($arr['_id'])) $arr['_id'] = (string)$arr['_id'];
                if (isset($arr['user']) && $arr['user'] instanceof ObjectId) $arr['user'] = (string)$arr['user'];
                return $arr;
            }, $docs),
            'total' => $total,
            'page' => $page,
            'pages' => ceil($total / $limit),
        ];
    }
}
