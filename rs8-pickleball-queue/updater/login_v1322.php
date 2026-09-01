<?php
require __DIR__.'/includes/app.php';
if(isAdmin()) go('home.php');
try{
    if($_SERVER['REQUEST_METHOD']==='POST'){
        verifyCsrf();
        $username=strtolower(trim((string)($_POST['username']??'')));
        $password=(string)($_POST['password']??'');
        if(!adminsInitialized($pdo)) throw new Exception('Admin accounts are not activated yet. Run first-time admin setup.');
        if(!loginAdmin($pdo,$username,$password)) throw new Exception('Invalid username or password.');
        rs8RememberAdmin($pdo);
        $after=(string)($_SESSION['after_login']??'home.php');unset($_SESSION['after_login']);
        if(str_contains($after,'login.php')||str_contains($after,'setup.php'))$after='home.php';
        flashSet('ok','Admin access unlocked on this device.');go($after);
    }
}catch(Throwable $e){$loginError=$e->getMessage();}
$activePage='settings';$pageTitle='Admin Login - RS8 Pickleball';require __DIR__.'/includes/header.php';
?>
<div class="page-title"><h1>Admin Login</h1><p>Management access for RS8 Pickleball Queue.</p></div>
<section class="section"><div class="card login-card">
<?php if(isset($loginError)):?><div class="notice danger-notice"><?=esc($loginError)?></div><?php endif;?>
<?php if(!adminsInitialized($pdo)):?><div class="notice info"><b>First-time setup required.</b><br>Complete the one-time admin account setup first.</div><a class="btn" href="setup.php">SET UP ADMIN ACCOUNTS</a>
<?php else:?><form method="post" class="form"><?=csrfField()?><label>Username</label><input name="username" autocomplete="username" autocapitalize="none" required placeholder="Username"><label>Password</label><input name="password" type="password" autocomplete="current-password" required placeholder="Password"><div class="hint">This device stays signed in for up to 30 days unless you tap Log Out.</div><button class="btn">LOGIN AS ADMIN</button></form><?php endif;?>
</div></section>
<?php require __DIR__.'/includes/footer.php';
