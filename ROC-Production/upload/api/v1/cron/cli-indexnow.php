<?php
/**
 * Run On Console — IndexNow for Bing, Yandex, Seznam and Naver (Deploy 86).
 *
 *   php cli-indexnow.php            dry run: the key, its file, the last call
 *   php cli-indexnow.php --apply    writes the key file; from now on every scheduler run sends what changed
 *   php cli-indexnow.php --all      sends every address in the sitemaps once (about 2,600; do this one time)
 *   php cli-indexnow.php --off | --on   stops / restarts the automatic sending
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$ROOT = dirname(__DIR__, 3);
$args = array_slice($argv, 1);
echo "Run On Console - IndexNow (Bing, Yandex, Seznam, Naver)\n\n";
ob_start();
require_once $ROOT . '/api/v1/config.php';
ob_end_clean();
$pdo = function_exists('getDBConnection') ? getDBConnection() : null;
if (!$pdo) exit("Database unavailable.\n");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
require_once $ROOT . '/api/v1/cms/indexnow-lib.php';

if (in_array('--off', $args, true) || in_array('--on', $args, true)) {
    $on = in_array('--on', $args, true);
    rocBlogSet($pdo, 'indexnow_auto', $on ? 'on' : 'off');
    exit('Automatic IndexNow is ' . ($on ? 'on' : 'off') . ".\n");
}

$key = (string)rocBlogGet($pdo, 'indexnow_key', '');
$last = rocBlogGet($pdo, 'indexnow_last', []);
echo '  Key: ' . ($key !== '' ? $key . (is_file($ROOT . '/' . $key . '.txt') ? ' (file in place)' : ' (file missing)') : 'not made yet') . "\n";
echo '  Automatic: ' . ((string)rocBlogGet($pdo, 'indexnow_auto', 'on') === 'off' ? 'off' : 'on (every scheduler run, what changed)') . "\n";
if (is_array($last) && !empty($last['at'])) echo '  Last call: ' . date('Y-m-d H:i', (int)$last['at']) . ', ' . (int)$last['sent'] . ' address(es), ' . (!empty($last['ok']) ? 'ok' : 'error: ' . $last['error']) . "\n";

$apply = in_array('--apply', $args, true);
$all = in_array('--all', $args, true);
if (!$apply && !$all) exit("\nDry run: nothing changed. Run with --apply, then once with --all.\n");

$key = rocIndexNowKey($pdo, $ROOT);
echo "\n  Key file: https://" . ROC_INDEXNOW_HOST . '/' . $key . '.txt ' . (is_file($ROOT . '/' . $key . '.txt') ? "written\n" : "COULD NOT BE WRITTEN\n");
if (!is_array($last) || empty($last['t'])) rocBlogSet($pdo, 'indexnow_last', ['t' => time(), 'sent' => 0, 'ok' => true, 'error' => '']);

if ($all) {
    // Every address of the sitemaps (read from this site itself).
    $urls = [];
    $get = function (string $u) { $c = curl_init($u); curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60, CURLOPT_USERAGENT => 'ROC-IndexNow']); $r = (string)curl_exec($c); curl_close($c); return $r; };
    preg_match_all('#<loc>([^<]+)</loc>#', $get('https://' . ROC_INDEXNOW_HOST . '/sitemap.xml'), $maps);
    foreach ($maps[1] as $m) {
        preg_match_all('#<loc>([^<]+)</loc>#', $get(html_entity_decode($m)), $mm);
        foreach ($mm[1] as $u) $urls[] = html_entity_decode($u);
        echo '  ' . basename((string)parse_url($m, PHP_URL_PATH)) . ': ' . count($mm[1]) . "\n";
    }
    $urls = array_values(array_unique($urls));
    $r = rocIndexNowSend($pdo, $ROOT, $urls);
    rocBlogSet($pdo, 'indexnow_last', ['t' => time(), 'sent' => count($urls), 'ok' => $r['ok'], 'error' => $r['error'], 'at' => time()]);
    echo "\n" . count($urls) . ' address(es) ' . ($r['ok'] ? "sent (HTTP {$r['code']}).\n" : "NOT sent: {$r['error']}\n");
    exit;
}
echo "Done. New and changed pages now go to Bing and the others at once. Send everything once: php " . __FILE__ . " --all\n";
