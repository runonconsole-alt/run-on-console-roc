<?php
require_once __DIR__.'/account-runtime.php';
$requestId='req_'.bin2hex(random_bytes(6));
$db=getDBConnection();
if (!$db) handleDatabaseUnavailable();
$action=$_GET['action']??'session';
$input=json_decode(file_get_contents('php://input'),true);
if (!is_array($input)) $input=$_POST;
try {
    if ($action==='session' || $action==='') {
        $u=validateDatabaseSession()?rocUserById($db,(int)$_SESSION['user_id']):null;
        rocRespond(200,['success'=>true,'authenticated'=>(bool)$u,'user'=>$u?rocUser($u):null,'csrf_token'=>getCsrfToken(),
            'config'=>['smtp_configured'=>!empty(SMTP_HOST)&&!empty(SMTP_PASSWORD),
                'google_oauth_configured'=>!empty(GOOGLE_CLIENT_ID)&&!empty(GOOGLE_CLIENT_SECRET),
                'captcha_configured'=>!empty(CAPTCHA_SITE_KEY)&&!empty(CAPTCHA_SECRET_KEY),
                'captcha_site_key'=>CAPTCHA_SITE_KEY,'captcha_provider'=>CAPTCHA_PROVIDER]]);
    }
    if (($_SERVER['REQUEST_METHOD']??'GET')!=='POST') rocRespond(405,['success'=>false,'error'=>'POST required.']);
    if (!validateCsrfToken($_SERVER['HTTP_X_CSRF_TOKEN']??$input['csrf_token']??''))
        rocRespond(403,['success'=>false,'error'=>'Security session expired. Refresh the page and try again.']);
    $email=strtolower(trim((string)($input['email']??'')));
    if ($action==='google-link') {
        if (!validateDatabaseSession() || time()-($_SESSION['authenticated_at']??0)>600)
            rocRespond(401,['success'=>false,'error'=>'Sign in again before linking Google.']);
        $u=rocUserById($db,(int)$_SESSION['user_id']);
        if (!$u['email_verified']) rocRespond(403,['success'=>false,'error'=>'Verify your email first.']);
        $_SESSION['oauth_link_user']=(int)$u['id'];
        rocRespond(200,['success'=>true,'redirect'=>'/api/v1/oauth/google-start.php']);
    }
    if ($action==='signup') {
        $name=trim((string)($input['name']??'')); $username=trim((string)($input['username']??''));
        $password=(string)($input['password']??'');
        if (!$name || strlen($name)>120 || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>150 || !preg_match('/^[a-zA-Z0-9_]{3,30}$/D',$username))
            rocRespond(422,['success'=>false,'error'=>'Enter a valid name, email and username.']);
        if (strlen($password)<8 || strlen($password)>72 || $password!==($input['confirmPassword']??''))
            rocRespond(422,['success'=>false,'error'=>'Passwords must match and contain 8–72 bytes.']);
        if (empty($input['agreeTerms'])) rocRespond(422,['success'=>false,'error'=>'Accept the terms to continue.']);
        if (rocLimit($db,'signup',$email,5,900)) rocRespond(429,['success'=>false,'error'=>'Too many attempts. Try again later.']);
        if (!CAPTCHA_SITE_KEY || !CAPTCHA_SECRET_KEY || !verifyCaptcha($input['captchaToken']??'',$_SERVER['REMOTE_ADDR']??'','signup'))
            rocRespond(422,['success'=>false,'error'=>'Security verification failed or expired. Please complete it again.']);
        $valid=validateUsername($username);
        if (!$valid['valid']) rocRespond(409,['success'=>false,'error'=>$valid['error']]);
        $q=$db->prepare('SELECT id FROM users WHERE LOWER(email)=? OR LOWER(username)=LOWER(?)'); $q->execute([$email,$username]);
        if ($q->fetch()) rocRespond(409,['success'=>false,'error'=>'Email or username already registered. Sign in or recover your account.']);
        $db->beginTransaction();
        $q=$db->prepare("INSERT INTO users (uuid,name,username,email,password_hash,email_verified,is_verified,is_admin,status) VALUES (?,?,?,?,?,0,0,0,'pending_verification')");
        $q->execute(['usr_'.bin2hex(random_bytes(16)),$name,$username,$email,hashPasswordSecure($password)]);
        $id=(int)$db->lastInsertId();
        $db->prepare("INSERT INTO user_profiles (user_id,country,bio,avatar_icon) VALUES (?,'','','avatar_01')")->execute([$id]);
        $job=rocIssueChallenge($db,rocUserById($db,$id));
        $db->commit(); $_SESSION['pending_verification_user']=$id;
        rocRespond(201,['success'=>true,'message'=>'Account created. Check your email for your code or verification link.'],$job);
    }
    if ($action==='login') {
        $identifier=trim((string)($input['identifier']??$input['email']??''));
        if (rocLimit($db,'login',strtolower($identifier),10,900)) rocRespond(429,['success'=>false,'error'=>'Too many sign-in attempts. Try again later.']);
        $q=$db->prepare(str_contains($identifier,'@')?'SELECT * FROM users WHERE LOWER(email)=LOWER(?) LIMIT 1':'SELECT * FROM users WHERE LOWER(username)=LOWER(?) LIMIT 1');
        $q->execute([$identifier]); $u=$q->fetch();
        if (!$u || !password_verify((string)($input['password']??''),$u['password_hash']) || $u['status']==='suspended')
            rocRespond(401,['success'=>false,'error'=>'Invalid username/email or password.']);
        if (!$u['email_verified']) rocRespond(403,['success'=>false,'error'=>'VERIFICATION_REQUIRED','message'=>'Verify your email to continue.','email'=>$u['email']]);
        rocEstablishSession($db,(int)$u['id']);
        rocRespond(200,['success'=>true,'user'=>rocUser($u, $db),'csrf_token'=>getCsrfToken(),'redirect'=>'/profile/']);
    }
    if ($action==='logout') {
        if (!empty($_SESSION['db_session_id'])) $db->prepare('DELETE FROM sessions WHERE id=? AND user_id=?')->execute([$_SESSION['db_session_id'],$_SESSION['user_id']??0]);
        $_SESSION=[]; session_regenerate_id(true);
        rocRespond(200,['success'=>true,'csrf_token'=>getCsrfToken()]);
    }
    if (in_array($action,['resend-verification','forgot-password'],true)) {
        if (!filter_var($email,FILTER_VALIDATE_EMAIL)) rocRespond(422,['success'=>false,'error'=>'Enter a valid email address.']);
        if (rocLimit($db,$action,$email,1,60)) rocRespond(429,['success'=>false,'error'=>'Please wait 60 seconds before requesting another email.']);
        if (rocLimit($db,$action.'-hour',$email,10,3600)) rocRespond(429,['success'=>false,'error'=>'Too many requests. Try again later.']);
        $db->beginTransaction();
        $q=$db->prepare('SELECT * FROM users WHERE LOWER(email)=? FOR UPDATE'); $q->execute([$email]); $u=$q->fetch();
        $job=null;
        if ($u && $u['status']!=='suspended' && ($action==='forgot-password'||!$u['email_verified']))
            $job=rocIssueChallenge($db,$u,$action==='forgot-password'?'reset':'verify');
        $db->commit();
        rocRespond(200,['success'=>true,'message'=>'If your account is eligible, an email will arrive shortly. Only the newest code or link works.'],$job);
    }
    if (in_array($action,['verify-email','verify-email-code','reset-password'],true)) {
        $purpose=$action==='reset-password'?'reset':'verify';
        $token=(string)($input['token']??''); $code=(string)($input['code']??'');
        if (rocLimit($db,'challenge',$email?:hash('sha256',$token),10,900)) rocRespond(429,['success'=>false,'error'=>'Too many verification attempts. Try again later.']);
        if (!$token && (!$email || !preg_match('/^[0-9]{6}$/D',$code))) rocRespond(422,['success'=>false,'error'=>'Enter your email and six-digit code.']);
        if ($token && !preg_match('/^[a-f0-9]{64}$/D',$token)) rocRespond(400,['success'=>false,'error'=>'Invalid or expired link.']);
        // Consistent lock order with resend: user, then challenge. Never match codes globally.
        $q=$db->prepare($token?'SELECT user_id AS id FROM roc_auth_challenges WHERE token_hash=? AND purpose=?':'SELECT id FROM users WHERE LOWER(email)=?');
        $q->execute($token?[hash('sha256',$token),$purpose]:[$email]); $owner=$q->fetch();
        if (!$owner) rocRespond(400,['success'=>false,'error'=>'Invalid or expired verification credentials.']);
        $db->beginTransaction();
        $q=$db->prepare('SELECT * FROM users WHERE id=? FOR UPDATE'); $q->execute([$owner['id']]); $u=$q->fetch();
        $q=$db->prepare('SELECT *, (code_expires_at>NOW()) AS code_valid,(link_expires_at>NOW()) AS link_valid FROM roc_auth_challenges WHERE user_id=? AND purpose=? ORDER BY id DESC LIMIT 1 FOR UPDATE');
        $q->execute([$owner['id'],$purpose]); $c=$q->fetch();
        $valid=$u && $u['status']!=='suspended' && $c && !$c['consumed_at'] && (int)$c['attempts']<5;
        $valid=$valid && ($token ? ($c['link_valid'] && hash_equals($c['token_hash'],hash('sha256',$token))) : ($purpose==='verify' && $c['code_valid'] && password_verify($code,$c['code_hash'])));
        if (!$valid) {
            if ($c && !$c['consumed_at']) $db->prepare('UPDATE roc_auth_challenges SET attempts=attempts+1 WHERE id=?')->execute([$c['id']]);
            $db->commit(); rocRespond(400,['success'=>false,'error'=>'Invalid, expired or already used code/link. Request a new one if needed.']);
        }
        $job=null;
        if ($purpose==='reset') {
            $password=(string)($input['password']??'');
            if (strlen($password)<8 || strlen($password)>72 || $password!==($input['confirmPassword']??'')) {
                $db->rollBack(); rocRespond(422,['success'=>false,'error'=>'Passwords must match and contain 8–72 bytes.']);
            }
            $db->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([hashPasswordSecure($password),$u['id']]);
            $db->prepare('DELETE FROM sessions WHERE user_id=?')->execute([$u['id']]);
        } else {
            $db->prepare("UPDATE users SET email_verified=1,is_verified=1,status='active' WHERE id=?")->execute([$u['id']]);
            if (!$u['email_verified']) $job=rocWelcome($db,$u);
        }
        $db->prepare('UPDATE roc_auth_challenges SET consumed_at=NOW() WHERE user_id=? AND purpose=? AND consumed_at IS NULL')->execute([$u['id'],$purpose]);
        if ($purpose==='verify') rocEstablishSession($db,(int)$u['id']);
        $db->commit();
        rocRespond(200,['success'=>true,'message'=>$purpose==='verify'?'Account verified. Opening your profile.':'Password reset. Sign in with your new password.',
            'redirect'=>$purpose==='verify'?'/profile/':'/auth/login/','csrf_token'=>getCsrfToken()]);
    }
    rocRespond(404,['success'=>false,'error'=>'Unknown action requested.']);
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('[ROC auth '.$requestId.'] '.get_class($e).' code='.$e->getCode().' line='.$e->getLine());
    rocRespond(500,['success'=>false,'error'=>'Unable to complete your request. Please contact support with the reference ID.','request_id'=>$requestId]);
}
