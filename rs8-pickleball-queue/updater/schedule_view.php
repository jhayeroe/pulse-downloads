<?php
require __DIR__.'/includes/app.php';
requireAdmin();
$id=(int)($_GET['id']??0);
$session=$id?sessionById($pdo,$id):null;
if(!$session){flashSet('err','Schedule not found.');go('calendar.php');}
$rental=isCourtRental($session);
$state=scheduleDisplayState($session);
$label=scheduleDisplayLabel($session);
$roster=[];$matchCount=0;
if(!$rental){
    try{$roster=rosterRows($pdo,$id);}catch(Throwable $e){$roster=[];}
    try{$q=$pdo->prepare("SELECT COUNT(*) FROM matches WHERE session_id=? AND status='completed'");$q->execute([$id]);$matchCount=(int)$q->fetchColumn();}catch(Throwable $e){$matchCount=0;}
}
$activePage='calendar';$pageTitle='Schedule Details - RS8 Pickleball';require __DIR__.'/includes/header.php';
?>
<div class="page-title"><h1>Schedule Details</h1><p>Review the booking first. Editing is a separate action so calendar taps never change a schedule by accident.</p></div>
<section class="section"><div class="card schedule-view-card <?=$rental?'rental-card':'openplay-card'?> calendar-<?=$state?>">
<div class="schedule-top"><div><div class="eyebrow"><?=esc(strtoupper(scheduleTypeLabel($session['schedule_type']??'open_play')))?></div><div class="schedule-name"><?=esc($session['name'])?></div><div class="schedule-date"><?=prettyDate($session['scheduled_date'])?></div><div class="meta"><?=prettyTime($session['scheduled_time'])?>–<?=prettyTime($session['scheduled_end_time'])?> • Court 1</div></div><span class="pill <?=$state==='ongoing'?'live':($state==='ended'?'ended-pill':'')?>"><?=esc($label)?></span></div>
<?php if($rental):?>
<div class="rental-summary" style="margin-top:14px"><div><span>RENTER</span><b><?=esc($session['renter_name']?:'—')?></b></div><div><span>CONTACT</span><b><?=esc($session['contact_number']?:'—')?></b></div><div><span>PAYMENT</span><b><?=esc(rentalPaymentStatus($session))?></b></div><div><span>BALANCE</span><b>₱<?=number_format(rentalBalance($session),2)?></b></div></div>
<div class="card compact" style="margin-top:12px"><div class="stat-row"><span>Rental Fee</span><b>₱<?=number_format((float)$session['rental_fee'],2)?></b></div><div class="stat-row"><span>Amount Paid</span><b>₱<?=number_format((float)$session['amount_paid'],2)?></b></div><?php if(trim((string)$session['rental_notes'])!==''):?><div class="stat-row"><span>Notes</span><b><?=esc($session['rental_notes'])?></b></div><?php endif;?></div>
<?php else:?>
<div class="stats-grid" style="margin-top:14px"><div><b><?=count($roster)?></b><span>EXPECTED</span></div><div><b><?=$matchCount?></b><span>MATCHES</span></div><div><b><?=esc($state==='ongoing'?'LIVE':strtoupper($state))?></b><span>STATUS</span></div></div>
<?php endif;?>
<div class="stack" style="margin-top:14px">
<?php if(($session['status']??'scheduled')==='active'):?><a class="btn success" href="queue.php">OPEN LIVE QUEUE</a>
<?php elseif(($session['status']??'scheduled')==='scheduled' && $state!=='ended'):?>
<a class="btn" href="schedule_edit.php?id=<?=$id?>">EDIT SCHEDULE</a>
<?php if(!$rental):?><a class="btn success" href="attendance.php?id=<?=$id?>">CONFIRM ATTENDANCE & START</a><?php endif;?>
<?php endif;?>
<a class="btn ghost" href="calendar.php?month=<?=esc(substr((string)$session['scheduled_date'],0,7))?>">BACK TO CALENDAR</a>
</div></div></section>
<?php if(!$rental):?><section class="section"><div class="section-head"><h2>EXPECTED PLAYERS</h2><span class="count"><?=count($roster)?> ROSTERED</span></div><div class="card compact"><?php if(!$roster):?><div class="empty">No players pre-registered for this Open Play.</div><?php endif;?><?php foreach($roster as $p):$nm=playerName($p);?><div class="player-row"><div class="avatar"><?=initials($nm)?></div><div class="player-info"><b><?=esc($nm)?></b><div class="meta"><?=esc($p['skill_level'])?> • DUPR <?=esc(declaredDuprLabel($p))?></div></div></div><?php endforeach;?></div></section><?php endif;?>
<?php require __DIR__.'/includes/footer.php';
