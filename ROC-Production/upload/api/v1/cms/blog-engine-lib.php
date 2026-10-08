<?php
/**
 * Run On Console (ROC) — the blog engine: content plan, internal links and writers.
 *
 * Used by:
 *   api/v1/blog-agent.php            the writer on the PC (Claude app) asks for the next topic and posts
 *   api/v1/cms/blog-agent-admin.php  CMS > Blog agent (plan, writer, times, log)
 *   api/v1/cron/cli-blog-scheduler.php
 *       every 10 minutes: publishes due posts, refreshes their "Keep reading" links and,
 *       when the writer is "server", writes the next post with Google Gemini (free key).
 *       Nothing on the PC is needed then, and no Claude subscription.
 *
 * Content plan (cms_settings.blog_plan):
 *   {pillars: [{id, name, url, kind}], topics: [{id, pillar, stage: tofu|mofu|bofu, title,
 *    keyword, secondary[], products[], status: planned|written|skipped, post_id?, slug?}]}
 *   A pillar is a topic cluster: its hub page (a product category, the game checker or
 *   the platform guides) plus every post about it. Each post links up to its hub, across
 *   to its sister posts and down to the products; the hub gets links back from every post.
 *   Topics rotate TOFU (learn) -> MOFU (compare) -> BOFU (buy) so every cluster grows evenly.
 *
 * Settings (cms_settings): blog_engine (server | pc | off), blog_gemini_key, blog_gemini_model,
 *   blog_slots (["16:00","23:00"], Pakistan time), blog_plan, blog_agent_log, blog_engine_state.
 */
if (defined('ROC_BLOG_ENGINE_LIB')) return;
define('ROC_BLOG_ENGINE_LIB', 1);

require_once __DIR__ . '/site-layer-lib.php';
require_once __DIR__ . '/cms-html.php';

const ROC_BLOG_TZ = 'Asia/Karachi';
const ROC_BLOG_SLOTS = ['16:00', '23:00'];
const ROC_BLOG_AUTHOR = 'ROC';
const ROC_GEMINI_MODEL = 'gemini-2.5-flash';
const ROC_GEMINI_API = 'https://generativelanguage.googleapis.com/v1beta';
const ROC_BLOG_STAGES = ['tofu', 'mofu', 'bofu'];
const ROC_RELATED_H2 = 'Keep reading';

/* -------------------------------------------------------------- settings */

function rocBlogGet(PDO $pdo, string $k, $default = null) {
    $v = rocLayerSettingGet($pdo, $k);
    if ($v === null || $v === '') return $default;
    $d = json_decode($v, true);
    return $d === null && $v !== 'null' ? $v : $d;
}
function rocBlogSet(PDO $pdo, string $k, $v, ?int $uid = null): void {
    rocLayerSettingSet($pdo, $k, is_string($v) ? $v : (string)json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $uid);
}
function rocBlogEngine(PDO $pdo): string {
    $e = (string)rocBlogGet($pdo, 'blog_engine', 'pc');
    return in_array($e, ['server', 'pc', 'off'], true) ? $e : 'pc';
}
function rocBlogSlots(PDO $pdo): array {
    $s = rocBlogGet($pdo, 'blog_slots', null);
    $s = is_array($s) ? array_values(array_filter($s, function ($x) { return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string)$x); })) : [];
    sort($s);
    return $s ?: ROC_BLOG_SLOTS;
}
function rocBlogCols(PDO $pdo, string $t): array {
    try { $st = $pdo->query("SELECT * FROM {$t} LIMIT 0"); } catch (\Throwable $e) { return []; }
    $c = []; for ($i = 0; $i < $st->columnCount(); $i++) $c[] = strtolower((string)$st->getColumnMeta($i)['name']);
    return $c;
}
function rocBlogSlug(string $s): string {
    $s = strtolower(trim((string)preg_replace('/[^a-z0-9]+/i', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s), '-'));
    return trim(substr($s, 0, 90), '-');
}
function rocBlogLog(PDO $pdo, array $entry): void {
    $log = rocBlogGet($pdo, 'blog_agent_log', []);
    $log = is_array($log) ? $log : [];
    array_unshift($log, $entry + ['created' => gmdate('Y-m-d H:i:s')]);
    rocBlogSet($pdo, 'blog_agent_log', array_slice($log, 0, 100));
}

/* ----------------------------------------------------------------- slots */

/** Free slots (UTC 'Y-m-d H:i:s' => label) for today and the next 2 days, after now + 5 minutes. */
function rocBlogFreeSlots(PDO $pdo, int $days = 3): array {
    $tz = new DateTimeZone(ROC_BLOG_TZ);
    $taken = [];
    $q = $pdo->query("SELECT published_at FROM blogs WHERE status IN ('scheduled', 'published') AND published_at >= '" . gmdate('Y-m-d H:i:s', time() - 86400) . "'");
    foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $t) $taken[] = strtotime($t . ' UTC');
    $out = [];
    for ($d = 0; $d < $days; $d++) {
        foreach (rocBlogSlots($pdo) as $hm) {
            $local = new DateTime('today ' . $hm, $tz);
            $local->modify("+{$d} day");
            $ts = $local->getTimestamp();
            if ($ts < time() + 300) continue;
            foreach ($taken as $t) if (abs($t - $ts) < 1800) continue 2;    // a post within 30 min of the slot
            $out[gmdate('Y-m-d H:i:s', $ts)] = $local->format('D j M, H:i') . ' (Pakistan time)';
        }
    }
    return $out;
}

/* ------------------------------------------------------------- site data */

/** Products, product categories, platforms and posts, with their addresses. */
function rocBlogSite(PDO $pdo, bool $fresh = false): array {
    static $cache = null;
    if ($cache !== null && !$fresh) return $cache;
    $cats = []; $byName = [];
    if (rocBlogCols($pdo, 'product_categories')) {
        foreach ($pdo->query("SELECT name, slug FROM product_categories WHERE status = 'published' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC) as $c) {
            $cats[$c['slug']] = ['name' => $c['name'], 'slug' => $c['slug'], 'url' => '/products/category/' . $c['slug'] . '/'];
            $byName[strtolower($c['name'])] = $c['slug'];
        }
    }
    $products = [];
    if (rocBlogCols($pdo, 'products')) {
        foreach ($pdo->query("SELECT title, slug, category FROM products WHERE status = 'published' ORDER BY category, title")->fetchAll(PDO::FETCH_ASSOC) as $p) {
            $cs = $byName[strtolower((string)$p['category'])] ?? (isset($cats[$p['category']]) ? $p['category'] : '');
            $products[$p['slug']] = ['title' => $p['title'], 'url' => '/products/' . $p['slug'] . '/', 'cat' => $cs, 'category' => $p['category']];
        }
    }
    $plats = [];
    if (rocBlogCols($pdo, 'gaming_categories')) {
        foreach ($pdo->query("SELECT title, slug FROM gaming_categories WHERE status = 'published'")->fetchAll(PDO::FETCH_ASSOC) as $c) {
            $plats[$c['slug']] = ['name' => $c['title'], 'url' => '/gaming-platforms/' . $c['slug'] . '/'];
        }
    }
    $posts = [];
    foreach ($pdo->query("SELECT id, title, slug, status, published_at FROM blogs ORDER BY COALESCE(published_at, created_at) DESC")->fetchAll(PDO::FETCH_ASSOC) as $b) {
        $posts[$b['id']] = $b + ['url' => '/blogs/' . $b['slug'] . '/'];
    }
    return $cache = ['cats' => $cats, 'products' => $products, 'platforms' => $plats, 'posts' => $posts];
}

/* ---------------------------------------------------------- content plan */

/** Words used in topic titles for each product category. */
function rocBlogNouns(string $slug, string $name): array {
    $m = ['gpu' => ['graphics card', 'graphics cards', 'GPU'], 'monitors' => ['gaming monitor', 'gaming monitors', 'monitor'],
          'mice' => ['gaming mouse', 'gaming mice', 'mouse'], 'keyboards' => ['gaming keyboard', 'gaming keyboards', 'keyboard'],
          'audio' => ['gaming headset', 'gaming headsets', 'headset'], 'speakers' => ['PC speakers', 'PC speakers', 'speakers']];
    if (isset($m[$slug])) return $m[$slug];
    $n = strtolower(preg_replace('/\s*\(.*\)$/', '', $name));
    return [rtrim($n, 's'), $n, rtrim($n, 's')];
}

/** Extra learn (TOFU) topics per category: the questions people really search. */
function rocBlogCatExtras(string $slug): array {
    $x = [
        'gpu' => [['How much VRAM do you need for gaming in 2026?', 'how much vram for gaming'], ['Ray tracing explained: is it worth it for gamers?', 'is ray tracing worth it'],
                  ['DLSS vs FSR vs XeSS: upscaling explained', 'dlss vs fsr'], ['How to tell if your GPU is bottlenecked by your CPU', 'gpu bottleneck']],
        'monitors' => [['Refresh rate vs response time: what matters for gaming', 'refresh rate vs response time'], ['OLED vs IPS gaming monitors: which is better?', 'oled vs ips for gaming'],
                       ['1080p vs 1440p vs 4K: which resolution should gamers pick?', '1440p vs 4k gaming'], ['Best monitor settings for gaming, step by step', 'best monitor settings for gaming']],
        'mice' => [['Mouse DPI and polling rate explained for gamers', 'mouse dpi explained'], ['Wired vs wireless gaming mice: is there still a difference?', 'wired vs wireless gaming mouse'],
                   ['Palm, claw or fingertip grip: how to pick a mouse shape', 'mouse grip types'], ['Lightweight gaming mice: why weight matters', 'lightweight gaming mouse']],
        'keyboards' => [['Hall effect vs mechanical switches: what is the difference?', 'hall effect vs mechanical'], ['What is rapid trigger and who needs it?', 'what is rapid trigger'],
                        ['Keyboard sizes explained: full, TKL, 75% and 60%', 'keyboard sizes explained'], ['Linear vs tactile vs clicky switches for gaming', 'linear vs tactile switches']],
        'audio' => [['Open-back vs closed-back headphones for gaming', 'open back vs closed back gaming'], ['Gaming headset or headphones plus a mic?', 'gaming headset vs headphones'],
                    ['Spatial audio for games explained', 'spatial audio gaming'], ['Wireless gaming headsets: latency and battery explained', 'wireless gaming headset latency']],
        'speakers' => [['2.0 vs 2.1 speakers for a gaming desk', '2.1 vs 2.0 speakers'], ['Bookshelf speakers vs computer speakers', 'bookshelf vs computer speakers'],
                       ['Soundbar or desktop speakers for a gaming setup?', 'soundbar vs pc speakers'], ['How to place desktop speakers for the best sound', 'desktop speaker placement']],
    ];
    return $x[$slug] ?? [];
}

const ROC_BLOG_GAMES = ['Cyberpunk 2077', 'Elden Ring', 'GTA V', 'Red Dead Redemption 2', 'Baldur\'s Gate 3', 'Call of Duty: Black Ops 6', 'Fortnite',
                        'Valorant', 'Counter-Strike 2', 'Minecraft', 'Apex Legends', 'Black Myth: Wukong', 'Starfield', 'Hogwarts Legacy', 'Forza Horizon 5'];

/** The plan built from the site: pillars and topics. Topics already in $old keep their status. */
function rocBlogPlanSeed(PDO $pdo, array $old = []): array {
    $S = rocBlogSite($pdo);
    $pillars = []; $topics = [];
    $add = function (string $pillar, string $stage, string $title, string $kw, array $products = [], array $sec = []) use (&$topics) {
        $id = substr(rocBlogSlug($pillar . '-' . $title), 0, 80);
        $topics[$id] = ['id' => $id, 'pillar' => $pillar, 'stage' => $stage, 'title' => $title, 'keyword' => strtolower($kw),
                        'secondary' => $sec, 'products' => $products, 'status' => 'planned'];
    };
    $year = gmdate('Y');
    foreach ($S['cats'] as $slug => $c) {
        [$one, $many] = rocBlogNouns($slug, $c['name']);
        $pid = 'cat-' . $slug;
        $pillars[] = ['id' => $pid, 'name' => $c['name'], 'url' => $c['url'], 'kind' => 'category'];
        $prods = array_values(array_filter($S['products'], function ($p) use ($slug) { return $p['cat'] === $slug; }));
        $pslugs = array_map(function ($p) { return basename(rtrim($p['url'], '/')); }, $prods);
        // Learn (TOFU)
        $add($pid, 'tofu', "How to choose a {$one}: a simple guide for gamers", "how to choose a {$one}", [], ["{$one} buying guide", "what to look for in a {$one}"]);
        $add($pid, 'tofu', ucfirst($one) . ' specs explained: what actually matters', "{$one} specs explained");
        $add($pid, 'tofu', ucfirst($one) . ' buying mistakes to avoid', "{$one} mistakes");
        $add($pid, 'tofu', "How long does a {$one} last, and when to upgrade?", "when to upgrade {$one}");
        foreach (rocBlogCatExtras($slug) as [$t, $kw]) $add($pid, 'tofu', $t, $kw);
        // Compare (MOFU): neighbouring products in the list, then use cases.
        for ($i = 0; $i + 1 < count($prods) && $i < 10; $i += 2) {
            $a = $prods[$i]; $b = $prods[$i + 1];
            $add($pid, 'mofu', "{$a['title']} vs {$b['title']}: which should you buy?", strtolower("{$a['title']} vs {$b['title']}"), [$pslugs[$i], $pslugs[$i + 1]]);
        }
        foreach (['competitive FPS games', 'a small desk', 'streaming and content creation'] as $use) {
            $add($pid, 'mofu', ucfirst($many) . " for {$use}: what to look for", "{$many} for {$use}", array_slice($pslugs, 0, 4));
        }
        // Buy (BOFU)
        $add($pid, 'bofu', "Best {$many} in {$year}: our picks", "best {$many} {$year}", array_slice($pslugs, 0, 6), ["best {$many}", "top {$many}"]);
        $add($pid, 'bofu', "Best budget {$many} that are still great", "best budget {$many}", array_slice($pslugs, -4));
        foreach (array_slice($prods, 0, 6) as $i => $p) {
            $add($pid, 'bofu', "{$p['title']} review: is it worth buying?", strtolower("{$p['title']} review"), [$pslugs[$i]]);
        }
    }
    // Game checker cluster.
    $pillars[] = ['id' => 'games', 'name' => 'Can I run it? Game requirements', 'url' => '/compatibility/', 'kind' => 'checker'];
    $gpus = array_values(array_map(function ($p) { return basename(rtrim($p['url'], '/')); }, array_filter($S['products'], function ($p) { return $p['cat'] === 'gpu'; })));
    $add('games', 'tofu', 'How to check if your PC can run a game', 'can my pc run it', [], ['check pc requirements for games']);
    $add('games', 'tofu', 'Minimum vs recommended requirements explained', 'minimum vs recommended requirements');
    $add('games', 'tofu', 'How to find your PC specs on Windows in 2 minutes', 'how to check pc specs');
    foreach (ROC_BLOG_GAMES as $g) $add('games', 'mofu', "Can I run {$g}? PC requirements and best settings", "can i run {$g}", array_slice($gpus, 0, 3), ["{$g} pc requirements", "{$g} system requirements"]);
    $add('games', 'bofu', "Best graphics card upgrades for 1080p and 1440p gaming", 'best gpu upgrade for 1440p', array_slice($gpus, 0, 5));
    // Platforms cluster.
    if ($S['platforms']) {
        $pillars[] = ['id' => 'platforms', 'name' => 'Gaming platforms', 'url' => '/gaming-platforms/', 'kind' => 'platforms'];
        $add('platforms', 'tofu', 'Which gaming platform should you choose?', 'which gaming platform to choose');
        $add('platforms', 'tofu', 'Handheld gaming PCs explained', 'handheld gaming pc explained');
        $add('platforms', 'mofu', 'PC vs console gaming: an honest comparison', 'pc vs console gaming');
        $add('platforms', 'mofu', 'Retro handhelds: what they can and cannot play', 'retro handhelds explained');
        $add('platforms', 'bofu', 'Best gaming accessories for a PC setup', 'best pc gaming accessories', []);
        $add('platforms', 'bofu', 'Best headsets and speakers for console gaming', 'best headset for console gaming', []);
    }
    // Keep what was already written, skipped or added by hand.
    $oldTopics = [];
    foreach ((array)($old['topics'] ?? []) as $t) if (!empty($t['id'])) $oldTopics[$t['id']] = $t;
    foreach ($oldTopics as $id => $t) {
        if (isset($topics[$id])) $topics[$id] = array_merge($topics[$id], array_intersect_key($t, array_flip(['status', 'post_id', 'slug', 'title', 'keyword', 'secondary', 'stage'])));
        elseif (($t['status'] ?? '') !== 'planned' || !empty($t['manual'])) $topics[$id] = $t;
    }
    return ['pillars' => $pillars, 'topics' => array_values($topics), 'built' => gmdate('c')];
}

function rocBlogPlan(PDO $pdo): array {
    $p = rocBlogGet($pdo, 'blog_plan', null);
    if (!is_array($p) || empty($p['topics'])) { $p = rocBlogPlanSeed($pdo); rocBlogSet($pdo, 'blog_plan', $p); }
    return $p;
}

/** Marks the posts already on the site against the plan (by matching keyword or title). */
function rocBlogPlanSync(PDO $pdo, array &$plan): void {
    $S = rocBlogSite($pdo);
    foreach ($plan['topics'] as &$t) {
        if (!empty($t['post_id']) && !isset($S['posts'][$t['post_id']])) { $t['status'] = 'planned'; unset($t['post_id'], $t['slug']); }
    }
    unset($t);
}

const ROC_CLUSTER_ROUND = 6;   // posts per cluster before moving to the next one (2 learn, 2 compare, 2 buy)

/**
 * Next topic. One cluster at a time: keep writing in the cluster that is under way until it has
 * ROC_CLUSTER_ROUND more posts, so its posts can link to each other quickly; then the cluster with
 * the fewest posts. Inside the cluster, the funnel stage that is furthest behind
 * (target mix 40% learn, 35% compare, 25% buy). Topics added by hand go first.
 */
function rocBlogNextTopic(PDO $pdo, array $plan): ?array {
    $open = array_values(array_filter($plan['topics'], function ($t) { return ($t['status'] ?? '') === 'planned'; }));
    if (!$open) return null;
    foreach ($open as $t) if (!empty($t['manual'])) return $t;
    $written = []; $byStage = [];
    foreach ($plan['topics'] as $t) {
        if (($t['status'] ?? '') !== 'written') continue;
        $written[$t['pillar']] = ($written[$t['pillar']] ?? 0) + 1;
        $byStage[$t['pillar']][$t['stage']] = ($byStage[$t['pillar']][$t['stage']] ?? 0) + 1;
    }
    $hasOpen = [];
    foreach ($open as $t) $hasOpen[$t['pillar']] = true;
    // The cluster under way: the most recent post's cluster, while its round is not full.
    $focus = null;
    $last = (string)($plan['last_pillar'] ?? '');
    if ($last !== '' && isset($hasOpen[$last]) && ($written[$last] ?? 0) % ROC_CLUSTER_ROUND !== 0) $focus = $last;
    if ($focus === null) {
        $ids = array_keys($hasOpen);
        usort($ids, function ($a, $b) use ($written) { return ($written[$a] ?? 0) <=> ($written[$b] ?? 0); });
        $focus = $ids[0];
    }
    $want = ['tofu' => 0.40, 'mofu' => 0.35, 'bofu' => 0.25];
    $done = $byStage[$focus] ?? [];
    $total = max(1, array_sum($done));
    $order = array_keys($want);
    usort($order, function ($a, $b) use ($done, $want, $total) { return (($done[$a] ?? 0) / $total - $want[$a]) <=> (($done[$b] ?? 0) / $total - $want[$b]); });
    foreach ($order as $stage) foreach ($open as $t) if ($t['pillar'] === $focus && $t['stage'] === $stage) return $t;
    return null;
}

/** Links a post on this topic must carry: up to its hub, down to products, across to sister posts. */
function rocBlogTopicLinks(PDO $pdo, array $plan, array $topic): array {
    $S = rocBlogSite($pdo);
    $pillar = null;
    foreach ($plan['pillars'] as $p) if ($p['id'] === $topic['pillar']) $pillar = $p;
    $must = []; $may = [];
    if ($pillar) $must[] = ['url' => $pillar['url'], 'text' => $pillar['name'], 'why' => 'hub page of this topic cluster: link it in the first two paragraphs'];
    foreach ((array)($topic['products'] ?? []) as $ps) if (isset($S['products'][$ps])) $must[] = ['url' => $S['products'][$ps]['url'], 'text' => $S['products'][$ps]['title'], 'why' => 'product this post is about'];
    // Sister posts already live in the same cluster.
    $sisters = [];
    foreach ($plan['topics'] as $t) {
        if ($t['pillar'] !== $topic['pillar'] || ($t['status'] ?? '') !== 'written' || empty($t['post_id'])) continue;
        $b = $S['posts'][$t['post_id']] ?? null;
        if ($b && $b['status'] === 'published') $sisters[] = ['url' => $b['url'], 'text' => $b['title'], 'why' => 'sister post in the same cluster (' . strtoupper($t['stage']) . ')'];
    }
    $must = array_merge($must, array_slice($sisters, 0, 2));
    $may = array_slice($sisters, 2, 4);
    // More products of this category when the topic names none.
    if ($pillar && $pillar['kind'] === 'category' && empty($topic['products'])) {
        $slug = substr($pillar['id'], 4); $n = 0;
        foreach ($S['products'] as $ps => $p) if ($p['cat'] === $slug && $n++ < 5) $may[] = ['url' => $p['url'], 'text' => $p['title'], 'why' => 'product in this category'];
    }
    if ($topic['pillar'] !== 'games') $may[] = ['url' => '/compatibility/', 'text' => 'Can I run it? game requirements checker', 'why' => 'tool'];
    foreach ($plan['pillars'] as $p) if ($p['id'] !== $topic['pillar']) $may[] = ['url' => $p['url'], 'text' => $p['name'], 'why' => 'another cluster hub (use one if it fits naturally)'];
    if ($topic['pillar'] === 'platforms') foreach (array_slice($S['platforms'], 0, 8) as $p) $may[] = ['url' => $p['url'], 'text' => $p['name'], 'why' => 'platform guide'];
    return ['must' => $must, 'may' => array_slice($may, 0, 14)];
}

/* ----------------------------------------------------------- post create */

/**
 * Checks and saves one article as "scheduled" for a free slot.
 * $in: title, focus_keyword, meta_title, meta_description, excerpt, category, content_html, image_alt?, publish_at, topic_id?
 * Returns ['ok' => true, ...] or ['ok' => false, 'errors' => [...]].
 */
function rocBlogCreate(PDO $pdo, string $root, array $in, string $source): array {
    rocBlogSite($pdo, true);
    $t = function ($k, $max) use ($in) { return trim(mb_substr((string)preg_replace('/\s+/u', ' ', strip_tags((string)($in[$k] ?? ''))), 0, $max)); };
    $title = $t('title', 220); $metaT = $t('meta_title', 255); $metaD = $t('meta_description', 500);
    $excerpt = $t('excerpt', 600); $category = $t('category', 100) ?: 'Guides'; $focus = $t('focus_keyword', 200);
    $alt = $t('image_alt', 300) ?: $title;
    $content = rocBlogCleanLinks(rocCmsSanitizeHtml((string)($in['content_html'] ?? '')), $pdo);
    $words = count(preg_split('/\s+/u', rocCmsTextFromHtml($content)) ?: []);

    $errors = [];
    if (mb_strlen($title) < 20) $errors['title'] = 'Title is too short.';
    if ($metaT === '' || mb_strlen($metaT) > 60) $errors['meta_title'] = 'Meta title must be 1-60 characters.';
    if (mb_strlen($metaD) < 70 || mb_strlen($metaD) > 160) $errors['meta_description'] = 'Meta description must be 70-160 characters.';
    if ($excerpt === '') $errors['excerpt'] = 'Excerpt is required.';
    if ($words < 600) $errors['content_html'] = "Content has {$words} words; at least 600 are needed.";

    $slug = rocBlogSlug((string)($in['slug'] ?? '') ?: $title);
    if ($slug === '') $errors['slug'] = 'Slug needs letters or numbers.';
    $st = $pdo->prepare('SELECT COUNT(*) FROM blogs WHERE slug = ? OR LOWER(title) = LOWER(?)');
    $st->execute([$slug, $title]);
    if ((int)$st->fetchColumn() > 0) $errors['title'] = 'A post with this title or address already exists. Choose another topic.';

    $free = rocBlogFreeSlots($pdo);
    try { $when = new DateTime((string)($in['publish_at'] ?? '')); $when->setTimezone(new DateTimeZone('UTC')); }
    catch (\Throwable $e) { $when = null; }
    $at = $when ? $when->format('Y-m-d H:i:s') : '';
    if (!isset($free[$at])) $errors['publish_at'] = 'Use one of the free slots: ' . implode(', ', array_map(function ($u) { return str_replace(' ', 'T', $u) . 'Z'; }, array_keys($free)));
    if ($errors) return ['ok' => false, 'errors' => $errors];

    // The topic from the plan: its cluster's "Keep reading" links go at the end.
    $plan = rocBlogPlan($pdo);
    $topicId = (string)($in['topic_id'] ?? '');
    $topic = null;
    foreach ($plan['topics'] as $tp) if ($tp['id'] === $topicId) $topic = $tp;
    if ($topic) $content = rocBlogWithRelated($pdo, $plan, $topic, $content, '');

    $image = rocBlogCover($root, $slug, $title, $category);
    $cols = rocBlogCols($pdo, 'blogs');
    $id = 'blog-' . bin2hex(random_bytes(4));
    $now = gmdate('Y-m-d H:i:s');
    $row = ['id' => $id, 'title' => $title, 'slug' => $slug, 'excerpt' => $excerpt, 'content' => $content, 'category' => $category,
            'author_name' => ROC_BLOG_AUTHOR, 'image' => $image, 'image_alt' => $alt, 'meta_title' => $metaT, 'meta_description' => $metaD,
            'focus_keyword' => $focus, 'read_time' => max(1, (int)ceil($words / 200)) . ' min read', 'status' => 'scheduled', 'version' => 1,
            'published_at' => $at, 'content_modified_at' => $now, 'created_at' => $now, 'updated_at' => $now,
            'og_title' => $metaT, 'og_description' => $metaD, 'og_image' => $image, 'og_type' => 'article', 'twitter_card' => 'summary_large_image',
            'twitter_title' => $metaT, 'twitter_description' => $metaD, 'twitter_image' => $image, 'is_noindex' => 0, 'is_nofollow' => 0];
    $row = array_filter($row, function ($k) use ($cols) { return in_array($k, $cols, true); }, ARRAY_FILTER_USE_KEY);
    $pdo->prepare('INSERT INTO blogs (' . implode(', ', array_keys($row)) . ') VALUES (' . implode(', ', array_fill(0, count($row), '?')) . ')')
        ->execute(array_values($row));

    if ($topic) {
        foreach ($plan['topics'] as &$tp) if ($tp['id'] === $topicId) { $tp['status'] = 'written'; $tp['post_id'] = $id; $tp['slug'] = $slug; unset($tp['manual']); }
        unset($tp);
        $plan['last_pillar'] = $topic['pillar'];
        rocBlogSet($pdo, 'blog_plan', $plan);
    }
    rocBlogLog($pdo, ['id' => $id, 'title' => $title, 'slug' => $slug, 'publish_at' => $at, 'words' => $words, 'by' => $source,
                      'stage' => $topic['stage'] ?? '', 'cluster' => $topic['pillar'] ?? '']);
    $local = (new DateTime($at . ' UTC'))->setTimezone(new DateTimeZone(ROC_BLOG_TZ));
    return ['ok' => true, 'id' => $id, 'slug' => $slug, 'url' => 'https://runonconsole.com/blogs/' . $slug . '/', 'image' => $image, 'words' => $words,
            'publish_at' => $at . ' UTC', 'publish_local' => $local->format('D j M, H:i') . ' Pakistan time',
            'message' => 'Scheduled. It goes live at ' . $local->format('H:i') . ' Pakistan time (the server publishes it, the PC can be off).'];
}

/** Internal links must point to real pages: a link to an unknown address becomes plain text. */
function rocBlogCleanLinks(string $html, PDO $pdo): string {
    $S = rocBlogSite($pdo);
    $ok = ['/' => 1, '/products/' => 1, '/blogs/' => 1, '/compatibility/' => 1, '/gaming-platforms/' => 1, '/about/' => 1, '/contact/' => 1,
           '/products/pc-hardware/' => 1, '/products/gaming-hardware/' => 1];
    foreach ($S['cats'] as $c) $ok[$c['url']] = 1;
    foreach ($S['products'] as $p) $ok[$p['url']] = 1;
    foreach ($S['platforms'] as $p) $ok[$p['url']] = 1;
    foreach ($S['posts'] as $b) if ($b['status'] === 'published') $ok[$b['url']] = 1;
    return (string)preg_replace_callback('#<a\b([^>]*)>(.*?)</a>#is', function ($m) use ($ok) {
        if (!preg_match('#href="([^"]*)"#i', $m[1], $h)) return $m[2];
        $u = html_entity_decode($h[1]);
        $u = preg_replace('#^https?://(www\.)?runonconsole\.com#i', '', $u);
        if ($u === '' || $u[0] !== '/') return $m[0];                    // external link: keep
        $path = (string)parse_url($u, PHP_URL_PATH);
        if ($path !== '' && substr($path, -1) !== '/' && strpos(basename($path), '.') === false) $path .= '/';
        return isset($ok[$path]) ? '<a href="' . htmlspecialchars($path, ENT_QUOTES) . '">' . $m[2] . '</a>' : $m[2];
    }, $html);
}

/** Puts (or refreshes) the "Keep reading" list of sister posts at the end of a post. */
function rocBlogWithRelated(PDO $pdo, array $plan, array $topic, string $content, string $selfSlug): string {
    $S = rocBlogSite($pdo);
    $content = (string)preg_replace('#\s*<h2>' . ROC_RELATED_H2 . '[^<]*</h2>\s*<ul>.*?</ul>\s*$#is', '', $content);
    $pillar = null;
    foreach ($plan['pillars'] as $p) if ($p['id'] === $topic['pillar']) $pillar = $p;
    $items = [];
    if ($pillar) $items[] = '<li><a href="' . rocLayerH($pillar['url']) . '">' . rocLayerH($pillar['name']) . '</a></li>';
    $sis = [];
    foreach ($plan['topics'] as $t) {
        if ($t['pillar'] !== $topic['pillar'] || empty($t['post_id']) || ($t['slug'] ?? '') === $selfSlug || $t['id'] === $topic['id']) continue;
        $b = $S['posts'][$t['post_id']] ?? null;
        if ($b && $b['status'] === 'published') $sis[] = $b;
    }
    usort($sis, function ($a, $b) { return strcmp((string)$b['published_at'], (string)$a['published_at']); });
    foreach (array_slice($sis, 0, 5) as $b) $items[] = '<li><a href="' . rocLayerH($b['url']) . '">' . rocLayerH($b['title']) . '</a></li>';
    if (count($items) < 2) return $content;
    return rtrim($content) . "\n<h2>" . ROC_RELATED_H2 . ': more on ' . rocLayerH($pillar ? $pillar['name'] : 'this topic') . "</h2>\n<ul>" . implode('', $items) . '</ul>';
}

/** After a post goes live: refresh "Keep reading" on it and on its sister posts, so the cluster links both ways. */
function rocBlogRelink(PDO $pdo, string $postId): int {
    rocBlogSite($pdo, true);
    $plan = rocBlogPlan($pdo);
    $topic = null;
    foreach ($plan['topics'] as $t) if (($t['post_id'] ?? '') === $postId) $topic = $t;
    if (!$topic) return 0;
    $n = 0;
    $get = $pdo->prepare('SELECT id, slug, content, status FROM blogs WHERE id = ?');
    $upd = $pdo->prepare('UPDATE blogs SET content = ?, updated_at = ? WHERE id = ?');
    foreach ($plan['topics'] as $t) {
        if ($t['pillar'] !== $topic['pillar'] || empty($t['post_id'])) continue;
        $get->execute([$t['post_id']]); $b = $get->fetch(PDO::FETCH_ASSOC);
        if (!$b || !in_array($b['status'], ['published', 'scheduled'], true)) continue;
        $new = rocBlogWithRelated($pdo, $plan, $t, (string)$b['content'], (string)$b['slug']);
        if ($new !== $b['content']) { $upd->execute([$new, gmdate('Y-m-d H:i:s'), $b['id']]); $n++; }
    }
    return $n;
}

/* Cover image: 1200 x 630 JPG with the title (falls back to a site photo). */
function rocBlogCover(string $root, string $slug, string $title, string $category): string {
    $fallback = '/images/hero_cod.jpg';
    if (!function_exists('imagettftext') || !function_exists('imagejpeg')) return $fallback;
    $font = null;
    foreach (array_merge(['/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf', '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
                          '/usr/share/fonts/liberation/LiberationSans-Bold.ttf', '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
                          'C:/Windows/Fonts/segoeuib.ttf'], glob('/usr/share/fonts/*/*Bold.ttf') ?: [], glob('/usr/share/fonts/*/*/*Bold.ttf') ?: []) as $f) {
        if (is_file($f)) { $font = $f; break; }
    }
    if (!$font) return $fallback;
    $W = 1200; $H = 630;
    $im = imagecreatetruecolor($W, $H);
    for ($y = 0; $y < $H; $y++) for ($x = 0; $x < $W; $x += 6) {
        $k = ($x / $W + $y / $H) / 2;
        imagefilledrectangle($im, $x, $y, $x + 5, $y, imagecolorallocate($im, (int)(2 + 13 * $k), (int)(44 - 21 * $k), (int)(34 + 8 * $k)));
    }
    $green = imagecolorallocate($im, 52, 211, 153); $white = imagecolorallocate($im, 248, 250, 252); $soft = imagecolorallocate($im, 167, 243, 208);
    imagefilledrectangle($im, 80, 96, 86, 534, $green);
    imagettftext($im, 22, 0, 116, 140, $green, $font, strtoupper(mb_substr($category, 0, 40)));
    for ($size = 56; $size >= 30; $size -= 2) {
        $lines = []; $cur = '';
        foreach (preg_split('/\s+/', $title) as $w) {
            $try = $cur === '' ? $w : "$cur $w";
            $b = imagettfbbox($size, 0, $font, $try);
            if ($cur !== '' && $b[2] - $b[0] > 1000) { $lines[] = $cur; $cur = $w; } else $cur = $try;
        }
        if ($cur !== '') $lines[] = $cur;
        if (count($lines) <= 4) break;
    }
    $lines = array_slice($lines, 0, 4);
    foreach ($lines as $i => $l) imagettftext($im, $size, 0, 116, 220 + $i * (int)($size * 1.35), $white, $font, $l);
    imagettftext($im, 20, 0, 116, 560, $soft, $font, 'runonconsole.com');
    $dir = $root . '/uploads/blog-covers';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $ok = imagejpeg($im, $dir . '/' . $slug . '.jpg', 84);
    imagedestroy($im);
    return $ok ? '/uploads/blog-covers/' . $slug . '.jpg' : $fallback;
}

/* ----------------------------------------------------------- the brief */

/** What any writer (Gemini on the server or Claude on the PC) gets for the next post. */
function rocBlogBrief(PDO $pdo, ?array $topic = null): ?array {
    rocBlogSite($pdo, true);
    $plan = rocBlogPlan($pdo);
    $topic = $topic ?: rocBlogNextTopic($pdo, $plan);
    if (!$topic) return null;
    $pillarName = '';
    foreach ($plan['pillars'] as $p) if ($p['id'] === $topic['pillar']) $pillarName = $p['name'];
    $intent = ['tofu' => 'TOFU (top of funnel): the reader is learning. Explain clearly, answer the question fully, define terms, give practical tips. Mention products only as examples and point to the hub page for picks.',
               'mofu' => 'MOFU (middle of funnel): the reader is comparing options. Compare honestly (strengths, weaknesses, who each is for), use a comparison table, end with "which one should you pick" by type of gamer.',
               'bofu' => 'BOFU (bottom of funnel): the reader is ready to buy. Give clear picks with who each is for, key specs from the product pages, pros and cons, and a clear call to check the product page. No prices.'][$topic['stage']];
    $recent = array_slice(array_values(array_map(function ($b) { return $b['title']; }, rocBlogSite($pdo)['posts'])), 0, 60);
    return ['topic_id' => $topic['id'], 'stage' => $topic['stage'], 'cluster' => $pillarName, 'title_idea' => $topic['title'],
            'focus_keyword' => $topic['keyword'], 'secondary_keywords' => (array)($topic['secondary'] ?? []), 'intent' => $intent,
            'links' => rocBlogTopicLinks($pdo, $plan, $topic), 'avoid_titles' => $recent,
            'categories' => ['Buying Guides', 'Can I Run It', 'How To', 'Platforms', 'Comparisons']];
}

/** The writing instructions (same rules for both writers). */
function rocBlogPrompt(array $brief, string $publishAt): string {
    $links = function ($l) { return implode("\n", array_map(function ($x) { return "- {$x['url']}  ({$x['text']}; {$x['why']})"; }, $l)); };
    return "You write for runonconsole.com (ROC, Run On Console): an independent gaming site with gaming gear picks, a \"Can I run it\" PC game requirements checker and guides to gaming platforms. Readers are gamers. Write ONE blog post.\n\n"
        . "TOPIC CLUSTER: {$brief['cluster']}\n"
        . "FUNNEL STAGE: {$brief['intent']}\n"
        . "TITLE IDEA: {$brief['title_idea']} (you may improve it; keep the focus keyword)\n"
        . "FOCUS KEYWORD: {$brief['focus_keyword']}\n"
        . ($brief['secondary_keywords'] ? 'SECONDARY KEYWORDS (use naturally): ' . implode(', ', $brief['secondary_keywords']) . "\n" : '')
        . "\nINTERNAL LINKS YOU MUST USE (each once, with descriptive anchor text, never \"click here\"):\n" . $links($brief['links']['must'])
        . "\n\nINTERNAL LINKS YOU MAY USE (1-3 of them, only where they really help):\n" . $links($brief['links']['may'])
        . "\nUse NO other internal addresses. External links only to official sources (game publishers, manufacturers) and only if essential.\n\n"
        . "RULES\n"
        . "- 1100-1600 words of genuinely useful, accurate content in clear natural English. No filler, no keyword stuffing.\n"
        . "- Focus keyword in the title, in the first 100 words, in one <h2> and in the meta description.\n"
        . "- Start with a 2-3 sentence intro that answers the search intent straight away. Link the hub page in the first two paragraphs.\n"
        . "- Sections with <h2>; <h3> only inside an <h2> section. Never <h1>. Every <h2> text must be different.\n"
        . "- Include a short comparison <table> when comparing things. End with an <h2>FAQ</h2> of 3-4 <h3> questions with <p> answers (real questions gamers search), then a short conclusion <h2>.\n"
        . "- No prices or discounts (say \"check the current price on the product page\"). No invented benchmarks or test results; never say \"we tested\". If unsure of a spec, leave it out.\n"
        . "- Allowed HTML only: p, h2, h3, ul, ol, li, strong, em, a, table, thead, tbody, tr, th, td, blockquote.\n"
        . "- Do not reuse any of these existing titles or near-duplicates: " . implode(' | ', array_slice($brief['avoid_titles'], 0, 40)) . "\n\n"
        . "Answer with ONLY a JSON object with exactly these keys:\n"
        . "{\"title\": \"30-70 characters, with the focus keyword\", \"focus_keyword\": \"...\", \"meta_title\": \"max 60 characters\", "
        . "\"meta_description\": \"120-155 characters with the keyword and a clear benefit\", \"excerpt\": \"1-2 sentences\", "
        . "\"category\": \"one of: " . implode(', ', $brief['categories']) . "\", \"content_html\": \"the article HTML\", \"image_alt\": \"short description of the cover\", "
        . "\"topic_id\": \"{$brief['topic_id']}\", \"publish_at\": \"{$publishAt}\"}\n";
}

/* --------------------------------------------------------- Gemini writer */

function rocGeminiCall(string $key, string $method, string $path, ?array $body = null, int $timeout = 170): array {
    $c = curl_init((getenv('ROC_GEMINI_TEST_BASE') ?: ROC_GEMINI_API) . $path);   // env: local tests only
    $h = ['x-goog-api-key: ' . $key, 'Content-Type: application/json'];
    curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 15, CURLOPT_HTTPHEADER => $h,
                           CURLOPT_CUSTOMREQUEST => $method]);
    if ($body !== null) curl_setopt($c, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    $raw = (string)curl_exec($c);
    $code = (int)curl_getinfo($c, CURLINFO_HTTP_CODE);
    $err = curl_error($c);
    curl_close($c);
    $j = json_decode($raw, true);
    if ($code !== 200) return ['ok' => false, 'code' => $code, 'error' => (string)($j['error']['message'] ?? ($err ?: "HTTP {$code}"))];
    return ['ok' => true, 'data' => $j];
}

/** A free "flash" model that this key can use (when the saved one has gone). */
function rocGeminiPickModel(string $key): ?string {
    $r = rocGeminiCall($key, 'GET', '/models?pageSize=200', null, 20);
    if (!$r['ok']) return null;
    $best = null;
    foreach ((array)($r['data']['models'] ?? []) as $m) {
        $n = substr((string)($m['name'] ?? ''), 7);
        if (!in_array('generateContent', (array)($m['supportedGenerationMethods'] ?? []), true)) continue;
        if (!preg_match('/^gemini-[\d.]+-flash(-latest)?$/', $n)) continue;
        if ($best === null || version_compare(preg_replace('/\D+([\d.]+).*/', '$1', $n), preg_replace('/\D+([\d.]+).*/', '$1', $best), '>')) $best = $n;
    }
    return $best;
}

/** Writes the article JSON with Gemini. */
function rocGeminiWrite(PDO $pdo, string $key, string $prompt, ?string &$used = null): array {
    $model = (string)rocBlogGet($pdo, 'blog_gemini_model', ROC_GEMINI_MODEL) ?: ROC_GEMINI_MODEL;
    $body = ['contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
             'generationConfig' => ['responseMimeType' => 'application/json', 'temperature' => 0.8, 'maxOutputTokens' => 16000]];
    $r = rocGeminiCall($key, 'POST', '/models/' . rawurlencode($model) . ':generateContent', $body);
    if (!$r['ok'] && in_array($r['code'], [400, 404], true) && stripos($r['error'], 'model') !== false) {
        $alt = rocGeminiPickModel($key);
        if ($alt && $alt !== $model) {
            $model = $alt; rocBlogSet($pdo, 'blog_gemini_model', $alt);
            $r = rocGeminiCall($key, 'POST', '/models/' . rawurlencode($model) . ':generateContent', $body);
        }
    }
    $used = $model;
    if (!$r['ok']) return ['ok' => false, 'error' => 'Gemini: ' . $r['error']];
    $text = '';
    foreach ((array)($r['data']['candidates'][0]['content']['parts'] ?? []) as $p) if (empty($p['thought'])) $text .= (string)($p['text'] ?? '');
    $text = trim(preg_replace('/^```(?:json)?\s*|\s*```$/', '', trim($text)));
    $a = json_decode($text, true);
    if (!is_array($a)) return ['ok' => false, 'error' => 'Gemini did not answer with an article (finish: ' . (string)($r['data']['candidates'][0]['finishReason'] ?? '?') . ').'];
    return ['ok' => true, 'article' => $a];
}

/**
 * Server writer, run by the scheduler cron every 10 minutes. Writes the next post when the
 * next free slot is less than 2.5 hours away. One try every 30 minutes at most.
 */
function rocBlogServerTick(PDO $pdo, string $root, bool $force = false): ?string {
    if (!$force && rocBlogEngine($pdo) !== 'server') return null;
    $key = trim((string)rocBlogGet($pdo, 'blog_gemini_key', ''));
    if ($key === '') return 'No Gemini key saved (CMS > Blog agent).';
    $state = rocBlogGet($pdo, 'blog_engine_state', []);
    $state = is_array($state) ? $state : [];
    if (!$force && !empty($state['last_try']) && time() - strtotime($state['last_try'] . ' UTC') < 1800) return null;
    $free = rocBlogFreeSlots($pdo, 2);
    if (!$free) return null;
    $slot = array_key_first($free);
    if (!$force && strtotime($slot . ' UTC') - time() > 9000) return null;     // not yet
    $brief = rocBlogBrief($pdo);
    if (!$brief) return 'The content plan has no topics left. Add topics or press "Refresh plan" in CMS > Blog agent.';
    $state['last_try'] = gmdate('Y-m-d H:i:s');
    rocBlogSet($pdo, 'blog_engine_state', $state);
    $at = str_replace(' ', 'T', $slot) . 'Z';
    $prompt = rocBlogPrompt($brief, $at);
    $msg = null;
    for ($try = 1; $try <= 2; $try++) {
        $w = rocGeminiWrite($pdo, $key, $prompt, $model);
        if (!$w['ok']) { $msg = $w['error']; continue; }
        $a = $w['article'];
        $a['publish_at'] = $at; $a['topic_id'] = $brief['topic_id'];
        if (mb_strlen((string)($a['meta_title'] ?? '')) > 60) $a['meta_title'] = rtrim(mb_substr((string)$a['meta_title'], 0, 57)) . '…';
        if (mb_strlen((string)($a['meta_description'] ?? '')) > 160) $a['meta_description'] = rtrim(mb_substr((string)$a['meta_description'], 0, 157)) . '…';
        $r = rocBlogCreate($pdo, $root, $a, 'server (' . $model . ')');
        if ($r['ok']) {
            $state['last_ok'] = gmdate('Y-m-d H:i:s'); $state['last_error'] = '';
            rocBlogSet($pdo, 'blog_engine_state', $state);
            return 'Written: ' . $r['url'] . ' (' . $r['publish_local'] . ')';
        }
        $msg = 'Article not accepted: ' . implode(' ', $r['errors']);
        $prompt .= "\n\nYour previous answer was rejected: " . implode(' ', $r['errors']) . ' Fix this.';
    }
    $state['last_error'] = gmdate('Y-m-d H:i') . ' UTC: ' . $msg;
    rocBlogSet($pdo, 'blog_engine_state', $state);
    return $msg;
}
