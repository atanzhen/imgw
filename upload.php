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
require_login();
$base_url = site_url();
$webp_ok = server_supports_webp();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GULU图床 - 上传图片</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <span class="nav-brand">🖼️ GULU图床</span>
            <div class="nav-links">
                <a href="upload.php" class="active">上传</a>
                <a href="admin.php">管理</a>
                <a href="#" id="btnChangePass">修改密码</a>
                <a href="logout.php">退出</a>
            </div>
        </div>
    </nav>

    <div class="container container-upload">
        <div class="upload-area" id="dropZone">
            <div class="upload-icon">📁</div>
            <h2>拖拽图片到此处上传</h2>
            <p>或点击选择文件（支持多文件，单文件最大 20MB）</p>
            <p class="upload-formats">
                支持 JPG / PNG / GIF / WebP / BMP
                <?php echo $webp_ok ? ' · 自动转为 WebP' : '自动压缩' ?>
            </p>
            <input type="file" id="fileInput" multiple accept="image/*" hidden>
        </div>

        <div id="uploadQueue" class="upload-queue" style="display:none;">
            <h3>📤 上传队列</h3>
            <div id="queueList"></div>
            <div class="queue-actions">
                <button id="btnStartUpload" class="btn btn-primary">开始上传</button>
                <button id="btnClearQueue" class="btn btn-secondary">清空队列</button>
                <span id="uploadStats" class="upload-stats"></span>
            </div>
        </div>

        <div id="uploadResults" class="upload-results" style="display:none;">
            <div class="result-top">
                <h3>✅ 上传结果 <span class="result-count" id="resultCount">0 条链接</span></h3>
            </div>
            <div class="format-tabs">
                <button class="fmt-tab active" data-fmt="url" onclick="switchFormat('url', this)">URL</button>
                <button class="fmt-tab" data-fmt="markdown" onclick="switchFormat('markdown', this)">Markdown</button>
                <button class="fmt-tab" data-fmt="html" onclick="switchFormat('html', this)">HTML</button>
                <button class="fmt-tab" data-fmt="bbcode" onclick="switchFormat('bbcode', this)">BBCode</button>
            </div>
            <div class="result-textarea-wrap">
                <textarea id="resultTextarea" class="result-textarea" readonly onclick="this.select()"></textarea>
            </div>
            <div class="result-actions">
                <button class="btn btn-primary copy-all-btn" onclick="copyAll(this)">📋 复制全部链接</button>
            </div>
        </div>
    </div>

    <footer class="footer">
        <p>&copy; <?php echo date('Y') ?> <a href="https://atusu.cn/" target="_blank">ATUSU</a> · GULU图床</p>
    </footer>

    <div id="toastContainer" class="toast-container"></div>

    <div id="passModal" class="modal-overlay" style="display:none;">
        <div class="modal-box">
            <div class="modal-header">
                <h3>🔑 修改密码</h3>
                <span class="modal-x" id="passClose">&times;</span>
            </div>
            <div class="modal-body">
                <div class="pass-field">
                    <label>当前密码</label>
                    <input type="text" id="passOld" placeholder="请输入当前密码" autocomplete="off">
                </div>
                <div class="pass-field">
                    <label>新密码</label>
                    <input type="text" id="passNew" placeholder="至少6位" autocomplete="off">
                </div>
                <div class="pass-field">
                    <label>确认新密码</label>
                    <input type="text" id="passConfirm" placeholder="再次输入新密码" autocomplete="off">
                </div>
                <p class="pass-hint">💡 密码明文显示，请确认周围无人窥视</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" id="passCancel">取消</button>
                <button class="btn btn-primary" id="passSubmit">确认修改</button>
            </div>
        </div>
    </div>

    <script>
        var SITE_URL = <?php echo json_encode($base_url, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    </script>
    <script src="assets/app.js"></script>
</body>
</html>