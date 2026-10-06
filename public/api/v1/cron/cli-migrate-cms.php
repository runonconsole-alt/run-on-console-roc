<?php
/**
 * Run On Console (ROC) - Additive CLI Migration: CMS Engine & Schema Setup
 * CLI-only execution script setting up CMS tables in `runoncon_rocstage`.
 *
 * Usage via CLI:
 *   php public/api/v1/cron/cli-migrate-cms.php           (Preview mode only)
 *   php public/api/v1/cron/cli-migrate-cms.php --apply   (Execute migration)
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden. CLI execution required.']);
    exit(1);
}

require_once dirname(__DIR__) . '/config.php';

function rocCmsCronCliMigrate(?array $args = null): void {
    global $argv;
    if ($args === null) {
        $args = $argv ?? [];
    }

    $isApply = in_array('--apply', $args, true);

    echo "============================================================\n";
    echo "RUN ON CONSOLE (ROC) - ADDITIVE CMS MIGRATION\n";
    echo "============================================================\n\n";

    $pdo = getDBConnection();
    if (!$pdo) {
        echo "❌ Error: Database connection unavailable.\n";
        exit(1);
    }

    try {
        $dbStmt = $pdo->query("SELECT DATABASE()");
        $currentDb = (string)$dbStmt->fetchColumn();

        echo "🔍 TARGET DATABASE CHECK:\n";
        echo "   Connected Database: {$currentDb}\n";
        echo "   Required Database: runoncon_rocstage\n\n";

        // Explicit Public Inventory Mapping for Legacy Products (29 actual product IDs from initialData.js)
        $mappedPublicProductIds = [
            'prod-101', 'prod-102', 'prod-103', 'prod-104', 'prod-105', 'prod-106',
            'prod-107', 'prod-108', 'prod-109', 'prod-110', 'prod-111', 'prod-112',
            'prod-113', 'prod-114', 'prod-115', 'prod-116', 'prod-gpu-1', 'prod-gpu-2',
            'prod-cpu-1', 'prod-cpu-2', 'prod-mem-1', 'prod-mem-2', 'prod-mem-3',
            'prod-spk-1', 'prod-spk-2', 'prod-ear-1', 'prod-ear-2', 'prod-chg-1', 'prod-chg-2'
        ];

        // Helper: Check column existence in table
        $hasColumn = function(PDO $pdo, string $table, string $column): bool {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
            $stmt->execute([$table, $column]);
            return (int)$stmt->fetchColumn() > 0;
        };

        // Helper: Check index existence in table
        $hasIndex = function(PDO $pdo, string $table, string $indexName): bool {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?");
            $stmt->execute([$table, $indexName]);
            return (int)$stmt->fetchColumn() > 0;
        };

        // Inspect Missing Columns for Legacy Tables
        $missingCols = [];
        $tablesToCheck = [
            'products' => ['slug', 'short_desc', 'summary', 'specs_json', 'price_state', 'affiliate_links_json', 'product_images_json', 'last_verified_at', 'meta_title', 'meta_description', 'og_image', 'is_noindex', 'status', 'draft_status', 'draft_scheduled_at', 'version', 'draft_data_json', 'content_modified_at'],
            'blogs' => ['status', 'draft_status', 'draft_scheduled_at', 'published_at', 'meta_title', 'meta_description', 'og_image', 'is_noindex', 'locked_by', 'locked_at', 'version', 'draft_data_json', 'content_modified_at'],
            'categories' => ['parent_id', 'description', 'icon_name', 'meta_title', 'meta_description', 'status', 'is_noindex', 'version', 'draft_data_json', 'content_modified_at']
        ];

        foreach ($tablesToCheck as $tbl => $cols) {
            $tblStmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
            $tblStmt->execute([$tbl]);
            $tblExists = (int)$tblStmt->fetchColumn() > 0;

            if ($tblExists) {
                foreach ($cols as $c) {
                    if (!$hasColumn($pdo, $tbl, $c)) {
                        $missingCols[] = "{$tbl}.{$c}";
                    }
                }
            } else {
                $missingCols[] = "{$tbl} (Full Table)";
            }
        }

        $preProdPubCount = 0;
        $preBlogPubCount = 0;
        try {
            $preProdPubCount = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE status = 'published'")->fetchColumn();
            $preBlogPubCount = (int)$pdo->query("SELECT COUNT(*) FROM blogs WHERE status = 'published'")->fetchColumn();
        } catch (\Throwable $e) {
            // Tables might not exist yet
        }

        if (!$isApply) {
            echo "🔍 PREVIEW MODE (Default):\n";
            echo "   Pre-Migration Publication State Counts:\n";
            echo "   - Published Products: {$preProdPubCount}\n";
            echo "   - Published Blogs: {$preBlogPubCount}\n\n";

            echo "   Missing Columns / Tables Detected:\n";
            if (empty($missingCols)) {
                echo "   ✓ All required columns and tables exist. Schema is 100% compatible.\n";
            } else {
                foreach ($missingCols as $m) {
                    echo "   - Missing: {$m}\n";
                }
            }

            echo "\n   Proposed Publication State Mapping:\n";
            echo "   - Legacy Products mapped to 'published' (" . count($mappedPublicProductIds) . " total): " . implode(', ', $mappedPublicProductIds) . "\n";
            echo "   - Unclassified / New Products & Blogs: Default to 'draft'\n";
            echo "   - Existing Blogs with status = 'published': Retain 'published'\n";
            echo "   - Existing Draft Blogs / Products: Retain 'draft' (never republish)\n";

            echo "\n💡 Migration was NOT applied. To execute additive changes, pass the literal --apply flag:\n";
            echo "   php public/api/v1/cron/cli-migrate-cms.php --apply\n";
            exit(0);
        }

        // Apply Mode - Strict Database Verification
        if ($currentDb !== 'runoncon_rocstage') {
            echo "❌ Error: Target database must be 'runoncon_rocstage'. Connected database is '{$currentDb}'. Migration aborted.\n";
            exit(1);
        }

        // 1. Table: products
        $pdo->exec("CREATE TABLE IF NOT EXISTS products (
            id VARCHAR(64) PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL UNIQUE,
            category VARCHAR(64) NULL,
            short_desc TEXT NULL,
            summary TEXT NULL,
            specs_json JSON NULL,
            price DECIMAL(10,2) NULL,
            price_state ENUM('set', 'unknown') DEFAULT 'set',
            rating DECIMAL(3,2) NULL DEFAULT 4.80,
            image VARCHAR(255) NULL,
            pros TEXT NULL,
            cons TEXT NULL,
            affiliate_amazon VARCHAR(255) NULL,
            affiliate_bestbuy VARCHAR(255) NULL,
            affiliate_official VARCHAR(255) NULL,
            affiliate_links_json JSON NULL,
            product_images_json JSON NULL,
            clicks_count INT DEFAULT 0,
            last_verified_at DATETIME NULL,
            meta_title VARCHAR(255) NULL,
            meta_description TEXT NULL,
            og_image VARCHAR(255) NULL,
            is_noindex TINYINT(1) DEFAULT 0,
            status ENUM('published', 'draft') DEFAULT 'draft',
            draft_status ENUM('none', 'draft_saved', 'in_review', 'scheduled') DEFAULT 'none',
            draft_scheduled_at DATETIME NULL,
            version INT DEFAULT 1,
            draft_data_json LONGTEXT NULL,
            content_modified_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_prod_status (status),
            KEY idx_prod_slug (slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // Add missing columns to products table if existing
        $prodColsToAdd = [
            'slug' => "ALTER TABLE products ADD COLUMN slug VARCHAR(255) NULL AFTER title",
            'short_desc' => "ALTER TABLE products ADD COLUMN short_desc TEXT NULL AFTER category",
            'summary' => "ALTER TABLE products ADD COLUMN summary TEXT NULL AFTER short_desc",
            'specs_json' => "ALTER TABLE products ADD COLUMN specs_json JSON NULL AFTER summary",
            'price_state' => "ALTER TABLE products ADD COLUMN price_state ENUM('set', 'unknown') DEFAULT 'set' AFTER price",
            'affiliate_links_json' => "ALTER TABLE products ADD COLUMN affiliate_links_json JSON NULL AFTER affiliate_official",
            'product_images_json' => "ALTER TABLE products ADD COLUMN product_images_json JSON NULL AFTER affiliate_links_json",
            'last_verified_at' => "ALTER TABLE products ADD COLUMN last_verified_at DATETIME NULL AFTER clicks_count",
            'meta_title' => "ALTER TABLE products ADD COLUMN meta_title VARCHAR(255) NULL AFTER last_verified_at",
            'meta_description' => "ALTER TABLE products ADD COLUMN meta_description TEXT NULL AFTER meta_title",
            'og_image' => "ALTER TABLE products ADD COLUMN og_image VARCHAR(255) NULL AFTER meta_description",
            'is_noindex' => "ALTER TABLE products ADD COLUMN is_noindex TINYINT(1) DEFAULT 0 AFTER og_image",
            'status' => "ALTER TABLE products ADD COLUMN status ENUM('published', 'draft') DEFAULT 'draft' AFTER is_noindex",
            'draft_status' => "ALTER TABLE products ADD COLUMN draft_status ENUM('none', 'draft_saved', 'in_review', 'scheduled') DEFAULT 'none' AFTER status",
            'draft_scheduled_at' => "ALTER TABLE products ADD COLUMN draft_scheduled_at DATETIME NULL AFTER draft_status",
            'version' => "ALTER TABLE products ADD COLUMN version INT DEFAULT 1 AFTER draft_scheduled_at",
            'draft_data_json' => "ALTER TABLE products ADD COLUMN draft_data_json LONGTEXT NULL AFTER version",
            'content_modified_at' => "ALTER TABLE products ADD COLUMN content_modified_at DATETIME NULL AFTER draft_data_json"
        ];

        foreach ($prodColsToAdd as $col => $sql) {
            if (!$hasColumn($pdo, 'products', $col)) {
                $pdo->exec($sql);
                echo "  ✓ Added missing column '{$col}' to table 'products'.\n";
            }
        }

        // Backfill missing slugs for products with collision detection
        $stmtNoSlug = $pdo->query("SELECT id, title FROM products WHERE slug IS NULL OR slug = ''");
        $noSlugProds = $stmtNoSlug->fetchAll();
        $existingSlugs = $pdo->query("SELECT slug FROM products WHERE slug IS NOT NULL AND slug != ''")->fetchAll(PDO::FETCH_COLUMN);

        foreach ($noSlugProds as $nsp) {
            $baseSlug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $nsp['title']), '-')) ?: ('prod-' . $nsp['id']);
            $genSlug = $baseSlug;
            $counter = 1;
            while (in_array($genSlug, $existingSlugs, true)) {
                echo "  ⚠️ Collision detected for slug '{$genSlug}' (Product ID: {$nsp['id']}). Generating unique suffix...\n";
                $genSlug = $baseSlug . '-' . $counter;
                $counter++;
            }
            $existingSlugs[] = $genSlug;
            $stmtUpdSlug = $pdo->prepare("UPDATE products SET slug = ? WHERE id = ?");
            $stmtUpdSlug->execute([$genSlug, $nsp['id']]);
            echo "  ✓ Backfilled product slug: '{$genSlug}' for ID: '{$nsp['id']}'\n";
        }

        // Add index on product status if missing
        if (!$hasIndex($pdo, 'products', 'idx_prod_status')) {
            $pdo->exec("ALTER TABLE products ADD INDEX idx_prod_status (status)");
        }

        // Explicit Publication Mapping for Legacy Products
        $inClause = implode(',', array_map(fn($id) => $pdo->quote($id), $mappedPublicProductIds));
        $pdo->exec("UPDATE products SET status = 'published' WHERE id IN ({$inClause}) AND (status IS NULL OR status = 'draft')");
        echo "  ✓ Explicit public publication status applied for legacy mapped products.\n";

        // 2. Table: blogs
        $pdo->exec("CREATE TABLE IF NOT EXISTS blogs (
            id VARCHAR(64) PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL UNIQUE,
            category VARCHAR(64) NULL,
            read_time VARCHAR(50) NULL DEFAULT '5 min read',
            author_name VARCHAR(100) NULL,
            image VARCHAR(255) NULL,
            excerpt TEXT NULL,
            content LONGTEXT NULL,
            views_count INT DEFAULT 0,
            status ENUM('published', 'draft', 'in_review', 'scheduled', 'archived') DEFAULT 'draft',
            draft_status ENUM('none', 'draft_saved', 'in_review', 'scheduled') DEFAULT 'none',
            draft_scheduled_at DATETIME NULL,
            published_at DATETIME NULL,
            meta_title VARCHAR(255) NULL,
            meta_description TEXT NULL,
            og_image VARCHAR(255) NULL,
            is_noindex TINYINT(1) DEFAULT 0,
            locked_by INT NULL,
            locked_at DATETIME NULL,
            version INT DEFAULT 1,
            draft_data_json LONGTEXT NULL,
            content_modified_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_blogs_status (status),
            KEY idx_blogs_slug (slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        $blogColsToAdd = [
            'status' => "ALTER TABLE blogs ADD COLUMN status ENUM('published', 'draft', 'in_review', 'scheduled', 'archived') DEFAULT 'draft' AFTER content",
            'draft_status' => "ALTER TABLE blogs ADD COLUMN draft_status ENUM('none', 'draft_saved', 'in_review', 'scheduled') DEFAULT 'none' AFTER status",
            'draft_scheduled_at' => "ALTER TABLE blogs ADD COLUMN draft_scheduled_at DATETIME NULL AFTER draft_status",
            'published_at' => "ALTER TABLE blogs ADD COLUMN published_at DATETIME NULL AFTER draft_scheduled_at",
            'meta_title' => "ALTER TABLE blogs ADD COLUMN meta_title VARCHAR(255) NULL AFTER published_at",
            'meta_description' => "ALTER TABLE blogs ADD COLUMN meta_description TEXT NULL AFTER meta_title",
            'og_image' => "ALTER TABLE blogs ADD COLUMN og_image VARCHAR(255) NULL AFTER meta_description",
            'is_noindex' => "ALTER TABLE blogs ADD COLUMN is_noindex TINYINT(1) DEFAULT 0 AFTER og_image",
            'locked_by' => "ALTER TABLE blogs ADD COLUMN locked_by INT NULL AFTER is_noindex",
            'locked_at' => "ALTER TABLE blogs ADD COLUMN locked_at DATETIME NULL AFTER locked_by",
            'version' => "ALTER TABLE blogs ADD COLUMN version INT DEFAULT 1 AFTER locked_at",
            'draft_data_json' => "ALTER TABLE blogs ADD COLUMN draft_data_json LONGTEXT NULL AFTER version",
            'content_modified_at' => "ALTER TABLE blogs ADD COLUMN content_modified_at DATETIME NULL AFTER draft_data_json"
        ];

        foreach ($blogColsToAdd as $col => $sql) {
            if (!$hasColumn($pdo, 'blogs', $col)) {
                $pdo->exec($sql);
                echo "  ✓ Added missing column '{$col}' to table 'blogs'.\n";
            }
        }

        // 3. Table: categories
        $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
            id VARCHAR(64) PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            slug VARCHAR(100) NOT NULL UNIQUE,
            parent_id VARCHAR(64) NULL,
            description TEXT NULL,
            icon_name VARCHAR(50) NULL,
            meta_title VARCHAR(255) NULL,
            meta_description TEXT NULL,
            status ENUM('published', 'draft') DEFAULT 'published',
            is_noindex TINYINT(1) DEFAULT 0,
            version INT DEFAULT 1,
            draft_data_json LONGTEXT NULL,
            content_modified_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_cat_slug (slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        $catColsToAdd = [
            'parent_id' => "ALTER TABLE categories ADD COLUMN parent_id VARCHAR(64) NULL AFTER slug",
            'description' => "ALTER TABLE categories ADD COLUMN description TEXT NULL AFTER parent_id",
            'icon_name' => "ALTER TABLE categories ADD COLUMN icon_name VARCHAR(50) NULL AFTER description",
            'meta_title' => "ALTER TABLE categories ADD COLUMN meta_title VARCHAR(255) NULL AFTER icon_name",
            'meta_description' => "ALTER TABLE categories ADD COLUMN meta_description TEXT NULL AFTER meta_title",
            'status' => "ALTER TABLE categories ADD COLUMN status ENUM('published', 'draft') DEFAULT 'published' AFTER meta_description",
            'is_noindex' => "ALTER TABLE categories ADD COLUMN is_noindex TINYINT(1) DEFAULT 0 AFTER status",
            'version' => "ALTER TABLE categories ADD COLUMN version INT DEFAULT 1 AFTER is_noindex",
            'draft_data_json' => "ALTER TABLE categories ADD COLUMN draft_data_json LONGTEXT NULL AFTER version",
            'content_modified_at' => "ALTER TABLE categories ADD COLUMN content_modified_at DATETIME NULL AFTER draft_data_json"
        ];

        foreach ($catColsToAdd as $col => $sql) {
            if (!$hasColumn($pdo, 'categories', $col)) {
                $pdo->exec($sql);
                echo "  ✓ Added missing column '{$col}' to table 'categories'.\n";
            }
        }

        // 4. Table: pages
        $pdo->exec("CREATE TABLE IF NOT EXISTS pages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(100) NOT NULL UNIQUE,
            meta_title VARCHAR(255) NULL,
            meta_description TEXT NULL,
            canonical_url VARCHAR(255) NULL,
            og_image VARCHAR(255) NULL,
            is_noindex TINYINT(1) DEFAULT 0,
            status ENUM('published', 'draft') DEFAULT 'draft',
            draft_status ENUM('none', 'draft_saved', 'in_review', 'scheduled') DEFAULT 'none',
            version INT DEFAULT 1,
            content_blocks_json LONGTEXT NULL,
            draft_data_json LONGTEXT NULL,
            content_modified_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_pages_slug (slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // 5. Table: authors (Editorial Authors)
        $pdo->exec("CREATE TABLE IF NOT EXISTS authors (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            name VARCHAR(100) NOT NULL,
            slug VARCHAR(100) NOT NULL UNIQUE,
            title VARCHAR(150) NULL,
            bio TEXT NULL,
            avatar_image VARCHAR(255) NULL,
            status ENUM('published', 'draft') NOT NULL DEFAULT 'draft',
            is_noindex TINYINT(1) NOT NULL DEFAULT 0,
            version INT DEFAULT 1,
            content_modified_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_authors_status (status, is_noindex),
            KEY idx_authors_slug (slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // 6. Table: cms_users
        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(60) NOT NULL UNIQUE,
            email VARCHAR(150) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM('administrator', 'editor') NOT NULL DEFAULT 'editor',
            status ENUM('active', 'disabled') NOT NULL DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_cms_user_role (role)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // 7. Table: cms_sessions
        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            session_token_hash VARCHAR(64) NOT NULL UNIQUE,
            csrf_token VARCHAR(64) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            user_agent VARCHAR(255) NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            KEY idx_cms_sess_token (session_token_hash),
            KEY idx_cms_sess_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // 8. Table: cms_media
        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_media (
            id INT AUTO_INCREMENT PRIMARY KEY,
            filename VARCHAR(255) NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            filepath VARCHAR(255) NOT NULL,
            file_url VARCHAR(255) NOT NULL,
            mime_type VARCHAR(100) NOT NULL,
            file_size INT NOT NULL,
            width INT NULL,
            height INT NULL,
            alt_text VARCHAR(255) NULL,
            caption TEXT NULL,
            folder VARCHAR(60) DEFAULT 'general',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            KEY idx_cms_media_url (file_url)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // 9. Table: cms_media_usage
        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_media_usage (
            id INT AUTO_INCREMENT PRIMARY KEY,
            media_id INT NOT NULL,
            target_type VARCHAR(50) NOT NULL,
            target_id VARCHAR(64) NOT NULL,
            field_name VARCHAR(50) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            KEY idx_cms_mu_media (media_id),
            KEY idx_cms_mu_target (target_type, target_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // 10. Table: cms_revisions
        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_revisions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            content_type VARCHAR(50) NOT NULL,
            content_id VARCHAR(64) NOT NULL,
            version_num INT NOT NULL,
            data_json LONGTEXT NOT NULL,
            created_by INT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            KEY idx_cms_rev_target (content_type, content_id, version_num)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // 11. Table: cms_audit_logs
        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_audit_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            action VARCHAR(100) NOT NULL,
            target_type VARCHAR(50) NULL,
            target_id VARCHAR(64) NULL,
            details_json JSON NULL,
            ip_address VARCHAR(45) NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            KEY idx_cms_audit_user (user_id),
            KEY idx_cms_audit_action (action)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // 12. Table: cms_redirects
        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_redirects (
            id INT AUTO_INCREMENT PRIMARY KEY,
            old_slug VARCHAR(255) NOT NULL,
            new_slug VARCHAR(255) NOT NULL,
            target_type VARCHAR(50) NOT NULL DEFAULT 'category',
            http_code INT DEFAULT 301,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            KEY idx_cms_redir_old (old_slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // 13. Table: cms_settings
        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_settings (
            setting_key VARCHAR(100) PRIMARY KEY,
            setting_value LONGTEXT NULL,
            updated_by INT NULL,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // 14. Table: cms_moderation
        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_moderation (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type ENUM('comment', 'write_for_us') NOT NULL DEFAULT 'comment',
            author_name VARCHAR(120) NOT NULL,
            author_email VARCHAR(150) NOT NULL,
            content TEXT NOT NULL,
            status ENUM('pending', 'approved', 'rejected', 'spam') NOT NULL DEFAULT 'pending',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_cms_mod_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // 15. Table: cms_cache_queue
        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_cache_queue (
            id INT AUTO_INCREMENT PRIMARY KEY,
            content_type VARCHAR(50) NOT NULL,
            content_id VARCHAR(64) NOT NULL,
            target_version_id INT NOT NULL,
            action VARCHAR(50) NOT NULL DEFAULT 'purge_and_prerender',
            status ENUM('pending', 'processing', 'completed', 'failed', 'stale_skipped') NOT NULL DEFAULT 'pending',
            attempts INT NOT NULL DEFAULT 0,
            last_error TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_cms_cq_status (status),
            KEY idx_cms_cq_target (content_type, content_id, target_version_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // Auto-generate uploads directory & security .htaccess
        $uploadsDir = dirname(__DIR__, 3) . '/uploads';
        if (!is_dir($uploadsDir)) {
            @mkdir($uploadsDir, 0755, true);
        }

        $uploadsHtaccess = $uploadsDir . '/.htaccess';
        $htaccessContent = <<<HTACCESS
# Security Guard: Block Script Execution in Uploads Directory
<IfModule mod_php7.c>
  php_flag engine off
</IfModule>
<IfModule mod_php.c>
  php_flag engine off
</IfModule>
RemoveHandler .php .phtml .php3 .pl .py .jsp .asp .sh .cgi
<FilesMatch "\.(php|phtml|php3|pl|py|jsp|asp|sh|cgi)$">
  <IfModule mod_authz_core.c>
    Require all denied
  </IfModule>
  <IfModule !mod_authz_core.c>
    Order allow,deny
    Deny from all
  </IfModule>
</FilesMatch>
HTACCESS;
        @file_put_contents($uploadsHtaccess, $htaccessContent);
        echo "  ✓ Uploads security .htaccess generated at 'public/uploads/.htaccess'.\n";

        // Verification of Final Schema & Publication State Counts
        $prodPubCount = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE status = 'published'")->fetchColumn();
        $blogPubCount = (int)$pdo->query("SELECT COUNT(*) FROM blogs WHERE status = 'published'")->fetchColumn();

        echo "\n📊 POST-MIGRATION RECORD COUNTS:\n";
        echo "   - Published Products: {$prodPubCount}\n";
        echo "   - Published Blogs: {$blogPubCount}\n";

        echo "\n✅ Additive CMS migration completed successfully.\n";
        exit(0);

    } catch (\Throwable $e) {
        echo "❌ Error applying CMS migration: " . $e->getMessage() . "\n";
        exit(1);
    }
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    rocCmsCronCliMigrate();
}
