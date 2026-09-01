<?php
require __DIR__.'/includes/app.php';
requireAdmin();
$id=(int)($_GET['id']??0);
$session=$id?sessionById($pdo,$id):null;
if(!$session){flashSet('err','Schedule not found.');go('calendar.php');}
$rental=isCourtRental($session);
$state=scheduleDisplayState($session);
$label=scheduleDisplayLabel($session);
$roster=[];$played=[];$matches=[];
if(!$rental){
    try{$roster=rosterRows($pdo,$id);}catch(Throwable $e){$roster=[];}
    try{
        $q=$pdo->prepare("SELECT sp.*,p.name,p.nickname,p.gender,p.skill_level,p.dupr_rating,p.hidden_rating,p.photo_url FROM session_players sp JOIN players p ON p.id=sp.player_id WHERE sp.session_id=? ORDER BY sp.games_played DESC,sp.wins DESC,p.name ASC");
        $q->execute([$id]);$played=$q->fetchAll();
    }catch(Throwable $e){$played=[];}
    try{
        $matches=array_values(array_filter(matchRows($pdo,$id),fn($m)=>($m['status']??'')==='completed'));
        usort($matches,fn($a,$b)=>(int)$a['id']<=>(int)$b['id']);
    }catch(Throwable $e){$matches=[];}
}
$matchCount=count($matches);
$activePage='calendar';$pageTitle='Schedule Details - RS8 Pickleball';require __DIR__.'/includes/header.php';
?>
<div class="page-title"><h1><?=$state==='ended'&&!$rental?'Session History':'Schedule Details'?></h1><p><?=$state==='ended'&&!$rental?'Saved court history, players, scores and completed games.':'Review the booking first. Editing is a separate action so calendar taps never change a schedule by accident.'?></p></div>
<section class="section"><div class="card schedule-view-card <?=$rental?'rental-card':'openplay-card'?> calendar-<?=$state?>">
<div class="schedule-top"><div><div class="eyebrow"><?=esc(strtoupper(scheduleTypeLabel($session['schedule_type']??'open_play')))?></div><div class="schedule-name"><?=esc($session['name'])?></div><div class="schedule-date"><?=prettyDate($session['scheduled_date'])?></div><div class="meta"><?=prettyTime($session['scheduled_time'])?>–<?=prettyTime($session['scheduled_end_time'])?> • Court 1</div></div><span class="pill <?=$state==='ongoing'?'live':($state==='ended'?'ended-pill':'')?>"><?=esc($label)?></span></div>
<?php if($rental):?>
<div class="rental-summary" style="margin-top:14px"><div><span>RENTER</span><b><?=esc($session['renter_name']?:'—')?></b></div><div><span>CONTACT</span><b><?=esc($session['contact_number']?:'—')?></b></div><div><span>PAYMENT</span><b><?=esc(rentalPaymentStatus($session))?></b></div><div><span>BALANCE</span><b>₱<?=number_format(rentalBalance($session),2)?></b></div></div>
<div class="card compact" style="margin-top:12px"><div class="stat-row"><span>Rental Fee</span><b>₱<?=number_format((float)$session['rental_fee'],2)?></b></div><div class="stat-row"><span>Amount Paid</span><b>₱<?=number_format((float)$session['amount_paid'],2)?></b></div><?php if(trim((string)$session['rental_notes'])!==''):?><div class="stat-row"><span>Notes</span><b><?=esc($session['rental_notes'])?></b></div><?php endif;?></div>
<?php else:?>
<div class="stats-grid" style="margin-top:14px"><div><b><?=count($played)?></b><span>PLAYERS</span></div><div><b><?=$matchCount?></b><span>COMPLETED GAMES</span></div><div><b><?=esc($state==='ongoing'?'LIVE':strtoupper($state))?></b><span>STATUS</span></div></div>
<?php endif;?>
<div class="stack" style="margin-top:14px">
<?php if(($session['status']??'scheduled')==='active'):?><a class="btn success" href="queue.php">OPEN LIVE QUEUE</a>
<?php elseif(($session['status']??'scheduled')==='scheduled' && $state!=='ended'):?>
<a class="btn" href="schedule_edit.php?id=<?=$id?>">EDIT SCHEDULE</a>
<?php if(!$rental):?><a class="btn success" href="attendance.php?id=<?=$id?>">CONFIRM ATTENDANCE & START</a><?php endif;?>
<?php endif;?>
<a class="btn ghost" href="schedules.php">BACK TO SCHEDULES</a>
<a class="btn ghost" href="calendar.php?month=<?=esc(substr((string)$session['scheduled_date'],0,7))?>">VIEW IN CALENDAR</a>
</div></div></section>

<?php if(!$rental && $state==='ended'):?>
<section class="section"><div class="section-head"><h2>COMPLETED GAMES</h2><span class="count"><?=$matchCount?> GAME<?=$matchCount===1?'':'S'?></span></div>
<?php if(!$matches):?><div class="card empty">No completed games were recorded for this session.</div><?php endif;?>
<div class="history-match-list">
<?php foreach($matches as $i=>$m):$aWin=(int)$m['score_a']>(int)$m['score_b'];$bWin=(int)$m['score_b']>(int)$m['score_a'];$startTs=!empty($m['started_at'])?strtotime((string)$m['started_at']):null;$endTs=!empty($m['ended_at'])?strtotime((string)$m['ended_at']):null;$duration=($startTs&&$endTs)?max(0,$endTs-$startTs):null;?>
<div class="card history-match"><div class="history-match-head"><div><div class="eyebrow">GAME <?=($i+1)?></div><?php if($startTs):?><b><?=date('M j, Y • g:i A',$startTs)?><?=$endTs?' → '.date('g:i A',$endTs):''?></b><?php else:?><b>Recorded Match</b><?php endif;?></div><?php if($duration!==null):?><span class="pill history-duration"><?=intdiv($duration,60)?>m <?=str_pad((string)($duration%60),2,'0',STR_PAD_LEFT)?>s</span><?php endif;?></div>
<div class="result-team <?=$aWin?'winner':''?>"><div class="result-name"><?php if($aWin):?><span class="winner-tag">WINNER</span><?php endif;?><b><?=displayName($m,'n1','x1')?> + <?=displayName($m,'n2','x2')?></b></div><strong class="result-score"><?=$m['score_a']?></strong></div>
<div class="result-team <?=$bWin?'winner':''?>"><div class="result-name"><?php if($bWin):?><span class="winner-tag">WINNER</span><?php endif;?><b><?=displayName($m,'n3','x3')?> + <?=displayName($m,'n4','x4')?></b></div><strong class="result-score"><?=$m['score_b']?></strong></div>
</div>
<?php endforeach;?></div></section>

<section class="section"><div class="section-head"><h2>PLAYERS WHO JOINED</h2><span class="count"><?=count($played)?> PLAYER<?=count($played)===1?'':'S'?></span></div><div class="card compact">
<?php if(!$played):?><div class="empty">No checked-in player history was recorded.</div><?php endif;?>
<?php foreach($played as $p):$nm=playerName($p);?><div class="player-row"><div class="avatar"><?=initials($nm)?></div><div class="player-info"><b><?=esc($nm)?></b><div class="meta"><?=esc($p['skill_level'])?> • DUPR <?=esc(declaredDuprLabel($p))?> • <?=$p['games_played']?> games</div></div><span class="pill"><?=$p['wins']?>W <?=$p['losses']?>L</span></div><?php endforeach;?>
</div></section>
<?php elseif(!$rental):?>
<section class="section"><div class="section-head"><h2>EXPECTED PLAYERS</h2><span class="count"><?=count($roster)?> ROSTERED</span></div><div class="card compact"><?php if(!$roster):?><div class="empty">No players pre-registered for this Open Play.</div><?php endif;?><?php foreach($roster as $p):$nm=playerName($p);?><div class="player-row"><div class="avatar"><?=initials($nm)?></div><div class="player-info"><b><?=esc($nm)?></b><div class="meta"><?=esc($p['skill_level'])?> • DUPR <?=esc(declaredDuprLabel($p))?></div></div></div><?php endforeach;?></div></section>
<?php endif;?>
<?php require __DIR__.'/includes/footer.php';
