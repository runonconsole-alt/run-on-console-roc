<?php
require_once dirname(__DIR__).'/account-runtime.php';
if (!GOOGLE_CLIENT_ID || !GOOGLE_CLIENT_SECRET) { header('Location: /auth/login/?error=oauth_unconfigured'); exit; }
$_SESSION['oauth_google_state']=bin2hex(random_bytes(32));
$_SESSION['oauth_google_nonce']=bin2hex(random_bytes(32));
$_SESSION['oauth_google_started']=time();
$_SESSION['oauth_return_url']='/profile/';
// A linking intent can only be set by an authenticated, CSRF-protected POST.
$args=['client_id'=>GOOGLE_CLIENT_ID,'redirect_uri'=>GOOGLE_REDIRECT_URI,'response_type'=>'code',
    'scope'=>'openid email profile','state'=>$_SESSION['oauth_google_state'],'nonce'=>$_SESSION['oauth_google_nonce'],'prompt'=>'select_account'];
session_write_close();
header('Cache-Control: no-store');
header('Location: https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query($args)); exit;
