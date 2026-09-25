<?php
require_once __DIR__ . '/../config/api_keys.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/auth.php';

$route = $_GET['route'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

requireAuthenticatedUser();

switch ($route) {
    case 'random-game':
        require __DIR__ . '/../routes/games.php';
        break;
    case 'random-movie':
        require __DIR__ . '/../routes/movies.php';
        break;
    case 'random-location':
        require __DIR__ . '/../routes/locations.php';
        break;
    case 'profile':
    case 'profile-save':
        require __DIR__ . '/../routes/profile.php';
        break;
    case 'shop':
    case 'shop-buy':
        require __DIR__ . '/../routes/shop.php';
        break;
    case 'pvp-create':
    case 'pvp-join':
    case 'pvp-ready':
    case 'pvp-state':
    case 'pvp-suggest':
    case 'pvp-submit':
    case 'pvp-chat-list':
    case 'pvp-chat-send':
    case 'pvp-leaderboard':
    case 'pvp-leave':
    case 'pvp-post-match':
        require __DIR__ . '/../routes/pvp.php';
        break;
    case 'single-ranking':
    case 'single-submit':
    case 'single-leaderboard':
        require __DIR__ . '/../routes/single.php';
        break;
    default:
        jsonResponse(['error' => 'Route not found', 'route' => $route, 'method' => $method], 404);
}

