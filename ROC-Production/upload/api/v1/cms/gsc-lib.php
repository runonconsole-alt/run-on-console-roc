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

/* ------------------------------------------------------------ search queries */
/*
 * Search queries (Deploy 120): once a day the Search Console search data of the last 4 weeks (query + page:
 * clicks, impressions, average position) is kept in cms_settings gsc_queries. Only queries that fit the site
 * (gaming, PC hardware, consoles, game requirements) are kept; adult, gambling, piracy and cheat searches never.
 * Used for:
 *   - the anchor text of the links inside blog, news and guide posts (rocTopicLinks, link-boost-lib.php);
 *   - the blog writer: the real searches that fit the next post go into its brief (rocGscForTopic);
 *   - new blog topics from questions people ask where ROC is not on page 1 yet (rocGscTopics);
 *   - CMS > Indexing > Search queries.
 */
const ROC_GSC_BLOCK = '/\b(porn\w*|sex\w*|xxx|nude\w*|nsfw|hentai|onlyfans|casino|betting|gambl\w*|slot machine|crack(ed)?|torrent\w*|pirat\w*|keygen|serial key|free download|apk|hack(s|ed|er)?|cheat engine|aimbot|wallhack|unblocked|loan|crypto\w*|bitcoin|dating|escort)\b/i';
const ROC_GSC_NICHE = '/\b(gam(e|es|ing|er|ers|eplay)|pc|computer|desktop|laptops?|gpus?|cpus?|ram|ddr[345]|ssd|nvme|hdd|storage|monitors?|display|hz|1080p|1440p|4k|keyboards?|mouse|mice|headsets?|headphones?|speakers?|microphone|webcam|controllers?|gamepad|console|ps[45]|playstation|xbox|switch|nintendo|steam|handhelds?|graphics|processor|motherboard|psu|cooler|chairs?|rtx|gtx|radeon|ryzen|intel|nvidia|amd|geforce|fps|requirements?|specs?|settings|emulators?|retro|vr|streaming|esports?|can i run|bottleneck|overclock\w*)\b/i';

function rocGscNicheOk(string $q, string $path = '', bool $strict = false): bool {
    $q = mb_strtolower(trim($q));
    if (mb_strlen($q) < 3 || mb_strlen($q) > 90 || !preg_match('/[a-z]/', $q) || preg_match(ROC_GSC_BLOCK, $q)) return false;
    if (preg_match(ROC_GSC_NICHE, $q)) return true;
    // A game or product name alone ("elden ring", "aoc 24g2") is fine for its own page, not as a new blog topic.
    return !$strict && (bool)preg_match('#^/(can-i-run|products|compare)/#', $path);
}

/** Once a day: the last 4 weeks of search data. Returns a log line or null. */
function rocGscQueriesSync(PDO $pdo): ?string {
    $day = gmdate('Y-m-d');
    if ((string)rocBlogGet($pdo, 'gsc_q_day', '') === $day) return null;
    $tok = rocGscToken($pdo);
    if (!$tok) return null;
    rocBlogSet($pdo, 'gsc_q_day', $day);                 // one try a day
    $from = gmdate('Y-m-d', time() - 30 * 86400); $to = gmdate('Y-m-d', time() - 2 * 86400);
    [$code, $out] = rocGscHttp('https://searchconsole.googleapis.com/webmasters/v3/sites/' . rawurlencode(ROC_GSC_SITE) . '/searchAnalytics/query',
        json_encode(['startDate' => $from, 'endDate' => $to, 'dimensions' => ['query', 'page'], 'rowLimit' => 5000, 'type' => 'web']),
        ['Content-Type: application/json', 'Authorization: Bearer ' . $tok], 60);
    $d = json_decode($out, true);
    if ($code !== 200) { rocBlogSet($pdo, 'gsc_error', ['t' => gmdate('c'), 'e' => 'search queries ' . $code . ': ' . mb_substr($out, 0, 200)]); return null; }
    $rows = []; $skipped = 0;
    foreach ((array)($d['rows'] ?? []) as $r) {
        $q = trim(mb_strtolower((string)($r['keys'][0] ?? '')));
        $page = (string)($r['keys'][1] ?? '');
        if (strpos($page, rtrim(ROC_GSC_SITE, '/')) !== 0) continue;
        $path = (string)parse_url($page, PHP_URL_PATH);
        if (!rocGscNicheOk($q, $path)) { $skipped++; continue; }
        $rows[] = ['q' => $q, 'p' => $path, 'c' => (int)($r['clicks'] ?? 0), 'i' => (int)($r['impressions'] ?? 0), 'pos' => round((float)($r['position'] ?? 0), 1)];
    }
    usort($rows, function ($a, $b) { return [$b['i'], $b['c']] <=> [$a['i'], $a['c']]; });
    $rows = array_slice($rows, 0, 2500);
    rocBlogSet($pdo, 'gsc_queries', ['day' => $day, 'from' => $from, 'to' => $to, 'skipped' => $skipped, 'rows' => $rows]);
    $added = rocGscTopics($pdo, $rows);
    return 'Search queries: ' . count($rows) . ' kept (' . $skipped . ' off-topic left out)' . ($added ? ', ' . $added . ' new blog topic' . ($added === 1 ? '' : 's') : '');
}

function rocGscQueryRows(PDO $pdo): array {
    static $rows = null;
    if ($rows !== null) return $rows;
    $d = rocBlogGet($pdo, 'gsc_queries', []);
    return $rows = (is_array($d) && is_array($d['rows'] ?? null)) ? $d['rows'] : [];
}

/** Significant words of a phrase (for matching queries with topics and titles). */
function rocGscWords(string $s): array {
    $stop = ['the', 'and', 'for', 'with', 'what', 'how', 'which', 'you', 'your', 'are', 'can', 'does', 'best', 'top', 'good', 'is', 'to', 'of', 'in', 'on', 'a', 'an', 'vs', 'or', 'it', 'my', 'do', 'i', 'should', 'why', 'when', 'will', '2025', '2026', '2027'];
    $w = [];
    foreach (preg_split('/[^a-z0-9]+/', mb_strtolower($s)) as $x) if ($x !== '' && !in_array($x, $stop, true) && strlen($x) > 1) $w[rtrim($x, 's')] = 1;
    return array_keys($w);
}

/** Real searches that fit a blog topic (for the writer's brief): up to 8, most shown first. */
function rocGscForTopic(PDO $pdo, array $topic): array {
    $kw = rocGscWords((string)($topic['keyword'] ?? '') . ' ' . (string)($topic['title'] ?? ''));
    if (!$kw) return [];
    $seen = []; $out = [];
    foreach (rocGscQueryRows($pdo) as $r) {
        if (isset($seen[$r['q']]) || !rocGscNicheOk($r['q'], '', true)) continue;
        $qw = rocGscWords($r['q']);
        if (count($qw) < 2) continue;
        $hit = count(array_intersect($qw, $kw));
        if ($hit >= 2 || ($hit >= 1 && count($qw) === 2)) { $seen[$r['q']] = 1; $out[] = $r['q']; }
        if (count($out) >= 8) break;
    }
    return $out;
}

/**
 * New blog topics from Search Console (at most 3 a day, at most 20 waiting): a question or buying search
 * (how / what / which / why / best / vs / review ...) about a product category, seen at least 15 times in
 * 4 weeks, where ROC is not on page 1 yet (average position worse than 8), and no post or planned topic
 * covers it already. It joins the plan of its category and is written in that category's next round.
 */
function rocGscTopics(PDO $pdo, array $rows): int {
    $plan = rocBlogPlan($pdo);
    $waiting = 0; $have = [];
    foreach ($plan['topics'] as $t) {
        if (($t['source'] ?? '') === 'gsc' && ($t['status'] ?? '') === 'planned') $waiting++;
        $have[] = rocGscWords((string)$t['keyword'] . ' ' . (string)$t['title']);
    }
    foreach (rocBlogSite($pdo)['posts'] as $b) $have[] = rocGscWords((string)$b['title']);
    if ($waiting >= 20) return 0;
    $cats = [];
    foreach ($plan['pillars'] as $p) if (($p['kind'] ?? '') === 'category') {
        $slug = substr((string)$p['id'], 4);
        $n = rocBlogNouns($slug, (string)$p['name']);
        $cats[$p['id']] = array_unique(array_merge(rocGscWords($n[0]), rocGscWords($n[2]), rocGscWords((string)$p['name'])));
    }
    // One row per query: impressions added up over its pages, the best position.
    $q = [];
    foreach ($rows as $r) {
        if (!isset($q[$r['q']])) $q[$r['q']] = ['i' => 0, 'pos' => 999];
        $q[$r['q']]['i'] += $r['i']; $q[$r['q']]['pos'] = min($q[$r['q']]['pos'], $r['pos']);
    }
    uasort($q, function ($a, $b) { return $b['i'] <=> $a['i']; });
    $added = 0;
    foreach ($q as $query => $s) {
        if ($added >= 3 || $waiting + $added >= 20) break;
        if ($s['i'] < 15 || $s['pos'] <= 8 || !rocGscNicheOk($query, '', true)) continue;
        if (!preg_match('/^(how|what|which|why|is|are|can|does|do|should|best|top|cheap\w*)\b|\b(vs|versus|review|worth|guide|for gaming|explained)\b/', $query)) continue;
        $qw = rocGscWords($query);
        if (count($qw) < 2) continue;
        $pillar = null; $hits = 0;
        foreach ($cats as $id => $words) { $h = count(array_intersect($qw, $words)); if ($h > $hits) { $hits = $h; $pillar = $id; } }
        if (!$pillar) continue;
        foreach ($have as $hw) if ($hw && count(array_intersect($qw, $hw)) >= max(2, (int)ceil(count($qw) * 0.75))) continue 2;   // covered already
        $stage = preg_match('/\b(vs|versus)\b/', $query) ? 'mofu' : (preg_match('/^(best|top|cheap\w*)\b|\b(review|worth)\b/', $query) ? 'bofu' : 'tofu');
        $title = ucfirst($query) . (preg_match('/^(how|what|which|why|is|are|can|does|do|should)\b/', $query) ? '?' : '');
        $id = substr('gsc-' . rocBlogSlug($query), 0, 80);
        $plan['topics'][] = ['id' => $id, 'pillar' => $pillar, 'stage' => $stage, 'title' => $title, 'keyword' => $query, 'secondary' => [],
                             'products' => [], 'status' => 'planned', 'source' => 'gsc', 'added' => gmdate('Y-m-d'), 'impressions' => $s['i'], 'position' => $s['pos']];
        $have[] = $qw;
        $added++;
    }
    if ($added) rocBlogSet($pdo, 'blog_plan', $plan);
    return $added;
}

/** Called by the scheduler: a few pages a run. Returns a log line or null. */
function rocGscTick(PDO $pdo): ?string {
    if (!rocGscKey()) return null;
    rocGscTable($pdo);
    $synced = rocGscSync($pdo);
    $qs = null;
    try { $qs = rocGscQueriesSync($pdo); } catch (\Throwable $e) { $qs = 'Search queries error: ' . $e->getMessage(); }
    $day = gmdate('Y-m-d');
    $c = rocBlogGet($pdo, 'gsc_daily', []); $c = (is_array($c) && ($c['day'] ?? '') === $day) ? $c : ['day' => $day, 'n' => 0];
    $left = min(ROC_GSC_PER_RUN, ROC_GSC_DAILY - (int)$c['n']);
    if ($left <= 0) return $qs;
    $now = gmdate('Y-m-d H:i:s');
    $rows = $pdo->query("SELECT url_hash, url, verdict FROM roc_index_status
        WHERE checked_at IS NULL
           OR (COALESCE(verdict, '') <> 'PASS' AND checked_at < '" . gmdate('Y-m-d H:i:s', time() - 2 * 86400) . "')
           OR (verdict = 'PASS' AND checked_at < '" . gmdate('Y-m-d H:i:s', time() - 10 * 86400) . "')
        ORDER BY (checked_at IS NULL) DESC, (verdict = 'PASS') ASC, checked_at ASC LIMIT " . (int)$left)->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) return $synced ? 'Indexing: page list updated (' . $synced . ' pages)' . ($qs ? '; ' . $qs : '') : $qs;
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
    return 'Indexing: ' . $n . ' checked' . ($new ? ', ' . count($new) . ' newly indexed' : '') . ($mail ? '; ' . $mail : '') . ($qs ? '; ' . $qs : '');
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
    $html = '<!doctype html><!--roc-from:alerts--><html><body style="margin:0;background:#fff;font-family:Arial,Helvetica,sans-serif"><div style="max-width:560px;margin:auto;padding:28px 16px;color:#262626;font-size:15px;line-height:23px">'
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
