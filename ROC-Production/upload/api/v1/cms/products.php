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

if ($method === 'GET') {
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

    $cols = array_map('strtolower', $pdo->query('SHOW COLUMNS FROM products')->fetchAll(PDO::FETCH_COLUMN));
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
    if (empty($id) && $action !== 'create') {
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
