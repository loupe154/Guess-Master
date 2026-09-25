<?php

declare(strict_types=1);

require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../utils/auth.php';

function shopEnsure(PDO $pdo): void
{
    profileEnsureColumnsForShop($pdo);
    $pdo->exec('CREATE TABLE IF NOT EXISTS user_wallets (user_id int(10) UNSIGNED NOT NULL PRIMARY KEY, coins int NOT NULL DEFAULT 0, updated_at timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $pdo->exec('CREATE TABLE IF NOT EXISTS shop_items (id int(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, field varchar(30) NOT NULL, value varchar(30) NOT NULL, name varchar(80) NOT NULL, price int NOT NULL DEFAULT 0, rarity varchar(30) NOT NULL DEFAULT "normal", is_default tinyint(1) NOT NULL DEFAULT 0, UNIQUE KEY field_value (field,value)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $pdo->exec('CREATE TABLE IF NOT EXISTS user_shop_items (user_id int(10) UNSIGNED NOT NULL, item_id int(10) UNSIGNED NOT NULL, purchased_at timestamp NOT NULL DEFAULT current_timestamp(), PRIMARY KEY (user_id,item_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $pdo->exec('CREATE TABLE IF NOT EXISTS monthly_seasons (month_key char(7) NOT NULL PRIMARY KEY, processed_at timestamp NOT NULL DEFAULT current_timestamp()) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $pdo->exec('CREATE TABLE IF NOT EXISTS monthly_rewards (id int(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, user_id int(10) UNSIGNED NOT NULL, month_key char(7) NOT NULL, source varchar(20) NOT NULL, position int NOT NULL DEFAULT 0, rank_name varchar(40) NOT NULL, points int NOT NULL DEFAULT 0, coins int NOT NULL DEFAULT 0, created_at timestamp NOT NULL DEFAULT current_timestamp(), UNIQUE KEY user_month_source (user_id,month_key,source)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
}

function profileEnsureColumnsForShop(PDO $pdo): void
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

function shopItemsSeed(): array
{
    return [
        ['skin','blue','Blue Skin',0,'normal',1],['skin','green','Green Skin',0,'normal',1],['skin','red','Red Skin',0,'normal',1],['skin','gold','Gold Skin',0,'normal',1],['skin','purple','Purple Skin',0,'normal',1],
        ['skin','ice','Ice Skin',450,'rare',0],['skin','shadow','Shadow Skin',900,'episch',0],['skin','ruby','Ruby Skin',280,'rare',0],['skin','galaxy','Galaxy Skin',1600,'legendaer',0],
        ['accessory','none','No Hat',0,'normal',1],['accessory','cap','Cap',0,'normal',1],['accessory','crown','Crown',0,'normal',1],['accessory','headset','Headset',0,'normal',1],['accessory','beanie','Beanie',0,'normal',1],
        ['accessory','fedora','Fedora',220,'rare',0],['accessory','halo','Halo',520,'episch',0],['accessory','mask','Mask',760,'episch',0],['accessory','wizard','Wizard Hat',1050,'super_rare',0],['accessory','legend_crown','Legend Crown',1800,'legendaer',0],
        ['hair','none','No Hair',0,'normal',1],['hair','short','Short Hair',0,'normal',1],['hair','spiky','Spiky Hair',0,'normal',1],['hair','curly','Curly Hair',0,'normal',1],['hair','side','Side Hair',0,'normal',1],
        ['hair','mohawk','Mohawk',180,'rare',0],['hair','long','Long Hair',260,'rare',0],['hair','flame','Flame Hair',650,'episch',0],['hair','wave','Moving Wave Hair',980,'super_rare',0],['hair','legend_flame','Legend Flame Hair',1900,'legendaer',0],
        ['beard','none','No Beard',0,'normal',1],['beard','stubble','Stubble',0,'normal',1],['beard','goatee','Goatee',0,'normal',1],['beard','full','Full Beard',0,'normal',1],['beard','royal','Royal Beard',300,'rare',0],['beard','diamond','Diamond Beard',850,'super_rare',0],
        ['glasses','none','No Glasses',0,'normal',1],['glasses','round','Round Glasses',0,'normal',1],['glasses','square','Square Glasses',0,'normal',1],['glasses','visor','Visor',0,'normal',1],['glasses','star','Star Glasses',320,'rare',0],['glasses','neon','Neon Glasses',620,'episch',0],['glasses','laser','Laser Glasses',1200,'super_rare',0],
        ['necklace','none','No Necklace',0,'normal',1],['necklace','chain','Silver Chain',260,'rare',0],['necklace','clock','Clock Chain',700,'episch',0],['necklace','diamond','Diamond Chain',1450,'super_rare',0],['necklace','legend_clock','Legend Clock Chain',2100,'legendaer',0],
        ['animation','none','No Animation',0,'normal',1],['animation','bounce','Bounce',350,'rare',0],['animation','hair_wave','Hair Wave',750,'episch',0],['animation','glow','Glow Aura',1100,'super_rare',0],['animation','legend_aura','Legend Aura',2200,'legendaer',0],
    ];
}

function shopSeed(PDO $pdo): void
{
    $stmt = $pdo->prepare('INSERT INTO shop_items (field,value,name,price,rarity,is_default) VALUES (:field,:value,:name,:price,:rarity,:is_default) ON DUPLICATE KEY UPDATE name=:name, price=:price, rarity=:rarity, is_default=:is_default');
    foreach (shopItemsSeed() as [$field,$value,$name,$price,$rarity,$default]) {
        $stmt->execute(['field'=>$field,'value'=>$value,'name'=>$name,'price'=>$price,'rarity'=>$rarity,'is_default'=>$default]);
    }
}

function shopWallet(PDO $pdo, int $userId): int
{
    $stmt = $pdo->prepare('INSERT INTO user_wallets (user_id, coins) VALUES (:id, 0) ON DUPLICATE KEY UPDATE user_id=user_id');
    $stmt->execute(['id' => $userId]);
    $stmt = $pdo->prepare('SELECT coins FROM user_wallets WHERE user_id=:id');
    $stmt->execute(['id' => $userId]);
    return (int) ($stmt->fetch()['coins'] ?? 0);
}

function shopGrantDefaults(PDO $pdo, int $userId): void
{
    $stmt = $pdo->prepare('INSERT IGNORE INTO user_shop_items (user_id,item_id) SELECT :id,id FROM shop_items WHERE is_default=1');
    $stmt->execute(['id' => $userId]);
}

function shopItems(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('SELECT i.*, CASE WHEN owned.item_id IS NULL THEN 0 ELSE 1 END owned FROM shop_items i LEFT JOIN user_shop_items owned ON owned.item_id=i.id AND owned.user_id=:id ORDER BY i.field, i.price, i.name');
    $stmt->execute(['id' => $userId]);
    return $stmt->fetchAll();
}

function shopUnlocked(PDO $pdo, int $userId): array
{
    $out = [];
    foreach (shopItems($pdo, $userId) as $item) {
        if ((int) $item['owned'] === 1) {
            $out[(string) $item['field']][] = (string) $item['value'];
        }
    }
    return $out;
}

$pdo = getDatabaseConnection();
$user = requireAuthenticatedUser();
$body = readJsonBody();
$userId = (int) $user['id'];
shopEnsure($pdo);
shopSeed($pdo);
shopWallet($pdo, $userId);
shopGrantDefaults($pdo, $userId);

if ($route === 'shop' && $method === 'GET') {
    $status = $pdo->prepare('SELECT placement_pvp_remaining, placement_single_remaining FROM users WHERE id=:id');
    $status->execute(['id' => $userId]);
    $row = $status->fetch() ?: ['placement_pvp_remaining' => 0, 'placement_single_remaining' => 0];
    jsonResponse(['coins' => shopWallet($pdo, $userId), 'items' => shopItems($pdo, $userId), 'unlocked' => shopUnlocked($pdo, $userId), 'season' => ['placement_pvp_remaining' => (int) $row['placement_pvp_remaining'], 'placement_single_remaining' => (int) $row['placement_single_remaining']]]);
}

if ($route === 'shop-buy' && $method === 'POST') {
    $itemId = (int) ($body['item_id'] ?? 0);
    $stmt = $pdo->prepare('SELECT * FROM shop_items WHERE id=:id LIMIT 1');
    $stmt->execute(['id' => $itemId]);
    $item = $stmt->fetch();
    if (!$item) jsonResponse(['error' => 'Item wurde nicht gefunden.'], 404);
    $owned = $pdo->prepare('SELECT item_id FROM user_shop_items WHERE user_id=:user_id AND item_id=:item_id');
    $owned->execute(['user_id' => $userId, 'item_id' => $itemId]);
    if ($owned->fetch()) jsonResponse(['error' => 'Du besitzt dieses Item schon.'], 409);
    if (shopWallet($pdo, $userId) < (int) $item['price']) jsonResponse(['error' => 'Nicht genug Coins.'], 400);
    $pdo->prepare('UPDATE user_wallets SET coins=coins-:price WHERE user_id=:id')->execute(['price' => (int) $item['price'], 'id' => $userId]);
    $pdo->prepare('INSERT INTO user_shop_items (user_id,item_id) VALUES (:user_id,:item_id)')->execute(['user_id' => $userId, 'item_id' => $itemId]);
    $avatarColumns = [
        'skin' => 'avatar_skin',
        'accessory' => 'avatar_accessory',
        'hair' => 'avatar_hair',
        'beard' => 'avatar_beard',
        'glasses' => 'avatar_glasses',
        'necklace' => 'avatar_necklace',
        'animation' => 'avatar_animation',
    ];
    $field = (string) $item['field'];
    $value = (string) $item['value'];
    if (isset($avatarColumns[$field])) {
        $pdo->prepare('UPDATE users SET ' . $avatarColumns[$field] . ' = :value WHERE id = :id')->execute(['value' => $value, 'id' => $userId]);
    }
    jsonResponse([
        'coins' => shopWallet($pdo, $userId),
        'items' => shopItems($pdo, $userId),
        'unlocked' => shopUnlocked($pdo, $userId),
        'bought' => ['field' => $field, 'value' => $value, 'name' => (string) $item['name']],
    ]);
}

jsonResponse(['error' => 'Route not found'], 404);
