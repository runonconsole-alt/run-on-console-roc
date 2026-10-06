import { ALL_PRODUCTS, ALL_BLOGS } from '../src/data/initialData.js';
import { slugify } from '../src/seo/routeRegistry.js';
import fs from 'fs';
import path from 'path';

const ROOT_DIR = process.cwd();

console.log('Building backend PHP content seeder and backfill files...');

function escapePhp(str) {
  if (str === null || str === undefined) return '';
  return String(str).replace(/\\/g, '\\\\').replace(/'/g, "\\'");
}

// 1. Format Products for PHP
const phpProductsCode = ALL_PRODUCTS.map(p => {
  const aff = p.affiliateLinks || {};
  const summary = p.shortDesc || p.summary || p.fullReview || '';
  const prosJson = JSON.stringify(p.pros || []);
  const consJson = JSON.stringify(p.cons || []);
  const rating = typeof p.rating === 'number' ? p.rating : parseFloat(p.rating || '4.5');

  return `        [
            'id' => '${escapePhp(p.id)}',
            'title' => '${escapePhp(p.title)}',
            'category' => '${escapePhp(p.category)}',
            'price' => '${escapePhp(p.price)}',
            'rating' => ${rating},
            'image' => '${escapePhp(p.image)}',
            'summary' => '${escapePhp(summary)}',
            'pros' => '${escapePhp(prosJson)}',
            'cons' => '${escapePhp(consJson)}',
            'affiliate_amazon' => '${escapePhp(aff.amazon || '')}',
            'affiliate_bestbuy' => '${escapePhp(aff.bestbuy || '')}',
            'affiliate_official' => '${escapePhp(aff.official || '')}'
        ]`;
}).join(',\n');

// 2. Format Blogs for PHP according to exact baseline schema:
// id, title, slug, category, read_time, author_name, image, excerpt, content, views_count, status
const phpBlogsCode = ALL_BLOGS.map(b => {
  const slug = slugify(b.title);
  const excerpt = b.summary || '';
  const content = b.content || '';
  const authorName = b.author || 'Omar Abobakar';
  const readTime = b.readTime || '7 min read';

  return `        [
            'id' => '${escapePhp(b.id)}',
            'title' => '${escapePhp(b.title)}',
            'slug' => '${escapePhp(slug)}',
            'category' => '${escapePhp(b.category)}',
            'read_time' => '${escapePhp(readTime)}',
            'author_name' => '${escapePhp(authorName)}',
            'image' => '${escapePhp(b.image)}',
            'excerpt' => '${escapePhp(excerpt)}',
            'content' => '${escapePhp(content)}',
            'views_count' => 0,
            'status' => 'draft'
        ]`;
}).join(',\n');

const seedContentPhpContent = `<?php
/**
 * Run On Console (ROC) - Staging-Only Database Content Seeder Script
 * CLI execution only. Populates products and draft blogs directly from initialData.js dataset.
 *
 * Safety & Schema Rules:
 * 1. Aborts unless SELECT DATABASE() returns 'runoncon_rocstage'.
 * 2. Preview mode by default (php api/v1/cron/seed-roc-agent-content.php).
 * 3. Database writes strictly require the literal CLI --confirm flag.
 * 4. Blog table uses exact baseline schema: id, title, slug, category, read_time, author_name, image, excerpt, content, views_count, status, created_at.
 * 5. On duplicate key update for blogs, both status and views_count are EXCLUDED so published status and view counts are NEVER overwritten on rerun.
 * 6. All writes wrapped in PDO transaction with rollback on Throwable.
 */

if (php_sapi_name() !== 'cli' && empty($_SERVER['ROC_CLI_RUN'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden. CLI execution required.']);
    exit(1);
}

require_once dirname(__DIR__) . '/config.php';

function rocAgentSeedContent(): void {
    $args = $_SERVER['argv'] ?? [];
    $isConfirmed = in_array('--confirm', $args, true);

    echo "============================================================\\n";
    echo "RUN ON CONSOLE (ROC) - STAGING CONTENT SEEDER\\n";
    echo "============================================================\\n\\n";

    $pdo = getDBConnection();
    if (!$pdo) {
        echo "❌ Error: Database connection unavailable.\\n";
        exit(1);
    }

    // 1. Strict Database Isolation Guard: Must be runoncon_rocstage
    $dbStmt = $pdo->query("SELECT DATABASE()");
    $currentDb = $dbStmt ? $dbStmt->fetchColumn() : null;

    if ($currentDb !== 'runoncon_rocstage') {
        echo "❌ ABORTED: Script is strictly restricted to staging database 'runoncon_rocstage'.\\n";
        echo "   Current database is: '" . ($currentDb ?: 'NONE') . "'\\n";
        echo "   This script will NEVER write to production or any other database.\\n";
        exit(1);
    }
    echo "✓ Verified Database Isolation: Connected to '{$currentDb}'\\n";

    if (!$isConfirmed) {
        echo "⚠️ PREVIEW MODE ONLY (No database writes performed).\\n";
        echo "   To apply seeding, run with explicit approval flag:\\n";
        echo "   php api/v1/cron/seed-roc-agent-content.php --confirm\\n\\n";
    } else {
        echo "🚀 CONFIRMED WRITE MODE ENABLED (--confirm flag provided).\\n\\n";
    }

    // 2. Canonical Products Dataset (${ALL_PRODUCTS.length} Products directly from src/data/initialData.js)
    $productsSeed = [
${phpProductsCode}
    ];

    // 3. Canonical Blogs Dataset (${ALL_BLOGS.length} Blogs directly from src/data/initialData.js matching baseline schema)
    $blogsSeed = [
${phpBlogsCode}
    ];

    echo "📊 PREVIEW SUMMARY OF CANONICAL DATA TO SEED:\\n";
    echo "------------------------------------------------------------\\n";
    echo "Products Count: " . count($productsSeed) . " (Source: src/data/initialData.js ALL_PRODUCTS)\\n";
    foreach ($productsSeed as $p) {
        echo "  - Product ID [{$p['id']}]: {$p['title']} ({$p['category']} - {$p['price']})\\n";
    }

    echo "\\nBlogs Count: " . count($blogsSeed) . " (Source: src/data/initialData.js ALL_BLOGS with baseline schema mapping)\\n";
    foreach ($blogsSeed as $b) {
        echo "  - Blog ID [{$b['id']}]: {$b['title']} -> Slug: {$b['slug']} (Author: {$b['author_name']}, ReadTime: {$b['read_time']}, Status: {$b['status']})\\n";
    }
    echo "------------------------------------------------------------\\n\\n";

    if (!$isConfirmed) {
        echo "✓ Preview completed successfully. No changes made to database.\\n";
        return;
    }

    // 4. Executing Confirmed Database Writes inside PDO Transaction
    try {
        $pdo->beginTransaction();

        $prodStmt = $pdo->prepare("
            INSERT INTO products (id, title, category, price, rating, image, summary, pros, cons, affiliate_amazon, affiliate_bestbuy, affiliate_official, created_at)
            VALUES (:id, :title, :category, :price, :rating, :image, :summary, :pros, :cons, :affiliate_amazon, :affiliate_bestbuy, :affiliate_official, NOW())
            ON DUPLICATE KEY UPDATE
                title = VALUES(title),
                category = VALUES(category),
                price = VALUES(price),
                rating = VALUES(rating),
                image = VALUES(image),
                summary = VALUES(summary),
                pros = VALUES(pros),
                cons = VALUES(cons),
                affiliate_amazon = VALUES(affiliate_amazon),
                affiliate_bestbuy = VALUES(affiliate_bestbuy),
                affiliate_official = VALUES(affiliate_official)
        ");

        $prodInserted = 0;
        foreach ($productsSeed as $p) {
            $prodStmt->execute([
                ':id' => $p['id'],
                ':title' => $p['title'],
                ':category' => $p['category'],
                ':price' => $p['price'],
                ':rating' => $p['rating'],
                ':image' => $p['image'],
                ':summary' => $p['summary'],
                ':pros' => $p['pros'],
                ':cons' => $p['cons'],
                ':affiliate_amazon' => $p['affiliate_amazon'],
                ':affiliate_bestbuy' => $p['affiliate_bestbuy'],
                ':affiliate_official' => $p['affiliate_official']
            ]);
            $prodInserted++;
        }

        // Exact baseline blog insert SQL: status and views_count are EXCLUDED from ON DUPLICATE KEY UPDATE
        $blogStmt = $pdo->prepare("
            INSERT INTO blogs (id, title, slug, category, read_time, author_name, image, excerpt, content, views_count, status, created_at)
            VALUES (:id, :title, :slug, :category, :read_time, :author_name, :image, :excerpt, :content, :views_count, :status, NOW())
            ON DUPLICATE KEY UPDATE
                title = VALUES(title),
                slug = VALUES(slug),
                category = VALUES(category),
                read_time = VALUES(read_time),
                author_name = VALUES(author_name),
                image = VALUES(image),
                excerpt = VALUES(excerpt),
                content = VALUES(content)
        ");

        $blogInserted = 0;
        foreach ($blogsSeed as $b) {
            $blogStmt->execute([
                ':id' => $b['id'],
                ':title' => $b['title'],
                ':slug' => $b['slug'],
                ':category' => $b['category'],
                ':read_time' => $b['read_time'],
                ':author_name' => $b['author_name'],
                ':image' => $b['image'],
                ':excerpt' => $b['excerpt'],
                ':content' => $b['content'],
                ':views_count' => $b['views_count'],
                ':status' => $b['status']
            ]);
            $blogInserted++;
        }

        // Post-Insert Verification Queries
        $prodCount = (int)$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
        $blogCount = (int)$pdo->query("SELECT COUNT(*) FROM blogs")->fetchColumn();

        if ($prodCount < count($productsSeed) || $blogCount < count($blogsSeed)) {
            $pdo->rollBack();
            echo "❌ Verification Error: Database table record count after insertion is below expected thresholds.\\n";
            echo "   Products: {$prodCount} (Expected >= " . count($productsSeed) . ")\\n";
            echo "   Blogs: {$blogCount} (Expected >= " . count($blogsSeed) . ")\\n";
            exit(1);
        }

        $pdo->commit();
        echo "============================================================\\n";
        echo "✅ SUCCESS: Content Seeder Completed Successfully.\\n";
        echo "   - Products Seeded: {$prodInserted} (Total in DB: {$prodCount})\\n";
        echo "   - Blogs Seeded: {$blogInserted} (Total in DB: {$blogCount})\\n";
        echo "============================================================\\n";

    } catch (\\Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo "❌ CRITICAL FAILURE during database write: " . $e->getMessage() . "\\n";
        exit(1);
    }
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    rocAgentSeedContent();
}
`;

const backfillPhpContent = `<?php
/**
 * Run On Console (ROC) - Standalone Optional Blog Publication Status Backfill CLI Command
 *
 * Displays proposed public blog mapping for owner review before applying changes.
 * Does NOT apply mapping automatically unless invoked with explicit --confirm parameter.
 * Independently rerunnable.
 *
 * Usage for Preview:
 *   php api/v1/cron/backfill-blog-status.php
 *
 * Usage for Execution:
 *   php api/v1/cron/backfill-blog-status.php --confirm
 */

if (php_sapi_name() !== 'cli' && empty($_SERVER['ROC_CLI_RUN'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden. CLI execution required.']);
    exit(1);
}

require_once dirname(__DIR__) . '/config.php';

function rocAgentBackfillBlogStatus(): void {
    $args = $_SERVER['argv'] ?? [];
    $isConfirmed = in_array('--confirm', $args, true);

    echo "============================================================\\n";
    echo "RUN ON CONSOLE (ROC) - BLOG PUBLICATION STATUS BACKFILL\\n";
    echo "============================================================\\n\\n";

    $pdo = getDBConnection();
    if (!$pdo) {
        echo "❌ Error: Database connection unavailable.\\n";
        exit(1);
    }

    // 1. Strict Database Guard: Must be runoncon_rocstage
    $dbStmt = $pdo->query("SELECT DATABASE()");
    $currentDb = $dbStmt ? $dbStmt->fetchColumn() : null;

    if ($currentDb !== 'runoncon_rocstage') {
        echo "❌ ABORTED: Script is strictly restricted to staging database 'runoncon_rocstage'.\\n";
        exit(1);
    }
    echo "✓ Verified Database Isolation: Connected to '{$currentDb}'\\n";

    if (!$isConfirmed) {
        echo "⚠️ PREVIEW MODE ONLY (No database writes performed).\\n";
        echo "   To apply publication backfill, run with explicit approval flag:\\n";
        echo "   php api/v1/cron/backfill-blog-status.php --confirm\\n\\n";
    } else {
        echo "🚀 CONFIRMED WRITE MODE ENABLED (--confirm flag provided).\\n\\n";
    }

    // Proposed Public Blog Mapping Candidates matching exact seeded IDs and canonical slugs
    $candidateList = [
${ALL_BLOGS.map(b => `        ['id' => '${escapePhp(b.id)}', 'slug' => '${escapePhp(slugify(b.title))}']`).join(',\n')}
    ];

    echo "🔍 QUERYING ACTUAL DATABASE FOR BLOG PUBLICATION BACKFILL MATCHES:\\n";
    echo "------------------------------------------------------------\\n";

    $matchedDbRecords = [];
    $missingCandidates = [];
    $conflictsFound = [];

    foreach ($candidateList as $cand) {
        $targetId = $cand['id'];
        $targetSlug = $cand['slug'];

        $stmtId = $pdo->prepare("SELECT id, slug, title, status FROM blogs WHERE id = ?");
        $stmtId->execute([$targetId]);
        $rowById = $stmtId->fetch(PDO::FETCH_ASSOC);

        $stmtSlug = $pdo->prepare("SELECT id, slug, title, status FROM blogs WHERE slug = ?");
        $stmtSlug->execute([$targetSlug]);
        $rowBySlug = $stmtSlug->fetch(PDO::FETCH_ASSOC);

        if (!$rowById && !$rowBySlug) {
            $missingCandidates[] = "Candidate (ID: {$targetId}, Slug: {$targetSlug}) -> NOT FOUND IN DATABASE";
            continue;
        }

        if ($rowById && $rowBySlug && $rowById['id'] !== $rowBySlug['id']) {
            $conflictsFound[] = "CRITICAL CONFLICT: Candidate (ID: {$targetId}, Slug: {$targetSlug}) matched TWO DIFFERENT RECORDS in DB!";
            continue;
        }

        if ($rowById && $rowBySlug && $rowById['id'] === $rowBySlug['id'] && $rowById['slug'] === $targetSlug) {
            $matchedDbRecords[$rowById['id']] = $rowById;
            echo "  ✓ MATCHED: [ID: {$rowById['id']}] Title: '{$rowById['title']}' | Current Status: '{$rowById['status']}' -> Proposed Target: 'published'\\n";
        }
    }

    echo "------------------------------------------------------------\\n";
    echo "Found " . count($matchedDbRecords) . " verified 1-to-1 match(es) out of " . count($candidateList) . " candidate(s).\\n\\n";

    if (!empty($missingCandidates)) {
        echo "⚠️ Missing Candidates:\\n";
        foreach ($missingCandidates as $m) echo "  - {$m}\\n";
        echo "\\n";
    }

    if (!empty($conflictsFound)) {
        echo "❌ Conflicts Found:\\n";
        foreach ($conflictsFound as $c) echo "  - {$c}\\n";
        echo "\\n";
        exit(1);
    }

    if (!$isConfirmed) {
        echo "✓ Preview completed successfully. No changes made to database.\\n";
        return;
    }

    // Execution wrapped in PDO Transaction
    try {
        $pdo->beginTransaction();

        $updateStmt = $pdo->prepare("UPDATE blogs SET status = 'published' WHERE id = ? AND slug = ?");
        $updatedCount = 0;

        foreach ($matchedDbRecords as $record) {
            $updateStmt->execute([$record['id'], $record['slug']]);
            $updatedCount++;
        }

        $pdo->commit();
        echo "============================================================\\n";
        echo "✅ SUCCESS: Blog Publication Backfill Completed Successfully.\\n";
        echo "   - Blogs Published: {$updatedCount}\\n";
        echo "============================================================\\n";

    } catch (\\Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo "❌ CRITICAL FAILURE during blog publication update: " . $e->getMessage() . "\\n";
        exit(1);
    }
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    rocAgentBackfillBlogStatus();
}
`;

const pathsToUpdate = [
  { file: 'public/api/v1/cron/seed-roc-agent-content.php', content: seedContentPhpContent },
  { file: 'dist/api/v1/cron/seed-roc-agent-content.php', content: seedContentPhpContent },
  { file: 'public/api/v1/cron/backfill-blog-status.php', content: backfillPhpContent },
  { file: 'dist/api/v1/cron/backfill-blog-status.php', content: backfillPhpContent }
];

for (const item of pathsToUpdate) {
  const fullPath = path.join(ROOT_DIR, item.file);
  fs.mkdirSync(path.dirname(fullPath), { recursive: true });
  fs.writeFileSync(fullPath, item.content, 'utf-8');
  console.log(`Updated: ${item.file}`);
}
