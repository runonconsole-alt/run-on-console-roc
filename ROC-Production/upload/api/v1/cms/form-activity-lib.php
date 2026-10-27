<?php
/**
 * Run On Console — who used which form, for CMS > Live activity (Deploy 120). The owner wants to see every
 * login, sign-up code, password reset and form message (contact, support, write for us, suggestion, comment,
 * forms filled by the ROC agents, emails to the mailboxes) with the person's name and email.
 *
 * Read from the tables the site already writes: roc_messages (every form and mailbox email), sessions (a
 * login makes a row; a logout deletes it, so new rows are copied into roc_login_log by the scheduler and by
 * the CMS, see rocFaSync) and roc_auth_challenges (sign-up codes and password resets). Nothing is added to
 * the sign-in code. Sign-ups, comments, AI chats and buy clicks stay in the Live activity feed they had.
 */

const ROC_FA_DAYS = 30;
const ROC_FA_KIND = ['contact' => 'Contact form', 'support' => 'Support form', 'write_for_us' => 'Write for us form',
                     'suggestion' => 'Suggestions form', 'comment' => 'Comment form', 'partnership' => 'Partnership form'];

function rocFaTable(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $pdo->exec('CREATE TABLE IF NOT EXISTS roc_login_log (
        session_id INT UNSIGNED NOT NULL PRIMARY KEY, user_id INT UNSIGNED NOT NULL, ip VARCHAR(45) NOT NULL DEFAULT \'\',
        device VARCHAR(80) NOT NULL DEFAULT \'\', created_at DATETIME NOT NULL, KEY k_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    $done = true;
}

/** "Chrome on Windows" from a user agent. */
function rocFaDevice(string $ua): string {
    $b = preg_match('/Edg\//', $ua) ? 'Edge' : (preg_match('/OPR\/|Opera/', $ua) ? 'Opera' : (preg_match('/Chrome\//', $ua) ? 'Chrome'
       : (preg_match('/Firefox\//', $ua) ? 'Firefox' : (preg_match('/Safari\//', $ua) ? 'Safari' : 'Browser'))));
    $o = preg_match('/Android/', $ua) ? 'Android' : (preg_match('/iPhone|iPad/', $ua) ? 'iPhone' : (preg_match('/Windows/', $ua) ? 'Windows'
       : (preg_match('/Mac OS/', $ua) ? 'Mac' : (preg_match('/Linux/', $ua) ? 'Linux' : ''))));
    return $b . ($o !== '' ? ' on ' . $o : '');
}

/** Copies new logins (sessions rows) into roc_login_log before a logout deletes them. Returns how many. */
function rocFaSync(PDO $pdo): int {
    try {
        rocFaTable($pdo);
        $last = (int)$pdo->query('SELECT COALESCE(MAX(session_id), 0) FROM roc_login_log')->fetchColumn();
        $st = $pdo->prepare('SELECT id, user_id, ip_address, user_agent, created_at FROM sessions WHERE id > ? ORDER BY id LIMIT 500');
        $st->execute([$last]);
        $ins = $pdo->prepare('INSERT IGNORE INTO roc_login_log (session_id, user_id, ip, device, created_at) VALUES (?, ?, ?, ?, ?)');
        $n = 0;
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $ins->execute([(int)$r['id'], (int)$r['user_id'], mb_substr((string)$r['ip_address'], 0, 45), rocFaDevice((string)$r['user_agent']), (string)$r['created_at']]);
            $n++;
        }
        return $n;
    } catch (\Throwable $e) { return 0; }
}

/** Feed items {type, at, title, detail, link}, newest first, for the last ROC_FA_DAYS days. */
function rocFaItems(PDO $pdo, int $limit = 300): array {
    rocFaSync($pdo);
    $since = date('Y-m-d H:i:s', time() - ROC_FA_DAYS * 86400);
    $who = function ($name, $email) { $name = trim((string)$name); $email = trim((string)$email); return $name !== '' && $email !== '' ? $name . ' (' . $email . ')' : ($name . $email); };
    $out = [];
    // Forms and mailbox emails
    try {
        $st = $pdo->prepare('SELECT id, kind, name, email, subject, meta_json, ip, created_at FROM roc_messages WHERE created_at > ? ORDER BY id DESC LIMIT ' . (int)$limit);
        $st->execute([$since]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $meta = json_decode((string)$r['meta_json'], true) ?: [];
            $by = (string)($meta['via'] ?? '') === 'email' ? 'Email to ' . (string)($meta['to'] ?? $r['kind'] . ' mailbox')
                : (!empty($meta['agent']) ? 'ROC agent ' . (string)$meta['agent'] . ' filled the ' . (ROC_FA_KIND[$r['kind']] ?? 'form')
                : (ROC_FA_KIND[$r['kind']] ?? ucfirst(str_replace('_', ' ', (string)$r['kind'])) . ' form'));
            $out[] = ['type' => 'form', 'at' => (string)$r['created_at'], 'title' => $who($r['name'], $r['email']) . ' · ' . $by,
                      'detail' => implode(' · ', array_filter([(string)$r['subject'], !empty($meta['page']) ? 'from ' . (string)$meta['page'] : '', (string)$r['ip'] !== '' ? 'IP ' . $r['ip'] : ''])), 'link' => '/cms/#messages'];
        }
    } catch (\Throwable $e) {}
    // Logins
    try {
        $st = $pdo->prepare('SELECT l.ip, l.device, l.created_at, u.name, u.email FROM roc_login_log l LEFT JOIN users u ON u.id = l.user_id
                             WHERE l.created_at > ? ORDER BY l.created_at DESC LIMIT ' . (int)$limit);
        $st->execute([$since]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r)
            $out[] = ['type' => 'login', 'at' => (string)$r['created_at'], 'title' => $who($r['name'], $r['email']) . ' · Signed in',
                      'detail' => trim((string)$r['device'] . ((string)$r['ip'] !== '' ? ' · IP ' . $r['ip'] : '')), 'link' => ''];
    } catch (\Throwable $e) {}
    // Sign-up codes and password resets
    try {
        $st = $pdo->prepare('SELECT c.purpose, c.created_at, c.consumed_at, u.name, u.email FROM roc_auth_challenges c LEFT JOIN users u ON u.id = c.user_id
                             WHERE c.created_at > ? ORDER BY c.created_at DESC LIMIT ' . (int)$limit);
        $st->execute([$since]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r)
            $out[] = ['type' => 'code', 'at' => (string)$r['created_at'],
                      'title' => $who($r['name'], $r['email']) . ' · ' . ((string)$r['purpose'] === 'reset' ? 'Asked to reset the password' : 'Got a sign-up code by email'),
                      'detail' => !empty($r['consumed_at']) ? 'Code entered' : 'Code not entered (yet)', 'link' => ''];
    } catch (\Throwable $e) {}
    usort($out, function ($a, $b) { return strcmp($b['at'], $a['at']); });
    return array_slice($out, 0, $limit);
}
