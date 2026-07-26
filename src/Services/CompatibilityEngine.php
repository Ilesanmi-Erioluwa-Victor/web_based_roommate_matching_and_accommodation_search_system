<?php

namespace RoomieMatch\Services;

use RoomieMatch\Models\User;

class CompatibilityEngine
{
    private const WEIGHTS = [
        'budget' => 20,
        'cleanliness' => 15,
        'sleepSchedule' => 15,
        'smoking' => 10,
        'pets' => 10,
        'noise' => 10,
        'guestFrequency' => 10,
        'workSchedule' => 5,
        'location' => 5,
    ];

    private const LIFESTYLE_FIELDS = [
        'budgetMin', 'budgetMax', 'cleanliness', 'sleepSchedule',
        'smoker', 'toleratesSmoking', 'hasPets', 'toleratesPets',
        'noiseLevel', 'guestFrequency', 'workSchedule',
    ];

    public function calculate(array $userA, array $userB): array
    {
        $result = [
            'score' => 0,
            'passedDealBreakers' => false,
            'dealBreakerFailures' => [],
            'isPartial' => false,
            'categoryScores' => [],
        ];

        $dealBreakerCheck = $this->checkDealBreakers($userA, $userB);
        if (!$dealBreakerCheck['passed']) {
            $result['dealBreakerFailures'] = $dealBreakerCheck['failures'];
            return $result;
        }

        $result['passedDealBreakers'] = true;

        $completenessA = $this->profileCompleteness($userA);
        $completenessB = $this->profileCompleteness($userB);

        if ($completenessA < 0.7 || $completenessB < 0.7) {
            $result['isPartial'] = true;
        }

        $totalWeightedScore = 0;
        $totalWeight = 0;

        $categoryScores = [];
        $categoryScores['budget'] = $this->calculateBudgetOverlap($userA, $userB);
        $categoryScores['cleanliness'] = $this->calculateNumericSimilarity(
            $userA['lifestyle']['cleanliness'] ?? null,
            $userB['lifestyle']['cleanliness'] ?? null,
            5
        );
        $categoryScores['sleepSchedule'] = $this->calculateSleepScheduleSimilarity(
            $userA['lifestyle']['sleepSchedule'] ?? null,
            $userB['lifestyle']['sleepSchedule'] ?? null
        );
        $categoryScores['smoking'] = $this->calculateSmokingCompatibility($userA, $userB);
        $categoryScores['pets'] = $this->calculatePetsCompatibility($userA, $userB);
        $categoryScores['noise'] = $this->calculateNumericSimilarity(
            $userA['lifestyle']['noiseLevel'] ?? null,
            $userB['lifestyle']['noiseLevel'] ?? null,
            5
        );
        $categoryScores['guestFrequency'] = $this->calculateGuestFrequencySimilarity(
            $userA['lifestyle']['guestFrequency'] ?? null,
            $userB['lifestyle']['guestFrequency'] ?? null
        );
        $categoryScores['workSchedule'] = $this->calculateWorkScheduleSimilarity(
            $userA['lifestyle']['workSchedule'] ?? null,
            $userB['lifestyle']['workSchedule'] ?? null
        );
        $categoryScores['location'] = $this->calculateLocationProximity($userA, $userB);

        foreach (self::WEIGHTS as $category => $weight) {
            if ($categoryScores[$category] !== null) {
                $totalWeightedScore += $weight * $categoryScores[$category];
                $totalWeight += $weight;
            }
        }

        $result['score'] = $totalWeight > 0 ? round(($totalWeightedScore / $totalWeight) * 100, 1) : 0;
        $result['categoryScores'] = $categoryScores;

        return $result;
    }

    public function calculateAggregate(array $userA, array $occupants): array
    {
        $individualScores = [];
        $totalScore = 0;
        $count = 0;

        foreach ($occupants as $occupant) {
            $score = $this->calculate($userA, $occupant);
            $individualScores[] = [
                'userId' => $occupant['_id'],
                'userName' => $occupant['name'],
                'score' => $score,
            ];
            if ($score['passedDealBreakers']) {
                $totalScore += $score['score'];
                $count++;
            }
        }

        return [
            'individualScores' => $individualScores,
            'aggregateScore' => $count > 0 ? round($totalScore / $count, 1) : 0,
            'matchingOccupants' => $count,
            'totalOccupants' => count($occupants),
        ];
    }

    public function rankUsers(array $currentUser, array $candidates): array
    {
        $results = [];

        foreach ($candidates as $candidate) {
            if ($candidate['_id'] === $currentUser['_id']) continue;
            if (in_array($candidate['_id'], $currentUser['blockedUsers'] ?? [])) continue;
            if (in_array($currentUser['_id'], $candidate['blockedUsers'] ?? [])) continue;

            $score = $this->calculate($currentUser, $candidate);
            $results[] = [
                'user' => $candidate,
                'compatibility' => $score,
            ];
        }

        usort($results, function ($a, $b) {
            return ($b['compatibility']['score'] ?? 0) <=> ($a['compatibility']['score'] ?? 0);
        });

        return $results;
    }

    private function checkDealBreakers(array $userA, array $userB): array
    {
        $failures = [];

        $dbA = $userA['dealBreakers'] ?? [];
        $dbB = $userB['dealBreakers'] ?? [];
        $lA = $userA['lifestyle'] ?? [];
        $lB = $userB['lifestyle'] ?? [];

        if (($dbA['noSmokers'] ?? false) && ($lB['smoker'] ?? false)) {
            $failures[] = 'noSmokers';
        }
        if (($dbB['noSmokers'] ?? false) && ($lA['smoker'] ?? false)) {
            $failures[] = 'noSmokers';
        }

        if (($dbA['noPets'] ?? false) && ($lB['hasPets'] ?? false)) {
            $failures[] = 'noPets';
        }
        if (($dbB['noPets'] ?? false) && ($lA['hasPets'] ?? false)) {
            $failures[] = 'noPets';
        }

        if (($dbA['sameGenderOnly'] ?? false) && ($userA['gender'] ?? '') !== ($userB['gender'] ?? '')) {
            $failures[] = 'sameGenderOnly';
        }
        if (($dbB['sameGenderOnly'] ?? false) && ($userA['gender'] ?? '') !== ($userB['gender'] ?? '')) {
            $failures[] = 'sameGenderOnly';
        }

        if (($dbA['maxBudgetStrict'] ?? false)) {
            $budgetA = ['min' => $lA['budgetMin'] ?? 0, 'max' => $lA['budgetMax'] ?? PHP_FLOAT_MAX];
            $budgetB = ['min' => $lB['budgetMin'] ?? 0, 'max' => $lB['budgetMax'] ?? PHP_FLOAT_MAX];
            if (!$this->rangesOverlap($budgetA, $budgetB)) {
                $failures[] = 'budgetStrict';
            }
        }
        if (($dbB['maxBudgetStrict'] ?? false)) {
            $budgetA = ['min' => $lA['budgetMin'] ?? 0, 'max' => $lA['budgetMax'] ?? PHP_FLOAT_MAX];
            $budgetB = ['min' => $lB['budgetMin'] ?? 0, 'max' => $lB['budgetMax'] ?? PHP_FLOAT_MAX];
            if (!$this->rangesOverlap($budgetA, $budgetB)) {
                $failures[] = 'budgetStrict';
            }
        }

        return [
            'passed' => empty($failures),
            'failures' => $failures,
        ];
    }

    private function rangesOverlap(array $a, array $b): bool
    {
        return max($a['min'], $b['min']) <= min($a['max'], $b['max']);
    }

    private function calculateBudgetOverlap(array $userA, array $userB): ?float
    {
        $bA = $userA['lifestyle'] ?? [];
        $bB = $userB['lifestyle'] ?? [];

        $minA = $bA['budgetMin'] ?? null;
        $maxA = $bA['budgetMax'] ?? null;
        $minB = $bB['budgetMin'] ?? null;
        $maxB = $bB['budgetMax'] ?? null;

        if ($minA === null || $maxA === null || $minB === null || $maxB === null) return null;

        $rangeA = $maxA - $minA;
        $rangeB = $maxB - $minB;

        if ($rangeA <= 0 || $rangeB <= 0) return $minA === $minB && $maxA === $maxB ? 1.0 : 0.0;

        $overlapMin = max($minA, $minB);
        $overlapMax = min($maxA, $maxB);

        if ($overlapMin >= $overlapMax) return 0.0;

        $overlapSize = $overlapMax - $overlapMin;
        $unionMax = max($maxA, $maxB);
        $unionMin = min($minA, $minB);
        $unionSize = $unionMax - $unionMin;

        if ($unionSize <= 0) return 0.0;

        return min(1.0, $overlapSize / $unionSize);
    }

    private function calculateNumericSimilarity(?int $a, ?int $b, int $maxScale): ?float
    {
        if ($a === null || $b === null) return null;
        return 1.0 - (abs($a - $b) / ($maxScale - 1));
    }

    private function calculateSleepScheduleSimilarity(?string $a, ?string $b): ?float
    {
        if ($a === null || $b === null) return null;
        if ($a === $b) return 1.0;
        if ($a === 'flexible' || $b === 'flexible') return 0.5;

        $opposites = ['early_bird' => 'night_owl', 'night_owl' => 'early_bird'];
        if (($opposites[$a] ?? null) === $b) return 0.0;

        return 0.5;
    }

    private function calculateSmokingCompatibility(array $userA, array $userB): ?float
    {
        $lA = $userA['lifestyle'] ?? [];
        $lB = $userB['lifestyle'] ?? [];

        $smokerA = $lA['smoker'] ?? false;
        $smokerB = $lB['smoker'] ?? false;
        $tolA = $lA['toleratesSmoking'] ?? false;
        $tolB = $lB['toleratesSmoking'] ?? false;

        if (!$smokerA && !$smokerB) return 1.0;
        if ($smokerA && $tolB && $smokerB && $tolA) return 1.0;
        if (!$smokerA && $tolB && $smokerB) return 0.7;
        if (!$smokerB && $tolA && $smokerA) return 0.7;
        if ($smokerA && $smokerB) return 0.5;

        return 0.0;
    }

    private function calculatePetsCompatibility(array $userA, array $userB): ?float
    {
        $lA = $userA['lifestyle'] ?? [];
        $lB = $userB['lifestyle'] ?? [];

        $petsA = $lA['hasPets'] ?? false;
        $petsB = $lB['hasPets'] ?? false;
        $tolA = $lA['toleratesPets'] ?? false;
        $tolB = $lB['toleratesPets'] ?? false;

        if (!$petsA && !$petsB) return 1.0;
        if ($petsA && $tolB && $petsB && $tolA) return 1.0;
        if (!$petsA && $tolB && $petsB) return 0.7;
        if (!$petsB && $tolA && $petsA) return 0.7;
        if ($petsA && $petsB) return 0.5;

        return 0.0;
    }

    private function calculateGuestFrequencySimilarity(?string $a, ?string $b): ?float
    {
        if ($a === null || $b === null) return null;
        if ($a === $b) return 1.0;

        $levels = ['rarely' => 0, 'sometimes' => 1, 'often' => 2];
        $valA = $levels[$a] ?? null;
        $valB = $levels[$b] ?? null;

        if ($valA === null || $valB === null) return 0.5;

        $diff = abs($valA - $valB);
        if ($diff === 1) return 0.5;

        return 0.0;
    }

    private function calculateWorkScheduleSimilarity(?string $a, ?string $b): ?float
    {
        if ($a === null || $b === null) return null;
        if ($a === $b) return 1.0;

        $compatible = [
            'remote' => ['student', 'remote', 'mixed'],
            'student' => ['remote', 'student', 'mixed'],
            'mixed' => ['remote', 'student', 'mixed', '9to5'],
            '9to5' => ['mixed', '9to5'],
            'night_shift' => ['night_shift'],
        ];

        if (in_array($b, $compatible[$a] ?? [])) return 0.5;
        return 0.0;
    }

    private function calculateLocationProximity(array $userA, array $userB): ?float
    {
        $prefA = $userA['lifestyle']['preferredLocations'] ?? [];
        $prefB = $userB['lifestyle']['preferredLocations'] ?? [];
        $locA = $userA['location'] ?? null;
        $locB = $userB['location'] ?? null;
        $lA = $userA['lifestyle'] ?? [];

        $locationsOverlap = !empty(array_intersect($prefA, $prefB));
        if ($locationsOverlap) return 1.0;

        if (!empty($prefA)) {
            $userALocations = $prefA;
        } else {
            $userALocations = $lA['preferredLocations'] ?? [];
        }

        if (!empty($userALocations) && !empty($prefB)) {
            if (!empty(array_intersect($userALocations, $prefB))) return 1.0;
        }

        if ($locA && $locB) {
            $dist = $this->haversineDistance(
                $locA['coordinates'][1], $locA['coordinates'][0],
                $locB['coordinates'][1], $locB['coordinates'][0]
            );
            if ($dist <= 5) return 1.0;
            if ($dist <= 20) return 0.7;
            if ($dist <= 50) return 0.3;
        }

        return $locationsOverlap ? 1.0 : 0.5;
    }

    private function haversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }

    public function profileCompleteness(array $user): float
    {
        $lifestyle = $user['lifestyle'] ?? [];
        $filled = 0;
        $total = count(self::LIFESTYLE_FIELDS);

        foreach (self::LIFESTYLE_FIELDS as $field) {
            $val = $lifestyle[$field] ?? null;
            if ($val !== null && $val !== '' && $val !== []) {
                $filled++;
            }
        }

        return $filled / $total;
    }
}
