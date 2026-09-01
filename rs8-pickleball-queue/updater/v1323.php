<?php
function v1323NicknameExists(PDO $pdo,string $nickname,int $excludeId=0): bool {
    $nickname=trim($nickname);if($nickname==='')return false;
    $q=$pdo->prepare("SELECT COUNT(*) FROM players WHERE LOWER(TRIM(COALESCE(nickname,'')))=LOWER(TRIM(?)) AND id<>?");
    $q->execute([$nickname,$excludeId]);return (int)$q->fetchColumn()>0;
}
function v1323FindDuplicatePlayers(PDO $pdo,string $name,string $nickname='',int $excludeId=0): array {
    $name=trim(preg_replace('/\s+/u',' ',$name));$nickname=trim($nickname);
    if($name===''&&$nickname==='')return [];
    $sql="SELECT p.*,COALESCE((SELECT SUM(sp.games_played) FROM session_players sp WHERE sp.player_id=p.id),0) total_games FROM players p WHERE p.id<>? AND (LOWER(TRIM(p.name))=LOWER(TRIM(?))";
    $args=[$excludeId,$name];
    if($nickname!==''){$sql.=" OR LOWER(TRIM(COALESCE(p.nickname,'')))=LOWER(TRIM(?))";$args[]=$nickname;}
    $sql.=") ORDER BY p.is_active DESC,p.id ASC";
    $q=$pdo->prepare($sql);$q->execute($args);return $q->fetchAll();
}
function v1323ValidatePlayerIdentity(PDO $pdo,string $name,string $nickname,int $excludeId=0): void {
    $dups=v1323FindDuplicatePlayers($pdo,$name,$nickname,$excludeId);if(!$dups)return;
    $sameName=false;$sameNick=false;
    foreach($dups as $d){if(strcasecmp(trim((string)$d['name']),trim($name))===0)$sameName=true;if($nickname!==''&&strcasecmp(trim((string)($d['nickname']??'')),trim($nickname))===0)$sameNick=true;}
    if($sameNick)throw new Exception('That nickname is already used. Change the nickname before saving.');
    if($sameName&&trim($nickname)==='')throw new Exception('Duplicate full name found. Add or change the nickname to clearly distinguish this player.');
    if($sameName&&v1323NicknameExists($pdo,$nickname,$excludeId))throw new Exception('Duplicate full name found and that nickname is already used. Choose a unique nickname.');
}
