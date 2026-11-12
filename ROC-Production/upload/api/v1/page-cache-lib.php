<?php
/**
 * Run On Console — a short-lived copy of the "Can I run it?" pages (Deploy 178).
 *
 * Owner, 2026-11-10: the game pages were slow; every visit read the 2.5 MB game list and drew the page again
 * (0.4-0.7 s on the server). A drawn page is now kept in ~/roc-cms-cache/pages/ (CMS > Clear cache empties it) for
 * 3 hours, and only while nothing it shows has changed: the game list, the page code, the template and site layer,
 * the CMS game edits, the products, the FPS reports and the players' answers are part of the key.
 * Only plain GET pages without a query string are kept (no searches, sitemaps or JSON).
 *
 * Deploy 183: on 12 November the hosting account (1 GB) was full. Every change of the game list (every 10 minutes
 * while the Steam update runs) made a new file for every page, and the old ones stayed a day, uncompressed. Now each
 * page has ONE file (its key is stored inside it), compressed, the game list counts by its date (not the file time),
 * and the folder never grows past ROC_PAGE_CACHE_MAX: the oldest copies go first.
 */

const ROC_PAGE_CACHE_TTL = 3 * 3600;
const ROC_PAGE_CACHE_MAX = 80 * 1024 * 1024;   // 80 MB

function rocPageCacheDir(string $root): string { return dirname($root) . '/roc-cms-cache/pages'; }

/** Serves the kept copy and exits, or remembers where rocSendHtml() keeps the new one. */
function rocPageCacheTry(string $root, ?PDO $pdo, array $extra = []): void {
    // Deploy 179: the visitor's own address (the server adds ?slug=... to every game page, which kept them all out).
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET' || (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_QUERY) !== '' || headers_sent()) return;
    if (!function_exists('gzcompress')) return;
    $files = ['/roc-steam-deck.json', '/game-render.php', '/game-i18n.php', '/blog-render.php', '/cms-templates/blog-post.html', '/cms-templates/site-layer.json',
              '/api/v1/game-pages-lib.php', '/api/v1/agent-lib.php', '/api/v1/fps-lib.php', '/api/v1/ram-pages-lib.php', '/api/v1/upcoming-lib.php', '/api/v1/gpu-vs-lib.php',
              '/api/v1/i18n-lib.php', '/api/v1/report-lib.php', '/api/v1/mypc-lib.php', '/api/v1/steam-deck-lib.php', '/api/v1/steam-deck-page.php', '/api/v1/link-boost-lib.php',
              '/api/v1/bottleneck-lib.php', '/api/v1/game-votes-lib.php'];
    $path = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    // The game list by its date (Deploy 183): the file changes every 10 minutes while new Steam games are added.
    $key = [function_exists('rocGpUpdated') ? (int)rocGpUpdated($root) : (int)@filemtime($root . '/roc-steam-games.json')];
    foreach ($files as $f) $key[] = (int)@filemtime($root . $f);
    if ($pdo) {
        foreach (["SELECT MAX(updated_at) FROM products", "SELECT CONCAT(COUNT(*), '|', MAX(created_at), '|', MAX(updated_at)) FROM roc_messages WHERE kind = 'fps'",
                  "SELECT MD5(setting_value) FROM cms_settings WHERE setting_key = 'game_edits'",
                  "SELECT CONCAT(COUNT(*), '|', MAX(updated_at), '|', SUM(hidden)) FROM roc_game_votes"] as $sql) {   // Deploy 181
            try { $key[] = (string)$pdo->query($sql)->fetchColumn(); } catch (\Throwable $e) { $key[] = '-'; }
        }
    }
    $key = md5(json_encode(array_merge($key, $extra)));
    $file = rocPageCacheDir($root) . '/' . md5($path . '|' . json_encode($extra)) . '.gz';
    if (is_file($file) && time() - (int)filemtime($file) < ROC_PAGE_CACHE_TTL) {
        $raw = @gzuncompress((string)file_get_contents($file));
        $d = is_string($raw) ? json_decode($raw, true) : null;
        if (is_array($d) && ($d['key'] ?? '') === $key && is_string($d['html'] ?? null) && $d['html'] !== '') {
            header('Content-Type: text/html; charset=utf-8');
            header('Cache-Control: public, max-age=0, must-revalidate');
            header('X-Content-Type-Options: nosniff');
            header('X-ROC-Cache: hit');
            if (!empty($d['robots'])) header('X-Robots-Tag: ' . $d['robots']);
            if (!empty($d['mod'])) header('Last-Modified: ' . gmdate('D, d M Y H:i:s', (int)$d['mod']) . ' GMT');
            echo $d['html'];
            exit;
        }
    }
    $GLOBALS['ROC_PAGE_CACHE'] = [$file, $key];
}

/** Called by rocSendHtml() with the finished page. */
function rocPageCacheSave(string $html, ?int $modified, string $robots): void {
    $c = $GLOBALS['ROC_PAGE_CACHE'] ?? null;
    if (!is_array($c) || http_response_code() !== 200) return;
    [$file, $key] = $c;
    $dir = dirname($file);
    if (!is_dir($dir)) @mkdir($dir, 0700, true);
    // Deploy 179: broken characters in Steam's text are replaced; an empty result is never written.
    $json = json_encode(['key' => $key, 'html' => $html, 'mod' => $modified, 'robots' => $robots], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if (!is_string($json) || $json === '') return;
    $gz = gzcompress($json, 6);
    if (!is_string($gz)) return;
    $tmp = $file . '.' . getmypid();
    if (@file_put_contents($tmp, $gz) !== false) @rename($tmp, $file);
    if (random_int(1, 50) === 1) rocPageCachePrune($dir);
}

/** Old copies go, and the folder stays under ROC_PAGE_CACHE_MAX (oldest first). Returns the bytes freed. */
function rocPageCachePrune(string $dir, int $max = ROC_PAGE_CACHE_MAX): int {
    $files = []; $total = 0; $freed = 0;
    foreach (glob($dir . '/*') ?: [] as $f) {
        $s = (int)@filesize($f); $t = (int)@filemtime($f);
        // Copies past their time, and the uncompressed .json files of Deploys 178-182.
        if (time() - $t > ROC_PAGE_CACHE_TTL || substr($f, -3) !== '.gz') { if (@unlink($f)) $freed += $s; continue; }
        $files[$f] = [$t, $s]; $total += $s;
    }
    if ($total > $max) {
        uasort($files, function ($a, $b) { return $a[0] <=> $b[0]; });
        foreach ($files as $f => [$t, $s]) { if ($total <= $max * 0.8) break; if (@unlink($f)) { $total -= $s; $freed += $s; } }
    }
    return $freed;
}
