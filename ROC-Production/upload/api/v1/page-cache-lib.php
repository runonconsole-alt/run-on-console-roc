<?php
/**
 * Run On Console — a short-lived copy of the "Can I run it?" pages (Deploy 178).
 *
 * Owner, 2026-11-10: the game pages were slow; every visit read the 2.5 MB game list and drew the page again
 * (0.4-0.7 s on the server). A drawn page is now kept in ~/roc-cms-cache/pages/ (CMS > Clear cache empties it) for
 * 3 hours, and only while nothing it shows has changed: the game list, the page code, the template and site layer,
 * the CMS game edits, the products and the FPS reports are part of the key, so a change shows at once.
 * Only plain GET pages without a query string are kept (no searches, sitemaps or JSON).
 */

const ROC_PAGE_CACHE_TTL = 3 * 3600;

/** Serves the kept copy and exits, or remembers where rocSendHtml() keeps the new one. */
function rocPageCacheTry(string $root, ?PDO $pdo, array $extra = []): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET' || (string)($_SERVER['QUERY_STRING'] ?? '') !== '' || headers_sent()) return;
    $files = ['/roc-steam-games.json', '/roc-steam-deck.json', '/game-render.php', '/game-i18n.php', '/blog-render.php', '/cms-templates/blog-post.html', '/cms-templates/site-layer.json',
              '/api/v1/game-pages-lib.php', '/api/v1/agent-lib.php', '/api/v1/fps-lib.php', '/api/v1/ram-pages-lib.php', '/api/v1/upcoming-lib.php', '/api/v1/gpu-vs-lib.php',
              '/api/v1/i18n-lib.php', '/api/v1/report-lib.php', '/api/v1/mypc-lib.php', '/api/v1/steam-deck-lib.php', '/api/v1/steam-deck-page.php', '/api/v1/link-boost-lib.php'];
    $key = [(string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH)];
    foreach ($files as $f) $key[] = (int)@filemtime($root . $f);
    if ($pdo) {
        foreach (["SELECT MAX(updated_at) FROM products", "SELECT CONCAT(COUNT(*), '|', MAX(created_at), '|', MAX(updated_at)) FROM roc_messages WHERE kind = 'fps'",
                  "SELECT MD5(setting_value) FROM cms_settings WHERE setting_key = 'game_edits'"] as $sql) {
            try { $key[] = (string)$pdo->query($sql)->fetchColumn(); } catch (\Throwable $e) { $key[] = '-'; }
        }
    }
    $key = array_merge($key, $extra);
    $dir = dirname($root) . '/roc-cms-cache/pages';
    $file = $dir . '/' . md5(json_encode($key)) . '.json';
    if (is_file($file) && time() - (int)filemtime($file) < ROC_PAGE_CACHE_TTL) {
        $d = json_decode((string)file_get_contents($file), true);
        if (is_array($d) && is_string($d['html'] ?? null) && $d['html'] !== '') {
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
    $GLOBALS['ROC_PAGE_CACHE'] = $file;
}

/** Called by rocSendHtml() with the finished page. */
function rocPageCacheSave(string $html, ?int $modified, string $robots): void {
    $file = (string)($GLOBALS['ROC_PAGE_CACHE'] ?? '');
    if ($file === '' || http_response_code() !== 200) return;
    $dir = dirname($file);
    if (!is_dir($dir)) @mkdir($dir, 0700, true);
    $tmp = $file . '.' . getmypid();
    if (@file_put_contents($tmp, json_encode(['html' => $html, 'mod' => $modified, 'robots' => $robots], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) !== false) @rename($tmp, $file);
    // Now and then: copies older than a day go.
    if (random_int(1, 200) === 1) foreach (glob($dir . '/*.json') ?: [] as $f) if (filemtime($f) < time() - 86400) @unlink($f);
}
