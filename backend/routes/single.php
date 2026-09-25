<?php

declare(strict_types=1);

require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../utils/auth.php';

const SINGLE_MODES = ['game', 'movie', 'locations'];

function singleEnsure(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS single_rankings (user_id int(10) UNSIGNED NOT NULL, mode varchar(30) NOT NULL, points int NOT NULL DEFAULT 0, matches int(10) UNSIGNED NOT NULL DEFAULT 0, correct int(10) UNSIGNED NOT NULL DEFAULT 0, skipped int(10) UNSIGNED NOT NULL DEFAULT 0, updated_at timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(), PRIMARY KEY (user_id,mode), KEY mode_points (mode,points)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    foreach (['game_name'=>'varchar(100) DEFAULT NULL','avatar_skin'=>"varchar(20) NOT NULL DEFAULT 'blue'",'avatar_eyes'=>"varchar(20) NOT NULL DEFAULT 'calm'",'avatar_mouth'=>"varchar(20) NOT NULL DEFAULT 'smile'",'avatar_accessory'=>"varchar(20) NOT NULL DEFAULT 'none'",'avatar_shape'=>"varchar(20) NOT NULL DEFAULT 'round'",'avatar_hair'=>"varchar(20) NOT NULL DEFAULT 'none'",'avatar_beard'=>"varchar(20) NOT NULL DEFAULT 'none'",'avatar_glasses'=>"varchar(20) NOT NULL DEFAULT 'none'",'avatar_necklace'=>"varchar(20) NOT NULL DEFAULT 'none'",'avatar_animation'=>"varchar(20) NOT NULL DEFAULT 'none'",'placement_single_remaining'=>'int NOT NULL DEFAULT 0'] as $column => $definition) {
        $stmt = $pdo->prepare('SELECT COUNT(*) found FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME="users" AND COLUMN_NAME=:column');
        $stmt->execute(['column'=>$column]);
        if ((int) ($stmt->fetch()['found'] ?? 0) === 0) $pdo->exec('ALTER TABLE users ADD COLUMN '.$column.' '.$definition);
    }
}

function singleRankName(int $points, int $position = 0): string
{
    if ($points >= 5000 && $position > 0 && $position <= 100) return 'Top 100 #' . $position;
    $tiers = [['Champion',3000,5000],['Platinum',2000,3000],['Gold',1200,2000],['Silver',500,1200],['Bronze',0,500]];
    $divisions = ['V','IV','III','II','I'];
    foreach ($tiers as [$name,$floor,$next]) {
        if ($points >= $floor) {
            $step = max(1, (int) ceil(($next - $floor) / 5));
            return $name . ' ' . $divisions[min(4, max(0, (int) floor(($points - $floor) / $step)))];
        }
    }
    return 'Bronze V';
}

function singleCoins(string $rank, int $position): int
{
    if (str_starts_with($rank, 'Top 100')) return 800 + max(0, 101 - $position) * 12;
    $map = ['Bronze V'=>5,'Bronze IV'=>10,'Bronze III'=>15,'Bronze II'=>20,'Bronze I'=>25,'Silver V'=>40,'Silver IV'=>50,'Silver III'=>60,'Silver II'=>70,'Silver I'=>80,'Gold V'=>110,'Gold IV'=>130,'Gold III'=>150,'Gold II'=>170,'Gold I'=>190,'Platinum V'=>240,'Platinum IV'=>270,'Platinum III'=>300,'Platinum II'=>330,'Platinum I'=>360,'Champion V'=>450,'Champion IV'=>500,'Champion III'=>550,'Champion II'=>600,'Champion I'=>650];
    return $map[$rank] ?? 5;
}

function singleDifficultyRule(string $difficulty): array
{
    $rules = [
        'easy' => ['label' => 'Einfach', 'points' => 6, 'miss' => 2],
        'normal' => ['label' => 'Normal', 'points' => 10, 'miss' => 5],
        'hard' => ['label' => 'Schwer', 'points' => 15, 'miss' => 8],
    ];
    return $rules[$difficulty] ?? $rules['normal'];
}

function singleRow(PDO $pdo, int $userId, string $mode): array
{
    $stmt = $pdo->prepare('SELECT points,matches,correct,skipped FROM single_rankings WHERE user_id=:user_id AND mode=:mode');
    $stmt->execute(['user_id'=>$userId,'mode'=>$mode]);
    $row = $stmt->fetch() ?: ['points'=>0,'matches'=>0,'correct'=>0,'skipped'=>0];
    $pos = $pdo->prepare('SELECT COUNT(*)+1 position FROM single_rankings WHERE mode=:mode AND points>:points');
    $pos->execute(['mode'=>$mode,'points'=>(int)$row['points']]);
    $position = (int) ($pos->fetch()['position'] ?? 1);
    $rank = singleRankName((int)$row['points'], $position);
    return ['mode'=>$mode,'points'=>(int)$row['points'],'matches'=>(int)$row['matches'],'correct'=>(int)$row['correct'],'skipped'=>(int)$row['skipped'],'position'=>$position,'rank'=>$rank,'monthly_coins'=>singleCoins($rank,$position)];
}

function singleLeaderboard(PDO $pdo, string $mode): array
{
    if ($mode === 'global') {
        $stmt = $pdo->query('SELECT u.id user_id,COALESCE(SUM(r.points),0) points,COALESCE(SUM(r.matches),0) matches,COALESCE(SUM(r.correct),0) correct,COALESCE(SUM(r.skipped),0) skipped,COALESCE(NULLIF(u.game_name,""),u.name) name,u.avatar_skin,u.avatar_eyes,u.avatar_mouth,u.avatar_accessory,u.avatar_shape,u.avatar_hair,u.avatar_beard,u.avatar_glasses,u.avatar_necklace,u.avatar_animation FROM users u LEFT JOIN single_rankings r ON r.user_id=u.id GROUP BY u.id,u.game_name,u.name,u.avatar_skin,u.avatar_eyes,u.avatar_mouth,u.avatar_accessory,u.avatar_shape,u.avatar_hair,u.avatar_beard,u.avatar_glasses,u.avatar_necklace,u.avatar_animation ORDER BY points DESC, correct DESC, skipped ASC, user_id ASC LIMIT 100');
        $rows = $stmt->fetchAll();
    } else {
        $mode = in_array($mode, SINGLE_MODES, true) ? $mode : 'game';
        $stmt = $pdo->prepare('SELECT u.id user_id,COALESCE(r.points,0) points,COALESCE(r.matches,0) matches,COALESCE(r.correct,0) correct,COALESCE(r.skipped,0) skipped,COALESCE(NULLIF(u.game_name,""),u.name) name,u.avatar_skin,u.avatar_eyes,u.avatar_mouth,u.avatar_accessory,u.avatar_shape,u.avatar_hair,u.avatar_beard,u.avatar_glasses,u.avatar_necklace,u.avatar_animation FROM users u LEFT JOIN single_rankings r ON r.user_id=u.id AND r.mode=:mode ORDER BY points DESC, correct DESC, skipped ASC, u.id ASC LIMIT 100');
        $stmt->execute(['mode'=>$mode]);
        $rows = $stmt->fetchAll();
    }
    $players = []; $position = 1;
    foreach ($rows as $row) {
        $rank = singleRankName((int)$row['points'], $position);
        $players[] = ['position'=>$position,'user_id'=>(int)$row['user_id'],'name'=>(string)$row['name'],'points'=>(int)$row['points'],'rank'=>$rank,'monthly_coins'=>singleCoins($rank,$position),'matches'=>(int)$row['matches'],'correct'=>(int)$row['correct'],'skipped'=>(int)$row['skipped'],'avatar'=>['skin'=>$row['avatar_skin']??'blue','eyes'=>$row['avatar_eyes']??'calm','mouth'=>$row['avatar_mouth']??'smile','accessory'=>$row['avatar_accessory']??'none','shape'=>$row['avatar_shape']??'round','hair'=>$row['avatar_hair']??'none','beard'=>$row['avatar_beard']??'none','glasses'=>$row['avatar_glasses']??'none','necklace'=>$row['avatar_necklace']??'none','animation'=>$row['avatar_animation']??'none']];
        $position++;
    }
    return ['mode'=>$mode,'players'=>$players];
}

$pdo = getDatabaseConnection();
$user = requireAuthenticatedUser();
$body = readJsonBody();
singleEnsure($pdo);

if ($route === 'single-ranking') {
    $mode = in_array((string)($_GET['mode'] ?? ''), SINGLE_MODES, true) ? (string)$_GET['mode'] : 'game';
    jsonResponse(singleRow($pdo, (int)$user['id'], $mode));
}
if ($route === 'single-leaderboard') {
    jsonResponse(singleLeaderboard($pdo, (string)($_GET['mode'] ?? 'global')));
}
if ($route === 'single-submit' && $method === 'POST') {
    $mode = in_array((string)($body['mode'] ?? ''), SINGLE_MODES, true) ? (string)$body['mode'] : 'game';
    $rounds = max(5, min(10, (int)($body['rounds'] ?? 5)));
    $difficulty = in_array((string)($body['difficulty'] ?? ''), ['easy', 'normal', 'hard'], true) ? (string)$body['difficulty'] : 'normal';
    $rule = singleDifficultyRule($difficulty);
    $correct = max(0, min($rounds, (int)($body['correct'] ?? 0)));
    $skipped = max(0, min($rounds - $correct, (int)($body['skipped'] ?? 0)));
    $before = singleRow($pdo, (int)$user['id'], $mode);
    $delta = ($correct * (int)$rule['points']) - ($skipped * (int)$rule['miss']);
    $points = max(0, $before['points'] + $delta);
    $stmt = $pdo->prepare('INSERT INTO single_rankings (user_id,mode,points,matches,correct,skipped) VALUES (:user_id,:mode,:points,1,:correct,:skipped) ON DUPLICATE KEY UPDATE points=:points,matches=matches+1,correct=correct+:correct,skipped=skipped+:skipped');
    $stmt->execute(['user_id'=>(int)$user['id'],'mode'=>$mode,'points'=>$points,'correct'=>$correct,'skipped'=>$skipped]);
    try { $pdo->prepare('UPDATE users SET placement_single_remaining=GREATEST(0,placement_single_remaining-1) WHERE id=:id')->execute(['id'=>(int)$user['id']]); } catch (Throwable) {}
    $after = singleRow($pdo, (int)$user['id'], $mode);
    jsonResponse(['delta'=>$delta,'before'=>$before,'after'=>$after,'summary'=>['rounds'=>$rounds,'correct'=>$correct,'skipped'=>$skipped,'difficulty'=>$difficulty,'difficulty_label'=>$rule['label'],'points_per_correct'=>(int)$rule['points']]]);
}

jsonResponse(['error'=>'Route not found'], 404);
