<?php

namespace RoomieMatch\Config;

use MongoDB\Client;
use MongoDB\Database as MongoDatabase;

class Database
{
    private static ?MongoDatabase $db = null;
    private static ?Client $client = null;

    public static function getConnection(): MongoDatabase
    {
        if (self::$db === null) {
            $uri = Env::get('MONGODB_URI');
            if (!$uri) {
                throw new \RuntimeException("MONGODB_URI environment variable is not set");
            }

            self::$client = new Client($uri);
            $dbName = 'roomiematch';
            if (preg_match('#/([^/?]+)(\?|$)#', $uri, $m)) {
                $dbName = $m[1];
            }
            self::$db = self::$client->selectDatabase($dbName);
        }
        return self::$db;
    }

    public static function getClient(): Client
    {
        if (self::$client === null) {
            self::getConnection();
        }
        return self::$client;
    }

    public static function ensureIndexes(): void
    {
        $db = self::getConnection();

        $users = $db->users;
        $users->createIndex(['email' => 1], ['unique' => true]);
        $users->createIndex(['location' => '2dsphere']);
        $users->createIndex(['matchingStatus' => 1]);

        $listings = $db->listings;
        $listings->createIndex(['location' => '2dsphere']);
        $listings->createIndex(['title' => 'text', 'description' => 'text']);
        $listings->createIndex(['status' => 1]);
        $listings->createIndex(['lister' => 1]);
        $listings->createIndex(['price' => 1]);
        $listings->createIndex(['roomType' => 1]);
        $listings->createIndex(['amenities' => 1]);

        $connections = $db->connections;
        $connections->createIndex(['requester' => 1, 'recipient' => 1]);
        $connections->createIndex(['status' => 1]);

        $messages = $db->messages;
        $messages->createIndex(['connection' => 1, 'createdAt' => 1]);

        $reviews = $db->reviews;
        $reviews->createIndex(['reviewee' => 1]);
        $reviews->createIndex(['listing' => 1]);

        $reports = $db->reports;
        $reports->createIndex(['status' => 1]);

        $auditLogs = $db->auditLogs;
        $auditLogs->createIndex(['user' => 1, 'timestamp' => -1]);
    }
}
