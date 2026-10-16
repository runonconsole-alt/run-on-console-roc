<?php
/**
 * Run On Console — IndexNow (Deploy 86): new and changed pages are sent to Bing, Yandex, Seznam and
 * Naver at once (one IndexNow call reaches them all), so they no longer wait for a crawl. Bing also
 * feeds ChatGPT search and Copilot. Google does not use IndexNow: it reads the sitemaps.
 *
 *   rocIndexNowKey($pdo, $root)     the site key; its file /{key}.txt (proof the site is ours) is written once
 *   rocIndexNowTick($pdo, $root)    every scheduler run: pages published or changed since the last call
 *                                   (posts, products, game page edits), at most 200 a call
 *   rocIndexNowSend($pdo, $root, $urls)   sends a list now (cli-indexnow.php)
 *
 * State in cms_settings: indexnow_key, indexnow_last {t, sent, ok, error}.
 */
if (defined('ROC_INDEXNOW_LIB')) return;
define('ROC_INDEXNOW_LIB', 1);
require_once __DIR__ . '/blog-engine-lib.php';

const ROC_INDEXNOW_HOST = 'runonconsole.com';
const ROC_INDEXNOW_API = 'https://api.indexnow.org/indexnow';

function rocIndexNowKey(PDO $pdo, string $root): string {
    $key = (string)rocBlogGet($pdo, 'indexnow_key', '');
    if (!preg_match('/^[a-f0-9]{32}$/', $key)) { $key = bin2hex(random_bytes(16)); rocBlogSet($pdo, 'indexnow_key', $key); }
    $file = $root . '/' . $key . '.txt';
    if (!is_file($file) || trim((string)@file_get_contents($file)) !== $key) @file_put_contents($file, $key);
    return $key;
}

/** Addresses published or changed after $since (Unix time), newest first. */
function rocIndexNowChanged(PDO $pdo, int $since): array {
    $urls = [];
    $at = gmdate('Y-m-d H:i:s', $since);
    $cols = rocBlogCols($pdo, 'blogs');
    $mod = in_array('content_modified_at', $cols, true) ? 'COALESCE(content_modified_at, updated_at, published_at)' : 'COALESCE(updated_at, published_at)';
    try {
        $st = $pdo->prepare("SELECT slug, category FROM blogs WHERE status = 'published' AND published_at <= ? AND ({$mod} > ? OR published_at > ?) ORDER BY published_at DESC LIMIT 150");
        $st->execute([gmdate('Y-m-d H:i:s'), $at, $at]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $c = trim((string)($r['category'] ?? ''));
            $sec = strcasecmp($c, 'News') === 0 ? 'news' : (preg_match('/^guides?\s*(:.*)?$/i', $c) ? 'guides' : 'blogs');
            $urls[] = 'https://' . ROC_INDEXNOW_HOST . '/' . $sec . '/' . $r['slug'] . '/';
        }
        if ($urls) foreach (['/updates/', '/news/', '/blogs/', '/guides/'] as $p) $urls[] = 'https://' . ROC_INDEXNOW_HOST . $p;
    } catch (\Throwable $e) {}
    try {
        $pc = rocBlogCols($pdo, 'products');
        $pm = in_array('content_modified_at', $pc, true) ? 'COALESCE(content_modified_at, updated_at)' : 'updated_at';
        $st = $pdo->prepare("SELECT slug FROM products WHERE status = 'published' AND {$pm} > ? ORDER BY {$pm} DESC LIMIT 60");
        $st->execute([$at]);
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $s) $urls[] = 'https://' . ROC_INDEXNOW_HOST . '/products/' . $s . '/';
    } catch (\Throwable $e) {}
    // Game pages changed in CMS > Games.
    $edits = rocBlogGet($pdo, 'game_edits', []);
    foreach ((is_array($edits) ? $edits : []) as $slug => $e) {
        if ((int)($e['t'] ?? 0) > $since && empty($e['hidden'])) $urls[] = 'https://' . ROC_INDEXNOW_HOST . '/can-i-run/' . $slug . '/';
    }
    return array_values(array_unique($urls));
}

/** Sends up to 10,000 addresses in one call. Returns ['ok' => bool, 'code' => int, 'error' => string]. */
function rocIndexNowSend(PDO $pdo, string $root, array $urls): array {
    $urls = array_values(array_unique(array_filter($urls, function ($u) { return strpos((string)$u, 'https://' . ROC_INDEXNOW_HOST . '/') === 0; })));
    if (!$urls) return ['ok' => true, 'code' => 0, 'error' => ''];
    $key = rocIndexNowKey($pdo, $root);
    $c = curl_init(getenv('ROC_INDEXNOW_TEST') ?: ROC_INDEXNOW_API);   // env: local tests only
    curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json; charset=utf-8'],
        CURLOPT_POSTFIELDS => json_encode(['host' => ROC_INDEXNOW_HOST, 'key' => $key, 'keyLocation' => 'https://' . ROC_INDEXNOW_HOST . '/' . $key . '.txt',
                                           'urlList' => array_slice($urls, 0, 10000)], JSON_UNESCAPED_SLASHES)]);
    $raw = (string)curl_exec($c); $code = (int)curl_getinfo($c, CURLINFO_HTTP_CODE); $err = curl_error($c); curl_close($c);
    // 200 = taken, 202 = taken (the key is checked later). 403 = the key file was not found, 422 = an address is not ours, 429 = too often.
    $ok = $code === 200 || $code === 202;
    return ['ok' => $ok, 'code' => $code, 'error' => $ok ? '' : ($err ?: ('HTTP ' . $code . ($raw !== '' ? ': ' . mb_substr(strip_tags($raw), 0, 160) : '')))];
}

/** Every scheduler run: what changed since the last call. Returns a log line or null. */
function rocIndexNowTick(PDO $pdo, string $root): ?string {
    if ((string)rocBlogGet($pdo, 'indexnow_auto', 'on') === 'off') return null;
    $last = rocBlogGet($pdo, 'indexnow_last', []); $last = is_array($last) ? $last : [];
    $since = (int)($last['t'] ?? 0);
    $now = time();
    if ($since === 0) { rocBlogSet($pdo, 'indexnow_last', ['t' => $now, 'sent' => 0, 'ok' => true, 'error' => '']); return null; }   // first run: from now on
    $urls = array_slice(rocIndexNowChanged($pdo, $since), 0, 200);
    if (!$urls) return null;
    $r = rocIndexNowSend($pdo, $root, $urls);
    // A refusal is tried again next run (at most for a day), except a wrong key or address.
    // 403 "SiteVerificationNotCompleted": Bing checks a new key file first (a few hours), so keep these too (3 days).
    $wait = !$r['ok'] && $r['code'] === 403 && stripos($r['error'], 'Verification') !== false;
    $keep = !$r['ok'] && (in_array($r['code'], [0, 429, 500, 502, 503], true) || $wait) && $now - $since < ($wait ? 259200 : 86400);
    rocBlogSet($pdo, 'indexnow_last', ['t' => $keep ? $since : $now, 'sent' => count($urls), 'ok' => $r['ok'], 'error' => $r['error'], 'at' => $now]);
    return 'IndexNow: ' . count($urls) . ' address(es) ' . ($r['ok'] ? 'sent' : 'not sent (' . $r['error'] . ')');
}
