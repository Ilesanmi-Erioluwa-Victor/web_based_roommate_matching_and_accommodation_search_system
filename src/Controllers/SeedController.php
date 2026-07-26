<?php

namespace RoomieMatch\Controllers;

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use RoomieMatch\Config\Database;

class SeedController
{
    public static function seed(): void
    {
        $db = Database::getConnection();
        $count = (int)($_GET['count'] ?? 15);
        $count = min($count, 50);

        $db->users->deleteMany([]);
        $db->listings->deleteMany([]);
        $db->connections->deleteMany([]);
        $db->messages->deleteMany([]);
        $db->reviews->deleteMany([]);
        $db->reports->deleteMany([]);

        $names = [
            'Chidi Okonkwo', 'Ngozi Eze', 'Emeka Okafor', 'Amara Nwachukwu', 'Tunde Balogun',
            'Folake Adeyemi', 'Bayo Ogundipe', 'Chioma Obi', 'Segun Alabi', 'Kemi Ogunlana',
            'Ifeanyi Nwosu', 'Zainab Bello', 'Yemi Ogunlesi', 'Ebere Nnamdi', 'Bisi Johnson',
            'Kayode Adewale', 'Rashidat Mohammed', 'Femi Olaoye', 'Simi Ogun', 'Uche Igwe',
            'Tolu Adepoju', 'Nkechi Okoro', 'Dotun Sanusi', 'Bola Ogunbiyi', 'Chinwe Okeke',
        ];

        $genders = ['male', 'female'];
        $sleepSchedules = ['early_bird', 'night_owl', 'flexible'];
        $guestFreqs = ['rarely', 'sometimes', 'often'];
        $workSchedules = ['9to5', 'night_shift', 'student', 'remote', 'mixed'];
        $roomTypes = ['self_contain', 'shared_room', 'whole_apartment', 'studio'];
        $areas = ['Ota', 'Sango', 'Idiroko', 'Atan', 'Ijoko', 'Abeokuta', 'Lagos', 'Ikeja', 'Yaba', 'Surulere'];
        $allAmenities = ['wifi', 'water_supply', 'generator', 'furnished', 'kitchen', 'parking', 'security', 'AC', 'bathroom', 'electricity'];

        $userIds = [];

        $adminUser = \RoomieMatch\Models\User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'password123',
            'role' => 'admin',
            'isEmailVerified' => true,
            'isVerified' => true,
        ]);
        $userIds[] = $adminUser['_id'];

        for ($i = 0; $i < $count; $i++) {
            $name = $names[$i % count($names)];
            $gender = $genders[array_rand($genders)];
            $firstName = explode(' ', $name)[0];
            $email = strtolower($firstName) . ($i + 1) . '@email.com';

            $cleanliness = rand(2, 5);
            $noiseLevel = rand(1, 5);
            $budgetMin = rand(15000, 50000);
            $budgetMax = $budgetMin + rand(20000, 100000);
            $smoker = (bool)rand(0, 1);
            $hasPets = (bool)rand(0, 1);
            $toleratesSmoking = $smoker ? true : (bool)rand(0, 1);
            $toleratesPets = $hasPets ? true : (bool)rand(0, 1);

            $sleepIdx = array_rand($sleepSchedules);
            $guestIdx = array_rand($guestFreqs);
            $workIdx = array_rand($workSchedules);

            $prefLocations = [];
            $numLocs = rand(1, 3);
            $areaKeys = array_rand($areas, min($numLocs, count($areas)));
            if (!is_array($areaKeys)) $areaKeys = [$areaKeys];
            foreach ($areaKeys as $k) $prefLocations[] = $areas[$k];

            $lat = 6.5 + (float)rand(-50, 50) / 100;
            $lng = 3.2 + (float)rand(-30, 30) / 100;

            $user = \RoomieMatch\Models\User::create([
                'name' => $name,
                'email' => $email,
                'password' => 'password123',
                'gender' => $gender,
                'phone' => '080' . str_pad((string)rand(10000000, 99999999), 8, '0', STR_PAD_LEFT),
                'role' => 'both',
                'isEmailVerified' => true,
                'isVerified' => true,
                'lifestyle' => [
                    'budgetMin' => $budgetMin, 'budgetMax' => $budgetMax, 'currency' => 'NGN',
                    'cleanliness' => $cleanliness, 'sleepSchedule' => $sleepSchedules[$sleepIdx],
                    'smoker' => $smoker, 'toleratesSmoking' => $toleratesSmoking,
                    'hasPets' => $hasPets, 'toleratesPets' => $toleratesPets,
                    'noiseLevel' => $noiseLevel, 'guestFrequency' => $guestFreqs[$guestIdx],
                    'workSchedule' => $workSchedules[$workIdx],
                    'dietaryPreference' => ['none', 'halal', 'vegetarian', 'vegan'][array_rand(['none', 'halal', 'vegetarian', 'vegan'])],
                    'religionPreference' => null,
                    'genderPreference' => ['any', 'same'][array_rand(['any', 'same'])],
                    'preferredLocations' => $prefLocations,
                    'additionalNotes' => '',
                ],
                'dealBreakers' => [
                    'noSmokers' => !$smoker && (bool)rand(0, 1),
                    'noPets' => !$hasPets && (bool)rand(0, 1),
                    'sameGenderOnly' => (bool)rand(0, 1),
                    'maxBudgetStrict' => (bool)rand(0, 1),
                ],
                'location' => ['type' => 'Point', 'coordinates' => [$lng, $lat]],
                'matchingStatus' => ['actively_looking', 'actively_looking', 'actively_looking', 'paused', 'found_roommate'][array_rand([0, 0, 0, 1, 2])],
            ]);

            $userIds[] = $user['_id'];
        }

        $listingTitles = [
            'Spacious self-contain in Ota', 'Shared room near Covenant University',
            '2-bedroom flat in Sango', 'Studio apartment with AC',
            'Room in shared apartment', 'Self-contain with generator',
            'Whole apartment in Abeokuta', 'Furnished studio in Ikeja',
            'Budget-friendly room in Yaba', 'Modern flat in Surulere',
            'Cozy room near campus', 'Self-contain with security',
            'Shared apartment in Lagos', 'Affordable studio in Ota',
            'Room in 3-bedroom flat', 'Executive studio apartment',
            'Single room in student hostel', 'Flat with 24/7 electricity',
            'Nice self-contain in Atan', 'Room for female roommate',
        ];

        $listingsCreated = 0;
        $listingIds = [];
        $listers = array_slice($userIds, 1, max(8, (int)($count / 2)));

        for ($i = 0; $i < min(count($listingTitles), count($listers)); $i++) {
            $listerId = $listers[$i % count($listers)];
            $area = $areas[array_rand($areas)];
            $city = in_array($area, ['Lagos', 'Ikeja', 'Yaba', 'Surulere']) ? 'Lagos' : 'Ogun';
            $lat = 6.5 + (float)rand(-50, 50) / 100;
            $lng = 3.2 + (float)rand(-30, 30) / 100;
            $price = rand(3, 30) * 10000;
            $amenityCount = rand(3, 7);
            $amenityKeys = array_rand($allAmenities, $amenityCount);
            if (!is_array($amenityKeys)) $amenityKeys = [$amenityKeys];
            $listingAmenities = [];
            foreach ($amenityKeys as $k) $listingAmenities[] = $allAmenities[$k];

            $numOccupants = rand(0, 3);
            $occupantIds = [];
            for ($j = 0; $j < $numOccupants; $j++) {
                $occIdx = rand(1, count($userIds) - 1);
                if ($occIdx !== array_search($listerId, $userIds)) {
                    $occupantIds[] = new ObjectId($userIds[$occIdx]);
                }
            }

            $listing = \RoomieMatch\Models\Listing::create([
                'lister' => new ObjectId($listerId),
                'title' => $listingTitles[$i],
                'description' => "A wonderful " . $roomTypes[array_rand($roomTypes)] . " located in $area. Great for students and professionals. Close to market, transport, and schools. " . ($listingAmenities ? 'Comes with ' . implode(', ', $listingAmenities) . '.' : ''),
                'address' => [
                    'fullAddress' => rand(1, 100) . ', ' . $area . ' Road',
                    'area' => $area, 'city' => $city, 'state' => $city === 'Lagos' ? 'Lagos' : 'Ogun',
                ],
                'location' => ['type' => 'Point', 'coordinates' => [$lng, $lat]],
                'price' => $price,
                'pricePeriod' => 'monthly',
                'amenities' => $listingAmenities,
                'roomType' => $roomTypes[array_rand($roomTypes)],
                'totalRoommatesNeeded' => rand(1, 3),
                'currentOccupants' => $occupantIds,
                'isVerified' => (bool)rand(0, 1),
                'availableFrom' => new UTCDateTime((time() + rand(-86400 * 30, 86400 * 60)) * 1000),
            ]);

            $listingIds[] = $listing['_id'];
            $listingsCreated++;
        }

        $connPairs = [];
        for ($i = 1; $i < min(10, $count); $i += 2) {
            if ($i + 1 < count($userIds)) {
                $connPairs[] = [$userIds[$i], $userIds[$i + 1]];
            }
        }

        $connectionIds = [];
        foreach ($connPairs as $pair) {
            $conn = \RoomieMatch\Models\Connection::create([
                'requester' => new ObjectId($pair[0]),
                'recipient' => new ObjectId($pair[1]),
                'listing' => count($listingIds) > 0 ? new ObjectId($listingIds[array_rand($listingIds)]) : null,
                'status' => (bool)rand(0, 1) ? 'accepted' : 'pending',
            ]);
            $connectionIds[] = $conn['_id'];
        }

        foreach ($connectionIds as $connId) {
            $conn = \RoomieMatch\Models\Connection::findById($connId);
            if (!$conn || $conn['status'] !== 'accepted') continue;

            $msgCount = rand(1, 5);
            $parties = [$conn['requester'], $conn['recipient']];
            for ($m = 0; $m < $msgCount; $m++) {
                $sender = $parties[$m % 2];
                $greetings = ['Hi', 'Hello', 'Hey', "What's up", 'Good day'];
                $questions = ['How are you?', 'Is the room still available?', 'When can I view?', 'What time works for viewing?', 'Are there any other fees?'];
                $replies = ["I'm good, thanks!", "Yes, still available.", "How about tomorrow?", "Anytime after 3pm works.", "No hidden fees."];
                $msgs = array_merge($greetings, $questions, $replies);

                \RoomieMatch\Models\Message::create([
                    'connection' => new ObjectId($connId),
                    'sender' => new ObjectId($sender),
                    'content' => $msgs[array_rand($msgs)],
                ]);
            }
        }

        for ($i = 1; $i < min(8, count($userIds)); $i += 2) {
            if ($i + 1 < count($userIds)) {
                \RoomieMatch\Models\Review::create([
                    'reviewer' => new ObjectId($userIds[$i]),
                    'reviewee' => new ObjectId($userIds[$i + 1]),
                    'rating' => rand(3, 5),
                    'comment' => ['Great roommate!', 'Very clean and tidy.', 'Would live with again.', 'Nice person, respectful.', 'Okay experience.'][array_rand([0, 1, 2, 3, 4])],
                ]);
            }
        }

        echo json_encode([
            'message' => "Database seeded successfully.",
            'stats' => [
                'users' => count($userIds),
                'listings' => $listingsCreated,
                'connections' => count($connectionIds),
                'reviews' => min(8, (int)(count($userIds) / 2)),
            ]
        ]);
    }
}
