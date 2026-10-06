<?php
/**
 * Run On Console — website settings managed in the CMS:
 *   - social profile links  (cms_settings.social_links)
 *   - Amazon affiliate tag  (cms_settings.amazon_tag)
 *
 * Both are published to /roc-site.json. /roc-nav.js reads that file on every page:
 *   - footer cards marked data-roc-social="facebook" (…) get the saved URL, or are
 *     hidden when that platform has no URL. Until at least one URL is saved, the
 *     footer keeps the links it was built with.
 *   - every Amazon link on the page gets ?tag=<amazon_tag>.
 */

const ROC_SOCIAL_PLATFORMS = [
    'facebook'  => ['label' => 'Facebook',    'host' => 'facebook.com',  'match' => ['facebook.com', 'fb.com']],
    'instagram' => ['label' => 'Instagram',   'host' => 'instagram.com', 'match' => ['instagram.com']],
    'pinterest' => ['label' => 'Pinterest',   'host' => 'pinterest.com', 'match' => ['pinterest.com', 'pin.it']],
    'twitter'   => ['label' => 'X (Twitter)', 'host' => 'x.com',         'match' => ['x.com', 'twitter.com']],
    'youtube'   => ['label' => 'YouTube',     'host' => 'youtube.com',   'match' => ['youtube.com', 'youtu.be']],
    'discord'   => ['label' => 'Discord',     'host' => 'discord.com',   'match' => ['discord.gg', 'discord.com']],
    'reddit'    => ['label' => 'Reddit',      'host' => 'reddit.com',    'match' => ['reddit.com']],
    'linkedin'  => ['label' => 'LinkedIn',    'host' => 'linkedin.com',  'match' => ['linkedin.com']],
    'quora'     => ['label' => 'Quora',       'host' => 'quora.com',     'match' => ['quora.com']],
];

/** Host of the placeholder link in footers built before data-roc-social existed. */
const ROC_SOCIAL_LEGACY_HOST = ['twitter' => 'twitter.com'];

function rocSocialRoot(): string { return realpath(__DIR__ . '/../../..') ?: __DIR__ . '/../../..'; }

function rocSettingGet(PDO $pdo, string $key): ?string {
    try {
        $st = $pdo->prepare('SELECT setting_value FROM cms_settings WHERE setting_key = ? LIMIT 1');
        $st->execute([$key]);
        $v = $st->fetchColumn();
    } catch (\Throwable $e) { return null; }
    return ($v === false || $v === null) ? null : (string)$v;
}

function rocSettingSet(PDO $pdo, string $key, string $value, ?int $userId): void {
    $pdo->prepare("INSERT INTO cms_settings (setting_key, setting_value, updated_by, updated_at) VALUES (?, ?, ?, NOW())
                   ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by), updated_at = NOW()")
        ->execute([$key, $value, $userId]);
}

/** Saved links (platform => url|''), or null when never saved. */
function rocSocialLoad(PDO $pdo): ?array {
    $v = rocSettingGet($pdo, 'social_links');
    if ($v === null) return null;
    $d = json_decode($v, true);
    if (!is_array($d)) return null;
    $out = [];
    foreach (ROC_SOCIAL_PLATFORMS as $k => $_) $out[$k] = (string)($d[$k] ?? '');
    return $out;
}

function rocAmazonTagLoad(PDO $pdo): string {
    return (string)(rocSettingGet($pdo, 'amazon_tag') ?? '');
}

/** Validate: https URL on that platform's domain, or empty. Returns [clean, errors]. */
function rocSocialClean(array $raw): array {
    $clean = []; $errors = [];
    foreach (ROC_SOCIAL_PLATFORMS as $k => $def) {
        $u = trim((string)($raw[$k] ?? ''));
        if ($u === '') { $clean[$k] = ''; continue; }
        if (!preg_match('#^https?://#i', $u)) $u = 'https://' . $u;
        $host = strtolower((string)parse_url($u, PHP_URL_HOST));
        $ok = false;
        foreach ($def['match'] as $m) if ($host === $m || substr($host, -strlen('.' . $m)) === '.' . $m) $ok = true;
        if (!$ok || strlen($u) > 300 || preg_match('/[\s"<>]/', $u)) { $errors[$k] = "Enter a {$def['label']} link (e.g. https://{$def['host']}/…)."; continue; }
        $clean[$k] = preg_replace('#^http://#i', 'https://', $u);
    }
    return [$clean, $errors];
}

/** Amazon Associates tracking ID, e.g. runonconsole-20. Returns [clean, error|null]. */
function rocAmazonTagClean(string $raw): array {
    $t = trim($raw);
    if ($t === '') return ['', null];
    if (preg_match('#[?&]tag=([^&\s]+)#', $t, $m)) $t = $m[1];            // pasted a whole link
    if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{1,62}-\d{2}$/', $t)) {
        return ['', 'Enter your Amazon Associates tracking ID, for example runonconsole-20.'];
    }
    return [$t, null];
}

function rocSocialSave(PDO $pdo, array $links, ?int $userId): void {
    rocSettingSet($pdo, 'social_links', json_encode($links, JSON_UNESCAPED_SLASHES), $userId);
    rocSitePublish($pdo);
}

function rocAmazonTagSave(PDO $pdo, string $tag, ?int $userId): void {
    rocSettingSet($pdo, 'amazon_tag', $tag, $userId);
    rocSitePublish($pdo);
}

/** Public file the website reads. Contains only what the pages show. */
function rocSitePublish(PDO $pdo): void {
    $links = rocSocialLoad($pdo) ?? [];
    $social = [];
    foreach (ROC_SOCIAL_PLATFORMS as $k => $def) {
        $social[] = ['platform' => $k, 'label' => $def['label'], 'match' => ROC_SOCIAL_LEGACY_HOST[$k] ?? $def['host'], 'url' => $links[$k] ?? ''];
    }
    $data = ['social' => $social, 'amazon_tag' => rocAmazonTagLoad($pdo), 'updated' => gmdate('c')];
    $file = rocSocialRoot() . '/roc-site.json';
    $tmp = $file . '.tmp' . getmypid();
    file_put_contents($tmp, json_encode($data, JSON_UNESCAPED_SLASHES));
    rename($tmp, $file);
}

/** Kept for older callers. */
function rocSocialPublish(array $links): void {
    $pdo = getDBConnection();
    if ($pdo) rocSitePublish($pdo);
}
