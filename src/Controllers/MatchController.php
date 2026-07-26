<?php

namespace RoomieMatch\Controllers;

use RoomieMatch\Middleware\Auth;
use RoomieMatch\Models\AuditLog;
use RoomieMatch\Models\User;
use RoomieMatch\Services\CompatibilityEngine;

class MatchController
{
    public static function getRoommates(): void
    {
        $currentUser = Auth::requireAuth();
        $engine = new CompatibilityEngine();
        $candidates = User::findCompatible([
            'excludeIds' => [$currentUser['_id'], ...($currentUser['blockedUsers'] ?? [])],
        ]);

        $ranked = $engine->rankUsers($currentUser, $candidates);

        $filtered = array_filter($ranked, fn($r) => !in_array($r['user']['_id'], $currentUser['blockedUsers'] ?? []));

        echo json_encode([
            'matches' => array_values($filtered),
            'count' => count($filtered),
        ]);
    }
}
