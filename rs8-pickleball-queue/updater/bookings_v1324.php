<?php
require __DIR__.'/includes/app.php';
$admin=isAdmin();$activePage='schedules';$pageTitle='Bookings - RS8 Pickleball';
$upcoming=[];$history=[];$requests=[];
try{$upcoming=upcomingScheduledSessions($pdo,80);}catch(Throwable $e){}
if($admin){try{$history=recentSessions($pdo,40);}catch(Throwable $e){}try{$requests=v1320PendingRequests($pdo,50);}catch(Throwable $e){}}
function bookingDateBox(array $s): array {
    $raw=(string)($s['scheduled_date']??'');$ts=strtotime($raw?:'now');
    return [strtoupper(date('M',$ts)),date('j',$ts)];
}
require __DIR__.'/includes/header.php';
?>
<div class="bookings-page">
  <div class="bookings-head">
    <div><h1>Bookings</h1><p><?=$admin?'Court schedule, pending requests and match history in one clean view.':'View confirmed court time or request your preferred slot.'?></p></div>
    <?php if($admin):?><a class="mini primary" href="schedule_edit.php">+ ADD</a><?php else:?><a class="mini primary" href="request_schedule.php">REQUEST</a><?php endif;?>
  </div>

  <div class="booking-stack">
    <details class="booking-panel" open>
      <summary><span class="booking-panel-title"><b>BOOKINGS</b><small>Upcoming / confirmed court time</small></span><span class="booking-count"><?=count($upcoming)?> UPCOMING</span></summary>
      <div class="booking-list">
      <?php if(!$upcoming):?><div class="booking-empty">No confirmed upcoming court bookings.</div><?php endif;?>
      <?php foreach($upcoming as $s):$rental=isCourtRental($s);$state=scheduleDisplayState($s);[$mon,$day]=bookingDateBox($s);?>
        <<?=$admin?'a':'div'?> class="booking-card" <?=$admin?'href="schedule_view.php?id='.(int)$s['id'].'"':''?>>
          <div class="booking-datebox"><span><?=$mon?></span><strong><?=$day?></strong></div>
          <div class="booking-info">
            <span class="eyebrow"><?=esc(strtoupper(scheduleTypeLabel($s['schedule_type']??'open_play')))?></span>
            <b><?=$rental?'Court Rental • Reserved':esc($s['name'])?></b>
            <small><?=prettyTime($s['scheduled_time'])?>–<?=prettyTime($s['scheduled_end_time'])?> • Court 1</small>
          </div>
          <span class="pill <?=$state==='ongoing'?'live':'upcoming-pill'?>"><?=esc(scheduleDisplayLabel($s))?></span>
        </<?=$admin?'a':'div'?>>
      <?php endforeach;?>
      <?php if(!$admin):?><a class="btn booking-action" href="request_schedule.php">REQUEST A COURT SLOT</a><?php endif;?>
      </div>
    </details>

    <?php if($admin):?>
    <details class="booking-panel">
      <summary><span class="booking-panel-title"><b>PENDING REQUESTS</b><small>Waiting for Admin review</small></span><span class="booking-count"><?=count($requests)?> PENDING</span></summary>
      <div class="booking-list">
      <?php if(!$requests):?><div class="booking-empty">No pending public schedule requests.</div><?php endif;?>
      <?php foreach($requests as $r):$conf=v1320RequestConflict($pdo,$r);$fake=['scheduled_date'=>$r['requested_date']];[$mon,$day]=bookingDateBox($fake);?>
        <div class="booking-card">
          <div class="booking-datebox"><span><?=$mon?></span><strong><?=$day?></strong></div>
          <div class="booking-info"><span class="eyebrow"><?=esc(strtoupper(scheduleTypeLabel($r['schedule_type'])))?> REQUEST</span><b><?=esc($r['request_name']?:$r['requester_name'])?></b><small><?=prettyTime($r['requested_time'])?>–<?=prettyTime($r['requested_end_time'])?> • <?=esc($r['requester_name'])?></small></div>
          <span class="pill <?=$conf?'warning-pill':'upcoming-pill'?>"><?=$conf?'CONFLICT':'PENDING'?></span>
        </div>
      <?php endforeach;?>
      <a class="btn ghost booking-action" href="schedules.php">REVIEW REQUESTS</a>
      </div>
    </details>

    <details class="booking-panel">
      <summary><span class="booking-panel-title"><b>PAST BOOKINGS / MATCH HISTORY</b><small>Completed rentals and Open Play sessions</small></span><span class="booking-count"><?=count($history)?> SAVED</span></summary>
      <div class="booking-list">
      <?php if(!$history):?><div class="booking-empty">No saved booking history yet.</div><?php endif;?>
      <?php foreach($history as $h):[$mon,$day]=bookingDateBox($h);?>
        <a class="booking-card" href="schedule_view.php?id=<?=(int)$h['id']?>">
          <div class="booking-datebox"><span><?=$mon?></span><strong><?=$day?></strong></div>
          <div class="booking-info"><span class="eyebrow"><?=esc(strtoupper(scheduleTypeLabel($h['schedule_type']??'open_play')))?></span><b><?=esc($h['name'])?></b><small><?=isCourtRental($h)?esc(rentalPaymentStatus($h)):(int)$h['match_count'].' completed matches'?></small></div>
          <span class="pill ended-pill">HISTORY</span>
        </a>
      <?php endforeach;?>
      </div>
    </details>
    <?php endif;?>
  </div>

  <?php if(!$admin):?>
  <section class="payment-proof-card">
    <div><div class="eyebrow">PAYMENT / PROOF</div><h3>Already instructed to pay?</h3></div>
    <p>Send your proof of payment directly to Drea. Your court slot is confirmed only after Admin approval.</p>
    <a class="btn ghost" href="https://www.facebook.com/messages/t/<?=esc(rs8DreaMessengerThreadId())?>" target="_blank" rel="noopener">SEND PROOF TO DREA</a>
  </section>
  <?php endif;?>
</div>
<?php require __DIR__.'/includes/footer.php';
