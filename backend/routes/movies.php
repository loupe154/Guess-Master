<?php
if ($method !== 'GET') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

if (TMDB_API_KEY === 'PASTE_TMDB_KEY_HERE') {
    jsonResponse(['error' => 'TMDB API key missing in backend/config/api_keys.php'], 500);
}

$page = random_int(1, 5);
$url = 'https://api.themoviedb.org/3/movie/popular?api_key=' . urlencode(TMDB_API_KEY) . '&page=' . $page;

$response = @file_get_contents($url);
if ($response === false) {
    jsonResponse(['error' => 'TMDB request failed'], 502);
}

$data = json_decode($response, true);
if (!isset($data['results']) || !is_array($data['results']) || count($data['results']) === 0) {
    jsonResponse(['error' => 'TMDB returned no movies'], 502);
}

$movie = $data['results'][array_rand($data['results'])];
$posterPath = $movie['poster_path'] ?? '';

jsonResponse([
    'title' => $movie['title'] ?? 'Unknown movie',
    'image' => $posterPath ? 'https://image.tmdb.org/t/p/w500' . $posterPath : '',
    'hint' => 'Release date: ' . ($movie['release_date'] ?? 'unknown'),
    'overview' => $movie['overview'] ?? ''
]);
