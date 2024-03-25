<?php
session_start();
mb_internal_encoding('UTF-8');
// ログインしていない場合は、login.phpにリダイレクト
if (empty($_SESSION['id'])) {
    header('Location:login.php');
}

// エラー配列を初期化
$errors = [];

if (isset($_POST['csrf_token']) && isset($_SESSION['csrf_token'])) {
    if ($_POST['csrf_token'] === $_SESSION['csrf_token']) {
        // CSRF対策のため、送信されたトークンとセッションのトークンが一致したときのみ、POST処理を行う

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            // POST処理
            // エスケープ処理
            $input['title'] = htmlentities($_POST['title'] ?? '', ENT_QUOTES);
            $input['comments'] = htmlentities($_POST['comments'] ?? '', ENT_QUOTES);

            // バリデーションチェック
            if (strlen(trim($_POST['title'])) == 0) {
                $errors['title'] = 'タイトルを入力して下さい。';
            }

            if (strlen(trim($_POST['comments'])) == 0) {
                $errors['comments'] = 'コメントを入力して下さい。';
            }

            // 掲示板への投稿に失敗した場合、再投稿をしやすくするため、セッションに投稿内容を代入
            $_SESSION['title'] = $input['title'];
            $_SESSION['comments'] = $input['comments'];

            // データベースの操作
            if (empty($errors)) {
                // データベースに投稿内容を挿入
                try {
                    $pdo = new PDO('mysql:dbname=php_jissen;host=localhost;charset=utf8;', 'root', '');
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $stmt = $pdo->prepare('insert into post values(?,?,?,null)');
                    $stmt->execute([$_SESSION['id'], $input['title'], $input['comments']]);
                } catch (PDOException $e) {
                    echo mb_convert_encoding($e->getMessage(), 'utf8', 'sjis');
                }

                // データベースを切断
                $pdo = null;

                // 無事に投稿が完了したら、投稿内容のセッションを破棄
                $_SESSION['title'] = '';
                $_SESSION['comments'] = '';

                // ページを更新したときにフォームを再送信しないようにする
                header('Location:board.php');
            }
        }
    } else {
        $errors['csrf_token'] = '送信に失敗しました。';
    }
}

// CSRF対策のため、トークンをセッションに代入する
$csrf_token = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;

// 掲示板の書き込みを表示する
try {
    // 最新の日時から順に10個まで、データベースから投稿内容を取得
    $pdo = new PDO('mysql:dbname=php_jissen;host=localhost;charset=utf8;', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $pdo->query('select count(user_id) from post');
    $total = $stmt->fetchColumn();
} catch (PDOException $e) {
    echo mb_convert_encoding($e->getMessage(), 'utf8', 'sjis');
}

// データベースを切断
$pdo = null;

// $GET['page']のバリデーションチェック
$options = ['options' => [
    'min_range' => 1,
]];
$input['page'] = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, $options);

// $GET['page']がnullでない、かつ、1以上の整数でない場合にエラーを出す
if ($input['page'] === false) {
    $errors['page'] = 'ページの読み込みに失敗しました。';
}

// エラーが無いときのみ処理を行う
if (empty($errors['page'])) {
    if (isset($input['page'])) {
        $pageNumber = $input['page'];
    } else {
        $pageNumber = 1;
    }

    $pageSize = 10;

    $offset = ($pageNumber - 1) * $pageSize;

    $lastPageNumber = ceil($total / $pageSize);

    if ($pageNumber >= 2) {
        $previousPageNumber = $pageNumber - 1;
    } else {
        $previousPageNumber = 1;
    }

    if ($pageNumber < $lastPageNumber) {
        $nextPageNumber = $pageNumber + 1;
    } else {
        $nextPageNumber = $lastPageNumber;
    }

    $pageRange = 2;

    $start = max($pageNumber - $pageRange, 1);
    $end = min($pageNumber + $pageRange, $lastPageNumber);

    try {
        $pdo = new PDO('mysql:dbname=php_jissen;host=localhost;charset=utf8;', 'root', '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $stmt = $pdo->prepare('select post.title, post.comments, posted_at, user.name from post join user on post.user_id = user.id order by posted_at desc limit :pageSize offset :offset');
        $stmt->bindValue('pageSize', $pageSize, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        echo mb_convert_encoding($e->getMessage(), 'utf8', 'sjis');
    }

    $pdo = null;
}

?>

<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="description" content="4eachの掲示板です">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>プログラミングに役立つ掲示板</title>
    <link rel="stylesheet" href="css/board.css">
</head>

<body>
    <header id="header" class="header">
        <div class="header__wrapper">
            <section class="header__siteTitle">
                <figure class="header__siteTitleLeft">
                    <img src="img/4each_logo.png" alt="4each" class="header__logo">
                </figure>
                <section class="header__siteTitleRight">
                    <p class="header__greetingUserName">こんにちは<?= $_SESSION['name'] ?>さん</p>
                    <form action="logout.php" method="post">
                        <input type="hidden" name="logout" value="1">
                        <input type="submit" class="header__logoutButton" value="ログアウト">
                    </form>
                </section>
            </section>
            <nav class="header__nav">
                <ul class="header__navLists">
                    <li class="header__navItem"><a href="#" class="header__navLink">トップ</a></li>
                    <li class="header__navItem"><a href="#" class="header__navLink">プロフィール</a></li>
                    <li class="header__navItem"><a href="#" class="header__navLink">4eachについて</a></li>
                    <li class="header__navItem"><a href="#" class="header__navLink">登録フォーム</a></li>
                    <li class="header__navItem"><a href="#" class="header__navLink">問い合わせ</a></li>
                    <li class="header__navItem"><a href="#" class="header__navLink">その他</a></li>
                </ul>
            </nav>
        </div>
    </header><!-- header -->

    <main>
        <article id="article" class="article">
            <h2 class="article__title">プログラミングに役立つ掲示板</h2>
            <form class="article__form" action="" method="post">
                <h3 class="article__formTitle">入力フォーム</h3>
                <label class="article__label">タイトル</label>
                <div class="article__item">
                    <input type="text" name="title" class="article__titleInput" value="<?php // セッションにタイトルがあれば、表示する
                                                                                        if (!empty($_SESSION['title'])) {
                                                                                            echo $_SESSION['title'];
                                                                                        }
                                                                                        ?>">
                    <?php if (!empty($errors['title'])) : // タイトルにエラーがあった時にエラーメッセージを表示 
                    ?>
                        <p class="article__errMessage"><?= htmlentities($errors['title'], ENT_QUOTES) ?></p>
                    <?php endif; ?>
                </div>
                <div class="article__item">
                    <label>コメント</label>
                    <textarea name="comments" cols="30" rows="10" class="article__commentsInput"><?php // セッションにコメントがあれば表示する
                                                                                                    if (!empty($_SESSION['comments'])) {
                                                                                                        echo $_SESSION['comments'];
                                                                                                    }
                                                                                                    ?></textarea>
                    <?php if (!empty($errors['comments'])) : // コメントにエラーがあった時に、エラーメッセージを表示 
                    ?>
                        <p class="article__errMessage"><?= htmlentities($errors['comments'], ENT_QUOTES) ?></p>
                    <?php endif; ?>
                </div>
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] // CSRF対策のため、セッションのトークンを送信 
                                                                ?>">
                <input type="submit" class="article__submit" value="送信">
                <?php if (!empty($errors['csrf_token'])) :  // トークンにエラーがあれば、エラーメッセージを表示 
                ?>
                    <p class="article__errMessage"><?= htmlentities($errors['csrf_token'], ENT_QUOTES) ?></p>
                <?php endif; ?>
            </form>
            <div class="article__board">
                <?php
                if (!empty($errors['page'])) {
                    echo '<p class="article__errMessage">', htmlentities($errors['page'], ENT_QUOTES), '</p>';
                } else {
                    // 投稿内容を表示する
                    foreach ($posts as $post) {
                        echo '<p class="article__boardTitle">', htmlentities($post['title'], ENT_QUOTES, 'UTF-8', false), '</p>';
                        echo '<p class="article__boardComments">', htmlentities($post['comments'], ENT_QUOTES, 'UTF-8', false), '</p>';
                        echo '<p class="article__boardPosterName">投稿者：', htmlentities($post['name'], ENT_QUOTES, 'UTF-8', false), '</p>';
                        echo '<p class="article__postTime">投稿時間：', htmlentities(date('Y年m月d日 H:i', strtotime($post['posted_at'])), ENT_QUOTES, 'UTF-8', false), '</p>';
                    }
                }
                ?>
                <section class="article__pageNumberLinks">
                    <?php if ($pageNumber !== 1) : ?>
                        <a href="board.php" class="article__firstPageLink">&lt;&lt;</a>
                        <a href="board.php?page=<?= $previousPageNumber ?>" class="article__previousPageLink">&lt;</a>
                    <?php else : ?>
                        <a href="board.php" class="article__firstPageLink hidden">&lt;&lt;</a>
                        <a href="board.php?page=<?= $previousPageNumber ?>" class="article__previousPageLink hidden">&lt;</a>
                    <?php endif; ?>
                    <?php
                    for ($i = $start; $i <= $end; $i++) {
                        if ($i === $pageNumber) {
                            echo '<a class="article__pageNumberLink notLink">', $i, '</a>';

                        } else {
                            echo '<a href="board.php?page=', $i, '" class="article__pageNumberLink">', $i, '</a>';

                        }
                    }
                    ?>
                    <?php if ($pageNumber != $lastPageNumber) : ?>
                        <a href="board.php?page=<?= $nextPageNumber ?>" class="article__nextPageLink">&gt;</a>
                        <a href="board.php?page=<?= $lastPageNumber ?>" class="article__lastPageLink">&gt;&gt;</a>
                    <?php else : ?>
                        <a href="board.php?page=<?= $nextPageNumber ?>" class="article__nextPageLink hidden">&gt;</a>
                        <a href="board.php?page=<?= $lastPageNumber ?>" class="article__lastPageLink hidden">&gt;&gt;</a>
                    <?php endif; ?>
                </section>
            </div>
        </article><!-- article -->

        <aside id="aside" class="aside">
            <section class="aside__item">
                <h3 class="aside__title">人気の記事</h3>
                <ul class="aside__lists">
                    <li class="aside__listItem"><a href="#" class="aside__link">PHP おすすめ本</a></li>
                    <li class="aside__listItem"><a href="#" class="aside__link">PHP MyAdminの使い方</a></li>
                    <li class="aside__listItem"><a href="#" class="aside__link">今人気のエディタ Top5</a></li>
                    <li class="aside__listItem"><a href="#" class="aside__link">HTMLの基礎</a></li>
                </ul>
            </section>
            <section class="aside__item">
                <h3 class="aside__title">オススメリンク</h3>
                <ul class="aside__lists">
                    <li class="aside__listItem"><a href="#" class="aside__link">インターノウス株式会社</a></li>
                    <li class="aside__listItem"><a href="#" class="aside__link">XAMPPのダウンロード</a></li>
                    <li class="aside__listItem"><a href="#" class="aside__link">Eclipseのダウンロード</a></li>
                    <li class="aside__listItem"><a href="#" class="aside__link">Bracketsのダウンロード</a></li>
                </ul>
            </section>
            <section class="aside__item">
                <h3 class="aside__title">カテゴリ</h3>
                <ul class="aside__lists">
                    <li class="aside__listItem"><a href="#" class="aside__link">HTML</a></li>
                    <li class="aside__listItem"><a href="#" class="aside__link">PHP</a></li>
                    <li class="aside__listItem"><a href="#" class="aside__link">MySQL</a></li>
                    <li class="aside__listItem"><a href="#" class="aside__link">JavaScript</a></li>
                </ul>
            </section>
        </aside><!-- aside -->
    </main>
</body>

</html>