<?php

// 默认管理员账号密码
define('ADMIN_USER', 'admin');
define('ADMIN_PASS', 'admin888');

// 上传目录
define('UPLOAD_DIR', __DIR__ . '/uploads');
define('UPLOAD_URL', '/uploads');

// 数据目录
define('DATA_DIR', __DIR__ . '/data');
define('META_FILE', DATA_DIR . '/images.json');
define('CRED_FILE', DATA_DIR . '/credentials.json');

// 分页每页数量
define('PAGE_SIZE', 20);

// 上传限制
define('MAX_FILE_SIZE', 20 * 1024 * 1024);
define('ALLOWED_TYPES', array('image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp'));

// 压缩配置
define('COMPRESS_QUALITY', 75);
define('COMPRESS_MAX_WIDTH', 2560);
define('COMPRESS_MAX_HEIGHT', 2560);


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 确保目录存在
$dirs = array(UPLOAD_DIR, DATA_DIR);
foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}
if (!file_exists(META_FILE)) {
    @file_put_contents(META_FILE, json_encode(array(), JSON_PRETTY_PRINT));
}