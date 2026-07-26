<?php

namespace RoomieMatch\Models;

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use RoomieMatch\Config\Database;

class User
{
    public static function getCollection(): \MongoDB\Collection
    {
        return Database::getConnection()->users;
    }

    public static function create(array $data): array
    {
        $data['passwordHash'] = password_hash($data['password'], PASSWORD_BCRYPT);
        unset($data['password']);

        $now = new UTCDateTime();
        $doc = array_merge([
            'name' => '',
            'email' => '',
            'phone' => null,
            'gender' => null,
            'dateOfBirth' => null,
            'role' => 'both',
            'isVerified' => false,
            'isEmailVerified' => false,
            'isSuspended' => false,
            'profilePhotoUrl' => null,
            'profilePhotoPublicId' => null,
            'lifestyle' => [
                'budgetMin' => null,
                'budgetMax' => null,
                'currency' => 'NGN',
                'cleanliness' => null,
                'sleepSchedule' => null,
                'smoker' => false,
                'toleratesSmoking' => false,
                'hasPets' => false,
                'toleratesPets' => false,
                'noiseLevel' => null,
                'guestFrequency' => null,
                'workSchedule' => null,
                'dietaryPreference' => null,
                'religionPreference' => null,
                'genderPreference' => 'any',
                'preferredLocations' => [],
                'additionalNotes' => '',
            ],
            'dealBreakers' => [
                'noSmokers' => false,
                'noPets' => false,
                'sameGenderOnly' => false,
                'maxBudgetStrict' => false,
            ],
            'matchingStatus' => 'actively_looking',
            'location' => null,
            'blockedUsers' => [],
            'savedListings' => [],
            'createdAt' => $now,
            'updatedAt' => $now,
        ], $data);

        $result = self::getCollection()->insertOne($doc);
        $doc['_id'] = (string)$result->getInsertedId();
        return self::formatDoc($doc);
    }

    public static function findByEmail(string $email): ?array
    {
        $doc = self::getCollection()->findOne(['email' => strtolower($email)]);
        return $doc ? self::formatDoc($doc) : null;
    }

    public static function findById(string|ObjectId $id): ?array
    {
        if (is_string($id)) $id = new ObjectId($id);
        $doc = self::getCollection()->findOne(['_id' => $id]);
        return $doc ? self::formatDoc($doc) : null;
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

    public static function findCompatible(array $criteria): array
    {
        $pipeline = [];

        $pipeline[] = ['$match' => ['matchingStatus' => 'actively_looking']];

        if (!empty($criteria['excludeIds'])) {
            $pipeline[] = ['$match' => ['_id' => ['$nin' => array_map(fn($id) => new ObjectId($id), $criteria['excludeIds'])]]];
        }

        if (isset($criteria['gender'])) {
            $match = ['$or' => [
                ['lifestyle.genderPreference' => 'any'],
                ['lifestyle.genderPreference' => $criteria['gender']]
            ]];
            $pipeline[] = ['$match' => $match];
        }

        $pipeline[] = ['$match' => ['isSuspended' => false]];
        $pipeline[] = ['$match' => ['isEmailVerified' => true]];

        $users = self::getCollection()->aggregate($pipeline)->toArray();
        return array_map([self::class, 'formatDoc'], $users);
    }

    public static function addBlockedUser(string|ObjectId $userId, string|ObjectId $blockedId): bool
    {
        if (is_string($userId)) $userId = new ObjectId($userId);
        if (is_string($blockedId)) $blockedId = new ObjectId($blockedId);
        $result = self::getCollection()->updateOne(
            ['_id' => $userId],
            ['$addToSet' => ['blockedUsers' => $blockedId]]
        );
        return $result->getModifiedCount() > 0;
    }

    public static function removeBlockedUser(string|ObjectId $userId, string|ObjectId $blockedId): bool
    {
        if (is_string($userId)) $userId = new ObjectId($userId);
        if (is_string($blockedId)) $blockedId = new ObjectId($blockedId);
        $result = self::getCollection()->updateOne(
            ['_id' => $userId],
            ['$pull' => ['blockedUsers' => $blockedId]]
        );
        return $result->getModifiedCount() > 0;
    }

    public static function toggleSavedListing(string|ObjectId $userId, string|ObjectId $listingId): bool
    {
        if (is_string($userId)) $userId = new ObjectId($userId);
        if (is_string($listingId)) $listingId = new ObjectId($listingId);
        $user = self::findById($userId);
        if (!$user) return false;

        $listingObjectId = $listingId;
        if (in_array((string)$listingObjectId, $user['savedListings'])) {
            $result = self::getCollection()->updateOne(
                ['_id' => $userId],
                ['$pull' => ['savedListings' => $listingObjectId]]
            );
        } else {
            $result = self::getCollection()->updateOne(
                ['_id' => $userId],
                ['$addToSet' => ['savedListings' => $listingObjectId]]
            );
        }
        return $result->getModifiedCount() > 0;
    }

    public static function anonymize(string|ObjectId $id): bool
    {
        if (is_string($id)) $id = new ObjectId($id);
        $result = self::getCollection()->updateOne(
            ['_id' => $id],
            ['$set' => [
                'name' => 'Deleted User',
                'email' => 'deleted_' . (string)$id . '@deleted.roomiematch',
                'phone' => null,
                'profilePhotoUrl' => null,
                'profilePhotoPublicId' => null,
                'isVerified' => false,
                'isEmailVerified' => false,
                'isSuspended' => true,
                'matchingStatus' => 'found_roommate',
                'lifestyle' => [
                    'budgetMin' => null, 'budgetMax' => null, 'currency' => 'NGN',
                    'cleanliness' => null, 'sleepSchedule' => null,
                    'smoker' => false, 'toleratesSmoking' => false,
                    'hasPets' => false, 'toleratesPets' => false,
                    'noiseLevel' => null, 'guestFrequency' => null,
                    'workSchedule' => null, 'dietaryPreference' => null,
                    'religionPreference' => null, 'genderPreference' => 'any',
                    'preferredLocations' => [], 'additionalNotes' => '',
                ],
                'dealBreakers' => [
                    'noSmokers' => false, 'noPets' => false,
                    'sameGenderOnly' => false, 'maxBudgetStrict' => false,
                ],
                'updatedAt' => new UTCDateTime(),
            ]]
        );
        return $result->getModifiedCount() > 0;
    }

    public static function formatDoc($doc): array
    {
        if ($doc === null) return [];
        $arr = $doc instanceof \MongoDB\Model\BSONDocument ? json_decode(json_encode($doc), true) : (array)$doc;
        if (isset($arr['_id'])) {
            $arr['_id'] = (string)$arr['_id'];
        }
        if (isset($arr['blockedUsers']) && is_array($arr['blockedUsers'])) {
            $arr['blockedUsers'] = array_map(fn($id) => (string)$id, $arr['blockedUsers']);
        }
        if (isset($arr['savedListings']) && is_array($arr['savedListings'])) {
            $arr['savedListings'] = array_map(fn($id) => (string)$id, $arr['savedListings']);
        }
        return $arr;
    }
}
