<?php
/**
 * Run On Console (ROC) - CMS Product Catalog Management API
 * GET    /api/v1/cms/products.php (List products / Get single product)
 * POST   /api/v1/cms/products.php (Save draft / Publish product)
 */

require_once __DIR__ . '/config.php';

$session = requireCmsPermission('products', 'view');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $peek = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    rocCmsAuthorize($session, 'products', ($peek['action'] ?? '') === 'publish' ? 'publish' : 'edit');
}
$pdo = getDBConnection();

if (!$pdo) {
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'Database connection unavailable.']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

/* ---------------------------------------------------------------- helpers */

function rocProdColumns(PDO $pdo): array {
    $st = $pdo->query('SELECT * FROM products LIMIT 0');
    $cols = [];
    for ($i = 0; $i < $st->columnCount(); $i++) $cols[] = strtolower((string)$st->getColumnMeta($i)['name']);
    return $cols;
}

function rocProdCategories(PDO $pdo): array {
    try {
        return $pdo->query('SELECT slug, name, status FROM product_categories ORDER BY sort_rank, name')->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) { return []; }
}

function rocProdIsAmazonHost(string $host): bool {
    return (bool)preg_match('/(^|\.)amazon\.(com|ca|com\.mx|com\.br|co\.uk|de|fr|it|es|nl|se|pl|com\.be|com\.tr|ae|sa|eg|in|sg|com\.au|co\.jp)$/i', $host)
        || (bool)preg_match('/^(amzn\.to|a\.co)$/i', $host);
}

/**
 * Amazon link as stored: product links become https://www.amazon.com/dp/ASIN
 * (the store tag is added when the page is shown), short links stay as they are.
 * Returns [url, error|null].
 */
function rocProdAmazon(string $raw): array {
    $raw = trim($raw);
    if ($raw === '') return ['', null];
    $u = parse_url($raw);
    $host = strtolower((string)($u['host'] ?? ''));
    if (!in_array(strtolower((string)($u['scheme'] ?? '')), ['http', 'https'], true) || !rocProdIsAmazonHost($host)) {
        return ['', 'The Amazon link must be an amazon.com (or amzn.to) address.'];
    }
    if (preg_match('#/(?:dp|gp/product|gp/aw/d|product|ASIN)/([A-Z0-9]{10})(?:[/?\#]|$)#i', (string)($u['path'] ?? '') . '/', $m)) {
        $base = preg_match('/amazon\.(.+)$/i', $host, $tld) ? 'https://www.amazon.' . strtolower($tld[1]) : 'https://www.amazon.com';
        return [$base . '/dp/' . strtoupper($m[1]), null];
    }
    return [$raw, null];
}

/** Image: an https URL or a path on this site. Returns [url, error|null]. */
function rocProdImage(string $raw): array {
    $raw = trim($raw);
    if ($raw === '') return ['', null];
    if ($raw[0] === '/' && strpos($raw, '//') !== 0 && !preg_match('/[\s"<>]/', $raw)) return [$raw, null];
    $u = parse_url($raw);
    if (strtolower((string)($u['scheme'] ?? '')) === 'https' && !empty($u['host']) && !preg_match('/[\s"<>]/', $raw)) return [$raw, null];
    return ['', 'The image must be an https:// link or an image from the Media library.'];
}

function rocProdSlug(string $s): string {
    $s = strtolower(trim($s));
    $s = (string)preg_replace('/[^a-z0-9]+/', '-', $s);
    return substr(trim($s, '-'), 0, 180);
}

function rocProdClip($v, int $max): string {
    $v = trim(is_scalar($v) ? (string)$v : '');
    return mb_substr($v, 0, $max);
}

/**
 * Validate the editor form. Returns [columns => values, errors[]].
 * Only columns that exist in the table are returned.
 */
function rocProdFields(PDO $pdo, array $in, array $cols): array {
    $err = [];
    $out = [];
    $out['title'] = rocProdClip($in['title'] ?? '', 255);
    if ($out['title'] === '') $err[] = 'Product name is required.';

    $catSlug = rocProdSlug((string)($in['category_slug'] ?? ''));
    $cats = rocProdCategories($pdo);
    if ($cats) {
        $match = array_values(array_filter($cats, function ($c) use ($catSlug) { return $c['slug'] === $catSlug; }));
        if (!$match) $err[] = 'Choose a category.';
        else { $out['category_slug'] = $catSlug; $out['category'] = $match[0]['name']; }
    } elseif (isset($in['category'])) {
        $out['category'] = rocProdClip($in['category'], 120);
    }

    foreach (['brand' => 120, 'subtitle' => 255, 'badge' => 120, 'best_for' => 255, 'image_alt' => 255,
              'meta_title' => 255, 'meta_description' => 500] as $k => $max) {
        if (array_key_exists($k, $in)) $out[$k] = rocProdClip($in[$k], $max);
    }
    if (array_key_exists('short_desc', $in)) {
        $out['short_desc'] = rocProdClip($in['short_desc'], 2000);
        $out['summary'] = $out['short_desc'];
    }

    if (array_key_exists('features', $in)) {
        $f = is_array($in['features']) ? $in['features'] : preg_split('/\r\n|\r|\n/', (string)$in['features']);
        $f = array_values(array_filter(array_map(function ($x) { return rocProdClip($x, 80); }, $f ?: []), 'strlen'));
        $out['features_json'] = json_encode(array_slice($f, 0, 12), JSON_UNESCAPED_UNICODE);
    }
    if (array_key_exists('specs', $in)) {
        $specs = [];
        $rows = is_array($in['specs']) ? $in['specs'] : [];
        foreach ($rows as $k => $v) {
            if (is_array($v)) { $k = $v['key'] ?? ''; $v = $v['value'] ?? ''; }
            $k = rocProdClip($k, 60); $v = rocProdClip($v, 200);
            if ($k !== '' && $v !== '') $specs[$k] = $v;
        }
        $out['specs_json'] = json_encode(array_slice($specs, 0, 20, true), JSON_UNESCAPED_UNICODE);
    }

    if (array_key_exists('affiliate_amazon', $in)) {
        [$amz, $e] = rocProdAmazon((string)$in['affiliate_amazon']);
        if ($e) $err[] = $e; else $out['affiliate_amazon'] = $amz;
    }
    if (array_key_exists('image', $in)) {
        [$img, $e] = rocProdImage((string)$in['image']);
        if ($e) $err[] = $e; else $out['image'] = $img;
    }
    if (array_key_exists('sort_rank', $in)) $out['sort_rank'] = max(0, min(9999, (int)$in['sort_rank']));
    if (array_key_exists('is_noindex', $in)) $out['is_noindex'] = !empty($in['is_noindex']) ? 1 : 0;

    return [array_intersect_key($out, array_flip($cols)), $err];
}


if ($method === 'GET') {
    if (isset($_GET['categories'])) {
        echo json_encode(['success' => true, 'categories' => rocProdCategories($pdo)]);
        exit();
    }
    $id = $_GET['id'] ?? null;
    if ($id !== null && $id !== '') {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? LIMIT 1");
        $stmt->execute([(string)$id]);
        $prod = $stmt->fetch();

        if (!$prod) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Product not found.']);
            exit();
        }

        echo json_encode(['success' => true, 'product' => $prod]);
        exit();
    }

    $cols = rocProdColumns($pdo);
    $want = ['id', 'title', 'slug', 'category', 'price', 'price_state', 'rating', 'image', 'status', 'draft_status', 'version', 'is_noindex', 'updated_at', 'affiliate_amazon', 'short_desc'];
    $sel = implode(', ', array_map(function ($c) { return "`{$c}`"; }, array_values(array_intersect($want, $cols))));
    $stmt = $pdo->query("SELECT {$sel} FROM products ORDER BY category, title");
    $products = $stmt->fetchAll();
    echo json_encode(['success' => true, 'products' => $products]);
    exit();
}

if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true) ?? $_POST;
    $action = $data['action'] ?? 'save_draft';

    $id = (string)($data['id'] ?? '');
    if (empty($id) && $action !== 'create' && $action !== 'save_product') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Product ID is required.']);
        exit();
    }

    if ($action === 'create') {
        $title = trim($data['title'] ?? 'New Product');
        $idStr = 'prod-' . bin2hex(random_bytes(4));
        $slug = rocAgentSlugify($title) ?: $idStr;

        $stmtIns = $pdo->prepare("INSERT INTO products (id, title, slug, category, status, draft_status, version, created_at, updated_at) VALUES (?, ?, ?, 'general', 'draft', 'draft_saved', 1, NOW(), NOW())");
        $stmtIns->execute([$idStr, $title, $slug]);

        logCmsAudit('cms_product_create', 'product', $idStr, ['title' => $title]);

        echo json_encode(['success' => true, 'id' => $idStr, 'slug' => $slug, 'version' => 1]);
        exit();
    }

    // Product editor: create or update every field in one go.
    if ($action === 'save_product') {
        $cols = rocProdColumns($pdo);
        [$fields, $errors] = rocProdFields($pdo, is_array($data['fields'] ?? null) ? $data['fields'] : [], $cols);
        $status = $data['status'] ?? null;
        if ($status !== null) {
            $status = $status === 'published' ? 'published' : 'draft';
            rocCmsAuthorize($session, 'products', 'publish');
        }
        if ($errors) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => implode(' ', $errors), 'errors' => $errors]);
            exit();
        }

        $now = date('Y-m-d H:i:s');
        $pdo->beginTransaction();
        try {
            if ($id === '') {
                // New product: the URL (slug) is set once from the name and never changes.
                $base = rocProdSlug((string)($data['slug'] ?? '')) ?: rocProdSlug($fields['title']);
                if ($base === '') $base = 'product';
                $slug = $base;
                $chk = $pdo->prepare('SELECT COUNT(*) FROM products WHERE slug = ?');
                for ($n = 2; ; $n++) { $chk->execute([$slug]); if (!(int)$chk->fetchColumn()) break; $slug = $base . '-' . $n; }
                $id = $slug;
                $chkId = $pdo->prepare('SELECT COUNT(*) FROM products WHERE id = ?');
                $chkId->execute([$id]);
                if ((int)$chkId->fetchColumn()) $id = 'prod-' . bin2hex(random_bytes(4));
                $row = $fields + ['id' => $id, 'slug' => $slug, 'status' => $status ?? 'draft', 'draft_status' => 'none',
                                  'version' => 1, 'created_at' => $now, 'updated_at' => $now, 'content_modified_at' => $now];
                $row = array_intersect_key($row, array_flip($cols));
                $names = array_keys($row);
                $pdo->prepare('INSERT INTO products (' . implode(', ', $names) . ') VALUES (' . implode(', ', array_fill(0, count($names), '?')) . ')')
                    ->execute(array_values($row));
                $newVersion = 1;
                $logAction = 'cms_product_create';
            } else {
                $expectedVersion = (int)($data['version'] ?? 1);
                $set = $fields + ['updated_at' => $now, 'content_modified_at' => $now, 'draft_status' => 'none'];
                if ($status !== null) $set['status'] = $status;
                $set = array_intersect_key($set, array_flip($cols));
                $sql = 'UPDATE products SET ' . implode(', ', array_map(function ($c) { return "{$c} = ?"; }, array_keys($set)))
                     . ', version = version + 1 WHERE id = ? AND version = ?';
                $st = $pdo->prepare($sql);
                $st->execute([...array_values($set), $id, $expectedVersion]);
                if ($st->rowCount() === 0) {
                    $pdo->rollBack();
                    http_response_code(409);
                    echo json_encode(['success' => false, 'error' => 'This product was changed by someone else. Reload and try again.']);
                    exit();
                }
                $newVersion = $expectedVersion + 1;
                $logAction = 'cms_product_update';
            }

            try {
                $pdo->prepare("INSERT INTO cms_revisions (content_type, content_id, version_num, data_json, created_by, created_at) VALUES ('product', ?, ?, ?, ?, ?)")
                    ->execute([$id, $newVersion, json_encode($fields, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $session['user_id'] ?? null, $now]);
            } catch (\Throwable $e) { /* revisions are optional */ }

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Save failed: ' . $e->getMessage()]);
            exit();
        }

        logCmsAudit($logAction, 'product', $id, ['version' => $newVersion, 'title' => $fields['title']]);
        $st = $pdo->prepare('SELECT * FROM products WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Product saved.', 'product' => $st->fetch(PDO::FETCH_ASSOC)]);
        exit();
    }

    // Show / hide on the website. Hidden products return 404 and leave the sitemap.
    if ($action === 'set_status') {
        $status = ($data['status'] ?? '') === 'published' ? 'published' : 'draft';
        rocCmsAuthorize($session, 'products', 'publish');
        $expectedVersion = (int)($data['version'] ?? 1);
        // Showing a product again counts as a content change (new sitemap lastmod); hiding does not.
        $touch = $status === 'published' ? ', content_modified_at = NOW()' : '';
        $stmt = $pdo->prepare("UPDATE products SET status = ?, version = version + 1, updated_at = NOW(){$touch} WHERE id = ? AND version = ?");
        $stmt->execute([$status, $id, $expectedVersion]);
        if ($stmt->rowCount() === 0) {
            http_response_code(409);
            echo json_encode(['success' => false, 'error' => 'This product was changed by someone else. Reload and try again.']);
            exit();
        }
        logCmsAudit($status === 'published' ? 'cms_product_show' : 'cms_product_hide', 'product', $id, ['version' => $expectedVersion + 1]);
        echo json_encode(['success' => true, 'status' => $status, 'version' => $expectedVersion + 1]);
        exit();
    }

    if ($action === 'save_draft') {
        $expectedVersion = (int)($data['version'] ?? 1);
        $draftPayload = $data['draft'] ?? $data;
        $draftJson = json_encode($draftPayload, JSON_UNESCAPED_SLASHES);

        $stmtUpd = $pdo->prepare("UPDATE products SET draft_data_json = ?, draft_status = 'draft_saved', version = version + 1, updated_at = NOW() WHERE id = ? AND version = ?");
        $stmtUpd->execute([$draftJson, $id, $expectedVersion]);

        if ($stmtUpd->rowCount() === 0) {
            http_response_code(409);
            echo json_encode(['success' => false, 'error' => 'Conflict: Product modified by another user. Please reload.']);
            exit();
        }

        $newVersion = $expectedVersion + 1;
        logCmsAudit('cms_product_save_draft', 'product', $id, ['version' => $newVersion]);

        echo json_encode(['success' => true, 'message' => 'Product draft saved successfully.', 'version' => $newVersion]);
        exit();
    }

    if ($action === 'publish') {
        $expectedVersion = (int)($data['version'] ?? 1);
        $draftPayload = $data['draft'] ?? $data;

        $title = trim($draftPayload['title'] ?? '');
        $category = $draftPayload['category'] ?? 'general';
        $shortDesc = $draftPayload['short_desc'] ?? '';
        $summary = $draftPayload['summary'] ?? $shortDesc;
        $price = isset($draftPayload['price']) && $draftPayload['price'] !== '' ? (float)$draftPayload['price'] : null;
        $priceState = ($draftPayload['price_state'] ?? 'set') === 'unknown' ? 'unknown' : 'set';
        $rating = isset($draftPayload['rating']) ? (float)$draftPayload['rating'] : 4.80;
        $image = $draftPayload['image'] ?? null;
        $pros = $draftPayload['pros'] ?? null;
        $cons = $draftPayload['cons'] ?? null;
        $affiliateAmazon = $draftPayload['affiliate_amazon'] ?? null;
        $affiliateBestbuy = $draftPayload['affiliate_bestbuy'] ?? null;
        $affiliateOfficial = $draftPayload['affiliate_official'] ?? null;
        $specsJson = isset($draftPayload['specs']) ? json_encode($draftPayload['specs'], JSON_UNESCAPED_SLASHES) : null;
        $isNoindex = !empty($draftPayload['is_noindex']) ? 1 : 0;

        if (empty($title)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Product title is required.']);
            exit();
        }

        $pdo->beginTransaction();

        try {
            $stmtPub = $pdo->prepare("UPDATE products SET title = ?, category = ?, short_desc = ?, summary = ?, specs_json = ?, price = ?, price_state = ?, rating = ?, image = ?, pros = ?, cons = ?, affiliate_amazon = ?, affiliate_bestbuy = ?, affiliate_official = ?, last_verified_at = NOW(), is_noindex = ?, status = 'published', draft_status = 'none', content_modified_at = NOW(), version = version + 1, updated_at = NOW() WHERE id = ? AND version = ?");
            $stmtPub->execute([$title, $category, $shortDesc, $summary, $specsJson, $price, $priceState, $rating, $image, $pros, $cons, $affiliateAmazon, $affiliateBestbuy, $affiliateOfficial, $isNoindex, $id, $expectedVersion]);

            if ($stmtPub->rowCount() === 0) {
                $pdo->rollBack();
                http_response_code(409);
                echo json_encode(['success' => false, 'error' => 'Conflict: Product modified by another user. Please reload.']);
                exit();
            }

            $newVersion = $expectedVersion + 1;

            // Revision snapshot
            $stmtRev = $pdo->prepare("INSERT INTO cms_revisions (content_type, content_id, version_num, data_json, created_by, created_at) VALUES ('product', ?, ?, ?, ?, NOW())");
            $stmtRev->execute([$id, $newVersion, json_encode($draftPayload, JSON_UNESCAPED_SLASHES), $session['user_id']]);

            // Durable Queue Task
            $stmtQ = $pdo->prepare("INSERT INTO cms_cache_queue (content_type, content_id, target_version_id, action, status, created_at) VALUES ('product', ?, ?, 'purge_and_prerender', 'pending', NOW())");
            $stmtQ->execute([$id, $newVersion]);

            $pdo->commit();

            logCmsAudit('cms_product_publish', 'product', $id, ['version' => $newVersion, 'title' => $title]);

            echo json_encode(['success' => true, 'message' => 'Product published successfully.', 'version' => $newVersion]);
            exit();

        } catch (\Throwable $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Publish failed: ' . $e->getMessage()]);
            exit();
        }
    }
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
