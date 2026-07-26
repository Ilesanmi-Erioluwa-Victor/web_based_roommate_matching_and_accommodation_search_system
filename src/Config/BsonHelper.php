<?php

namespace RoomieMatch\Config;

class BsonHelper
{
    public static function toArray($doc): array
    {
        if ($doc === null) return [];

        if ($doc instanceof \MongoDB\Model\BSONDocument) {
            $result = [];
            foreach ($doc as $key => $value) {
                $result[$key] = self::convertValue($value);
            }
            return $result;
        }

        if ($doc instanceof \MongoDB\Model\BSONArray) {
            return array_map([self::class, 'convertValue'], iterator_to_array($doc));
        }

        if (is_array($doc)) {
            $result = [];
            foreach ($doc as $key => $value) {
                $result[$key] = self::convertValue($value);
            }
            return $result;
        }

        if (is_object($doc)) {
            return self::toArray((array)$doc);
        }

        return [];
    }

    private static function convertValue($value): mixed
    {
        if ($value instanceof \MongoDB\Model\BSONDocument) {
            return self::toArray($value);
        }
        if ($value instanceof \MongoDB\Model\BSONArray) {
            return array_map([self::class, 'convertValue'], iterator_to_array($value));
        }
        if ($value instanceof \MongoDB\BSON\ObjectId) {
            return (string)$value;
        }
        if ($value instanceof \MongoDB\BSON\UTCDateTime) {
            return $value->toDateTime()->format('c');
        }
        if (is_array($value) || is_object($value)) {
            return self::toArray($value);
        }
        return $value;
    }
}
