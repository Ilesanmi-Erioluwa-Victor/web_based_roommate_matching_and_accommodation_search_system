<?php

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . '/../vendor/autoload.php';

use RoomieMatch\Config\Env;
use RoomieMatch\Config\Database;
use RoomieMatch\Controllers\AuthController;
use RoomieMatch\Controllers\UserController;
use RoomieMatch\Controllers\ListingController;
use RoomieMatch\Controllers\MatchController;
use RoomieMatch\Controllers\ConnectionController;
use RoomieMatch\Controllers\MessageController;
use RoomieMatch\Controllers\ReviewController;
use RoomieMatch\Controllers\ReportController;
use RoomieMatch\Controllers\AdminController;
use RoomieMatch\Controllers\SeedController;

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($uri === '/api/health') {
    if (file_exists(__DIR__ . '/../.env')) {
        Env::load(__DIR__ . '/../.env');
    }
    try {
        $db = Database::getConnection();
        $db->command(['ping' => 1]);
    } catch (\Exception $e) {}
    echo json_encode(['status' => 'ok', 'timestamp' => time()]);
    exit;
}

$envPath = __DIR__ . '/../.env';
if (file_exists($envPath)) {
    Env::load($envPath);
}

try {
    if (Env::get('APP_ENV') !== 'development' || !empty(Env::get('MONGODB_URI'))) {
        $db = Database::getConnection();
        $db->command(['ping' => 1]);
    }
} catch (\Exception $e) {
    if (Env::get('APP_ENV') !== 'development') {
        http_response_code(500);
        echo json_encode(['error' => 'Database connection failed.']);
        exit;
    }
}
$method = $_SERVER['REQUEST_METHOD'];

function matchRoute(string $uri, string $pattern): ?array {
    $pattern = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[a-zA-Z0-9_]+)', $pattern);
    $pattern = '#^' . $pattern . '$#';
    if (preg_match($pattern, $uri, $matches)) {
        return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
    }
    return null;
}

try {
    switch (true) {
        case $uri === '/' || $uri === '/index.php':
            header('Content-Type: text/html; charset=utf-8');
            readfile(__DIR__ . '/../src/views/index.html');
            break;

        case $uri === '/api/auth/register' && $method === 'POST':
            AuthController::register();
            break;

        case $uri === '/api/auth/login' && $method === 'POST':
            AuthController::login();
            break;

        case $uri === '/api/auth/logout' && $method === 'POST':
            AuthController::logout();
            break;

        case $uri === '/api/auth/forgot-password' && $method === 'POST':
            AuthController::forgotPassword();
            break;

        case $uri === '/api/auth/reset-password' && $method === 'POST':
            AuthController::resetPassword();
            break;

        case $uri === '/verify-email' && $method === 'GET':
            AuthController::verifyEmail();
            break;

        case $uri === '/api/users/me' && $method === 'GET':
            UserController::getMe();
            break;

        case $uri === '/api/users/me/profile' && $method === 'PATCH':
            UserController::updateProfile();
            break;

        case $uri === '/api/users/me/lifestyle' && $method === 'PATCH':
            UserController::updateLifestyle();
            break;

        case $uri === '/api/users/me/matching-status' && $method === 'PATCH':
            UserController::updateMatchingStatus();
            break;

        case $uri === '/api/users/me/profile-photo' && $method === 'POST':
            UserController::uploadProfilePhoto();
            break;

        case $uri === '/api/users/me/delete-account' && $method === 'POST':
            UserController::deleteAccount();
            break;

        case $uri === '/api/users/me/saved-listings' && $method === 'GET':
            ListingController::getSaved();
            break;

        case $uri === '/api/users/me/listings' && $method === 'GET':
            ListingController::getByUser();
            break;

        case ($params = matchRoute($uri, '/api/users/{id}/block')) !== null && $method === 'POST':
            UserController::blockUser($params['id']);
            break;

        case ($params = matchRoute($uri, '/api/users/{id}/unblock')) !== null && $method === 'POST':
            UserController::unblockUser($params['id']);
            break;

        case $uri === '/api/listings' && $method === 'GET':
            ListingController::index();
            break;

        case $uri === '/api/listings' && $method === 'POST':
            ListingController::store();
            break;

        case ($params = matchRoute($uri, '/api/listings/{id}')) !== null && $method === 'GET':
            ListingController::show($params['id']);
            break;

        case ($params = matchRoute($uri, '/api/listings/{id}')) !== null && $method === 'PATCH':
            ListingController::update($params['id']);
            break;

        case ($params = matchRoute($uri, '/api/listings/{id}')) !== null && $method === 'DELETE':
            ListingController::destroy($params['id']);
            break;

        case ($params = matchRoute($uri, '/api/listings/{id}/photos')) !== null && $method === 'POST':
            ListingController::uploadPhotos($params['id']);
            break;

        case ($params = matchRoute($uri, '/api/listings/{id}/photos/{publicId}')) !== null && $method === 'DELETE':
            ListingController::deletePhoto($params['id'], $params['publicId']);
            break;

        case ($params = matchRoute($uri, '/api/listings/{id}/favorite')) !== null && $method === 'POST':
            ListingController::toggleFavorite($params['id']);
            break;

        case ($params = matchRoute($uri, '/api/listings/{id}/compatibility')) !== null && $method === 'GET':
            ListingController::compatibility($params['id']);
            break;

        case $uri === '/api/matches/roommates' && $method === 'GET':
            MatchController::getRoommates();
            break;

        case $uri === '/api/connections' && $method === 'POST':
            ConnectionController::sendRequest();
            break;

        case $uri === '/api/connections/pending' && $method === 'GET':
            ConnectionController::getPending();
            break;

        case $uri === '/api/connections/accepted' && $method === 'GET':
            ConnectionController::getAccepted();
            break;

        case ($params = matchRoute($uri, '/api/connections/{id}/respond')) !== null && $method === 'PATCH':
            ConnectionController::respond($params['id']);
            break;

        case ($params = matchRoute($uri, '/api/connections/{id}/messages')) !== null && $method === 'GET':
            MessageController::index($params['id']);
            break;

        case ($params = matchRoute($uri, '/api/connections/{id}/messages')) !== null && $method === 'POST':
            MessageController::store($params['id']);
            break;

        case $uri === '/api/reviews' && $method === 'POST':
            ReviewController::store();
            break;

        case ($params = matchRoute($uri, '/api/reviews/user/{id}')) !== null && $method === 'GET':
            ReviewController::getUserReviews($params['id']);
            break;

        case ($params = matchRoute($uri, '/api/reviews/listing/{id}')) !== null && $method === 'GET':
            ReviewController::getListingReviews($params['id']);
            break;

        case $uri === '/api/reports' && $method === 'POST':
            ReportController::store();
            break;

        case $uri === '/api/admin/reports' && $method === 'GET':
            AdminController::getReports();
            break;

        case ($params = matchRoute($uri, '/api/admin/reports/{id}')) !== null && $method === 'PATCH':
            AdminController::updateReport($params['id']);
            break;

        case ($params = matchRoute($uri, '/api/admin/users/{id}/suspend')) !== null && $method === 'POST':
            AdminController::suspendUser($params['id']);
            break;

        case ($params = matchRoute($uri, '/api/admin/users/{id}/unsuspend')) !== null && $method === 'POST':
            AdminController::unsuspendUser($params['id']);
            break;

        case ($params = matchRoute($uri, '/api/admin/listings/{id}/remove')) !== null && $method === 'POST':
            AdminController::removeListing($params['id']);
            break;

        case ($params = matchRoute($uri, '/api/admin/users/{id}/verify')) !== null && $method === 'POST':
            AdminController::verifyUser($params['id']);
            break;

        case $uri === '/api/admin/users' && $method === 'GET':
            AdminController::getUsers();
            break;

        case $uri === '/api/admin/audit-logs' && $method === 'GET':
            AdminController::getAuditLogs();
            break;

        case $uri === '/api/admin/stats' && $method === 'GET':
            AdminController::getStats();
            break;

        case $uri === '/api/setup/ensure-indexes' && ($method === 'GET' || $method === 'POST'):
            try {
                $db = Database::getConnection();
                $db->command(['ping' => 1]);
                Database::ensureIndexes();
                echo json_encode(['message' => 'Database indexes ensured.']);
            } catch (\Exception $e) {
                http_response_code(500);
                echo json_encode(['error' => $e->getMessage()]);
            }
            break;

        case ($uri === '/api/seed' || $uri === '/api/seed/run') && ($method === 'GET' || $method === 'POST'):
            SeedController::seed();
            break;

        case $uri === '/api/setup/create-admin' && $method === 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            if (empty($data['email']) || empty($data['password']) || empty($data['name'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Name, email, and password required.']);
                break;
            }
            $existing = \RoomieMatch\Models\User::findByEmail($data['email']);
            if ($existing) {
                http_response_code(409);
                echo json_encode(['error' => 'User already exists.']);
                break;
            }
            $admin = \RoomieMatch\Models\User::create([
                'name' => $data['name'],
                'email' => strtolower($data['email']),
                'password' => $data['password'],
                'role' => 'admin',
                'isEmailVerified' => true,
                'isVerified' => true,
            ]);
            echo json_encode(['message' => 'Admin user created.', 'user' => $admin]);
            break;

        default:
            $filePath = __DIR__ . '/assets' . $uri;
            if ($uri !== '/index.php' && file_exists($filePath) && !is_dir($filePath)) {
                $ext = pathinfo($filePath, PATHINFO_EXTENSION);
                $mimeTypes = [
                    'css' => 'text/css',
                    'js' => 'application/javascript',
                    'png' => 'image/png',
                    'jpg' => 'image/jpeg',
                    'jpeg' => 'image/jpeg',
                    'webp' => 'image/webp',
                    'svg' => 'image/svg+xml',
                    'ico' => 'image/x-icon',
                ];
                if (isset($mimeTypes[$ext])) {
                    header('Content-Type: ' . $mimeTypes[$ext]);
                }
                readfile($filePath);
                break;
            }
            if (!str_starts_with($uri, '/api/')) {
                header('Content-Type: text/html; charset=utf-8');
                readfile(__DIR__ . '/../src/views/index.html');
                break;
            }
            http_response_code(404);
            echo json_encode(['error' => 'Not found.']);
            break;
    }
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
