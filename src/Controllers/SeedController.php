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
        $count = min((int)($_GET['count'] ?? 25), 60);

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
                    'cleanliness' => $cleanliness, 'sleepSchedule' => $sleepSchedules[array_rand($sleepSchedules)],
                    'smoker' => $smoker, 'toleratesSmoking' => $toleratesSmoking,
                    'hasPets' => $hasPets, 'toleratesPets' => $toleratesPets,
                    'noiseLevel' => $noiseLevel, 'guestFrequency' => $guestFreqs[array_rand($guestFreqs)],
                    'workSchedule' => $workSchedules[array_rand($workSchedules)],
                    'dietaryPreference' => ['none', 'none', 'halal', 'vegetarian', 'vegan'][array_rand([0, 0, 1, 2, 3])],
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
                'matchingStatus' => ['actively_looking', 'actively_looking', 'paused', 'found_roommate'][array_rand([0, 0, 1, 2])],
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
            'Spacious flat in Abeokuta', 'Self-contain with parking',
            'Cozy studio in Ikeja', 'Room in Yaba for students',
            'Affordable 1-bedroom in Sango', 'Apartment with generator',
            'Shared room in Ota', 'Furnished flat in Surulere',
            'Studio with kitchen in Lagos', 'Big room in Idiroko',
        ];

        $descriptions = [
            'Fully furnished and ready for immediate move-in. Close to schools, markets, and major transport routes.',
            'Well-maintained property with 24-hour security and constant water supply. Perfect for students and young professionals.',
            'Quiet neighborhood with easy access to all amenities. Generous living space with modern finishes.',
            'Newly renovated with premium fittings. Includes generator backup and fast WiFi connection.',
            'Affordable living in a prime location. Walking distance to shopping centers and public transport.',
            'Spacious rooms with great natural lighting. Landlord lives on premises for added security.',
            'Serene environment ideal for studying and relaxation. Gated compound with dedicated parking.',
            'Modern apartment complex with gym and communal areas. Close to major business districts.',
            'Budget-friendly option without compromising on quality. All basic amenities included in rent.',
            'Executive living space with premium furnishings. Ideal for professionals seeking comfort and convenience.',
        ];

        $listingIds = [];
        $listers = array_slice($userIds, 1);
        $totalListings = min(count($listingTitles), count($listers));

        for ($i = 0; $i < $totalListings; $i++) {
            $listerId = $listers[$i % count($listers)];
            $area = $areas[array_rand($areas)];
            $city = in_array($area, ['Lagos', 'Ikeja', 'Yaba', 'Surulere']) ? 'Lagos' : 'Ogun';
            $lat = 6.5 + (float)rand(-50, 50) / 100;
            $lng = 3.2 + (float)rand(-30, 30) / 100;
            $price = rand(3, 35) * 10000;
            $amenityCount = rand(3, 7);
            $amenityKeys = array_rand($allAmenities, $amenityCount);
            if (!is_array($amenityKeys)) $amenityKeys = [$amenityKeys];
            $listingAmenities = [];
            foreach ($amenityKeys as $k) $listingAmenities[] = $allAmenities[$k];

            $numOccupants = rand(0, 3);
            $occupantIds = [];
            for ($j = 0; $j < $numOccupants; $j++) {
                $occIdx = rand(1, count($userIds) - 1);
                if ((string)$userIds[$occIdx] !== (string)$listerId) {
                    $occupantIds[] = new ObjectId($userIds[$occIdx]);
                }
            }

            $roomType = $roomTypes[array_rand($roomTypes)];
            $title = $listingTitles[$i];
            $desc = $descriptions[array_rand($descriptions)];

            $listing = \RoomieMatch\Models\Listing::create([
                'lister' => new ObjectId($listerId),
                'title' => $title,
                'description' => "$desc Located in $area — $roomType.",
                'address' => [
                    'fullAddress' => rand(1, 100) . ', ' . $area . ' Road',
                    'area' => $area, 'city' => $city, 'state' => $city === 'Lagos' ? 'Lagos' : 'Ogun',
                ],
                'location' => ['type' => 'Point', 'coordinates' => [$lng, $lat]],
                'price' => $price,
                'pricePeriod' => 'monthly',
                'amenities' => $listingAmenities,
                'roomType' => $roomType,
                'totalRoommatesNeeded' => rand(1, 4),
                'currentOccupants' => $occupantIds,
                'isVerified' => (bool)rand(0, 1),
                'availableFrom' => new UTCDateTime((time() + rand(-86400 * 30, 86400 * 90)) * 1000),
            ]);

            $id = (string)$listing['_id'];
            $listingIds[] = $id;

            $photoCount = rand(1, 3);
            $photos = [];
            for ($p = 0; $p < $photoCount; $p++) {
                $seed = "listing_{$id}_{$p}";
                $photos[] = [
                    'url' => "https://picsum.photos/seed/{$seed}/800/600",
                    'publicId' => "seed_{$id}_{$p}",
                ];
            }
            \RoomieMatch\Models\Listing::update($id, ['photos' => $photos]);
        }


        $connPairs = [];
        for ($i = 1; $i < min($count, 30); $i += 2) {
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
            $phrases = [
                'Hi there!', 'Hello', 'Hey!', "How's it going?",
                'Is the room still available?', 'When can I come view?',
                'What time works for you?', 'Are utilities included?',
                "I'm interested in the room.", 'How many roommates are there?',
                'Is there parking?', 'What is the neighborhood like?',
                "Sure, let's set up a viewing.", 'Sounds good!',
                'Yes, it is still available.', 'Anytime after 4pm works.',
                'Water and electricity are included.', 'There are 2 other roommates.',
                'Street parking is available.', 'It is a quiet area.',
            ];
            for ($m = 0; $m < $msgCount; $m++) {
                $sender = $parties[$m % 2];
                \RoomieMatch\Models\Message::create([
                    'connection' => new ObjectId($connId),
                    'sender' => new ObjectId($sender),
                    'content' => $phrases[array_rand($phrases)],
                ]);
            }
        }

        for ($i = 1; $i < min($count, 16); $i += 2) {
            if ($i + 1 < count($userIds)) {
                \RoomieMatch\Models\Review::create([
                    'reviewer' => new ObjectId($userIds[$i]),
                    'reviewee' => new ObjectId($userIds[$i + 1]),
                    'rating' => rand(3, 5),
                    'comment' => ['Great roommate! Very tidy and respectful.', 'Clean and organized. Would recommend.', 'Awesome person, very friendly.', 'Respectful of shared spaces.', 'Good roommate experience overall.'][array_rand([0, 1, 2, 3, 4])],
                ]);
            }
        }

        echo json_encode([
            'message' => 'Database seeded successfully.',
            'stats' => [
                'users' => count($userIds),
                'listings' => count($listingIds),
                'connections' => count($connectionIds),
                'reviews' => min(16, (int)(count($userIds) / 2)),
            ]
        ]);
    }
}
