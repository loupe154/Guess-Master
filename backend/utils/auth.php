<?php

declare(strict_types=1);

function requireAuthenticatedUser(): array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (empty($_SESSION['user']['id'])) {
        jsonResponse(['error' => 'Bitte zuerst einloggen.'], 401);
    }

    return [
        'id' => (int) $_SESSION['user']['id'],
        'name' => (string) ($_SESSION['user']['name'] ?? 'Benutzer'),
        'email' => (string) ($_SESSION['user']['email'] ?? ''),
    ];
}

function readJsonBody(): array
{
    $rawBody = file_get_contents('php://input');
    if ($rawBody === false || trim($rawBody) === '') {
        return [];
    }

    $data = json_decode($rawBody, true);
    if (!is_array($data)) {
        jsonResponse(['error' => 'Ungueltige JSON-Anfrage.'], 400);
    }

    return $data;
}
