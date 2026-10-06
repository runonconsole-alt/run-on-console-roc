<?php
/**
 * Run On Console (ROC) - CMS Gaming Platforms API (/categories/ pages)
 * GET  ?                       all platforms with device counts
 * GET  ?slug=...               one platform with its devices
 * POST {action:'save', slug?, fields{...}, status?}             create / update a platform
 * POST {action:'save_device', id?, category_slug, fields{...}, status?}   create / update a device
 *
 * Tables are created by cron/cli-gaming-categories.php. Uses the "pages"
 * permission section, like the other site pages in the CMS.
 */

require_once __DIR__ . '/config.php';

$session = requireCmsPermission('pages', 'view');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') rocCmsAuthorize($session, 'pages', 'edit');
$pdo = getDBConnection();
if (!$pdo) {
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'Database connection unavailable.']);
    exit();
}
try { $pdo->query('SELECT 1 FROM gaming_categories LIMIT 1'); }
catch (\Throwable $e) {
    http_response_code(409);
    echo json_encode(['success' => false, 'error' => 'Gaming platforms are not set up yet. Run api/v1/cron/cli-gaming-categories.php --apply on the server first.']);
    exit();
}

function gcClip($v, int $max): string { return mb_substr(trim(is_scalar($v) ? (string)$v : ''), 0, $max); }
function gcSlug(string $s, int $max = 80): string {
    $s = (string)preg_replace('/[^a-z0-9]+/', '-', strtolower(trim($s)));
    return substr(trim($s, '-'), 0, $max);
}
function gcImage(string $img, array &$errors): ?string {
    $img = trim($img);
    if ($img === '') return '';
    $ok = ($img[0] === '/' && strpos($img, '//') !== 0)
        || (strtolower((string)parse_url($img, PHP_URL_SCHEME)) === 'https' && parse_url($img, PHP_URL_HOST));
    if (!$ok || preg_match('/[\s"<>]/', $img)) { $errors[] = 'The image must be an https:// link or an image from the Media library.'; return null; }
    return $img;
}
function gcLines($v, int $maxItems, int $maxLen): string {
    $items = is_array($v) ? $v : preg_split('/\r\n|\r|\n/', (string)$v);
    $items = array_values(array_filter(array_map(function ($x) use ($maxLen) { return gcClip($x, $maxLen); }, $items ?: []), 'strlen'));
    return json_encode(array_slice($items, 0, $maxItems), JSON_UNESCAPED_UNICODE);
}
function gcFail(int $code, string $msg, array $errors = []): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg] + ($errors ? ['errors' => $errors] : []));
    exit();
}
function gcStatus($data, $session): ?string {
    if (!isset($data['status'])) return null;
    rocCmsAuthorize($session, 'pages', 'publish');
    return $data['status'] === 'published' ? 'published' : 'draft';
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $slug = gcSlug((string)($_GET['slug'] ?? ''));
    if ($slug !== '') {
        $st = $pdo->prepare('SELECT * FROM gaming_categories WHERE slug = ?');
        $st->execute([$slug]);
        $cat = $st->fetch(PDO::FETCH_ASSOC);
        if (!$cat) gcFail(404, 'Platform not found.');
        $st = $pdo->prepare('SELECT * FROM gaming_devices WHERE category_slug = ? ORDER BY sort_rank, name');
        $st->execute([$slug]);
        echo json_encode(['success' => true, 'category' => $cat, 'devices' => $st->fetchAll(PDO::FETCH_ASSOC)]);
        exit();
    }
    $cats = $pdo->query('SELECT slug, title, image, status, sort_rank, updated_at FROM gaming_categories ORDER BY sort_rank, title')->fetchAll(PDO::FETCH_ASSOC);
    $counts = [];
    foreach ($pdo->query('SELECT category_slug, status, COUNT(*) AS n FROM gaming_devices GROUP BY category_slug, status')->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $counts[$r['category_slug']][$r['status'] === 'published' ? 'live' : 'hidden'] = (int)$r['n'];
    }
    foreach ($cats as &$c) $c['devices'] = ($counts[$c['slug']] ?? []) + ['live' => 0, 'hidden' => 0];
    unset($c);
    echo json_encode(['success' => true, 'categories' => $cats]);
    exit();
}

if ($method !== 'POST') gcFail(405, 'Method not allowed.');
$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$in = is_array($data['fields'] ?? null) ? $data['fields'] : [];
$errors = [];
$now = date('Y-m-d H:i:s');
$action = $data['action'] ?? '';

if ($action === 'save') {
    $f = ['title' => gcClip($in['title'] ?? '', 160)];
    if ($f['title'] === '') $errors[] = 'Platform name is required.';
    foreach (['description' => 1500, 'badge' => 120, 'era' => 120, 'meta_title' => 255, 'meta_description' => 500] as $k => $max) {
        if (array_key_exists($k, $in)) $f[$k] = gcClip($in[$k], $max);
    }
    if (array_key_exists('image', $in)) { $img = gcImage((string)$in['image'], $errors); if ($img !== null) $f['image'] = $img; }
    if (array_key_exists('focus', $in)) $f['focus_json'] = gcLines($in['focus'], 10, 80);
    if (array_key_exists('faqs', $in)) {
        $faqs = [];
        foreach ((array)$in['faqs'] as $q) {
            $qq = gcClip($q['q'] ?? '', 300); $aa = gcClip($q['a'] ?? '', 2000);
            if ($qq !== '' && $aa !== '') $faqs[] = ['q' => $qq, 'a' => $aa];
        }
        $f['faq_json'] = json_encode(array_slice($faqs, 0, 12), JSON_UNESCAPED_UNICODE);
    }
    if (array_key_exists('sort_rank', $in)) $f['sort_rank'] = max(0, min(9999, (int)$in['sort_rank']));
    if (array_key_exists('is_noindex', $in)) $f['is_noindex'] = !empty($in['is_noindex']) ? 1 : 0;
    $status = gcStatus($data, $session);
    if ($errors) gcFail(422, implode(' ', $errors), $errors);

    $slug = gcSlug((string)($data['slug'] ?? ''));
    $st = $pdo->prepare('SELECT COUNT(*) FROM gaming_categories WHERE slug = ?');
    $st->execute([$slug]);
    if ($slug !== '' && (int)$st->fetchColumn()) {
        $set = $f + ['updated_at' => $now, 'content_modified_at' => $now] + ($status !== null ? ['status' => $status] : []);
        $pdo->prepare('UPDATE gaming_categories SET ' . implode(', ', array_map(function ($c) { return "{$c} = ?"; }, array_keys($set))) . ' WHERE slug = ?')
            ->execute([...array_values($set), $slug]);
        $log = 'cms_gaming_category_update';
    } else {
        // New platform: /categories/{slug}/ is set once from the name.
        $base = gcSlug($f['title']) ?: 'platform';
        $slug = $base;
        for ($n = 2; ; $n++) { $st->execute([$slug]); if (!(int)$st->fetchColumn()) break; $slug = $base . '-' . $n; }
        if (!isset($f['sort_rank'])) $f['sort_rank'] = (int)$pdo->query('SELECT COALESCE(MAX(sort_rank), 0) + 1 FROM gaming_categories')->fetchColumn();
        $row = $f + ['slug' => $slug, 'status' => $status ?? 'draft', 'created_at' => $now, 'updated_at' => $now, 'content_modified_at' => $now];
        $pdo->prepare('INSERT INTO gaming_categories (' . implode(', ', array_keys($row)) . ') VALUES (' . implode(', ', array_fill(0, count($row), '?')) . ')')
            ->execute(array_values($row));
        $log = 'cms_gaming_category_create';
    }
    logCmsAudit($log, 'gaming_category', $slug, ['title' => $f['title'], 'status' => $status]);
    $st = $pdo->prepare('SELECT * FROM gaming_categories WHERE slug = ?');
    $st->execute([$slug]);
    echo json_encode(['success' => true, 'category' => $st->fetch(PDO::FETCH_ASSOC)]);
    exit();
}

if ($action === 'save_device') {
    $cat = gcSlug((string)($data['category_slug'] ?? ''));
    $st = $pdo->prepare('SELECT COUNT(*) FROM gaming_categories WHERE slug = ?');
    $st->execute([$cat]);
    if (!(int)$st->fetchColumn()) $errors[] = 'Unknown platform.';
    $f = ['name' => gcClip($in['name'] ?? '', 200)];
    if ($f['name'] === '') $errors[] = 'Device name is required.';
    foreach (['year' => 40, 'era' => 80, 'type' => 120, 'specs' => 500, 'status_label' => 80] as $k => $max) {
        if (array_key_exists($k, $in)) $f[$k] = gcClip($in[$k], $max);
    }
    if (array_key_exists('games', $in)) $f['iconic_games_json'] = gcLines($in['games'], 8, 80);
    if (array_key_exists('image', $in)) { $img = gcImage((string)$in['image'], $errors); if ($img !== null) $f['image'] = $img; }
    if (array_key_exists('sort_rank', $in)) $f['sort_rank'] = max(0, min(9999, (int)$in['sort_rank']));
    $status = gcStatus($data, $session);
    if ($errors) gcFail(422, implode(' ', $errors), $errors);

    $id = gcClip($data['id'] ?? '', 80);
    $chk = $pdo->prepare('SELECT COUNT(*) FROM gaming_devices WHERE id = ? AND category_slug = ?');
    $chk->execute([$id, $cat]);
    if ($id !== '' && (int)$chk->fetchColumn()) {
        $set = $f + ['updated_at' => $now, 'content_modified_at' => $now] + ($status !== null ? ['status' => $status] : []);
        $pdo->prepare('UPDATE gaming_devices SET ' . implode(', ', array_map(function ($c) { return "{$c} = ?"; }, array_keys($set))) . ' WHERE id = ?')
            ->execute([...array_values($set), $id]);
        $log = 'cms_gaming_device_update';
    } else {
        $id = 'dev-' . gcSlug($f['name'], 60) . '-' . bin2hex(random_bytes(3));
        if (!isset($f['sort_rank'])) {
            $st = $pdo->prepare('SELECT COALESCE(MAX(sort_rank), 0) + 1 FROM gaming_devices WHERE category_slug = ?');
            $st->execute([$cat]);
            $f['sort_rank'] = (int)$st->fetchColumn();
        }
        $row = $f + ['id' => $id, 'category_slug' => $cat, 'status' => $status ?? 'published', 'created_at' => $now, 'updated_at' => $now, 'content_modified_at' => $now];
        $pdo->prepare('INSERT INTO gaming_devices (' . implode(', ', array_keys($row)) . ') VALUES (' . implode(', ', array_fill(0, count($row), '?')) . ')')
            ->execute(array_values($row));
        $log = 'cms_gaming_device_create';
    }
    logCmsAudit($log, 'gaming_device', $id, ['name' => $f['name'], 'platform' => $cat, 'status' => $status]);
    $st = $pdo->prepare('SELECT * FROM gaming_devices WHERE id = ?');
    $st->execute([$id]);
    echo json_encode(['success' => true, 'device' => $st->fetch(PDO::FETCH_ASSOC)]);
    exit();
}

gcFail(400, 'Unknown action.');
