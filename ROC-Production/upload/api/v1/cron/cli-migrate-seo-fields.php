<?php
/**
 * Run On Console CMS — SEO field migration
 *
 * Adds the Open Graph, Twitter, canonical, schema and alt-text columns that the
 * new editor writes and index.php renders.
 *
 * Follows the same conventions as cli-migrate-cms.php: CLI only, and it makes no
 * change at all unless --apply is passed.
 *
 *   php cli-migrate-seo-fields.php            # dry run, shows what it would do
 *   php cli-migrate-seo-fields.php --apply    # actually applies
 *
 * Every step is checked against information_schema first, so running it twice is
 * harmless and it works on MySQL 5.7 as well as 8.x and MariaDB.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This migration runs from the command line only.\n");
}

$isApply = in_array('--apply', $argv, true);

// config.php sits two levels up when this file lives in api/v1/cron/
$configCandidates = [
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../api/v1/config.php',
    __DIR__ . '/config.php',
];
$loaded = false;
foreach ($configCandidates as $candidate) {
    if (file_exists($candidate)) {
        require_once $candidate;
        $loaded = true;
        break;
    }
}
if (!$loaded) {
    exit("Could not find config.php. Put this script in public/api/v1/cron/ and run it from there.\n");
}

$pdo = getDBConnection();
if (!$pdo) {
    exit("Database connection failed. Check the credentials in config.php.\n");
}
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
echo "\nDatabase: {$dbName}\n";
echo $isApply ? "Mode: APPLY\n\n" : "Mode: DRY RUN (pass --apply to make changes)\n\n";

/** Columns to add, per table. Order matters only for readability. */
$COLUMNS = [
    'blogs' => [
        'image_alt'           => "VARCHAR(500) NOT NULL DEFAULT ''",
        'canonical_url'       => "VARCHAR(2048) NOT NULL DEFAULT ''",
        'focus_keyword'       => "VARCHAR(200) NOT NULL DEFAULT ''",
        'is_nofollow'         => "TINYINT(1) NOT NULL DEFAULT 0",
        'og_title'            => "VARCHAR(255) NOT NULL DEFAULT ''",
        'og_description'      => "VARCHAR(500) NOT NULL DEFAULT ''",
        'og_image'            => "VARCHAR(2048) NOT NULL DEFAULT ''",
        'og_type'             => "VARCHAR(40) NOT NULL DEFAULT ''",
        'twitter_card'        => "VARCHAR(40) NOT NULL DEFAULT ''",
        'twitter_title'       => "VARCHAR(255) NOT NULL DEFAULT ''",
        'twitter_description' => "VARCHAR(500) NOT NULL DEFAULT ''",
        'twitter_image'       => "VARCHAR(2048) NOT NULL DEFAULT ''",
        'schema_type'         => "VARCHAR(60) NOT NULL DEFAULT ''",
        'schema_json'         => "MEDIUMTEXT NULL",
    ],
    'products' => [
        'image_alt'           => "VARCHAR(500) NOT NULL DEFAULT ''",
        'canonical_url'       => "VARCHAR(2048) NOT NULL DEFAULT ''",
        'is_noindex'          => "TINYINT(1) NOT NULL DEFAULT 0",
        'is_nofollow'         => "TINYINT(1) NOT NULL DEFAULT 0",
        'og_title'            => "VARCHAR(255) NOT NULL DEFAULT ''",
        'og_description'      => "VARCHAR(500) NOT NULL DEFAULT ''",
        'og_image'            => "VARCHAR(2048) NOT NULL DEFAULT ''",
        'twitter_card'        => "VARCHAR(40) NOT NULL DEFAULT ''",
        'twitter_title'       => "VARCHAR(255) NOT NULL DEFAULT ''",
        'twitter_description' => "VARCHAR(500) NOT NULL DEFAULT ''",
        'twitter_image'       => "VARCHAR(2048) NOT NULL DEFAULT ''",
        'schema_type'         => "VARCHAR(60) NOT NULL DEFAULT ''",
        'schema_json'         => "MEDIUMTEXT NULL",
    ],
    'cms_media' => [
        'alt_text' => "VARCHAR(500) NOT NULL DEFAULT ''",
        'caption'  => "VARCHAR(300) NOT NULL DEFAULT ''",
    ],
];

$INDEXES = [
    'blogs'    => ['idx_blogs_slug_status'    => '(slug, status)'],
    'products' => ['idx_products_slug_status' => '(slug, status)'],
];

function tableExists(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.tables
          WHERE table_schema = DATABASE() AND table_name = ?'
    );
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

function columnExists(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.columns
          WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
    );
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function indexExists(PDO $pdo, string $table, string $index): bool {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.statistics
          WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?'
    );
    $stmt->execute([$table, $index]);
    return (int)$stmt->fetchColumn() > 0;
}

$added = 0;
$skipped = 0;
$missingTables = [];

foreach ($COLUMNS as $table => $columns) {
    if (!tableExists($pdo, $table)) {
        $missingTables[] = $table;
        echo "  - table `{$table}` not found, skipping its columns\n";
        continue;
    }
    echo "Table `{$table}`\n";
    foreach ($columns as $column => $definition) {
        if (columnExists($pdo, $table, $column)) {
            echo "    = {$column} already present\n";
            $skipped++;
            continue;
        }
        $sql = "ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}";
        if ($isApply) {
            $pdo->exec($sql);
            echo "    + {$column} added\n";
        } else {
            echo "    + would add {$column}  ({$definition})\n";
        }
        $added++;
    }
    echo "\n";
}

foreach ($INDEXES as $table => $indexes) {
    if (!tableExists($pdo, $table)) continue;
    foreach ($indexes as $name => $cols) {
        if (indexExists($pdo, $table, $name)) {
            echo "Index {$name} already present\n";
            continue;
        }
        $sql = "CREATE INDEX `{$name}` ON `{$table}` {$cols}";
        if ($isApply) {
            $pdo->exec($sql);
            echo "Index {$name} created\n";
        } else {
            echo "Would create index {$name} on {$table} {$cols}\n";
        }
    }
}

// Backfill OG defaults so no published post ends up with an empty share card.
if ($isApply && tableExists($pdo, 'blogs') && columnExists($pdo, 'blogs', 'og_title')) {
    $backfill = $pdo->exec("
        UPDATE blogs
           SET og_title       = IF(og_title = '', COALESCE(NULLIF(meta_title, ''), title), og_title),
               og_description = IF(og_description = '', COALESCE(NULLIF(meta_description, ''), COALESCE(excerpt, '')), og_description),
               og_image       = IF(og_image = '', COALESCE(image, ''), og_image),
               og_type        = IF(og_type = '', 'article', og_type),
               twitter_card   = IF(twitter_card = '', 'summary_large_image', twitter_card)
         WHERE status = 'published'
    ");
    echo "\nBackfilled Open Graph defaults on {$backfill} published post(s).\n";
}

echo "\n";
echo $isApply
    ? "Done. {$added} column(s) added, {$skipped} already present.\n"
    : "Dry run finished. {$added} column(s) would be added, {$skipped} already present.\n      Re-run with --apply to make the changes.\n";

if ($missingTables) {
    echo "\nNote: these tables were not found, so nothing was changed for them: "
       . implode(', ', $missingTables) . "\n"
       . "If your media table has a different name, edit the \$COLUMNS array at the top.\n";
}
echo "\n";
