<?php
/**
 * Run On Console — CMS > Live activity: logins, sign-up codes, password resets and every form message with
 * the person's name and email (form-activity-lib.php). Administrators only.
 *
 *   GET  form-activity.php   {success, items: [{type, at, title, detail, link}], counts_24h}
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/form-activity-lib.php';
$session = requireCmsAdmin();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$pdo = getDBConnection();
if (!$pdo) { http_response_code(503); echo json_encode(['success' => false, 'error' => 'Database connection unavailable.']); exit; }
$items = rocFaItems($pdo);
$day = date('Y-m-d H:i:s', time() - 86400);
$counts = [];
foreach ($items as $i) if ($i['at'] > $day) $counts[$i['type']] = ($counts[$i['type']] ?? 0) + 1;
echo json_encode(['success' => true, 'items' => $items, 'counts_24h' => $counts ?: new stdClass()], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
