<?php
/**
 * Run On Console — the questions people ask Google, answered in the guides (Deploy 134).
 *
 *   php cli-guide-faq.php                  dry run: shows what would change
 *   php cli-guide-faq.php --apply          makes the changes, with a backup
 *   php cli-guide-faq.php --rollback=FILE  undoes an --apply using its backup
 *
 * Search Console (owner's question filter, 2026-10-29) shows these guides in Google for questions they do not answer
 * in those words: "how to use amd expo", "should i enable expo", "how to enable ram", "wired or wireless gaming
 * headset", "pc case size for a high-end graphics card", "how to choose a graphics card", "how to clean gaming
 * keyboard", "how to sit in gaming chair", "sgx300 chair how to adjust backrest", "how to enhance gaming performance
 * on laptop". Each guide gets those questions in its FAQ (FAQPage markup follows from blog-render.php), a few get a
 * clearer title or meta title, and four titles lose the keyword the writer stuck on the end ("– gaming chair setup").
 * The addresses (slugs) stay the same; no new guide competes with these.
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$ROOT = dirname(__DIR__, 3);
$HOME = dirname($ROOT);
$args = array_slice($argv, 1);
$apply = in_array('--apply', $args, true);
$rollback = null;
foreach ($args as $a) if (strpos($a, '--rollback=') === 0) $rollback = substr($a, 11);

const GF_POSTS = [
    'how-to-enable-xmp-or-expo-and-boost-your-ram-speed' => [
        'meta_title' => 'How to Enable XMP or EXPO in BIOS for Full RAM Speed',
        'faq' => [
            ['How do I use AMD EXPO?', 'Restart the PC and press Delete or F2 to open the BIOS (UEFI). Find the EXPO setting, usually in the overclocking or memory menu, choose EXPO Profile 1, then save and exit (often F10). The first start can take a minute longer while the memory trains. In Windows, open Task Manager, Performance, Memory and check that the speed matches the number on your RAM kit.'],
            ['Should I enable EXPO?', 'Yes, for most gaming PCs with an AMD AM5 processor (Ryzen 7000, 8000 or 9000) and an EXPO memory kit. Without it DDR5 runs at the slower default speed (often 4800 MT/s) instead of the speed you paid for. EXPO is supported by AMD and the memory makers. If the PC is not stable afterwards, update the BIOS first, then try a lower profile.'],
            ['Why is my RAM slower than the speed on the box?', 'RAM always starts at a safe default speed. The higher speed on the box is stored in an XMP (Intel) or EXPO (AMD) profile, and it only works after you turn that profile on in the BIOS as shown in this guide.'],
        ]],
    'how-to-choose-a-gaming-headset-wired-vs-wireless-sound-mic' => [
        'faq' => [
            ['Should I get a wired or wireless gaming headset?', 'Choose wired if you want the lowest price for the sound, no charging and no delay. Choose wireless if you move around or hate cables: a 2.4 GHz USB dongle has no delay you will notice in games, while Bluetooth alone adds delay and is better for phones and music. Wireless costs more for the same sound and needs charging.'],
            ['Are wireless gaming headsets worth it compared to wired models?', 'They are worth it if the freedom from a cable matters to you every day, for example on a couch with a console or when you stand up between games. For a desk PC where the headset never leaves the chair, a wired model usually gives better sound and a better mic for the same money.'],
            ['Can a budget gaming headset still sound good?', 'Yes. Many budget wired headsets have clear sound and a usable mic. Look for comfortable earcups, a detachable or flexible mic and good reviews of the mic; skip extras such as RGB lights, which add to the price but not to the sound.'],
        ]],
    'how-to-install-a-graphics-card-step-by-step-guide' => [
        'faq' => [
            ['Will a new graphics card fit in my PC case?', 'Check three numbers before you buy: the card length against the maximum GPU length in your case specs, the card thickness (2, 2.5 or 3 slots) against the free slots, and the height, so the power cable can bend without pressing on the side panel. Then check that your power supply has enough watts and the right power connectors for the card.'],
        ]],
    'how-to-choose-which-graphics-card-to-buy-for-your-pc-and-budget' => [
        'faq' => [
            ['How do I choose a graphics card?', 'Start from your monitor: its resolution and refresh rate tell you how strong a card you need. Then set a budget, check that your power supply and case can take the card, and compare the cards in that price range on real game performance and VRAM (8 GB is the minimum for new games at 1080p, more for 1440p).'],
            ['What size PC case do I need for a high-end graphics card?', 'High-end cards are often over 300 mm long and three slots thick, so look for a mid-tower or full-tower case that lists a maximum GPU length longer than your card, with room to spare for the power cable. A small form factor or micro-ATX case often has less room, so always compare the card length with the case specs.'],
        ]],
    'how-to-clean-a-mechanical-keyboard-without-damage' => [
        'meta_title' => 'How to Clean a Mechanical Gaming Keyboard Safely',
        'faq' => [
            ['How do I clean a gaming keyboard quickly?', 'Unplug it, turn it upside down and shake out the crumbs, then use compressed air between the keys. Wipe the keycaps with a cloth that is only slightly damp, with a little isopropyl alcohol for greasy spots. Never spray liquid straight onto the keyboard.'],
        ]],
    'how-to-adjust-a-gaming-chair-for-good-posture-gaming-chair-setup' => [
        'title' => 'How to Adjust a Gaming Chair for Good Posture',
        'meta_title' => 'How to Sit in a Gaming Chair: Setup for Good Posture',
        'meta_description' => 'How to sit in and adjust a gaming chair: seat height, seat depth, backrest recline, lumbar support and armrests, step by step for long gaming sessions.',
        'faq' => [
            ['How should I sit in a gaming chair?', 'Sit all the way back so your lower back touches the backrest or lumbar pillow. Keep your feet flat on the floor with your knees at about 90 degrees, rest your elbows on the armrests at about desk height, and keep the top of the monitor near eye level so you do not lean forward.'],
            ['How do I adjust the backrest of a gaming chair?', 'Most gaming chairs have a recline lever on the side of the seat. Pull it up, lean back until the backrest is where you want it, then let go to lock it. About 100 to 110 degrees is a good angle for playing; lean further back only for resting.'],
        ]],
    'gaming-laptop-performance-tips-power-cooling-settings-guide' => [
        'meta_title' => 'How to Improve Gaming Laptop Performance: Power & Cooling',
        'faq' => [
            ['How can I improve gaming performance on a laptop?', 'Plug the charger in, set the Windows power mode to Best performance and pick the performance mode in the laptop maker\'s app. In Windows Graphics settings, set each game to High performance so it uses the graphics card. Then update the graphics driver, keep the air vents free and lower heavy settings such as shadows, or turn on DLSS or FSR.'],
        ]],
    'how-to-set-up-a-new-handheld-gaming-pc-handheld-gaming-pc-setup' => [
        'title' => 'How to Set Up a New Handheld Gaming PC, Step by Step'],
    'cs2-best-settings-for-high-fps-on-a-mid-range-pc' => [
        'title' => 'CS2 Best Settings for High FPS on a Mid-Range PC'],
    'how-to-set-up-a-gaming-monitor-for-optimal-picture-and-low-input-lag-gaming-monitor-settin' => [
        'title' => 'How to Set Up a Gaming Monitor for the Best Picture and Low Input Lag'],
];

/** $html with the questions added at the end of its FAQ section (questions already there are skipped). */
function gfAddFaq(string $html, array $faq, array &$added): ?string {
    if (!preg_match('~<h2[^>]*>\s*(?:FAQs?|Frequently asked questions|Common questions)[^<]{0,40}</h2>~i', $html, $m, PREG_OFFSET_CAPTURE)) return null;
    $start = $m[0][1] + strlen($m[0][0]);
    $end = preg_match('~<h2[\s>]~i', $html, $n, PREG_OFFSET_CAPTURE, $start) ? $n[0][1] : strlen($html);
    $nl = strpos(substr($html, $start, $end - $start), "\n") !== false ? "\n" : '';
    $plain = mb_strtolower(strip_tags($html));
    $add = '';
    foreach ($faq as [$q, $a]) {
        if (strpos($plain, mb_strtolower($q)) !== false) continue;
        $add .= '<h3>' . htmlspecialchars($q, ENT_QUOTES) . '</h3>' . $nl . '<p>' . htmlspecialchars($a, ENT_QUOTES) . '</p>' . $nl;
        $added[] = $q;
    }
    if ($add === '') return $html;
    $before = rtrim(substr($html, 0, $end));
    return $before . $nl . $add . ($nl === '' ? '' : "\n") . substr($html, $end);
}

echo "Run On Console - guide questions from Search Console (Deploy 134)\npublic_html: {$ROOT}\n\n";

ob_start();
require_once $ROOT . '/api/v1/config.php';
ob_end_clean();
$pdo = function_exists('getDBConnection') ? getDBConnection() : null;
if (!$pdo) exit("STOP: could not connect to the database.\n");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$cols = [];
$st = $pdo->query('SELECT * FROM blogs LIMIT 0');
for ($i = 0; $i < $st->columnCount(); $i++) $cols[] = strtolower((string)$st->getColumnMeta($i)['name']);
$has = function (string $c) use ($cols) { return in_array($c, $cols, true); };

/* -------------------------------------------------------------- rollback */
if ($rollback !== null) {
    $b = json_decode((string)@file_get_contents($rollback), true);
    if (!is_array($b) || !is_array($b['rows'] ?? null)) exit("No backup found in {$rollback}\n");
    foreach ($b['rows'] as $id => $row) {
        $set = array_filter($row, function ($k) use ($has) { return $has($k) && $k !== 'id'; }, ARRAY_FILTER_USE_KEY);
        $pdo->prepare('UPDATE blogs SET ' . implode(', ', array_map(function ($k) { return $k . ' = ?'; }, array_keys($set))) . ' WHERE id = ?')
            ->execute(array_merge(array_values($set), [$id]));
        echo "Restored {$id}\n";
    }
    exit("Done. Clear the cache in the CMS Dashboard.\n");
}

/* ------------------------------------------------------------------ plan */
$plan = []; $backup = [];
$fields = ['title', 'meta_title', 'meta_description', 'content', 'og_title', 'og_description', 'twitter_title', 'twitter_description'];
foreach (GF_POSTS as $slug => $want) {
    $q = $pdo->prepare('SELECT * FROM blogs WHERE slug = ? LIMIT 1');
    $q->execute([$slug]);
    $row = $q->fetch(PDO::FETCH_ASSOC);
    if (!$row) { echo "  ?  /guides/{$slug}/ not found (skipped)\n"; continue; }
    $new = [];
    foreach (['title', 'meta_title', 'meta_description'] as $f) if (isset($want[$f]) && $has($f) && (string)$row[$f] !== $want[$f]) $new[$f] = $want[$f];
    if (isset($want['meta_title'])) { foreach (['og_title', 'twitter_title'] as $f) if ($has($f) && (string)$row[$f] !== $want['meta_title']) $new[$f] = $want['meta_title']; }
    if (isset($want['meta_description'])) { foreach (['og_description', 'twitter_description'] as $f) if ($has($f) && (string)$row[$f] !== $want['meta_description']) $new[$f] = $want['meta_description']; }
    $added = [];
    if (!empty($want['faq']) && $has('content')) {
        $c = gfAddFaq((string)$row['content'], $want['faq'], $added);
        if ($c === null) echo "  !  /guides/{$slug}/ has no FAQ heading: questions skipped\n";
        elseif ($c !== (string)$row['content']) $new['content'] = $c;
    }
    if (!$new) { echo "  =  /guides/{$slug}/ already done\n"; continue; }
    echo "  +  /guides/{$slug}/\n";
    foreach ($new as $f => $v) if ($f !== 'content') echo '       ' . $f . ': ' . mb_substr((string)$v, 0, 110) . "\n";
    foreach ($added as $qq) echo '       FAQ: ' . $qq . "\n";
    $plan[$row['id']] = $new;
    $backup[$row['id']] = array_intersect_key($row, array_flip(array_merge($fields, ['version', 'content_modified_at', 'updated_at'])));
}

if (!$plan) exit("\nNothing to change.\n");
if (!$apply) { echo "\nDry run: nothing changed. Run again with --apply.\n"; exit(0); }

/* ------------------------------------------------------------------- apply */
$file = $HOME . '/roc-guide-faq-backup-' . date('Ymd-His') . '.json';
file_put_contents($file, json_encode(['made' => date('c'), 'rows' => $backup], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
$now = gmdate('Y-m-d H:i:s');
foreach ($plan as $id => $set) {
    if ($has('content_modified_at')) $set['content_modified_at'] = $now;
    if ($has('updated_at')) $set['updated_at'] = $now;
    $sql = 'UPDATE blogs SET ' . implode(', ', array_map(function ($k) { return $k . ' = ?'; }, array_keys($set))) . ($has('version') ? ', version = version + 1' : '') . ' WHERE id = ?';
    $pdo->prepare($sql)->execute(array_merge(array_values($set), [$id]));
    try {
        $pdo->prepare("INSERT INTO cms_cache_queue (content_type, content_id, target_version_id, action, status, created_at) VALUES ('blog', ?, 1, 'purge_and_prerender', 'pending', ?)")->execute([$id, $now]);
    } catch (\Throwable $e) {}
    echo "Updated {$id}\n";
}
echo "\nDone. Backup: {$file}\nRollback: php " . __FILE__ . " --rollback={$file}\n";
echo "Now: CMS Dashboard > Clear cache, then open https://runonconsole.com/guides/how-to-enable-xmp-or-expo-and-boost-your-ram-speed/\n";
