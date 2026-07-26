<?php

namespace RoomieMatch\Controllers;

use RoomieMatch\Middleware\Auth;
use RoomieMatch\Models\Connection;
use RoomieMatch\Models\Review;

class ReviewController
{
    public static function store(): void
    {
        $user = Auth::requireAuth();
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['revieweeId']) || !isset($data['rating'])) {
            http_response_code(400);
            echo json_encode(['error' => 'revieweeId and rating are required.']);
            return;
        }

        if ($data['revieweeId'] === $user['_id']) {
            http_response_code(400);
            echo json_encode(['error' => 'Cannot review yourself.']);
            return;
        }

        $rating = (int)$data['rating'];
        if ($rating < 1 || $rating > 5) {
            http_response_code(400);
            echo json_encode(['error' => 'Rating must be between 1 and 5.']);
            return;
        }

        $connection = Connection::findBetweenUsers($user['_id'], $data['revieweeId']);
        if (!$connection || $connection['status'] !== 'accepted') {
            http_response_code(403);
            echo json_encode(['error' => 'You can only review users you have an accepted connection with.']);
            return;
        }

        if (Review::hasReviewed($user['_id'], $data['revieweeId'])) {
            http_response_code(409);
            echo json_encode(['error' => 'You have already reviewed this user.']);
            return;
        }

        $review = Review::create([
            'reviewer' => new \MongoDB\BSON\ObjectId($user['_id']),
            'reviewee' => new \MongoDB\BSON\ObjectId($data['revieweeId']),
            'listing' => !empty($data['listingId']) ? new \MongoDB\BSON\ObjectId($data['listingId']) : null,
            'rating' => $rating,
            'comment' => $data['comment'] ?? '',
        ]);

        echo json_encode(['message' => 'Review submitted.', 'review' => $review]);
    }

    public static function getUserReviews(string $userId): void
    {
        $reviews = Review::findByReviewee($userId);
        $avgRating = Review::getAverageRating($userId);

        echo json_encode([
            'reviews' => $reviews,
            'averageRating' => $avgRating,
            'totalReviews' => count($reviews),
        ]);
    }

    public static function getListingReviews(string $listingId): void
    {
        $reviews = Review::findByListing($listingId);

        $ratings = array_column($reviews, 'rating');
        $avg = !empty($ratings) ? round(array_sum($ratings) / count($ratings), 1) : null;

        echo json_encode([
            'reviews' => $reviews,
            'averageRating' => $avg,
            'totalReviews' => count($reviews),
        ]);
    }
}
