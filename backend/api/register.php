<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/db.php';

function redirectWithMessage(string $location, string $type, string $message): never
{
    $separator = str_contains($location, '?') ? '&' : '?';
    header('Location: ' . $location . $separator . $type . '=' . urlencode($message));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectWithMessage('../../frontend/index.html?tab=register', 'error', 'Ungueltige Anfrage.');
}

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if ($name === '' || $email === '' || $password === '') {
    redirectWithMessage('../../frontend/index.html?tab=register', 'error', 'Bitte alle Felder ausfuellen.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirectWithMessage('../../frontend/index.html?tab=register', 'error', 'Bitte eine gueltige E-Mail eingeben.');
}

if (strlen($password) < 8) {
    redirectWithMessage('../../frontend/index.html?tab=register', 'error', 'Das Passwort muss mindestens 8 Zeichen lang sein.');
}

try {
    $pdo = getDatabaseConnection();
    $checkStmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $checkStmt->execute(['email' => $email]);
    if ($checkStmt->fetch()) {
        redirectWithMessage('../../frontend/index.html?tab=register', 'error', 'Diese E-Mail ist bereits registriert.');
    }

    $insertStmt = $pdo->prepare('INSERT INTO users (name, email, password_hash) VALUES (:name, :email, :password_hash)');
    $insertStmt->execute([
        'name' => $name,
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    ]);

    $userId = (int) $pdo->lastInsertId();
    $_SESSION['user'] = ['id' => $userId, 'name' => $name, 'email' => $email];
    header('Location: ../../frontend/index.html?page=profile&new=1');
    exit;
} catch (PDOException) {
    redirectWithMessage('../../frontend/index.html?tab=register', 'error', 'Datenbankfehler: Bitte pruefe deine Konfiguration in backend/api/db.php.');
}