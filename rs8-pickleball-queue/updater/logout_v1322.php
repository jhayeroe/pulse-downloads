<?php
require __DIR__.'/includes/app.php';
rs8ForgetAdmin($pdo);
logoutAdmin();
flashSet('ok','Admin logged out on this device. Public view is active.');
go('home.php');
