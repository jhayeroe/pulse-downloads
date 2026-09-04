<?php
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');header('Pragma: no-cache');
require __DIR__.'/db.php';require __DIR__.'/functions.php';require_once __DIR__.'/v1320.php';require_once __DIR__.'/remember_admin.php';require_once __DIR__.'/v1323.php';require_once __DIR__.'/booking_config.php';require_once __DIR__.'/v1323_runtime.php';
date_default_timezone_set('Asia/Manila');try{$pdo->exec("SET SESSION time_zone = '+08:00'");}catch(Throwable $e){}
rs8RestoreRememberedAdmin($pdo);
if(isAdmin()){try{$q=$pdo->prepare("SELECT username,is_active FROM admin_users WHERE id=? LIMIT 1");$q->execute([(int)$_SESSION['admin_user_id']]);$a=$q->fetch();if(!$a||!(int)$a['is_active']||(string)$a['username']!==(string)$_SESSION['admin_username']){rs8ForgetAdmin($pdo);logoutAdmin();flashSet('err','Your admin access is no longer active.');go('login.php');}}catch(Throwable $e){error_log('RS8 admin session check: '.$e->getMessage());}}
if(!defined('APP_VERSION')) define('APP_VERSION','1.6.0');$settings=settings($pdo);v1323QueueRuntime($pdo);
