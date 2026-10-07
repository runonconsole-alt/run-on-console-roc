<?php
/**
 * Run On Console (ROC) - Gaming Profile Processing Engine
 * Manages 20 validated esports gallery avatar selection, bio, games, IDs, and social profile links.
 */

const ROC_VALID_AVATARS = [
    'avatar_01', 'avatar_02', 'avatar_03', 'avatar_04', 'avatar_05',
    'avatar_06', 'avatar_07', 'avatar_08', 'avatar_09', 'avatar_10',
    'avatar_11', 'avatar_12', 'avatar_13', 'avatar_14', 'avatar_15',
    'avatar_16', 'avatar_17', 'avatar_18', 'avatar_19', 'avatar_20'
];

function rocGamingProfile(PDO $db, int $userId): array {
    $q = $db->prepare('SELECT city, date_of_birth, avatar_data, favorite_games, gaming_ids, social_links FROM roc_gaming_profiles WHERE user_id = ?');
    $q->execute([$userId]);
    $p = $q->fetch() ?: [];

    return [
        'city' => $p['city'] ?? '',
        'dateOfBirth' => $p['date_of_birth'] ?? '',
        'avatarData' => '',
        'favoriteGames' => json_decode($p['favorite_games'] ?? '[]', true) ?: [],
        'gamingIds' => json_decode($p['gaming_ids'] ?? '{}', true) ?: new stdClass(),
        'socialLinks' => json_decode($p['social_links'] ?? '{}', true) ?: new stdClass()
    ];
}

function rocSaveGaming(PDO $db, int $id, array $in): void {
    $name = trim((string)($in['name'] ?? ''));
    $bio = trim((string)($in['bio'] ?? ''));
    $city = trim((string)($in['city'] ?? ''));
    $country = trim((string)($in['country'] ?? ''));
    $dob = (string)($in['dateOfBirth'] ?? '');
    $icon = (string)($in['avatarIcon'] ?? 'avatar_01');

    if (!$name || strlen($name) > 120 || strlen($bio) > 2000 || strlen($city) > 100 || strlen($country) > 50) {
        throw new InvalidArgumentException('Please check display name, bio, city, and country length limits.');
    }

    if ($dob !== '') {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $dob);
        if (!$date || $date->format('Y-m-d') !== $dob || $dob > gmdate('Y-m-d') || $dob < '1900-01-01') {
            throw new InvalidArgumentException('Please enter a valid date of birth.');
        }
    }

    // Strictly validate avatar icon against 20 gallery options; reject arbitrary URLs/paths/base64 uploads
    if (!in_array($icon, ROC_VALID_AVATARS, true)) {
        throw new InvalidArgumentException('Please choose a valid esports avatar from the official gallery.');
    }

    $games = $in['favoriteGames'] ?? [];
    $ids = $in['gamingIds'] ?? [];
    $links = $in['socialLinks'] ?? [];

    if (!is_array($games) || !is_array($ids) || !is_array($links) || count($games) > 20 || count($ids) > 12 || count($links) > 12) {
        throw new InvalidArgumentException('Too many profile entries.');
    }

    foreach ($games as $v) {
        if (!is_string($v) || strlen($v) > 80) throw new InvalidArgumentException('Invalid game name entry.');
    }

    foreach ($ids as $k => $v) {
        if (!is_string($v) || strlen((string)$k) > 30 || strlen($v) > 100) throw new InvalidArgumentException('Invalid gaming ID entry.');
    }

    foreach ($links as $k => $v) {
        if (!is_string($v) || strlen((string)$k) > 30 || strlen($v) > 300) throw new InvalidArgumentException('Invalid social link URL.');
        if ($v !== '' && (!filter_var($v, FILTER_VALIDATE_URL) || strtolower(parse_url($v, PHP_URL_SCHEME) ?? '') !== 'https' || parse_url($v, PHP_URL_USER) || parse_url($v, PHP_URL_PASS))) {
            throw new InvalidArgumentException('Social links must be valid HTTPS URLs without credentials.');
        }
    }

    $db->beginTransaction();
    try {
        $db->prepare('UPDATE users SET name = ? WHERE id = ?')->execute([$name, $id]);
        
        $db->prepare('INSERT INTO user_profiles (user_id, country, bio, avatar_icon) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE country = VALUES(country), bio = VALUES(bio), avatar_icon = VALUES(avatar_icon)')
           ->execute([$id, $country, $bio, $icon]);

        $db->prepare('INSERT INTO roc_gaming_profiles (user_id, city, date_of_birth, avatar_data, favorite_games, gaming_ids, social_links) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE city = VALUES(city), date_of_birth = VALUES(date_of_birth), avatar_data = VALUES(avatar_data), favorite_games = VALUES(favorite_games), gaming_ids = VALUES(gaming_ids), social_links = VALUES(social_links)')
           ->execute([$id, $city, $dob ?: null, '', json_encode(array_values($games)), json_encode($ids), json_encode($links)]);

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}
