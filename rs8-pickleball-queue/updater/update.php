<?php
// RS8 self-update engine. It does not contact the update server during normal
// page loads. Network access happens only after an admin taps Check/Update.

if (!defined('RS8_UPDATE_MANIFEST_URL')) {
    define('RS8_UPDATE_MANIFEST_URL', 'https://raw.githubusercontent.com/jhayeroe/pulse-downloads/main/rs8-pickleball-queue/manifest.json');
}

function rs8HttpGet(string $url, int $timeout = 20): string {
    $separator = str_contains($url, '?') ? '&' : '?';
    $url .= $separator . '_rs8cb=' . rawurlencode((string)time());
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => min(8, $timeout),
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_USERAGENT => 'RS8-Pickleball-Queue/' . (defined('APP_VERSION') ? APP_VERSION : 'unknown'),
            CURLOPT_HTTPHEADER => ['Cache-Control: no-cache', 'Pragma: no-cache'],
        ]);
        $body = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($body === false || $code < 200 || $code >= 300) {
            throw new RuntimeException('Update server could not be reached' . ($err ? ': ' . $err : ' (HTTP ' . $code . ')') . '.');
        }
        return (string)$body;
    }
    $ctx = stream_context_create(['http' => [
        'timeout' => $timeout, 'follow_location' => 1,
        'header' => "User-Agent: RS8-Pickleball-Queue\r\nCache-Control: no-cache\r\nPragma: no-cache\r\n",
    ]]);
    $body = @file_get_contents($url, false, $ctx);
    if ($body === false) throw new RuntimeException('Update server could not be reached from this hosting account.');
    return (string)$body;
}

function rs8FetchUpdateManifest(): array {
    $m = json_decode(rs8HttpGet(RS8_UPDATE_MANIFEST_URL, 15), true);
    if (!is_array($m)) throw new RuntimeException('Update manifest is invalid.');
    foreach (['version','package_url','sha256','published_at','changelog'] as $key) if (!array_key_exists($key, $m)) throw new RuntimeException('Update manifest is missing ' . $key . '.');
    if (!preg_match('/^\d+\.\d+\.\d+$/', (string)$m['version'])) throw new RuntimeException('Update version is invalid.');
    if (!preg_match('/^[a-f0-9]{64}$/i', (string)$m['sha256'])) throw new RuntimeException('Update checksum is invalid.');
    if (!filter_var((string)$m['package_url'], FILTER_VALIDATE_URL)) throw new RuntimeException('Update package URL is invalid.');
    if (!is_array($m['changelog'])) $m['changelog'] = [(string)$m['changelog']];
    return $m;
}

function rs8UpdateAvailable(array $manifest): bool { return version_compare((string)$manifest['version'], APP_VERSION, '>'); }

function rs8SafeUpdatePath(string $path): bool {
    $path = str_replace('\\', '/', $path);
    if ($path === '' || str_contains($path, "\0") || str_starts_with($path, '/') || preg_match('#(^|/)\.\.?(/|$)#', $path)) return false;
    $lower = strtolower($path);
    if (in_array($lower, ['config.php','.env','.user.ini'], true)) return false;
    foreach (['uploads/','_update_backups/','_update_tmp/'] as $prefix) if (str_starts_with($lower, $prefix)) return false;
    return true;
}

function rs8ProtectUpdateFolder(string $dir): void {
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $rules = "Options -Indexes\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n";
    @file_put_contents($dir . '/.htaccess', $rules);
    @file_put_contents($dir . '/index.php', "<?php http_response_code(404); exit;\n");
}

function rs8DecodeUpdatePackage(string $bytes, string $expectedVersion): array {
    $bundle = json_decode($bytes, true);
    if (!is_array($bundle) || ($bundle['format'] ?? '') !== 'rs8-update-v1') throw new RuntimeException('Update package format is invalid.');
    if ((string)($bundle['version'] ?? '') !== $expectedVersion) throw new RuntimeException('Update package version does not match the manifest.');
    $hasFiles=!empty($bundle['files'])&&is_array($bundle['files']);
    $hasOps=!empty($bundle['operations'])&&is_array($bundle['operations']);
    if(!$hasFiles&&!$hasOps) throw new RuntimeException('Update package has no changes.');
    return $bundle;
}

function rs8ApplyUpdate(PDO $pdo, array $manifest): array {
    if (!rs8UpdateAvailable($manifest)) return ['updated'=>false,'version'=>APP_VERSION,'message'=>'Already up to date.'];
    $root = realpath(__DIR__ . '/..');
    if (!$root || !is_dir($root) || !is_writable($root)) throw new RuntimeException('The app folder is not writable by PHP.');

    $backupRoot = $root . '/_update_backups'; $tmpRoot = $root . '/_update_tmp';
    rs8ProtectUpdateFolder($backupRoot); rs8ProtectUpdateFolder($tmpRoot);
    $token = date('Ymd-His') . '-' . bin2hex(random_bytes(3));
    $backup = $backupRoot . '/' . $token . '-from-v' . APP_VERSION;
    @mkdir($backup,0755,true); rs8ProtectUpdateFolder($backup);

    $backed=[]; $newFiles=[]; $touched=[];
    $backupOnce=function(string $rel) use (&$backed,&$newFiles,&$touched,$root,$backup): string {
        if(!rs8SafeUpdatePath($rel)) throw new RuntimeException('Unsafe update path was blocked: '.$rel);
        $dest=$root.'/'.$rel;
        if(isset($touched[$rel])) return $dest;
        $touched[$rel]=true;
        if(is_file($dest)){
            $bp=$backup.'/'.$rel; $bpd=dirname($bp);
            if(!is_dir($bpd)&&!@mkdir($bpd,0755,true)&&!is_dir($bpd)) throw new RuntimeException('Could not create backup folder.');
            if(!@copy($dest,$bp)) throw new RuntimeException('Could not back up '.$rel.'.');
            $backed[]=$rel;
        } else $newFiles[]=$rel;
        return $dest;
    };
    $writeAtomic=function(string $dest,string $data,string $rel): void {
        $parent=dirname($dest); if(!is_dir($parent)&&!@mkdir($parent,0755,true)&&!is_dir($parent)) throw new RuntimeException('Could not create folder for '.$rel.'.');
        $temp=$dest.'.rs8new'; if(@file_put_contents($temp,$data,LOCK_EX)===false) throw new RuntimeException('Could not stage '.$rel.'.');
        @chmod($temp,0644); if(!@rename($temp,$dest)){@unlink($temp);throw new RuntimeException('Could not install '.$rel.'.');}
    };

    try {
        $bytes=rs8HttpGet((string)$manifest['package_url'],45);
        if(!hash_equals(strtolower((string)$manifest['sha256']),strtolower(hash('sha256',$bytes)))) throw new RuntimeException('Update checksum did not match. Update was cancelled.');
        $bundle=rs8DecodeUpdatePackage($bytes,(string)$manifest['version']);
        $installed=0;

        foreach(($bundle['files']??[]) as $file){
            if(!is_array($file)) throw new RuntimeException('Update file entry is invalid.');
            $rel=str_replace('\\','/',(string)($file['path']??'')); $data=base64_decode((string)($file['data']??''),true);
            if($data===false) throw new RuntimeException('Update data is invalid for '.$rel.'.');
            if(isset($file['sha256'])&&!hash_equals(strtolower((string)$file['sha256']),strtolower(hash('sha256',$data)))) throw new RuntimeException('File checksum failed for '.$rel.'.');
            $dest=$backupOnce($rel); $writeAtomic($dest,$data,$rel); $installed++;
        }

        foreach(($bundle['operations']??[]) as $op){
            if(!is_array($op)) throw new RuntimeException('Update operation is invalid.');
            $type=(string)($op['type']??''); $rel=str_replace('\\','/',(string)($op['path']??''));
            if($type==='write_file'){
                $data=base64_decode((string)($op['data']??''),true); if($data===false) throw new RuntimeException('Invalid file data for '.$rel.'.');
                $dest=$backupOnce($rel); $writeAtomic($dest,$data,$rel); $installed++; continue;
            }
            if($type==='download_file'){
                $url=(string)($op['url']??''); if(!filter_var($url,FILTER_VALIDATE_URL)) throw new RuntimeException('Invalid download URL for '.$rel.'.');
                $data=rs8HttpGet($url,30);
                if(isset($op['sha256'])&&!hash_equals(strtolower((string)$op['sha256']),strtolower(hash('sha256',$data)))) throw new RuntimeException('Downloaded file checksum failed for '.$rel.'.');
                $dest=$backupOnce($rel); $writeAtomic($dest,$data,$rel); $installed++; continue;
            }
            if($type==='replace_text'){
                $dest=$backupOnce($rel); if(!is_file($dest)) throw new RuntimeException('Patch target does not exist: '.$rel);
                $current=(string)file_get_contents($dest); $search=(string)($op['search']??''); $replace=(string)($op['replace']??'');
                if($search==='') throw new RuntimeException('Empty patch search for '.$rel.'.');
                $count=substr_count($current,$search); $expected=(int)($op['expected']??1);
                if($count!==$expected){ if(str_contains($current,$replace)) continue; throw new RuntimeException('Patch check failed for '.$rel.' (expected '.$expected.', found '.$count.').'); }
                $current=str_replace($search,$replace,$current); $writeAtomic($dest,$current,$rel); $installed++; continue;
            }
            if($type==='append_text'){
                $dest=$backupOnce($rel); if(!is_file($dest)) throw new RuntimeException('Append target does not exist: '.$rel);
                $current=(string)file_get_contents($dest); $marker=(string)($op['marker']??''); $text=(string)($op['text']??'');
                if($marker!==''&&str_contains($current,$marker)) continue;
                $current.=($current!==''&&!str_ends_with($current,"\n")?"\n":"").$text; $writeAtomic($dest,$current,$rel); $installed++; continue;
            }
            if($type==='delete_file'){
                $dest=$backupOnce($rel); if(is_file($dest)&&!@unlink($dest)) throw new RuntimeException('Could not remove '.$rel.'.'); $installed++; continue;
            }
            throw new RuntimeException('Unsupported update operation: '.$type);
        }

        foreach(($bundle['sql']??[]) as $sql){$sql=trim((string)$sql);if($sql!=='')$pdo->exec($sql);}
        $log=['updated_at'=>date(DATE_ATOM),'from_version'=>APP_VERSION,'to_version'=>(string)$manifest['version'],'backup_folder'=>basename($backup),'changes_applied'=>$installed];
        @file_put_contents($backupRoot.'/last_update.json',json_encode($log,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
        return ['updated'=>true,'version'=>(string)$manifest['version'],'backup'=>basename($backup),'files'=>$installed];
    } catch(Throwable $e){
        foreach(array_reverse($backed) as $rel){$src=$backup.'/'.$rel;$dest=$root.'/'.$rel;if(is_file($src))@copy($src,$dest);} foreach($newFiles as $rel)@unlink($root.'/'.$rel); throw $e;
    }
}

function rs8LastUpdateInfo(): ?array {
    $root = realpath(__DIR__ . '/..'); if(!$root) return null;
    $file=$root.'/_update_backups/last_update.json'; if(!is_file($file)) return null;
    $j=json_decode((string)@file_get_contents($file),true); return is_array($j)?$j:null;
}
