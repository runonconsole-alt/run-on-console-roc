<?php
/**
 * Run On Console (ROC) — CMS > Website info (administrators)
 *
 * GET  what the website is built with, what each part is for, when it was made, the tools
 *      connected to it (with a live status from the saved settings) and every deploy
 *      (roc-releases/deploy-NN.json next to public_html). Read-only; no key is ever shown.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/news-engine-lib.php';

requireCmsAdmin();
$pdo = getDBConnection();
if (!$pdo) rocCmsDeny(503, 'Database connection unavailable.');

$ROOT = dirname(__DIR__, 3);
$g = function (string $k) use ($pdo): string { try { return trim((string)rocLayerSettingGet($pdo, $k)); } catch (\Throwable $e) { return ''; } };
$ago = function (string $utc): string {
    if ($utc === '') return '';
    $t = strtotime(preg_replace('/\s*\(.*$/', '', $utc) . ' UTC');
    if (!$t) return '';
    $d = time() - $t;
    return $d < 3600 ? max(1, (int)($d / 60)) . ' min ago' : ($d < 172800 ? (int)($d / 3600) . ' hours ago' : (int)($d / 86400) . ' days ago');
};

/* ---------------------------------------------------------------- history */
$history = [];
foreach (glob(dirname($ROOT) . '/roc-releases/deploy-*.json') ?: [] as $f) {
    $r = json_decode((string)file_get_contents($f), true);
    if (is_array($r) && isset($r['deploy'])) $history[] = ['deploy' => (int)$r['deploy'], 'date' => (string)($r['date'] ?? ''), 'title' => (string)($r['title'] ?? '')];
}
usort($history, function ($a, $b) { return $b['deploy'] <=> $a['deploy']; });

/* ------------------------------------------------------------------ counts */
$count = function (string $sql) use ($pdo): int { try { return (int)$pdo->query($sql)->fetchColumn(); } catch (\Throwable $e) { return 0; } };
$products = $count("SELECT COUNT(*) FROM products WHERE status = 'published'");
$cats = $count("SELECT COUNT(*) FROM product_categories WHERE status = 'published'");
$blogs = $count("SELECT COUNT(*) FROM blogs WHERE status = 'published' AND (category IS NULL OR category <> 'News')");
$news = $count("SELECT COUNT(*) FROM blogs WHERE status = 'published' AND category = 'News'");
$plats = $count("SELECT COUNT(*) FROM gaming_categories WHERE status = 'published'");

$facts = [
    ['Address', 'https://runonconsole.com'],
    ['What it is', 'ROC (Run On Console): an independent gaming and PC hardware site with product picks (Amazon affiliate), a "Can I run it" game checker, gaming platform guides, blogs and live news.'],
    ['Built', 'October 2026 (first code: 6 October 2026); live since Deploy 1, updated with every deploy below.'],
    ['Latest deploy', $history ? '#' . $history[0]['deploy'] . ' on ' . $history[0]['date'] . ': ' . $history[0]['title'] : 'unknown'],
    ['Content now', "{$products} products in {$cats} categories · {$blogs} blog posts · {$news} news stories · {$plats} gaming platforms"],
    ['Hosting', 'cPanel shared hosting (LiteSpeed web server, PHP ' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . ', MySQL); files in /home2/runoncon/public_html'],
    ['Code', 'GitHub repository runonconsole-alt/run-on-console-roc (every change is a pull request, deployed as a zip)'],
];

$stack = [
    ['name' => 'React 18', 'purpose' => 'The look and the interactive parts of the main pages (home, "Can I run it" checker, platforms).'],
    ['name' => 'Vite 5', 'purpose' => 'Builds the React code into fast static files and prerenders every page to HTML for Google.'],
    ['name' => 'Tailwind CSS 3', 'purpose' => 'The design (colours, spacing, layout on phones and computers).'],
    ['name' => 'Lucide icons', 'purpose' => 'The icons in the menus, cards and buttons.'],
    ['name' => 'PHP renderers', 'purpose' => 'product-render.php, blog-render.php, category-render.php, page-render.php and sitemap-render.php build product, blog, news, platform and CMS pages straight from the database, so a change in the CMS is live at once.'],
    ['name' => 'MySQL database', 'purpose' => 'Products, categories, blog posts, news, pages, CMS users, settings and messages.'],
    ['name' => 'This CMS', 'purpose' => 'A one-file admin app (cms/index.html) with its API in api/v1/cms/: content, site builder, menus, metas, sitemap, analytics, blog and news writer.'],
    ['name' => 'Cron jobs (cPanel)', 'purpose' => 'cli-blog-scheduler.php runs every few minutes on the server: publishes scheduled posts, writes the next blog post, reads the news sources and publishes news. No PC needed.'],
    ['name' => 'roc-nav.js', 'purpose' => 'The header search box, the menu from the CMS and the Amazon tag on product links, on every page.'],
    ['name' => '.htaccess (LiteSpeed)', 'purpose' => 'Clean addresses (/products/..., /blogs/..., /news/...), redirects, HTTPS and security rules.'],
    ['name' => 'GD image library', 'purpose' => 'Draws the blog and news cover pictures (title + product photo).'],
];

/* ------------------------------------------------------------------- tools */
$bstate = rocBlogGet($pdo, 'blog_engine_state', []); $bstate = is_array($bstate) ? $bstate : [];
$nstate = rocNewsState($pdo);
$nset = rocNewsSettings($pdo);
$groq = $g('blog_groq_key') !== ''; $gem = $g('blog_gemini_key') !== '';
$tag = $g('amazon_tag');
$ga4 = $g('ga4_property_id'); $gsc = $g('gsc_site_url');
$saKey = false;
if (is_file(dirname($ROOT) . '/roc-google-sa.json')) $saKey = true;
$mail = (string)rocBlogGet($pdo, 'notify_email', '');
$tools = [
    ['name' => 'Amazon Associates', 'url' => 'https://affiliate-program.amazon.com/', 'purpose' => 'Earns a commission when visitors buy through the product and news links.',
     'status' => $tag !== '' ? 'ok' : 'off', 'detail' => $tag !== '' ? 'Tag ' . $tag . ' is added to every Amazon link.' : 'Set the tag in CMS > Social & Amazon tag.'],
    ['name' => 'Groq (AI writer)', 'url' => 'https://console.groq.com/', 'purpose' => 'Writes the blog posts and news stories on the server (free key).',
     'status' => $groq ? (!empty($bstate['last_error']) && empty($bstate['last_ok']) ? 'bad' : 'ok') : 'off',
     'detail' => $groq ? 'Model: ' . ($g('blog_groq_model') ?: 'picked automatically') . (!empty($bstate['last_ok']) ? '. Last post written ' . $ago((string)$bstate['last_ok']) . '.' : '') : 'Add the key in CMS > Blog & news writer.'],
    ['name' => 'Google Gemini (AI writer)', 'url' => 'https://aistudio.google.com/', 'purpose' => 'Second writer, used first when its key works.',
     'status' => $gem ? 'ok' : 'off', 'detail' => $gem ? '' : 'Optional (Google refused this account, Groq does the writing).'],
    ['name' => 'Cloudflare Workers AI', 'url' => 'https://dash.cloudflare.com/', 'purpose' => 'Makes a cover picture for posts that have no product photo (FLUX model, free).',
     'status' => rocBlogCf($pdo) ? (!empty($bstate['last_image_error']) ? 'bad' : 'ok') : 'off', 'detail' => !empty($bstate['last_image_error']) ? 'Last problem: ' . $bstate['last_image_error'] : ''],
    ['name' => 'Google Analytics 4', 'url' => 'https://analytics.google.com/', 'purpose' => 'Counts visitors, pages and traffic sources (CMS > Analytics).',
     'status' => $ga4 !== '' && $saKey ? 'ok' : ($ga4 !== '' ? 'bad' : 'off'), 'detail' => $ga4 !== '' ? 'Property ' . $ga4 . ($saKey ? '' : ' (service account key file missing)') : ''],
    ['name' => 'Google Search Console', 'url' => 'https://search.google.com/search-console', 'purpose' => 'Google searches, clicks and positions (CMS > Analytics); sitemaps are submitted here.',
     'status' => $gsc !== '' && $saKey ? 'ok' : ($gsc !== '' ? 'bad' : 'off'), 'detail' => $gsc],
    ['name' => 'Google autocomplete', 'url' => '', 'purpose' => 'Finds the keywords people really search, for blog titles and links (free, no key).', 'status' => function_exists('curl_init') ? 'ok' : 'bad', 'detail' => ''],
    ['name' => 'Live news sources', 'url' => '', 'purpose' => count(rocNewsFeeds($pdo)) . ' trusted RSS sources (official company blogs and gaming/hardware sites) that every news story is checked against.',
     'status' => !$nset['on'] ? 'off' : (!empty($nstate['last_error']) && empty($nstate['last_ok']) ? 'bad' : 'ok'),
     'detail' => ($nset['on'] ? 'On, up to ' . $nset['per_day'] . ' stories a day.' : 'Off (CMS > Blog & news writer > News).') . (!empty($nstate['last_fetch']) ? ' Sources read ' . $ago((string)$nstate['last_fetch']) . '.' : '')],
    ['name' => 'Email alerts', 'url' => '', 'purpose' => 'Emails you when a blog post or a news story goes live (server mail).',
     'status' => $mail !== '' ? (strpos((string)($nstate['last_email'] ?? ''), 'failed') !== false ? 'bad' : 'ok') : 'off',
     'detail' => $mail !== '' ? 'To ' . $mail . (!empty($nstate['last_email']) ? '. Last email ' . $ago((string)$nstate['last_email']) . '.' : '') : 'Set an address in CMS > Blog & news writer > News.'],
    ['name' => 'Server cron', 'url' => '', 'purpose' => 'Runs the scheduler, the blog writer and the news writer by itself.',
     'status' => !empty($nstate['last_fetch']) || !empty($bstate['last_try']) ? 'ok' : 'unknown',
     'detail' => !empty($bstate['last_try']) ? 'Blog writer last ran ' . $ago((string)$bstate['last_try']) . '.' : ''],
    ['name' => 'GitHub', 'url' => 'https://github.com/runonconsole-alt/run-on-console-roc', 'purpose' => 'Keeps all the code and every change (pull requests).', 'status' => 'unknown', 'detail' => ''],
];

echo json_encode(['success' => true, 'facts' => $facts, 'stack' => $stack, 'tools' => $tools, 'history' => $history], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
