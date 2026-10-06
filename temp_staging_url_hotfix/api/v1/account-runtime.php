<?php
// Shared account/session and email-outbox operations. No request-time schema changes.
require_once __DIR__ . '/config.php';

function rocUser(array $u, ?PDO $db = null): array {
    $avatar = 'avatar_01';
    if ($db && isset($u['id'])) {
        try {
            $q = $db->prepare('SELECT avatar_icon FROM user_profiles WHERE user_id = ? LIMIT 1');
            $q->execute([(int)$u['id']]);
            $p = $q->fetch();
            if ($p && !empty($p['avatar_icon']) && in_array($p['avatar_icon'], ['avatar_01','avatar_02','avatar_03','avatar_04','avatar_05','avatar_06','avatar_07','avatar_08','avatar_09','avatar_10','avatar_11','avatar_12','avatar_13','avatar_14','avatar_15','avatar_16','avatar_17','avatar_18','avatar_19','avatar_20'], true)) {
                $avatar = $p['avatar_icon'];
            }
        } catch (Throwable $e) {}
    }
    return ['id'=>(int)$u['id'], 'name'=>$u['name'], 'username'=>$u['username'],
        'email'=>$u['email'], 'isAdmin'=>(bool)$u['is_admin'],
        'emailVerified'=>(bool)$u['email_verified'], 'isVerified'=>(bool)$u['email_verified'],
        'avatarIcon'=>$avatar];
}
function rocEstablishSession(PDO $db, int $id): void {
    session_regenerate_id(true);
    $token = bin2hex(random_bytes(32));
    $q = $db->prepare('INSERT INTO sessions (user_id,session_token_hash,ip_address,user_agent,expires_at) VALUES (?,?,?,?,DATE_ADD(NOW(), INTERVAL 30 DAY))');
    $q->execute([$id,hash('sha256',$token),$_SERVER['REMOTE_ADDR']??'',substr($_SERVER['HTTP_USER_AGENT']??'',0,255)]);
    $_SESSION['user_id']=$id;
    $_SESSION['db_session_id']=(int)$db->lastInsertId();
    $_SESSION['db_session_token']=$token;
    $_SESSION['csrf_token']=bin2hex(random_bytes(32));
    $_SESSION['authenticated_at']=time();
}
function rocUserById(PDO $db, int $id): array {
    $q=$db->prepare('SELECT * FROM users WHERE id=?'); $q->execute([$id]);
    $u=$q->fetch();
    if (!$u || $u['status']==='suspended') throw new RuntimeException('Account unavailable');
    return $u;
}
function rocSafeReturn($path): string {
    // Only application profile destination is accepted by the authentication flow.
    return '/profile/';
}
function rocLimit(PDO $db, string $action, string $email, int $maximum, int $seconds): bool {
    // Both account and IP buckets are serialized; do not trust forwarded IP headers.
    $keys=[hash('sha256',$action.':ip:'.($_SERVER['REMOTE_ADDR']??'cli')),
        hash('sha256',$action.':account:'.strtolower($email))]; sort($keys);
    $db->beginTransaction();
    try {
        foreach ($keys as $key) {
            $db->prepare('INSERT IGNORE INTO roc_auth_limits (bucket,attempts,window_start) VALUES (?,0,NOW())')->execute([$key]);
            $q=$db->prepare('SELECT attempts, UNIX_TIMESTAMP(window_start) AS started FROM roc_auth_limits WHERE bucket=? FOR UPDATE');
            $q->execute([$key]); $row=$q->fetch();
            if ((int)$row['started']+$seconds <= time()) {
                $db->prepare('UPDATE roc_auth_limits SET attempts=0,window_start=NOW() WHERE bucket=?')->execute([$key]);
                $row['attempts']=0;
            }
            if ((int)$row['attempts'] >= $maximum) { $db->rollBack(); return true; }
        }
        foreach ($keys as $key) $db->prepare('UPDATE roc_auth_limits SET attempts=attempts+1 WHERE bucket=?')->execute([$key]);
        $db->commit(); return false;
    } catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
}
function rocQueue(PDO $db, string $email, string $subject, string $html, int $priority=0): int {
    $q=$db->prepare("INSERT INTO email_queue (to_email,subject,html_body,status,attempts,next_attempt_at,priority) VALUES (?,?,?,'pending',0,NOW(),?)");
    $q->execute([$email,$subject,$html,$priority]); return (int)$db->lastInsertId();
}
function rocEmail(string $heading, string $text, string $url, string $label): string {
    $esc=fn($s)=>htmlspecialchars($s,ENT_QUOTES,'UTF-8');
    return '<!doctype html><html><body style="background:#eef7f4;font-family:Arial,sans-serif;padding:24px"><main style="max-width:560px;margin:auto;background:white;padding:32px;border-radius:16px"><h2 style="color:#047857">Run On Console</h2><h1>'.$esc($heading).'</h1><p>'.$esc($text).'</p><p><a style="display:inline-block;padding:14px 22px;background:#047857;color:white;border-radius:8px" href="'.$esc($url).'">'.$esc($label).'</a></p><p>Button not working? '.$esc($url).'</p><p>If you did not request this, you can ignore this email.</p></main></body></html>';
}
function rocIssueChallenge(PDO $db, array $u, string $purpose='verify'): int {
    // Caller holds users row lock. Every resend invalidates earlier credentials.
    $db->prepare('UPDATE roc_auth_challenges SET consumed_at=NOW() WHERE user_id=? AND purpose=? AND consumed_at IS NULL')->execute([$u['id'],$purpose]);
    $raw=bin2hex(random_bytes(32)); $code=(string)random_int(100000,999999);
    $expiry=$purpose==='verify'?86400:3600;
    $q=$db->prepare('INSERT INTO roc_auth_challenges (user_id,purpose,code_hash,token_hash,code_expires_at,link_expires_at) VALUES (?,?,?,?,?,?)');
    $q->execute([$u['id'],$purpose,password_hash($code,PASSWORD_DEFAULT),hash('sha256',$raw),gmdate('Y-m-d H:i:s',time()+600),gmdate('Y-m-d H:i:s',time()+$expiry)]);
    $baseUrl=defined('ROC_SITE_URL')?rtrim(ROC_SITE_URL,'/'):'https://runonconsole.com';
    $url=$baseUrl.'/auth/'.($purpose==='verify'?'verify-email':'reset-password').'/?token='.rawurlencode($raw);
    $text=$purpose==='verify'?'Your verification code is '.$code.'. It expires in 10 minutes. This verification link expires in 24 hours.':'Choose a new password using the button below. This link expires in one hour.';
    return rocQueue($db,$u['email'],$purpose==='verify'?'Verify your Run On Console account':'Reset your Run On Console password',rocEmail($purpose==='verify'?'Verify your email':'Reset your password',$text,$url,$purpose==='verify'?'Verify my account':'Reset password'),10);
}
function rocWelcome(PDO $db, array $u): int {
    $baseUrl=defined('ROC_SITE_URL')?rtrim(ROC_SITE_URL,'/'):'https://runonconsole.com';
    return rocQueue($db,$u['email'],'Your Run On Console account is verified',rocEmail('Your account is verified','Thank you. Your account has been verified. You can now personalise your gaming profile. Sign in first if prompted.',$baseUrl.'/profile/','Open my profile'));
}
function rocDispatch(PDO $db, ?int $jobId=null): bool {
    $owner=bin2hex(random_bytes(16));
    $sql="UPDATE email_queue SET status='processing',locked_by=?,locked_at=NOW(),attempts=attempts+1 WHERE status='pending' AND attempts<5 AND (next_attempt_at IS NULL OR next_attempt_at<=NOW())";
    $args=[$owner]; if ($jobId!==null) { $sql.=' AND id=?'; $args[]=$jobId; }
    $q=$db->prepare($sql.' ORDER BY priority DESC,created_at ASC LIMIT 1'); $q->execute($args);
    if (!$q->rowCount()) return false;
    $q=$db->prepare('SELECT * FROM email_queue WHERE locked_by=?'); $q->execute([$owner]); $job=$q->fetch();
    $mail=null;
    try {
        require_once __DIR__.'/cron/PHPMailer/loader.php';
        $mail=new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP(); $mail->Host=SMTP_HOST; $mail->Port=SMTP_PORT;
        $mail->SMTPAuth=true; $mail->Username=SMTP_USERNAME; $mail->Password=SMTP_PASSWORD;
        $mail->SMTPSecure=SMTP_PORT===465?'ssl':'tls';
        // Never disable certificate or hostname verification.
        $mail->Timeout=5; $mail->Timelimit=8; $mail->SMTPDebug=0; $mail->CharSet='UTF-8';
        $mail->setFrom(MAIL_FROM_ADDRESS,MAIL_FROM_NAME); $mail->addReplyTo(MAIL_REPLY_TO,MAIL_FROM_NAME);
        $mail->addAddress($job['to_email']); $mail->Subject=$job['subject'];
        $mail->isHTML(true); $mail->Body=$job['html_body']; $mail->AltBody=html_entity_decode(strip_tags(str_replace(['</p>','</h1>','</h2>'],"\n",$job['html_body'])));
        $mail->send();
        $db->prepare("UPDATE email_queue SET status='sent',sent_at=NOW(),error_message=NULL,locked_by=NULL,locked_at=NULL WHERE id=? AND locked_by=?")->execute([$job['id'],$owner]);
        error_log('[ROC mail accepted] job='.(int)$job['id']);
    } catch (Throwable $e) {
        // Never log SMTP credentials, message bodies, verification codes or tokens.
        $delay=min(3600,60*(2**max(0,(int)$job['attempts']-1)));
        $db->prepare("UPDATE email_queue SET status=?,error_message=?,next_attempt_at=?,locked_by=NULL,locked_at=NULL WHERE id=? AND locked_by=?")
            ->execute([(int)$job['attempts']>=5?'failed':'pending','SMTP dispatch failed. Check private server diagnostics.',gmdate('Y-m-d H:i:s',time()+$delay),$job['id'],$owner]);
        error_log('[ROC mail deferred] job='.(int)$job['id'].' type='.get_class($e));
    } finally { if ($mail) $mail->smtpClose(); }
    return true;
}
function rocRespond(int $status, array $data, ?int $mailId=null): void {
    if (session_status()===PHP_SESSION_ACTIVE) session_write_close();
    if ($mailId!==null) {
        try { rocDispatch(getDBConnection(),$mailId); }
        catch (Throwable $e) { error_log('[ROC dispatch deferred] job='.$mailId); }
    }
    http_response_code($status); header('Cache-Control: no-store'); echo json_encode($data); exit;
}
