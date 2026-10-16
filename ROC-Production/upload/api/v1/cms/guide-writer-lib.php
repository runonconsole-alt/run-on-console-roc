<?php
/**
 * Run On Console — the guide writer (CMS > Updates writer > Guides, Deploy 73).
 *
 * Owner, 2026-10-16: helpful guides this week, 2 a day on 16, 17 and 18 October; from Monday 19 October
 * only news is written by itself (blogs and guides by the owner). The scheduler cron calls rocGuideTick():
 * between 10:00 and 23:30 Pakistan time, at least 2 hours apart, until the day's count is reached, it writes
 * the next topic of ROC_GUIDE_TOPICS with the blog writer's AI key and publishes it right away at
 * /guides/{slug}/ (category "Guides: <type>", topic group and category, tags, an AI cover, the
 * "Want to buy?" boxes to our products). Settings in cms_settings.guide_writer: {on, per_day, until}.
 */
if (defined('ROC_GUIDE_WRITER_LIB')) return;
define('ROC_GUIDE_WRITER_LIB', 1);
require_once __DIR__ . '/blog-engine-lib.php';
require_once __DIR__ . '/topics-lib.php';

const ROC_GUIDE_DEFAULTS = ['on' => true, 'per_day' => 2, 'until' => '2026-10-19'];
const ROC_GUIDE_GAP = 7200;   // seconds between two guides

/** [key, title idea, type, hardware group, product category, focus keyword]. */
const ROC_GUIDE_TOPICS = [
    ['setup-gaming-monitor', 'How to set up a gaming monitor for the best picture and lowest input lag', 'Setup', 'pc-hardware', 'monitors', 'gaming monitor settings'],
    ['choose-gaming-mouse', 'How to choose a gaming mouse: grip, weight, sensor and size explained', 'Buying', 'gaming-hardware', 'mice', 'how to choose a gaming mouse'],
    ['install-graphics-card', 'How to install a new graphics card in your PC, step by step', 'Setup', 'pc-hardware', 'gpu', 'how to install a graphics card'],
    ['enable-xmp-expo', 'How to turn on XMP or EXPO so your RAM runs at full speed', 'How to use', 'pc-hardware', 'ram', 'how to enable xmp'],
    ['cs2-settings', 'Best Counter-Strike 2 settings for high FPS on a mid-range PC', 'Game setup', 'pc-hardware', 'gpu', 'cs2 best settings'],
    ['clean-mechanical-keyboard', 'How to clean a mechanical keyboard without damaging it', 'How to use', 'gaming-hardware', 'keyboards', 'how to clean a mechanical keyboard'],
    ['choose-gaming-headset', 'How to choose a gaming headset: wired vs wireless, sound and mic', 'Buying', 'gaming-hardware', 'audio', 'how to choose a gaming headset'],
    ['setup-gaming-chair', 'How to adjust a gaming chair for good posture during long sessions', 'How to use', 'gaming-hardware', 'chairs', 'gaming chair setup'],
    ['valorant-settings', 'Best Valorant settings for more FPS and clearer visuals', 'Game setup', 'pc-hardware', 'gpu', 'valorant best settings'],
    ['buy-graphics-card', 'How to buy the right graphics card for your PC and budget', 'Buying', 'pc-hardware', 'gpu', 'which graphics card to buy'],
    ['setup-handheld-pc', 'How to set up a new handheld gaming PC (Steam Deck, ROG Ally, Legion Go)', 'Setup', 'gaming-hardware', 'handhelds', 'handheld gaming pc setup'],
    ['gaming-laptop-performance', 'How to get more performance from a gaming laptop: power, cooling and settings', 'How to use', 'gaming-hardware', 'laptops', 'gaming laptop performance tips'],
];

function rocGuideSettings(PDO $pdo): array {
    $s = rocBlogGet($pdo, 'guide_writer', []);
    $s = is_array($s) ? $s : [];
    return ['on' => array_key_exists('on', $s) ? !empty($s['on']) : ROC_GUIDE_DEFAULTS['on'],
            'per_day' => max(0, min(6, (int)($s['per_day'] ?? ROC_GUIDE_DEFAULTS['per_day']))),
            'until' => (string)($s['until'] ?? ROC_GUIDE_DEFAULTS['until'])];
}

function rocGuideLog(PDO $pdo): array { $l = rocBlogGet($pdo, 'guide_log', []); return is_array($l) ? $l : []; }

/** The next topic that is not written yet (by key, or a post with the same title). */
function rocGuideNext(PDO $pdo): ?array {
    $done = array_column(rocGuideLog($pdo), 'key');
    $titles = array_map('strtolower', $pdo->query('SELECT title FROM blogs')->fetchAll(PDO::FETCH_COLUMN));
    foreach (ROC_GUIDE_TOPICS as $t) if (!in_array($t[0], $done, true) && !in_array(strtolower($t[1]), $titles, true)) return $t;
    return null;
}

/** Why the writer waits (null = it may write now). */
function rocGuideWait(PDO $pdo): ?string {
    $s = rocGuideSettings($pdo);
    $now = new DateTime('now', new DateTimeZone(ROC_BLOG_TZ));
    $day = $now->format('Y-m-d');
    if (!$s['on']) return 'off';
    if ($s['until'] !== '' && $day >= $s['until']) return 'ended';
    $today = array_filter(rocGuideLog($pdo), function ($l) use ($day) { return ($l['day'] ?? '') === $day; });
    if (count($today) >= $s['per_day']) return 'done today';
    $hm = $now->format('H:i');
    if ($hm < '10:00' || $hm > '23:30') return 'outside 10:00-23:30';
    $last = rocGuideLog($pdo)[0]['at'] ?? '';
    if ($last !== '' && time() - strtotime($last) < ROC_GUIDE_GAP) return 'next one later';
    return null;
}

/** Writes and publishes one guide. Returns ['ok' => true, 'url' => ...] or ['ok' => false, 'error' => ...]. */
function rocGuideWrite(PDO $pdo, string $root, ?array $topic = null): array {
    $topic = $topic ?: rocGuideNext($pdo);
    if (!$topic) return ['ok' => false, 'error' => 'All planned guide topics are written.'];
    if (!rocBlogWriters($pdo)) return ['ok' => false, 'error' => 'No AI key saved (CMS > Updates writer > Blog).'];
    [$key, $idea, $type, $group, $cat, $kw] = $topic;
    $S = rocBlogSite($pdo, true);
    $hub = $S['cats'][$cat] ?? null;
    $links = [];
    if ($hub) $links[] = "- {$hub['url']}  ({$hub['name']}: link it in the first two paragraphs)";
    $n = 0;
    foreach ($S['products'] as $p) if ($p['cat'] === $cat && $n++ < 5) $links[] = "- {$p['url']}  ({$p['title']}: product page, link where it helps the reader)";
    $links[] = '- /compatibility/  (Can I run it? game checker)';
    $links[] = '- /can-i-run/  (PC requirements of 2,000+ games)';
    $prompt = "You write for runonconsole.com (Run On Console), a gaming and PC hardware site. Write ONE practical, genuinely helpful step by step GUIDE.\n\n"
        . "GUIDE TYPE: {$type}\nTITLE IDEA: {$idea}\nFOCUS KEYWORD: {$kw}\n\nINTERNAL LINKS (use 3-5 of them, each once, descriptive anchor text, never \"click here\"):\n" . implode("\n", $links) . "\n\n"
        . "RULES\n- At least 1,100 words (aim for 1,300-1,600), clear simple English for gamers, no filler.\n"
        . "- Start with 2-3 sentences that say what the reader will achieve. Then a short \"What you need\" list.\n"
        . "- The steps as <h2> sections (\"Step 1: ...\") or an <ol> with a short explanation for each step; add tips and common mistakes.\n"
        . "- No prices, no invented benchmarks or test results, never \"we tested\". If unsure of a detail, leave it out.\n"
        . "- End with <h2>FAQ</h2> (3 <h3> questions with <p> answers) and a short conclusion <h2>.\n"
        . "- Allowed HTML: p, h2, h3, ul, ol, li, strong, em, a, table, thead, tbody, tr, th, td, blockquote. Never h1.\n\n"
        . "Answer with ONLY a JSON object: {\"title\": \"40-70 characters with the focus keyword\", \"meta_title\": \"max 60 characters\", "
        . "\"meta_description\": \"120-155 characters\", \"excerpt\": \"1-2 sentences\", \"content_html\": \"the guide HTML\", \"image_alt\": \"short description of the cover\", "
        . "\"image_prompt\": \"one sentence: a realistic photo for this guide, the gear or the setup, no text, no logos, no faces\", \"tags\": [\"4 to 6 short search words\"]}";
    $used = null;
    $w = rocBlogWriteAny($pdo, $prompt, $used);
    if (!$w['ok']) return ['ok' => false, 'error' => $w['error']];
    $a = $w['article'];
    $title = trim(mb_substr(strip_tags((string)($a['title'] ?? '')), 0, 200)) ?: $idea;
    $content = rocBlogCleanLinks(rocCmsSanitizeHtml((string)($a['content_html'] ?? '')), $pdo);
    $words = count(preg_split('/\s+/u', rocCmsTextFromHtml($content)) ?: []);
    if ($words < 900) return ['ok' => false, 'error' => "The guide was too short ({$words} words)."];
    $content = rocBlogBuyBoxes($pdo, $content, ['pillar' => 'cat-' . $cat]);
    $slug = rocBlogSlug($title);
    $st = $pdo->prepare('SELECT COUNT(*) FROM blogs WHERE slug = ?'); $st->execute([$slug]);
    if ((int)$st->fetchColumn() > 0) $slug .= '-' . substr(bin2hex(random_bytes(2)), 0, 3);
    $metaT = trim(mb_substr(strip_tags((string)($a['meta_title'] ?? $title)), 0, 60));
    $metaD = trim(strip_tags((string)($a['meta_description'] ?? '')));
    if (mb_strlen($metaD) > 160) $metaD = rtrim(mb_substr($metaD, 0, 157)) . '…';
    $alt = trim(mb_substr(strip_tags((string)($a['image_alt'] ?? '')), 0, 300)) ?: $title;
    $image = rocBlogMakeCover($pdo, $root, $slug, $title, 'Guides', null, (string)($a['image_prompt'] ?? ''), $alt);
    $tags = implode(', ', rocTopicTags(implode(',', is_array($a['tags'] ?? null) ? $a['tags'] : [])));
    $id = 'blog-' . bin2hex(random_bytes(4));
    $now = gmdate('Y-m-d H:i:s');
    $row = ['id' => $id, 'title' => $title, 'slug' => $slug, 'excerpt' => trim(mb_substr(strip_tags((string)($a['excerpt'] ?? '')), 0, 600)), 'content' => $content,
            'category' => 'Guides: ' . $type, 'author_name' => ROC_BLOG_AUTHOR, 'image' => $image, 'image_alt' => $alt, 'meta_title' => $metaT, 'meta_description' => $metaD,
            'focus_keyword' => $kw, 'read_time' => max(1, (int)ceil($words / 200)) . ' min read', 'status' => 'published', 'version' => 1,
            'published_at' => $now, 'content_modified_at' => $now, 'created_at' => $now, 'updated_at' => $now,
            'og_title' => $metaT, 'og_description' => $metaD, 'og_image' => $image, 'og_type' => 'article', 'twitter_card' => 'summary_large_image',
            'twitter_title' => $metaT, 'twitter_description' => $metaD, 'twitter_image' => $image, 'is_noindex' => 0, 'is_nofollow' => 0,
            'topic_group' => $group, 'topic_cat' => $cat, 'tags' => $tags];
    $cols = rocBlogCols($pdo, 'blogs');
    $row = array_filter($row, function ($k) use ($cols) { return in_array($k, $cols, true); }, ARRAY_FILTER_USE_KEY);
    $pdo->prepare('INSERT INTO blogs (' . implode(', ', array_keys($row)) . ') VALUES (' . implode(', ', array_fill(0, count($row), '?')) . ')')->execute(array_values($row));
    try { $pdo->prepare("INSERT INTO cms_cache_queue (content_type, content_id, target_version_id, action, status, created_at) VALUES ('blog', ?, 1, 'purge_and_prerender', 'pending', ?)")->execute([$id, $now]); } catch (\Throwable $e) {}
    $log = rocGuideLog($pdo);
    array_unshift($log, ['key' => $key, 'id' => $id, 'title' => $title, 'slug' => $slug, 'type' => $type, 'words' => $words, 'by' => (string)$used, 'at' => $now,
                         'day' => (new DateTime('now', new DateTimeZone(ROC_BLOG_TZ)))->format('Y-m-d')]);
    rocBlogSet($pdo, 'guide_log', array_slice($log, 0, 100));
    return ['ok' => true, 'id' => $id, 'title' => $title, 'url' => 'https://runonconsole.com/guides/' . $slug . '/', 'words' => $words];
}

/** Scheduler cron: one guide when it is time (see rocGuideWait). */
function rocGuideTick(PDO $pdo, string $root): ?string {
    if (rocGuideWait($pdo) !== null) return null;
    if (!rocGuideNext($pdo)) return null;
    $st = rocBlogGet($pdo, 'guide_state', []); $st = is_array($st) ? $st : [];
    if (!empty($st['last_try']) && time() - strtotime($st['last_try'] . ' UTC') < 1800) return null;   // a failed try waits 30 minutes
    $st['last_try'] = gmdate('Y-m-d H:i:s');
    $r = rocGuideWrite($pdo, $root);
    $st['last_error'] = $r['ok'] ? '' : gmdate('Y-m-d H:i') . ' UTC: ' . $r['error'];
    rocBlogSet($pdo, 'guide_state', $st);
    return $r['ok'] ? 'Guide published: ' . $r['url'] . ' (' . $r['words'] . ' words)' : 'Guide failed: ' . $r['error'];
}
