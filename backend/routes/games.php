<?php
if ($method !== 'GET') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

if (RAWG_API_KEY === 'PASTE_RAWG_KEY_HERE') {
    jsonResponse(['error' => 'RAWG API key missing in backend/config/api_keys.php'], 500);
}

$page = random_int(1, 5);
$url = 'https://api.rawg.io/api/games?key=' . urlencode(RAWG_API_KEY) . '&page=' . $page . '&page_size=20';

$response = @file_get_contents($url);
if ($response === false) {
    jsonResponse(['error' => 'RAWG request failed'], 502);
}

$data = json_decode($response, true);
if (!isset($data['results']) || !is_array($data['results']) || count($data['results']) === 0) {
    jsonResponse(['error' => 'RAWG returned no games'], 502);
}

$game = $data['results'][array_rand($data['results'])];
$genres = [];
if (!empty($game['genres']) && is_array($game['genres'])) {
    foreach ($game['genres'] as $genre) {
        if (!empty($genre['name'])) {
            $genres[] = $genre['name'];
        }
    }
}

jsonResponse([
    'title' => $game['name'] ?? 'Unknown game',
    'image' => $game['background_image'] ?? '',
    'hint' => !empty($genres) ? 'Genre: ' . implode(', ', array_slice($genres, 0, 2)) : 'Release: ' . ($game['released'] ?? 'unknown'),
    'released' => $game['released'] ?? null
]);
