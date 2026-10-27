<?php require dirname(__DIR__).'/lib.php';unset($_SESSION['apple_admin']);session_regenerate_id(true);header('Location: login.php');exit;
