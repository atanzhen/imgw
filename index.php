<?php

if (!file_exists(__DIR__ . '/data/.install_env_checked')) {
    header('Location: install.php');
    exit;
}

if (isset($_GET['installed'])) {
    session_start();
    $_SESSION['_install_notice'] = true;
}

require_once __DIR__ . '/functions.php';

if (is_logged_in()) {
    header('Location: upload.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['username'] ?? '');
    $pass = trim($_POST['password'] ?? '');

    if ($user === ADMIN_USER && $pass === get_current_password()) {
        $_SESSION['logged_in'] = true;
        header('Location: upload.php');
        exit;
    } else {
        $error = '用户名或密码错误';
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>图床 - 登录</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-body">
    <div class="login-box">
        <h1>🖼️ GULU图床</h1>
        <p class="subtitle">请登录以使用图片管理功能</p>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST" action="">
            <div class="form-group">
                <label>用户名</label>
                <input type="text" name="username" required autofocus
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>密码</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block">登 录</button>
        </form>
    </div>
    <footer class="footer footer-login">
        <p>&copy; <?= date('Y') ?> <a href="https://atusu.cn/" target="_blank">Atusu</a> · GULU图床系统</p>
    </footer>
</body>
</html>