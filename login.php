<?php
session_start();
mb_internal_encoding('utf8');

// 既にログイン済みなら、board.phpにリダイレクト
if (isset($_SESSION['id'])) {
    header('Location:board.php');
}

// 自動ログイン用のトークンがある場合
if (isset($_COOKIE['id']) && isset($_COOKIE['auto_login_token'])) {

    $options = [
        'options' => [
            'min_range' => 1,
        ],
    ];

    // SQLインジェクション対策のため、クッキーのidが1以上の整数のときのみ、処理を行う
    if (filter_var($_COOKIE['id'], FILTER_VALIDATE_INT, $options)) {
        try {
            $pdo = new PDO('mysql:dbname=php_jissen;host=localhost;charset=utf8;', 'root', '');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $stmt = $pdo->prepare('select id, name, mail, auto_login_token from user where id = ?');
            $stmt->execute([$_COOKIE['id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            echo mb_convert_encoding($e->getMessage(), 'utf8', 'sjis');
        }

        $pdo = null;

        // クッキーの自動ログイン用のトークンをハッシュ化し、データベースのものと一致する場合、ログイン処理を行う
        if ($user && password_verify($_COOKIE['auto_login_token'], $user['auto_login_token'])) {

            $_SESSION['id'] = $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['mail'] = $user['mail'];

            // ログインに成功した場合、自動ログイン用のトークンを更新する。
            $auto_login_token = bin2hex(random_bytes(32));
            $hashed_auto_login_token = password_hash($auto_login_token, PASSWORD_DEFAULT);

            try {
                $pdo = new PDO('mysql:dbname=php_jissen;host=localhost;charset=utf8;', 'root', '');
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $stmt = $pdo->prepare('update user set auto_login_token = ? where id = ?');
                $stmt->execute([$hashed_auto_login_token, $_SESSION['id']]);
            } catch (PDOException $e) {
                echo mb_convert_encoding($e->getMessage(), 'utf8', 'sjis');
            }

            $pdo = null;

            setcookie('id', $_SESSION['id'], time() + 60 * 60 * 24 * 7, null, null, false, true);
            setcookie('auto_login_token', $auto_login_token, time() + 60 * 60 * 24 * 7, null, null, false, true);

            header('Location:board.php');
        }
    }
}

// エラー変数をリセット
$errors = [];

if (isset($_POST['csrf_token']) && isset($_SESSION['csrf_token'])) {
    if ($_POST['csrf_token'] === $_SESSION['csrf_token']) {
        // CSRF対策のため、送信されたトークンとセッションのトークンが一致したときのみ、POST処理を行う

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            // POST処理
            // エスケープ処理
            $input['mail'] = htmlentities($_POST['mail'] ?? '', ENT_QUOTES);
            $input['password'] = htmlentities($_POST['password'] ?? '', ENT_QUOTES);

            // 再投稿しやすいようにメールアドレスのみセッションに格納（パスワードはセキュリティを確保するため、格納しない）
            $_SESSION['mail'] = $input['mail'];

            // ログイン状態を保持するにチェックがあった場合、その旨をセッションに代入
            if ($_POST['login_keep'] ?? '') {
                $_SESSION['login_keep'] = $_POST['login_keep'];
            }

            // メールアドレスのバリデーションチェック
            if (!filter_input(INPUT_POST, 'mail', FILTER_VALIDATE_EMAIL) || $_POST['mail'] == "1' or '1' = '1';--") {
                $errors['mail_password'] = 'メールアドレスとパスワードを正しく入力して下さい。';
            }

            // パスワードのバリデーションチェック
            if (strlen(trim($_POST['password'] ?? '')) == 0) {
                $errors['mail_password'] = 'メールアドレスとパスワードを正しく入力して下さい。';
            }

            // ログイン認証
            if (empty($errors)) {
                // データベースに接続
                try {
                    $pdo = new PDO('mysql:dbname=php_jissen;host=localhost;charset=utf8;', 'root', '');
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $stmt = $pdo->prepare('select * from user where mail = ?');
                    $stmt->execute([$input['mail']]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                } catch (PDOException $e) {
                    echo mb_convert_encoding($e->getMessage(), 'utf8', 'sjis');
                }

                // データベース切断
                $pdo = null;

                // データベースの照合が完了した場合の処理
                if ($user && password_verify($input['password'], $user['password'])) {
                    // セッションにデータベースの情報を代入、パスワードのみ入力されたものを代入
                    $_SESSION['id'] = $user['id'];
                    $_SESSION['name'] = $user['name'];
                    $_SESSION['mail'] = $user['mail'];

                    // ログイン状態を保持するにチェックがあった場合、自動ログイン用のトークンをクッキーにセットし、それをハッシュ化したものをデータベースに保存
                    if (!empty($_SESSION['id']) && !empty($_SESSION['login_keep'])) {
                        $auto_login_token = bin2hex(random_bytes(32));
                        $hashed_auto_login_token = password_hash($auto_login_token, PASSWORD_DEFAULT);

                        try {
                            $pdo = new PDO('mysql:dbname=php_jissen;host=localhost;charset=utf8;', 'root', '');
                            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                            $stmt = $pdo->prepare('update user set auto_login_token = ? where id = ?;');
                            $stmt->execute([$hashed_auto_login_token, $_SESSION['id']]);
                        } catch (PDOException $e) {
                            echo mb_convert_encoding($e->getMessage(), 'utf8', 'sjis');
                        }

                        $pdo = null;

                        setcookie('id', $_SESSION['id'], time() + 60 * 60 * 24 * 7, null, null, false, true);
                        setcookie('auto_login_token', $auto_login_token, time() + 60 * 60 * 24 * 7, null, null, false, true);
                    }

                    // board.phpにジャンプ
                    header('Location:board.php');
                } else {
                    // ログインに失敗した場合
                    $errors['mail_password'] = 'メールアドレスとパスワードを正しく入力して下さい。';
                }
            }
        }
    } else {
        // csrf対策用トークンが、セッションと送信されたもので一致しないとき
        $errors['csrf_token'] = 'ログインに失敗しました。<br>もう一度お試しください。';
    }
}

// CSRF対策のため、トークンを生成し、セッションに代入
$csrf_token = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;

?>

<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="description" content="ログインページです。">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ログインページ</title>
    <link rel="stylesheet" href="css/login.css">
</head>

<body>
    <h1 class="form__title">ログインページ</h1>
    <form action="" method="post">
        <label>メールアドレス</label>
        <input type="text" name="mail" class="text" value="<?php if (!empty($_SESSION['mail'])) {
                                                                echo $_SESSION['mail'];
                                                            } ?>">
        <label>パスワード</label>
        <input type="password" name="password" class="text" value="">
        <?php if (!empty($errors['mail_password'])) : ?>
            <p class="err_message"><?php echo $errors['mail_password']; ?></p>
        <?php endif; ?>
        <label><input type="checkbox" name="login_keep" value="1" <?php if ($_SESSION['login_keep'] ?? '') {
                                                                        echo 'checked';
                                                                    }
                                                                    ?>>ログイン状態を保持する</label>
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="submit" class="submit" value="ログイン">
        <?php if (!empty($errors['csrf_token'])) : ?>
            <p class="err_message"><?php echo $errors['csrf_token']; ?></p>
        <?php endif; ?>
    </form>
</body>

</html>