<?php
// One-time v1.3.20 finalizer. The System Updater injects this include into app.php,
// then this script patches the v1.3.18 install atomically, migrates queue state,
// removes its own include, and redirects back to Settings.
if(defined('RS8_V1320_FINALIZER_RUNNING')) return;
define('RS8_V1320_FINALIZER_RUNNING',true);
$root=dirname(__DIR__);
$bootstrapLine="require_once __DIR__.'/v1320_finalize.php';\n";
$helperLine="require_once __DIR__.'/v1320.php';\n";
$patchFiles=[__DIR__.'/v1320_patch_queue1.php',__DIR__.'/v1320_patch_queue2.php',__DIR__.'/v1320_patch_pages.php',__DIR__.'/v1320_patch_misc.php'];
$backupRoot=$root.'/_update_backups';
if(!is_dir($backupRoot)) @mkdir($backupRoot,0755,true);
$backup=$backupRoot.'/v1320-finalize-'.date('Ymd-His').'-'.bin2hex(random_bytes(2));
@mkdir($backup,0755,true);
@file_put_contents($backup.'/.htaccess',"Options -Indexes\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n");
$backed=[];
function v1320f_safe(string $rel): bool {return $rel!==''&&!str_starts_with($rel,'/')&&!str_contains($rel,'..')&&!str_contains($rel,"\0");}
function v1320f_backup(string $root,string $backup,string $rel,array &$backed): void {
    if(isset($backed[$rel]))return;if(!v1320f_safe($rel))throw new RuntimeException('Unsafe patch path: '.$rel);$src=$root.'/'.$rel;if(!is_file($src))throw new RuntimeException('Patch target missing: '.$rel);$dst=$backup.'/'.$rel;if(!is_dir(dirname($dst)))@mkdir(dirname($dst),0755,true);if(!@copy($src,$dst))throw new RuntimeException('Could not back up '.$rel);$backed[$rel]=true;
}
function v1320f_write(string $path,string $data): void {$tmp=$path.'.v1320new';if(@file_put_contents($tmp,$data,LOCK_EX)===false)throw new RuntimeException('Could not stage '.basename($path));@chmod($tmp,0644);if(!@rename($tmp,$path)){@unlink($tmp);throw new RuntimeException('Could not install '.basename($path));}}
try{
    date_default_timezone_set('Asia/Manila');
    try{$pdo->exec("SET SESSION time_zone = '+08:00'");}catch(Throwable $e){}
    $patches=[];foreach($patchFiles as $pf){if(!is_file($pf))throw new RuntimeException('Missing update component: '.basename($pf));$part=require $pf;if(!is_array($part))throw new RuntimeException('Invalid patch component: '.basename($pf));$patches=array_merge($patches,$part);}
    // Validate every exact patch first. Do not touch production files if the v1.3.18 base is unexpected.
    foreach($patches as $op){$rel=(string)($op['path']??'');if(!v1320f_safe($rel))throw new RuntimeException('Unsafe patch path: '.$rel);$path=$root.'/'.$rel;if(!is_file($path))throw new RuntimeException('Patch target missing: '.$rel);$cur=(string)file_get_contents($path);$type=(string)($op['type']??'');if($type==='replace_text'){$count=substr_count($cur,(string)$op['search']);$expected=(int)($op['expected']??1);if($count!==$expected){if(str_contains($cur,(string)$op['replace']))continue;throw new RuntimeException('Patch validation failed for '.$rel.' (expected '.$expected.', found '.$count.').');}}elseif($type==='append_text'){if(($op['marker']??'')!==''&&str_contains($cur,(string)$op['marker']))continue;}else throw new RuntimeException('Unsupported finalizer operation: '.$type);}
    // app.php itself is finalized last, but back it up now too.
    v1320f_backup($root,$backup,'includes/app.php',$backed);
    foreach($patches as $op){$rel=(string)$op['path'];$path=$root.'/'.$rel;$cur=(string)file_get_contents($path);$type=(string)$op['type'];if($type==='replace_text'){if(str_contains($cur,(string)$op['replace'])&&!str_contains($cur,(string)$op['search']))continue;v1320f_backup($root,$backup,$rel,$backed);$cur=str_replace((string)$op['search'],(string)$op['replace'],$cur);v1320f_write($path,$cur);}else{$marker=(string)($op['marker']??'');if($marker!==''&&str_contains($cur,$marker))continue;v1320f_backup($root,$backup,$rel,$backed);if($cur!==''&&!str_ends_with($cur,"\n"))$cur.="\n";$cur.=(string)$op['text'];v1320f_write($path,$cur);}}
    // Queue migration: legacy Resting players go to the END, then Resting disappears from live behavior.
    $pdo->exec("UPDATE session_players SET queue_credit=id");
    $pdo->exec("UPDATE session_players SET queue_credit=queue_credit+1000000000 WHERE status='Resting'");
    $pdo->exec("UPDATE session_players SET status='Waiting' WHERE status='Resting'");
    $pdo->exec("CREATE TABLE IF NOT EXISTS schedule_requests (id INT AUTO_INCREMENT PRIMARY KEY, requester_name VARCHAR(120) NOT NULL, contact_number VARCHAR(80) NOT NULL, schedule_type VARCHAR(20) NOT NULL DEFAULT 'court_rental', request_name VARCHAR(160) NULL, requested_date DATE NOT NULL, requested_time TIME NOT NULL, requested_end_time TIME NOT NULL, notes TEXT NULL, status VARCHAR(20) NOT NULL DEFAULT 'pending', reviewed_by_admin_id INT NULL, reviewed_at DATETIME NULL, approved_session_id INT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL, KEY idx_schedule_requests_status(status,requested_date,requested_time), KEY idx_schedule_requests_date(requested_date)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Finalize app.php only after all file and DB changes succeeded.
    $appPath=$root.'/includes/app.php';$app=(string)file_get_contents($appPath);
    if(substr_count($app,$bootstrapLine)!==1)throw new RuntimeException('Finalizer hook was not found exactly once.');
    $tzOld="date_default_timezone_set(\$config['timezone'] ?? 'Asia/Manila');";$verOld="define('APP_VERSION','1.3.18')";
    if(substr_count($app,$tzOld)!==1)throw new RuntimeException('App timezone base did not match v1.3.18.');
    if(substr_count($app,$verOld)!==1)throw new RuntimeException('App version base did not match v1.3.18.');
    $app=str_replace($bootstrapLine,$helperLine,$app);
    $app=str_replace($tzOld,"// RS8 court operations use Philippine Standard Time regardless of hosting server location.\ndate_default_timezone_set('Asia/Manila');",$app);
    $app=str_replace($verOld,"define('APP_VERSION','1.3.20')",$app);
    v1320f_write($appPath,$app);
    @file_put_contents($backup.'/finalized.json',json_encode(['version'=>'1.3.20','completed_at'=>date(DATE_ATOM),'backup'=>basename($backup)],JSON_PRETTY_PRINT));
    // Redirect before app.php continues with its already-parsed old constant. Next request loads v1.3.20 cleanly.
    header('Location: settings.php?updated=1&finalized=1320#system-update',true,302);exit;
}catch(Throwable $e){
    foreach(array_keys($backed) as $rel){$src=$backup.'/'.$rel;$dst=$root.'/'.$rel;if(is_file($src))@copy($src,$dst);}
    // Never leave a broken finalizer loop. Restore v1.3.18 app entrypoint without this temporary include.
    $appPath=$root.'/includes/app.php';if(is_file($appPath)){$app=(string)@file_get_contents($appPath);$app=str_replace($bootstrapLine,'',$app);@file_put_contents($appPath,$app,LOCK_EX);}
    error_log('RS8 v1.3.20 finalizer: '.$e->getMessage());
    http_response_code(500);echo '<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1"><style>body{font-family:system-ui;padding:24px;background:#111;color:#fff}.box{max-width:620px;margin:auto;background:#1b1b1b;padding:22px;border-radius:18px}a{color:#fff}</style><div class="box"><h2>RS8 Update Finalization Stopped</h2><p>No production file changes were kept. The site was restored to the previous version.</p><p><b>'.htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8').'</b></p><p><a href="settings.php#system-update">Return to Settings</a></p></div>';exit;
}
