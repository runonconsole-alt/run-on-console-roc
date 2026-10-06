<?php
/**
 * Run On Console — make sure blog posts can be 'scheduled'.
 *
 *   php cli-blog-scheduling.php              dry run: shows the blogs.status column
 *   php cli-blog-scheduling.php --apply      adds 'scheduled' (and 'archived', used by Delete) if missing
 *   php cli-blog-scheduling.php --rollback   removes 'scheduled' again (only if no post uses it)
 *
 * Nothing else changes: existing values, the default and NULL/NOT NULL are kept.
 * A VARCHAR status column needs no change.
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$ROOT = dirname(__DIR__, 3);
$args = array_slice($argv, 1);
ob_start();
require_once $ROOT . '/api/v1/config.php';
ob_end_clean();
$pdo = function_exists('getDBConnection') ? getDBConnection() : null;
if (!$pdo) exit("STOP: could not connect to the database.\n");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "Run On Console - blog scheduling\n\n";

$col = $pdo->query("SHOW COLUMNS FROM blogs LIKE 'status'")->fetch(PDO::FETCH_ASSOC);
if (!$col) exit("STOP: blogs.status not found.\n");
$type = (string)$col['Type'];
echo "blogs.status: {$type}, " . ($col['Null'] === 'YES' ? 'NULL' : 'NOT NULL') . ', default ' . var_export($col['Default'], true) . "\n";
foreach ($pdo->query('SELECT status, COUNT(*) AS n FROM blogs GROUP BY status')->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "  {$r['n']} post(s) with status '{$r['status']}'\n";
}

if (!preg_match('/^enum\((.*)\)$/i', $type, $m)) {
    echo "\nNot an ENUM: 'scheduled' already works. Nothing to do.\n";
    exit(0);
}
// Status values are plain words (draft, published, …): no quotes or backslashes inside.
preg_match_all("/'([^']*)'/", $m[1], $vals);
$values = $vals[1];
$has = in_array('scheduled', $values, true);
// 'archived' is what the CMS Delete button sets; add it too if this column lacks it.
$missing = array_values(array_diff(['scheduled', 'archived'], $values));

function enumSql(array $values, array $col): string {
    $list = implode(',', array_map(function ($v) { return "'" . str_replace("'", "''", $v) . "'"; }, $values));
    $sql = "ALTER TABLE blogs MODIFY status ENUM({$list}) " . ($col['Null'] === 'YES' ? 'NULL' : 'NOT NULL');
    if ($col['Default'] !== null) $sql .= " DEFAULT '" . str_replace("'", "''", (string)$col['Default']) . "'";
    elseif ($col['Null'] === 'YES') $sql .= ' DEFAULT NULL';
    return $sql;
}

if (in_array('--rollback', $args, true)) {
    if (!$has) exit("\n'scheduled' is not in the list. Nothing to undo.\n");
    $n = (int)$pdo->query("SELECT COUNT(*) FROM blogs WHERE status = 'scheduled'")->fetchColumn();
    if ($n) exit("\nSTOP: {$n} post(s) are scheduled. Publish or unschedule them first.\n");
    $sql = enumSql(array_values(array_diff($values, ['scheduled'])), $col);
    $pdo->exec($sql);
    exit("\nDone: {$sql}\n");
}

if (!$missing) { echo "\n'scheduled' and 'archived' are already allowed. Nothing to do.\n"; exit(0); }

$sql = enumSql(array_merge($values, $missing), $col);
echo "\nMissing: " . implode(', ', $missing) . "\nWill run:\n  {$sql}\n";
if (!in_array('--apply', $args, true)) { echo "\nDRY RUN - nothing changed. Run again with --apply.\n"; exit(0); }
$pdo->exec($sql);
echo "\nDone. Rollback: php " . __FILE__ . " --rollback\n";
