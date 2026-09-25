<?php
if ($method !== 'GET') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$locations = [
    ['title' => 'Paris', 'country' => 'France', 'queries' => ['Paris skyline', 'Paris street', 'Eiffel Tower Paris'], 'answers' => ['Paris']],
    ['title' => 'New York', 'country' => 'United States', 'queries' => ['New York skyline', 'Times Square New York', 'Statue of Liberty New York'], 'answers' => ['New York', 'New York City', 'NYC']],
    ['title' => 'Rome', 'country' => 'Italy', 'queries' => ['Rome city', 'Colosseum Rome', 'Rome street'], 'answers' => ['Rome', 'Roma']],
    ['title' => 'London', 'country' => 'United Kingdom', 'queries' => ['London skyline', 'Big Ben London', 'London street'], 'answers' => ['London']],
    ['title' => 'Agra', 'country' => 'India', 'queries' => ['Agra India', 'Taj Mahal Agra', 'Agra city'], 'answers' => ['Agra']],
    ['title' => 'Sydney', 'country' => 'Australia', 'queries' => ['Sydney skyline', 'Sydney Opera House', 'Sydney harbour'], 'answers' => ['Sydney']],
    ['title' => 'Dubai', 'country' => 'United Arab Emirates', 'queries' => ['Dubai skyline', 'Burj Khalifa Dubai', 'Dubai city'], 'answers' => ['Dubai']],
    ['title' => 'Tokyo', 'country' => 'Japan', 'queries' => ['Tokyo skyline', 'Shibuya Tokyo', 'Tokyo city'], 'answers' => ['Tokyo']],
    ['title' => 'Berlin', 'country' => 'Germany', 'queries' => ['Berlin city', 'Brandenburg Gate Berlin', 'Berlin skyline'], 'answers' => ['Berlin']],
    ['title' => 'Barcelona', 'country' => 'Spain', 'queries' => ['Barcelona city', 'Sagrada Familia Barcelona', 'Barcelona street'], 'answers' => ['Barcelona']],
    ['title' => 'Cairo', 'country' => 'Egypt', 'queries' => ['Cairo city', 'Giza pyramids Cairo', 'Cairo skyline'], 'answers' => ['Cairo', 'Kairo']],
    ['title' => 'Rio de Janeiro', 'country' => 'Brazil', 'queries' => ['Rio de Janeiro city', 'Christ the Redeemer Rio', 'Rio beach'], 'answers' => ['Rio de Janeiro', 'Rio']],
];

$location = $locations[array_rand($locations)];
$image = '';
if (defined('UNSPLASH_ACCESS_KEY') && UNSPLASH_ACCESS_KEY !== '' && UNSPLASH_ACCESS_KEY !== 'PASTE_UNSPLASH_KEY_HERE') {
    $query = $location['queries'][array_rand($location['queries'])];
    $page = random_int(1, 4);
    $url = 'https://api.unsplash.com/search/photos?query=' . urlencode($query) . '&orientation=landscape&per_page=20&page=' . $page . '&client_id=' . urlencode(UNSPLASH_ACCESS_KEY);
    $response = @file_get_contents($url);
    $data = $response === false ? null : json_decode($response, true);
    if (!empty($data['results']) && is_array($data['results'])) {
        $photos = array_values(array_filter($data['results'], fn($photo) => !empty($photo['urls']['regular'])));
        if ($photos) {
            $photo = $photos[array_rand($photos)];
            $image = (string) $photo['urls']['regular'];
        }
    }
}

$fallbacks = [
    'Paris' => 'https://images.unsplash.com/photo-1543349689-9a4d426bee8e?auto=format&fit=crop&w=1200&q=80',
    'New York' => 'https://images.unsplash.com/photo-1485738422979-f5c462d49f74?auto=format&fit=crop&w=1200&q=80',
    'Rome' => 'https://images.unsplash.com/photo-1552832230-c0197dd311b5?auto=format&fit=crop&w=1200&q=80',
    'London' => 'https://images.unsplash.com/photo-1513635269975-59663e0ac1ad?auto=format&fit=crop&w=1200&q=80',
    'Agra' => 'https://images.unsplash.com/photo-1564507592333-c60657eea523?auto=format&fit=crop&w=1200&q=80',
    'Sydney' => 'https://images.unsplash.com/photo-1506973035872-a4ec16b8e8d9?auto=format&fit=crop&w=1200&q=80',
];

jsonResponse([
    'title' => $location['title'],
    'landmark' => $location['title'],
    'image' => $image ?: ($fallbacks[$location['title']] ?? $fallbacks['Paris']),
    'hint' => 'Country: ' . $location['country'],
    'extra_hint' => 'Country: ' . $location['country'],
    'answers' => $location['answers'],
]);