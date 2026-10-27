<?php
/**
 * Run On Console — members: wishlist and the weekly email (Deploy 70).
 *
 * Wishlist: signed-in members save products (/products/{slug}/) and games (/can-i-run/{slug}/) with the
 * "Save" button that roc-nav.js adds to those pages; the list is in Profile > Wishlist.
 * Weekly email (opt-in, off until the member turns it on): every Friday from 18:00 Pakistan time the
 * scheduler cron queues one email per subscriber in email_queue: the week's top news, new blog posts and
 * their wishlist. Every email has a one-click unsubscribe link. CMS setting digest_on = 'off' stops it.
 *
 * Tables (made here when missing): roc_wishlist (user_id, item_type, item_key, created_at),
 * roc_member_mail (user_id, weekly, token, last_sent).
 */
if (defined('ROC_MEMBER_LIB')) return;
define('ROC_MEMBER_LIB', 1);

const ROC_DIGEST_DAY = 5;          // Friday (ISO-8601 day number)
const ROC_DIGEST_HOUR = 18;        // from 18:00 Pakistan time

function rocMemberTables(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $sqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    $tail = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    try { $pdo->query('SELECT 1 FROM roc_wishlist LIMIT 1'); }
    catch (\Throwable $e) {
        $pdo->exec('CREATE TABLE IF NOT EXISTS roc_wishlist (user_id INT NOT NULL, item_type VARCHAR(10) NOT NULL, item_key VARCHAR(160) NOT NULL,
                    created_at DATETIME NOT NULL, PRIMARY KEY (user_id, item_type, item_key))' . $tail);
    }
    try { $pdo->query('SELECT 1 FROM roc_member_mail LIMIT 1'); }
    catch (\Throwable $e) {
        $pdo->exec('CREATE TABLE IF NOT EXISTS roc_member_mail (user_id INT NOT NULL PRIMARY KEY, weekly TINYINT(1) NOT NULL DEFAULT 0,
                    token VARCHAR(64) NOT NULL, last_sent DATETIME NULL)' . $tail);
    }
}

/** The member's saved items with their titles, pages and pictures (items that no longer exist are left out). */
function rocWishlist(PDO $pdo, string $root, int $uid): array {
    rocMemberTables($pdo);
    $st = $pdo->prepare('SELECT item_type, item_key, created_at FROM roc_wishlist WHERE user_id = ? ORDER BY created_at DESC');
    $st->execute([$uid]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    $out = [];
    $prod = $pdo->prepare("SELECT title, image, best_for FROM products WHERE slug = ? AND status = 'published' LIMIT 1");
    $games = null;
    foreach ($rows as $r) {
        if ($r['item_type'] === 'product') {
            $prod->execute([$r['item_key']]);
            $p = $prod->fetch(PDO::FETCH_ASSOC);
            if (!$p) continue;
            $out[] = ['type' => 'product', 'key' => $r['item_key'], 'title' => (string)$p['title'], 'url' => '/products/' . $r['item_key'] . '/',
                      'image' => (string)$p['image'], 'note' => (string)$p['best_for'], 'saved' => $r['created_at']];
        } elseif ($r['item_type'] === 'game') {
            if ($games === null) { require_once __DIR__ . '/game-pages-lib.php'; $games = rocGpAll($root); }
            $g = $games[$r['item_key']] ?? null;
            if (!$g) continue;
            $out[] = ['type' => 'game', 'key' => $r['item_key'], 'title' => $g['name'], 'url' => rocGpPath($g), 'image' => '',
                      'note' => 'PC requirements and a quick check', 'saved' => $r['created_at']];
        }
    }
    return $out;
}

/** Saves or removes an item; returns true when it is saved now. */
function rocWishlistToggle(PDO $pdo, string $root, int $uid, string $type, string $key): ?bool {
    rocMemberTables($pdo);
    if (!in_array($type, ['product', 'game'], true) || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $key) || strlen($key) > 160) return null;
    if ($type === 'product') {
        $st = $pdo->prepare("SELECT 1 FROM products WHERE slug = ? AND status = 'published'"); $st->execute([$key]);
        if (!$st->fetchColumn()) return null;
    } else {
        require_once __DIR__ . '/game-pages-lib.php';
        if (!isset(rocGpAll($root)[$key])) return null;
    }
    $st = $pdo->prepare('SELECT 1 FROM roc_wishlist WHERE user_id = ? AND item_type = ? AND item_key = ?');
    $st->execute([$uid, $type, $key]);
    if ($st->fetchColumn()) {
        $pdo->prepare('DELETE FROM roc_wishlist WHERE user_id = ? AND item_type = ? AND item_key = ?')->execute([$uid, $type, $key]);
        return false;
    }
    $n = $pdo->prepare('SELECT COUNT(*) FROM roc_wishlist WHERE user_id = ?'); $n->execute([$uid]);
    if ((int)$n->fetchColumn() >= 200) return null;   // a sane limit
    $pdo->prepare('INSERT INTO roc_wishlist (user_id, item_type, item_key, created_at) VALUES (?, ?, ?, ?)')->execute([$uid, $type, $key, gmdate('Y-m-d H:i:s')]);
    return true;
}

/** ['weekly' => bool, 'token' => ...] (a row is made the first time). */
function rocMemberMail(PDO $pdo, int $uid): array {
    rocMemberTables($pdo);
    $st = $pdo->prepare('SELECT weekly, token, last_sent FROM roc_member_mail WHERE user_id = ?');
    $st->execute([$uid]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    if ($r) return ['weekly' => (int)$r['weekly'] === 1, 'token' => (string)$r['token'], 'last_sent' => $r['last_sent']];
    $t = bin2hex(random_bytes(20));
    $pdo->prepare('INSERT INTO roc_member_mail (user_id, weekly, token) VALUES (?, 0, ?)')->execute([$uid, $t]);
    return ['weekly' => false, 'token' => $t, 'last_sent' => null];
}

function rocMemberWeeklySet(PDO $pdo, int $uid, bool $on): void {
    rocMemberMail($pdo, $uid);
    $pdo->prepare('UPDATE roc_member_mail SET weekly = ? WHERE user_id = ?')->execute([$on ? 1 : 0, $uid]);
}

/** The unsubscribe link of a member's email. */
function rocMemberUnsubUrl(string $token): string { return 'https://runonconsole.com/api/v1/member.php?unsub=' . rawurlencode($token); }

/* ------------------------------------------------------------ the weekly email */

function rocDigestH($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/** This week's news and blog posts (published in the last 7 days), shared by every email. */
function rocDigestContent(PDO $pdo): array {
    $since = gmdate('Y-m-d H:i:s', time() - 7 * 86400);
    $st = $pdo->prepare("SELECT title, slug, excerpt, category FROM blogs WHERE status = 'published' AND published_at >= ? ORDER BY published_at DESC");
    $st->execute([$since]);
    $news = []; $blogs = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $b) {
        $isNews = strcasecmp(trim((string)$b['category']), 'News') === 0;
        $item = ['title' => (string)$b['title'], 'url' => 'https://runonconsole.com/' . ($isNews ? 'news' : 'blogs') . '/' . $b['slug'] . '/', 'excerpt' => (string)$b['excerpt']];
        if ($isNews) { if (count($news) < 5) $news[] = $item; } elseif (count($blogs) < 3) $blogs[] = $item;
    }
    return ['news' => $news, 'blogs' => $blogs];
}

/** One member's email: [subject, html]. */
function rocDigestEmail(array $content, array $wishlist, string $name, string $unsubUrl): array {
    $first = trim(explode(' ', trim($name))[0] ?? '');
    $sec = function (string $title, array $items) {
        if (!$items) return '';
        $h = '<h2 style="font-size:17px;margin:26px 0 10px;color:#064e3b">' . $title . '</h2>';
        foreach ($items as $it) $h .= '<p style="margin:0 0 12px"><a href="' . rocDigestH($it['url']) . '" style="color:#047857;font-weight:bold;text-decoration:none">' . rocDigestH($it['title']) . '</a>'
            . (($it['excerpt'] ?? '') !== '' ? '<br><span style="color:#475569;font-size:14px">' . rocDigestH(mb_substr($it['excerpt'], 0, 160)) . '</span>' : '') . '</p>';
        return $h;
    };
    $wl = array_map(function ($w) { return ['title' => $w['title'], 'url' => 'https://runonconsole.com' . $w['url'],
        'excerpt' => $w['type'] === 'product' ? 'See the details and check the current price on the product page.' : 'Check if your PC runs it.']; }, array_slice($wishlist, 0, 6));
    // <!--roc-from:alerts-->: sent from "Run On Console Updates" <alerts@runonconsole.com> (cli-alerts-sender.php).
    $html = '<!doctype html><!--roc-from:alerts--><html><body style="margin:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#0f172a">'
        . '<div style="max-width:600px;margin:0 auto;background:#fff">'
        . '<div style="background:#064e3b;color:#fff;padding:22px 24px"><div style="font-size:20px;font-weight:bold">Run On Console</div><div style="color:#a7f3d0;font-size:13px">Your week in gaming</div></div>'
        . '<div style="padding:8px 24px 24px;font-size:15px;line-height:1.6">'
        . '<p style="margin-top:18px">Hi' . ($first !== '' ? ' ' . rocDigestH($first) : '') . ', here is what happened this week.</p>'
        . $sec('Top gaming news', $content['news']) . $sec('New guides on the site', $content['blogs']) . $sec('Your wishlist', $wl)
        . '<p style="margin-top:26px"><a href="https://runonconsole.com/can-i-run/" style="background:#10b981;color:#052e2b;font-weight:bold;padding:10px 16px;border-radius:10px;text-decoration:none">Can your PC run it? Check 2,000+ games</a></p>'
        . '</div><div style="padding:16px 24px;background:#f8fafc;color:#64748b;font-size:12px;line-height:1.6">'
        . 'You get this because you turned on the weekly email in your Run On Console profile. '
        . '<a href="' . rocDigestH($unsubUrl) . '" style="color:#64748b">Unsubscribe</a> (one click). Run On Console, Lahore, Pakistan.</div></div></body></html>';
    $top = $content['news'][0]['title'] ?? ($content['blogs'][0]['title'] ?? 'new gaming guides');
    return ['This week in gaming: ' . mb_substr($top, 0, 90), $html];
}

/** Puts one email in the queue. The worker sends the highest priority first: 1 for the weekly email (after sign-up
 * and password emails), 9 for a test from the CMS (right away). */
function rocDigestQueue(PDO $pdo, string $to, string $subject, string $html, int $priority = 1): bool {
    try {
        $pdo->prepare("INSERT INTO email_queue (to_email, subject, html_body, status, attempts, next_attempt_at, priority) VALUES (?, ?, ?, 'pending', 0, NOW(), ?)")
            ->execute([$to, mb_substr($subject, 0, 250), $html, $priority]);
        return true;
    } catch (\Throwable $e) {
        try { $pdo->prepare("INSERT INTO email_queue (to_email, subject, html_body, status) VALUES (?, ?, ?, 'pending')")->execute([$to, mb_substr($subject, 0, 250), $html]); return true; }
        catch (\Throwable $e2) { error_log('roc digest queue: ' . $e2->getMessage()); return false; }
    }
}

/**
 * Called by the scheduler cron: on Friday from 18:00 Pakistan time, once a week, one email per subscriber
 * (verified, active members who turned it on). Returns a log line or null.
 */
function rocDigestTick(PDO $pdo, string $root, bool $force = false, ?int $onlyUser = null, ?string $toOverride = null): ?string {
    require_once __DIR__ . '/cms/blog-engine-lib.php';
    if (!$force && (string)rocBlogGet($pdo, 'digest_on', 'on') === 'off') return null;
    $now = new DateTime('now', new DateTimeZone('Asia/Karachi'));
    $week = $now->format('o-\WW');
    if (!$force && ((int)$now->format('N') !== ROC_DIGEST_DAY || (int)$now->format('G') < ROC_DIGEST_HOUR || rocBlogGet($pdo, 'digest_last_week', '') === $week)) return null;
    rocMemberTables($pdo);
    $content = rocDigestContent($pdo);
    if (!$force && !$content['news'] && !$content['blogs']) { rocBlogSet($pdo, 'digest_last_week', $week); return 'Weekly email: nothing new this week, not sent.'; }
    $sql = "SELECT u.id, u.name, u.email, m.token FROM roc_member_mail m JOIN users u ON u.id = m.user_id WHERE m.weekly = 1 AND u.status = 'active'";
    try { $pdo->query('SELECT email_verified FROM users LIMIT 1'); $sql .= ' AND u.email_verified = 1'; } catch (\Throwable $e) {}
    $args = [];
    if ($onlyUser !== null) { $sql = "SELECT u.id, u.name, u.email, m.token FROM roc_member_mail m JOIN users u ON u.id = m.user_id WHERE u.id = ?"; $args = [$onlyUser]; }
    $st = $pdo->prepare($sql); $st->execute($args);
    $n = 0;
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $u) {
        $to = $toOverride ?? (string)$u['email'];
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) continue;
        [$subj, $html] = rocDigestEmail($content, rocWishlist($pdo, $root, (int)$u['id']), (string)$u['name'], rocMemberUnsubUrl((string)$u['token']));
        if (rocDigestQueue($pdo, $to, $subj, $html)) {
            $n++;
            if ($onlyUser === null) $pdo->prepare('UPDATE roc_member_mail SET last_sent = ? WHERE user_id = ?')->execute([gmdate('Y-m-d H:i:s'), $u['id']]);
        }
    }
    if ($onlyUser === null) { rocBlogSet($pdo, 'digest_last_week', $week); rocBlogSet($pdo, 'digest_last', ['at' => gmdate('c'), 'sent' => $n]); }
    return 'Weekly email: ' . $n . ' queued.';
}
