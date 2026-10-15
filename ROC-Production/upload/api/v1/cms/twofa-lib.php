<?php
/**
 * Run On Console — 2-step verification for CMS sign-in (Deploy 69).
 *
 * Time-based codes (TOTP, RFC 6238: 6 digits, 30 seconds), the same as Google Authenticator,
 * Microsoft Authenticator or Authy. Each CMS user turns it on for their own account in
 * CMS > 2-step verification; from then on sign-in asks for the code after the password.
 * 8 one-time backup codes are shown once, for a lost phone.
 *
 * Table cms_user_2fa (created by cli-cms-2fa.php): user_id, secret, enabled, backup_json (hashes),
 * fails, locked_until. auth.php calls rocTwofaLoginGate() after the password is checked (the hook is
 * added by cli-cms-2fa.php). Locked out? cPanel Terminal: php cli-cms-2fa.php --disable=USERNAME
 */
if (defined('ROC_TWOFA_LIB')) return;
define('ROC_TWOFA_LIB', 1);

const ROC_TWOFA_ISSUER = 'Run On Console CMS';
const ROC_TWOFA_MAX_FAILS = 5;          // wrong codes in a row, then ...
const ROC_TWOFA_LOCK_MIN = 15;          // ... this many minutes locked

function rocTwofaTable(PDO $pdo): bool {
    try { $pdo->query('SELECT user_id FROM cms_user_2fa LIMIT 1'); return true; } catch (\Throwable $e) { return false; }
}

function rocTwofaB32Encode(string $bin): string {
    $a = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $bits = ''; $out = '';
    foreach (str_split($bin) as $c) $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
    foreach (str_split($bits, 5) as $chunk) $out .= $a[bindec(str_pad($chunk, 5, '0'))];
    return $out;
}

function rocTwofaB32Decode(string $s): string {
    $a = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $bits = ''; $out = '';
    foreach (str_split(strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $s))) as $c) $bits .= str_pad(decbin((int)strpos($a, $c)), 5, '0', STR_PAD_LEFT);
    foreach (str_split($bits, 8) as $byte) if (strlen($byte) === 8) $out .= chr(bindec($byte));
    return $out;
}

/** The 6-digit code of a secret for a 30-second step. */
function rocTwofaCode(string $secretB32, int $step): string {
    $key = rocTwofaB32Decode($secretB32);
    $msg = pack('N*', 0) . pack('N*', $step);
    $h = hash_hmac('sha1', $msg, $key, true);
    $o = ord($h[19]) & 0xf;
    $n = ((ord($h[$o]) & 0x7f) << 24) | (ord($h[$o + 1]) << 16) | (ord($h[$o + 2]) << 8) | ord($h[$o + 3]);
    return str_pad((string)($n % 1000000), 6, '0', STR_PAD_LEFT);
}

/** A code is right for now, 30 seconds before or after (phone clocks drift a little). */
function rocTwofaCheck(string $secretB32, string $code): bool {
    $code = preg_replace('/\D/', '', $code);
    if (strlen($code) !== 6) return false;
    $t = intdiv(time(), 30);
    foreach ([-1, 0, 1] as $d) if (hash_equals(rocTwofaCode($secretB32, $t + $d), $code)) return true;
    return false;
}

function rocTwofaRow(PDO $pdo, int $uid): ?array {
    if (!rocTwofaTable($pdo)) return null;
    $st = $pdo->prepare('SELECT * FROM cms_user_2fa WHERE user_id = ?');
    $st->execute([$uid]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    return $r ?: null;
}

function rocTwofaEnabled(PDO $pdo, int $uid): bool { $r = rocTwofaRow($pdo, $uid); return $r && (int)$r['enabled'] === 1; }

function rocTwofaSave(PDO $pdo, int $uid, array $f): void {
    $row = rocTwofaRow($pdo, $uid);
    if ($row) {
        $set = implode(', ', array_map(function ($k) { return "{$k} = ?"; }, array_keys($f)));
        $pdo->prepare("UPDATE cms_user_2fa SET {$set}, updated_at = ? WHERE user_id = ?")->execute(array_merge(array_values($f), [gmdate('Y-m-d H:i:s'), $uid]));
    } else {
        $f += ['secret' => '', 'enabled' => 0, 'backup_json' => '[]', 'fails' => 0, 'locked_until' => null];
        $pdo->prepare('INSERT INTO cms_user_2fa (user_id, secret, enabled, backup_json, fails, locked_until, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([$uid, $f['secret'], $f['enabled'], $f['backup_json'], $f['fails'], $f['locked_until'], gmdate('Y-m-d H:i:s')]);
    }
}

/** Step 1 of turning it on: a new secret (not active until a code proves the phone has it). */
function rocTwofaStart(PDO $pdo, array $session): array {
    $secret = rocTwofaB32Encode(random_bytes(20));
    rocTwofaSave($pdo, (int)$session['user_id'], ['secret' => $secret, 'enabled' => 0, 'backup_json' => '[]', 'fails' => 0, 'locked_until' => null]);
    $label = rawurlencode(ROC_TWOFA_ISSUER . ':' . ($session['username'] ?? 'admin'));
    return ['secret' => trim(chunk_split($secret, 4, ' ')), 'uri' => 'otpauth://totp/' . $label . '?secret=' . $secret . '&issuer=' . rawurlencode(ROC_TWOFA_ISSUER) . '&digits=6&period=30'];
}

/** Step 2: the first code from the phone switches it on; returns the backup codes (shown once). */
function rocTwofaEnable(PDO $pdo, int $uid, string $code): ?array {
    $r = rocTwofaRow($pdo, $uid);
    if (!$r || $r['secret'] === '' || !rocTwofaCheck($r['secret'], $code)) return null;
    $codes = []; $hashes = [];
    for ($i = 0; $i < 8; $i++) { $c = strtoupper(bin2hex(random_bytes(4))); $c = substr($c, 0, 4) . '-' . substr($c, 4); $codes[] = $c; $hashes[] = password_hash($c, PASSWORD_DEFAULT); }
    rocTwofaSave($pdo, $uid, ['enabled' => 1, 'backup_json' => json_encode($hashes), 'fails' => 0, 'locked_until' => null]);
    return $codes;
}

function rocTwofaDisable(PDO $pdo, int $uid): void {
    if (rocTwofaRow($pdo, $uid)) $pdo->prepare('DELETE FROM cms_user_2fa WHERE user_id = ?')->execute([$uid]);
}

/** A code from the app or an unused backup code (a backup code works once). Counts wrong tries. */
function rocTwofaVerify(PDO $pdo, int $uid, string $code): array {
    $r = rocTwofaRow($pdo, $uid);
    if (!$r || (int)$r['enabled'] !== 1) return ['ok' => true];
    if (!empty($r['locked_until']) && strtotime($r['locked_until'] . ' UTC') > time())
        return ['ok' => false, 'error' => 'Too many wrong codes. Try again in ' . max(1, (int)ceil((strtotime($r['locked_until'] . ' UTC') - time()) / 60)) . ' minute(s).'];
    $ok = rocTwofaCheck($r['secret'], $code);
    if (!$ok && preg_match('/^[0-9A-F]{4}-?[0-9A-F]{4}$/i', trim($code))) {
        $c = strtoupper(trim($code)); if (strpos($c, '-') === false) $c = substr($c, 0, 4) . '-' . substr($c, 4);
        $hashes = json_decode((string)$r['backup_json'], true) ?: [];
        foreach ($hashes as $i => $h) if (password_verify($c, $h)) { unset($hashes[$i]); $ok = true; rocTwofaSave($pdo, $uid, ['backup_json' => json_encode(array_values($hashes))]); break; }
    }
    if ($ok) { rocTwofaSave($pdo, $uid, ['fails' => 0, 'locked_until' => null]); return ['ok' => true]; }
    $fails = (int)$r['fails'] + 1;
    rocTwofaSave($pdo, $uid, $fails >= ROC_TWOFA_MAX_FAILS ? ['fails' => 0, 'locked_until' => gmdate('Y-m-d H:i:s', time() + ROC_TWOFA_LOCK_MIN * 60)] : ['fails' => $fails]);
    return ['ok' => false, 'error' => $fails >= ROC_TWOFA_MAX_FAILS ? 'Too many wrong codes. Try again in ' . ROC_TWOFA_LOCK_MIN . ' minutes.' : 'That code is not right. Use the newest 6-digit code from your authenticator app.'];
}

/**
 * Called by auth.php after the password is right and before the session is made. Without 2-step
 * verification, or with a right code, it returns; otherwise it answers {need_2fa} and stops.
 */
function rocTwofaLoginGate(PDO $pdo, array $user, array $data): void {
    if (!rocTwofaEnabled($pdo, (int)$user['id'])) return;
    $code = trim((string)($data['otp'] ?? ''));
    if ($code === '') {
        http_response_code(401);
        echo json_encode(['success' => false, 'need_2fa' => true, 'code' => 'NEED_2FA', 'error' => 'Enter the 6-digit code from your authenticator app.']);
        exit();
    }
    $v = rocTwofaVerify($pdo, (int)$user['id'], $code);
    if (!$v['ok']) {
        if (function_exists('logCmsAudit')) logCmsAudit('cms_login_2fa_failed', 'cms_user', (string)$user['id']);
        http_response_code(401);
        echo json_encode(['success' => false, 'need_2fa' => true, 'code' => 'NEED_2FA', 'error' => $v['error']]);
        exit();
    }
}
