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
 *
 * Owner, 2026-10-27 (Deploy 126): from the week of Monday 2 November, one guide on Tuesday, Friday and Saturday
 * every week, live at exactly 12:00 Pakistan time. The writer starts at 09:30 and saves the guide as scheduled for
 * 12:00 (the scheduler publishes it, emails the alert and links it inside its cluster); a guide written after
 * 12:00 (a failed try earlier) goes live at once, until 20:00. Topics: first a real "how to" search from Google
 * Search Console that no post answers yet (gsc-lib.php), then ROC_GUIDE_TOPICS in order.
 */
if (defined('ROC_GUIDE_WRITER_LIB')) return;
define('ROC_GUIDE_WRITER_LIB', 1);
require_once __DIR__ . '/blog-engine-lib.php';
require_once __DIR__ . '/topics-lib.php';

const ROC_GUIDE_DEFAULTS = ['on' => true, 'per_day' => 2, 'until' => '2026-10-19'];
const ROC_GUIDE_GAP = 7200;   // seconds between two guides
const ROC_GUIDE_FROM = '2026-11-02';
const ROC_GUIDE_DAYS = [2, 5, 6];     // 1 = Monday ... 7 = Sunday: Tuesday, Friday, Saturday
const ROC_GUIDE_TIME = '12:00';

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
    // Deploy 126: the topics for November onward (3 a week).
    ['check-pc-specs', 'How to check your PC specs: graphics card, processor, RAM and storage', 'How to use', 'pc-hardware', 'gpu', 'how to check pc specs'],
    ['update-gpu-drivers', 'How to update your graphics card drivers the safe way (NVIDIA, AMD, Intel)', 'How to use', 'pc-hardware', 'gpu', 'how to update graphics drivers'],
    ['choose-gaming-monitor', 'How to choose a gaming monitor: size, resolution, refresh rate and panel type', 'Buying', 'pc-hardware', 'monitors', 'how to choose a gaming monitor'],
    ['enable-gsync-freesync', 'How to turn on G-Sync or FreeSync and stop screen tearing', 'Setup', 'pc-hardware', 'monitors', 'how to enable g-sync freesync'],
    ['install-cpu', 'How to install a CPU and cooler without bending pins or skipping thermal paste', 'Setup', 'pc-hardware', 'cpu', 'how to install a cpu'],
    ['choose-gaming-cpu', 'How to choose a gaming CPU: cores, clock speed, cache and platform', 'Buying', 'pc-hardware', 'cpu', 'how to choose a cpu for gaming'],
    ['install-ram', 'How to install RAM and check it runs in dual channel', 'Setup', 'pc-hardware', 'ram', 'how to install ram'],
    ['how-much-ram-gaming', 'How much RAM you need for gaming: 16 GB vs 32 GB explained', 'Buying', 'pc-hardware', 'ram', 'how much ram for gaming'],
    ['choose-pc-case', 'How to choose a PC case: size, airflow, GPU clearance and cable space', 'Buying', 'pc-hardware', 'cases', 'how to choose a pc case'],
    ['case-airflow', 'How to set up case fans for better airflow and lower temperatures', 'Setup', 'pc-hardware', 'cases', 'pc case fan setup'],
    ['cable-management', 'How to do clean cable management in a gaming PC', 'Setup', 'pc-hardware', 'cases', 'pc cable management'],
    ['mouse-dpi-sensitivity', 'How to find the right mouse DPI and in-game sensitivity', 'How to use', 'gaming-hardware', 'mice', 'best mouse dpi for gaming'],
    ['clean-gaming-mouse', 'How to clean a gaming mouse, its feet and the sensor', 'How to use', 'gaming-hardware', 'mice', 'how to clean a gaming mouse'],
    ['choose-mechanical-keyboard', 'How to choose a mechanical keyboard: switches, size and keycaps', 'Buying', 'gaming-hardware', 'keyboards', 'how to choose a mechanical keyboard'],
    ['keyboard-switches', 'Linear vs tactile vs clicky switches: which keyboard switch is best for gaming', 'Buying', 'gaming-hardware', 'keyboards', 'best keyboard switches for gaming'],
    ['headset-mic-setup', 'How to set up a headset microphone in Windows and Discord so you sound clear', 'Setup', 'gaming-hardware', 'audio', 'headset mic setup'],
    ['spatial-audio', 'How to turn on spatial audio for gaming on PC and console', 'Setup', 'gaming-hardware', 'audio', 'how to enable spatial audio'],
    ['choose-pc-speakers', 'How to choose PC speakers for gaming: 2.0, 2.1 and soundbars', 'Buying', 'gaming-hardware', 'speakers', 'how to choose pc speakers'],
    ['speaker-placement', 'How to place desktop speakers for the best sound', 'Setup', 'gaming-hardware', 'speakers', 'desktop speaker placement'],
    ['choose-gaming-chair', 'How to choose a gaming chair: size, weight limit, lumbar support and materials', 'Buying', 'gaming-hardware', 'chairs', 'how to choose a gaming chair'],
    ['choose-gaming-laptop', 'How to choose a gaming laptop: GPU, cooling, screen and battery', 'Buying', 'gaming-hardware', 'laptops', 'how to choose a gaming laptop'],
    ['laptop-cooling', 'How to keep a gaming laptop cool: stands, fans and settings', 'How to use', 'gaming-hardware', 'laptops', 'how to keep gaming laptop cool'],
    ['handheld-battery', 'How to get longer battery life on a handheld gaming PC', 'How to use', 'gaming-hardware', 'handhelds', 'handheld pc battery life'],
    ['handheld-settings', 'Best settings for a handheld gaming PC: TDP, frame rate limit and resolution', 'Game setup', 'gaming-hardware', 'handhelds', 'steam deck best settings'],
    ['windows-gaming', 'How to set up Windows for gaming: Game Mode, power plan and background apps', 'Setup', 'pc-hardware', 'gpu', 'optimize windows for gaming'],
    ['fps-counter', 'How to show an FPS counter in any game (Steam, NVIDIA, AMD, Xbox Game Bar)', 'How to use', 'pc-hardware', 'gpu', 'how to show fps in games'],
    ['gpu-temps', 'How to check GPU and CPU temperatures and what is too hot', 'How to use', 'pc-hardware', 'gpu', 'how to check gpu temperature'],
    ['bottleneck', 'How to find out if your CPU or GPU is a bottleneck', 'How to use', 'pc-hardware', 'cpu', 'cpu gpu bottleneck'],
    ['fortnite-settings', 'Best Fortnite settings for high FPS on a mid-range PC', 'Game setup', 'pc-hardware', 'gpu', 'fortnite best settings'],
    ['apex-settings', 'Best Apex Legends settings for more FPS and better visibility', 'Game setup', 'pc-hardware', 'gpu', 'apex legends best settings'],
    ['warzone-settings', 'Best Call of Duty Warzone settings for high FPS', 'Game setup', 'pc-hardware', 'gpu', 'warzone best settings'],
    ['cyberpunk-settings', 'Best Cyberpunk 2077 settings for a smooth game on mid-range GPUs', 'Game setup', 'pc-hardware', 'gpu', 'cyberpunk 2077 best settings'],
    ['gta5-settings', 'Best GTA 5 settings for high FPS on a low-end or mid-range PC', 'Game setup', 'pc-hardware', 'gpu', 'gta 5 best settings'],
    ['minecraft-fps', 'How to get more FPS in Minecraft Java and Bedrock', 'Game setup', 'pc-hardware', 'gpu', 'how to get more fps in minecraft'],
    ['dlss-fsr', 'DLSS vs FSR vs XeSS: how to use upscaling for more FPS', 'How to use', 'pc-hardware', 'gpu', 'dlss vs fsr'],
    ['second-monitor', 'How to set up a second monitor for gaming and streaming', 'Setup', 'pc-hardware', 'monitors', 'how to set up dual monitors'],
];

/**
 * A real "how to" search from Search Console (gsc-lib.php) that no post answers yet, as a guide topic: strictly
 * in the niche, shown at least 10 times, a question of how to set up, use, fix or tune something. The product
 * category comes from the words; without one the search is skipped (a guide must lead to our products).
 */
const ROC_GUIDE_CAT_WORDS = ['monitors' => 'monitor|display|refresh rate|hz|g-?sync|freesync|hdr', 'mice' => 'mouse|mice|dpi', 'keyboards' => 'keyboard|switch|keycap',
    'audio' => 'headset|headphone|mic|microphone|spatial', 'speakers' => 'speaker|soundbar', 'chairs' => 'chair', 'handhelds' => 'steam deck|rog ally|legion go|handheld',
    'laptops' => 'laptop', 'ram' => '\bram\b|memory|xmp|expo|ddr', 'cpu' => '\bcpu\b|processor|ryzen|intel core|bottleneck', 'cases' => '\bcase\b|airflow|case fan',
    'gpu' => 'gpu|graphics|fps|settings|nvidia|radeon|rtx|gtx|driver|dlss|fsr'];
function rocGuideFromSearches(PDO $pdo): ?array {
    if (!is_file(__DIR__ . '/gsc-lib.php')) return null;
    require_once __DIR__ . '/gsc-lib.php';
    if (!function_exists('rocGscQueryRows')) return null;
    $done = array_column(rocGuideLog($pdo), 'key');
    $titles = [];
    foreach ($pdo->query('SELECT title FROM blogs')->fetchAll(PDO::FETCH_COLUMN) as $t) $titles[] = rocGscWords((string)$t);
    $best = [];
    foreach (rocGscQueryRows($pdo) as $r) {
        $q = trim((string)$r['q']);
        if ((int)$r['i'] < 10 || !preg_match('/^(how (to|do i|do you)|best .{3,40} settings|why (is|does) my|fix )/', $q) || !rocGscNicheOk($q, '', true)) continue;
        $key = 'gsc-' . rocBlogSlug($q);
        if (in_array($key, $done, true) || isset($best[$key])) continue;
        $w = rocGscWords($q);
        if (count($w) < 2) continue;
        foreach ($titles as $tw) if (count(array_intersect($w, $tw)) >= max(2, (int)ceil(count($w) * 0.7))) continue 2;   // a post answers it
        $cat = '';
        foreach (ROC_GUIDE_CAT_WORDS as $c => $re) if (preg_match('/' . $re . '/', $q)) { $cat = $c; break; }
        if ($cat === '') continue;
        $type = preg_match('/settings|fps/', $q) ? 'Game setup' : (preg_match('/set ?up|install|connect|turn on|enable/', $q) ? 'Setup' : (preg_match('/choose|buy|which/', $q) ? 'Buying' : 'How to use'));
        $group = in_array($cat, ['monitors', 'cpu', 'gpu', 'ram', 'cases'], true) ? 'pc-hardware' : 'gaming-hardware';
        $best[$key] = [(int)$r['i'], [$key, ucfirst($q), $type, $group, $cat, $q]];
    }
    if (!$best) return null;
    uasort($best, function ($a, $b) { return $b[0] <=> $a[0]; });
    return reset($best)[1];
}

/** True from the week the 3-day guide schedule starts (Deploy 126). */
function rocGuideWeekly(): bool { return (new DateTime('now', new DateTimeZone(ROC_BLOG_TZ)))->format('Y-m-d') >= ROC_GUIDE_FROM; }

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
    if (rocGuideWeekly()) { try { $g = rocGuideFromSearches($pdo); if ($g) return $g; } catch (\Throwable $e) {} }
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
    if (rocAiEnded($pdo)) return 'ended: the AI writes guides until ' . rocAiLastDay($pdo) . ' (Deploy 145)';
    if (rocGuideWeekly()) {
        // Deploy 126: one guide on Tuesday, Friday and Saturday, written from 09:30 for 12:00.
        if (!in_array((int)$now->format('N'), ROC_GUIDE_DAYS, true)) return 'not a guide day';
        foreach (rocGuideLog($pdo) as $l) if (($l['day'] ?? '') === $day) return 'done today';
        $hm = $now->format('H:i');
        if ($hm < '09:30') return 'writes from 09:30';
        if ($hm > '20:00') return 'too late today';
        return null;
    }
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
function rocGuideWrite(PDO $pdo, string $root, ?array $topic = null, ?string $publishAt = null): array {
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
        . "GUIDE TYPE: {$type}\nTITLE IDEA: {$idea}\nFOCUS KEYWORD: {$kw}\n\nINTERNAL LINKS (use 5-8 of them, each once, descriptive anchor text, never \"click here\"):\n" . implode("\n", $links) . "\n\n"
        . "RULES\n- At least 1,100 words (aim for 1,300-1,600), clear simple English for gamers, no filler.\n"
        . "- Start with 2-3 sentences that say what the reader will achieve. Then a short \"What you need\" list.\n"
        . "- The steps as <h2> sections (\"Step 1: ...\") or an <ol> with a short explanation for each step; add tips and common mistakes.\n"
        . "- No prices, no invented benchmarks or test results, never \"we tested\". If unsure of a detail, leave it out.\n"
        . "- End with <h2>FAQ</h2> (3 <h3> questions with <p> answers) and a short conclusion <h2>.\n"
        . "- Allowed HTML: p, h2, h3, ul, ol, li, strong, em, a, table, thead, tbody, tr, th, td, blockquote. Never h1.\n\n"
        . "TITLE AND DESCRIPTION RULES (checked by the site; a post that breaks one is sent back):\n"
        . "- Never name another review site or magazine (RTINGS, IGN, PCMag, Tom's Hardware, TechRadar ...) anywhere, not even if it is in a keyword.\n"
        . "- Write product and brand names the way the maker does: \"ATH-M50x\", \"RTX 4060\", \"NVIDIA\", \"Xbox\", \"PS5\", \"GPU\", \"FPS\"; never in lowercase.\n"
        . "- Start the title, meta title and meta description with a capital letter; the meta description starts with what the reader gets, never with \"Discover\", \"Read our\", \"Learn\" or \"Check out\".\n"
        . "- Meta title at most 60 characters; meta description 120-155 characters; the focus keyword once in the title, in natural words.\n\n"
        . "Answer with ONLY a JSON object: {\"title\": \"40-70 characters with the focus keyword\", \"meta_title\": \"max 60 characters\", "
        . "\"meta_description\": \"120-155 characters\", \"excerpt\": \"1-2 sentences\", \"content_html\": \"the guide HTML\", \"image_alt\": \"short description of the cover\", "
        . "\"image_prompt\": \"one sentence: a realistic photo for this guide, the gear or the setup, no text, no logos, no faces\", \"tags\": [\"4 to 6 short search words\"]}";
    // Deploy 127: the title and description check (seo-check-lib.php); a guide that fails goes back to the AI once.
    $used = null;
    for ($try = 1; $try <= 2; $try++) {
        $w = rocBlogWriteAny($pdo, $prompt, $used);
        if (!$w['ok']) return ['ok' => false, 'error' => $w['error']];
        $a = $w['article'];
        $bad = rocSeoCheck(['title' => (string)($a['title'] ?? ''), 'meta_title' => (string)($a['meta_title'] ?? ''), 'meta_description' => (string)($a['meta_description'] ?? ''),
                            'excerpt' => (string)($a['excerpt'] ?? ''), 'focus_keyword' => $kw]);
        if (!$bad) break;
        if ($try === 2) return ['ok' => false, 'error' => 'Title or description not accepted: ' . implode(' ', $bad)];
        $prompt .= "\n\nYour previous answer was rejected: " . implode(' ', $bad) . ' Fix this.';
    }
    $title = trim(mb_substr(strip_tags((string)($a['title'] ?? '')), 0, 200)) ?: $idea;
    $content = rocBlogCleanLinks(rocCmsSanitizeHtml((string)($a['content_html'] ?? '')), $pdo);
    $words = count(preg_split('/\s+/u', rocCmsTextFromHtml($content)) ?: []);
    if ($words < 900) return ['ok' => false, 'error' => "The guide was too short ({$words} words)."];
    $content = rocBlogBuyBoxes($pdo, $content, ['pillar' => 'cat-' . $cat]);
    $slug = rocBlogSlug($title);
    $st = $pdo->prepare('SELECT COUNT(*) FROM blogs WHERE slug = ?'); $st->execute([$slug]);
    if ((int)$st->fetchColumn() > 0) $slug .= '-' . substr(bin2hex(random_bytes(2)), 0, 3);
    $metaT = rocSeoCut(strip_tags((string)($a['meta_title'] ?? $title)), 60);
    $metaD = rocSeoCut(strip_tags((string)($a['meta_description'] ?? '')), 160);
    $alt = trim(mb_substr(strip_tags((string)($a['image_alt'] ?? '')), 0, 300)) ?: $title;
    $image = rocBlogMakeCover($pdo, $root, $slug, $title, 'Guides', null, (string)($a['image_prompt'] ?? ''), $alt);
    $tags = implode(', ', rocTopicTags(implode(',', is_array($a['tags'] ?? null) ? $a['tags'] : [])));
    $id = 'blog-' . bin2hex(random_bytes(4));
    $now = gmdate('Y-m-d H:i:s');
    $later = $publishAt !== null && strtotime($publishAt . ' UTC') > time();   // scheduled for 12:00 (Deploy 126)
    $row = ['id' => $id, 'title' => $title, 'slug' => $slug, 'excerpt' => trim(mb_substr(strip_tags((string)($a['excerpt'] ?? '')), 0, 600)), 'content' => $content,
            'category' => 'Guides: ' . $type, 'author_name' => ROC_BLOG_AUTHOR, 'image' => $image, 'image_alt' => $alt, 'meta_title' => $metaT, 'meta_description' => $metaD,
            'focus_keyword' => $kw, 'read_time' => max(1, (int)ceil($words / 200)) . ' min read', 'status' => $later ? 'scheduled' : 'published', 'version' => 1,
            'published_at' => $later ? $publishAt : $now, 'content_modified_at' => $now, 'created_at' => $now, 'updated_at' => $now,
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
    // The alert email, as for news and blog posts (CMS > Updates writer > News > Email alerts); a scheduled guide
    // gets it from the scheduler when it goes live.
    if (!$later) try {
        require_once __DIR__ . '/news-engine-lib.php';
        rocNotify($pdo, 'Guide published: ' . $title, '<p><b>A guide was published on Run On Console.</b></p><p style="font-size:18px"><a href="https://runonconsole.com/guides/'
            . rocLayerH($slug) . '/">' . rocLayerH($title) . '</a></p><p>' . rocLayerH($type) . ' guide, ' . $words . ' words.</p>');
    } catch (\Throwable $e) {}
    return ['ok' => true, 'id' => $id, 'title' => $title, 'url' => 'https://runonconsole.com/guides/' . $slug . '/', 'words' => $words,
            'live' => $later ? (new DateTime('@' . strtotime($publishAt . ' UTC')))->setTimezone(new DateTimeZone(ROC_BLOG_TZ))->format('D j M H:i') . ' Pakistan time' : 'now'];
}

/** Scheduler cron: one guide when it is time (see rocGuideWait). */
function rocGuideTick(PDO $pdo, string $root): ?string {
    if (rocGuideWait($pdo) !== null) return null;
    if (!rocGuideNext($pdo)) return null;
    $st = rocBlogGet($pdo, 'guide_state', []); $st = is_array($st) ? $st : [];
    if (!empty($st['last_try']) && time() - strtotime($st['last_try'] . ' UTC') < 1800) return null;   // a failed try waits 30 minutes
    $st['last_try'] = gmdate('Y-m-d H:i:s');
    $at = null;
    if (rocGuideWeekly()) {
        $tz = new DateTimeZone(ROC_BLOG_TZ);
        $at = gmdate('Y-m-d H:i:s', (new DateTime((new DateTime('now', $tz))->format('Y-m-d') . ' ' . ROC_GUIDE_TIME, $tz))->getTimestamp());
    }
    $r = rocGuideWrite($pdo, $root, null, $at);
    $st['last_error'] = $r['ok'] ? '' : gmdate('Y-m-d H:i') . ' UTC: ' . $r['error'];
    rocBlogSet($pdo, 'guide_state', $st);
    return $r['ok'] ? 'Guide written: ' . $r['url'] . ' (' . $r['words'] . ' words, live ' . $r['live'] . ')' : 'Guide failed: ' . $r['error'];
}
