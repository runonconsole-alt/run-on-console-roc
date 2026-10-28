<?php
/**
 * Run On Console — Off-page researcher on the server (Deploy 93). OFF until the owner turns it on in
 * CMS > Off-page: while his Claude subscription runs, the daily research is done by Claude (the
 * "roc-link-opportunities" task, saved to the ROC Listing Kit page); this is the same job with the free
 * Groq key, for when that ends.
 *
 * Once a day after 12:00 Pakistan time: Groq's web-search model proposes candidates, then THIS server
 * opens every page itself and drops anything that breaks the owner's rules (free only, gaming niche only,
 * real healthy sites, no link farms, no spam topics), and the content models write a draft for each kept
 * one. Quality over count: fewer than 10 is fine. Nothing is ever posted, no account is ever made.
 *
 * cms_settings: offpage_auto (off|on), offpage_opps [items, newest first, at most 400], offpage_log [runs].
 */
if (defined('ROC_OFFPAGE_LIB')) return;
define('ROC_OFFPAGE_LIB', 1);
require_once __DIR__ . '/content-writer-lib.php';

const ROC_OP_SITE = "runonconsole.com (Run On Console): free \"Can I run it?\" pages for 2,400+ PC games (/can-i-run/{game}/ with official Steam requirements and PC builds), the PC checker (/compatibility/), \"X vs Y\" comparisons (/compare/), best-of lists per category (/category/{mice|keyboards|audio|monitors|gpu|cpu|ram|cases|chairs|handhelds|laptops}/), guides (/guides/), news (/news/), gaming platform guides (/gaming-platforms/).";
// Domains that sell links or are not places gamers read.
const ROC_OP_BLOCK = '/(^|\.)(fiverr|legiit|seoclerks|upwork|peopleperhour|freelancer|blogger|medium|pinterest|facebook|instagram|x|twitter|tiktok|linkedin|youtube|wikipedia|amazon|ebay|aliexpress)\.|guestpost|guest-post|backlink|linkbuild|seo(tool|service|agency)|pbn|casino|betting|loan|crypto|cbd/i';
const ROC_OP_SPAM = '/\b(casino|betting|gambl\w*|slot machines?|poker online|cbd|cannabis|payday|loans?|forex|binary options|crypto (?:signals|trading)|essay writing|escort|porn|viagra|replica watches|weight loss pills)\b/i';
const ROC_OP_PAID = '/(\$\s?\d{2,}|\b\d{2,}\s?(usd|dollars)\b|\bprice\b|\bpricing\b|\bfee\b|\bpaid (guest )?posts?\b|sponsored posts?\b|link insertion|paypal|payment)/i';
const ROC_OP_NICHE = '/\b(game|games|gaming|gamer|gamers|pc build|graphics card|gpu|cpu|steam|playstation|ps5|xbox|nintendo|switch 2|esports|console|fps|rtx|radeon|ryzen|keyboard|mouse|headset|monitor)\b/i';

function rocOpLog(PDO $pdo): array { $l = rocBlogGet($pdo, 'offpage_log', []); return is_array($l) ? $l : []; }
function rocOpItems(PDO $pdo): array { $l = rocBlogGet($pdo, 'offpage_opps', []); return is_array($l) ? $l : []; }
function rocOpDomain(string $url): string { return strtolower((string)preg_replace('/^www\./', '', (string)parse_url($url, PHP_URL_HOST))); }

/** Opens a page and checks it against the owner's rules: ['ok' => bool, 'why' => reason when not]. */
function rocOpCheck(string $url, string $kind): array {
    if (!preg_match('#^https?://#i', $url)) return ['ok' => false, 'why' => 'not a web address'];
    $d = rocOpDomain($url);
    $forum = preg_match('/(^|\.)reddit\.com$|(^|\.)quora\.com$/', $d);
    if (!$forum && preg_match(ROC_OP_BLOCK, $d)) return ['ok' => false, 'why' => 'blocked kind of site'];
    if ($forum) {
        // Reddit and Quora block servers: judge them by their address (a real question in a gaming place).
        if (preg_match('/reddit\.com$/', $d) && !preg_match('#/r/(buildapc|pcgaming|lowendgaming|gamingsuggestions|pakistanigamers|gamingpc|pcmasterrace|suggestalaptop|laptops|steamdeck|GamingLaptops)/comments/#i', $url)) return ['ok' => false, 'why' => 'not a gaming subreddit question'];
        if (preg_match('/quora\.com$/', $d) && !preg_match('#quora\.com/[A-Za-z0-9%]+(?:-[A-Za-z0-9%]+){2,}/?$#', $url)) return ['ok' => false, 'why' => 'not a Quora question'];
        return ['ok' => true, 'why' => ''];
    }
    $c = curl_init($url);
    curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 4, CURLOPT_TIMEOUT => 20, CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; RunOnConsole-research/1.0; +https://runonconsole.com/)']);
    $html = (string)curl_exec($c); $code = (int)curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c);
    if ($code !== 200) return ['ok' => false, 'why' => 'page does not open (HTTP ' . $code . ')'];
    $text = trim((string)preg_replace('/\s+/', ' ', strip_tags((string)preg_replace('#<(script|style|noscript)[^>]*>.*?</\1>#si', ' ', $html))));
    $words = str_word_count($text);
    if ($words < 400) return ['ok' => false, 'why' => 'thin page'];
    if (preg_match_all(ROC_OP_SPAM, $text) >= 1) return ['ok' => false, 'why' => 'spam topics on the site'];
    if (in_array($kind, ['Guest post', 'Listing'], true) && preg_match(ROC_OP_PAID, $text)) return ['ok' => false, 'why' => 'asks for money'];
    if (preg_match_all(ROC_OP_NICHE, $text) < 8) return ['ok' => false, 'why' => 'not a gaming site'];
    if (!preg_match('/\b(2026|2025)\b/', $text)) return ['ok' => false, 'why' => 'no recent posts'];
    $ext = preg_match_all('#<a\s[^>]*href=["\']https?://(?!(?:www\.)?' . preg_quote($d, '#') . ')#i', $html);
    if ($ext > 120 && $words < 1500) return ['ok' => false, 'why' => 'too many outside links (link farm)'];
    return ['ok' => true, 'why' => ''];
}

/** Candidates from Groq's web-search model: [{kind, url, title, link_to, why}]. */
function rocOpCandidates(PDO $pdo, array $known): array {
    $key = trim((string)rocBlogGet($pdo, 'blog_groq_key', ''));
    if ($key === '') return ['error' => 'no Groq key (CMS > Updates writer)'];
    $prompt = "Find link opportunities for this website: " . ROC_OP_SITE . "\n\nSearch the web now and list 20 REAL pages (exact URLs you found today) of these kinds:\n"
        . "- 6 \"write for us\" / contributor pages on real PC gaming, video game, esports or PC hardware blogs that accept FREE guest posts;\n"
        . "- 4 resource pages on gaming sites that list useful tools for PC gamers;\n"
        . "- 6 fresh questions (this week) on reddit.com (r/buildapc, r/pcgaming, r/lowendgaming, r/gamingsuggestions, r/PakistaniGamers, r/SuggestALaptop) or quora.com that a page above answers;\n"
        . "- 4 free gaming or tech directories / communities to list the site.\n"
        . "Rules: free only (no paid posts, no link sellers), gaming niche only, no general blogs that accept every topic, no casino/CBD/loans/crypto/adult sites.\n"
        . (count($known) ? 'Do not list these domains: ' . implode(', ', array_slice($known, 0, 60)) . "\n" : '')
        . "Answer ONLY with a JSON array: [{\"kind\": \"Guest post\"|\"Resource page\"|\"Reddit\"|\"Quora\"|\"Listing\", \"url\": \"...\", \"title\": \"site name or the question\", \"link_to\": \"the runonconsole.com page that fits\", \"why\": \"one sentence\"}]";
    $body = ['messages' => [['role' => 'user', 'content' => $prompt]], 'temperature' => 0.3, 'max_tokens' => 3500];
    $errs = [];
    foreach (['groq/compound', 'groq/compound-mini', 'openai/gpt-oss-120b'] as $model) {
        $b = $body + ['model' => $model];
        if (strpos($model, 'gpt-oss') !== false) { $b['tools'] = [['type' => 'browser_search']]; $b['reasoning_effort'] = 'low'; }
        $r = rocGroqCall($key, 'POST', '/chat/completions', $b, 120);
        if (!$r['ok']) { $errs[] = $model . ': ' . $r['error']; continue; }
        $t = (string)($r['data']['choices'][0]['message']['content'] ?? '');
        if (preg_match('/\[\s*\{.*\}\s*\]/s', $t, $m)) { $a = json_decode($m[0], true); if (is_array($a) && $a) return ['items' => $a, 'model' => $model]; }
        $errs[] = $model . ': no list in the answer';
    }
    return ['error' => implode(' | ', $errs)];
}

/** One research run. Returns a log line. */
function rocOpRun(PDO $pdo): string {
    @set_time_limit(600);
    $items = rocOpItems($pdo);
    $known = []; $week = time() - 7 * 86400;
    foreach ($items as $it) { $known[$it['url']] = 1; if ((int)($it['t'] ?? 0) > $week) $known[rocOpDomain($it['url'])] = 1; }
    $cand = rocOpCandidates($pdo, array_keys(array_filter($known, function ($k) { return strpos((string)$k, '/') === false; }, ARRAY_FILTER_USE_KEY)));
    $day = (new DateTime('now', new DateTimeZone('Asia/Karachi')))->format('Y-m-d');
    $log = ['date' => $day, 't' => time(), 'kept' => 0, 'dropped' => [], 'error' => ''];
    if (!empty($cand['error'])) { $log['error'] = $cand['error']; rocOpSaveLog($pdo, $log); return 'off-page: ' . $cand['error']; }
    $kept = []; $domains = [];
    foreach ((array)$cand['items'] as $c) {
        if (count($kept) >= 10) break;
        $url = trim((string)($c['url'] ?? '')); $kind = (string)($c['kind'] ?? '');
        if ($url === '' || isset($known[$url])) continue;
        $d = rocOpDomain($url);
        if (!preg_match('/reddit|quora/', $d) && (isset($known[$d]) || isset($domains[$d]))) { $log['dropped']['already this week'] = ($log['dropped']['already this week'] ?? 0) + 1; continue; }
        $chk = rocOpCheck($url, $kind);
        if (!$chk['ok']) { $log['dropped'][$chk['why']] = ($log['dropped'][$chk['why']] ?? 0) + 1; continue; }
        $domains[$d] = 1;
        $kept[] = ['id' => $day . '-' . str_pad((string)(count($kept) + 1), 2, '0', STR_PAD_LEFT), 'date' => $day, 't' => time(), 'kind' => $kind ?: 'Listing',
                   'title' => mb_substr(trim((string)($c['title'] ?? $d)), 0, 160), 'site' => $d, 'url' => $url,
                   'link_to' => mb_substr(trim((string)($c['link_to'] ?? 'https://runonconsole.com/')), 0, 200), 'why' => mb_substr(trim((string)($c['why'] ?? '')), 0, 300), 'draft' => '', 'status' => ''];
    }
    // Drafts for the kept ones (one call; the owner edits and posts them himself).
    if ($kept) {
        $list = array_map(function ($k) { return ['id' => $k['id'], 'kind' => $k['kind'], 'title' => $k['title'], 'url' => $k['url'], 'link_to' => $k['link_to']]; }, $kept);
        $r = rocContentAsk($pdo, "Write drafts the owner of runonconsole.com will edit and post himself. Site: " . ROC_OP_SITE . "\n\nITEMS:\n" . json_encode($list, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            . "\n\nFor a Reddit or Quora question: a genuinely helpful 60-120 word answer that solves the question first and mentions the link_to page once as a source with a natural anchor (the page title or the site name, never a stuffed keyword). For a guest post: a pitch email, subject line + 90 words, with 2 article ideas that fit that blog. For a resource page: a 70-word note suggesting the tool. For a listing: the fields to fill (name, website, short description). English, no hype, no prices, no fake claims.\n"
            . "Answer with one JSON object: {\"drafts\": [{\"id\": ..., \"draft\": ...}]}", ['drafts']);
        if ($r['ok']) foreach ((array)$r['a']['drafts'] as $dr) foreach ($kept as &$k) if ($k['id'] === (string)($dr['id'] ?? '')) $k['draft'] = rocContentClean(mb_substr((string)($dr['draft'] ?? ''), 0, 2000));
        unset($k);
    }
    rocBlogSet($pdo, 'offpage_opps', array_slice(array_merge($kept, $items), 0, 400));
    $log['kept'] = count($kept); $log['model'] = $cand['model'] ?? '';
    rocOpSaveLog($pdo, $log);
    return 'off-page: ' . count($kept) . ' kept, ' . array_sum($log['dropped']) . ' dropped';
}

function rocOpSaveLog(PDO $pdo, array $log): void { rocBlogSet($pdo, 'offpage_log', array_slice(array_merge([$log], rocOpLog($pdo)), 0, 30)); }

/**
 * Owner, 2026-10-28 (Deploy 129): the link list comes by email, made by the server (the PC can be off): on
 * 1 November 2026, then every Monday and on the 1st of every month, from 10:00 Pakistan time, to the alert
 * address (CMS > Updates writer > News > Email alerts). It lists the opportunities still open (no status) found
 * since the last email, newest first, with the drafts. cms_settings offpage_report {day, t}.
 */
const ROC_OP_REPORT_FROM = '2026-11-01';
function rocOffpageReport(PDO $pdo, bool $force = false): ?string {
    $now = new DateTime('now', new DateTimeZone('Asia/Karachi'));
    $day = $now->format('Y-m-d');
    $last = rocBlogGet($pdo, 'offpage_report', []);
    $last = is_array($last) ? $last : [];
    if (!$force) {
        if ($day < ROC_OP_REPORT_FROM || (int)$now->format('G') < 10 || ($last['day'] ?? '') === $day) return null;
        if ($day !== ROC_OP_REPORT_FROM && $now->format('N') !== '1' && $now->format('j') !== '1') return null;
    }
    $since = (int)($last['t'] ?? 0);
    $open = array_values(array_filter(rocOpItems($pdo), function ($it) use ($since) { return ($it['status'] ?? '') === '' && (int)($it['t'] ?? 0) > $since; }));
    rocBlogSet($pdo, 'offpage_report', ['day' => $day, 't' => time(), 'n' => count($open)]);
    if (!$open) return 'off-page report: nothing new to send';
    $e = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
    $rows = ''; $drafts = '';
    foreach (array_slice($open, 0, 60) as $i => $it) {
        $rows .= '<tr><td style="padding:6px;border-bottom:1px solid #e2e8f0">' . $e($it['date'] ?? '') . '</td><td style="padding:6px;border-bottom:1px solid #e2e8f0">' . $e($it['kind'] ?? '')
               . '</td><td style="padding:6px;border-bottom:1px solid #e2e8f0"><a href="' . $e($it['url']) . '">' . $e($it['title'] ?: $it['site']) . '</a><br><span style="color:#64748b;font-size:12px">' . $e($it['why'] ?? '') . '</span></td>'
               . '<td style="padding:6px;border-bottom:1px solid #e2e8f0">' . $e(str_replace('https://runonconsole.com', '', (string)($it['link_to'] ?? ''))) . '</td></tr>';
        if ($i < 15 && trim((string)($it['draft'] ?? '')) !== '')
            $drafts .= '<h3 style="font-size:15px;margin:18px 0 4px">' . ($i + 1) . '. ' . $e($it['title'] ?: $it['site']) . '</h3><div style="white-space:pre-wrap;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:10px">' . $e($it['draft']) . '</div>';
    }
    $html = '<p><b>Backlink opportunities for Run On Console</b>: ' . count($open) . ' new, free, gaming-niche places found since ' . ($since ? $e(date('j M Y', $since)) : 'the start') . '.</p>'
          . '<p>Rules: free and gaming-niche only, you post them yourself, natural link words (the page title or Run On Console). Mark each done or skip in CMS &gt; Off-page.</p>'
          . '<table style="border-collapse:collapse;width:100%;font-size:14px"><tr style="background:#f1f5f9"><th style="padding:6px;text-align:left">Date</th><th style="padding:6px;text-align:left">Kind</th><th style="padding:6px;text-align:left">Where</th><th style="padding:6px;text-align:left">Link to</th></tr>' . $rows . '</table>'
          . ($drafts !== '' ? '<h2 style="font-size:17px;margin-top:24px">Drafts to edit and post</h2>' . $drafts : '');
    require_once __DIR__ . '/news-engine-lib.php';
    $ok = rocNotify($pdo, 'ROC backlinks report - ' . $now->format('j F Y') . ' (' . count($open) . ' new)', $html);
    return 'off-page report: ' . ($ok ? 'emailed ' . count($open) : 'email failed (check CMS > News > Email alerts)');
}

/** The scheduler: once a day after 12:00 Pakistan time, only when the owner turned it on. */
function rocOffpageTick(PDO $pdo): ?string {
    if ((string)rocBlogGet($pdo, 'offpage_auto', 'off') !== 'on') return null;
    $now = new DateTime('now', new DateTimeZone('Asia/Karachi'));
    if ((int)$now->format('G') < 12) return null;
    $last = rocOpLog($pdo)[0]['date'] ?? '';
    if ($last === $now->format('Y-m-d')) return null;
    return rocOpRun($pdo);
}
