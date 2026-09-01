<?php
require __DIR__.'/includes/app.php';
$admin=isAdmin();$activePage='schedules';$pageTitle='Bookings - RS8 Pickleball';
$upcoming=[];$history=[];$requests=[];
try{$upcoming=upcomingScheduledSessions($pdo,80);}catch(Throwable $e){}
if($admin){try{$history=recentSessions($pdo,40);}catch(Throwable $e){}try{$requests=v1320PendingRequests($pdo,50);}catch(Throwable $e){}}
require __DIR__.'/includes/header.php';
function bookingDateBits(string $date): array {$ts=strtotime($date);return [strtoupper(date('M',$ts)),date('j',$ts),date('D',$ts)];}
?>
<div class="page-title title-with-action"><div><h1>Bookings</h1><p><?=$admin?'Confirmed court time, pending requests and saved history.':'View confirmed Court 1 time and request your preferred slot.'?></p></div><?php if($admin):?><a class="mini primary add-top" href="schedule_edit.php">+ ADD</a><?php else:?><a class="mini primary add-top" href="request_schedule.php">REQUEST</a><?php endif;?></div>
<div class="booking-groups booking-groups-v2">
<details class="booking-group booking-group-v2" open><summary><span class="booking-summary-copy"><b>BOOKINGS</b><small>Upcoming / confirmed court time</small></span><span class="count"><?=count($upcoming)?> UPCOMING</span></summary><div class="booking-group-body booking-list-v2">
<?php if(!$upcoming):?><div class="empty">No confirmed upcoming court bookings.</div><?php endif;?>
<?php foreach($upcoming as $s):$rental=isCourtRental($s);$state=scheduleDisplayState($s);[$mon,$day,$dow]=bookingDateBits($s['scheduled_date']);?>
<?php if($admin):?><a class="booking-card-v2" href="schedule_view.php?id=<?=(int)$s['id']?>"><?php else:?><div class="booking-card-v2"><?php endif;?>
<div class="booking-datebox"><span><?=$mon?></span><b><?=$day?></b><small><?=$dow?></small></div>
<div class="booking-main"><div class="booking-topline"><span class="eyebrow"><?=esc(strtoupper(scheduleTypeLabel($s['schedule_type']??'open_play')))?></span><span class="pill <?=$state==='ongoing'?'live':'upcoming-pill'?>"><?=esc(scheduleDisplayLabel($s))?></span></div><b class="booking-title-v2"><?=$rental?'Court Rental • Reserved':esc($s['name'])?></b><small><?=prettyTime($s['scheduled_time'])?>–<?=prettyTime($s['scheduled_end_time'])?> • Court 1</small></div>
<?php if($admin):?></a><?php else:?></div><?php endif;?>
<?php endforeach;?>
<?php if(!$admin):?><a class="btn booking-request-btn" href="request_schedule.php">REQUEST A COURT SLOT</a><?php endif;?>
</div></details>

<?php if($admin):?><details class="booking-group booking-group-v2"><summary><span class="booking-summary-copy"><b>PENDING REQUESTS</b><small>Public requests waiting for Admin review</small></span><span class="count"><?=count($requests)?> PENDING</span></summary><div class="booking-group-body booking-list-v2">
<?php if(!$requests):?><div class="empty">No pending public schedule requests.</div><?php endif;?>
<?php foreach($requests as $r):$conf=v1320RequestConflict($pdo,$r);[$mon,$day,$dow]=bookingDateBits($r['requested_date']);?><div class="booking-card-v2"><div class="booking-datebox"><span><?=$mon?></span><b><?=$day?></b><small><?=$dow?></small></div><div class="booking-main"><div class="booking-topline"><span class="eyebrow"><?=esc(strtoupper(scheduleTypeLabel($r['schedule_type'])))?> REQUEST</span><span class="pill <?=$conf?'warning-pill':'upcoming-pill'?>"><?=$conf?'CONFLICT':'PENDING'?></span></div><b class="booking-title-v2"><?=esc($r['request_name']?:$r['requester_name'])?></b><small><?=prettyTime($r['requested_time'])?>–<?=prettyTime($r['requested_end_time'])?> • <?=esc($r['requester_name'])?></small></div></div><?php endforeach;?>
<a class="btn ghost" href="schedules.php">REVIEW / APPROVE REQUESTS</a></div></details>

<details class="booking-group booking-group-v2"><summary><span class="booking-summary-copy"><b>PAST BOOKINGS / MATCH HISTORY</b><small>Completed rentals and Open Play sessions</small></span><span class="count"><?=count($history)?> SAVED</span></summary><div class="booking-group-body booking-list-v2">
<?php if(!$history):?><div class="empty">No saved booking history yet.</div><?php endif;?>
<?php foreach($history as $h):[$mon,$day,$dow]=bookingDateBits($h['scheduled_date']);?><a class="booking-card-v2" href="schedule_view.php?id=<?=(int)$h['id']?>"><div class="booking-datebox ended"><span><?=$mon?></span><b><?=$day?></b><small><?=$dow?></small></div><div class="booking-main"><div class="booking-topline"><span class="eyebrow"><?=esc(strtoupper(scheduleTypeLabel($h['schedule_type']??'open_play')))?></span><span class="pill ended-pill">HISTORY</span></div><b class="booking-title-v2"><?=esc($h['name'])?></b><small><?=isCourtRental($h)?esc(rentalPaymentStatus($h)):(int)$h['match_count'].' completed matches'?></small></div></a><?php endforeach;?>
</div></details><?php endif;?></div>

<?php if(!$admin):?><section class="section"><div class="card payment-help payment-help-v2"><div><div class="eyebrow">PAYMENT / PROOF</div><b>Already instructed to pay?</b><p class="meta">Send your proof only after Admin confirms your booking. This opens the official Messenger conversation for Drea Mateo.</p></div><a class="btn ghost" href="<?=esc(rs8DreaMessengerUrl())?>" target="_blank" rel="noopener noreferrer">OPEN DREA MESSENGER</a><div class="hint">If Messenger does not open automatically, the same thread will open in Messenger Web.</div></div></section><?php endif;?>
<?php require __DIR__.'/includes/footer.php';
