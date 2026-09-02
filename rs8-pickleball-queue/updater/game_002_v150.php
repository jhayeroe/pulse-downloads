<?php
require_once __DIR__.'/includes/app.php';
$activePage='home';
$pageTitle='0-0-2';
require __DIR__.'/includes/header.php';
?>
<link rel="stylesheet" href="assets/002.css?v=1.5.0">
<div class="zero150">
  <div class="zero150-stage">
    <canvas aria-label="0-0-2 pickleball court"></canvas>
    <div class="zero150-hud">
      <a class="zero150-chip zero150-home" href="home.php">HOME</a>
      <div class="zero150-chip"><span>ROOKIE</span> <b>2.000</b></div>
      <div class="zero150-chip zero150-score" data-score>0 - 0 - 2</div>
    </div>
    <div class="zero150-toast">DRIVE</div>
    <div class="zero150-target"></div><div class="zero150-swipe-trail"></div>
    <div class="zero150-help">
      <span><b>TAP / SHORT</b>Dink</span><span><b>SWIPE UP</b>Drop / Lob</span><span><b>SWIPE SIDE</b>Slice / Angle</span><span><b>LONG SWIPE</b>Drive</span>
    </div>
    <div class="zero150-start">
      <div class="zero150-card">
        <small>PICKLE QUE MINI GAME</small>
        <h1 data-title>0-0-2</h1>
        <p data-copy>Swipe when the ball reaches you. Footwork is automatic — your timing, direction and swipe shape the shot.</p>
        <div class="zero150-legend"><div><b>AUTO FOOTWORK</b><small>Your player tracks the incoming ball.</small></div><div><b>YOU CONTROL CONTACT</b><small>Timing + swipe determine the return.</small></div></div>
        <button type="button" data-start>START MATCH</button>
      </div>
    </div>
  </div>
</div>
<script src="assets/002.js?v=1.5.0"></script>
<?php require __DIR__.'/includes/footer.php'; ?>