<?php
session_start();
mb_internal_encoding('utf8');

// セッションを破棄
$_SESSION = [];
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 1800, '/');
}
session_destroy();

// クッキーも削除
setcookie('id', '', time() - 1800);
setcookie('auto_login_token', '', time() - 1800);

// login.phpへ遷移
header('Location:login.php');
