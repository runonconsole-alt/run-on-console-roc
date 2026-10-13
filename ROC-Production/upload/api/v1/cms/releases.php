<?php
/**
 * Run On Console (ROC) — which website version is live (CMS dashboard + "Live version")
 *
 * GET /api/v1/cms/releases.php
 *
 * Every deploy zip carries roc-releases/deploy-NN.json, unzipped next to public_html
 * (/home2/runoncon/roc-releases/, not reachable from the web):
 *   {deploy, date, title, build, changes: [...], steps: [...], checks: [{label, type, ...}]}
 * build is the JS bundle of that deploy (index-XXXX.js): when it matches the one the
 * home page loads, that deploy's website files are what visitors get.
 * The newest file is the live version. Checks show whether the deploy's installers
 * were run on this server:
 *   {type:'file', path:'images/logo-512.png'}             file exists in public_html
 *   {type:'htaccess', marker:'# ROC speed BEGIN'}          .htaccess contains the text
 *   {type:'contains', path:'api/v1/config.php', text:'…'}   file contains the text
 *   {type:'setting', key:'site_announcement'}              cms_settings row exists
 */
require_once __DIR__ . '/config.php';

$session = requireCmsPermission('blogs', 'view');
$ROOT = realpath(__DIR__ . '/../../..') ?: dirname(__DIR__, 3);
$dir = dirname($ROOT) . '/roc-releases';

$pdo = getDBConnection();
$check = function (array $c) use ($ROOT, $pdo): ?bool {
    $p = isset($c['path']) ? $ROOT . '/' . ltrim(str_replace('..', '', (string)$c['path']), '/') : '';
    switch ($c['type'] ?? '') {
        case 'file':     return $p !== '' && is_file($p);
        case 'htaccess': return strpos((string)@file_get_contents($ROOT . '/.htaccess'), (string)($c['marker'] ?? "\0")) !== false;
        case 'contains': return $p !== '' && strpos((string)@file_get_contents($p), (string)($c['text'] ?? "\0")) !== false;
        case 'setting':
            if (!$pdo) return null;
            try { $st = $pdo->prepare('SELECT 1 FROM cms_settings WHERE setting_key = ?'); $st->execute([(string)($c['key'] ?? '')]); return (bool)$st->fetchColumn(); }
            catch (\Throwable $e) { return null; }
    }
    return null;
};

$out = [];
foreach (glob($dir . '/deploy-*.json') ?: [] as $f) {
    $r = json_decode((string)file_get_contents($f), true);
    if (!is_array($r) || !isset($r['deploy'])) continue;
    $checks = [];
    foreach ((array)($r['checks'] ?? []) as $c) if (is_array($c)) $checks[] = ['label' => (string)($c['label'] ?? ''), 'ok' => $check($c)];
    $out[] = ['deploy' => (int)$r['deploy'], 'date' => (string)($r['date'] ?? ''), 'title' => (string)($r['title'] ?? ''),
              'changes' => array_values(array_map('strval', (array)($r['changes'] ?? []))),
              'steps' => array_values(array_map('strval', (array)($r['steps'] ?? []))), 'checks' => $checks,
              'pr' => (string)($r['pr'] ?? ''), 'build' => (string)($r['build'] ?? '')];
}
usort($out, function ($a, $b) { return $b['deploy'] <=> $a['deploy']; });

// The website build that is actually being served (file names change with every build).
$build = '';
if (preg_match('#/assets/(index-[A-Za-z0-9_-]+\.js)#', (string)@file_get_contents($ROOT . '/index.html', false, null, 0, 200000), $m)) $build = $m[1];

// A release whose build file is the one being served is live for sure.
foreach ($out as &$r) $r['served'] = $r['build'] !== '' && $r['build'] === $build;
unset($r);
echo json_encode(['success' => true, 'live' => $out[0] ?? null, 'releases' => $out, 'build' => $build], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
