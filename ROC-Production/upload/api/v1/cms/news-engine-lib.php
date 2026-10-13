<?php
/**
 * Run On Console (ROC) — live gaming and hardware news, written on the server.
 *
 * Used by:
 *   api/v1/cron/cli-blog-scheduler.php   every run: rocNewsTick() (reads the feeds, may publish one story)
 *   api/v1/cms/news-admin.php            CMS > Blog writer > News (settings, feeds, "Check now", log)
 *
 * How a story gets on the site:
 *   1. The RSS feeds of trusted sources (official company blogs and established gaming and
 *      hardware sites, editable in the CMS) are read every 20 minutes. Only items of the last
 *      2 days are kept (cms_settings.news_items).
 *   2. Reports about the same event are grouped (their titles share the key words). A story
 *      is used only when at least 2 different sources report it, or 1 official source
 *      (PlayStation, Xbox, NVIDIA, Steam...) announces it.
 *   3. It must connect to this site: a product we list (best: one with an exact Amazon page),
 *      a product category, a gaming platform or the "Can I run it" checker.
 *   4. The writer (Groq or Gemini, the same keys as the blog writer) writes it in its own words
 *      from the reports only. The site adds "Where to buy" (product pages and Amazon) and
 *      "Sources: verified by N trusted sources" with links to the original reports.
 *   5. It is published at once at /news/{slug}/ (blogs table, category "News"), and an email
 *      is sent to the address in CMS > Blog writer > Email alerts.
 *
 * Limits (CMS): news per day (default 5), at least 2 hours between two stories, only between
 * 09:00 and 23:00 Pakistan time. Nothing here needs the PC or Claude.
 *
 * Settings (cms_settings): news_settings {on, per_day, gap_min, start, end, min_sources},
 *   news_feeds [{name, url, official}], news_items, news_done, news_state, notify_email.
 */
if (defined('ROC_NEWS_ENGINE_LIB')) return;
define('ROC_NEWS_ENGINE_LIB', 1);

require_once __DIR__ . '/blog-engine-lib.php';

const ROC_NEWS_CATEGORY = 'News';
const ROC_NEWS_MAX_AGE = 172800;          // 2 days
/** Trusted sources: name, RSS address, official (the company itself). */
const ROC_NEWS_FEEDS = [
    ['PlayStation Blog', 'https://blog.playstation.com/feed/', true],
    ['Xbox Wire', 'https://news.xbox.com/en-us/feed/', true],
    ['NVIDIA Newsroom', 'https://nvidianews.nvidia.com/releases.xml', true],
    ['Steam News', 'https://store.steampowered.com/feeds/news/app/593110/', true],
    ['IGN', 'https://feeds.ign.com/ign/all', false],
    ['GameSpot', 'https://www.gamespot.com/feeds/news/', false],
    ['PC Gamer', 'https://www.pcgamer.com/rss/', false],
    ['Eurogamer', 'https://www.eurogamer.net/feed', false],
    ['The Verge Games', 'https://www.theverge.com/rss/games/index.xml', false],
    ['Polygon', 'https://www.polygon.com/rss/index.xml', false],
    ['Rock Paper Shotgun', 'https://www.rockpapershotgun.com/feed', false],
    ['GamesIndustry.biz', 'https://www.gamesindustry.biz/feed', false],
    ["Tom's Hardware", 'https://www.tomshardware.com/feeds/all', false],
    ['TechPowerUp', 'https://www.techpowerup.com/rss/news', false],
    ['Windows Central', 'https://www.windowscentral.com/feeds/all', false],
    ['Notebookcheck', 'https://www.notebookcheck.net/News.152.100.html', false],
    ['Digital Trends Gaming', 'https://www.digitaltrends.com/gaming/feed/', false],
    ['Push Square', 'https://www.pushsquare.com/feeds/latest', false, 'Hookshot Media'],
    ['Pure Xbox', 'https://www.purexbox.com/feeds/latest', false, 'Hookshot Media'],
    ['Nintendo Life', 'https://www.nintendolife.com/feeds/latest', false, 'Hookshot Media'],
];
/** Not news (guides, opinion, deals, reviews) or not confirmed (leaks, rumours): never used. */
const ROC_NEWS_SKIP = '/^(guide|talking point|poll|soapbox|feature|random|preview|interview|opinion|weekly|round ?up|quiz|podcast|video|best|top \d+)\b|what are you playing|\bdeals?\b|\breviews?\b|\bleak(s|ed|er|ers)?\b|\brumou?rs?\b|\bspeculat|\bhands-on\b|\bsponsored\b/i';
const ROC_NEWS_STOP = ['the', 'and', 'for', 'with', 'from', 'that', 'this', 'are', 'was', 'its', 'it', 'has', 'have', 'will', 'new', 'now', 'out',
    'you', 'your', 'how', 'what', 'why', 'who', 'all', 'can', 'get', 'gets', 'got', 'just', 'more', 'than', 'into', 'about', 'after', 'over', 'off',
    'our', 'not', 'but', 'his', 'her', 'they', 'their', 'one', 'two', 'first', 'best', 'deal', 'deals', 'sale', 'today', 'week', 'here', 'says',
    'said', 'report', 'reportedly', 'review', 'game', 'games', 'gaming', 'gamers', 'could', 'would', 'should', 'may', 'might', 'only', 'still',
    'also', 'back', 'big', 'every', 'year', 'years', 'time', 'next', 'make', 'makes', 'like', 'look', 'looks', 'news', 'update', 'via'];
/** Words that do not name a product (left out when matching product names in a story). */
const ROC_NEWS_GENERIC = ['gaming', 'chair', 'keyboard', 'mouse', 'headset', 'wireless', 'edition', 'laptop', 'case', 'memory', 'ram', 'desktop',
    'processor', 'monitor', 'speakers', 'speaker', 'the', 'and', 'with', 'for', 'handheld', 'black', 'white', 'geforce'];

/* -------------------------------------------------------------- settings */

function rocNewsSettings(PDO $pdo): array {
    $s = rocBlogGet($pdo, 'news_settings', []);
    $s = is_array($s) ? $s : [];
    $s += ['on' => false, 'per_day' => 5, 'gap_min' => 120, 'start' => '09:00', 'end' => '23:00', 'min_sources' => 2];
    $s['per_day'] = max(1, min(12, (int)$s['per_day']));
    $s['gap_min'] = max(30, min(720, (int)$s['gap_min']));
    $s['min_sources'] = max(1, min(4, (int)$s['min_sources']));
    foreach (['start', 'end'] as $k) if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string)$s[$k])) $s[$k] = $k === 'start' ? '09:00' : '23:00';
    $s['on'] = (bool)$s['on'];
    return $s;
}

/** The trusted feeds (CMS list, else the built-in list). */
function rocNewsFeeds(PDO $pdo): array {
    $f = rocBlogGet($pdo, 'news_feeds', null);
    $out = [];
    foreach (is_array($f) && $f ? $f : array_map(function ($x) { return ['name' => $x[0], 'url' => $x[1], 'official' => $x[2], 'group' => $x[3] ?? '']; }, ROC_NEWS_FEEDS) as $x) {
        $u = trim((string)($x['url'] ?? ''));
        if (!preg_match('#^https://[^\s"<>]+$#i', $u)) continue;
        $name = trim((string)($x['name'] ?? '')) ?: (string)parse_url($u, PHP_URL_HOST);
        // Sister sites of one publisher count as one source.
        $group = trim((string)($x['group'] ?? ''));
        if ($group === '') foreach (ROC_NEWS_FEEDS as $d) if (strcasecmp($d[1], $u) === 0 && !empty($d[3])) $group = $d[3];
        $out[] = ['name' => $name, 'url' => $u, 'official' => !empty($x['official']), 'group' => $group ?: $name];
    }
    return $out;
}

function rocNewsState(PDO $pdo): array { $s = rocBlogGet($pdo, 'news_state', []); return is_array($s) ? $s : []; }
function rocNewsSaveState(PDO $pdo, array $s): void { rocBlogSet($pdo, 'news_state', $s); }

/** The address of a post: news at /news/, everything else at /blogs/. */
function rocNewsIsNews(?string $category): bool { return strcasecmp(trim((string)$category), ROC_NEWS_CATEGORY) === 0; }
function rocPostPath(string $slug, ?string $category): string { return (rocNewsIsNews($category) ? '/news/' : '/blogs/') . $slug . '/'; }

/* ----------------------------------------------------------- reading feeds */

function rocNewsHttp(string $url, int $timeout = 12): array {
    if (!function_exists('curl_init')) return ['ok' => false, 'error' => 'curl is not available'];
    $c = curl_init($url);
    curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3, CURLOPT_TIMEOUT => $timeout,
                           CURLOPT_CONNECTTIMEOUT => 6, CURLOPT_ENCODING => '',
                           CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; RunOnConsole/1.0; +https://runonconsole.com)']);
    $raw = (string)curl_exec($c);
    $code = (int)curl_getinfo($c, CURLINFO_HTTP_CODE);
    $err = curl_error($c);
    curl_close($c);
    return $code === 200 && $raw !== '' ? ['ok' => true, 'body' => $raw] : ['ok' => false, 'error' => $err ?: "HTTP {$code}"];
}

function rocNewsText(string $s, int $max): string {
    $s = html_entity_decode(strip_tags(html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $s = trim((string)preg_replace('/\s+/u', ' ', $s));
    return mb_strlen($s) > $max ? rtrim(mb_substr($s, 0, $max - 1)) . '…' : $s;
}

/** Items of one RSS 2.0, RSS 1.0 or Atom feed. */
function rocNewsParse(string $xml, array $feed): array {
    $prev = libxml_use_internal_errors(true);
    $x = @simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NONET);
    libxml_clear_errors(); libxml_use_internal_errors($prev);
    if (!$x) return [];
    $raw = [];
    $rssItems = isset($x->channel->item) ? $x->channel->item : (isset($x->item) ? $x->item : null);
    if ($rssItems !== null) {
        foreach ($rssItems as $it) {
            $dc = $it->children('http://purl.org/dc/elements/1.1/');
            $raw[] = [(string)$it->title, trim((string)$it->link), (string)$it->description, (string)($it->pubDate ?: $dc->date)];
        }
    } elseif (isset($x->entry)) {
        foreach ($x->entry as $e) {
            $link = '';
            foreach ($e->link as $l) { $a = $l->attributes(); if (!isset($a['rel']) || (string)$a['rel'] === 'alternate') { $link = (string)$a['href']; break; } }
            $raw[] = [(string)$e->title, trim($link), (string)($e->summary ?: $e->content), (string)($e->published ?: $e->updated)];
        }
    }
    $out = [];
    foreach ($raw as [$title, $link, $desc, $date]) {
        $ts = strtotime($date) ?: 0;
        $title = rocNewsText($title, 220);
        if ($title === '' || !preg_match('#^https?://#i', $link) || $ts < time() - ROC_NEWS_MAX_AGE || $ts > time() + 3600) continue;
        if (preg_match(ROC_NEWS_SKIP, $title)) continue;
        $out[] = ['id' => substr(sha1($link), 0, 16), 'src' => $feed['name'], 'grp' => (string)($feed['group'] ?? $feed['name']), 'host' => strtolower((string)parse_url($feed['url'], PHP_URL_HOST)),
                  'official' => !empty($feed['official']), 'title' => $title, 'link' => $link, 'desc' => rocNewsText($desc, 600), 'ts' => $ts];
    }
    return $out;
}

/** Reads every feed and keeps the reports of the last 2 days. Returns [items, errors]. */
function rocNewsFetch(PDO $pdo): array {
    $keep = [];
    foreach ((array)rocBlogGet($pdo, 'news_items', []) as $it) if (is_array($it) && ($it['ts'] ?? 0) >= time() - ROC_NEWS_MAX_AGE) $keep[$it['id']] = $it;
    $errors = [];
    foreach (rocNewsFeeds($pdo) as $f) {
        $r = rocNewsHttp($f['url']);
        if (!$r['ok']) { $errors[$f['name']] = $r['error']; continue; }
        $items = rocNewsParse($r['body'], $f);
        if (!$items) { $errors[$f['name']] = 'no recent items'; }
        foreach ($items as $it) $keep[$it['id']] = $it;
    }
    uasort($keep, function ($a, $b) { return $b['ts'] <=> $a['ts']; });
    $keep = array_slice($keep, 0, 600, true);
    rocBlogSet($pdo, 'news_items', array_values($keep));
    return [array_values($keep), $errors];
}

/* ------------------------------------------------- same story, other sources */

function rocNewsTokens(string $t): array {
    $t = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $t) ?: $t);
    $out = [];
    foreach (preg_split('/[^a-z0-9]+/', $t) ?: [] as $w) {
        if ($w === '' || in_array($w, ROC_NEWS_STOP, true)) continue;
        if (strlen($w) < 3 && !preg_match('/\d/', $w)) continue;
        if (strlen($w) > 4 && substr($w, -1) === 's' && !preg_match('/\d|ss$/', $w)) $w = substr($w, 0, -1);   // plurals
        $out[$w] = 1;
    }
    return array_keys($out);
}

/** True when two titles are about the same event. */
function rocNewsSame(array $a, array $b): bool {
    if (!$a || !$b) return false;
    $shared = array_values(array_intersect($a, $b));
    $n = count($shared);
    $ratio = $n / min(count($a), count($b));
    $model = false;
    foreach ($shared as $w) if (preg_match('/\d/', $w) && strlen($w) >= 3) $model = true;
    return ($n >= 3 && $ratio >= 0.5) || ($n >= 4 && $ratio >= 0.3) || ($n >= 2 && $model && $ratio >= 0.4);
}

/** Groups the reports into stories: [{items, tokens, sources, official, ts}]. */
function rocNewsCluster(array $items): array {
    $clusters = [];
    foreach ($items as $it) {
        if (preg_match(ROC_NEWS_SKIP, (string)$it['title'])) continue;
        $tok = rocNewsTokens($it['title']);
        $grp = (string)($it['grp'] ?? $it['src']);
        $placed = false;
        foreach ($clusters as &$c) {
            if (rocNewsSame($tok, $c['tokens'])) {
                $c['items'][] = $it;
                $c['srcs'][$grp] = 1;
                if ($it['official']) $c['official'] = true;
                $c['ts'] = max($c['ts'], $it['ts']);
                $placed = true; break;
            }
        }
        unset($c);
        if (!$placed) $clusters[] = ['items' => [$it], 'tokens' => $tok, 'srcs' => [$grp => 1], 'official' => $it['official'], 'ts' => $it['ts']];
    }
    foreach ($clusters as &$c) $c['sources'] = count($c['srcs']);
    unset($c);
    return $clusters;
}

/* ----------------------------------------------------- links to this site */

/** Product name words that must be in a story for it to be about that product. */
function rocNewsProductKeys(string $title): array {
    $tok = [];
    foreach (preg_split('/[^a-z0-9+]+/', strtolower($title)) ?: [] as $w) {
        if ($w === '' || in_array($w, ROC_NEWS_GENERIC, true)) continue;
        if (strlen($w) < 2 || (strlen($w) < 3 && !preg_match('/\d/', $w))) continue;
        if (preg_match('/^\d+(gb|tb)$/', $w)) continue;             // capacity: any version matches
        $tok[] = $w;
    }
    if (count($tok) >= 3) array_shift($tok);                      // the brand is optional when the model is clear
    return count($tok) >= 2 || (count($tok) === 1 && preg_match('/\d/', $tok[0]) && strlen($tok[0]) >= 4) ? $tok : [];
}

/** What a story connects to on this site: products, categories, platforms, checker, posts. */
function rocNewsMatch(PDO $pdo, string $text): array {
    $S = rocBlogSite($pdo);
    $words = array_flip(preg_split('/[^a-z0-9+]+/', strtolower($text)) ?: []);
    $low = ' ' . strtolower((string)preg_replace('/[^a-z0-9+]+/i', ' ', $text)) . ' ';
    $products = [];
    foreach ($S['products'] as $slug => $p) {
        $keys = rocNewsProductKeys((string)$p['title']);
        if (!$keys) continue;
        $all = true;
        foreach ($keys as $k) if (!isset($words[$k])) { $all = false; break; }
        if ($all) $products[$slug] = $p;
    }
    // A product version (8GB / 16GB...) counts once.
    $seen = [];
    foreach ($products as $slug => $p) {
        $base = implode(' ', rocNewsProductKeys((string)$p['title']));
        if (isset($seen[$base])) { unset($products[$slug]); continue; }
        $seen[$base] = 1;
    }
    uasort($products, function ($a, $b) { return (int)!empty($b['dp']) <=> (int)!empty($a['dp']); });
    $catWords = ['gpu' => 'graphics card|gpu|geforce|radeon|rtx [0-9]|rx [0-9]|arc b[0-9]', 'cpu' => 'cpu|processor|ryzen|core ultra|threadripper',
        'ram' => 'ddr5|ddr4|memory kit|ram kit', 'cases' => 'pc case|computer case|mid tower', 'monitors' => 'monitor|oled display|gaming display',
        'keyboards' => 'keyboard', 'mice' => 'gaming mouse|mice', 'headsets' => 'headset|gaming headphones', 'chairs' => 'gaming chair|office chair',
        'handhelds' => 'handheld|steam deck|rog ally|legion go|msi claw', 'laptops' => 'gaming laptop|laptop', 'speakers' => 'speaker|soundbar',
        'audio' => 'microphone|headphones|earbuds'];
    $cats = [];
    foreach ($products as $p) if ($p['cat'] !== '' && isset($S['cats'][$p['cat']])) $cats[$p['cat']] = $S['cats'][$p['cat']];
    foreach ($catWords as $slug => $re) if (isset($S['cats'][$slug]) && preg_match('/ (' . $re . ')/', $low)) $cats[$slug] = $S['cats'][$slug];
    $platWords = ['sony-playstation' => 'playstation|ps5|ps4|ps plus', 'microsoft-xbox' => 'xbox|game pass', 'nintendo-home' => 'nintendo switch|switch 2',
        'nintendo-handhelds' => 'nintendo switch lite', 'pc-handheld-pc' => 'steam deck|rog ally|legion go|handheld pc', 'vr-ar' => 'meta quest|vr headset|psvr|virtual reality',
        'cloud-gaming' => 'cloud gaming|geforce now|xcloud', 'mobile' => 'iphone|android|mobile game'];
    $plats = [];
    foreach ($platWords as $slug => $re) if (isset($S['platforms'][$slug]) && preg_match('/ (' . $re . ')/', $low)) $plats[$slug] = $S['platforms'][$slug];
    $checker = (bool)preg_match('/ (system requirements|pc requirements|pc specs|minimum specs|recommended specs|steam|pc port|on pc) /', $low);
    // Published posts that share words with the story.
    $tok = rocNewsTokens($text);
    $posts = [];
    foreach ($S['posts'] as $b) {
        if (($b['status'] ?? '') !== 'published') continue;
        $n = count(array_intersect($tok, rocNewsTokens((string)$b['title'])));
        if ($n >= 2) $posts[] = $b + ['score' => $n];
    }
    usort($posts, function ($a, $b) { return $b['score'] <=> $a['score']; });
    return ['products' => array_slice($products, 0, 4, true), 'cats' => array_slice($cats, 0, 2, true), 'platforms' => array_slice($plats, 0, 2, true),
            'checker' => $checker, 'posts' => array_slice($posts, 0, 2)];
}

/* ----------------------------------------------------------- choose a story */

function rocNewsDoneList(PDO $pdo): array {
    $d = rocBlogGet($pdo, 'news_done', []);
    $d = is_array($d) ? $d : [];
    return array_values(array_filter($d, function ($x) { return is_array($x) && ($x['ts'] ?? 0) > time() - 14 * 86400; }));
}
function rocNewsMarkDone(PDO $pdo, array $cluster, string $result, string $postId = ''): void {
    $d = rocNewsDoneList($pdo);
    array_unshift($d, ['ts' => time(), 'title' => $cluster['items'][0]['title'], 'tokens' => $cluster['tokens'], 'links' => array_column($cluster['items'], 'link'),
                       'result' => $result, 'post_id' => $postId]);
    rocBlogSet($pdo, 'news_done', array_slice($d, 0, 300));
}

/** Stories that may be published, best first, each with its links to this site and a score. */
function rocNewsCandidates(PDO $pdo, array $items, ?array $settings = null): array {
    $set = $settings ?? rocNewsSettings($pdo);
    $done = rocNewsDoneList($pdo);
    $doneLinks = [];
    foreach ($done as $d) foreach ((array)($d['links'] ?? []) as $l) $doneLinks[$l] = 1;
    $out = [];
    foreach (rocNewsCluster($items) as $c) {
        if ($c['sources'] < $set['min_sources'] && !$c['official']) continue;
        foreach ($c['items'] as $it) if (isset($doneLinks[$it['link']])) continue 2;
        foreach ($done as $d) if (rocNewsSame($c['tokens'], (array)($d['tokens'] ?? []))) continue 2;
        $text = implode(' ', array_map(function ($it) { return $it['title'] . ' ' . $it['desc']; }, $c['items']));
        $m = rocNewsMatch($pdo, $text);
        if (!$m['products'] && !$m['cats'] && !$m['platforms']) continue;     // must lead readers somewhere on this site
        $dp = 0; foreach ($m['products'] as $p) if (!empty($p['dp'])) $dp++;
        $titles = implode(' ', array_column($c['items'], 'title'));
        $score = $dp * 6 + count($m['products']) * 3 + count($m['cats']) * 2 + count($m['platforms']) + ($m['checker'] ? 1 : 0)
               + min(4, $c['sources']) * 2 + ($c['official'] ? 3 : 0) - (int)((time() - $c['ts']) / 21600)
               - (preg_match('/\b(reportedly|allegedly|could|might|may)\b/i', $titles) ? 3 : 0);
        if ($score < 3) continue;                                            // too weak or too old
        $out[] = $c + ['match' => $m, 'score' => $score];
    }
    usort($out, function ($a, $b) { return $b['score'] <=> $a['score']; });
    return $out;
}

/* --------------------------------------------------------------- the writer */

function rocNewsPrompt(array $c): string {
    $m = $c['match'];
    $reports = '';
    foreach (array_slice($c['items'], 0, 6) as $i => $it) {
        $reports .= ($i + 1) . '. ' . $it['src'] . ($it['official'] ? ' (official)' : '') . ', ' . gmdate('Y-m-d H:i', $it['ts']) . " UTC\n   Title: {$it['title']}\n"
                  . ($it['desc'] !== '' ? "   Summary: {$it['desc']}\n" : '');
    }
    $links = [];
    foreach ($m['products'] as $p) $links[] = "- {$p['url']}  ({$p['title']}; our product page with specs and the Amazon link)";
    foreach ($m['cats'] as $cat) $links[] = "- {$cat['url']}  (best {$cat['name']}; our picks)";
    foreach ($m['platforms'] as $pl) $links[] = "- {$pl['url']}  ({$pl['name']}; our platform guide)";
    if ($m['checker']) $links[] = '- /compatibility/  (Can I run it: free PC game requirements checker)';
    foreach ($m['posts'] as $b) $links[] = "- {$b['url']}  ({$b['title']}; our article)";
    return "You write short, accurate news for runonconsole.com (Run On Console): an independent gaming and PC hardware site. Write ONE news article in English.\n\n"
        . "REPORTS FROM TRUSTED SOURCES (the ONLY facts you may use):\n{$reports}\n"
        . "INTERNAL LINKS (use 2-4 of them, each once, where they help the reader; anchor text = the searched name, e.g. \"AMD Ryzen 7 9800X3D\" or \"best graphics cards\", never \"click here\"):\n" . implode("\n", $links) . "\n\n"
        . "RULES\n"
        . "- 350-550 words, in your own words. Never copy sentences from the reports; quote at most 8 words and say who said it.\n"
        . "- Only facts found in the reports. Attribute them (\"according to the PlayStation Blog\"). No guesses, no invented specs, prices, dates or benchmarks. If the reports disagree, say so.\n"
        . "- Start with 1-2 sentences that say what happened (who, what, when). Then <h2>What we know</h2>, <h2>Why it matters for gamers</h2> and <h2>What to do now</h2> (where readers can see the products or guides on our site).\n"
        . "- Allowed HTML only: p, h2, h3, ul, li, strong, em, a. Never <h1>. No source list (the site adds it). No prices.\n"
        . "- The title is newsy and specific (40-70 characters) and names the product, game or company. The focus keyword is what people will search for this news (e.g. \"RTX 5080 Super release date\").\n\n"
        . "Answer with ONLY a JSON object with exactly these keys:\n"
        . "{\"title\": \"...\", \"focus_keyword\": \"...\", \"meta_title\": \"max 60 characters\", \"meta_description\": \"110-155 characters\", "
        . "\"excerpt\": \"1-2 sentences\", \"content_html\": \"the article HTML\", \"image_alt\": \"short description of the cover\", "
        . "\"image_prompt\": \"one sentence describing a realistic cover photo for this news: the hardware or scene; no text, no logos, no faces\"}\n";
}

function rocNewsAmazonTag(PDO $pdo): string {
    try { $t = trim((string)rocLayerSettingGet($pdo, 'amazon_tag')); } catch (\Throwable $e) { $t = ''; }
    return preg_match('/^[A-Za-z0-9_-]{2,40}$/', $t) ? $t : '';
}

/** "Where to buy" and "Sources" boxes added under the article. */
function rocNewsBoxes(PDO $pdo, array $c): string {
    $m = $c['match'];
    $h = '';
    if ($m['products'] || $m['cats'] || $m['platforms']) {
        $tag = rocNewsAmazonTag($pdo);
        $st = $pdo->prepare('SELECT affiliate_amazon FROM products WHERE slug = ?');
        $items = []; $amazon = false;
        foreach ($m['products'] as $slug => $p) {
            $st->execute([$slug]);
            $link = (string)$st->fetchColumn();
            $li = '<li><a href="' . rocLayerH($p['url']) . '">' . rocLayerH($p['title']) . '</a>: specs and our verdict';
            if (preg_match('#^https://www\.amazon\.com/dp/([A-Z0-9]{10})#', $link, $am)) {
                $li .= ' · <a href="https://www.amazon.com/dp/' . $am[1] . ($tag !== '' ? '?tag=' . rawurlencode($tag) : '') . '" target="_blank" rel="nofollow sponsored noopener">check the price on Amazon</a>';
                $amazon = true;
            }
            $items[] = $li . '</li>';
        }
        foreach ($m['cats'] as $cat) $items[] = '<li><a href="' . rocLayerH($cat['url']) . '">Best ' . rocLayerH(strtolower($cat['name'])) . '</a>: our picks</li>';
        foreach ($m['platforms'] as $pl) $items[] = '<li><a href="' . rocLayerH($pl['url']) . '">' . rocLayerH($pl['name']) . ' guide</a>: the consoles, games and gear</li>';
        if ($m['checker']) $items[] = '<li><a href="/compatibility/">Can I run it?</a>: check a PC game against your hardware</li>';
        $h .= "\n<h2>See it on Run On Console</h2>\n<ul>" . implode('', $items) . '</ul>'
            . ($amazon ? '<p><em>As an Amazon Associate, Run On Console earns from qualifying purchases. Prices and stock are on Amazon.</em></p>' : '');
    }
    $n = $c['sources'];
    $srcs = [];
    foreach ($c['items'] as $it) {
        if (isset($srcs[$it['src']])) continue;
        $srcs[$it['src']] = '<li><a href="' . rocLayerH($it['link']) . '" target="_blank" rel="noopener">' . rocLayerH($it['title']) . '</a> (' . rocLayerH($it['src']) . ($it['official'] ? ', official' : '') . ')</li>';
    }
    $h .= "\n<h2>Sources</h2>\n<p><strong>Verified by " . $n . ' trusted source' . ($n === 1 ? '' : 's') . '</strong>'
        . ($c['official'] ? ', including the official announcement' : '') . '. This story was written from the reports below and checked against them before it was published.</p>'
        . '<ul>' . implode('', array_slice($srcs, 0, 6)) . '</ul>';
    return $h;
}

/** Saves and publishes one news article. */
function rocNewsCreate(PDO $pdo, string $root, array $a, array $c, string $by): array {
    rocBlogSite($pdo, true);
    $t = function ($k, $max) use ($a) { return trim(mb_substr((string)preg_replace('/\s+/u', ' ', strip_tags((string)($a[$k] ?? ''))), 0, $max)); };
    $title = $t('title', 160);
    $metaT = $t('meta_title', 120); if (mb_strlen($metaT) > 60) $metaT = rtrim(mb_substr($metaT, 0, 57)) . '…'; if ($metaT === '') $metaT = mb_substr($title, 0, 60);
    $metaD = $t('meta_description', 300); if (mb_strlen($metaD) > 160) $metaD = rtrim(mb_substr($metaD, 0, 157)) . '…';
    $excerpt = $t('excerpt', 400); $focus = $t('focus_keyword', 120); $alt = $t('image_alt', 200) ?: $title;
    $content = rocBlogCleanLinks(rocCmsSanitizeHtml((string)($a['content_html'] ?? '')), $pdo);
    $content = (string)preg_replace('#<h1\b[^>]*>(.*?)</h1>#is', '<h2>$1</h2>', $content);
    $words = count(preg_split('/\s+/u', rocCmsTextFromHtml($content)) ?: []);
    $errors = [];
    if (mb_strlen($title) < 20) $errors[] = 'The title is too short.';
    if ($words < 250) $errors[] = "The article has {$words} words; at least 300 are needed.";
    if ($excerpt === '') $excerpt = rocCmsTruncate(rocCmsTextFromHtml($content), 200);
    if (mb_strlen($metaD) < 70) $metaD = rocCmsTruncate($excerpt, 155);
    if ($errors) return ['ok' => false, 'errors' => $errors];

    $base = rocBlogSlug($title) ?: 'news-' . gmdate('Ymd-Hi');
    $slug = $base;
    $st = $pdo->prepare('SELECT COUNT(*) FROM blogs WHERE slug = ?');
    for ($i = 2; $i < 20; $i++) { $st->execute([$slug]); if ((int)$st->fetchColumn() === 0) break; $slug = $base . '-' . $i; }
    $content = rtrim($content) . rocNewsBoxes($pdo, $c);

    $image = rocBlogMakeCover($pdo, $root, $slug, $title, 'News', ['id' => $slug, 'pillar' => '', 'products' => array_keys($c['match']['products'])],
                              (string)($a['image_prompt'] ?? ''), $alt);
    $cols = rocBlogCols($pdo, 'blogs');
    $id = 'news-' . bin2hex(random_bytes(4));
    $now = gmdate('Y-m-d H:i:s');
    $row = ['id' => $id, 'title' => $title, 'slug' => $slug, 'excerpt' => $excerpt, 'content' => $content, 'category' => ROC_NEWS_CATEGORY,
            'author_name' => ROC_BLOG_AUTHOR, 'image' => $image, 'image_alt' => $alt, 'meta_title' => $metaT, 'meta_description' => $metaD,
            'focus_keyword' => $focus, 'read_time' => max(1, (int)ceil($words / 200)) . ' min read', 'status' => 'published', 'version' => 1,
            'published_at' => $now, 'content_modified_at' => $now, 'created_at' => $now, 'updated_at' => $now,
            'og_title' => $metaT, 'og_description' => $metaD, 'og_image' => $image, 'og_type' => 'article', 'twitter_card' => 'summary_large_image',
            'twitter_title' => $metaT, 'twitter_description' => $metaD, 'twitter_image' => $image, 'is_noindex' => 0, 'is_nofollow' => 0];
    $row = array_filter($row, function ($k) use ($cols) { return in_array($k, $cols, true); }, ARRAY_FILTER_USE_KEY);
    $pdo->prepare('INSERT INTO blogs (' . implode(', ', array_keys($row)) . ') VALUES (' . implode(', ', array_fill(0, count($row), '?')) . ')')
        ->execute(array_values($row));
    try {
        $pdo->prepare("INSERT INTO cms_cache_queue (content_type, content_id, target_version_id, action, status, created_at) VALUES ('blog', ?, 1, 'purge_and_prerender', 'pending', ?)")
            ->execute([$id, $now]);
    } catch (\Throwable $e) { /* optional table */ }
    $url = 'https://runonconsole.com/news/' . $slug . '/';
    $log = rocBlogGet($pdo, 'news_log', []); $log = is_array($log) ? $log : [];
    array_unshift($log, ['id' => $id, 'title' => $title, 'slug' => $slug, 'url' => $url, 'words' => $words, 'sources' => $c['sources'], 'official' => $c['official'],
                         'products' => array_keys($c['match']['products']), 'by' => $by, 'created' => $now,
                         'links' => array_map(function ($it) { return ['src' => $it['src'], 'title' => $it['title'], 'link' => $it['link']]; }, array_slice($c['items'], 0, 6))]);
    rocBlogSet($pdo, 'news_log', array_slice($log, 0, 100));
    return ['ok' => true, 'id' => $id, 'slug' => $slug, 'url' => $url, 'title' => $title, 'words' => $words];
}

/* ------------------------------------------------------------------ email */

/** Sends an alert to the address in CMS > Blog writer > Email alerts (nothing when none is set). */
function rocNotify(PDO $pdo, string $subject, string $html): bool {
    $to = trim((string)rocBlogGet($pdo, 'notify_email', ''));
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
    $smtp = rocBlogGet($pdo, 'notify_smtp', []);
    $smtp = is_array($smtp) ? $smtp : [];
    $fromAddr = filter_var((string)($smtp['user'] ?? ''), FILTER_VALIDATE_EMAIL) ? (string)$smtp['user'] : 'alerts@runonconsole.com';
    $body = '<!doctype html><html><body style="font-family:Arial,sans-serif;font-size:15px;color:#0f172a;line-height:1.6">' . $html
          . '<p style="color:#64748b;font-size:12px;margin-top:24px">Sent by runonconsole.com. Change or remove this address in CMS &gt; Blog &amp; news writer &gt; News &gt; Email alerts.</p></body></html>';
    $subj = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    // Base64 body: no line is longer than 76 characters (mail servers may refuse or break longer lines).
    $body = rtrim(chunk_split(base64_encode($body), 76, "\r\n"));
    $headers = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\nFrom: Run On Console <{$fromAddr}>\r\nReply-To: {$to}\r\n"
             . 'Date: ' . gmdate('D, d M Y H:i:s') . " +0000\r\nMessage-ID: <" . bin2hex(random_bytes(8)) . "@runonconsole.com>\r\nX-Mailer: RunOnConsole";
    $errors = [];
    $ok = false; $via = '';
    // 1. The mailbox from the CMS (most reliable: signed in, so Gmail trusts it).
    if (!empty($smtp['host']) && !empty($smtp['user']) && !empty($smtp['pass'])) {
        $r = rocSmtpSend($smtp, $fromAddr, $to, $subj, $headers, $body);
        $ok = $r === true; if (!$ok) $errors[] = 'SMTP: ' . $r; else $via = 'mailbox ' . $fromAddr;
    }
    // 2. The server's own mail program.
    if (!$ok && function_exists('mail')) {
        foreach (['-f' . $fromAddr, ''] as $extra) {
            error_clear_last();
            $ok = $extra !== '' ? @mail($to, $subj, $body, $headers, $extra) : @mail($to, $subj, $body, $headers);
            if ($ok) { $via = 'server mail program'; break; }
            $e = error_get_last(); $errors[] = 'mail(): ' . ($e['message'] ?? 'refused');
        }
    } elseif (!$ok) $errors[] = 'mail() is switched off on this server';
    // 3. sendmail directly.
    if (!$ok && function_exists('popen')) {
        foreach (['/usr/sbin/sendmail', '/usr/lib/sendmail'] as $bin) {
            if (!is_executable($bin)) continue;
            $h = @popen($bin . ' -t -i -f' . escapeshellarg($fromAddr), 'w');
            if (!$h) continue;
            fwrite($h, "To: {$to}\r\nSubject: {$subj}\r\n{$headers}\r\n\r\n{$body}\r\n");
            $code = pclose($h);
            if ($code === 0) { $ok = true; $via = 'sendmail'; break; }
            $errors[] = "sendmail exit {$code}";
        }
    }
    $s = rocNewsState($pdo);
    $s['last_email'] = gmdate('Y-m-d H:i:s') . ($ok ? '' : ' (failed)');
    $s['last_email_via'] = $via;
    // When the mailbox failed but another way worked, keep the mailbox error to show it.
    $s['last_email_error'] = $ok ? ($errors ? 'Sent by the ' . $via . ' because ' . mb_substr(implode(' | ', $errors), 0, 300) : '') : mb_substr(implode(' | ', $errors) ?: 'no way to send mail on this server', 0, 400);
    rocNewsSaveState($pdo, $s);
    return $ok;
}

/** Minimal SMTP client (SSL on 465, STARTTLS on 587). Returns true or an error text. */
function rocSmtpSend(array $c, string $from, string $to, string $subj, string $headers, string $body) {
    $host = (string)$c['host']; $port = (int)($c['port'] ?? 465) ?: 465;
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true, 'peer_name' => $host]]);
    $fp = @stream_socket_client(($port === 465 ? 'ssl://' : 'tcp://') . $host . ':' . $port, $en, $es, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) return "cannot connect to {$host}:{$port} ({$es})";
    stream_set_timeout($fp, 20);
    $read = function () use ($fp) { $d = ''; while (($l = fgets($fp, 1024)) !== false) { $d .= $l; if (strlen($l) < 4 || $l[3] === ' ') break; } return $d; };
    $cmd = function (string $line, array $okCodes) use ($fp, $read) {
        if ($line !== '') fwrite($fp, $line . "\r\n");
        $r = $read();
        return in_array((int)substr($r, 0, 3), $okCodes, true) ? true : trim($r);
    };
    $steps = [['', [220]], ['EHLO runonconsole.com', [250]]];
    foreach ($steps as [$l, $codes]) { $r = $cmd($l, $codes); if ($r !== true) { fclose($fp); return $r ?: 'no answer'; } }
    if ($port !== 465) {
        $r = $cmd('STARTTLS', [220]);
        if ($r !== true || !stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) { fclose($fp); return 'STARTTLS failed'; }
        $r = $cmd('EHLO runonconsole.com', [250]); if ($r !== true) { fclose($fp); return $r; }
    }
    foreach ([['AUTH LOGIN', [334]], [base64_encode((string)$c['user']), [334]], [base64_encode((string)$c['pass']), [235]],
              ['MAIL FROM:<' . $from . '>', [250]], ['RCPT TO:<' . $to . '>', [250, 251]], ['DATA', [354]]] as [$l, $codes]) {
        $r = $cmd($l, $codes);
        if ($r !== true) { fclose($fp); return stripos($l, 'AUTH') === 0 || $codes === [235] ? 'sign-in refused: ' . $r : $r; }
    }
    $msg = "To: {$to}\r\nSubject: {$subj}\r\n{$headers}\r\n\r\n" . preg_replace('/^\./m', '..', $body);
    $r = $cmd($msg . "\r\n.", [250]);
    $cmd('QUIT', [221]);
    fclose($fp);
    return $r;
}

function rocNotifyNews(PDO $pdo, array $r, array $c): void {
    $src = '';
    foreach ($c['items'] as $it) $src .= '<li><a href="' . rocLayerH($it['link']) . '">' . rocLayerH($it['title']) . '</a> (' . rocLayerH($it['src']) . ')</li>';
    rocNotify($pdo, 'News published: ' . $r['title'],
        '<p><b>A news story was published on Run On Console.</b></p><p style="font-size:18px"><a href="' . rocLayerH($r['url']) . '">' . rocLayerH($r['title']) . '</a></p>'
        . '<p>Verified by <b>' . $c['sources'] . '</b> trusted source' . ($c['sources'] === 1 ? '' : 's') . ($c['official'] ? ' (incl. the official announcement)' : '') . ':</p><ul>' . $src . '</ul>'
        . ($c['match']['products'] ? '<p>Links to: ' . rocLayerH(implode(', ', array_column($c['match']['products'], 'title'))) . '</p>' : ''));
}

function rocNotifyBlog(PDO $pdo, string $postId): void {
    $st = $pdo->prepare('SELECT title, slug, category, excerpt FROM blogs WHERE id = ?');
    $st->execute([$postId]);
    $b = $st->fetch(PDO::FETCH_ASSOC);
    if (!$b) return;
    $url = 'https://runonconsole.com' . rocPostPath((string)$b['slug'], (string)$b['category']);
    rocNotify($pdo, 'Blog published: ' . $b['title'],
        '<p><b>A blog post went live on Run On Console.</b></p><p style="font-size:18px"><a href="' . rocLayerH($url) . '">' . rocLayerH((string)$b['title']) . '</a></p>'
        . '<p>' . rocLayerH((string)$b['excerpt']) . '</p>');
}

/** Shows the newest story in the announcement bar of every page ("LIVE" + headline, linked to it) for 24 hours. */
function rocNewsToBar(PDO $pdo, string $root, string $title, string $url): void {
    $raw = rocLayerSettingGet($pdo, ROC_LAYER_KEYS['announcement']);
    $a = $raw ? (json_decode($raw, true) ?: []) : [];
    $a = array_merge(rocLayerDefaults()['announcement'], $a);
    $a['live_title'] = mb_substr($title, 0, 140);
    $a['live_url'] = $url;
    $a['live_at'] = gmdate('c');
    rocLayerSave($pdo, $root, 'announcement', $a, null);
}

/* -------------------------------------------------------------------- tick */

/** News published today (Pakistan time) and the time of the last one. */
function rocNewsToday(PDO $pdo): array {
    $tz = new DateTimeZone(ROC_BLOG_TZ);
    $start = (new DateTime('today', $tz))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    $st = $pdo->prepare("SELECT COUNT(*) FROM blogs WHERE category = ? AND published_at >= ?");
    $st->execute([ROC_NEWS_CATEGORY, $start]);
    $n = (int)$st->fetchColumn();
    $st = $pdo->prepare('SELECT MAX(published_at) FROM blogs WHERE category = ?');
    $st->execute([ROC_NEWS_CATEGORY]);
    $last = (string)$st->fetchColumn();
    return [$n, $last !== '' ? strtotime($last . ' UTC') : 0];
}

/**
 * Run by the scheduler. Reads the feeds every 20 minutes and, inside the publishing hours and
 * limits, writes and publishes the best story. $force: now, ignoring hours, gap and "on".
 */
function rocNewsTick(PDO $pdo, string $root, bool $force = false): ?string {
    $set = rocNewsSettings($pdo);
    if (!$set['on'] && !$force) return null;
    $state = rocNewsState($pdo);
    $items = (array)rocBlogGet($pdo, 'news_items', []);
    if ($force || empty($state['last_fetch']) || time() - strtotime($state['last_fetch'] . ' UTC') >= 1200) {
        [$items, $errors] = rocNewsFetch($pdo);
        $state['last_fetch'] = gmdate('Y-m-d H:i:s');
        $state['feed_errors'] = $errors;
        $state['items'] = count($items);
        rocNewsSaveState($pdo, $state);
    }
    if (!$force) {
        $local = new DateTime('now', new DateTimeZone(ROC_BLOG_TZ));
        $hm = $local->format('H:i');
        if ($hm < $set['start'] || $hm > $set['end']) return null;
        [$today, $last] = rocNewsToday($pdo);
        if ($today >= $set['per_day']) return null;
        if ($last && time() - $last < $set['gap_min'] * 60) return null;
        if (!empty($state['last_try']) && time() - strtotime($state['last_try'] . ' UTC') < 1200) return null;
    }
    if (!rocBlogWriters($pdo)) return 'No Groq or Gemini key saved (CMS > Blog writer).';
    $cands = rocNewsCandidates($pdo, $items, $set);
    $state['candidates'] = count($cands);
    if (!$cands) { rocNewsSaveState($pdo, $state); return $force ? 'No story right now: none is reported by enough trusted sources and linked to our products or guides.' : null; }
    $c = $cands[0];
    $state['last_try'] = gmdate('Y-m-d H:i:s');
    rocNewsSaveState($pdo, $state);
    $prompt = rocNewsPrompt($c);
    $msg = null;
    for ($try = 1; $try <= 2; $try++) {
        $model = null;
        $w = rocBlogWriteAny($pdo, $prompt, $model);
        if (!$w['ok']) { $msg = $w['error']; continue; }
        $r = rocNewsCreate($pdo, $root, $w['article'], $c, 'server (' . $model . ')');
        if ($r['ok']) {
            rocNewsMarkDone($pdo, $c, 'published', $r['id']);
            $state = rocNewsState($pdo);
            $state['last_ok'] = gmdate('Y-m-d H:i:s'); $state['last_error'] = '';
            rocNewsSaveState($pdo, $state);
            try { rocNewsToBar($pdo, $root, $r['title'], '/news/' . $r['slug'] . '/'); } catch (\Throwable $e) {}
            try { rocNotifyNews($pdo, $r, $c); } catch (\Throwable $e) {}
            return 'News published: ' . $r['url'];
        }
        $msg = 'Article not accepted: ' . implode(' ', $r['errors']);
        $prompt .= "\n\nYour previous answer was rejected: " . implode(' ', $r['errors']) . ' Fix this.';
    }
    rocNewsMarkDone($pdo, $c, 'failed');
    $state = rocNewsState($pdo);
    $state['last_error'] = gmdate('Y-m-d H:i') . ' UTC: ' . $msg;
    rocNewsSaveState($pdo, $state);
    return $msg;
}
