<?php
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
require __DIR__.'/db.php';
require __DIR__.'/functions.php';
require_once __DIR__.'/v1320.php';
require_once __DIR__.'/remember_admin.php';

// RS8 court operations always use Philippine Standard Time, independent of hosting location.
date_default_timezone_set('Asia/Manila');
try{$pdo->exec("SET SESSION time_zone = '+08:00'");}catch(Throwable $e){}

// Restore a valid 30-day same-device admin login before checking access state.
rs8RestoreRememberedAdmin($pdo);

if(isAdmin()){
    try{
        $q=$pdo->prepare("SELECT username,is_active FROM admin_users WHERE id=? LIMIT 1");
        $q->execute([(int)$_SESSION['admin_user_id']]);
        $sessionAdmin=$q->fetch();
        if(!$sessionAdmin || !(int)$sessionAdmin['is_active'] || (string)$sessionAdmin['username']!==(string)$_SESSION['admin_username']){
            rs8ForgetAdmin($pdo);
            logoutAdmin();
            flashSet('err','Your admin access is no longer active.');
            go('login.php');
        }
    }catch(Throwable $e){
        error_log('RS8 admin session check: '.$e->getMessage());
    }
}
if(!defined('APP_VERSION')) define('APP_VERSION','1.3.22');

/*
 * IMPORTANT PERFORMANCE RULE
 * --------------------------
 * Do not run ensureSchema() on normal page requests. Database schema changes
 * belong to the System Update path only so public/admin page loads stay fast.
 */
$settings=settings($pdo);
