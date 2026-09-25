<?php

declare(strict_types=1);

require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../utils/auth.php';

function profileEnsureColumns(PDO $pdo): void
{
    $columns = [
        'game_name' => 'varchar(100) DEFAULT NULL',
        'avatar_skin' => "varchar(20) NOT NULL DEFAULT 'blue'",
        'avatar_eyes' => "varchar(20) NOT NULL DEFAULT 'calm'",
        'avatar_mouth' => "varchar(20) NOT NULL DEFAULT 'smile'",
        'avatar_accessory' => "varchar(20) NOT NULL DEFAULT 'none'",
        'avatar_shape' => "varchar(20) NOT NULL DEFAULT 'round'",
        'avatar_hair' => "varchar(20) NOT NULL DEFAULT 'none'",
        'avatar_beard' => "varchar(20) NOT NULL DEFAULT 'none'",
        'avatar_glasses' => "varchar(20) NOT NULL DEFAULT 'none'",
        'avatar_necklace' => "varchar(20) NOT NULL DEFAULT 'none'",
        'avatar_animation' => "varchar(20) NOT NULL DEFAULT 'none'",
        'placement_pvp_remaining' => 'int NOT NULL DEFAULT 0',
        'placement_single_remaining' => 'int NOT NULL DEFAULT 0',
    ];

    foreach ($columns as $column => $definition) {
        $stmt = $pdo->prepare('SELECT COUNT(*) found FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "users" AND COLUMN_NAME = :column');
        $stmt->execute(['column' => $column]);
        if ((int) ($stmt->fetch()['found'] ?? 0) === 0) {
            $pdo->exec('ALTER TABLE users ADD COLUMN ' . $column . ' ' . $definition);
        }
    }
}

function profileLoad(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        'SELECT id, name, email, COALESCE(NULLIF(game_name, ""), name) game_name,
                avatar_skin, avatar_eyes, avatar_mouth, avatar_accessory, avatar_shape,
                avatar_hair, avatar_beard, avatar_glasses, avatar_necklace, avatar_animation
         FROM users WHERE id = :id LIMIT 1'
    );
    $stmt->execute(['id' => $userId]);
    $row = $stmt->fetch();
    if (!$row) {
        jsonResponse(['error' => 'Profil wurde nicht gefunden.'], 404);
    }

    return [
        'id' => (int) $row['id'],
        'name' => (string) $row['name'],
        'email' => (string) $row['email'],
        'game_name' => (string) $row['game_name'],
        'avatar' => [
            'skin' => (string) $row['avatar_skin'],
            'eyes' => (string) $row['avatar_eyes'],
            'mouth' => (string) $row['avatar_mouth'],
            'accessory' => (string) $row['avatar_accessory'],
            'shape' => (string) $row['avatar_shape'],
            'hair' => (string) $row['avatar_hair'],
            'beard' => (string) $row['avatar_beard'],
            'glasses' => (string) $row['avatar_glasses'],
            'necklace' => (string) $row['avatar_necklace'],
            'animation' => (string) $row['avatar_animation'],
        ],
    ];
}

function profileOption(mixed $value, array $allowed, string $default): string
{
    $value = trim((string) $value);
    return in_array($value, $allowed, true) ? $value : $default;
}

function profileOwns(PDO $pdo, int $userId, string $field, string $value): bool
{
    $free = [
        'skin' => ['blue', 'green', 'red', 'gold', 'purple'],
        'accessory' => ['none', 'cap', 'crown', 'headset', 'beanie'],
        'hair' => ['none', 'short', 'spiky', 'curly', 'side'],
        'beard' => ['none', 'stubble', 'goatee', 'full'],
        'glasses' => ['none', 'round', 'square', 'visor'],
        'necklace' => ['none'],
        'animation' => ['none'],
    ];
    if (in_array($value, $free[$field] ?? [], true)) {
        return true;
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT owned.item_id
             FROM shop_items item
             JOIN user_shop_items owned ON owned.item_id = item.id
             WHERE owned.user_id = :user_id AND item.field = :field AND item.value = :value
             LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId, 'field' => $field, 'value' => $value]);
        return (bool) $stmt->fetch();
    } catch (Throwable) {
        return false;
    }
}

$pdo = getDatabaseConnection();
$user = requireAuthenticatedUser();
profileEnsureColumns($pdo);

if ($route === 'profile' && $method === 'GET') {
    jsonResponse(['profile' => profileLoad($pdo, (int) $user['id'])]);
}

if ($route === 'profile-save' && $method === 'POST') {
    $body = readJsonBody();
    $gameName = trim((string) ($body['game_name'] ?? ''));
    $avatar = is_array($body['avatar'] ?? null) ? $body['avatar'] : [];
    if ($gameName === '') {
        jsonResponse(['error' => 'Bitte einen In-Game-Namen eingeben.'], 400);
    }

    $dupe = $pdo->prepare('SELECT id FROM users WHERE LOWER(COALESCE(NULLIF(game_name, ""), name)) = LOWER(:name) AND id <> :id LIMIT 1');
    $dupe->execute(['name' => $gameName, 'id' => (int) $user['id']]);
    if ($dupe->fetch()) {
        jsonResponse(['error' => 'The name is already in use.'], 409);
    }

    $values = [
        'skin' => profileOption($avatar['skin'] ?? '', ['blue', 'green', 'red', 'gold', 'purple', 'ice', 'shadow', 'ruby', 'galaxy'], 'blue'),
        'eyes' => profileOption($avatar['eyes'] ?? '', ['calm', 'focus', 'happy'], 'calm'),
        'mouth' => profileOption($avatar['mouth'] ?? '', ['smile', 'serious', 'open'], 'smile'),
        'accessory' => profileOption($avatar['accessory'] ?? '', ['none', 'cap', 'crown', 'headset', 'beanie', 'fedora', 'halo', 'mask', 'wizard', 'legend_crown'], 'none'),
        'shape' => profileOption($avatar['shape'] ?? '', ['round', 'oval', 'square', 'hex'], 'round'),
        'hair' => profileOption($avatar['hair'] ?? '', ['none', 'short', 'spiky', 'curly', 'side', 'mohawk', 'long', 'flame', 'wave', 'legend_flame'], 'none'),
        'beard' => profileOption($avatar['beard'] ?? '', ['none', 'stubble', 'goatee', 'full', 'royal', 'diamond'], 'none'),
        'glasses' => profileOption($avatar['glasses'] ?? '', ['none', 'round', 'square', 'visor', 'star', 'neon', 'laser'], 'none'),
        'necklace' => profileOption($avatar['necklace'] ?? '', ['none', 'chain', 'clock', 'diamond', 'legend_clock'], 'none'),
        'animation' => profileOption($avatar['animation'] ?? '', ['none', 'bounce', 'hair_wave', 'glow', 'legend_aura'], 'none'),
    ];

    foreach (['skin', 'accessory', 'hair', 'beard', 'glasses', 'necklace', 'animation'] as $field) {
        if (!profileOwns($pdo, (int) $user['id'], $field, $values[$field])) {
            jsonResponse(['error' => 'Dieses Avatar-Item musst du zuerst im Shop kaufen.'], 403);
        }
    }

    $stmt = $pdo->prepare(
        'UPDATE users SET game_name = :game_name,
            avatar_skin = :skin, avatar_eyes = :eyes, avatar_mouth = :mouth,
            avatar_accessory = :accessory, avatar_shape = :shape, avatar_hair = :hair,
            avatar_beard = :beard, avatar_glasses = :glasses, avatar_necklace = :necklace,
            avatar_animation = :animation
         WHERE id = :id'
    );
    $values['game_name'] = $gameName;
    $values['id'] = (int) $user['id'];
    $stmt->execute($values);
    $_SESSION['user']['name'] = $gameName;

    jsonResponse(['profile' => profileLoad($pdo, (int) $user['id'])]);
}

jsonResponse(['error' => 'Method not allowed'], 405);
