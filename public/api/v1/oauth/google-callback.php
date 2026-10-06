<?php
require_once dirname(__DIR__).'/account-runtime.php';
require_once __DIR__.'/google-token.php';
$reference='req_'.bin2hex(random_bytes(6)); $stage='state'; $db=null;
function rocOAuthError(string $code,string $reference): void {
    header('Location: /auth/login/?error='.rawurlencode($code).'&request_id='.$reference); exit;
}
$state=$_SESSION['oauth_google_state']??''; $nonce=$_SESSION['oauth_google_nonce']??'';
$started=$_SESSION['oauth_google_started']??0; $linkUser=$_SESSION['oauth_link_user']??null;
unset($_SESSION['oauth_google_state'],$_SESSION['oauth_google_nonce'],$_SESSION['oauth_google_started'],$_SESSION['oauth_link_user']);
if (!$state || !$nonce || time()-$started>600 || !hash_equals($state,(string)($_GET['state']??''))) rocOAuthError('oauth_state_invalid',$reference);
if (!empty($_GET['error'])) rocOAuthError('oauth_access_denied',$reference);
if (empty($_GET['code'])) rocOAuthError('oauth_state_invalid',$reference);
try {
    $stage='token_exchange';
    $tokens=rocGoogleHttp('https://oauth2.googleapis.com/token',['code'=>$_GET['code'],'client_id'=>GOOGLE_CLIENT_ID,
        'client_secret'=>GOOGLE_CLIENT_SECRET,'redirect_uri'=>GOOGLE_REDIRECT_URI,'grant_type'=>'authorization_code']);
    $stage='id_token';
    $claims=rocGoogleClaims($tokens['id_token']??'',$nonce,GOOGLE_CLIENT_ID,rocGoogleHttp('https://www.googleapis.com/oauth2/v1/certs'));
    $db=getDBConnection(); if (!$db) throw new RuntimeException('Database unavailable');
    $stage='database'; $email=strtolower($claims['email']); $sub=$claims['sub'];
    if ($linkUser && (!validateDatabaseSession() || (int)$_SESSION['user_id']!==(int)$linkUser || time()-($_SESSION['authenticated_at']??0)>600))
        rocOAuthError('oauth_reauthentication_required',$reference);
    $db->beginTransaction();
    $q=$db->prepare("SELECT user_id FROM oauth_accounts WHERE provider='google' AND provider_user_id=? FOR UPDATE"); $q->execute([$sub]); $oauth=$q->fetch();
    $job=null;
    if ($oauth) {
        $id=(int)$oauth['user_id'];
        if ($linkUser && $id!==(int)$linkUser) { $db->rollBack(); rocOAuthError('oauth_already_linked',$reference); }
        $u=rocUserById($db,$id);
    } else {
        $q=$db->prepare('SELECT * FROM users WHERE LOWER(email)=? FOR UPDATE'); $q->execute([$email]); $u=$q->fetch();
        if ($u && (!$linkUser || (int)$u['id']!==(int)$linkUser || !$u['email_verified'])) {
            $db->rollBack(); rocOAuthError('oauth_link_required',$reference);
        }
        if ($linkUser && !$u) { $db->rollBack(); rocOAuthError('oauth_email_mismatch',$reference); }
        if ($u && $u['status']==='suspended') throw new RuntimeException('Suspended account');
        if (!$u) {
            $username='gamer_'.bin2hex(random_bytes(6)); $name=substr($claims['name']??'Gamer',0,120);
            $q=$db->prepare("INSERT INTO users (uuid,name,username,email,password_hash,email_verified,is_verified,is_admin,status) VALUES (?,?,?,?,'',1,1,0,'active')");
            $q->execute(['usr_'.bin2hex(random_bytes(16)),$name,$username,$email]); $id=(int)$db->lastInsertId();
            $db->prepare("INSERT INTO user_profiles (user_id,country,bio,avatar_icon) VALUES (?,'','','avatar_01')")->execute([$id]);
            $u=rocUserById($db,$id); $job=rocWelcome($db,$u);
        }
        $id=(int)$u['id'];
        $db->prepare("INSERT INTO oauth_accounts (user_id,provider,provider_user_id,provider_email) VALUES (?,'google',?,?)")->execute([$id,$sub,$email]);
    }
    // Google verifies the linked identity, never merge unrelated email accounts.
    $db->prepare("UPDATE users SET email_verified=1,is_verified=1,status='active' WHERE id=?")->execute([$id]);
    rocEstablishSession($db,$id); $db->commit();
    session_write_close();
    // Welcome email remains queued; do not delay profile navigation for SMTP.
    header('Cache-Control: no-store'); header('Location: /profile/'); exit;
} catch (Throwable $e) {
    if ($db && $db->inTransaction()) $db->rollBack();
    error_log('[ROC OAuth '.$reference.'] stage='.$stage.' type='.get_class($e).' code='.$e->getCode());
    rocOAuthError($stage==='token_exchange'?'oauth_token_exchange_failed':($stage==='id_token'?'oauth_id_token_invalid':'oauth_server_error'),$reference);
}
