<?php
require __DIR__.'/includes/app.php';
require_once __DIR__.'/includes/update.php';

$openAfter='';
try{
    if($_SERVER['REQUEST_METHOD']==='POST'){
        $action=(string)($_POST['action']??'');
        $openMap=['theme'=>'theme','appearance'=>'theme','change_admin_password'=>'account','initialize_system'=>'data','check_system_update'=>'update','apply_system_update'=>'update'];
        $openAfter=$openMap[$action]??'';
        if(in_array($action,['theme','appearance','change_admin_password','initialize_system','check_system_update','apply_system_update'],true)) requireAdmin();
        if($action==='theme'){
            verifyCsrf();
            $preset=$_POST['preset']??'Custom';
            $maps=['RS8'=>['#D71920','#FFFFFF','#111111'],'Nanomoly'=>['#D4AF37','#111111','#FFD400'],'SRF'=>['#1769E0','#FFFFFF','#FFD400']];
            if(isset($maps[$preset]))$colors=$maps[$preset];
            else{
                $preset='Custom';$colors=[$_POST['primary_color']??'#D71920',$_POST['secondary_color']??'#FFFFFF',$_POST['accent_color']??'#111111'];
                foreach($colors as $c)if(!preg_match('/^#[0-9A-Fa-f]{6}$/',$c))throw new Exception('Invalid custom color.');
            }
            saveAdminPreferences($pdo,['theme_name'=>$preset,'primary_color'=>$colors[0],'secondary_color'=>$colors[1],'accent_color'=>$colors[2]]);
            flashSet('ok','Theme saved for '.adminUsername().' only.');go('settings.php?open=theme');
        }
        if($action==='appearance'){
            verifyCsrf();$mode=$_POST['appearance_mode']??'light';if(!in_array($mode,['light','dark'],true))$mode='light';
            saveAdminPreferences($pdo,['appearance_mode'=>$mode]);flashSet('ok',ucfirst($mode).' mode saved.');go('settings.php?open=theme');
        }
        if($action==='change_admin_password'){
            verifyCsrf();$target=(string)adminUsername();$current=(string)($_POST['current_password']??'');$pw=(string)($_POST['new_password']??'');$confirm=(string)($_POST['confirm_password']??'');
            if(!verifyAdminPassword($pdo,$target,$current))throw new Exception('Current password is incorrect.');
            if(strlen($pw)<8)throw new Exception('New password must be at least 8 characters.');
            if($pw!==$confirm)throw new Exception('Password confirmation does not match.');
            $uid=(int)($_SESSION['admin_user_id']??0);
            $pdo->prepare("UPDATE admin_users SET password_hash=?,updated_at=NOW() WHERE username=?")->execute([password_hash($pw,PASSWORD_DEFAULT),$target]);
            rs8ForgetAllAdminDevices($pdo,$uid);rs8RememberAdmin($pdo);
            flashSet('ok','Password updated. Other remembered device logins were revoked.');go('settings.php?open=account');
        }
        if($action==='initialize_system'){
            verifyCsrf();$phrase=trim((string)($_POST['confirm_phrase']??''));$password=(string)($_POST['current_password']??'');
            if($phrase!=='INITIALIZE RS8')throw new Exception('Type INITIALIZE RS8 exactly to confirm.');
            if(!verifyAdminPassword($pdo,(string)adminUsername(),$password))throw new Exception('Current admin password is incorrect.');
            initializeOperationalData($pdo);try{$pdo->exec("DELETE FROM schedule_requests");}catch(Throwable $e){}
            flashSet('ok','System initialized. Operational records were cleared; admins and settings were preserved.');go('settings.php?open=data');
        }
        if($action==='check_system_update'){
            verifyCsrf();$m=rs8FetchUpdateManifest();$_SESSION['rs8_update_manifest']=$m;
            if(rs8UpdateAvailable($m))flashSet('ok','Update v'.$m['version'].' is ready.');
            else flashSet('ok','RS8 Queue is up to date. Current version: v'.APP_VERSION.'.');
            go('settings.php?open=update');
        }
        if($action==='apply_system_update'){
            verifyCsrf();$m=rs8FetchUpdateManifest();
            if(!rs8UpdateAvailable($m)){flashSet('ok','RS8 Queue is already up to date.');go('settings.php?open=update');}
            $r=rs8ApplyUpdate($pdo,$m);unset($_SESSION['rs8_update_manifest']);
            flashSet('ok','Update installed successfully to v'.$r['version'].'. A backup was created automatically.');
            go('settings.php?updated=1&open=update');
        }
    }
}catch(Throwable $e){flashSet('err',$e->getMessage());go('settings.php'.($openAfter!==''?'?open='.rawurlencode($openAfter):''));}

$settings=settings($pdo);$activePage='settings';$pageTitle='Settings - RS8 Pickleball';$admin=isAdmin();
$updateManifest=($admin&&!empty($_SESSION['rs8_update_manifest'])&&is_array($_SESSION['rs8_update_manifest']))?$_SESSION['rs8_update_manifest']:null;
$lastUpdate=$admin?rs8LastUpdateInfo():null;
$versions=[
 ['1.3.22','September 2, 2026',['Settings redesigned into a compact menu with tap-to-open modal controls','Fixed the failed v1.3.21 app.php patch by shipping a clean v1.3.22 core file','Upcoming Calendar indicator is yellow','Past Open Play sessions are clickable with full match and player history','Admin login is remembered on the same device for up to 30 days']],
 ['1.3.20','September 1, 2026',['PH time UTC+8 enforced for match timestamps','Sports-360 style FIFO: finished players return to the end of the Queue','Resting removed from live rotation','On Deck remains editable until match start','Public court schedule requests with admin approval','Duplicate player-name protection']],
 ['1.3.18','September 1, 2026',['Active Match shows actual start time and a live running timer','Recent Results show actual date, start/completion time and match duration']],
 ['1.3.17','September 1, 2026',['Calendar events open Schedule Details first','Edit is a separate explicit action']],
 ['1.3.16','September 1, 2026',['Built-in System Update added under Settings','Updates are checksum-verified and backed up automatically','Recent Results winner highlighting']],
 ['1.3.15','September 1, 2026',['Manual Active Match','Manual On Deck','Shuffle On Deck']],
 ['1.3.14','September 1, 2026',['Admin Management','Create, reset, deactivate/reactivate and manage admin accounts']],
 ['1.3.13','September 1, 2026',['Admin color theme and Light/Dark preference stored per account']],
 ['1.3.12','September 1, 2026',['Queue-first live layout','Registered arrivals one-tap Add to Queue','Partial On Deck support']],
 ['1.3.10','September 1, 2026',['504 timeout stabilization','Removed runtime schema migrations from normal requests']],
 ['1.3.9','September 1, 2026',['Home and Queue share one upcoming-schedule source']],
 ['1.3.8','September 1, 2026',['Public refresh/service-worker stability hotfix']],
 ['1.3.7','September 1, 2026',['Public PWA install suggestion','Public Court Status','Controlled-random Match IQ']],
 ['1.3.6','September 1, 2026',['Declared DUPR optional; blank = NR']],
 ['1.3.5','September 1, 2026',['RS8 red pickleball PWA and navigation identity']],
 ['1.3.4','September 1, 2026',['Generic Admin login UI','Declared DUPR uses exactly 3 decimals']],
 ['1.3.3','September 1, 2026',['Admin authentication and role-based access','Initialize System','Past schedule deletion']],
 ['1.3.2','September 1, 2026',['Home Dashboard resilience','Dynamic PHP cache fix']],
 ['1.3.1','September 1, 2026',['Fast roster picker without reload or scroll jump']],
 ['1.3.0','September 1, 2026',['Home Dashboard','5-tab admin navigation','Court Rental','Court Calendar','Themes and Version History']],
 ['1.2.0','September 1, 2026',['Separate operational pages','Roster attendance and Restart Schedule']],
 ['1.1.0','September 1, 2026',['Scheduled Games','Native date/time pickers','Required score before End Match']],
 ['1.0.0','September 1, 2026',['Intelligent doubles queue','Partner/opponent variety','Private RS8 performance index']],
];
require __DIR__.'/includes/header.php';
?>
<div class="page-title"><h1>Settings</h1><p>Choose what you want to manage. Controls stay hidden until you open them.</p></div>

<div class="settings-hub">
<?php if($admin):?>
<button type="button" class="settings-tile" data-settings-open="theme"><span class="settings-tile-icon">T</span><span class="settings-tile-copy"><b>Theme & Appearance</b><small><?=esc($settings['theme_name'])?> • <?=ucfirst(esc($settings['appearance_mode']??'light'))?></small></span><span class="settings-chevron">›</span></button>
<button type="button" class="settings-tile" data-settings-open="account"><span class="settings-tile-icon">A</span><span class="settings-tile-copy"><b>Admin Account</b><small>@<?=esc(adminUsername()??'')?> • Signed in</small></span><span class="settings-chevron">›</span></button>
<button type="button" class="settings-tile" data-settings-open="update"><span class="settings-tile-icon">U</span><span class="settings-tile-copy"><b>System Update</b><small>Current v<?=APP_VERSION?><?=$updateManifest&&rs8UpdateAvailable($updateManifest)?' • Update ready':''?></small></span><span class="settings-chevron">›</span></button>
<button type="button" class="settings-tile" data-settings-open="history"><span class="settings-tile-icon">V</span><span class="settings-tile-copy"><b>Version History</b><small>Changelog & previous releases</small></span><span class="settings-chevron">›</span></button>
<button type="button" class="settings-tile" data-settings-open="device"><span class="settings-tile-icon">D</span><span class="settings-tile-copy"><b>App & Device</b><small>Install app & status colors</small></span><span class="settings-chevron">›</span></button>
<button type="button" class="settings-tile danger-tile" data-settings-open="data"><span class="settings-tile-icon">!</span><span class="settings-tile-copy"><b>System Data</b><small>Initialize / clear operational records</small></span><span class="settings-chevron">›</span></button>
<?php else:?>
<button type="button" class="settings-tile" data-settings-open="theme"><span class="settings-tile-icon">T</span><span class="settings-tile-copy"><b>Theme & Appearance</b><small>Light / Dark on this device</small></span><span class="settings-chevron">›</span></button>
<button type="button" class="settings-tile" data-settings-open="device"><span class="settings-tile-icon">D</span><span class="settings-tile-copy"><b>App & Device</b><small>Install RS8 Queue</small></span><span class="settings-chevron">›</span></button>
<button type="button" class="settings-tile" data-settings-open="access"><span class="settings-tile-icon">A</span><span class="settings-tile-copy"><b>Admin Access</b><small>Management login</small></span><span class="settings-chevron">›</span></button>
<button type="button" class="settings-tile" data-settings-open="history"><span class="settings-tile-icon">V</span><span class="settings-tile-copy"><b>Version History</b><small>Current v<?=APP_VERSION?></small></span><span class="settings-chevron">›</span></button>
<?php endif;?>
</div>

<dialog class="settings-modal" id="settings-theme"><div class="settings-sheet"><div class="settings-sheet-head"><div><div class="eyebrow">PERSONALIZATION</div><h2>Theme & Appearance</h2></div><button type="button" class="settings-close" data-settings-close>×</button></div><div class="settings-sheet-body">
<?php if($admin):?>
<div class="settings-subsection"><h3>Appearance</h3><form method="post" class="appearance-switch"><?=csrfField()?><input type="hidden" name="action" value="appearance"><button name="appearance_mode" value="light" class="appearance-option <?=($settings['appearance_mode']??'light')==='light'?'selected':''?>"><span>☀</span><b>Light</b></button><button name="appearance_mode" value="dark" class="appearance-option <?=($settings['appearance_mode']??'light')==='dark'?'selected':''?>"><span>◐</span><b>Dark</b></button></form></div>
<div class="settings-subsection"><h3>Color Theme</h3><div class="theme-preview"><div class="theme-chip" style="background:<?=esc($settings['primary_color'])?>"></div><div class="theme-chip" style="background:<?=esc($settings['secondary_color'])?>"></div><div class="theme-chip" style="background:<?=esc($settings['accent_color'])?>"></div></div><form method="post" class="form"><?=csrfField()?><input type="hidden" name="action" value="theme"><label>Preset</label><select name="preset"><option <?=$settings['theme_name']==='RS8'?'selected':''?>>RS8</option><option <?=$settings['theme_name']==='Nanomoly'?'selected':''?>>Nanomoly</option><option <?=$settings['theme_name']==='SRF'?'selected':''?>>SRF</option><option <?=$settings['theme_name']==='Custom'?'selected':''?>>Custom</option></select><div class="three-colors"><label>Primary<input type="color" name="primary_color" value="<?=esc($settings['primary_color'])?>"></label><label>Secondary<input type="color" name="secondary_color" value="<?=esc($settings['secondary_color'])?>"></label><label>Accent<input type="color" name="accent_color" value="<?=esc($settings['accent_color'])?>"></label></div><div class="hint">Saved only to your admin account.</div><button class="btn">APPLY THEME</button></form></div>
<?php else:?>
<div class="settings-subsection"><h3>This Device</h3><div class="appearance-switch"><button type="button" class="appearance-option" data-local-appearance="light"><span>☀</span><b>Light</b></button><button type="button" class="appearance-option" data-local-appearance="dark"><span>◐</span><b>Dark</b></button></div><div class="hint">Changes only this browser/device.</div></div>
<?php endif;?>
</div></div></dialog>

<?php if($admin):?>
<dialog class="settings-modal" id="settings-account"><div class="settings-sheet"><div class="settings-sheet-head"><div><div class="eyebrow">SECURITY</div><h2>Admin Account</h2></div><button type="button" class="settings-close" data-settings-close>×</button></div><div class="settings-sheet-body">
<div class="account-summary"><div class="avatar"><?=initials((string)adminUsername())?></div><div><b>@<?=esc(adminUsername()??'')?></b><div class="meta">Management access active • remembered on this device up to 30 days</div></div></div>
<a class="btn ghost" href="admins.php">MANAGE ADMIN ACCOUNTS</a>
<div class="settings-subsection"><h3>Change My Password</h3><form method="post" class="form"><?=csrfField()?><input type="hidden" name="action" value="change_admin_password"><label>Current Password</label><input name="current_password" type="password" autocomplete="current-password" required><label>New Password</label><input name="new_password" type="password" minlength="8" autocomplete="new-password" required><label>Confirm Password</label><input name="confirm_password" type="password" minlength="8" autocomplete="new-password" required><button class="btn ghost">UPDATE PASSWORD</button></form></div>
<a class="btn danger" href="logout.php">LOG OUT ON THIS DEVICE</a>
</div></div></dialog>

<dialog class="settings-modal" id="settings-update"><div class="settings-sheet"><div class="settings-sheet-head"><div><div class="eyebrow">SOFTWARE</div><h2>System Update</h2></div><button type="button" class="settings-close" data-settings-close>×</button></div><div class="settings-sheet-body"><div class="update-status-row"><div><b>RS8 Queue v<?=APP_VERSION?></b><div class="meta">Updates are checksum-verified and backed up before replacement.</div></div><span class="pill <?=($updateManifest&&rs8UpdateAvailable($updateManifest))?'live':''?>"><?=($updateManifest&&rs8UpdateAvailable($updateManifest))?'UPDATE READY':'CURRENT'?></span></div>
<?php if($updateManifest):?><div class="update-release"><b>Latest: v<?=esc((string)$updateManifest['version'])?></b><div class="meta"><?=esc((string)$updateManifest['published_at'])?></div><?php if(rs8UpdateAvailable($updateManifest)):?><ul><?php foreach($updateManifest['changelog'] as $change):?><li><?=esc((string)$change)?></li><?php endforeach;?></ul><?php else:?><div class="hint">You are already on the latest published version.</div><?php endif;?></div><?php endif;?>
<?php if($lastUpdate):?><div class="hint">Last update: <?=esc((string)($lastUpdate['from_version']??'?'))?> → <?=esc((string)($lastUpdate['to_version']??'?'))?></div><?php endif;?>
<div class="update-actions"><form method="post"><?=csrfField()?><input type="hidden" name="action" value="check_system_update"><button class="btn ghost">CHECK FOR UPDATE</button></form><?php if($updateManifest&&rs8UpdateAvailable($updateManifest)):?><form method="post"><?=csrfField()?><input type="hidden" name="action" value="apply_system_update"><button class="btn" data-confirm="Install RS8 Queue v<?=esc((string)$updateManifest['version'])?> now? A backup will be created first.">UPDATE NOW</button></form><?php endif;?></div></div></div></dialog>

<dialog class="settings-modal" id="settings-data"><div class="settings-sheet"><div class="settings-sheet-head"><div><div class="eyebrow danger-text">DESTRUCTIVE</div><h2>System Data</h2></div><button type="button" class="settings-close" data-settings-close>×</button></div><div class="settings-sheet-body"><div class="danger-zone"><b>Initialize RS8 Queue</b><p>Deletes players, schedules, rentals, public requests, sessions, attendance, matches, scores and private insights. Admin accounts and personal themes stay intact.</p><form method="post" class="form"><?=csrfField()?><input type="hidden" name="action" value="initialize_system"><label>Type INITIALIZE RS8</label><input name="confirm_phrase" autocomplete="off" required placeholder="INITIALIZE RS8"><label>Your Current Admin Password</label><input name="current_password" type="password" autocomplete="current-password" required><button class="btn danger" data-confirm="Permanently erase all operational RS8 Pickleball records?">INITIALIZE & CLEAR RECORDS</button></form></div></div></div></dialog>
<?php else:?>
<dialog class="settings-modal" id="settings-access"><div class="settings-sheet"><div class="settings-sheet-head"><div><div class="eyebrow">MANAGEMENT</div><h2>Admin Access</h2></div><button type="button" class="settings-close" data-settings-close>×</button></div><div class="settings-sheet-body"><p class="setting-copy">Schedules, Calendar, Players, scoring and private Insights require admin access.</p><?php if(adminsInitialized($pdo)):?><a class="btn" href="login.php">ADMIN LOGIN</a><?php else:?><a class="btn" href="setup.php">FIRST-TIME ADMIN SETUP</a><?php endif;?></div></div></dialog>
<?php endif;?>

<dialog class="settings-modal" id="settings-device"><div class="settings-sheet"><div class="settings-sheet-head"><div><div class="eyebrow">THIS DEVICE</div><h2>App & Device</h2></div><button type="button" class="settings-close" data-settings-close>×</button></div><div class="settings-sheet-body"><div class="install-card compact-install"><img src="assets/icons/brand-128.png?v=1.3.22" alt="RS8 Queue"><div class="install-card-copy"><b>RS8 Queue on your Home Screen</b><div class="meta">Install for faster access.</div></div><button type="button" class="mini primary" data-install-manual>INSTALL</button></div><div class="settings-subsection"><h3>Match Status Colors</h3><div class="card match-card active status-preview"><b>ACTIVE GAME</b><div class="meta">Red</div></div><div class="card match-card ondeck status-preview"><b>ON DECK</b><div class="meta">Yellow</div></div></div></div></div></dialog>

<dialog class="settings-modal history-modal" id="settings-history"><div class="settings-sheet"><div class="settings-sheet-head"><div><div class="eyebrow">CHANGELOG</div><h2>Version History</h2></div><button type="button" class="settings-close" data-settings-close>×</button></div><div class="settings-sheet-body"><div class="version-list"><?php foreach($versions as $i=>$v):?><details class="card version-card" <?=$i===0?'open':''?>><summary><div><b>Version <?=$v[0]?></b><span><?=$v[1]?></span></div><span class="pill"><?=$i===0?'CURRENT':'HISTORY'?></span></summary><ul><?php foreach($v[2] as $c):?><li><?=esc($c)?></li><?php endforeach;?></ul></details><?php endforeach;?></div></div></div></dialog>

<script>
(function(){
  const dialogs=[...document.querySelectorAll('.settings-modal')];
  const openDialog=(key)=>{const d=document.getElementById('settings-'+key);if(d&&typeof d.showModal==='function'&&!d.open)d.showModal();};
  document.querySelectorAll('[data-settings-open]').forEach(btn=>btn.addEventListener('click',()=>openDialog(btn.dataset.settingsOpen)));
  document.querySelectorAll('[data-settings-close]').forEach(btn=>btn.addEventListener('click',()=>btn.closest('dialog')?.close()));
  dialogs.forEach(d=>d.addEventListener('click',e=>{if(e.target===d)d.close();}));
  const requested=new URLSearchParams(location.search).get('open');if(requested)window.setTimeout(()=>openDialog(requested),0);
})();
</script>
<?php require __DIR__.'/includes/footer.php';
