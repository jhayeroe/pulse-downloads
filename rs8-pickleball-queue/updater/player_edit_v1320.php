<?php
requireAdmin();
$id=(int)($_GET['id']??$_POST['player_id']??0);$player=null;$duplicateMatches=[];$formData=null;
if($id){$q=$pdo->prepare("SELECT * FROM players WHERE id=? LIMIT 1");$q->execute([$id]);$player=$q->fetch()?:null;if(!$player){flashSet('err','Player not found.');go('players.php');}}
try{
    if($_SERVER['REQUEST_METHOD']==='POST'){
        verifyCsrf();
        $name=trim((string)($_POST['name']??''));if($name==='')throw new Exception('Player name is required.');
        $nickname=trim((string)($_POST['nickname']??''));$gender=$_POST['gender']??'M';if(!in_array($gender,['M','F','Other'],true))$gender='M';
        $dupr=normalizeDeclaredDupr($_POST['dupr_rating']??null);$level=skillLevelFromDupr($dupr);$resolution=(string)($_POST['duplicate_resolution']??'');
        $duplicateMatches=v1320FindDuplicatePlayers($pdo,$name,$id);
        if($duplicateMatches){
            if($resolution==='use_existing'){
                $existing=(int)($_POST['existing_player_id']??0);foreach($duplicateMatches as $d)if((int)$d['id']===$existing)go('player_edit.php?id='.$existing);throw new Exception('Existing player was not found.');
            }
            if($resolution!=='create_distinct'){
                $formData=['name'=>$name,'nickname'=>$nickname,'gender'=>$gender,'dupr_rating'=>$dupr];
            }else{
                if($nickname==='')throw new Exception('Add a unique nickname to distinguish this different person.');
                if(!v1320NicknameAvailable($pdo,$name,$nickname,$id))throw new Exception('That full name + nickname already exists. Choose another nickname.');
                if($id){$pdo->prepare("UPDATE players SET name=?,nickname=?,gender=?,skill_level=?,dupr_rating=?,updated_at=NOW() WHERE id=?")->execute([$name,$nickname,$gender,$level,$dupr,$id]);flashSet('ok','Player profile updated with a distinguishing nickname.');go('players.php');}
                v1320CreatePlayer($pdo,$name,$nickname,$gender,$level,$_POST['dupr_rating']??null,true);flashSet('ok','Different player created with a unique nickname.');go('players.php');
            }
        }else{
            if($id){$pdo->prepare("UPDATE players SET name=?,nickname=?,gender=?,skill_level=?,dupr_rating=?,updated_at=NOW() WHERE id=?")->execute([$name,$nickname,$gender,$level,$dupr,$id]);flashSet('ok',$dupr===null?'Player profile updated as NR (Not Rated).':'Player profile updated.');go('players.php');}
            v1320CreatePlayer($pdo,$name,$nickname,$gender,$level,$_POST['dupr_rating']??null);flashSet('ok',$dupr===null?'Player added as NR (Not Rated).':'Player added.');go('players.php');
        }
    }
}catch(Throwable $e){flashSet('err',$e->getMessage());if(!$formData)go('player_edit.php'.($id?'?id='.$id:''));}
$activePage='home';$pageTitle=($player?'Edit':'Add').' Player - RS8 Pickleball';require __DIR__.'/includes/header.php';
$nameValue=$formData['name']??($player['name']??'');$nicknameValue=$formData['nickname']??($player['nickname']??'');$genderValue=$formData['gender']??($player['gender']??'M');
$duprValue=$formData&&$formData['dupr_rating']!==null?number_format((float)$formData['dupr_rating'],3,'.',''):(isset($player['dupr_rating'])&&$player['dupr_rating']!==null?number_format((float)$player['dupr_rating'],3,'.',''):'');
?>
<div class="page-title"><h1><?=$player?'Edit Player':'Add Player'?></h1><p>Declared DUPR is optional. Duplicate full names are checked before saving.</p></div>
<?php if($duplicateMatches):?><section class="section"><div class="card duplicate-player-card"><div class="notice warning"><b>DUPLICATE PLAYER FOUND</b><br><?=esc($nameValue)?> already exists. Use the existing profile, add a distinguishing nickname if this is genuinely a different person, or cancel.</div><?php foreach($duplicateMatches as $d):?><div class="player-row"><div class="avatar"><?=initials(playerName($d))?></div><div class="player-info"><b><?=esc($d['name'])?><?=trim((string)$d['nickname'])!==''?' • '.esc($d['nickname']):''?></b><div class="meta"><?=esc($d['skill_level'])?> • DUPR <?=esc(declaredDuprLabel($d))?> • <?=intval($d['total_games']??0)?> games</div></div><form method="post"><?=csrfField()?><input type="hidden" name="player_id" value="<?=$id?>"><input type="hidden" name="name" value="<?=esc($nameValue)?>"><input type="hidden" name="nickname" value="<?=esc($nicknameValue)?>"><input type="hidden" name="gender" value="<?=esc($genderValue)?>"><input type="hidden" name="dupr_rating" value="<?=esc($duprValue)?>"><input type="hidden" name="duplicate_resolution" value="use_existing"><input type="hidden" name="existing_player_id" value="<?=$d['id']?>"><button class="mini success">USE EXISTING</button></form></div><?php endforeach;?></div></section><?php endif;?>
<section class="section"><div class="card"><form method="post" class="form"><?=csrfField()?><input type="hidden" name="player_id" value="<?=$id?>"><label>Full Name</label><input name="name" value="<?=esc($nameValue)?>" required><label>Nickname</label><input name="nickname" value="<?=esc($nicknameValue)?>" placeholder="Optional<?= $duplicateMatches?' — required if this is a different person':'' ?>"><div class="two"><div><label>Gender</label><select name="gender"><?php foreach(['M'=>'Male','F'=>'Female','Other'=>'Other'] as $v=>$label):?><option value="<?=$v?>" <?=$genderValue===$v?'selected':''?>><?=$label?></option><?php endforeach;?></select></div><div><label>Declared DUPR</label><input name="dupr_rating" type="text" inputmode="decimal" pattern="(?:10\.000|[0-9]\.[0-9]{3})" maxlength="6" value="<?=esc($duprValue)?>" placeholder="Optional e.g. 3.500" data-dupr-input><div class="hint">Blank = NR. If entered, exactly 3 decimals.</div></div></div><div class="notice info dupr-classification"><b>Auto Classification</b><br><span data-dupr-class><?=esc($player['skill_level']??'NR')?></span></div><?php if($duplicateMatches):?><input type="hidden" name="duplicate_resolution" value="create_distinct"><button class="btn warning">SAVE AS DIFFERENT PERSON</button><?php else:?><button class="btn"><?=$player?'SAVE PROFILE':'+ ADD PLAYER'?></button><?php endif;?><a class="btn ghost" href="players.php">DISCARD / CANCEL</a></form></div></section>
<script>(function(){const input=document.querySelector('[data-dupr-input]'),out=document.querySelector('[data-dupr-class]');if(!input||!out)return;function classify(){const raw=input.value.trim();if(raw===''){out.textContent='NR (Not Rated)';return;}if(!/^(?:10\.000|[0-9]\.[0-9]{3})$/.test(raw)){out.textContent='Enter DUPR using exactly 3 decimals';return;}const r=parseFloat(raw);let label='Beginner';if(r>=5)label='Pro / Elite';else if(r>=4)label='Advance';else if(r>=3.5)label='Inter High';else if(r>=3)label='Inter Low';else if(r>=2.5)label='Novice';out.textContent=label;}input.addEventListener('input',classify);classify();})();</script>
<?php require __DIR__.'/includes/footer.php';
