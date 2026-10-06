<?php
function rocGamingProfile(PDO $db,int $userId): array {
    $q=$db->prepare('SELECT city,date_of_birth,avatar_data,favorite_games,gaming_ids,social_links FROM roc_gaming_profiles WHERE user_id=?'); $q->execute([$userId]); $p=$q->fetch()?:[];
    return ['city'=>$p['city']??'','dateOfBirth'=>$p['date_of_birth']??'', 'avatarData'=>$p['avatar_data']??'',
        'favoriteGames'=>json_decode($p['favorite_games']??'[]',true)?:[],
        'gamingIds'=>json_decode($p['gaming_ids']??'{}',true)?:new stdClass(),
        'socialLinks'=>json_decode($p['social_links']??'{}',true)?:new stdClass()];
}
function rocCleanAvatar(string $data): string {
    if ($data==='') return '';
    if (strlen($data)>450000 || !preg_match('#^data:image/(png|jpeg|webp);base64,([A-Za-z0-9+/=]+)$#D',$data,$m)) throw new InvalidArgumentException('Choose a PNG, JPEG or WebP picture under 300 KB.');
    $bytes=base64_decode($m[2],true); $info=$bytes===false?false:getimagesizefromstring($bytes);
    if (!$info || $info[0]>1024 || $info[1]>1024 || $info[0]<1 || $info[1]<1) throw new InvalidArgumentException('Picture dimensions must be between 1 and 1024 pixels.');
    if (!function_exists('imagecreatefromstring')) throw new InvalidArgumentException('Photo processing is unavailable. Choose a built-in avatar instead.');
    $im=imagecreatefromstring($bytes); if (!$im) throw new InvalidArgumentException('Invalid picture.');
    ob_start(); imagepng($im); $clean=ob_get_clean(); imagedestroy($im);
    if (strlen($clean)>300000) throw new InvalidArgumentException('Picture is too large. Try a smaller photo.');
    return 'data:image/png;base64,'.base64_encode($clean);
}
function rocSaveGaming(PDO $db,int $id,array $in): void {
    $name=trim((string)($in['name']??'')); $bio=trim((string)($in['bio']??''));
    $city=trim((string)($in['city']??'')); $country=trim((string)($in['country']??''));
    $dob=(string)($in['dateOfBirth']??''); $icon=(string)($in['avatarIcon']??'gamepad');
    if (!$name || strlen($name)>120 || strlen($bio)>2000 || strlen($city)>100 || strlen($country)>50) throw new InvalidArgumentException('Check your name, bio, city and country lengths.');
    if ($dob!=='') {
        $date=DateTimeImmutable::createFromFormat('!Y-m-d',$dob);
        if (!$date || $date->format('Y-m-d')!==$dob || $dob>gmdate('Y-m-d') || $dob<'1900-01-01') throw new InvalidArgumentException('Enter a valid date of birth.');
    }
    if (!in_array($icon,['gamepad','shield','rocket','swords'],true)) throw new InvalidArgumentException('Choose a supported avatar.');
    $games=$in['favoriteGames']??[]; $ids=$in['gamingIds']??[]; $links=$in['socialLinks']??[];
    if (!is_array($games)||!is_array($ids)||!is_array($links)||count($games)>20||count($ids)>12||count($links)>12) throw new InvalidArgumentException('Too many profile entries.');
    foreach($games as $v) if (!is_string($v)||strlen($v)>80) throw new InvalidArgumentException('Invalid game name.');
    foreach($ids as $k=>$v) if (!is_string($v)||strlen((string)$k)>30||strlen($v)>100) throw new InvalidArgumentException('Invalid gaming ID.');
    foreach($links as $k=>$v) {
        if (!is_string($v)||strlen((string)$k)>30||strlen($v)>300) throw new InvalidArgumentException('Invalid social link.');
        if ($v!=='' && (!filter_var($v,FILTER_VALIDATE_URL)||strtolower(parse_url($v,PHP_URL_SCHEME)??'')!=='https'||parse_url($v,PHP_URL_USER)||parse_url($v,PHP_URL_PASS))) throw new InvalidArgumentException('Social links must be full HTTPS URLs without credentials.');
    }
    $avatar=rocCleanAvatar((string)($in['avatarData']??''));
    $db->beginTransaction();
    try {
        $db->prepare('UPDATE users SET name=? WHERE id=?')->execute([$name,$id]);
        $db->prepare('INSERT INTO user_profiles (user_id,country,bio,avatar_icon) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE country=VALUES(country),bio=VALUES(bio),avatar_icon=VALUES(avatar_icon)')->execute([$id,$country,$bio,$icon]);
        $db->prepare('INSERT INTO roc_gaming_profiles (user_id,city,date_of_birth,avatar_data,favorite_games,gaming_ids,social_links) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE city=VALUES(city),date_of_birth=VALUES(date_of_birth),avatar_data=VALUES(avatar_data),favorite_games=VALUES(favorite_games),gaming_ids=VALUES(gaming_ids),social_links=VALUES(social_links)')
            ->execute([$id,$city,$dob?:null,$avatar,json_encode(array_values($games)),json_encode($ids),json_encode($links)]);
        $db->commit();
    } catch(Throwable $e) { if($db->inTransaction()) $db->rollBack(); throw $e; }
}
