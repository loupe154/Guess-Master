<?php

declare(strict_types=1);

require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../utils/auth.php';

const PVP_DEFAULT_MAX_ROUNDS = 5;
const PVP_DEFAULT_ROUND_SECONDS = 60;
const PVP_MIN_ROUNDS = 1;
const PVP_MAX_ROUNDS = 20;
const PVP_MIN_ROUND_SECONDS = 15;
const PVP_MAX_ROUND_SECONDS = 180;
const PVP_RESULT_SECONDS = 3;
const PVP_READY_SECONDS = 60;
const PVP_START_SECONDS = 5;
const PVP_BACKEND_VERSION = '2026-04-28-direct-playing-v2';
const PVP_MODES = ['game', 'movie', 'locations'];

function pvpNormalizeAnswer(string $value): string
{
    $value = strtolower(trim($value));
    $value = str_replace('&', ' and ', $value);
    $value = preg_replace("/['\x{2019}]/u", '', $value) ?? $value;
    $value = preg_replace('/[^a-z0-9]+/i', ' ', $value) ?? $value;
    return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
}

function pvpAnswerOptions(string $title): array
{
    $clean = trim($title);
    $short = trim(preg_split('/:|\s-\s|\|/', $clean)[0] ?? $clean);
    $options = [$clean, $short];
    foreach ([$clean, $short] as $candidate) {
        $candidate = pvpNormalizeAnswer($candidate);
        foreach (['remastered','remaster','standard','deluxe','ultimate','complete','definitive','anniversary','edition','version','game','of','the','year','goty'] as $word) {
            $candidate = pvpNormalizeAnswer(preg_replace('/(^| )' . preg_quote($word, '/') . '( |$)/', ' ', $candidate) ?? $candidate);
        }
        $options[] = $candidate;
    }
    if (str_contains(pvpNormalizeAnswer($clean), 'spider man')) {
        $options[] = 'Spider Man';
        $options[] = 'Spiderman';
    }
    $normalized = [];
    foreach ($options as $option) {
        $answer = pvpNormalizeAnswer((string) $option);
        if ($answer !== '') $normalized[$answer] = $answer;
    }
    return array_values($normalized);
}

function pvpClampInt(mixed $value, int $default, int $min, int $max): int
{
    $number = filter_var($value, FILTER_VALIDATE_INT);
    return $number === false ? $default : max($min, min($max, (int) $number));
}

function pvpSchemaExec(PDO $pdo, string $sql): void
{
    try {
        $pdo->exec($sql);
    } catch (PDOException $exception) {
        error_log('GuessMaster PvP schema update failed: ' . $exception->getMessage());
    }
}

function pvpSchemaColumnExists(PDO $pdo, string $table, string $column): bool
{
    $check = $pdo->prepare('SELECT COUNT(*) found FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name');
    $check->execute(['table_name' => $table, 'column_name' => $column]);
    return (int) ($check->fetch()['found'] ?? 0) > 0;
}

function pvpSchemaTableExists(PDO $pdo, string $table): bool
{
    $check = $pdo->prepare('SELECT COUNT(*) found FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name');
    $check->execute(['table_name' => $table]);
    return (int) ($check->fetch()['found'] ?? 0) > 0;
}

function pvpEnsureSchema(PDO $pdo): void
{
    pvpSchemaExec($pdo, "CREATE TABLE IF NOT EXISTS pvp_matches (id int(10) UNSIGNED NOT NULL AUTO_INCREMENT, code varchar(8) NOT NULL, mode varchar(30) NOT NULL DEFAULT 'game', status enum('waiting','ready','starting','playing','finished') NOT NULL DEFAULT 'waiting', host_user_id int(10) UNSIGNED NOT NULL, guest_user_id int(10) UNSIGNED DEFAULT NULL, current_round int(10) UNSIGNED NOT NULL DEFAULT 0, max_rounds int(10) UNSIGNED NOT NULL DEFAULT 5, round_duration int(10) UNSIGNED NOT NULL DEFAULT 60, winner_user_id int(10) UNSIGNED DEFAULT NULL, ready_deadline_at datetime DEFAULT NULL, starts_at datetime DEFAULT NULL, ranking_applied tinyint(1) NOT NULL DEFAULT 0, ranking_changes_json text DEFAULT NULL, post_match_choices_json text DEFAULT NULL, created_at timestamp NOT NULL DEFAULT current_timestamp(), updated_at timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(), PRIMARY KEY (id), UNIQUE KEY code (code), KEY host_user_id (host_user_id), KEY guest_user_id (guest_user_id), KEY winner_user_id (winner_user_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    pvpSchemaExec($pdo, "ALTER TABLE pvp_matches MODIFY status enum('waiting','ready','starting','playing','finished') NOT NULL DEFAULT 'waiting'");
    pvpSchemaExec($pdo, "CREATE TABLE IF NOT EXISTS pvp_ready (match_id int(10) UNSIGNED NOT NULL, user_id int(10) UNSIGNED NOT NULL, ready_at timestamp NOT NULL DEFAULT current_timestamp(), PRIMARY KEY (match_id,user_id), KEY user_id (user_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    pvpSchemaExec($pdo, "CREATE TABLE IF NOT EXISTS pvp_rounds (id int(10) UNSIGNED NOT NULL AUTO_INCREMENT, match_id int(10) UNSIGNED NOT NULL, round_number int(10) UNSIGNED NOT NULL, title varchar(255) NOT NULL, image text NOT NULL, hint varchar(255) NOT NULL, answers_json text NOT NULL, started_at datetime NOT NULL DEFAULT current_timestamp(), ends_at datetime NOT NULL, completed_at datetime DEFAULT NULL, PRIMARY KEY (id), UNIQUE KEY match_round (match_id,round_number)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    pvpSchemaExec($pdo, "CREATE TABLE IF NOT EXISTS pvp_answers (id int(10) UNSIGNED NOT NULL AUTO_INCREMENT, match_id int(10) UNSIGNED NOT NULL, round_id int(10) UNSIGNED NOT NULL, user_id int(10) UNSIGNED NOT NULL, answer varchar(255) NOT NULL, is_correct tinyint(1) NOT NULL DEFAULT 0, points int(10) UNSIGNED NOT NULL DEFAULT 0, answered_at timestamp NOT NULL DEFAULT current_timestamp(), PRIMARY KEY (id), KEY match_id (match_id), KEY round_id (round_id), KEY user_id (user_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    pvpSchemaExec($pdo, "CREATE TABLE IF NOT EXISTS pvp_chat_messages (id int(10) UNSIGNED NOT NULL AUTO_INCREMENT, match_id int(10) UNSIGNED NOT NULL, user_id int(10) UNSIGNED NOT NULL, message varchar(300) NOT NULL, created_at timestamp NOT NULL DEFAULT current_timestamp(), PRIMARY KEY (id), KEY match_id (match_id), KEY user_id (user_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    pvpSchemaExec($pdo, "CREATE TABLE IF NOT EXISTS pvp_rankings (user_id int(10) UNSIGNED NOT NULL, mode varchar(30) NOT NULL, points int NOT NULL DEFAULT 0, wins int(10) UNSIGNED NOT NULL DEFAULT 0, losses int(10) UNSIGNED NOT NULL DEFAULT 0, updated_at timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(), PRIMARY KEY (user_id,mode), KEY mode_points (mode,points)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $columns = [
        'pvp_matches' => [
            'status' => "enum('waiting','ready','starting','playing','finished') NOT NULL DEFAULT 'waiting'",
            'mode' => "varchar(30) NOT NULL DEFAULT 'game'",
            'guest_user_id' => 'int(10) UNSIGNED DEFAULT NULL',
            'current_round' => 'int(10) UNSIGNED NOT NULL DEFAULT 0',
            'max_rounds' => 'int(10) UNSIGNED NOT NULL DEFAULT 5',
            'round_duration' => 'int(10) UNSIGNED NOT NULL DEFAULT 60',
            'winner_user_id' => 'int(10) UNSIGNED DEFAULT NULL',
            'ready_deadline_at' => 'datetime DEFAULT NULL',
            'starts_at' => 'datetime DEFAULT NULL',
            'ranking_applied' => 'tinyint(1) NOT NULL DEFAULT 0',
            'ranking_changes_json' => 'text DEFAULT NULL',
            'post_match_choices_json' => 'text DEFAULT NULL',
            'updated_at' => 'timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()',
        ],
        'pvp_answers' => [
            'is_correct' => 'tinyint(1) NOT NULL DEFAULT 0',
            'points' => 'int(10) UNSIGNED NOT NULL DEFAULT 0',
            'answered_at' => 'timestamp NOT NULL DEFAULT current_timestamp()',
        ],
        'pvp_rankings' => [
            'wins' => 'int(10) UNSIGNED NOT NULL DEFAULT 0',
            'losses' => 'int(10) UNSIGNED NOT NULL DEFAULT 0',
            'updated_at' => 'timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()',
        ],
    ];
    foreach ($columns as $table => $tableColumns) {
        foreach ($tableColumns as $column => $definition) {
            if (!pvpSchemaColumnExists($pdo, $table, $column)) {
                pvpSchemaExec($pdo, 'ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
            }
        }
    }

    $missing = [];
    foreach (['pvp_matches', 'pvp_ready', 'pvp_rounds', 'pvp_answers', 'pvp_chat_messages', 'pvp_rankings'] as $table) {
        if (!pvpSchemaTableExists($pdo, $table)) $missing[] = $table;
    }
    foreach ($columns as $table => $tableColumns) {
        foreach (array_keys($tableColumns) as $column) {
            if (!pvpSchemaColumnExists($pdo, $table, $column)) $missing[] = $table . '.' . $column;
        }
    }
    if ($missing) {
        jsonResponse(['error' => 'PvP-Datenbank ist auf dem Server nicht vollstaendig. Importiere backend/data/pvp_tables.sql und backend/data/pvp_migration_ready_chat_ranking.sql. Fehlend: ' . implode(', ', $missing)], 500);
    }
}

function pvpRankTier(string $rank): string
{
    return explode(' ', trim($rank))[0] ?: 'Bronze';
}

function pvpRankBounds(string $rank): array
{
    if (str_starts_with($rank, 'Top 100')) return [5000, null];
    $tiers = [
        'Bronze' => [0, 500],
        'Silver' => [500, 1200],
        'Gold' => [1200, 2000],
        'Platinum' => [2000, 3000],
        'Champion' => [3000, 5000],
    ];
    $tier = pvpRankTier($rank);
    return $tiers[$tier] ?? [0, 500];
}

function pvpRankName(int $points, int $position = 0): string
{
    if ($points >= 5000 && $position > 0 && $position <= 100) return 'Top 100 #' . $position;
    $tiers = [['Champion', 3000, 5000], ['Platinum', 2000, 3000], ['Gold', 1200, 2000], ['Silver', 500, 1200], ['Bronze', 0, 500]];
    $divisions = ['V', 'IV', 'III', 'II', 'I'];
    foreach ($tiers as [$name, $floor, $next]) {
        if ($points >= $floor) {
            $step = max(1, (int) ceil(($next - $floor) / 5));
            $division = $divisions[min(4, max(0, (int) floor(($points - $floor) / $step)))];
            return $name . ' ' . $division;
        }
    }
    return 'Bronze V';
}

function pvpNextRankTarget(string $rank): ?int
{
    if (str_starts_with($rank, 'Top 100')) return null;
    [$floor, $nextTier] = pvpRankBounds($rank);
    if ($nextTier === null) return null;
    $step = max(1, (int) ceil(($nextTier - $floor) / 5));
    $parts = explode(' ', $rank);
    $division = $parts[1] ?? 'V';
    $index = array_search($division, ['V', 'IV', 'III', 'II', 'I'], true);
    $index = $index === false ? 0 : (int) $index;
    return $index >= 4 ? $nextTier : $floor + ($index + 1) * $step;
}

function pvpRankFloor(string $rank): int
{
    if (str_starts_with($rank, 'Top 100')) return 5000;
    [$floor, $nextTier] = pvpRankBounds($rank);
    $step = $nextTier ? max(1, (int) ceil(($nextTier - $floor) / 5)) : 1;
    $parts = explode(' ', $rank);
    $division = $parts[1] ?? 'V';
    $index = array_search($division, ['V', 'IV', 'III', 'II', 'I'], true);
    return $floor + (($index === false ? 0 : (int) $index) * $step);
}

function pvpMonthlyCoins(string $rank, int $position): int
{
    if (str_starts_with($rank, 'Top 100')) return max(700, 2000 - (($position - 1) * 13));
    $map = [
        'Bronze V' => 5, 'Bronze IV' => 10, 'Bronze III' => 15, 'Bronze II' => 20, 'Bronze I' => 25,
        'Silver V' => 45, 'Silver IV' => 60, 'Silver III' => 75, 'Silver II' => 90, 'Silver I' => 105,
        'Gold V' => 140, 'Gold IV' => 165, 'Gold III' => 190, 'Gold II' => 215, 'Gold I' => 240,
        'Platinum V' => 300, 'Platinum IV' => 340, 'Platinum III' => 380, 'Platinum II' => 420, 'Platinum I' => 460,
        'Champion V' => 560, 'Champion IV' => 620, 'Champion III' => 680, 'Champion II' => 740, 'Champion I' => 800,
    ];
    return $map[$rank] ?? 5;
}

function pvpRankScoreRules(string $rank): array
{
    return match (pvpRankTier($rank)) {
        'Bronze' => ['max_gain' => 10, 'max_loss' => 0, 'leave_gain' => 10],
        'Silver' => ['max_gain' => 20, 'max_loss' => 20, 'leave_gain' => 20],
        'Gold' => ['max_gain' => 30, 'max_loss' => 30, 'leave_gain' => 30],
        'Platinum' => ['max_gain' => 40, 'max_loss' => 60, 'leave_gain' => 50],
        'Champion', 'Top' => ['max_gain' => 50, 'max_loss' => 100, 'leave_gain' => 50],
        default => ['max_gain' => 10, 'max_loss' => 0, 'leave_gain' => 10],
    };
}

function pvpScoreBreakdown(int $score, int $maxScore, array $rules): array
{
    $totalRounds = max(1, (int) ceil(max(10, $maxScore) / 10));
    $correctRounds = max(0, min($totalRounds, (int) floor(max(0, $score) / 10)));
    $missedRounds = max(0, $totalRounds - $correctRounds);
    $missedRatio = $missedRounds / $totalRounds;
    $maxGain = max(0, (int) ($rules['max_gain'] ?? 0));
    $maxLoss = max(0, (int) ($rules['max_loss'] ?? 0));
    $winDelta = max(0, $maxGain - (int) round($maxGain * $missedRatio));
    $lossPenalty = min($maxLoss, (int) round($maxLoss * $missedRatio));

    return [
        'total_rounds' => $totalRounds,
        'correct_rounds' => $correctRounds,
        'missed_rounds' => $missedRounds,
        'missed_penalty' => $lossPenalty,
        'win_delta' => $winDelta,
        'max_delta' => $maxGain,
        'max_gain' => $maxGain,
        'max_loss' => $maxLoss,
        'leave_gain' => max(0, (int) ($rules['leave_gain'] ?? $maxGain)),
        'flawless' => $missedRounds === 0,
    ];
}

function pvpScoreDelta(array $before, bool $won, int $score, int $maxScore, ?int $gainOverride = null): int
{
    $breakdown = pvpScoreBreakdown($score, $maxScore, pvpRankScoreRules((string) $before['rank']));

    if ($won) {
        if ($gainOverride !== null) return max(0, min((int) $breakdown['leave_gain'], $gainOverride));
        return (int) $breakdown['win_delta'];
    }

    return -((int) $breakdown['missed_penalty']);
}

function pvpRankingRow(PDO $pdo, int $userId, string $mode): array
{
    $stmt = $pdo->prepare('SELECT points, wins, losses FROM pvp_rankings WHERE user_id = :user_id AND mode = :mode');
    $stmt->execute(['user_id' => $userId, 'mode' => $mode]);
    $row = $stmt->fetch() ?: ['points' => 0, 'wins' => 0, 'losses' => 0];
    $posStmt = $pdo->prepare('SELECT COUNT(*) + 1 AS position FROM pvp_rankings WHERE mode = :mode AND (points > :points OR (points = :points AND (wins > :wins OR (wins = :wins AND losses < :losses) OR (wins = :wins AND losses = :losses AND user_id < :user_id))))');
    $posStmt->execute(['mode' => $mode, 'points' => (int) $row['points'], 'wins' => (int) $row['wins'], 'losses' => (int) $row['losses'], 'user_id' => $userId]);
    $position = (int) ($posStmt->fetch()['position'] ?? 0);
    return ['mode' => $mode, 'points' => (int) $row['points'], 'wins' => (int) $row['wins'], 'losses' => (int) $row['losses'], 'position' => $position, 'rank' => pvpRankName((int) $row['points'], $position)];
}

function pvpHasAllModeRankings(PDO $pdo, int $userId): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(DISTINCT mode) AS mode_count FROM pvp_rankings WHERE user_id = :user_id AND mode IN ("game", "movie", "locations")');
    $stmt->execute(['user_id' => $userId]);
    return (int) ($stmt->fetch()['mode_count'] ?? 0) >= 3;
}

function pvpRankingSet(PDO $pdo, int $userId): array
{
    $rankings = [];
    foreach (PVP_MODES as $mode) $rankings[$mode] = pvpRankingRow($pdo, $userId, $mode);
    $rankings['global'] = pvpHasAllModeRankings($pdo, $userId) ? pvpRankingRow($pdo, $userId, 'global') : null;
    return $rankings;
}

function pvpUpsertRanking(PDO $pdo, int $userId, string $mode, int $delta, bool $won): void
{
    $row = pvpRankingRow($pdo, $userId, $mode);
    $points = max(0, $row['points'] + $delta);
    $stmt = $pdo->prepare('INSERT INTO pvp_rankings (user_id, mode, points, wins, losses) VALUES (:user_id, :mode, :points, :wins, :losses) ON DUPLICATE KEY UPDATE points = :points, wins = wins + :wins, losses = losses + :losses');
    $stmt->execute(['user_id' => $userId, 'mode' => $mode, 'points' => $points, 'wins' => $won ? 1 : 0, 'losses' => $won ? 0 : 1]);
}

function pvpGenerateCode(PDO $pdo): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do {
        $code = '';
        for ($i = 0; $i < 5; $i++) $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        $stmt = $pdo->prepare('SELECT id FROM pvp_matches WHERE code = :code LIMIT 1');
        $stmt->execute(['code' => $code]);
    } while ($stmt->fetch());
    return $code;
}

function pvpHttpJson(string $url): ?array
{
    $context = stream_context_create([
        'http' => [
            'timeout' => 4,
            'ignore_errors' => true,
            'header' => "User-Agent: GuessMaster/1.0\r\n",
        ],
    ]);
    $response = @file_get_contents($url, false, $context);
    if ($response === false) return null;
    $data = json_decode($response, true);
    return is_array($data) ? $data : null;
}

function pvpFetchGameApi(): ?array
{
    if (!defined('RAWG_API_KEY') || RAWG_API_KEY === '' || RAWG_API_KEY === 'PASTE_RAWG_KEY_HERE') return null;
    $page = random_int(1, 5);
    $data = pvpHttpJson('https://api.rawg.io/api/games?key=' . urlencode(RAWG_API_KEY) . '&page=' . $page . '&page_size=20');
    if (empty($data['results']) || !is_array($data['results'])) return null;
    $games = array_values(array_filter($data['results'], fn($game) => !empty($game['name']) && !empty($game['background_image'])));
    if (!$games) return null;
    $game = $games[array_rand($games)];
    $genres = [];
    foreach (($game['genres'] ?? []) as $genre) {
        if (!empty($genre['name'])) $genres[] = $genre['name'];
    }
    return [
        'title' => (string) $game['name'],
        'image' => (string) $game['background_image'],
        'hint' => $genres ? 'Genre: ' . implode(', ', array_slice($genres, 0, 2)) : 'Release: ' . ($game['released'] ?? 'unknown'),
    ];
}

function pvpFetchMovieApi(): ?array
{
    if (!defined('TMDB_API_KEY') || TMDB_API_KEY === '' || TMDB_API_KEY === 'PASTE_TMDB_KEY_HERE') return null;
    $page = random_int(1, 5);
    $data = pvpHttpJson('https://api.themoviedb.org/3/movie/popular?api_key=' . urlencode(TMDB_API_KEY) . '&page=' . $page);
    if (empty($data['results']) || !is_array($data['results'])) return null;
    $movies = array_values(array_filter($data['results'], fn($movie) => !empty($movie['title']) && !empty($movie['poster_path'])));
    if (!$movies) return null;
    $movie = $movies[array_rand($movies)];
    return [
        'title' => (string) $movie['title'],
        'image' => 'https://image.tmdb.org/t/p/w500' . $movie['poster_path'],
        'hint' => 'Release date: ' . ($movie['release_date'] ?? 'unknown'),
    ];
}

function pvpFetchLocationApi(): ?array
{
    if (!defined('UNSPLASH_ACCESS_KEY') || UNSPLASH_ACCESS_KEY === '' || UNSPLASH_ACCESS_KEY === 'PASTE_UNSPLASH_KEY_HERE') return null;
    $locations = [
        ['title' => 'Paris', 'landmark' => 'Eiffel Tower', 'answers' => ['Paris']],
        ['title' => 'New York', 'landmark' => 'Statue of Liberty', 'answers' => ['New York', 'New York City', 'NYC']],
        ['title' => 'Rome', 'landmark' => 'Colosseum', 'answers' => ['Rome', 'Roma']],
        ['title' => 'London', 'landmark' => 'Big Ben', 'answers' => ['London']],
        ['title' => 'Agra', 'landmark' => 'Taj Mahal', 'answers' => ['Agra']],
        ['title' => 'Sydney', 'landmark' => 'Sydney Opera House', 'answers' => ['Sydney']],
    ];
    $location = $locations[array_rand($locations)];
    $query = urlencode($location['landmark'] . ' ' . $location['title']);
    $data = pvpHttpJson('https://api.unsplash.com/search/photos?query=' . $query . '&orientation=landscape&per_page=10&client_id=' . urlencode(UNSPLASH_ACCESS_KEY));
    if (empty($data['results']) || !is_array($data['results'])) return null;
    $photos = array_values(array_filter($data['results'], fn($photo) => !empty($photo['urls']['regular'])));
    if (!$photos) return null;
    $photo = $photos[array_rand($photos)];
    return [
        'title' => $location['title'],
        'hint' => 'Landmark: ' . $location['landmark'],
        'answers' => $location['answers'],
        'image' => (string) $photo['urls']['regular'],
    ];
}

function pvpFallbackGame(): array
{
    $items = [
        ['title' => 'Minecraft', 'image' => 'https://media.rawg.io/media/games/b21/b21555ff4af7d8f01846c185a3f9361e.jpg', 'hint' => 'Genre: Sandbox, Survival'],
        ['title' => 'Grand Theft Auto V', 'image' => 'https://media.rawg.io/media/games/20a/20aa03a18b2b4f96f9e8e1cbec9d09ac.jpg', 'hint' => 'Genre: Action, Open world'],
        ['title' => 'Portal 2', 'image' => 'https://media.rawg.io/media/games/2ba/2bac0e87d98670933265da71b0807e4d.jpg', 'hint' => 'Genre: Puzzle'],
    ];
    return $items[array_rand($items)];
}

function pvpFallbackMovie(): array
{
    $items = [
        ['title' => 'Inception', 'image' => 'https://image.tmdb.org/t/p/w500/9gk7adHYeDvHkCSEqAvQNLV5Uge.jpg', 'hint' => 'Release date: 2010-07-15'],
        ['title' => 'The Matrix', 'image' => 'https://image.tmdb.org/t/p/w500/f89U3ADr1oiB1s9GkdPOEpXUk5H.jpg', 'hint' => 'Release date: 1999-03-31'],
        ['title' => 'Interstellar', 'image' => 'https://image.tmdb.org/t/p/w500/gEU2QniE6E77NI6lCU6MxlNBvIx.jpg', 'hint' => 'Release date: 2014-11-05'],
    ];
    return $items[array_rand($items)];
}

function pvpLocationPool(): array
{
    return [
        ['title' => 'Paris', 'hint' => 'Landmark: Eiffel Tower | Country: France', 'answers' => ['Paris'], 'image' => 'https://images.unsplash.com/photo-1543349689-9a4d426bee8e?auto=format&fit=crop&w=1200&q=80'],
        ['title' => 'New York', 'hint' => 'Landmark: Statue of Liberty | Country: United States', 'answers' => ['New York', 'New York City', 'NYC'], 'image' => 'https://images.unsplash.com/photo-1485738422979-f5c462d49f74?auto=format&fit=crop&w=1200&q=80'],
        ['title' => 'Rome', 'hint' => 'Landmark: Colosseum | Country: Italy', 'answers' => ['Rome', 'Roma'], 'image' => 'https://images.unsplash.com/photo-1552832230-c0197dd311b5?auto=format&fit=crop&w=1200&q=80'],
        ['title' => 'London', 'hint' => 'Landmark: Big Ben | Country: United Kingdom', 'answers' => ['London'], 'image' => 'https://images.unsplash.com/photo-1513635269975-59663e0ac1ad?auto=format&fit=crop&w=1200&q=80'],
    ];
}

function pvpFetchRound(string $mode): array
{
    if ($mode === 'movie') return pvpFetchMovieApi() ?? pvpFallbackMovie();
    if ($mode === 'locations') return pvpFetchLocationApi() ?? pvpLocationPool()[array_rand(pvpLocationPool())];
    return pvpFetchGameApi() ?? pvpFallbackGame();
}

function pvpDatabaseNow(PDO $pdo): string
{
    $row = $pdo->query('SELECT NOW() AS now_value')->fetch();
    return (string) ($row['now_value'] ?? date('Y-m-d H:i:s'));
}

function pvpDatabaseDateReached(PDO $pdo, ?string $value): bool
{
    if (empty($value)) return false;
    $stmt = $pdo->prepare('SELECT CASE WHEN :date_value <= NOW() THEN 1 ELSE 0 END AS reached');
    $stmt->execute(['date_value' => $value]);
    return (int) ($stmt->fetch()['reached'] ?? 0) === 1;
}

function pvpDatabaseDateDelayReached(PDO $pdo, ?string $value, int $seconds): bool
{
    if (empty($value)) return false;
    $seconds = max(0, $seconds);
    $stmt = $pdo->prepare('SELECT CASE WHEN DATE_ADD(:date_value, INTERVAL ' . $seconds . ' SECOND) <= NOW() THEN 1 ELSE 0 END AS reached');
    $stmt->execute(['date_value' => $value]);
    return (int) ($stmt->fetch()['reached'] ?? 0) === 1;
}

function pvpCreateRound(PDO $pdo, int $matchId, int $roundNumber, string $mode, int $duration): void
{
    $duration = max(PVP_MIN_ROUND_SECONDS, min(PVP_MAX_ROUND_SECONDS, $duration));
    $exists = $pdo->prepare('SELECT id FROM pvp_rounds WHERE match_id = :match_id AND round_number = :round_number LIMIT 1');
    $exists->execute(['match_id' => $matchId, 'round_number' => $roundNumber]);
    if ($exists->fetch()) return;
    $round = pvpFetchRound($mode);
    $answers = !empty($round['answers']) ? array_map('pvpNormalizeAnswer', $round['answers']) : pvpAnswerOptions($round['title']);
    $stmt = $pdo->prepare('INSERT IGNORE INTO pvp_rounds (match_id, round_number, title, image, hint, answers_json, started_at, ends_at) VALUES (:match_id, :round_number, :title, :image, :hint, :answers_json, NOW(), DATE_ADD(NOW(), INTERVAL ' . $duration . ' SECOND))');
    $stmt->execute(['match_id' => $matchId, 'round_number' => $roundNumber, 'title' => $round['title'], 'image' => $round['image'], 'hint' => $round['hint'], 'answers_json' => json_encode($answers)]);
}

function pvpLoadMatch(PDO $pdo, string $code): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM pvp_matches WHERE code = :code LIMIT 1');
    $stmt->execute(['code' => strtoupper(trim($code))]);
    return $stmt->fetch() ?: null;
}

function pvpRequireMember(array $match, array $user): void
{
    if ((int) $match['host_user_id'] !== $user['id'] && (int) ($match['guest_user_id'] ?? 0) !== $user['id']) jsonResponse(['error' => 'Du bist nicht in diesem PvP-Raum.'], 403);
}

function pvpAvatarFromRow(array $row): array
{
    return [
        'skin' => (string) ($row['avatar_skin'] ?? 'blue'),
        'eyes' => (string) ($row['avatar_eyes'] ?? 'calm'),
        'mouth' => (string) ($row['avatar_mouth'] ?? 'smile'),
        'accessory' => (string) ($row['avatar_accessory'] ?? 'none'),
        'shape' => (string) ($row['avatar_shape'] ?? 'round'),
        'hair' => (string) ($row['avatar_hair'] ?? 'none'),
        'beard' => (string) ($row['avatar_beard'] ?? 'none'),
        'glasses' => (string) ($row['avatar_glasses'] ?? 'none'),
        'necklace' => (string) ($row['avatar_necklace'] ?? 'none'),
        'animation' => (string) ($row['avatar_animation'] ?? 'none'),
    ];
}

function pvpEnsureUserProfileColumns(PDO $pdo): void
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
    ];
    foreach ($columns as $column => $definition) {
        if (!pvpSchemaColumnExists($pdo, 'users', $column)) {
            pvpSchemaExec($pdo, 'ALTER TABLE users ADD COLUMN ' . $column . ' ' . $definition);
        }
    }
    $missing = [];
    foreach (array_keys($columns) as $column) {
        if (!pvpSchemaColumnExists($pdo, 'users', $column)) $missing[] = 'users.' . $column;
    }
    if ($missing) {
        jsonResponse(['error' => 'PvP-Profilspalten fehlen auf dem Server. Importiere den aktuellen SQL-Dump oder ergaenze die Spalten. Fehlend: ' . implode(', ', $missing)], 500);
    }
}

function pvpPlayers(PDO $pdo, array $match): array
{
    pvpEnsureUserProfileColumns($pdo);
    $ids = array_values(array_filter([(int) $match['host_user_id'], (int) ($match['guest_user_id'] ?? 0)]));
    if (!$ids) return [];
    $stmt = $pdo->prepare('SELECT id, COALESCE(NULLIF(game_name, ""), name) AS name, avatar_skin, avatar_eyes, avatar_mouth, avatar_accessory, avatar_shape, avatar_hair, avatar_beard, avatar_glasses, avatar_necklace, avatar_animation FROM users WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')');
    $stmt->execute($ids);
    $players = [];
    foreach ($stmt->fetchAll() as $p) {
        $players[(int) $p['id']] = [
            'id' => (int) $p['id'],
            'name' => (string) $p['name'],
            'score' => 0,
            'answered' => false,
            'ready' => false,
            'avatar' => pvpAvatarFromRow($p),
        ];
    }
    $ready = $pdo->prepare('SELECT user_id FROM pvp_ready WHERE match_id = :match_id');
    $ready->execute(['match_id' => (int) $match['id']]);
    foreach ($ready->fetchAll() as $r) if (isset($players[(int) $r['user_id']])) $players[(int) $r['user_id']]['ready'] = true;
    $scores = $pdo->prepare('SELECT user_id, COALESCE(SUM(points), 0) score FROM pvp_answers WHERE match_id = :match_id GROUP BY user_id');
    $scores->execute(['match_id' => (int) $match['id']]);
    foreach ($scores->fetchAll() as $s) if (isset($players[(int) $s['user_id']])) $players[(int) $s['user_id']]['score'] = (int) $s['score'];
    return array_values($players);
}

function pvpCurrentRound(PDO $pdo, array $match): ?array
{
    if ((int) $match['current_round'] <= 0) return null;
    $stmt = $pdo->prepare('SELECT * FROM pvp_rounds WHERE match_id = :match_id AND round_number = :round_number LIMIT 1');
    $stmt->execute(['match_id' => (int) $match['id'], 'round_number' => (int) $match['current_round']]);
    return $stmt->fetch() ?: null;
}

function pvpBuildChange(array $before, array $after, int $delta, string $mode, int $score, int $maxScore): array
{
    $breakdown = pvpScoreBreakdown($score, $maxScore, pvpRankScoreRules((string) $before['rank']));

    return [
        'mode' => $mode,
        'delta' => $delta,
        'before' => $before,
        'after' => $after,
        'score' => $score,
        'max_score' => $maxScore,
        'total_rounds' => $breakdown['total_rounds'],
        'correct_rounds' => $breakdown['correct_rounds'],
        'missed_rounds' => $breakdown['missed_rounds'],
        'missed_penalty' => $breakdown['missed_penalty'],
        'win_delta' => $breakdown['win_delta'],
        'max_delta' => $breakdown['max_delta'],
        'max_gain' => $breakdown['max_gain'],
        'max_loss' => $breakdown['max_loss'],
        'leave_gain' => $breakdown['leave_gain'],
        'flawless' => $breakdown['flawless'],
        'rank_floor' => pvpRankFloor((string) $after['rank']),
        'next_target' => pvpNextRankTarget((string) $after['rank']),
    ];
}

function pvpApplyRanking(PDO $pdo, array $match, ?int $winnerId, ?int $gainOverride = null): void
{
    if ((int) ($match['ranking_applied'] ?? 0) === 1 || !$winnerId || empty($match['guest_user_id'])) return;
    $mode = in_array($match['mode'], PVP_MODES, true) ? $match['mode'] : 'game';
    $players = pvpPlayers($pdo, $match);
    $scores = [];
    foreach ($players as $player) $scores[(int) $player['id']] = (int) $player['score'];
    $maxScore = max(10, (int) $match['max_rounds'] * 10);
    $changes = [];

    foreach ([(int) $match['host_user_id'], (int) $match['guest_user_id']] as $playerId) {
        $score = $scores[$playerId] ?? 0;
        $won = $playerId === $winnerId;
        $before = pvpRankingRow($pdo, $playerId, $mode);
        $delta = pvpScoreDelta($before, $won, $score, $maxScore, $won ? $gainOverride : null);
        pvpUpsertRanking($pdo, $playerId, $mode, $delta, $won);
        $after = pvpRankingRow($pdo, $playerId, $mode);
        $changes[$playerId][$mode] = pvpBuildChange($before, $after, $delta, $mode, $score, $maxScore);

        if (pvpHasAllModeRankings($pdo, $playerId)) {
            $gb = pvpRankingRow($pdo, $playerId, 'global');
            $gd = pvpScoreDelta($gb, $won, $score, $maxScore, $won ? $gainOverride : null);
            pvpUpsertRanking($pdo, $playerId, 'global', $gd, $won);
            $ga = pvpRankingRow($pdo, $playerId, 'global');
            $changes[$playerId]['global'] = pvpBuildChange($gb, $ga, $gd, 'global', $score, $maxScore);
        }
    }
    $stmt = $pdo->prepare('UPDATE pvp_matches SET ranking_applied = 1, ranking_changes_json = :changes WHERE id = :id');
    $stmt->execute(['changes' => json_encode($changes), 'id' => (int) $match['id']]);
}

function pvpAdvance(PDO $pdo, array $match): array
{
    $postMatchChoices = json_decode((string) ($match['post_match_choices_json'] ?? ''), true);
    $postMatchChoices = is_array($postMatchChoices) ? $postMatchChoices : [];
    if (($match['status'] ?? '') === 'finished' && in_array('leave', $postMatchChoices, true)) return $match;

    if ($match['status'] === 'ready') {
        $playerIds = array_values(array_filter([(int) $match['host_user_id'], (int) ($match['guest_user_id'] ?? 0)]));
        if (count($playerIds) < 2) return $match;
        $count = $pdo->prepare('SELECT COUNT(DISTINCT user_id) ready_count FROM pvp_ready WHERE match_id = ? AND user_id IN (' . implode(',', array_fill(0, count($playerIds), '?')) . ')');
        $count->execute(array_merge([(int) $match['id']], $playerIds));
        if ((int) ($count->fetch()['ready_count'] ?? 0) >= 2) {
            $pdo->prepare('UPDATE pvp_matches SET status = "playing", current_round = 1, starts_at = NULL, post_match_choices_json = NULL, updated_at = NOW() WHERE id = :id')->execute(['id' => (int) $match['id']]);
            pvpCreateRound($pdo, (int) $match['id'], 1, $match['mode'], (int) $match['round_duration']);
            return pvpLoadMatch($pdo, $match['code']) ?? $match;
        }
        if (pvpDatabaseDateReached($pdo, $match['ready_deadline_at'] ?? null)) {
            $pdo->prepare('UPDATE pvp_matches SET status = "finished", updated_at = NOW() WHERE id = :id')->execute(['id' => (int) $match['id']]);
            return pvpLoadMatch($pdo, $match['code']) ?? $match;
        }
        return $match;
    }
    if ($match['status'] === 'starting') {
        if (empty($match['starts_at']) || pvpDatabaseDateReached($pdo, $match['starts_at'] ?? null)) {
            $stmt = $pdo->prepare('UPDATE pvp_matches SET status = "playing", current_round = 1, updated_at = NOW() WHERE id = :id AND status = "starting"');
            $stmt->execute(['id' => (int) $match['id']]);
            pvpCreateRound($pdo, (int) $match['id'], 1, $match['mode'], (int) $match['round_duration']);
            return pvpLoadMatch($pdo, $match['code']) ?? $match;
        }
        return $match;
    }
    if ($match['status'] !== 'playing') return $match;
    $round = pvpCurrentRound($pdo, $match);
    if (!$round) {
        pvpCreateRound($pdo, (int) $match['id'], (int) $match['current_round'], $match['mode'], (int) $match['round_duration']);
        return pvpLoadMatch($pdo, $match['code']) ?? $match;
    }
    $correct = $pdo->prepare('SELECT COUNT(*) correct_count FROM pvp_answers WHERE round_id = :round_id AND is_correct = 1');
    $correct->execute(['round_id' => (int) $round['id']]);
    if (empty($round['completed_at']) && ((int) ($correct->fetch()['correct_count'] ?? 0) > 0 || pvpDatabaseDateReached($pdo, $round['ends_at'] ?? null))) {
        $pdo->prepare('UPDATE pvp_rounds SET completed_at = NOW() WHERE id = :id AND completed_at IS NULL')->execute(['id' => (int) $round['id']]);
        $round = pvpCurrentRound($pdo, $match);
    }
    if (empty($round['completed_at']) || !pvpDatabaseDateDelayReached($pdo, $round['completed_at'] ?? null, PVP_RESULT_SECONDS)) return $match;
    $next = (int) $match['current_round'] + 1;
    if ($next > (int) $match['max_rounds']) {
        $players = pvpPlayers($pdo, $match);
        usort($players, fn($a, $b) => ($b['score'] <=> $a['score']) ?: ($a['id'] <=> $b['id']));
        if (count($players) >= 2 && $players[0]['score'] === $players[1]['score']) {
            $pdo->prepare('UPDATE pvp_matches SET current_round = :round, updated_at = NOW() WHERE id = :id')->execute(['round' => $next, 'id' => (int) $match['id']]);
            pvpCreateRound($pdo, (int) $match['id'], $next, $match['mode'], (int) $match['round_duration']);
            return pvpLoadMatch($pdo, $match['code']) ?? $match;
        }
        $winnerId = count($players) >= 2 ? $players[0]['id'] : null;
        $pdo->prepare('UPDATE pvp_matches SET status = "finished", winner_user_id = :winner_id, updated_at = NOW() WHERE id = :id')->execute(['winner_id' => $winnerId, 'id' => (int) $match['id']]);
        pvpApplyRanking($pdo, $match, $winnerId);
        return pvpLoadMatch($pdo, $match['code']) ?? $match;
    }
    $pdo->prepare('UPDATE pvp_matches SET current_round = :round, updated_at = NOW() WHERE id = :id')->execute(['round' => $next, 'id' => (int) $match['id']]);
    pvpCreateRound($pdo, (int) $match['id'], $next, $match['mode'], (int) $match['round_duration']);
    return pvpLoadMatch($pdo, $match['code']) ?? $match;
}

function pvpState(PDO $pdo, array $match, array $user): array
{
    $match = pvpAdvance($pdo, $match);
    if (($match['status'] ?? '') === 'finished' && !empty($match['winner_user_id']) && (int) ($match['ranking_applied'] ?? 0) === 0) {
        pvpApplyRanking($pdo, $match, (int) $match['winner_user_id']);
        $match = pvpLoadMatch($pdo, (string) $match['code']) ?? $match;
    }
    $round = pvpCurrentRound($pdo, $match);
    $players = pvpPlayers($pdo, $match);
    $winner = null;
    foreach ($players as $p) if ((int) ($match['winner_user_id'] ?? 0) === $p['id']) $winner = $p;
    $myAnswer = null;
    if ($round) {
        $stmt = $pdo->prepare('SELECT answer, is_correct, points FROM pvp_answers WHERE round_id = :round_id AND user_id = :user_id LIMIT 1');
        $stmt->execute(['round_id' => (int) $round['id'], 'user_id' => (int) $user['id']]);
        $myAnswer = $stmt->fetch() ?: null;
    }
    $changes = json_decode((string) ($match['ranking_changes_json'] ?? ''), true);
    $changes = is_array($changes) ? $changes : [];
    $postMatchChoices = json_decode((string) ($match['post_match_choices_json'] ?? ''), true);
    $postMatchChoices = is_array($postMatchChoices) ? $postMatchChoices : [];
    $postMatchAction = in_array('leave', $postMatchChoices, true) ? 'leave' : null;
    return [
        'match' => ['code' => $match['code'], 'status' => $match['status'], 'mode' => $match['mode'], 'current_round' => (int) $match['current_round'], 'max_rounds' => (int) $match['max_rounds'], 'sudden_death' => (int) $match['current_round'] > (int) $match['max_rounds'], 'winner_user_id' => isset($match['winner_user_id']) ? (int) $match['winner_user_id'] : null, 'winner_name' => $winner['name'] ?? null, 'round_duration' => (int) $match['round_duration'], 'ready_deadline_at' => $match['ready_deadline_at'] ?? null, 'starts_at' => $match['starts_at'] ?? null],
        'user' => $user,
        'server_now' => pvpDatabaseNow($pdo),
        'backend_version' => PVP_BACKEND_VERSION,
        'players' => $players,
        'rankings' => pvpRankingSet($pdo, (int) $user['id']),
        'ranking_change' => $changes[(string) $user['id']] ?? $changes[$user['id']] ?? null,
        'post_match_action' => $postMatchAction,
        'round' => $round ? ['number' => (int) $round['round_number'], 'image' => $round['image'], 'hint' => $round['hint'], 'ends_at' => $round['ends_at'], 'completed' => !empty($round['completed_at']), 'title' => !empty($round['completed_at']) || $match['status'] === 'finished' ? $round['title'] : null, 'my_answer' => $myAnswer ? ['answer' => $myAnswer['answer'], 'is_correct' => (bool) $myAnswer['is_correct'], 'points' => (int) $myAnswer['points']] : null] : null,
    ];
}

function pvpSuggestion(array $round, string $guess): ?string
{
    $guess = pvpNormalizeAnswer($guess);
    if (strlen($guess) < 3) return null;
    $answers = json_decode($round['answers_json'], true);
    $answers = is_array($answers) ? $answers : [];
    $answers[] = pvpNormalizeAnswer($round['title']);
    foreach (array_unique($answers) as $answer) {
        $answer = pvpNormalizeAnswer($answer);
        if (strlen($guess) < max(3, (int) floor(strlen($answer) * 0.45))) continue;
        if (str_starts_with($answer, $guess) || (strlen($guess) >= 5 && str_contains($answer, $guess)) || abs(strlen($answer) - strlen($guess)) <= 3 && levenshtein($guess, $answer) <= 3) return $round['title'];
    }
    return null;
}

$pdo = getDatabaseConnection();
pvpEnsureSchema($pdo);
$user = requireAuthenticatedUser();
$body = readJsonBody();

if ($route === 'pvp-create') {
    if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
    $mode = in_array((string) ($body['mode'] ?? 'game'), PVP_MODES, true) ? (string) $body['mode'] : 'game';
    $code = pvpGenerateCode($pdo);
    $stmt = $pdo->prepare('INSERT INTO pvp_matches (code, mode, status, host_user_id, max_rounds, round_duration) VALUES (:code, :mode, "waiting", :host, :rounds, :duration)');
    $stmt->execute(['code' => $code, 'mode' => $mode, 'host' => $user['id'], 'rounds' => pvpClampInt($body['max_rounds'] ?? null, PVP_DEFAULT_MAX_ROUNDS, PVP_MIN_ROUNDS, PVP_MAX_ROUNDS), 'duration' => pvpClampInt($body['round_duration'] ?? null, PVP_DEFAULT_ROUND_SECONDS, PVP_MIN_ROUND_SECONDS, PVP_MAX_ROUND_SECONDS)]);
    jsonResponse(pvpState($pdo, pvpLoadMatch($pdo, $code), $user), 201);
}

if ($route === 'pvp-join') {
    if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
    $code = strtoupper(trim((string) ($body['code'] ?? '')));
    $match = pvpLoadMatch($pdo, $code);
    if (!$match) jsonResponse(['error' => 'PvP-Raum wurde nicht gefunden.'], 404);
    if ((int) $match['host_user_id'] === $user['id']) {
        jsonResponse(['error' => 'Du bist schon Host in diesem Raum. Bitte nutze im zweiten Browser einen anderen Account.'], 409);
    }
    if ($match['status'] !== 'waiting' || !empty($match['guest_user_id'])) jsonResponse(['error' => 'Dieser PvP-Raum ist nicht mehr frei.'], 409);
    $pdo->prepare('DELETE FROM pvp_ready WHERE match_id = :match_id')->execute(['match_id' => (int) $match['id']]);
    $pdo->prepare('UPDATE pvp_matches SET guest_user_id = :guest, status = "ready", ready_deadline_at = DATE_ADD(NOW(), INTERVAL ' . PVP_READY_SECONDS . ' SECOND), starts_at = NULL, current_round = 0, winner_user_id = NULL, ranking_applied = 0, ranking_changes_json = NULL, post_match_choices_json = NULL, updated_at = NOW() WHERE id = :id')->execute(['guest' => $user['id'], 'id' => (int) $match['id']]);
    jsonResponse(pvpState($pdo, pvpLoadMatch($pdo, $code), $user));
}

if ($route === 'pvp-ready') {
    if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
    $match = pvpLoadMatch($pdo, (string) ($body['code'] ?? ''));
    if (!$match) jsonResponse(['error' => 'PvP-Raum wurde nicht gefunden.'], 404);
    pvpRequireMember($match, $user);
    if (!in_array($match['status'], ['ready', 'starting'], true)) jsonResponse(['error' => 'Dieser Raum ist noch nicht bereit.'], 409);
    if (empty($match['guest_user_id'])) jsonResponse(['error' => 'Es fehlt noch ein zweiter Spieler.'], 409);
    if (!empty($match['post_match_choices_json'])) {
        $pdo->prepare('UPDATE pvp_matches SET post_match_choices_json = NULL WHERE id = :id')->execute(['id' => (int) $match['id']]);
        $match = pvpLoadMatch($pdo, (string) $match['code']) ?? $match;
    }
    $stmt = $pdo->prepare('INSERT INTO pvp_ready (match_id, user_id) VALUES (:match_id, :user_id) ON DUPLICATE KEY UPDATE ready_at = ready_at');
    $stmt->execute(['match_id' => (int) $match['id'], 'user_id' => (int) $user['id']]);
    jsonResponse(pvpState($pdo, pvpLoadMatch($pdo, (string) $match['code']) ?? $match, $user));
}

if ($route === 'pvp-state') {
    if ($method !== 'GET') jsonResponse(['error' => 'Method not allowed'], 405);
    $match = pvpLoadMatch($pdo, (string) ($_GET['code'] ?? ''));
    if (!$match) jsonResponse(['error' => 'PvP-Raum wurde nicht gefunden.'], 404);
    pvpRequireMember($match, $user);
    jsonResponse(pvpState($pdo, $match, $user));
}

if ($route === 'pvp-suggest') {
    if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
    $match = pvpLoadMatch($pdo, (string) ($body['code'] ?? ''));
    if (!$match) jsonResponse(['error' => 'PvP-Raum wurde nicht gefunden.'], 404);
    pvpRequireMember($match, $user);
    $round = pvpCurrentRound($pdo, $match);
    $suggestion = $round && empty($round['completed_at']) ? pvpSuggestion($round, (string) ($body['guess'] ?? '')) : null;
    jsonResponse(['suggestions' => $suggestion ? [$suggestion] : []]);
}

if ($route === 'pvp-submit') {
    if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
    $answer = trim((string) ($body['answer'] ?? ''));
    if ($answer === '') jsonResponse(['error' => 'Bitte eine Antwort eingeben.'], 400);
    $match = pvpLoadMatch($pdo, (string) ($body['code'] ?? ''));
    if (!$match) jsonResponse(['error' => 'PvP-Raum wurde nicht gefunden.'], 404);
    pvpRequireMember($match, $user);
    $round = pvpCurrentRound($pdo, $match);
    if ($match['status'] !== 'playing' || !$round || !empty($round['completed_at']) || pvpDatabaseDateReached($pdo, $round['ends_at'] ?? null)) jsonResponse(['error' => 'Diese Runde ist bereits vorbei.'], 409);
    $answers = json_decode($round['answers_json'], true);
    $isCorrect = in_array(pvpNormalizeAnswer($answer), is_array($answers) ? $answers : [], true);
    if ($isCorrect) {
        $pdo->beginTransaction();
        $lock = $pdo->prepare('SELECT completed_at FROM pvp_rounds WHERE id = :id FOR UPDATE');
        $lock->execute(['id' => (int) $round['id']]);
        $winner = $pdo->prepare('SELECT id FROM pvp_answers WHERE round_id = :round_id AND is_correct = 1 LIMIT 1 FOR UPDATE');
        $winner->execute(['round_id' => (int) $round['id']]);
        if (!$winner->fetch()) {
            $pdo->prepare('INSERT INTO pvp_answers (match_id, round_id, user_id, answer, is_correct, points) VALUES (:match_id, :round_id, :user_id, :answer, 1, 10)')->execute(['match_id' => (int) $match['id'], 'round_id' => (int) $round['id'], 'user_id' => (int) $user['id'], 'answer' => $answer]);
            $pdo->prepare('UPDATE pvp_rounds SET completed_at = NOW() WHERE id = :id AND completed_at IS NULL')->execute(['id' => (int) $round['id']]);
        }
        $pdo->commit();
    }
    $state = pvpState($pdo, pvpLoadMatch($pdo, $match['code']), $user);
    $state['last_attempt'] = ['answer' => $answer, 'is_correct' => $isCorrect, 'scored' => $isCorrect && !empty($state['round']['my_answer']['points'])];
    jsonResponse($state);
}

if ($route === 'pvp-chat-list') {
    if ($method !== 'GET') jsonResponse(['error' => 'Method not allowed'], 405);
    $match = pvpLoadMatch($pdo, (string) ($_GET['code'] ?? ''));
    if (!$match) jsonResponse(['error' => 'PvP-Raum wurde nicht gefunden.'], 404);
    pvpRequireMember($match, $user);
    $stmt = $pdo->prepare('SELECT m.id, m.user_id, u.name, m.message, m.created_at FROM pvp_chat_messages m JOIN users u ON u.id = m.user_id WHERE m.match_id = :match_id AND m.id > :after_id ORDER BY m.id ASC LIMIT 50');
    $stmt->bindValue(':match_id', (int) $match['id'], PDO::PARAM_INT);
    $stmt->bindValue(':after_id', pvpClampInt($_GET['after_id'] ?? 0, 0, 0, PHP_INT_MAX), PDO::PARAM_INT);
    $stmt->execute();
    jsonResponse(['messages' => $stmt->fetchAll()]);
}

if ($route === 'pvp-chat-send') {
    if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
    $message = trim((string) ($body['message'] ?? ''));
    if ($message === '') jsonResponse(['error' => 'Bitte eine Nachricht eingeben.'], 400);
    $match = pvpLoadMatch($pdo, (string) ($body['code'] ?? ''));
    if (!$match) jsonResponse(['error' => 'PvP-Raum wurde nicht gefunden.'], 404);
    pvpRequireMember($match, $user);
    $message = function_exists('mb_substr') ? mb_substr($message, 0, 300) : substr($message, 0, 300);
    $pdo->prepare('INSERT INTO pvp_chat_messages (match_id, user_id, message) VALUES (:match_id, :user_id, :message)')->execute(['match_id' => (int) $match['id'], 'user_id' => (int) $user['id'], 'message' => $message]);
    jsonResponse(['ok' => true], 201);
}

if ($route === 'pvp-leaderboard') {
    if ($method !== 'GET') jsonResponse(['error' => 'Method not allowed'], 405);
    pvpEnsureUserProfileColumns($pdo);
    $mode = in_array((string) ($_GET['mode'] ?? 'global'), ['game','movie','locations','global'], true) ? (string) $_GET['mode'] : 'global';
    if ($mode === 'global') {
        $stmt = $pdo->query('SELECT u.id user_id, COALESCE(SUM(r.points),0) points, COALESCE(SUM(r.wins),0) wins, COALESCE(SUM(r.losses),0) losses, COALESCE(NULLIF(u.game_name,""),u.name) name, u.avatar_skin, u.avatar_eyes, u.avatar_mouth, u.avatar_accessory, u.avatar_shape, u.avatar_hair, u.avatar_beard, u.avatar_glasses, u.avatar_necklace, u.avatar_animation FROM users u LEFT JOIN pvp_rankings r ON r.user_id=u.id AND r.mode IN ("game","movie","locations") GROUP BY u.id,u.game_name,u.name,u.avatar_skin,u.avatar_eyes,u.avatar_mouth,u.avatar_accessory,u.avatar_shape,u.avatar_hair,u.avatar_beard,u.avatar_glasses,u.avatar_necklace,u.avatar_animation ORDER BY points DESC,wins DESC,losses ASC,user_id ASC,name ASC LIMIT 100');
    } else {
        $stmt = $pdo->prepare('SELECT u.id user_id, COALESCE(r.points,0) points, COALESCE(r.wins,0) wins, COALESCE(r.losses,0) losses, COALESCE(NULLIF(u.game_name,""),u.name) name, u.avatar_skin, u.avatar_eyes, u.avatar_mouth, u.avatar_accessory, u.avatar_shape, u.avatar_hair, u.avatar_beard, u.avatar_glasses, u.avatar_necklace, u.avatar_animation FROM users u LEFT JOIN pvp_rankings r ON r.user_id=u.id AND r.mode=:mode ORDER BY points DESC,wins DESC,losses ASC,u.id ASC,name ASC LIMIT 100');
        $stmt->execute(['mode'=>$mode]);
    }
    $players=[]; $position=1;
    foreach ($stmt->fetchAll() as $row) {
        $rank = pvpRankName((int)$row['points'], $position);
        $coins = pvpMonthlyCoins($rank, $position);
        $players[]=['position'=>$position,'user_id'=>(int)$row['user_id'],'name'=>(string)$row['name'],'points'=>(int)$row['points'],'wins'=>(int)$row['wins'],'losses'=>(int)$row['losses'],'rank'=>$rank,'monthly_coins'=>$coins,'avatar'=>pvpAvatarFromRow($row)];
        $position++;
    }
    jsonResponse(['mode'=>$mode,'players'=>$players]);
}

if ($route === 'pvp-leave') {
    if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
    $match = pvpLoadMatch($pdo, (string) ($body['code'] ?? ''));
    if (!$match) jsonResponse(['error' => 'PvP-Raum wurde nicht gefunden.'], 404);
    pvpRequireMember($match, $user);
    if ($match['status'] === 'finished') {
        $choices = json_decode((string) ($match['post_match_choices_json'] ?? ''), true);
        $choices = is_array($choices) ? $choices : [];
        $choices[(string) $user['id']] = 'leave';
        $pdo->prepare('UPDATE pvp_matches SET post_match_choices_json = :choices, updated_at = NOW() WHERE id = :id')->execute(['choices' => json_encode($choices), 'id' => (int) $match['id']]);
        jsonResponse(['action' => 'leave']);
    }

    $hostId = (int) $match['host_user_id'];
    $guestId = (int) ($match['guest_user_id'] ?? 0);
    $leaverId = (int) $user['id'];
    $winnerId = null;
    if ($guestId > 0) {
        $winnerId = $leaverId === $hostId ? $guestId : $hostId;
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE pvp_rounds SET completed_at = NOW() WHERE match_id = :match_id AND completed_at IS NULL')->execute(['match_id' => (int) $match['id']]);
        $pdo->prepare('UPDATE pvp_matches SET status = "finished", winner_user_id = :winner_id, post_match_choices_json = NULL, updated_at = NOW() WHERE id = :id')->execute(['winner_id' => $winnerId, 'id' => (int) $match['id']]);
        $updated = pvpLoadMatch($pdo, $match['code']) ?? $match;
        if ($winnerId) pvpApplyRanking($pdo, $updated, $winnerId, 50);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    $updated = pvpLoadMatch($pdo, $match['code']) ?? $match;
    $state = pvpState($pdo, $updated, $user);
    $state['left_match'] = true;
    jsonResponse($state);
}

if ($route === 'pvp-post-match') {
    if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
    $match = pvpLoadMatch($pdo, (string) ($body['code'] ?? ''));
    if (!$match) jsonResponse(['error' => 'PvP-Raum wurde nicht gefunden.'], 404);
    pvpRequireMember($match, $user);
    if ($match['status'] !== 'finished') jsonResponse(pvpState($pdo, $match, $user));

    $choices = json_decode((string) ($match['post_match_choices_json'] ?? ''), true);
    $choices = is_array($choices) ? $choices : [];
    $choice = (string) ($body['choice'] ?? 'leave');
    if ($choice !== 'rematch') {
        $choices[(string) $user['id']] = 'leave';
        $pdo->prepare('UPDATE pvp_matches SET post_match_choices_json = :choices, updated_at = NOW() WHERE id = :id')->execute(['choices' => json_encode($choices), 'id' => (int) $match['id']]);
        jsonResponse(['action' => 'leave']);
    }

    $choices[(string) $user['id']] = 'rematch';
    $playerIds = array_values(array_filter([(int) $match['host_user_id'], (int) ($match['guest_user_id'] ?? 0)]));
    $bothRematch = count($playerIds) === 2;
    foreach ($playerIds as $playerId) {
        if (($choices[(string) $playerId] ?? '') !== 'rematch') $bothRematch = false;
    }

    if (!$bothRematch) {
        $pdo->prepare('UPDATE pvp_matches SET post_match_choices_json = :choices, updated_at = NOW() WHERE id = :id')->execute(['choices' => json_encode($choices), 'id' => (int) $match['id']]);
        jsonResponse(['action' => 'waiting_rematch', 'message' => 'Warte auf den anderen Spieler.']);
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM pvp_answers WHERE match_id = :match_id')->execute(['match_id' => (int) $match['id']]);
        $pdo->prepare('DELETE FROM pvp_rounds WHERE match_id = :match_id')->execute(['match_id' => (int) $match['id']]);
        $pdo->prepare('DELETE FROM pvp_ready WHERE match_id = :match_id')->execute(['match_id' => (int) $match['id']]);
        $pdo->prepare('UPDATE pvp_matches SET status = "ready", current_round = 0, winner_user_id = NULL, starts_at = NULL, ready_deadline_at = DATE_ADD(NOW(), INTERVAL ' . PVP_READY_SECONDS . ' SECOND), ranking_applied = 0, ranking_changes_json = NULL, post_match_choices_json = NULL, updated_at = NOW() WHERE id = :id')->execute(['id' => (int) $match['id']]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    jsonResponse(pvpState($pdo, pvpLoadMatch($pdo, $match['code']) ?? $match, $user));
}

jsonResponse(['error' => 'Route not found'], 404);
