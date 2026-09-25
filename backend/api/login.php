<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/db.php';

function redirectWithMessage(string $location, string $type, string $message, array $old = []): never
{
    $separator = str_contains($location, '?') ? '&' : '?';
    header('Location: ' . $location . $separator . $type . '=' . urlencode($message));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectWithMessage('../../frontend/index.html?tab=login', 'error', 'Ungueltige Anfrage.');
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if ($email === '' || $password === '') {
    redirectWithMessage('../../frontend/index.html?tab=login', 'error', 'Bitte E-Mail und Passwort eingeben.');
}

try {
    $pdo = getDatabaseConnection();

    $stmt = $pdo->prepare('SELECT id, name, email, password_hash FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        redirectWithMessage('../../frontend/index.html?tab=login', 'error', 'Login fehlgeschlagen. Bitte pruefe deine Daten.');
    }

    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => $user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
    ];

    header('Location: ../../frontend/index.html?page=home');
    exit;
} catch (PDOException $exception) {
    redirectWithMessage(
        '../../frontend/index.html?tab=login',
        'error',
        'Datenbankfehler: Bitte pruefe deine Konfiguration in backend/api/db.php.'
    );
}
