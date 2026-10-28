<?php
/**
 * Run On Console — score the graphics card of every game again with the Deploy 136 rule (steam-req-lib.php rocSrGpu).
 *
 *   php cli-steam-rescore.php            dry run: how many games change, with examples
 *   php cli-steam-rescore.php --apply    writes roc-steam-games.json (a copy goes to roc-steam-backups first)
 *
 * Steam's lines list cards that are each enough and extras for ray tracing or VR. The old rule took the dearest card
 * of the line, so F1 2021 asked for an RTX 3070 class card at minimum (Steam says GTX 950) and Rust for an Arc A580
 * class card (Steam: GTX 1060 or RX 470). Only the graphics card scores change; the text from Steam stays as it is.
 * To undo: copy the backup back over public_html/roc-steam-games.json.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$ROOT = dirname(__DIR__, 3);
$apply = in_array('--apply', array_slice($argv, 1), true);
require_once $ROOT . '/api/v1/steam-req-lib.php';

echo "Run On Console - graphics card scores of the Steam games (Deploy 136)\n\n";
$file = $ROOT . '/roc-steam-games.json';
$d = json_decode((string)@file_get_contents($file), true);
if (!is_array($d) || !is_array($d['games'] ?? null)) exit("STOP: could not read {$file}\n");

$changed = 0; $games = 0; $ex = [];
foreach ($d['games'] as $i => $g) {
    $hit = false;
    foreach (['min', 'rec'] as $k) {
        $b = $g[$k] ?? null;
        if (!is_array($b) || !isset($g['req'][$k])) continue;
        $new = (rocSrEstimate($b, rocSrScore($b)) ?? [])['gpu'] ?? null;
        $old = $g['req'][$k]['gpu'] ?? null;
        if ($new === null || (float)$new === (float)$old) continue;
        $d['games'][$i]['req'][$k]['gpu'] = $new;
        $changed++; $hit = true;
        if (count($ex) < 25) $ex[] = '  ' . $g['t'] . ' (' . ($k === 'min' ? 'minimum' : 'recommended') . "): {$old} -> {$new}   " . mb_substr((string)$b['gpu'], 0, 80);
    }
    // A game without a recommended block uses its minimum for both.
    if (!is_array($g['rec'] ?? null) && isset($d['games'][$i]['req']['min']['gpu'], $d['games'][$i]['req']['rec'])) $d['games'][$i]['req']['rec']['gpu'] = $d['games'][$i]['req']['min']['gpu'];
    if ($hit) $games++;
}
echo implode("\n", $ex) . "\n\n{$games} of " . count($d['games']) . " games change ({$changed} scores).\n";
if (!$changed) exit("Nothing to change.\n");
if (!$apply) exit("Dry run: nothing changed. Run again with --apply.\n");

$bdir = dirname($ROOT) . '/roc-steam-backups';
if (!is_dir($bdir)) @mkdir($bdir, 0700, true);
$backup = $bdir . '/roc-steam-games-' . date('Ymd-His') . '-before-rescore.json';
if (!@copy($file, $backup)) exit("STOP: could not write the backup {$backup}\n");
$d['updated'] = gmdate('Y-m-d');
$tmp = $file . '.roc-tmp';
if (@file_put_contents($tmp, json_encode($d, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) === false || !@rename($tmp, $file)) exit("STOP: could not write {$file}\n");
echo "Done. Backup: {$backup}\nUndo: cp {$backup} {$file}\n";
