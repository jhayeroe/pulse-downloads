<?php
if (!defined('RS8_REMEMBER_COOKIE')) define('RS8_REMEMBER_COOKIE','rs8_queue_admin');
if (!defined('RS8_REMEMBER_DAYS')) define('RS8_REMEMBER_DAYS',30);

function v1321CookiePath(): string {
    $script=(string)($_SERVER['SCRIPT_NAME']??'/');
    $dir=str_replace('\\','/',dirname($script));
    if($dir==='.'||$dir==='\\'||$dir==='')$dir='/';
    if($dir!=='/'&&!str_ends_with($dir,'/'))$dir.='/';
    return $dir;
}
function v1321CookieOptions(int $expires): array {
    return ['expires'=>$expires,'path'=>v1321CookiePath(),'secure'=>(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'),'httponly'=>true,'samesite'=>'Lax'];
}
function v1321ClearRememberCookie(): void {
    setcookie(RS8_REMEMBER_COOKIE,'',v1321CookieOptions(time()-3600));
    unset($_COOKIE[RS8_REMEMBER_COOKIE]);
}
function v1321RememberAdmin(PDO $pdo): void {
    $uid=(int)($_SESSION['admin_user_id']??0);if(!$uid)return;
    $selector=bin2hex(random_bytes(12));$validator=bin2hex(random_bytes(32));$hash=hash('sha256',$validator);$expires=time()+RS8_REMEMBER_DAYS*86400;$expiresSql=date('Y-m-d H:i:s',$expires);
    try{
        $pdo->prepare("DELETE FROM admin_remember_tokens WHERE admin_user_id=? OR expires_at<NOW()")->execute([$uid]);
        $pdo->prepare("INSERT INTO admin_remember_tokens(selector,admin_user_id,token_hash,expires_at,created_at,last_used_at) VALUES(?,?,?,?,NOW(),NOW())")->execute([$selector,$uid,$hash,$expiresSql]);
        setcookie(RS8_REMEMBER_COOKIE,$selector.':'.$validator,v1321CookieOptions($expires));
        $_COOKIE[RS8_REMEMBER_COOKIE]=$selector.':'.$validator;
    }catch(Throwable $e){error_log('RS8 remember admin: '.$e->getMessage());}
}
function v1321RestoreAdmin(PDO $pdo): void {
    if(isAdmin())return;
    $raw=(string)($_COOKIE[RS8_REMEMBER_COOKIE]??'');if($raw===''||!str_contains($raw,':'))return;
    [$selector,$validator]=explode(':',$raw,2);
    if(!preg_match('/^[a-f0-9]{24}$/',$selector)||!preg_match('/^[a-f0-9]{64}$/',$validator)){v1321ClearRememberCookie();return;}
    try{
        $q=$pdo->prepare("SELECT t.*,u.username,u.is_active FROM admin_remember_tokens t JOIN admin_users u ON u.id=t.admin_user_id WHERE t.selector=? AND t.expires_at>NOW() LIMIT 1");$q->execute([$selector]);$row=$q->fetch();
        if(!$row||!(int)$row['is_active']||!hash_equals((string)$row['token_hash'],hash('sha256',$validator))){if($row)$pdo->prepare("DELETE FROM admin_remember_tokens WHERE selector=?")->execute([$selector]);v1321ClearRememberCookie();return;}
        session_regenerate_id(true);$_SESSION['admin_user_id']=(int)$row['admin_user_id'];$_SESSION['admin_username']=(string)$row['username'];
        $pdo->prepare("UPDATE admin_remember_tokens SET last_used_at=NOW() WHERE selector=?")->execute([$selector]);
    }catch(Throwable $e){error_log('RS8 restore admin: '.$e->getMessage());}
}
function v1321ForgetAdmin(PDO $pdo): void {
    $raw=(string)($_COOKIE[RS8_REMEMBER_COOKIE]??'');
    if($raw!==''&&str_contains($raw,':')){$selector=explode(':',$raw,2)[0];if(preg_match('/^[a-f0-9]{24}$/',$selector)){try{$pdo->prepare("DELETE FROM admin_remember_tokens WHERE selector=?")->execute([$selector]);}catch(Throwable $e){}}}
    v1321ClearRememberCookie();
}
