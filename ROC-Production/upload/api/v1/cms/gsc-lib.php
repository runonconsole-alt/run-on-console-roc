<?php
/**
 * Run On Console — CMS > Indexing (Deploy 117): which pages Google has indexed, from the Search Console
 * URL Inspection API, checked by the scheduler a few at a time.
 *
 * Key: a Google service account JSON key at ~/config/gsc-key.json (outside public_html; the service account
 * is a Full user of the Search Console property). Without the key nothing runs.
 *
 * Every scheduler run (10 minutes) inspects ROC_GSC_PER_RUN pages, at most ROC_GSC_DAILY a day (Google allows
 * 2,000): pages never checked first, then pages not indexed yet (every 2 days), then indexed ones (every 10 days).
 * The page list is the site's sitemaps, read once a day. When a page becomes indexed it is remembered, and once a
 * day (after 20:00 Pakistan time) an email lists the newly indexed pages (CMS setting gsc_email, default the
 * owner's Gmail). Pages that are not indexed yet go to the link box (link_boost, api/v1/link-boost-lib.php).
 */

const ROC_GSC_SITE = 'https://runonconsole.com/';
const ROC_GSC_PER_RUN = 14;
const ROC_GSC_DAILY = 1800;

function rocGscKeyFile(): string { return dirname(__DIR__, 4) . '/config/gsc-key.json'; }

function rocGscKey(): ?array {
    $k = json_decode((string)@file_get_contents(rocGscKeyFile()), true);
    return is_array($k) && !empty($k['client_email']) && !empty($k['private_key']) ? $k : null;
}

function rocGscHttp(string $url, ?string $body, array $headers = [], int $timeout = 25): array {
    $c = curl_init($url);
    curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3,
                           CURLOPT_HTTPHEADER => $headers, CURLOPT_USERAGENT => 'RunOnConsole-Indexing/1.0']);
    if ($body !== null) { curl_setopt($c, CURLOPT_POST, true); curl_setopt($c, CURLOPT_POSTFIELDS, $body); }
    $out = (string)curl_exec($c);
    $code = (int)curl_getinfo($c, CURLINFO_HTTP_CODE);
    curl_close($c);
    return [$code, $out];
}

function rocGscB64(string $s): string { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }

/** An access token (one hour), kept in cms_settings gsc_token. */
function rocGscToken(PDO $pdo): ?string {
    $t = rocBlogGet($pdo, 'gsc_token', []);
    if (is_array($t) && !empty($t['token']) && (int)($t['exp'] ?? 0) > time() + 120) return (string)$t['token'];
    $k = rocGscKey();
    if (!$k || !function_exists('openssl_sign')) return null;
    $now = time();
    $jwt = rocGscB64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])) . '.' . rocGscB64(json_encode([
        'iss' => $k['client_email'], 'scope' => 'https://www.googleapis.com/auth/webmasters.readonly',
        'aud' => 'https://oauth2.googleapis.com/token', 'iat' => $now, 'exp' => $now + 3600]));
    $sig = '';
    if (!openssl_sign($jwt, $sig, (string)$k['private_key'], OPENSSL_ALGO_SHA256)) return null;
    [$code, $out] = rocGscHttp('https://oauth2.googleapis.com/token', http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $jwt . '.' . rocGscB64($sig)]), ['Content-Type: application/x-www-form-urlencoded']);
    $d = json_decode($out, true);
    if ($code !== 200 || empty($d['access_token'])) { rocBlogSet($pdo, 'gsc_error', ['t' => gmdate('c'), 'e' => 'token ' . $code . ': ' . mb_substr($out, 0, 200)]); return null; }
    rocBlogSet($pdo, 'gsc_token', ['token' => $d['access_token'], 'exp' => $now + (int)($d['expires_in'] ?? 3600)]);
    return (string)$d['access_token'];
}

function rocGscTable(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $pdo->exec('CREATE TABLE IF NOT EXISTS roc_index_status (
        url_hash CHAR(40) NOT NULL PRIMARY KEY, url VARCHAR(500) NOT NULL, kind VARCHAR(20) NOT NULL DEFAULT \'page\',
        verdict VARCHAR(20) NULL, coverage VARCHAR(160) NULL, last_crawl DATETIME NULL, checked_at DATETIME NULL,
        indexed_at DATETIME NULL, created_at DATETIME NOT NULL, KEY k_checked (checked_at), KEY k_verdict (verdict)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    $done = true;
}

function rocGscKind(string $url): string {
    $p = (string)parse_url($url, PHP_URL_PATH);
    foreach (['/can-i-run/' => 'game', '/products/' => 'product', '/compare/' => 'compare', '/category/' => 'category', '/news/' => 'news',
              '/blogs/' => 'blog', '/guides/' => 'guide', '/gaming-platforms/' => 'platform'] as $pre => $k) if (strpos($p, $pre) === 0) return $k;
    return 'page';
}

/** Once a day: the sitemap pages become the list (new ones added, removed ones dropped). */
function rocGscSync(PDO $pdo): ?int {
    $day = gmdate('Y-m-d');
    if ((string)rocBlogGet($pdo, 'gsc_synced', '') === $day) return null;
    [$code, $idx] = rocGscHttp(ROC_GSC_SITE . 'sitemap.xml', null);
    if ($code !== 200) return null;
    $urls = [];
    foreach (array_slice(preg_match_all('#<loc>([^<]+)</loc>#', $idx, $m) ? $m[1] : [], 0, 30) as $sm) {
        [$c2, $x] = rocGscHttp(html_entity_decode($sm), null);
        if ($c2 === 200 && preg_match_all('#<loc>([^<]+)</loc>#', $x, $m2)) foreach ($m2[1] as $u) $urls[html_entity_decode($u)] = 1;
    }
    if (count($urls) < 50) return null;   // a broken sitemap must not empty the list
    rocGscTable($pdo);
    $ins = $pdo->prepare('INSERT IGNORE INTO roc_index_status (url_hash, url, kind, created_at) VALUES (?, ?, ?, ?)');
    $now = gmdate('Y-m-d H:i:s');
    foreach (array_keys($urls) as $u) $ins->execute([sha1($u), mb_substr($u, 0, 500), rocGscKind($u), $now]);
    $keep = array_flip(array_map('sha1', array_keys($urls)));
    $del = $pdo->prepare('DELETE FROM roc_index_status WHERE url_hash = ?');
    foreach ($pdo->query('SELECT url_hash FROM roc_index_status')->fetchAll(PDO::FETCH_COLUMN) as $h) if (!isset($keep[$h])) $del->execute([$h]);
    rocBlogSet($pdo, 'gsc_synced', $day);
    return count($urls);
}

/** One URL: [verdict, coverage, lastCrawl] or null. */
function rocGscInspect(PDO $pdo, string $url): ?array {
    $tok = rocGscToken($pdo);
    if (!$tok) return null;
    [$code, $out] = rocGscHttp('https://searchconsole.googleapis.com/v1/urlInspection/index:inspect',
        json_encode(['inspectionUrl' => $url, 'siteUrl' => ROC_GSC_SITE]), ['Content-Type: application/json', 'Authorization: Bearer ' . $tok]);
    $d = json_decode($out, true);
    if ($code !== 200) { rocBlogSet($pdo, 'gsc_error', ['t' => gmdate('c'), 'e' => 'inspect ' . $code . ': ' . mb_substr($out, 0, 200)]); return null; }
    $r = (array)($d['inspectionResult']['indexStatusResult'] ?? []);
    $lc = !empty($r['lastCrawlTime']) ? gmdate('Y-m-d H:i:s', (int)strtotime((string)$r['lastCrawlTime'])) : null;
    return [(string)($r['verdict'] ?? 'NEUTRAL'), mb_substr((string)($r['coverageState'] ?? ''), 0, 160), $lc];
}

/** Called by the scheduler: a few pages a run. Returns a log line or null. */
function rocGscTick(PDO $pdo): ?string {
    if (!rocGscKey()) return null;
    rocGscTable($pdo);
    $synced = rocGscSync($pdo);
    $day = gmdate('Y-m-d');
    $c = rocBlogGet($pdo, 'gsc_daily', []); $c = (is_array($c) && ($c['day'] ?? '') === $day) ? $c : ['day' => $day, 'n' => 0];
    $left = min(ROC_GSC_PER_RUN, ROC_GSC_DAILY - (int)$c['n']);
    if ($left <= 0) return null;
    $now = gmdate('Y-m-d H:i:s');
    $rows = $pdo->query("SELECT url_hash, url, verdict FROM roc_index_status
        WHERE checked_at IS NULL
           OR (COALESCE(verdict, '') <> 'PASS' AND checked_at < '" . gmdate('Y-m-d H:i:s', time() - 2 * 86400) . "')
           OR (verdict = 'PASS' AND checked_at < '" . gmdate('Y-m-d H:i:s', time() - 10 * 86400) . "')
        ORDER BY (checked_at IS NULL) DESC, (verdict = 'PASS') ASC, checked_at ASC LIMIT " . (int)$left)->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) return $synced ? 'Indexing: page list updated (' . $synced . ' pages)' : null;
    $up = $pdo->prepare('UPDATE roc_index_status SET verdict = ?, coverage = ?, last_crawl = ?, checked_at = ?, indexed_at = COALESCE(indexed_at, ?) WHERE url_hash = ?');
    $n = 0; $new = [];
    foreach ($rows as $r) {
        $res = rocGscInspect($pdo, (string)$r['url']);
        if ($res === null) break;                      // no token or Google said no: try again next run
        $n++;
        $isNew = $res[0] === 'PASS' && $r['verdict'] !== 'PASS';
        $up->execute([$res[0], $res[1], $res[2], $now, $res[0] === 'PASS' ? $now : null, $r['url_hash']]);
        if ($isNew && $r['verdict'] !== null) $new[] = (string)$r['url'];   // a page we had seen not indexed
    }
    $c['n'] = (int)$c['n'] + $n;
    rocBlogSet($pdo, 'gsc_daily', $c);
    if ($new) { $q = rocBlogGet($pdo, 'gsc_new', []); $q = is_array($q) ? $q : []; rocBlogSet($pdo, 'gsc_new', array_slice(array_values(array_unique(array_merge($q, $new))), -500)); }
    rocGscBoost($pdo);
    $mail = rocGscEmail($pdo);
    return 'Indexing: ' . $n . ' checked' . ($new ? ', ' . count($new) . ' newly indexed' : '') . ($mail ? '; ' . $mail : '');
}

/** Pages that are not indexed yet (products, comparisons, categories, posts) go first in the link box. */
function rocGscBoost(PDO $pdo): void {
    $paths = [];
    foreach ($pdo->query("SELECT url FROM roc_index_status WHERE checked_at IS NOT NULL AND COALESCE(verdict, '') <> 'PASS'
                          AND kind IN ('product', 'compare', 'category', 'platform') ORDER BY checked_at DESC LIMIT 400")->fetchAll(PDO::FETCH_COLUMN) as $u)
        $paths[] = (string)parse_url((string)$u, PHP_URL_PATH);
    if ($paths) rocBlogSet($pdo, 'link_boost', array_values(array_unique($paths)));
}

/** Once a day after 20:00 Pakistan time: the newly indexed pages by email. */
function rocGscEmail(PDO $pdo): ?string {
    $pk = new DateTime('now', new DateTimeZone('Asia/Karachi'));
    if ((int)$pk->format('G') < 20 || (string)rocBlogGet($pdo, 'gsc_mailed', '') === $pk->format('Y-m-d')) return null;
    rocBlogSet($pdo, 'gsc_mailed', $pk->format('Y-m-d'));
    $new = rocBlogGet($pdo, 'gsc_new', []);
    if (!is_array($new) || !$new) return null;
    $to = (string)rocBlogGet($pdo, 'gsc_email', 'runonconsole@gmail.com');
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return null;
    $st = rocGscStats($pdo);
    $e = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
    $li = '';
    foreach (array_slice($new, 0, 100) as $u) $li .= '<li style="margin:0 0 6px"><a href="' . $e($u) . '" style="color:#047857">' . $e(str_replace(ROC_GSC_SITE, '/', $u)) . '</a></li>';
    $html = '<!doctype html><html><body style="margin:0;background:#fff;font-family:Arial,Helvetica,sans-serif"><div style="max-width:560px;margin:auto;padding:28px 16px;color:#262626;font-size:15px;line-height:23px">'
          . '<div style="font-size:20px;font-weight:700;color:#047857;margin-bottom:18px">Run On Console</div>'
          . '<p style="margin:0 0 12px"><b>' . count($new) . ' page' . (count($new) === 1 ? '' : 's') . '</b> newly indexed by Google:</p><ul style="padding-left:18px;margin:0 0 18px">' . $li . '</ul>'
          . '<p style="margin:0 0 6px;color:#64748b;font-size:13px">Now: ' . (int)$st['indexed'] . ' indexed, ' . (int)$st['not_indexed'] . ' not indexed yet, ' . (int)$st['unchecked'] . ' still to check.</p>'
          . '<p style="margin:0"><a href="https://runonconsole.com/cms/#indexing" style="color:#047857;font-weight:700">Open CMS &gt; Indexing</a></p></div></body></html>';
    require_once dirname(__DIR__) . '/member-lib.php';
    if (!rocDigestQueue($pdo, $to, '[ROC · Indexing] ' . count($new) . ' new page' . (count($new) === 1 ? '' : 's') . ' indexed by Google', $html, 2)) return null;
    rocBlogSet($pdo, 'gsc_new', []);
    return 'email with ' . count($new) . ' new pages';
}

function rocGscStats(PDO $pdo): array {
    rocGscTable($pdo);
    $r = $pdo->query("SELECT COUNT(*) AS total, SUM(verdict = 'PASS') AS indexed, SUM(checked_at IS NOT NULL AND COALESCE(verdict, '') <> 'PASS') AS not_indexed,
                      SUM(checked_at IS NULL) AS unchecked FROM roc_index_status")->fetch(PDO::FETCH_ASSOC) ?: [];
    $by = [];
    foreach ($pdo->query("SELECT kind, COUNT(*) AS total, SUM(verdict = 'PASS') AS indexed, SUM(checked_at IS NOT NULL) AS checked FROM roc_index_status GROUP BY kind")->fetchAll(PDO::FETCH_ASSOC) as $k)
        $by[$k['kind']] = ['total' => (int)$k['total'], 'indexed' => (int)$k['indexed'], 'checked' => (int)$k['checked']];
    return ['total' => (int)($r['total'] ?? 0), 'indexed' => (int)($r['indexed'] ?? 0), 'not_indexed' => (int)($r['not_indexed'] ?? 0), 'unchecked' => (int)($r['unchecked'] ?? 0), 'by_kind' => $by];
}
