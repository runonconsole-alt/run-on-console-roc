<?php
/**
 * Run On Console — social kits posted by themselves on the Facebook Page and Instagram (Deploy 95).
 *
 * The scheduler cron (every 10 minutes) takes the oldest kit (social-kit-lib.php) made after auto-posting
 * was turned on and posts its square picture with the caption:
 *   - Facebook Page: a photo post, caption with the link (utm_source=facebook) and the hashtags;
 *   - Instagram (Business or Creator account linked to the Page): the same picture, "link in our bio" instead
 *     of the link (Instagram captions have no clickable links).
 * At most one post a run, one post per platform per hour and ROC_AUTO_DAY_MAX per platform a day, so the
 * accounts never look like spam. A kit that fails 3 times on a platform is skipped. When Meta says the
 * token is no longer valid, auto-posting stops and the CMS shows why.
 *
 * Only official Meta Graph API calls. Settings in cms_settings.social_auto:
 *   on, fb, ig (bool), page_id, page_name, page_token, ig_id, ig_name, expires (0 = never), from (time),
 *   last (platform => time), day (Y-m-d => [platform => count]), fails (kit:platform => count), error, log (newest 20)
 * The token is never sent back to the browser.
 */
if (defined('ROC_SOCIAL_AUTO_LIB')) return;
define('ROC_SOCIAL_AUTO_LIB', 1);
require_once __DIR__ . '/social-kit-lib.php';

const ROC_GRAPH = 'https://graph.facebook.com/v23.0';
const ROC_AUTO_GAP = 3600;      // seconds between two posts on the same platform
const ROC_AUTO_DAY_MAX = 6;     // posts per platform per day (Pakistan time)
const ROC_SITE = 'https://runonconsole.com';

function rocAutoGet(PDO $pdo): array { $a = rocBlogGet($pdo, 'social_auto', []); return is_array($a) ? $a : []; }
function rocAutoSave(PDO $pdo, array $a, ?int $uid = null): void { rocBlogSet($pdo, 'social_auto', $a, $uid); }

/** One Graph API call. Returns ['ok' => true, 'data' => [...]] or ['ok' => false, 'error' => text, 'code' => Meta error code]. */
function rocGraph(string $method, string $path, array $params): array {
    $base = getenv('ROC_GRAPH_TEST') ?: ROC_GRAPH;   // env: local tests only
    $url = $base . $path;
    $c = curl_init($method === 'GET' ? $url . '?' . http_build_query($params) : $url);
    curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60, CURLOPT_CONNECTTIMEOUT => 15]);
    if ($method === 'POST') { curl_setopt($c, CURLOPT_POST, true); curl_setopt($c, CURLOPT_POSTFIELDS, http_build_query($params)); }
    $body = curl_exec($c);
    $http = (int)curl_getinfo($c, CURLINFO_HTTP_CODE);
    $err = curl_error($c);
    curl_close($c);
    if ($body === false) return ['ok' => false, 'error' => 'No answer from Facebook: ' . $err, 'code' => 0];
    $d = json_decode((string)$body, true);
    if (!is_array($d)) return ['ok' => false, 'error' => 'Facebook answered HTTP ' . $http . '.', 'code' => 0];
    if (isset($d['error'])) return ['ok' => false, 'error' => trim((string)($d['error']['error_user_msg'] ?? $d['error']['message'] ?? 'Unknown error')), 'code' => (int)($d['error']['code'] ?? 0)];
    if ($http >= 400) return ['ok' => false, 'error' => 'Facebook answered HTTP ' . $http . '.', 'code' => 0];
    return ['ok' => true, 'data' => $d];
}

/**
 * Connects with a token pasted in the CMS: a Page token, or a (long-lived) user token from which the
 * Page token is taken (a Page token from a long-lived user token never expires). $pageId picks the Page
 * when the user runs more than one. Returns the settings to save, or ['error' => text, 'pages' => [...]].
 */
function rocAutoConnect(string $token, string $pageId = ''): array {
    $fields = 'id,name,access_token,instagram_business_account{id,username}';
    $acc = rocGraph('GET', '/me/accounts', ['fields' => $fields, 'limit' => 50, 'access_token' => $token]);
    if ($acc['ok'] && !empty($acc['data']['data'])) {
        $pages = $acc['data']['data'];
        $page = null;
        foreach ($pages as $p) if ($pageId !== '' && (string)$p['id'] === $pageId) $page = $p;
        if (!$page && count($pages) === 1) $page = $pages[0];
        if (!$page) return ['error' => 'This account runs more than one Page: pick the Page.', 'pages' => array_map(function ($p) { return ['id' => (string)$p['id'], 'name' => (string)$p['name']]; }, $pages)];
        $pageToken = (string)($page['access_token'] ?? '');
    } else {
        // A Page token: /me is the Page itself.
        $me = rocGraph('GET', '/me', ['fields' => 'id,name,instagram_business_account{id,username}', 'access_token' => $token]);
        if (!$me['ok']) return ['error' => 'Facebook refused this token: ' . $me['error']];
        $page = $me['data'];
        if (empty($page['name']) || $acc['ok']) return ['error' => 'This token has no Page. Give it the pages_show_list and pages_manage_posts permissions and pick the Page.'];
        $pageToken = $token;
    }
    if ($pageToken === '') return ['error' => 'Facebook gave no Page token: the token needs pages_show_list and pages_manage_posts.'];
    // Expiry and permissions (a token may check itself).
    $dbg = rocGraph('GET', '/debug_token', ['input_token' => $pageToken, 'access_token' => $pageToken]);
    $scopes = $dbg['ok'] ? (array)($dbg['data']['data']['scopes'] ?? []) : [];
    $expires = $dbg['ok'] ? (int)($dbg['data']['data']['expires_at'] ?? 0) : -1;
    if ($scopes && !in_array('pages_manage_posts', $scopes, true)) return ['error' => 'The token is missing the pages_manage_posts permission.'];
    $ig = $page['instagram_business_account'] ?? null;
    return ['page_id' => (string)$page['id'], 'page_name' => (string)$page['name'], 'page_token' => $pageToken,
            'ig_id' => (string)($ig['id'] ?? ''), 'ig_name' => (string)($ig['username'] ?? ''),
            'ig_ok' => !$scopes || in_array('instagram_content_publish', $scopes, true), 'expires' => $expires];
}

/** The caption of a kit for a platform: link with utm on Facebook, "link in our bio" on Instagram, then the hashtags. */
function rocAutoCaption(array $k, string $pl): string {
    $url = (string)$k['url'];
    $tags = implode(' ', array_map(function ($t) { return '#' . $t; }, (array)($k['hashtags'] ?? [])));
    $cap = (string)($k['caption'] ?? '');
    $cap = $pl === 'instagram' ? str_replace(['Read more: ' . $url, $url], ['Full story: the link in our bio', 'the link in our bio'], $cap)
                               : str_replace($url, $url . '?utm_source=facebook&utm_medium=social&utm_campaign=auto', $cap);
    return trim($cap . ($tags !== '' ? "\n\n" . $tags : ''));
}

/** Posts one kit on one platform. Returns ['ok' => true, 'id' => post id, 'link' => url] or ['ok' => false, ...]. */
function rocAutoPost(array $a, array $k, string $pl): array {
    $img = ROC_SITE . $k['square'];
    if ($pl === 'facebook') {
        $r = rocGraph('POST', '/' . $a['page_id'] . '/photos', ['url' => $img, 'caption' => rocAutoCaption($k, 'facebook'), 'access_token' => $a['page_token']]);
        if (!$r['ok']) return $r;
        $id = (string)($r['data']['post_id'] ?? $r['data']['id'] ?? '');
        return ['ok' => true, 'id' => $id, 'link' => 'https://www.facebook.com/' . $id];
    }
    $c = rocGraph('POST', '/' . $a['ig_id'] . '/media', ['image_url' => $img, 'caption' => rocAutoCaption($k, 'instagram'), 'access_token' => $a['page_token']]);
    if (!$c['ok']) return $c;
    $cid = (string)$c['data']['id'];
    // Instagram fetches the picture first; publish once the container is ready (a few seconds).
    for ($i = 0; $i < 6; $i++) {
        $s = rocGraph('GET', '/' . $cid, ['fields' => 'status_code', 'access_token' => $a['page_token']]);
        $st = $s['ok'] ? (string)($s['data']['status_code'] ?? '') : '';
        if ($st === 'FINISHED' || $st === '') break;
        if ($st === 'ERROR' || $st === 'EXPIRED') return ['ok' => false, 'error' => 'Instagram could not use the picture (' . $st . ').', 'code' => 0];
        if (!getenv('ROC_GRAPH_TEST')) sleep(5);
    }
    $p = rocGraph('POST', '/' . $a['ig_id'] . '/media_publish', ['creation_id' => $cid, 'access_token' => $a['page_token']]);
    if (!$p['ok']) return $p;
    $id = (string)$p['data']['id'];
    $l = rocGraph('GET', '/' . $id, ['fields' => 'permalink', 'access_token' => $a['page_token']]);
    return ['ok' => true, 'id' => $id, 'link' => $l['ok'] ? (string)($l['data']['permalink'] ?? '') : ''];
}

/** The platforms that are switched on and connected. */
function rocAutoPlatforms(array $a): array {
    $p = [];
    if (!empty($a['fb']) && !empty($a['page_id'])) $p[] = 'facebook';
    if (!empty($a['ig']) && !empty($a['ig_id'])) $p[] = 'instagram';
    return $p;
}

/**
 * Posts one kit on one platform: the oldest kit made since auto-posting was turned on that is not yet on
 * that platform. $force skips the hourly gap and the daily limit (the CMS "Post one now" button).
 * Returns a log line, or null when there was nothing to do.
 */
function rocAutoTick(PDO $pdo, bool $force = false): ?string {
    $a = rocAutoGet($pdo);
    if (empty($a['on']) || empty($a['page_token'])) return null;
    $now = time();
    $day = (new DateTime('now', new DateTimeZone('Asia/Karachi')))->format('Y-m-d');
    $kits = array_values(rocKitAll($pdo));
    usort($kits, function ($x, $y) { return strcmp((string)$x['published'], (string)$y['published']); });
    foreach (rocAutoPlatforms($a) as $pl) {
        if (!$force && ($now - (int)($a['last'][$pl] ?? 0) < ROC_AUTO_GAP || (int)($a['day'][$day][$pl] ?? 0) >= ROC_AUTO_DAY_MAX)) continue;
        foreach ($kits as $k) {
            if (empty($k['square']) || !empty($k['posted'][$pl]) || strtotime((string)($k['made'] ?? '')) < (int)($a['from'] ?? $now)) continue;
            $fk = $k['id'] . ':' . $pl;
            if ((int)($a['fails'][$fk] ?? 0) >= 3) continue;
            $r = rocAutoPost($a, $k, $pl);
            $a = rocAutoGet($pdo);   // fresh copy: the CMS may have saved meanwhile
            $name = $pl === 'facebook' ? 'Facebook' : 'Instagram';
            if (!$r['ok']) {
                $a['fails'][$fk] = (int)($a['fails'][$fk] ?? 0) + 1;
                $a['fails'] = array_slice((array)$a['fails'], -60, null, true);
                $a['error'] = $name . ': ' . $r['error'];
                // 190 = the token expired or was taken back, 10 / 200-299 = a permission is missing: stop until it is fixed.
                if (in_array((int)$r['code'], [190, 10], true) || ((int)$r['code'] >= 200 && (int)$r['code'] < 300)) $a['on'] = false;
                $line = 'Auto-post failed on ' . $name . ' for "' . $k['title'] . '": ' . $r['error'] . (empty($a['on']) ? ' (auto-posting stopped)' : '');
            } else {
                $a['last'][$pl] = $now;
                $a['day'] = [$day => array_merge((array)($a['day'][$day] ?? []), [$pl => (int)($a['day'][$day][$pl] ?? 0) + 1])];
                $a['error'] = '';
                $all = rocKitAll($pdo);
                if (isset($all[$k['id']])) {
                    $all[$k['id']]['posted'][$pl] = gmdate('c');
                    $all[$k['id']]['auto'][$pl] = ['id' => $r['id'], 'link' => $r['link']];
                    rocKitSaveAll($pdo, $all);
                }
                $line = 'Auto-posted on ' . $name . ': ' . $k['title'];
            }
            $a['log'] = array_slice(array_merge([['at' => gmdate('c'), 'text' => $line, 'link' => $r['link'] ?? '']], (array)($a['log'] ?? [])), 0, 20);
            rocAutoSave($pdo, $a);
            return $line;
        }
    }
    return null;
}
