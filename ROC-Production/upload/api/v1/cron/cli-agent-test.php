<?php
/**
 * Run On Console — test ROC's AI from the server (Deploy 58).
 *
 *   php cli-agent-test.php                       models this Groq key can use + one test question
 *   php cli-agent-test.php "kya haal hai"        your own question
 *
 * Shows which models ROC will use, the answer, and the error of every model that did not answer
 * (the same errors CMS > ROC assistant shows). Nothing is saved in the chat log.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$ROOT = dirname(__DIR__, 3);
ob_start();
require_once $ROOT . '/api/v1/config.php';
ob_end_clean();
$pdo = function_exists('getDBConnection') ? getDBConnection() : null;
if (!$pdo) exit("Database unavailable.\n");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
require_once $ROOT . '/api/v1/cms/blog-engine-lib.php';
require_once $ROOT . '/api/v1/agent-lib.php';

echo "Run On Console - ROC AI test\n\n";
$key = trim((string)rocBlogGet($pdo, 'blog_groq_key', ''));
if ($key === '') exit("No Groq key saved (CMS > Blog & news writer). ROC can only use its fixed sentences.\n");
$m = rocAgentModels($pdo, $key, true);
if (!empty($m['error'])) echo "  !  Groq model list: {$m['error']}\n";
echo '  Chat models:     ' . implode(', ', $m['chat']) . "\n";
echo '  Web search:      ' . (implode(', ', $m['web']) ?: 'none on this key') . "\n";
echo '  Pictures:        ' . (implode(', ', $m['vision']) ?: 'none on this key') . "\n\n";

$q = trim((string)($argv[1] ?? '')) ?: 'Salam! kya haal hai? GTA 5 mere PC par chalega? GTX 1650, i5 8th gen, 8GB RAM';
rocBlogSet($pdo, 'agent_last_error', null);
$t = microtime(true);
$a = rocAgentAnswer($pdo, $ROOT, [['role' => 'user', 'content' => $q]], true);
echo "  Question: {$q}\n  Engine:   {$a['engine']} (" . round(microtime(true) - $t, 1) . " s)\n  Answer:   {$a['reply']}\n";
$e = rocBlogGet($pdo, 'agent_last_error', null);
if (is_array($e) && !empty($e['errors'])) echo "\n  Models that did not answer:\n    - " . implode("\n    - ", $e['errors']) . "\n";
echo $a['engine'] === 'basic' ? "\nThe AI did not answer: see the errors above.\n" : "\nThe AI works.\n";
