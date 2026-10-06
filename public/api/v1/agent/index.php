<?php
/**
 * Run On Console (ROC) - ROC Agent Central REST API Router
 * Dispatcher mapping all /api/v1/agent/ endpoints cleanly.
 */

require_once __DIR__ . '/config-agent.php';
require_once __DIR__ . '/products.php';
require_once __DIR__ . '/categories.php';
require_once __DIR__ . '/blogs.php';
require_once __DIR__ . '/games.php';
require_once __DIR__ . '/search.php';
require_once __DIR__ . '/devices.php';
require_once __DIR__ . '/check-compatibility.php';
require_once __DIR__ . '/write-for-us.php';
require_once __DIR__ . '/conversations.php';

// Parse endpoint path
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$path = trim(str_replace('/api/v1/agent', '', $uri), '/');
$parts = array_values(array_filter(explode('/', $path)));
$resource = $parts[0] ?? '';
$subParam = $parts[1] ?? null;

// Clean extension if present (.php)
$resource = str_replace('.php', '', strtolower($resource));

switch ($resource) {
    case 'products':
        $id = $subParam ?? $_GET['id'] ?? null;
        $cat = $_GET['category'] ?? null;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 3;
        rocAgentJsonOutput(rocAgentGetProducts($id, $cat, $limit));
        break;

    case 'categories':
        rocAgentJsonOutput(rocAgentGetCategories());
        break;

    case 'blogs':
        $cat = $_GET['category'] ?? null;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 5;
        rocAgentJsonOutput(rocAgentGetBlogs($cat, $limit));
        break;

    case 'games':
        $id = $subParam ?? $_GET['id'] ?? null;
        $q = $_GET['q'] ?? null;
        rocAgentJsonOutput(rocAgentGetGames($id, $q));
        break;

    case 'search':
        $q = $_GET['q'] ?? '';
        rocAgentJsonOutput(rocAgentUnifiedSearch($q));
        break;

    case 'devices':
        rocAgentJsonOutput(rocAgentHandleDevices());
        break;

    case 'check-compatibility':
        rocAgentJsonOutput(rocAgentCheckCompatibility());
        break;

    case 'write-for-us':
        rocAgentJsonOutput(rocAgentHandleWriteForUs());
        break;

    case 'conversations':
        rocAgentJsonOutput(rocAgentHandleConversations());
        break;

    default:
        rocAgentJsonOutput([
            'success' => true,
            'service' => 'Run On Console (ROC) Agent API v1',
            'status' => 'online',
            'greeting' => "Hi, I’m ROC Agent. I’m built to make gaming and shopping easier for you. How can I help?",
            'endpoints' => [
                'GET /api/v1/agent/products',
                'GET /api/v1/agent/products/{id}',
                'GET /api/v1/agent/categories',
                'GET /api/v1/agent/blogs',
                'GET /api/v1/agent/games',
                'GET /api/v1/agent/games/{id}',
                'GET /api/v1/agent/search?q=',
                'GET /api/v1/agent/devices',
                'POST /api/v1/agent/devices',
                'POST /api/v1/agent/check-compatibility',
                'POST /api/v1/agent/write-for-us',
                'GET/POST /api/v1/agent/conversations'
            ]
        ]);
        break;
}
