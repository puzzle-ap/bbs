<?php
mb_internal_encoding('utf8');
$csrf_token = bin2hex(random_bytes(32));

?>

<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>

<body>
    <form action="login.php" method="post">
        <input type="text" name="mail" class="text" value="satou@yahoo.com">
        <input type="password" name="password" class="text" value="123456">
        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
        <input type="submit" value="送信">
    </form>
    <form action="board.php" method="post">
        <input type="text" name="title" class="article__titleInput" value="CSRF対策ができているかテスト（タイトル）">
        <textarea name="comments" cols="30" rows="10" class="article__commentsInput">CSRF対策ができているかテスト（コメント）</textarea>
        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
        <input type="submit" value="送信">
    </form>

</html>