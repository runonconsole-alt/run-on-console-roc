<?php
/**
 * Run On Console (ROC) - CMS Site Settings API
 * GET  /api/v1/cms/settings.php
 * POST /api/v1/cms/settings.php (Update robots.txt, llms.txt, nav links)
 */

require_once __DIR__ . '/config.php';

$session = requireCmsSession('administrator'); // Admin Only
$pdo = getDBConnection();

if (!$pdo) {
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'Database connection unavailable.']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $stmt = $pdo->query("SELECT setting_key, setting_value, updated_at FROM cms_settings");
    $settingsRaw = $stmt->fetchAll();
    $settings = [];
    foreach ($settingsRaw as $s) {
        $settings[$s['setting_key']] = $s['setting_value'];
    }

    // Default fallbacks from disk files
    if (!isset($settings['robots_txt'])) {
        $robotsPath = dirname(__DIR__, 3) . '/robots.txt';
        $settings['robots_txt'] = file_exists($robotsPath) ? file_get_contents($robotsPath) : "User-agent: *\nAllow: /\nSitemap: https://runonconsole.com/sitemap.xml";
    }

    if (!isset($settings['llms_txt'])) {
        $llmsPath = dirname(__DIR__, 3) . '/llms.txt';
        $settings['llms_txt'] = file_exists($llmsPath) ? file_get_contents($llmsPath) : "# Run On Console LLM Guide\nTitle: Run On Console";
    }

    echo json_encode(['success' => true, 'settings' => $settings]);
    exit();
}

if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true) ?? $_POST;

    $stmtUpd = $pdo->prepare("INSERT INTO cms_settings (setting_key, setting_value, updated_by, updated_at) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by), updated_at = NOW()");

    foreach ($data as $key => $val) {
        if (is_string($key) && !empty($key)) {
            $valStr = is_array($val) ? json_encode($val, JSON_UNESCAPED_SLASHES) : (string)$val;
            $stmtUpd->execute([$key, $valStr, $session['user_id']]);

            // Update disk files for robots.txt and llms.txt
            if ($key === 'robots_txt') {
                @file_put_contents(dirname(__DIR__, 3) . '/robots.txt', $valStr);
            } elseif ($key === 'llms_txt') {
                @file_put_contents(dirname(__DIR__, 3) . '/llms.txt', $valStr);
            }
        }
    }

    logCmsAudit('cms_settings_update', 'settings', 'site', ['updated_keys' => array_keys($data)]);

    echo json_encode(['success' => true, 'message' => 'Site settings saved successfully.']);
    exit();
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
