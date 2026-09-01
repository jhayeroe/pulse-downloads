<?php
require __DIR__.'/includes/app.php';
$admin=isAdmin();$activePage='schedules';$pageTitle='Bookings - RS8 Pickleball';
$upcoming=[];$history=[];$requests=[];
try{$upcoming=upcomingScheduledSessions($pdo,80);}catch(Throwable $e){}
if($admin){try{$history=recentSessions($pdo,40);}catch(Throwable $e){}try{$requests=v1320PendingRequests($pdo,50);}catch(Throwable $e){}}
require __DIR__.'/includes/header.php';
?>
<div class="page-title title-with-action"><div><h1>Bookings</h1><p><?=$admin?'Court bookings, pending requests and saved match history — grouped so you only open what you need.':'View confirmed court time and request your preferred slot.'?></p></div><?php if($admin):?><a class="mini primary add-top" href="schedule_edit.php">+ ADD</a><?php else:?><a class="mini primary add-top" href="request_schedule.php">REQUEST</a><?php endif;?></div>
<div class="booking-groups">
<details class="booking-group" open><summary><span><b>BOOKINGS</b><small>Upcoming / confirmed court time</small></span><span class="count"><?=count($upcoming)?> BOOKING<?=count($upcoming)===1?'':'S'?></span></summary><div class="booking-group-body">
<?php if(!$upcoming):?><div class="empty">No confirmed upcoming court bookings.</div><?php endif;?>
<?php foreach($upcoming as $s):$rental=isCourtRental($s);$state=scheduleDisplayState($s);?><a class="booking-row" href="<?=$admin?'schedule_view.php?id='.(int)$s['id']:'#'?>"><div><span class="eyebrow"><?=esc(strtoupper(scheduleTypeLabel($s['schedule_type']??'open_play')))?></span><b><?=$rental?'Court Rental • Reserved':esc($s['name'])?></b><small><?=prettyDate($s['scheduled_date'])?> • <?=prettyTime($s['scheduled_time'])?>–<?=prettyTime($s['scheduled_end_time'])?> • Court 1</small></div><span class="pill <?=$state==='ongoing'?'live':'upcoming-pill'?>"><?=esc(scheduleDisplayLabel($s))?></span></a><?php endforeach;?>
<?php if(!$admin):?><a class="btn" href="request_schedule.php">REQUEST A COURT SLOT</a><?php endif;?>
</div></details>
<?php if($admin):?><details class="booking-group"><summary><span><b>PENDING REQUESTS</b><small>Public requests waiting for Admin review</small></span><span class="count"><?=count($requests)?> PENDING</span></summary><div class="booking-group-body">
<?php if(!$requests):?><div class="empty">No pending public schedule requests.</div><?php endif;?>
<?php foreach($requests as $r):$conf=v1320RequestConflict($pdo,$r);?><div class="booking-row"><div><span class="eyebrow"><?=esc(strtoupper(scheduleTypeLabel($r['schedule_type'])))?> REQUEST</span><b><?=esc($r['request_name']?:$r['requester_name'])?></b><small><?=prettyDate($r['requested_date'])?> • <?=prettyTime($r['requested_time'])?>–<?=prettyTime($r['requested_end_time'])?> • <?=esc($r['requester_name'])?></small></div><span class="pill <?=$conf?'warning-pill':'upcoming-pill'?>"><?=$conf?'CONFLICT':'PENDING'?></span></div><?php endforeach;?>
<a class="btn ghost" href="schedules.php">REVIEW / APPROVE REQUESTS</a></div></details>
<details class="booking-group"><summary><span><b>PAST BOOKINGS / MATCH HISTORY</b><small>Completed rentals and Open Play sessions</small></span><span class="count"><?=count($history)?> SAVED</span></summary><div class="booking-group-body">
<?php if(!$history):?><div class="empty">No saved booking history yet.</div><?php endif;?>
<?php foreach($history as $h):?><a class="booking-row" href="schedule_view.php?id=<?=(int)$h['id']?>"><div><span class="eyebrow"><?=esc(strtoupper(scheduleTypeLabel($h['schedule_type']??'open_play')))?></span><b><?=esc($h['name'])?></b><small><?=prettyDate($h['scheduled_date'])?> • <?=isCourtRental($h)?esc(rentalPaymentStatus($h)):(int)$h['match_count'].' completed matches'?></small></div><span class="pill ended-pill">VIEW HISTORY</span></a><?php endforeach;?>
</div></details><?php endif;?></div>
<?php if(!$admin):?><section class="section"><div class="card payment-help"><div><div class="eyebrow">PAYMENT / PROOF</div><b>Already instructed to pay?</b><p class="meta">Use the official Drea Mateo Messenger thread to send your proof of payment. A booking is only confirmed after Admin approval.</p></div><a class="btn ghost" href="<?=esc(rs8DreaMessengerUrl())?>" target="_blank" rel="noopener">SEND PROOF TO DREA</a></div></section><?php endif;?>
<?php require __DIR__.'/includes/footer.php';
