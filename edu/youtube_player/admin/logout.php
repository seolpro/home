<?php
require_once __DIR__.'/../config.php';
unset($_SESSION['yt_admin']);
header('Location: login.php');
exit;
