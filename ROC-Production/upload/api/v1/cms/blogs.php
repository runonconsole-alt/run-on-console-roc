<?php
/**
 * Run On Console (ROC) — CMS Blog Posts Management API
 *
 * GET  /api/v1/cms/blogs.php          list posts
 * GET  /api/v1/cms/blogs.php?id=X     one post, with any unsaved draft merged in
 * POST /api/v1/cms/blogs.php          create | save_draft | publish | unpublish | release_lock
 *
 * Changes from the previous version:
 *
 *  - `create` now stores the whole post, not just the title. Previously every
 *    other field on a new post was silently discarded.
 *  - `update` no longer pretends to save content. It only changes status, it
 *    says so, and it now checks the version like every other write. Content is
 *    saved through save_draft / publish.
 *  - `publish` promotes the Open Graph, Twitter, canonical, schema and alt-text
 *    fields, so what the editor collects is what the site renders.
 *  - Content is sanitised against an allowlist (cms-html.php) rather than by two
 *    regexes that miss unclosed <script> tags and single-quoted handlers.
 *  - GET with an id merges draft_data_json over the live row, so reopening a
 *    post shows the draft rather than the last published version.
 *  - `release_lock` exists, so closing the editor frees the post immediately
 *    instead of leaving it locked for 15 minutes.
 *
 * Clean SEO (2026-09-28):
 *  - Editors set only title, slug, excerpt, content, category, author, cover
 *    image + alt, meta title, meta description and focus keyword.
 *  - Open Graph / Twitter / canonical / robots / schema fields are no longer
 *    accepted from the client. Old hidden values in a request are ignored.
 *  - Every content save and publish sets is_noindex = 0, is_nofollow = 0 and
 *    clears canonical_url / schema_json / schema_type overrides.
 *  - Sharing metadata is derived on publish from the meta title, meta
 *    description and cover image.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/cms-html.php';

$session = requireCmsPermission('blogs', 'view');   // per-action checks below
$pdo = getDBConnection();

if (!$pdo) {
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'Database connection unavailable.']);
    exit();
}

const ROC_LOCK_SECONDS = 900;   // 15 minutes

/** Fields the editor may set. Anything else in the payload is ignored. */
const ROC_BLOG_FIELDS = [
    'title', 'slug', 'excerpt', 'content', 'category', 'author_name', 'image', 'image_alt',
    'meta_title', 'meta_description', 'focus_keyword',
];

/**
 * Columns the server owns. They are reset on every content save / publish so a
 * published post can never carry noindex, nofollow, a foreign canonical or a
 * stale schema override. Only columns that exist are touched.
 */
function rocSeoResetSql(PDO $pdo): string {
    $reset = [
        'is_noindex'    => '0',
        'is_nofollow'   => '0',
        'canonical_url' => "''",
        'schema_type'   => "''",
        'schema_json'   => 'NULL',
    ];
    $available = rocBlogColumns($pdo);
    $parts = [];
    foreach ($reset as $col => $val) {
        if (in_array($col, $available, true)) $parts[] = "`{$col}` = {$val}";
    }
    return $parts ? ', ' . implode(', ', $parts) : '';
}

/** Read time from the body, e.g. "6 min read". */
function rocReadTimeFromHtml(string $html): string {
    $text = rocCmsTextFromHtml($html);
    $words = $text === '' ? 0 : count(preg_split('/\s+/u', $text) ?: []);
    return max(1, (int)ceil($words / 200)) . ' min read';
}

const ROC_BLOG_LIMITS = [
    'title' => 220, 'slug' => 180, 'excerpt' => 3000, 'category' => 100,
    'author_name' => 150, 'image' => 2048, 'image_alt' => 500,
    'meta_title' => 255, 'meta_description' => 500, 'canonical_url' => 2048,
    'focus_keyword' => 200, 'og_title' => 255, 'og_description' => 500,
    'og_image' => 2048, 'og_type' => 40, 'twitter_card' => 40,
    'twitter_title' => 255, 'twitter_description' => 500, 'twitter_image' => 2048,
    'schema_type' => 60,
];

const ROC_ENUMS = [
    'og_type'      => ['', 'article', 'website', 'product'],
    'twitter_card' => ['', 'summary', 'summary_large_image'],
    'schema_type'  => ['', 'Article', 'BlogPosting', 'NewsArticle', 'Review',
                       'HowTo', 'FAQPage', 'WebPage'],
];

function rocFail(string $message, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message]);
    exit();
}

/** Publishing is strict; drafts may remain incomplete. */
function rocPublishErrors(array $post): array {
    $errors = [];
    $required = ['title'=>'Title', 'excerpt'=>'Short description', 'category'=>'Category',
                 'image'=>'Featured image', 'image_alt'=>'Image alt text',
                 'meta_title'=>'Meta title', 'meta_description'=>'Meta description'];
    foreach ($required as $field => $label) {
        $text = html_entity_decode((string)($post[$field] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (preg_replace('/[\s\x{00A0}\x{200B}\x{FEFF}]+/u', '', $text) === '') {
            $errors[$field] = $label . ' is required before publishing.';
        }
    }
    $text = rocCmsTextFromHtml(rocCmsSanitizeHtml((string)($post['content'] ?? '')));
    if (preg_replace('/[\s\x{00A0}\x{200B}\x{FEFF}]+/u', '', $text) === '') {
        $errors['content'] = 'Add body content before publishing.';
    }
    if (rocAgentSlugify((string)($post['slug'] ?? '') ?: (string)($post['title'] ?? '')) === '') {
        $errors['slug'] = 'URL slug must contain letters or numbers.';
    }
    return $errors;
}

function rocRequirePublishFields(array $post): void {
    $errors = rocPublishErrors($post);
    if (!$errors) return;
    http_response_code(422);
    echo json_encode(['success'=>false, 'error'=>'Complete the required fields before publishing.',
                      'errors'=>$errors]);
    exit();
}

/** Which of ROC_BLOG_FIELDS actually exist as columns, so this works pre-migration. */
function rocBlogColumns(PDO $pdo): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    $stmt = $pdo->prepare(
        'SELECT column_name FROM information_schema.columns
          WHERE table_schema = DATABASE() AND table_name = "blogs"'
    );
    $stmt->execute();
    $cache = array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    return $cache;
}

/** Normalise and bound-check an incoming payload. Returns [clean, errorOrNull]. */
function rocCleanBlogPayload(array $raw): array {
    $clean = [];

    foreach (ROC_BLOG_FIELDS as $field) {
        if (!array_key_exists($field, $raw)) continue;
        $value = $raw[$field];

        if ($field === 'is_noindex' || $field === 'is_nofollow') {
            $clean[$field] = !empty($value) ? 1 : 0;
            continue;
        }
        if ($field === 'content') {
            $clean['content'] = rocCmsSanitizeHtml((string)$value);
            continue;
        }
        if ($field === 'schema_json') {
            $value = trim((string)$value);
            if ($value !== '') {
                json_decode($value);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return [null, 'Schema JSON is not valid: ' . json_last_error_msg()];
                }
                if (strlen($value) > 40000) {
                    return [null, 'Schema JSON is too large (40 KB limit).'];
                }
            }
            $clean['schema_json'] = $value;
            continue;
        }

        $value = is_scalar($value) ? trim((string)$value) : '';

        if (isset(ROC_ENUMS[$field]) && !in_array($value, ROC_ENUMS[$field], true)) {
            return [null, "Invalid value for {$field}."];
        }
        if (isset(ROC_BLOG_LIMITS[$field]) && mb_strlen($value) > ROC_BLOG_LIMITS[$field]) {
            return [null, ucfirst(str_replace('_', ' ', $field))
                        . ' is too long (limit ' . ROC_BLOG_LIMITS[$field] . ' characters).'];
        }
        if ($field === 'image' && $value !== '') {
            $safe = rocCmsSafeUrl($value);
            if ($safe === null) return [null, "Invalid URL in {$field}."];
            $value = $safe;
        }
        if ($field === 'slug' && $value !== '') {
            $value = rocAgentSlugify($value);
            if ($value === '') return [null, 'Slug must contain letters or numbers.'];
        }
        $clean[$field] = $value;
    }

    return [$clean, null];
}

/** Make a slug unique within blogs, ignoring the row being edited. */
function rocUniqueSlug(PDO $pdo, string $slug, string $ignoreId = ''): string {
    $base = $slug;
    $attempt = 1;
    while (true) {
        $sql = 'SELECT COUNT(*) FROM blogs WHERE slug = ?' . ($ignoreId ? ' AND id <> ?' : '');
        $stmt = $pdo->prepare($sql);
        $stmt->execute($ignoreId ? [$slug, $ignoreId] : [$slug]);
        if ((int)$stmt->fetchColumn() === 0) return $slug;
        $attempt++;
        $slug = $base . '-' . $attempt;
        if ($attempt > 50) return $base . '-' . time();
    }
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

/* ------------------------------------------------------------------ GET */

if ($method === 'GET') {
    $id = $_GET['id'] ?? null;

    if ($id !== null && $id !== '') {
        $stmt = $pdo->prepare('SELECT * FROM blogs WHERE id = ? LIMIT 1');
        $stmt->execute([(string)$id]);
        $blog = $stmt->fetch();

        if (!$blog) rocFail('Blog post not found.', 404);

        // Report an active lock held by somebody else, but do not block editing.
        $blog['lock_warning'] = null;
        if (!empty($blog['locked_by']) && (int)$blog['locked_by'] !== (int)$session['user_id']) {
            $lockAge = time() - strtotime($blog['locked_at'] ?? '1970-01-01');
            if ($lockAge < ROC_LOCK_SECONDS) {
                $stmtL = $pdo->prepare('SELECT username FROM cms_users WHERE id = ? LIMIT 1');
                $stmtL->execute([$blog['locked_by']]);
                $who = $stmtL->fetchColumn() ?: 'Another editor';
                $minutes = max(1, (int)round((ROC_LOCK_SECONDS - $lockAge) / 60));
                $blog['lock_warning'] =
                    "{$who} opened this post " . max(1, (int)round($lockAge / 60))
                    . " minute(s) ago. Their lock expires in {$minutes} minute(s). "
                    . "If you both save, whoever saves second will get a conflict warning.";
            }
        }

        // Take the lock for this editor (read-only users never lock a post).
        if (rocCmsCan($session, 'blogs', 'edit')) {
            $stmtLock = $pdo->prepare('UPDATE blogs SET locked_by = ?, locked_at = NOW() WHERE id = ?');
            $stmtLock->execute([$session['user_id'], (string)$id]);
        }

        // Merge the unsaved draft over the live row so the editor resumes where
        // the writer left off rather than showing the published version.
        $blog['live_slug'] = $blog['status'] === 'published' ? $blog['slug'] : null;
        $blog['has_draft'] = false;
        if (!empty($blog['draft_data_json'])) {
            $draft = json_decode($blog['draft_data_json'], true);
            if (is_array($draft)) {
                $blog['has_draft'] = true;
                foreach (ROC_BLOG_FIELDS as $field) {
                    if (array_key_exists($field, $draft)) $blog[$field] = $draft[$field];
                }
            }
        }
        unset($blog['draft_data_json']);
        $t = !empty($blog['published_at']) ? strtotime((string)$blog['published_at']) : false;
        $blog['published_at_iso'] = $t ? date(DATE_ATOM, $t) : null;

        echo json_encode(['success' => true, 'blog' => $blog]);
        exit();
    }

    $stmt = $pdo->query(
        "SELECT id, title, slug, category, author_name, status, draft_status, version,
                is_noindex, image, meta_title, meta_description, locked_by, locked_at,
                created_at, updated_at, published_at
           FROM blogs WHERE status <> 'archived' ORDER BY updated_at DESC"
    );
    $blogs = $stmt->fetchAll();
    // Scheduled times with their timezone, so the CMS shows them in the editor's local time.
    foreach ($blogs as &$b) {
        $t = !empty($b['published_at']) ? strtotime((string)$b['published_at']) : false;
        $b['published_at_iso'] = $t ? date(DATE_ATOM, $t) : null;
    }
    unset($b);
    echo json_encode(['success' => true, 'blogs' => $blogs, 'server_time' => date(DATE_ATOM)]);
    exit();
}

/* ----------------------------------------------------------------- POST */

if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true) ?? $_POST;
    $action = $data['action'] ?? 'save_draft';

    // Permission per action (enforced here, not just hidden in the UI).
    $need = [
        'create' => 'edit', 'save_draft' => 'edit', 'release_lock' => 'edit',
        'publish' => 'publish', 'schedule' => 'publish', 'unpublish' => 'publish', 'update' => 'publish', 'set_status' => 'publish',
        'delete' => 'delete',
    ][$action] ?? 'edit';
    rocCmsAuthorize($session, 'blogs', $need);

    $id = (string)($data['id'] ?? '');
    if ($id === '' && $action !== 'create') {
        rocFail('Blog ID is required.');
    }

    /* ---------------------------------------------------------- create */

    if ($action === 'create') {
        $payload = $data['draft'] ?? $data;
        [$clean, $error] = rocCleanBlogPayload(is_array($payload) ? $payload : []);
        if ($error) rocFail($error);

        $title = trim($clean['title'] ?? '') ?: 'Untitled Blog Post';
        $idStr = 'blog-' . bin2hex(random_bytes(4));
        $slug = rocUniqueSlug($pdo, ($clean['slug'] ?? '') ?: (rocAgentSlugify($title) ?: $idStr));

        $clean['title'] = $title;
        $clean['slug'] = $slug;
        $clean['category'] = $clean['category'] ?? 'general';
        $clean['author_name'] = $clean['author_name'] ?? $session['username'];

        // Persist every field the schema actually has, not just the title.
        $available = rocBlogColumns($pdo);
        $columns = ['id', 'status', 'draft_status', 'version', 'created_at', 'updated_at'];
        $places  = ['?', "'draft'", "'draft_saved'", '1', 'NOW()', 'NOW()'];
        $values  = [$idStr];

        foreach ($clean as $field => $value) {
            if (!in_array($field, $available, true)) continue;
            $columns[] = "`{$field}`";
            $places[]  = '?';
            $values[]  = $value;
        }

        $sql = 'INSERT INTO blogs (' . implode(', ', $columns) . ') VALUES ('
             . implode(', ', $places) . ')';
        $pdo->prepare($sql)->execute($values);

        // Keep the full payload in the draft too, so fields the schema lacks survive.
        $stmtDraft = $pdo->prepare('UPDATE blogs SET draft_data_json = ?' . rocSeoResetSql($pdo) . ' WHERE id = ?');
        $stmtDraft->execute([json_encode($clean, JSON_UNESCAPED_SLASHES), $idStr]);

        logCmsAudit('cms_blog_create', 'blog', $idStr, ['title' => $title]);

        echo json_encode(['success' => true, 'id' => $idStr, 'slug' => $slug, 'version' => 1]);
        exit();
    }

    /* ------------------------------------------------------ save_draft */

    if ($action === 'save_draft') {
        $expectedVersion = (int)($data['version'] ?? 1);
        [$clean, $error] = rocCleanBlogPayload($data['draft'] ?? $data);
        if ($error) rocFail($error);

        if (isset($clean['slug']) && $clean['slug'] !== '') {
            $clean['slug'] = rocUniqueSlug($pdo, $clean['slug'], $id);
        }

        $draftJson = json_encode($clean, JSON_UNESCAPED_SLASHES);

        $stmtUpd = $pdo->prepare(
            "UPDATE blogs
                SET draft_data_json = ?, draft_status = 'draft_saved',
                    version = version + 1, updated_at = NOW(),
                    locked_by = ?, locked_at = NOW()" . rocSeoResetSql($pdo) . "
              WHERE id = ? AND version = ?"
        );
        $stmtUpd->execute([$draftJson, $session['user_id'], $id, $expectedVersion]);

        if ($stmtUpd->rowCount() === 0) {
            rocFail('This post was changed by someone else while you were editing. '
                  . 'Reload to see their version before saving again.', 409);
        }

        $newVersion = $expectedVersion + 1;
        logCmsAudit('cms_blog_save_draft', 'blog', $id, ['version' => $newVersion]);

        echo json_encode([
            'success' => true,
            'message' => 'Draft saved.',
            'version' => $newVersion,
            'slug'    => $clean['slug'] ?? null,
        ]);
        exit();
    }

    /* --------------------------------------------------------- publish */

    // schedule = publish now in every respect (checks, slug, read time, revision) except
    // that the post stays 'scheduled' until cron/cli-blog-scheduler.php makes it live
    // at published_at. Only for posts that are not live yet.
    if ($action === 'publish' || $action === 'schedule') {
        $expectedVersion = (int)($data['version'] ?? 1);
        $scheduleAt = null;
        if ($action === 'schedule') {
            try {
                $when = new DateTime((string)($data['publish_at'] ?? ''));
                $when->setTimezone(new DateTimeZone(date_default_timezone_get()));
            } catch (\Throwable $e) {
                rocFail('Choose a valid date and time for publishing.');
            }
            if ($when->getTimestamp() < time() + 60) rocFail('Choose a time at least one minute from now.');
            if ($when->getTimestamp() > time() + 400 * 86400) rocFail('Choose a time within the next year.');
            $cur = $pdo->prepare('SELECT status FROM blogs WHERE id = ? LIMIT 1');
            $cur->execute([$id]);
            if ($cur->fetchColumn() === 'published') rocFail('This post is already live. Unpublish it first to schedule it.');
            $scheduleAt = $when->format('Y-m-d H:i:s');
        }
        [$clean, $error] = rocCleanBlogPayload($data['draft'] ?? $data);
        if ($error) rocFail($error);

        rocRequirePublishFields($clean);
        $title = $clean['title'];
        // Sharing metadata is derived, never typed in.
        $clean['og_title']            = $clean['meta_title'];
        $clean['og_description']      = $clean['meta_description'];
        $clean['og_image']            = $clean['image'] ?? '';
        $clean['og_type']             = 'article';
        $clean['twitter_card']        = 'summary_large_image';
        $clean['twitter_title']       = $clean['meta_title'];
        $clean['twitter_description'] = $clean['meta_description'];
        $clean['twitter_image']       = $clean['image'] ?? '';
        $clean['read_time']           = rocReadTimeFromHtml((string)($clean['content'] ?? ''));
        $clean['slug']             = rocUniqueSlug(
            $pdo,
            ($clean['slug'] ?? '') ?: rocAgentSlugify($title),
            $id
        );
        $clean['category']         = ($clean['category'] ?? '') ?: 'general';

        $available = rocBlogColumns($pdo);
        $assignments = [];
        $values = [];
        foreach ($clean as $field => $value) {
            if (!in_array($field, $available, true)) continue;
            $assignments[] = "`{$field}` = ?";
            $values[] = $value;
        }
        if (!$assignments) rocFail('Nothing to publish.');

        $pdo->beginTransaction();
        try {
            $sql = 'UPDATE blogs SET ' . implode(', ', $assignments)
                 . ($scheduleAt ? ", status = 'scheduled', draft_status = 'none'," : ", status = 'published', draft_status = 'none',")
                 . ($scheduleAt ? '  published_at = ?,' : '  published_at = IFNULL(published_at, NOW()),')
                 . '  content_modified_at = NOW(), draft_data_json = NULL,'
                 . '  locked_by = NULL, locked_at = NULL,'
                 . '  version = version + 1, updated_at = NOW()'
                 . rocSeoResetSql($pdo)
                 . ' WHERE id = ? AND version = ?';
            if ($scheduleAt) $values[] = $scheduleAt;
            $values[] = $id;
            $values[] = $expectedVersion;

            $stmtPub = $pdo->prepare($sql);
            $stmtPub->execute($values);

            if ($stmtPub->rowCount() === 0) {
                $pdo->rollBack();
                rocFail('This post was changed by someone else while you were editing. '
                      . 'Reload before publishing.', 409);
            }

            $newVersion = $expectedVersion + 1;

            $stmtRev = $pdo->prepare(
                "INSERT INTO cms_revisions (content_type, content_id, version_num, data_json, created_by, created_at)
                 VALUES ('blog', ?, ?, ?, ?, NOW())"
            );
            $stmtRev->execute([$id, $newVersion, json_encode($clean, JSON_UNESCAPED_SLASHES), $session['user_id']]);

            $stmtQ = $pdo->prepare(
                "INSERT INTO cms_cache_queue (content_type, content_id, target_version_id, action, status, created_at)
                 VALUES ('blog', ?, ?, 'purge_and_prerender', 'pending', NOW())"
            );
            $stmtQ->execute([$id, $newVersion]);

            $pdo->commit();

            logCmsAudit($scheduleAt ? 'cms_blog_schedule' : 'cms_blog_publish', 'blog', $id,
                        ['version' => $newVersion, 'title' => $title] + ($scheduleAt ? ['publish_at' => $scheduleAt] : []));

            if ($scheduleAt) {
                echo json_encode([
                    'success'      => true,
                    'message'      => 'Scheduled.',
                    'version'      => $newVersion,
                    'slug'         => $clean['slug'],
                    'status'       => 'scheduled',
                    'published_at' => $scheduleAt,
                    'publish_at'   => $when->format(DATE_ATOM),
                ]);
                exit();
            }

            echo json_encode([
                'success' => true,
                'message' => 'Published.',
                'version' => $newVersion,
                'slug'    => $clean['slug'],
                'url'     => '/blogs/' . $clean['slug'] . '/',
            ]);
            exit();

        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('CMS publish failed: ' . $e->getMessage());
            rocFail('Publishing failed. Your draft is still saved. Please try again.', 500);
        }
    }

    /* ------------------------------------------------- status changes */

    // Status only. Content is saved through save_draft / publish; this used to
    // accept a full payload and quietly write none of it.
    if ($action === 'unpublish' || $action === 'update' || $action === 'set_status') {
        // The version is mandatory. Treating a missing or zero version as
        // "skip the check" would let a caller bypass concurrency control by
        // simply omitting the field.
        if (!isset($data['version']) || (int)$data['version'] < 1) {
            rocFail('A version is required for a status change. Reload the post and try again.');
        }
        $expectedVersion = (int)$data['version'];
        $wanted = $action === 'unpublish' ? 'draft' : ($data['status'] ?? 'draft');
        $status = $wanted === 'published' ? 'published' : 'draft';
        $draftStatus = $status === 'published' ? 'none' : 'draft_saved';

        // Do not let a post go live without the fields that publishing requires.
        if ($status === 'published') {
            $check = $pdo->prepare('SELECT * FROM blogs WHERE id = ? LIMIT 1');
            $check->execute([$id]);
            $row = $check->fetch();
            if (!$row) rocFail('Blog post not found.', 404);
            rocRequirePublishFields($row);
        }

        $sql = 'UPDATE blogs SET status = ?, draft_status = ?, version = version + 1, updated_at = NOW()'
             . ($status === 'published' ? ', published_at = IFNULL(published_at, NOW())' . rocSeoResetSql($pdo) : '')
             . ' WHERE id = ? AND version = ?';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$status, $draftStatus, $id, $expectedVersion]);

        if ($stmt->rowCount() === 0) {
            // Distinguish "gone" from "changed underneath you".
            $exists = $pdo->prepare('SELECT COUNT(*) FROM blogs WHERE id = ?');
            $exists->execute([$id]);
            if ((int)$exists->fetchColumn() === 0) rocFail('Blog post not found.', 404);
            rocFail('This post was changed by someone else. Reload and try again.', 409);
        }

        // A status change alters what the site serves, so the cache must go too.
        $pdo->prepare(
            "INSERT INTO cms_cache_queue (content_type, content_id, target_version_id, action, status, created_at)
             VALUES ('blog', ?, ?, 'purge_and_prerender', 'pending', NOW())"
        )->execute([$id, $expectedVersion + 1]);

        logCmsAudit('cms_blog_update_status', 'blog', $id, ['status' => $status]);
        echo json_encode([
            'success' => true,
            'message' => $status === 'published' ? 'Post is live.' : 'Post moved back to draft.',
            'version' => $expectedVersion + 1,
        ]);
        exit();
    }

    /* ---------------------------------------------------------- delete */
    // Soft delete: the post leaves the site and the CMS list, the row and its
    // revisions stay in the database so it can be recovered by an administrator.
    if ($action === 'delete') {
        if (!isset($data['version']) || (int)$data['version'] < 1) rocFail('Reload the post and try again.');
        $stmt = $pdo->prepare("UPDATE blogs SET status = 'archived', draft_status = 'none', locked_by = NULL, locked_at = NULL,
                                      version = version + 1, updated_at = NOW() WHERE id = ? AND version = ?");
        $stmt->execute([$id, (int)$data['version']]);
        if ($stmt->rowCount() === 0) rocFail('This post was changed by someone else. Reload and try again.', 409);
        logCmsAudit('cms_blog_delete', 'blog', $id, ['soft' => true]);
        echo json_encode(['success' => true, 'message' => 'Post deleted. It is no longer on the website.']);
        exit();
    }

    /* ---------------------------------------------------- release_lock */

    if ($action === 'release_lock') {
        $stmt = $pdo->prepare(
            'UPDATE blogs SET locked_by = NULL, locked_at = NULL WHERE id = ? AND locked_by = ?'
        );
        $stmt->execute([$id, $session['user_id']]);
        echo json_encode(['success' => true]);
        exit();
    }

    rocFail('Unknown action: ' . htmlspecialchars((string)$action));
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
