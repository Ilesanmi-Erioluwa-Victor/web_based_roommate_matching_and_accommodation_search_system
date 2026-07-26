<?php

namespace RoomieMatch\Controllers;

use RoomieMatch\Middleware\Auth;
use RoomieMatch\Models\AuditLog;
use RoomieMatch\Models\Listing;
use RoomieMatch\Models\User;
use RoomieMatch\Services\CloudinaryService;

class ListingController
{
    public static function index(): void
    {
        $page = (int)($_GET['page'] ?? 1);
        $limit = (int)($_GET['limit'] ?? 20);

        $filters = [
            'text' => $_GET['text'] ?? null,
            'priceMin' => $_GET['priceMin'] ?? null,
            'priceMax' => $_GET['priceMax'] ?? null,
            'roomType' => $_GET['roomType'] ?? null,
            'amenities' => $_GET['amenities'] ?? null,
            'lat' => $_GET['lat'] ?? null,
            'lng' => $_GET['lng'] ?? null,
            'radius' => $_GET['radius'] ?? null,
            'sort' => $_GET['sort'] ?? 'newest',
            'expired' => !isset($_GET['showExpired']),
        ];

        $result = Listing::search($filters, $page, $limit);

        if (empty($result['listings'])) {
            $suggestion = '';

            if (!empty($filters['radius'])) {
                $suggestion = "No listings found within {$filters['radius']}km. Try increasing the search radius.";
                $filters['radius'] = (float)$filters['radius'] * 2;
                $widerResult = Listing::search($filters, $page, $limit);
                if (!empty($widerResult['listings'])) {
                    $suggestion .= " Found results at {$filters['radius']}km. Try searching with radius={$filters['radius']}";
                }
            } elseif (!empty($filters['priceMax']) || !empty($filters['priceMin'])) {
                $suggestion = 'No listings match your price range. Try adjusting your budget.';
            } else {
                $suggestion = 'No active listings found. Check back later or adjust your filters.';
            }

            $result['suggestion'] = $suggestion;
        }

        echo json_encode($result);
    }

    public static function show(string $id): void
    {
        $listing = Listing::findById($id);
        if (!$listing) {
            http_response_code(404);
            echo json_encode(['error' => 'Listing not found.']);
            return;
        }

        Listing::incrementViews($id);

        $lister = User::findById($listing['lister']);
        $listing['listerData'] = $lister ? [
            '_id' => $lister['_id'],
            'name' => $lister['name'],
            'profilePhotoUrl' => $lister['profilePhotoUrl'],
            'isVerified' => $lister['isVerified'],
            'rating' => null,
        ] : null;

        echo json_encode(['listing' => $listing]);
    }

    public static function store(): void
    {
        $user = Auth::requireAuth();

        $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
        $isMultipart = str_contains($contentType, 'multipart/form-data');

        if ($isMultipart) {
            $data = $_POST;
            $data['address'] = [
                'fullAddress' => $_POST['address']['fullAddress'] ?? '',
                'city' => $_POST['address']['city'] ?? '',
                'state' => $_POST['address']['state'] ?? '',
            ];
            if (!empty($_POST['amenities']) && is_array($_POST['amenities'])) {
                $data['amenities'] = $_POST['amenities'];
            }
            $data['price'] = (float)($_POST['price'] ?? 0);
            $data['totalRoommatesNeeded'] = (int)($_POST['totalRoommatesNeeded'] ?? 1);
        } else {
            $data = json_decode(file_get_contents('php://input'), true);
        }

        if (empty($data['title']) || empty($data['price'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Title and price are required.']);
            return;
        }

        $listing = Listing::create([
            'lister' => new \MongoDB\BSON\ObjectId($user['_id']),
            'title' => $data['title'],
            'description' => $data['description'] ?? '',
            'address' => $data['address'] ?? ['fullAddress' => '', 'area' => '', 'city' => '', 'state' => ''],
            'location' => $data['location'] ?? null,
            'price' => (float)$data['price'],
            'pricePeriod' => $data['pricePeriod'] ?? 'monthly',
            'amenities' => $data['amenities'] ?? [],
            'roomType' => $data['roomType'] ?? 'shared_room',
            'totalRoommatesNeeded' => (int)($data['totalRoommatesNeeded'] ?? 1),
            'availableFrom' => $data['availableFrom'] ?? null,
            'currentOccupants' => !empty($data['currentOccupants'])
                ? array_map(fn($id) => new \MongoDB\BSON\ObjectId($id), $data['currentOccupants'])
                : [],
        ]);

        if (isset($data['currentOccupantIds']) && is_array($data['currentOccupantIds'])) {
            Listing::update($listing['_id'], [
                'currentOccupants' => array_map(fn($id) => new \MongoDB\BSON\ObjectId($id), $data['currentOccupantIds'])
            ]);
        }

        if ($isMultipart && !empty($_FILES['photos'])) {
            $files = $_FILES['photos'];
            $cloudinary = new CloudinaryService();
            $id = (string)$listing['_id'];

            if (is_array($files['name'])) {
                $count = count($files['name']);
                for ($i = 0; $i < $count && $i < 8; $i++) {
                    if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
                    $file = [
                        'name' => $files['name'][$i],
                        'tmp_name' => $files['tmp_name'][$i],
                        'size' => $files['size'][$i],
                        'error' => $files['error'][$i],
                    ];
                    $validation = $cloudinary->validateUploadedFile($file);
                    if (!$validation['valid']) continue;
                    try {
                        $index = count(Listing::findById($id)['photos'] ?? []);
                        $result = $cloudinary->uploadListingPhoto($file['tmp_name'], $id, $index);
                        Listing::addPhoto($id, $result);
                    } catch (\Exception $e) {
                        continue;
                    }
                }
            }
        }

        $listing = Listing::findById((string)$listing['_id']);

        AuditLog::log($user['_id'], 'listing.create.' . $listing['_id'], $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '');
        echo json_encode(['message' => 'Listing created.', 'listing' => $listing], JSON_UNESCAPED_UNICODE);
    }

    public static function update(string $id): void
    {
        $user = Auth::requireAuth();
        $listing = Listing::findById($id);

        if (!$listing) {
            http_response_code(404);
            echo json_encode(['error' => 'Listing not found.']);
            return;
        }

        if ($listing['lister'] !== $user['_id']) {
            http_response_code(403);
            echo json_encode(['error' => 'You can only edit your own listings.']);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $update = [];

        $allowed = ['title', 'description', 'address', 'price', 'pricePeriod', 'amenities', 'roomType', 'totalRoommatesNeeded', 'status', 'availableFrom'];
        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                $update[$field] = $data[$field];
            }
        }

        if (isset($data['location'])) {
            $update['location'] = $data['location'];
        }

        if (isset($data['currentOccupantIds'])) {
            $update['currentOccupants'] = array_map(fn($oid) => new \MongoDB\BSON\ObjectId($oid), $data['currentOccupantIds']);
        }

        if (!empty($update)) {
            Listing::update($id, $update);
        }

        AuditLog::log($user['_id'], 'listing.update.' . $id, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '');
        echo json_encode(['message' => 'Listing updated.', 'listing' => Listing::findById($id)]);
    }

    public static function destroy(string $id): void
    {
        $user = Auth::requireAuth();
        $listing = Listing::findById($id);

        if (!$listing) {
            http_response_code(404);
            echo json_encode(['error' => 'Listing not found.']);
            return;
        }

        if ($listing['lister'] !== $user['_id']) {
            http_response_code(403);
            echo json_encode(['error' => 'You can only delete your own listings.']);
            return;
        }

        $cloudinary = new CloudinaryService();
        foreach ($listing['photos'] as $photo) {
            $cloudinary->deleteImage($photo['publicId']);
        }

        Listing::delete($id);
        AuditLog::log($user['_id'], 'listing.delete.' . $id, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '');
        echo json_encode(['message' => 'Listing deleted.']);
    }

    public static function uploadPhotos(string $id): void
    {
        $user = Auth::requireAuth();
        $listing = Listing::findById($id);

        if (!$listing) {
            http_response_code(404);
            echo json_encode(['error' => 'Listing not found.']);
            return;
        }

        if ($listing['lister'] !== $user['_id']) {
            http_response_code(403);
            echo json_encode(['error' => 'You can only add photos to your own listings.']);
            return;
        }

        if (count($listing['photos']) >= 8) {
            http_response_code(400);
            echo json_encode(['error' => 'Maximum 8 photos per listing.']);
            return;
        }

        if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['error' => 'Photo upload failed.']);
            return;
        }

        $cloudinary = new CloudinaryService();
        $validation = $cloudinary->validateUploadedFile($_FILES['photo']);
        if (!$validation['valid']) {
            http_response_code(400);
            echo json_encode(['error' => $validation['error']]);
            return;
        }

        try {
            $index = count($listing['photos']);
            $result = $cloudinary->uploadListingPhoto($_FILES['photo']['tmp_name'], $id, $index);
            Listing::addPhoto($id, $result);
            $listing = Listing::findById($id);

            echo json_encode(['message' => 'Photo added.', 'photos' => $listing['photos']]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Upload failed. Please try again.']);
        }
    }

    public static function deletePhoto(string $id, string $publicId): void
    {
        $user = Auth::requireAuth();
        $listing = Listing::findById($id);

        if (!$listing) {
            http_response_code(404);
            echo json_encode(['error' => 'Listing not found.']);
            return;
        }

        if ($listing['lister'] !== $user['_id']) {
            http_response_code(403);
            echo json_encode(['error' => 'You can only remove photos from your own listings.']);
            return;
        }

        $cloudinary = new CloudinaryService();
        $cloudinary->deleteImage($publicId);
        Listing::removePhoto($id, $publicId);

        echo json_encode(['message' => 'Photo removed.', 'photos' => Listing::findById($id)['photos']]);
    }

    public static function toggleFavorite(string $id): void
    {
        $user = Auth::requireAuth();
        $listing = Listing::findById($id);

        if (!$listing) {
            http_response_code(404);
            echo json_encode(['error' => 'Listing not found.']);
            return;
        }

        $wasSaved = in_array($id, $user['savedListings'] ?? []);
        User::toggleSavedListing($user['_id'], $id);

        echo json_encode([
            'message' => $wasSaved ? 'Removed from favorites.' : 'Added to favorites.',
            'isFavorite' => !$wasSaved,
        ]);
    }

    public static function getSaved(): void
    {
        $user = Auth::requireAuth();
        $listings = [];
        foreach ($user['savedListings'] ?? [] as $lid) {
            $listing = Listing::findById($lid);
            if ($listing) $listings[] = $listing;
        }
        echo json_encode(['listings' => $listings]);
    }

    public static function getByUser(): void
    {
        $user = Auth::requireAuth();
        $listings = Listing::findByLister($user['_id']);
        echo json_encode(['listings' => $listings]);
    }

    public static function compatibility(string $id): void
    {
        $currentUser = Auth::requireAuth();
        $listing = Listing::findById($id);

        if (!$listing) {
            http_response_code(404);
            echo json_encode(['error' => 'Listing not found.']);
            return;
        }

        $engine = new \RoomieMatch\Services\CompatibilityEngine();
        $occupants = [];

        foreach ($listing['currentOccupants'] as $occId) {
            $occ = User::findById($occId);
            if ($occ) $occupants[] = $occ;
        }

        if (empty($occupants)) {
            $lister = User::findById($listing['lister']);
            if ($lister) {
                $score = $engine->calculate($currentUser, $lister);
                echo json_encode([
                    'type' => 'lister_only',
                    'score' => $score,
                ]);
                return;
            }
        }

        $result = $engine->calculateAggregate($currentUser, $occupants);
        echo json_encode([
            'type' => 'multi_occupant',
            'result' => $result,
        ]);
    }
}
