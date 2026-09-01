<?php
requireAdmin();
$session=currentSession($pdo);if(!$session){flashSet('err','No active Queue session.');go('queue.php');}
$mode=($_GET['mode']??$_POST['mode']??'ondeck')==='active'?'active':'ondeck';
$active=$ondeck=null;foreach(matchRows($pdo,(int)$session['id']) as $m){if($m['status']==='active'&&!$active)$active=$m;elseif($m['status']==='ondeck'&&!$ondeck)$ondeck=$m;}
if($mode==='active'&&!$active){flashSet('err','No active match to edit.');go('queue.php');}
try{
    if($_SERVER['REQUEST_METHOD']==='POST'){
        verifyCsrf();$ids=[(int)($_POST['a1']??0),(int)($_POST['a2']??0),(int)($_POST['b1']??0),(int)($_POST['b2']??0)];
        if(in_array(0,$ids,true)||count(array_unique($ids))!==4)throw new Exception('Choose four different players.');
        if($mode==='active'){v1320SaveManualActive($pdo,(int)$session['id'],(int)$active['id'],$ids);flashSet('ok','Active matchup updated manually.');}
        else{v1320SaveManualOnDeck($pdo,(int)$session['id'],$ids);flashSet('ok','On Deck updated. It stays editable until the match starts.');}
        go('queue.php');
    }
}catch(Throwable $e){flashSet('err',$e->getMessage());}
$rows=v1320PlayerRows($pdo,(int)$session['id']);$activeIds=$active?array_map('intval',[$active['a1'],$active['a2'],$active['b1'],$active['b2']]):[];$deckIds=$ondeck?array_map('intval',[$ondeck['a1'],$ondeck['a2'],$ondeck['b1'],$ondeck['b2']]):[];
if($mode==='ondeck'){$eligible=array_values(array_filter($rows,fn($p)=>$p['status']==='Waiting'&&!in_array((int)$p['player_id'],$activeIds,true)));$selected=$ondeck?$deckIds:array_slice(array_map(fn($p)=>(int)$p['player_id'],$eligible),0,4);}
else{$eligible=array_values(array_filter($rows,fn($p)=>!in_array($p['status'],['Paused','Checked Out'],true)));$selected=$activeIds;}
while(count($selected)<4)$selected[]=0;
$activePage='queue';$pageTitle=($mode==='active'?'Manual Active Match':'Edit On Deck').' - RS8 Pickleball';require __DIR__.'/includes/header.php';
function manualOptionLabel(array $p): string {return playerName($p).' • '.($p['status']??'').' • DUPR '.declaredDuprLabel($p);}
?>
<div class="page-title"><h1><?=$mode==='active'?'Manual Active Match':'Edit On Deck'?></h1><p><?=$mode==='active'?'Choose the exact four players and teams currently playing.':'On Deck is editable until it becomes the Active Match. Choose any four Waiting players and exact teams.'?></p></div>
<section class="section"><div class="card manual-match-card"><div class="manual-warning"><?=$mode==='active'?'Manual override: players removed from the court return to the Queue. If you pull someone from On Deck, On Deck is rebuilt automatically.':'No lock: you may replace a player who leaves, change all four, or change partners before the game starts.'?></div>
<?php if(count($eligible)<4):?><div class="notice info"><b>Only <?=count($eligible)?> eligible player<?=count($eligible)===1?'':'s'?> available.</b> A doubles matchup needs four different checked-in players.</div><?php endif;?>
<form method="post" class="form manual-match-form"><?=csrfField()?><input type="hidden" name="mode" value="<?=esc($mode)?>">
<div class="manual-team team-a"><div class="manual-team-head"><b>TEAM A</b><span>2 PLAYERS</span></div><label>Player 1</label><select name="a1" required><option value="">Select player</option><?php foreach($eligible as $p):?><option value="<?=$p['player_id']?>" <?=((int)$selected[0]===(int)$p['player_id'])?'selected':''?>><?=esc(manualOptionLabel($p))?></option><?php endforeach;?></select><label>Player 2</label><select name="a2" required><option value="">Select player</option><?php foreach($eligible as $p):?><option value="<?=$p['player_id']?>" <?=((int)$selected[1]===(int)$p['player_id'])?'selected':''?>><?=esc(manualOptionLabel($p))?></option><?php endforeach;?></select></div>
<div class="manual-vs">VS</div>
<div class="manual-team team-b"><div class="manual-team-head"><b>TEAM B</b><span>2 PLAYERS</span></div><label>Player 1</label><select name="b1" required><option value="">Select player</option><?php foreach($eligible as $p):?><option value="<?=$p['player_id']?>" <?=((int)$selected[2]===(int)$p['player_id'])?'selected':''?>><?=esc(manualOptionLabel($p))?></option><?php endforeach;?></select><label>Player 2</label><select name="b2" required><option value="">Select player</option><?php foreach($eligible as $p):?><option value="<?=$p['player_id']?>" <?=((int)$selected[3]===(int)$p['player_id'])?'selected':''?>><?=esc(manualOptionLabel($p))?></option><?php endforeach;?></select></div>
<button class="btn" <?=count($eligible)<4?'disabled':''?>><?=$mode==='active'?'SAVE ACTIVE MATCHUP':'SAVE ON DECK'?></button><a class="btn ghost" href="queue.php">CANCEL / BACK TO QUEUE</a></form></div></section>
<script>(()=>{const sels=[...document.querySelectorAll('.manual-match-form select')];function update(){const vals=sels.map(s=>s.value).filter(Boolean);sels.forEach(s=>[...s.options].forEach(o=>{if(!o.value)return;o.disabled=vals.includes(o.value)&&s.value!==o.value;}));}sels.forEach(s=>s.addEventListener('change',update));update();})();</script>
<?php require __DIR__.'/includes/footer.php';
