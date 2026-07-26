<?php

namespace RoomieMatch\Models;

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use RoomieMatch\Config\Database;

class Listing
{
    public static function getCollection(): \MongoDB\Collection
    {
        return Database::getConnection()->listings;
    }

    public static function create(array $data): array
    {
        $now = new UTCDateTime();
        $expiresAt = new UTCDateTime((time() + 60 * 24 * 60 * 60) * 1000);

        $doc = array_merge([
            'lister' => $data['lister'],
            'title' => '',
            'description' => '',
            'address' => ['fullAddress' => '', 'area' => '', 'city' => '', 'state' => ''],
            'location' => null,
            'price' => 0,
            'currency' => 'NGN',
            'pricePeriod' => 'monthly',
            'amenities' => [],
            'roomType' => 'shared_room',
            'totalRoommatesNeeded' => 1,
            'currentOccupants' => [],
            'photos' => [],
            'status' => 'active',
            'isVerified' => false,
            'verificationDoc' => null,
            'availableFrom' => null,
            'viewsCount' => 0,
            'favoritesCount' => 0,
            'createdAt' => $now,
            'updatedAt' => $now,
            'expiresAt' => $expiresAt,
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

    public static function findByLister(string|ObjectId $listerId): array
    {
        if (is_string($listerId)) $listerId = new ObjectId($listerId);
        $docs = self::getCollection()->find(['lister' => $listerId])->toArray();
        return array_map([self::class, 'formatDoc'], $docs);
    }

    public static function update(string|ObjectId $id, array $data): bool
    {
        if (is_string($id)) $id = new ObjectId($id);
        $data['updatedAt'] = new UTCDateTime();
        $result = self::getCollection()->updateOne(
            ['_id' => $id],
            ['$set' => $data]
        );
        return $result->getModifiedCount() > 0 || $result->getUpsertedCount() > 0;
    }

    public static function delete(string|ObjectId $id): bool
    {
        if (is_string($id)) $id = new ObjectId($id);
        $result = self::getCollection()->deleteOne(['_id' => $id]);
        return $result->getDeletedCount() > 0;
    }

    public static function search(array $filters, int $page = 1, int $limit = 20): array
    {
        $pipeline = [];
        $match = ['status' => 'active'];

        if (isset($filters['expired'])) {
            $match['$or'] = [
                ['expiresAt' => ['$gte' => new UTCDateTime()]],
                ['expiresAt' => null],
            ];
        }

        if (!empty($filters['priceMin']) || !empty($filters['priceMax'])) {
            $priceMatch = [];
            if (!empty($filters['priceMin'])) $priceMatch['$gte'] = (float)$filters['priceMin'];
            if (!empty($filters['priceMax'])) $priceMatch['$lte'] = (float)$filters['priceMax'];
            $match['price'] = $priceMatch;
        }

        if (!empty($filters['roomType'])) {
            $match['roomType'] = $filters['roomType'];
        }

        if (!empty($filters['amenities'])) {
            $amenities = is_string($filters['amenities']) ? explode(',', $filters['amenities']) : $filters['amenities'];
            $match['amenities'] = ['$all' => $amenities];
        }

        if (!empty($filters['listerId'])) {
            $match['lister'] = new ObjectId($filters['listerId']);
        }

        if (!empty($filters['text'])) {
            $escaped = preg_quote($filters['text'], '/');
            $match['$or'] = [
                ['title' => ['$regex' => $escaped, '$options' => 'i']],
                ['description' => ['$regex' => $escaped, '$options' => 'i']],
                ['address.area' => ['$regex' => $escaped, '$options' => 'i']],
                ['address.city' => ['$regex' => $escaped, '$options' => 'i']],
            ];
        }

        $pipeline[] = ['$match' => $match];

        $geoNear = null;
        if (!empty($filters['lat']) && !empty($filters['lng'])) {
            $radius = !empty($filters['radius']) ? (float)$filters['radius'] : 10;
            $geoNear = [
                'near' => ['type' => 'Point', 'coordinates' => [(float)$filters['lng'], (float)$filters['lat']]],
                'distanceField' => 'distance',
                'maxDistance' => $radius * 1000,
                'spherical' => true,
            ];
        }

        $sort = [];
        if (!empty($filters['sort'])) {
            switch ($filters['sort']) {
                case 'price_asc': $sort['price'] = 1; break;
                case 'price_desc': $sort['price'] = -1; break;
                case 'newest': $sort['createdAt'] = -1; break;
                case 'distance': $sort['distance'] = 1; break;
                default: $sort['createdAt'] = -1;
            }
        } else {
            $sort['createdAt'] = -1;
        }

        $skip = ($page - 1) * $limit;

        $totalPipeline = $pipeline;
        $totalPipeline[] = ['$count' => 'total'];
        $totalResult = self::getCollection()->aggregate($totalPipeline)->toArray();
        $total = $totalResult[0]['total'] ?? 0;

        if ($geoNear) {
            $geoPipeline = [
                ['$geoNear' => $geoNear],
            ];
            $geoPipeline = array_merge($geoPipeline, $pipeline);
            $geoPipeline[] = ['$sort' => $sort];
            $geoPipeline[] = ['$skip' => $skip];
            $geoPipeline[] = ['$limit' => $limit];
            $docs = self::getCollection()->aggregate($geoPipeline)->toArray();
        } else {
            $pipeline[] = ['$sort' => $sort];
            $pipeline[] = ['$skip' => $skip];
            $pipeline[] = ['$limit' => $limit];
            $docs = self::getCollection()->aggregate($pipeline)->toArray();
        }

        return [
            'listings' => array_map([self::class, 'formatDoc'], $docs),
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit),
        ];
    }

    public static function addPhoto(string|ObjectId $id, array $photo): bool
    {
        if (is_string($id)) $id = new ObjectId($id);
        $result = self::getCollection()->updateOne(
            ['_id' => $id],
            ['$push' => ['photos' => $photo]]
        );
        return $result->getModifiedCount() > 0;
    }

    public static function removePhoto(string|ObjectId $id, string $publicId): bool
    {
        if (is_string($id)) $id = new ObjectId($id);
        $result = self::getCollection()->updateOne(
            ['_id' => $id],
            ['$pull' => ['photos' => ['publicId' => $publicId]]]
        );
        return $result->getModifiedCount() > 0;
    }

    public static function incrementViews(string|ObjectId $id): void
    {
        if (is_string($id)) $id = new ObjectId($id);
        self::getCollection()->updateOne(
            ['_id' => $id],
            ['$inc' => ['viewsCount' => 1]]
        );
    }

    public static function expireStale(): int
    {
        $result = self::getCollection()->updateMany(
            [
                'status' => 'active',
                'expiresAt' => ['$lt' => new UTCDateTime()]
            ],
            ['$set' => ['status' => 'expired', 'updatedAt' => new UTCDateTime()]]
        );
        return $result->getModifiedCount();
    }

    public static function formatDoc($doc): array
    {
        if ($doc === null) return [];
        $arr = \RoomieMatch\Config\BsonHelper::toArray($doc);
        if (isset($arr['_id'])) $arr['_id'] = (string)$arr['_id'];
        if (isset($arr['lister']) && $arr['lister'] instanceof ObjectId) $arr['lister'] = (string)$arr['lister'];
        if (isset($arr['currentOccupants']) && is_array($arr['currentOccupants'])) {
            $arr['currentOccupants'] = array_map(fn($id) => (string)$id, $arr['currentOccupants']);
        }
        return $arr;
    }
}
