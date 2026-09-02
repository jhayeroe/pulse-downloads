<?php
require __DIR__.'/includes/app.php';
$activePage='game002';$pageTitle='0-0-2 - Pickle Que';
require __DIR__.'/includes/header.php';
?>
<link rel="stylesheet" href="assets/002.css?v=1.3.25">
<div class="zero-shell" id="zeroGame" data-admin="<?=isAdmin()?'1':'0'?>">
  <div class="zero-head">
    <div><div class="eyebrow">PICKLE QUE MINI GAME</div><h1>0-0-2</h1><p>One thumb. Real timing. Real placement.</p></div>
    <button class="zero-pill" id="paddleBtn" type="button">VATIC PRO</button>
  </div>

  <div class="zero-scorebar">
    <div><small>OPPONENT</small><b id="oppName">ROOKIE 2.000</b></div>
    <div class="zero-score"><b id="scoreYou">0</b><span>-</span><b id="scoreOpp">0</b><span>-</span><b id="serverNo">2</b></div>
    <div class="zero-rating"><small>CAREER</small><b id="ratingText">UNRANKED</b></div>
  </div>

  <div class="zero-court-wrap">
    <canvas id="zeroCourt" width="720" height="1280" aria-label="0-0-2 pickleball court"></canvas>
    <div class="zero-toast" id="shotToast">SWIPE TO HIT</div>
    <div class="zero-contact" id="contactCue"></div>
  </div>

  <div class="zero-help">
    <span><b>LONG + FAST</b> Drive</span>
    <span><b>SHORT + FAST</b> Lob</span>
    <span><b>SHORT + SOFT</b> Drop / Dink</span>
  </div>

  <div class="zero-actions">
    <button class="btn ghost" id="practiceBtn" type="button">PRACTICE</button>
    <button class="btn primary" id="startMatchBtn" type="button">START MATCH</button>
  </div>
</div>

<div class="zero-modal" id="paddleModal" hidden>
 <div class="zero-modal-card">
  <div class="zero-modal-top"><div><div class="eyebrow">PADDLE LOCKER</div><h2>Choose your paddle</h2></div><button type="button" id="closePaddles">×</button></div>
  <div class="zero-paddles" id="paddleList"></div>
  <p class="meta">Trial paddles are prepared for the 10-day system. Admin accounts can test all paddles.</p>
 </div>
</div>
<script src="assets/002.js?v=1.3.25"></script>
<?php require __DIR__.'/includes/footer.php';
