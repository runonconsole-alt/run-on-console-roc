<?php
function rocGoogleHttp(string $url, ?array $post=null): array {
    $c=curl_init($url);
    curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,CURLOPT_CONNECTTIMEOUT=>5,
        CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2]);
    if ($post!==null) { curl_setopt($c,CURLOPT_POST,true); curl_setopt($c,CURLOPT_POSTFIELDS,http_build_query($post)); }
    $body=curl_exec($c); $status=curl_getinfo($c,CURLINFO_HTTP_CODE); curl_close($c);
    if ($status!==200 || !is_string($body)) throw new RuntimeException('Google HTTPS request failed');
    $data=json_decode($body,true,512,JSON_THROW_ON_ERROR);
    if (!is_array($data)) throw new RuntimeException('Invalid Google response');
    return $data;
}
function rocDecode64(string $s): string {
    $v=base64_decode(strtr($s,'-_','+/'),true);
    if ($v===false) throw new RuntimeException('Invalid token encoding'); return $v;
}
function rocGoogleClaims(string $jwt, string $nonce, string $clientId, array $certs): array {
    $parts=explode('.',$jwt); if (count($parts)!==3) throw new RuntimeException('Invalid ID token');
    $head=json_decode(rocDecode64($parts[0]),true,512,JSON_THROW_ON_ERROR);
    $cert=$certs[$head['kid']??'']??null;
    if (($head['alg']??'')!=='RS256' || !is_string($cert) || openssl_verify($parts[0].'.'.$parts[1],rocDecode64($parts[2]),$cert,OPENSSL_ALGO_SHA256)!==1)
        throw new RuntimeException('Invalid ID token signature');
    $claims=json_decode(rocDecode64($parts[1]),true,512,JSON_THROW_ON_ERROR);
    $aud=(array)($claims['aud']??[]);
    if (!in_array($clientId,$aud,true) || (isset($claims['azp']) && $claims['azp']!==$clientId) || (count($aud)>1 && ($claims['azp']??'')!==$clientId)
        || !in_array($claims['iss']??'',['https://accounts.google.com','accounts.google.com'],true)
        || ($claims['exp']??0)<=time() || ($claims['iat']??0)>time()+60
        || !$nonce || !hash_equals($nonce,(string)($claims['nonce']??'')) || empty($claims['sub'])
        || ($claims['email_verified']??false)!==true || !filter_var($claims['email']??'',FILTER_VALIDATE_EMAIL))
        throw new RuntimeException('Invalid ID token claims');
    return $claims;
}
