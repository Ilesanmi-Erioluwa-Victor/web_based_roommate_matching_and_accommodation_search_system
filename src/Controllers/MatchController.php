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
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(50, max(1, (int)($_GET['limit'] ?? 10)));

        $engine = new CompatibilityEngine();
        $candidates = User::findCompatible([
            'excludeIds' => [$currentUser['_id'], ...($currentUser['blockedUsers'] ?? [])],
        ]);

        $ranked = $engine->rankUsers($currentUser, $candidates);

        $filtered = array_values(array_filter($ranked, fn($r) => !in_array($r['user']['_id'], $currentUser['blockedUsers'] ?? [])));

        $total = count($filtered);
        $offset = ($page - 1) * $limit;
        $matches = array_slice($filtered, $offset, $limit);

        echo json_encode([
            'matches' => $matches,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => max(1, ceil($total / $limit)),
        ]);
    }
}
