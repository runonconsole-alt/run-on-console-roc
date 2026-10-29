<?php
/**
 * Run On Console — the owner's audit list of 2026-10-29 (Deploy 143), fixed in the database.
 *
 *   php cli-content-cleanup.php                  dry run: shows what would change
 *   php cli-content-cleanup.php --apply          makes the changes, with a backup
 *   php cli-content-cleanup.php --rollback=FILE  undoes an --apply using its backup
 *
 * 1. Product pages: lines where the writer said what it did not know ("Battery life not disclosed", "The description
 *    does not list RGB lighting") go: such pros and cons, such sentences in the About text, and such questions in its FAQ.
 *    (content-writer-lib.php no longer writes them.)
 * 2. News: meta descriptions that stop in the middle ("...for gamers seeking…") end on a whole sentence
 *    (rocSeoSentence), the share cards too.
 * 3. A wrong claim: "The RTX 5060 is the first RTX 5000-series GPU to appear in mainstream laptops" (the site has
 *    RTX 5070 Ti and 5080 laptop news); the sentence is taken out wherever it is.
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$ROOT = dirname(__DIR__, 3);
$HOME = dirname($ROOT);
$args = array_slice($argv, 1);
$apply = in_array('--apply', $args, true);
$rollback = null;
foreach ($args as $a) if (strpos($a, '--rollback=') === 0) $rollback = substr($a, 11);

echo "Run On Console - content cleanup (Deploy 143)\npublic_html: {$ROOT}\n\n";
ob_start();
require_once $ROOT . '/api/v1/config.php';
ob_end_clean();
require_once $ROOT . '/api/v1/cms/seo-check-lib.php';
$pdo = function_exists('getDBConnection') ? getDBConnection() : null;
if (!$pdo) exit("STOP: could not connect to the database.\n");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$colsOf = function (string $table) use ($pdo): array {
    $st = $pdo->query("SELECT * FROM {$table} LIMIT 0"); $c = [];
    for ($i = 0; $i < $st->columnCount(); $i++) $c[] = strtolower((string)$st->getColumnMeta($i)['name']);
    return $c;
};
$P = $colsOf('products'); $B = $colsOf('blogs');

/* -------------------------------------------------------------- rollback */
if ($rollback !== null) {
    $b = json_decode((string)@file_get_contents($rollback), true);
    if (!is_array($b)) exit("No backup found in {$rollback}\n");
    foreach (['products' => $P, 'blogs' => $B] as $table => $cols) foreach ((array)($b[$table] ?? []) as $id => $row) {
        $set = array_filter($row, function ($k) use ($cols) { return in_array($k, $cols, true) && $k !== 'id'; }, ARRAY_FILTER_USE_KEY);
        if (!$set) continue;
        $pdo->prepare("UPDATE {$table} SET " . implode(', ', array_map(function ($k) { return $k . ' = ?'; }, array_keys($set))) . ' WHERE id = ?')
            ->execute(array_merge(array_values($set), [$id]));
        echo "Restored {$table} {$id}\n";
    }
    exit("Done. Clear the cache in the CMS Dashboard.\n");
}

$hedge = function (string $s): bool { return (bool)preg_match(ROC_SEO_HEDGE, $s); };
// Sentences (in plain text or one Markdown line) that say what is not known.
$dropSentences = function (string $text) use ($hedge): string {
    $lines = explode("\n", str_replace("\r\n", "\n", $text));
    foreach ($lines as &$l) {
        if (!$hedge($l)) continue;
        $parts = preg_split('/(?<=[.!?])\s+/u', $l);
        $l = implode(' ', array_filter($parts, function ($x) use ($hedge) { return !$hedge($x); }));
    }
    unset($l);
    return (string)preg_replace("/\n{3,}/", "\n\n", implode("\n", $lines));
};
const CC_WRONG = '/[^.!?<>]*\bfirst RTX 50(?:00)?[\x{2010}\x{2011}-]?series GPU to appear in mainstream laptops[^.!?<>]*[.!?]/iu';

$plan = []; $backup = ['products' => [], 'blogs' => []]; $n = ['pros' => 0, 'cons' => 0, 'sent' => 0, 'faq' => 0, 'meta' => 0, 'wrong' => 0];

/* ------------------------------------------------------------- products */
foreach ($pdo->query('SELECT * FROM products')->fetchAll(PDO::FETCH_ASSOC) as $p) {
    $new = [];
    foreach (['pros', 'cons'] as $k) {
        if (!in_array($k, $P, true)) continue;
        $list = json_decode((string)($p[$k] ?? ''), true);
        if (!is_array($list)) continue;
        $keep = array_values(array_filter($list, function ($x) use ($hedge) { return !$hedge(is_array($x) ? implode(' ', $x) : (string)$x); }));
        if (count($keep) !== count($list)) { $new[$k] = json_encode($keep, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); $n[$k] += count($list) - count($keep); }
    }
    if (in_array('summary', $P, true) && trim((string)$p['summary']) !== '') {
        $s = str_replace("\r\n", "\n", (string)$p['summary']);
        $i = strpos($s, '## Common questions');
        $about = $i === false ? $s : substr($s, 0, $i);
        $faq = $i === false ? '' : substr($s, $i);
        $about2 = $dropSentences($about);
        if ($faq !== '') {
            $faq2 = (string)preg_replace_callback('/^Q:\s*(.+?)\s*\nA:\s*(.+?)\s*(?=\n\s*\n|\n##|\z)/ms', function ($m) use ($hedge, &$n) {
                if (!$hedge($m[2])) return $m[0];
                $n['faq']++; return '';
            }, $faq);
            $faq2 = (string)preg_replace("/\n{3,}/", "\n\n", $faq2);
        } else $faq2 = '';
        $n['sent'] += max(0, substr_count($about, '.') - substr_count($about2, '.'));
        $s2 = rtrim($about2) . ($faq2 !== '' ? "\n\n" . ltrim($faq2) : '');
        if (trim($s2) !== trim($s)) $new['summary'] = $s2;
    }
    if (!$new) continue;
    echo "  +  /products/{$p['slug']}/  " . implode(', ', array_keys($new)) . "\n";
    $plan['products'][$p['id']] = $new;
    $backup['products'][$p['id']] = array_intersect_key($p, array_flip(array_merge(array_keys($new), ['updated_at', 'content_modified_at'])));
}

/* ---------------------------------------------------------------- posts */
foreach ($pdo->query("SELECT * FROM blogs WHERE status = 'published'")->fetchAll(PDO::FETCH_ASSOC) as $p) {
    $new = [];
    foreach (['content', 'excerpt'] as $k) if (in_array($k, $B, true) && preg_match(CC_WRONG, (string)$p[$k])) { $new[$k] = (string)preg_replace(CC_WRONG, '', (string)$p[$k]); $n['wrong']++; }
    $isNews = strcasecmp(trim((string)($p['category'] ?? '')), 'News') === 0;
    if ($isNews && in_array('meta_description', $B, true)) {
        $md = trim((string)$p['meta_description']);
        if ($md === '' || preg_match('/(…|\.\.\.)$/u', $md) || !preg_match('/[.!?"\')]$/u', $md)) {
            $src = $md;
            // The excerpt is the same story in whole sentences: use it when the cut description is part of it.
            $ex = trim((string)($p['excerpt'] ?? ''));
            if ($ex !== '' && mb_strlen($ex) >= 70) $src = $ex;
            $fix = rocSeoSentence($src, 155);
            if ($fix !== '' && $fix !== $md) {
                $new['meta_description'] = $fix; $n['meta']++;
                foreach (['og_description', 'twitter_description'] as $k) if (in_array($k, $B, true) && trim((string)$p[$k]) === $md) $new[$k] = $fix;
            }
        }
    }
    if (!$new) continue;
    echo "  +  /" . ($isNews ? 'news' : 'blogs') . "/{$p['slug']}/  " . implode(', ', array_keys($new)) . (isset($new['meta_description']) ? '  -> ' . $new['meta_description'] : '') . "\n";
    $plan['blogs'][$p['id']] = $new;
    $backup['blogs'][$p['id']] = array_intersect_key($p, array_flip(array_merge(array_keys($new), ['version', 'updated_at', 'content_modified_at'])));
}

echo "\nProducts: {$n['pros']} pros and {$n['cons']} cons, about {$n['sent']} sentences and {$n['faq']} questions about missing details.\n";
echo "News: {$n['meta']} descriptions to whole sentences; the wrong RTX 5060 claim in {$n['wrong']} field(s).\n";
if (!$plan) exit("Nothing to change.\n");
if (!$apply) exit("Dry run: nothing changed. Run again with --apply.\n");

/* ------------------------------------------------------------------- apply */
$file = $HOME . '/roc-content-cleanup-backup-' . date('Ymd-His') . '.json';
file_put_contents($file, json_encode($backup, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
$now = gmdate('Y-m-d H:i:s');
foreach (['products' => $P, 'blogs' => $B] as $table => $cols) foreach ((array)($plan[$table] ?? []) as $id => $set) {
    if (in_array('content_modified_at', $cols, true)) $set['content_modified_at'] = $now;
    if (in_array('updated_at', $cols, true)) $set['updated_at'] = $now;
    $pdo->prepare("UPDATE {$table} SET " . implode(', ', array_map(function ($k) { return $k . ' = ?'; }, array_keys($set))) . ($table === 'blogs' && in_array('version', $cols, true) ? ', version = version + 1' : '') . ' WHERE id = ?')
        ->execute(array_merge(array_values($set), [$id]));
    try {
        $pdo->prepare("INSERT INTO cms_cache_queue (content_type, content_id, target_version_id, action, status, created_at) VALUES (?, ?, 1, 'purge_and_prerender', 'pending', ?)")
            ->execute([$table === 'blogs' ? 'blog' : 'product', $id, $now]);
    } catch (\Throwable $e) {}
}
echo "Done. Backup: {$file}\nRollback: php " . __FILE__ . " --rollback={$file}\nNow: CMS Dashboard > Clear cache.\n";
