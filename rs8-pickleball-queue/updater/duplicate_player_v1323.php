<?php
require __DIR__.'/includes/app.php';requireAdmin();
$data=$_SESSION['queue_duplicate_candidate']??null;if(!$data||!is_array($data)){flashSet('err','Duplicate draft expired.');go('queue.php');}
$matches=v1323FindDuplicatePlayers($pdo,(string)$data['name'],(string)($data['nickname']??''));
try{if($_SERVER['REQUEST_METHOD']==='POST'){
    $action=(string)($_POST['action']??'');$session=currentSession($pdo);if(!$session)throw new Exception('No active session.');
    if($action==='use_existing'){$pid=(int)($_POST['player_id']??0);$ok=false;foreach($matches as $m)if((int)$m['id']===$pid)$ok=true;if(!$ok)throw new Exception('Existing player not found.');unset($_SESSION['queue_duplicate_candidate']);v1320CheckInPlayer($pdo,(int)$session['id'],$pid);flashSet('ok','Existing player added to Queue.');go('queue.php');}
    if($action==='change_nickname'){$nickname=trim((string)($_POST['nickname']??''));if($nickname==='')throw new Exception('Enter a unique nickname.');if(v1323NicknameExists($pdo,$nickname))throw new Exception('That nickname is already used. Change it again.');$pid=v1320CreatePlayer($pdo,(string)$data['name'],$nickname,(string)$data['gender'],'NR',(string)$data['dupr_rating'],true);unset($_SESSION['queue_duplicate_candidate']);v1320CheckInPlayer($pdo,(int)$session['id'],$pid);flashSet('ok','Player created with the new unique nickname.');go('queue.php');}
    if($action==='discard'){unset($_SESSION['queue_duplicate_candidate']);flashSet('ok','New-player draft discarded.');go('queue.php');}
}}catch(Throwable $e){flashSet('err',$e->getMessage());go('duplicate_player.php');}
$activePage='queue';$pageTitle='Duplicate Player - RS8 Pickleball';require __DIR__.'/includes/header.php';?>
<div class="page-title"><h1>Duplicate Player Found</h1><p><?=esc((string)$data['name'])?> matches an existing full name or nickname.</p></div>
<section class="section"><div class="card duplicate-player-card"><div class="notice warning"><b>Do not create another duplicate profile.</b><br>Use the existing player, change the nickname, or discard.</div>
<?php foreach($matches as $m):?><div class="player-row"><div class="avatar"><?=initials(playerName($m))?></div><div class="player-info"><b><?=esc($m['name'])?><?=trim((string)$m['nickname'])!==''?' • '.esc($m['nickname']):''?></b><div class="meta"><?=esc($m['skill_level'])?> • DUPR <?=esc(declaredDuprLabel($m))?></div></div><form method="post"><input type="hidden" name="action" value="use_existing"><input type="hidden" name="player_id" value="<?=$m['id']?>"><button class="mini success">USE EXISTING</button></form></div><?php endforeach;?>
<form method="post" class="form"><input type="hidden" name="action" value="change_nickname"><label>Change Nickname</label><input name="nickname" required placeholder="Enter a unique nickname"><button class="btn warning">CHANGE NICKNAME & CREATE</button></form>
<form method="post"><input type="hidden" name="action" value="discard"><button class="btn ghost">DISCARD</button></form></div></section><?php require __DIR__.'/includes/footer.php';
