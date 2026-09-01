<?php
function v1323QueueRuntime(PDO $pdo): void {
    if(basename((string)($_SERVER['SCRIPT_NAME']??''))!=='queue.php' || ($_SERVER['REQUEST_METHOD']??'GET')!=='POST')return;
    if(!isAdmin())return;
    $action=(string)($_POST['action']??'');
    if(!in_array($action,['finish_match','shuffle_ondeck','status','checkin','quick_add_arrival'],true))return;
    try{
        $session=currentSession($pdo);if(!$session)throw new Exception('No active session.');$sid=(int)$session['id'];
        if($action==='finish_match'){if(($_POST['score_a']??'')===''||($_POST['score_b']??'')==='')throw new Exception('Enter both scores before ending the match.');v1320FinishMatch($pdo,(int)$_POST['match_id'],(int)$_POST['score_a'],(int)$_POST['score_b']);flashSet('ok','Score saved. Finished players moved to the end of the Queue.');go('queue.php');}
        if($action==='shuffle_ondeck'){v1320ShuffleOnDeck($pdo,$sid);flashSet('ok','On Deck shuffled. Same four players, new pairing.');go('queue.php');}
        if($action==='status'){$pid=(int)($_POST['player_id']??0);$status=(string)($_POST['status']??'');if(!in_array($status,['Waiting','Paused','Checked Out'],true))throw new Exception('Invalid status.');v1320ClearOnDeckIfContains($pdo,$sid,$pid);if($status==='Waiting')$pdo->prepare("UPDATE session_players SET status='Waiting',queue_credit=? WHERE session_id=? AND player_id=?")->execute([v1320NextQueueOrder($pdo,$sid),$sid,$pid]);else $pdo->prepare("UPDATE session_players SET status=? WHERE session_id=? AND player_id=?")->execute([$status,$sid,$pid]);v1320GenerateOnDeck($pdo,$sid);flashSet('ok','Player status updated. On Deck stays editable.');go('queue.php');}
        if($action==='checkin'){$pid=(int)($_POST['player_id']??0);if(!$pid)throw new Exception('Player not found.');v1320CheckInPlayer($pdo,$sid,$pid);flashSet('ok','Player added to the end of the Queue.');go('queue.php#registered');}
        if($action==='quick_add_arrival'){$data=['name'=>trim((string)($_POST['name']??'')),'nickname'=>trim((string)($_POST['nickname']??'')),'gender'=>(string)($_POST['gender']??'M'),'dupr_rating'=>(string)($_POST['dupr_rating']??'')];if($data['name']==='')throw new Exception('Player name is required.');$dups=v1323FindDuplicatePlayers($pdo,$data['name'],$data['nickname']);if($dups){$_SESSION['queue_duplicate_candidate']=$data;go('duplicate_player.php');}$pid=v1320CreatePlayer($pdo,$data['name'],$data['nickname'],$data['gender'],'NR',$data['dupr_rating']);v1320CheckInPlayer($pdo,$sid,$pid);flashSet('ok','New player created and added to Queue.');go('queue.php');}
    }catch(Throwable $e){flashSet('err',$e->getMessage());go('queue.php');}
}
