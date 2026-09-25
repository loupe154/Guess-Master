<?php

declare(strict_types=1);

session_start();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user'])) {
    echo json_encode([
        'authenticated' => false,
    ]);
    exit;
}

echo json_encode([
    'authenticated' => true,
    'user' => [
        'id' => $_SESSION['user']['id'] ?? null,
        'name' => $_SESSION['user']['name'] ?? 'Benutzer',
        'email' => $_SESSION['user']['email'] ?? '',
    ],
]);
