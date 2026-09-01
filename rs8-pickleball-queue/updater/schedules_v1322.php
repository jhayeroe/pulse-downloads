<?php
require __DIR__.'/includes/app.php';
requireAdmin();
try{
    if($_SERVER['REQUEST_METHOD']==='POST'){
        verifyCsrf();
        $action=(string)($_POST['action']??'');$sid=(int)($_POST['session_id']??0);
        if($action==='approve_request'){
            $rid=(int)($_POST['request_id']??0);$newId=v1320ApproveRequest($pdo,$rid);
            flashSet('ok','Public schedule request approved and added to Court 1.');go('schedule_view.php?id='.$newId);
        }
        if($action==='reject_request'){
            $rid=(int)($_POST['request_id']??0);v1320RejectRequest($pdo,$rid);
            flashSet('ok','Public schedule request rejected.');go('schedules.php');
        }
        if($action==='delete'){
            $s=sessionById($pdo,$sid);if(!$s)throw new Exception('Schedule not found.');
            if(($s['status']??'scheduled')==='active'||scheduleDisplayState($s)!=='ended')throw new Exception('Only past / ended or saved schedules can be deleted. Upcoming and live court time is protected.');
            $pdo->prepare("DELETE FROM sessions WHERE id=?")->execute([$sid]);flashSet('ok','Past schedule and its linked history were deleted.');go('schedules.php');
        }
        if($action==='restart'){
            $newId=restartSession($pdo,$sid);flashSet('ok','Fresh schedule created. Previous session history remains saved.');go('schedule_edit.php?id='.$newId);
        }
        if($action==='complete_rental'){
            $s=sessionById($pdo,$sid);if(!$s||!isCourtRental($s)||$s['status']!=='scheduled')throw new Exception('Rental is not available to complete.');
            $pdo->prepare("UPDATE sessions SET status='closed',closed_at=NOW(),updated_at=NOW() WHERE id=?")->execute([$sid]);flashSet('ok','Court Rental marked complete and retained in history.');go('schedules.php');
        }
    }
}catch(Throwable $e){flashSet('err',$e->getMessage());go('schedules.php');}
$activePage='schedules';$pageTitle='Schedules - RS8 Pickleball';$scheduled=scheduledSessions($pdo,80);$history=recentSessions($pdo,25);$requests=[];
try{$requests=v1320PendingRequests($pdo,50);}catch(Throwable $e){}
require __DIR__.'/includes/header.php';
?>
<div class="page-title title-with-action"><div><h1>Schedules</h1><p>Confirmed Court 1 bookings plus public requests pending admin approval.</p></div><a class="mini primary add-top" href="schedule_edit.php">+ ADD</a></div>

<section class="section"><div class="section-head"><h2>PENDING PUBLIC REQUESTS</h2><span class="count"><?=count($requests)?> PENDING</span></div>
<?php if(!$requests):?><div class="card empty">No public schedule requests waiting for approval.</div><?php endif;?>
<?php foreach($requests as $r):$conf=v1320RequestConflict($pdo,$r);?>
<div class="card request-card <?=$conf?'request-conflict':''?>"><div class="schedule-top"><div><div class="eyebrow"><?=esc(strtoupper(scheduleTypeLabel($r['schedule_type'])))?> REQUEST</div><div class="schedule-name"><?=esc($r['request_name']?:$r['requester_name'])?></div><div class="schedule-date"><?=prettyDate($r['requested_date'])?></div><div class="meta"><?=prettyTime($r['requested_time'])?>–<?=prettyTime($r['requested_end_time'])?> • <?=esc($r['requester_name'])?> • <?=esc($r['contact_number'])?></div><?php if(trim((string)$r['notes'])!==''):?><div class="hint"><?=esc($r['notes'])?></div><?php endif;?></div><span class="pill <?=$conf?'warning-pill':'upcoming-pill'?>"><?=$conf?'CONFLICT':'PENDING'?></span></div>
<?php if($conf):?><div class="hint">Conflicts with <b><?=esc($conf['name'])?></b> • <?=prettyTime($conf['scheduled_time'])?>–<?=prettyTime($conf['scheduled_end_time'])?>. Admin makes the final booking decision; resolve the confirmed booking first if this request should take priority.</div><?php endif;?>
<div class="schedule-actions two"><form method="post"><?=csrfField()?><input type="hidden" name="action" value="approve_request"><input type="hidden" name="request_id" value="<?=$r['id']?>"><button class="btn success" <?=$conf?'disabled':''?>>APPROVE</button></form><form method="post"><?=csrfField()?><input type="hidden" name="action" value="reject_request"><input type="hidden" name="request_id" value="<?=$r['id']?>"><button class="btn danger" data-confirm="Reject this court request?">REJECT</button></form></div></div>
<?php endforeach;?></section>

<section class="section"><div class="section-head"><h2>COURT 1 SCHEDULE</h2><span class="count"><?=count($scheduled)?> BOOKING<?=count($scheduled)===1?'':'S'?></span></div>
<?php if(!$scheduled):?><div class="card empty">No scheduled court activity.<br><br><a class="btn" href="schedule_edit.php">+ CREATE SCHEDULE</a></div><?php endif;?>
<?php foreach($scheduled as $s):$rental=isCourtRental($s);$displayState=scheduleDisplayState($s);$ended=$displayState==='ended';?>
<div class="card schedule-card <?=$rental?'rental-card':'openplay-card'?> <?=$ended?'schedule-ended':''?>">
<a class="schedule-tap" href="schedule_view.php?id=<?=(int)$s['id']?>"><div class="schedule-top"><div><div class="eyebrow"><?=esc(strtoupper(scheduleTypeLabel($s['schedule_type']??'open_play')))?></div><div class="schedule-name"><?=esc($s['name'])?></div><div class="schedule-date"><?=prettyDate($s['scheduled_date'])?></div><div class="meta"><?=prettyTime($s['scheduled_time'])?>–<?=prettyTime($s['scheduled_end_time'])?> • Court 1<?=!$rental?' • '.(int)$s['roster_count'].' rostered':''?></div></div><span class="pill <?=$ended?'ended-pill':''?>"><?=esc(scheduleDisplayLabel($s))?></span></div>
<?php if($rental):?><div class="rental-summary"><div><span>RENTER</span><b><?=esc($s['renter_name'])?></b></div><div><span>PAYMENT</span><b><?=esc(rentalPaymentStatus($s))?></b></div><div><span>BALANCE</span><b>₱<?=number_format(rentalBalance($s),2)?></b></div></div><?php endif;?></a>
<div class="schedule-actions two"><a class="btn ghost" href="schedule_view.php?id=<?=(int)$s['id']?>">VIEW</a><?php if($ended):?><form method="post"><?=csrfField()?><input type="hidden" name="action" value="delete"><input type="hidden" name="session_id" value="<?=(int)$s['id']?>"><button class="btn danger" data-confirm="Delete this past schedule permanently? Any linked roster/history will also be removed.">DELETE</button></form><?php elseif($rental):?><form method="post"><?=csrfField()?><input type="hidden" name="action" value="complete_rental"><input type="hidden" name="session_id" value="<?=(int)$s['id']?>"><button class="btn success" data-confirm="Mark this Court Rental complete?">COMPLETE</button></form><?php else:?><a class="btn success" href="attendance.php?id=<?=(int)$s['id']?>">START</a><?php endif;?></div>
</div>
<?php endforeach;?></section>

<section class="section"><div class="section-head"><h2>PAST / SAVED</h2><span class="count">VIEWABLE</span></div>
<?php if(!$history):?><div class="card empty">No completed history yet.</div><?php endif;?>
<?php foreach($history as $h):?>
<div class="card schedule-card schedule-ended"><a class="schedule-tap" href="schedule_view.php?id=<?=(int)$h['id']?>"><div class="schedule-top"><div><div class="eyebrow"><?=esc(strtoupper(scheduleTypeLabel($h['schedule_type']??'open_play')))?></div><div class="schedule-name"><?=esc($h['name'])?></div><div class="meta"><?=prettyDate($h['scheduled_date'])?> • <?=prettyTime($h['scheduled_time'])?>–<?=prettyTime($h['scheduled_end_time'])?><?=isCourtRental($h)?' • '.esc(rentalPaymentStatus($h)):' • '.(int)$h['match_count'].' matches'?></div></div><span class="pill ended-pill">ENDED</span></div></a>
<div class="schedule-actions three"><a class="btn ghost" href="schedule_view.php?id=<?=(int)$h['id']?>">VIEW HISTORY</a><form method="post"><?=csrfField()?><input type="hidden" name="action" value="restart"><input type="hidden" name="session_id" value="<?=(int)$h['id']?>"><button class="btn warning">RESTART</button></form><form method="post"><?=csrfField()?><input type="hidden" name="action" value="delete"><input type="hidden" name="session_id" value="<?=(int)$h['id']?>"><button class="btn danger" data-confirm="Delete this saved schedule permanently? Match scores, attendance and linked private insights for this session will be removed.">DELETE</button></form></div></div>
<?php endforeach;?></section>
<?php require __DIR__.'/includes/footer.php';
