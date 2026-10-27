<?php
/**
 * Run On Console — better titles and descriptions for 5 blog posts (Deploy 127), from the Search Console searches.
 *
 *   php cli-blog-titles.php                  dry run: shows what would change
 *   php cli-blog-titles.php --apply          makes the changes, with a backup
 *   php cli-blog-titles.php --rollback=FILE  undoes an --apply using its backup
 *
 * The Audeze Maxwell 2 post said "Review RTINGS" (RTINGS is another review site; the writer took it from Google
 * autocomplete, Deploy 123 stops that): its title, focus keyword, summary and text lose the name. The other four get
 * a meta title and description with the words people search ("for gaming", "weight limit", "computer cases" ...).
 * The addresses (slugs) stay the same.
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$ROOT = dirname(__DIR__, 3);
$HOME = dirname($ROOT);
$args = array_slice($argv, 1);
$apply = in_array('--apply', $args, true);
$rollback = null;
foreach ($args as $a) if (strpos($a, '--rollback=') === 0) $rollback = substr($a, 11);

const BT_POSTS = [
    'audeze-maxwell-2-review-rtings-is-it-worth-buying' => [
        'title' => 'Audeze Maxwell 2 Review: Is It Worth Buying?',
        'meta_title' => 'Audeze Maxwell 2 Review: Sound, ANC & Is It Worth Buying?',
        'meta_description' => 'Audeze Maxwell 2 review: planar magnetic sound, ANC, mic and gaming performance, with pros, cons and who should buy this wireless headset.',
        'focus_keyword' => 'audeze maxwell 2 review',
        'text' => true],
    'audio-technica-ath-m50x-review-is-it-worth-buying' => [
        'meta_title' => 'Audio-Technica ATH-M50x Review: Good for Gaming?',
        'meta_description' => 'Audio-Technica ATH-M50x review for gamers: sound, comfort, mic options and build quality, with pros, cons and whether these studio headphones suit gaming.'],
    'blacklyte-athena-pro-gaming-chair-review-is-it-worth-buying' => [
        'meta_title' => 'Blacklyte Athena Pro Review: Comfort, Weight Limit & Verdict',
        'meta_description' => 'Blacklyte Athena Pro gaming chair review: comfort, build, weight limit and adjustments, with pros, cons and how it compares with Secretlab.'],
    'best-budget-gaming-headsets-that-deliver-big-sound-without-breaking-the-bank' => [
        'meta_title' => 'Best Budget Gaming Headsets: Top Affordable Picks',
        'meta_description' => 'The best budget gaming headsets: affordable picks with clear sound, good mics and all-day comfort, plus a quick specs comparison and buying tips.'],
    'best-pc-cases-2026-for-gaming-our-top-picks' => [
        'meta_title' => 'Best PC Cases 2026 for Gaming: Top Computer Case Picks',
        'meta_description' => 'The best PC cases of 2026 for gaming builds: airflow and quiet computer cases compared with key specs, pros and cons, so you can pick the right one.'],
];

/** The text without the other site's name. */
function btText(string $s): string {
    $s = (string)preg_replace('/\bRTINGS[\x{2010}\x{2011}\x{2012}\x{2013}-]style\s+review\b/iu', 'review', $s);
    $s = (string)preg_replace('/\baudeze maxwell 2 review rtings\b/iu', 'Audeze Maxwell 2 review', $s);
    $s = (string)preg_replace('/\breview rtings\b/iu', 'review', $s);
    return $s;
}

echo "Run On Console - blog titles and descriptions (Deploy 127)\npublic_html: {$ROOT}\n\n";

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
$fields = ['title', 'meta_title', 'meta_description', 'focus_keyword', 'excerpt', 'content', 'og_title', 'og_description', 'twitter_title', 'twitter_description'];
foreach (BT_POSTS as $slug => $want) {
    $q = $pdo->prepare('SELECT * FROM blogs WHERE slug = ? LIMIT 1');
    $q->execute([$slug]);
    $row = $q->fetch(PDO::FETCH_ASSOC);
    if (!$row) { echo "  ?  /blogs/{$slug}/ not found (skipped)\n"; continue; }
    $new = [];
    foreach (['title', 'meta_title', 'meta_description', 'focus_keyword'] as $f) if (isset($want[$f]) && $has($f) && (string)$row[$f] !== $want[$f]) $new[$f] = $want[$f];
    // Social cards follow the meta title and description.
    if (isset($want['meta_title'])) { foreach (['og_title', 'twitter_title'] as $f) if ($has($f) && (string)$row[$f] !== $want['meta_title']) $new[$f] = $want['meta_title']; }
    if (isset($want['meta_description'])) { foreach (['og_description', 'twitter_description'] as $f) if ($has($f) && (string)$row[$f] !== $want['meta_description']) $new[$f] = $want['meta_description']; }
    if (!empty($want['text'])) foreach (['excerpt', 'content'] as $f) {
        if (!$has($f)) continue;
        $t = btText((string)$row[$f]);
        if ($t !== (string)$row[$f]) $new[$f] = $t;
    }
    if (!$new) { echo "  =  /blogs/{$slug}/ already done\n"; continue; }
    echo "  +  /blogs/{$slug}/\n";
    foreach ($new as $f => $v) echo '       ' . $f . ': ' . ($f === 'content' ? 'the text (other site\'s name taken out)' : mb_substr((string)$v, 0, 110)) . "\n";
    if (!empty($want['text'])) {
        $left = preg_match_all('/rtings/i', ($new['content'] ?? (string)$row['content']) . ' ' . ($new['title'] ?? (string)$row['title']) . ' ' . ($new['excerpt'] ?? (string)$row['excerpt']));
        if ($left) echo "       note: \"RTINGS\" is still named {$left} time(s) in the text; edit it in the CMS.\n";
    }
    $plan[$row['id']] = $new;
    $backup[$row['id']] = array_intersect_key($row, array_flip(array_merge($fields, ['version', 'content_modified_at', 'updated_at'])));
}

if (!$plan) exit("\nNothing to change.\n");
if (!$apply) { echo "\nDry run: nothing changed. Run again with --apply.\n"; exit(0); }

/* ------------------------------------------------------------------- apply */
$file = $HOME . '/roc-blog-titles-backup-' . date('Ymd-His') . '.json';
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
echo "Now: CMS Dashboard > Clear cache, then open https://runonconsole.com/blogs/audeze-maxwell-2-review-rtings-is-it-worth-buying/\n";
