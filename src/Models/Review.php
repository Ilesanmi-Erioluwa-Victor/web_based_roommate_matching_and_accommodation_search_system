<?php

namespace RoomieMatch\Models;

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use RoomieMatch\Config\Database;

class Review
{
    public static function getCollection(): \MongoDB\Collection
    {
        return Database::getConnection()->reviews;
    }

    public static function create(array $data): array
    {
        $doc = array_merge([
            'reviewer' => $data['reviewer'],
            'reviewee' => $data['reviewee'],
            'listing' => null,
            'rating' => 5,
            'comment' => '',
            'createdAt' => new UTCDateTime(),
        ], $data);

        $result = self::getCollection()->insertOne($doc);
        $doc['_id'] = (string)$result->getInsertedId();
        return self::formatDoc($doc);
    }

    public static function findByReviewee(string|ObjectId $revieweeId): array
    {
        if (is_string($revieweeId)) $revieweeId = new ObjectId($revieweeId);
        $docs = self::getCollection()->find(['reviewee' => $revieweeId], ['sort' => ['createdAt' => -1]])->toArray();
        return array_map([self::class, 'formatDoc'], $docs);
    }

    public static function findByListing(string|ObjectId $listingId): array
    {
        if (is_string($listingId)) $listingId = new ObjectId($listingId);
        $docs = self::getCollection()->find(['listing' => $listingId], ['sort' => ['createdAt' => -1]])->toArray();
        return array_map([self::class, 'formatDoc'], $docs);
    }

    public static function getAverageRating(string|ObjectId $revieweeId): ?float
    {
        if (is_string($revieweeId)) $revieweeId = new ObjectId($revieweeId);
        $result = self::getCollection()->aggregate([
            ['$match' => ['reviewee' => $revieweeId]],
            ['$group' => ['_id' => null, 'avg' => ['$avg' => '$rating'], 'count' => ['$sum' => 1]]]
        ])->toArray();
        return !empty($result) ? round($result[0]['avg'], 1) : null;
    }

    public static function hasReviewed(string|ObjectId $reviewerId, string|ObjectId $revieweeId): bool
    {
        if (is_string($reviewerId)) $reviewerId = new ObjectId($reviewerId);
        if (is_string($revieweeId)) $revieweeId = new ObjectId($revieweeId);
        return self::getCollection()->countDocuments(['reviewer' => $reviewerId, 'reviewee' => $revieweeId]) > 0;
    }

    public static function anonymizeUser(string|ObjectId $userId): int
    {
        if (is_string($userId)) $userId = new ObjectId($userId);
        $count = 0;
        $count += self::getCollection()->updateMany(
            ['reviewer' => $userId],
            ['$set' => ['comment' => '[Deleted]']]
        )->getModifiedCount();
        return $count;
    }

    public static function formatDoc($doc): array
    {
        if ($doc === null) return [];
        $arr = $doc instanceof \MongoDB\Model\BSONDocument ? $doc->getArrayCopy() : (array)$doc;
        if (isset($arr['_id'])) $arr['_id'] = (string)$arr['_id'];
        if (isset($arr['reviewer']) && $arr['reviewer'] instanceof ObjectId) $arr['reviewer'] = (string)$arr['reviewer'];
        if (isset($arr['reviewee']) && $arr['reviewee'] instanceof ObjectId) $arr['reviewee'] = (string)$arr['reviewee'];
        if (isset($arr['listing']) && $arr['listing'] instanceof ObjectId) $arr['listing'] = (string)$arr['listing'];
        return $arr;
    }
}
