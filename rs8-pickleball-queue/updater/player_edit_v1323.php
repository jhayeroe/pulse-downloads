<?php
require __DIR__.'/includes/app.php';
requireAdmin();
$id=(int)($_GET['id']??$_POST['player_id']??0);$player=null;
if($id){$q=$pdo->prepare("SELECT * FROM players WHERE id=? LIMIT 1");$q->execute([$id]);$player=$q->fetch()?:null;if(!$player){flashSet('err','Player not found.');go('players.php');}}
try{
    if($_SERVER['REQUEST_METHOD']==='POST'){
        $name=trim($_POST['name']??'');if($name==='')throw new Exception('Player name is required.');
        $nickname=trim($_POST['nickname']??'');$gender=$_POST['gender']??'M';if(!in_array($gender,['M','F','Other'],true))$gender='M';
        $dupr=normalizeDeclaredDupr($_POST['dupr_rating']??null);$level=skillLevelFromDupr($dupr);v1323ValidatePlayerIdentity($pdo,$name,$nickname,$id);
        if($id){$pdo->prepare("UPDATE players SET name=?,nickname=?,gender=?,skill_level=?,dupr_rating=?,updated_at=NOW() WHERE id=?")->execute([$name,$nickname,$gender,$level,$dupr,$id]);flashSet('ok','Player profile updated.');go('players.php');}
        createPlayer($pdo,$name,$nickname,$gender,$level,$_POST['dupr_rating']??null);flashSet('ok','Player added.');go('players.php');
    }
}catch(Throwable $e){flashSet('err',$e->getMessage());go('player_edit.php'.($id?'?id='.$id:''));}
$activePage='home';$pageTitle=($player?'Edit':'Add').' Player - RS8 Pickleball';require __DIR__.'/includes/header.php';
$duprValue=isset($player['dupr_rating'])&&$player['dupr_rating']!==null?number_format((float)$player['dupr_rating'],3,'.',''):'';
?>
<div class="page-title"><h1><?=$player?'Edit Player':'Add Player'?></h1><p>Declared DUPR is optional. Duplicate full names must be distinguished by a unique nickname, and duplicate nicknames are not allowed.</p></div>
<section class="section"><div class="card"><form method="post" class="form"><input type="hidden" name="player_id" value="<?=$id?>"><label>Full Name</label><input name="name" value="<?=esc($player['name']??'')?>" required><label>Nickname</label><input name="nickname" value="<?=esc($player['nickname']??'')?>" placeholder="Optional unless a duplicate name exists"><div class="hint">If the full name already exists, change/add a unique nickname. Nicknames cannot be duplicated.</div><div class="two"><div><label>Gender</label><select name="gender"><?php foreach(['M'=>'Male','F'=>'Female','Other'=>'Other'] as $v=>$label):?><option value="<?=$v?>" <?=($player['gender']??'M')===$v?'selected':''?>><?=$label?></option><?php endforeach;?></select></div><div><label>Declared DUPR</label><input name="dupr_rating" type="text" inputmode="decimal" pattern="(?:10\.000|[0-9]\.[0-9]{3})" maxlength="6" value="<?=esc($duprValue)?>" placeholder="Optional e.g. 3.500"></div></div><button class="btn"><?=$player?'SAVE PROFILE':'+ ADD PLAYER'?></button><a class="btn ghost" href="players.php">CANCEL</a></form></div></section>
<?php require __DIR__.'/includes/footer.php';
