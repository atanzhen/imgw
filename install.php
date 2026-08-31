<?php
/**
 * 首次安装环境检测（独立文件）
 * 仅在未安装时由 index.php 引导访问，不自动写入数据
 */

// 如果已安装，禁止直接访问此文件
if (file_exists(__DIR__ . '/data/.install_env_checked')) {
    header('Location: index.php');
    exit;
}

// 读取配置（不依赖 functions.php，避免触发授权验证）
require_once __DIR__ . '/config.php';

$step = isset($_GET['step']) ? $_GET['step'] : 'check';
$checks = [];
$allPassed = true;

// ==================== 环境检测项 ====================
$items = [
    ['PHP >= 7.4',      version_compare(PHP_VERSION, '7.4.0', '>='), PHP_VERSION],
    ['GD 扩展',         extension_loaded('gd'),                      null],
    ['OpenSSL 扩展',    extension_loaded('openssl'),                 null],
    ['cURL 扩展',       extension_loaded('curl'),                    null],
    ['JSON 扩展',       extension_loaded('json'),                    null],
    ['上传目录可写',     is_dir(UPLOAD_DIR) && is_writable(UPLOAD_DIR), 'uploads'],
    ['数据目录可写',     is_dir(DATA_DIR) && is_writable(DATA_DIR),   'data'],
    ['WebP 支持(可选)',  function_exists('imagewebp'),                null, true],
];

foreach ($items as $item) {
    [$name, $pass, $extra, $optional] = array_pad($item, 4, false);
    if (!$optional && !$pass) $allPassed = false;
    $label = $pass ? '✅ 通过' : ($optional ? '⚠️ 不支持' : '❌ 失败');
    if ($extra) $label .= " ({$extra})";
    $checks[] = ['name' => $name, 'label' => $label, 'pass' => $pass];
}

// ==================== 第二步：手动确认安装 ====================
if ($step === 'confirm' && $allPassed && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // 写入默认凭证
    $cred = [
        'username'   => ADMIN_USER,
        'password'   => ADMIN_PASS,
        'changed_at' => time(),
        '_default'   => true
    ];
    @file_put_contents(CRED_FILE, json_encode($cred, JSON_PRETTY_PRINT), LOCK_EX);
    // 写入安装锁
    @file_put_contents(DATA_DIR . '/.install_env_checked', date('Y-m-d H:i:s'), LOCK_EX);
    
    header('Location: index.php?installed=1');
    exit;
}
?>
<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>安装检测 - GULU图床</title>
<style>
    *{margin:0;padding:0;box-sizing:border-box}
    body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
    .box{background:#fff;border-radius:20px;padding:40px;max-width:520px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,.2)}
    h1{text-align:center;font-size:1.3rem;color:#1e293b;margin-bottom:20px}
    table{width:100%;border-collapse:collapse;margin-bottom:20px}
    td{padding:9px 10px;border-bottom:1px solid #f1f5f9;font-size:.88rem}
    td:first-child{color:#475569;font-weight:600}
    td:last-child{text-align:right}
    .cred{background:#ecfdf5;border:1px solid #a7f3d0;border-radius:12px;padding:18px;margin-bottom:18px;text-align:center}
    .cred h2{font-size:.95rem;color:#065f46;margin-bottom:10px}
    .cred p{font-size:1.05rem;font-weight:700;color:#047857;margin:4px 0}
    .cred span{font-size:.78rem;color:#6b7280;font-weight:400}
    .fail{background:#fef2f2;border:1px solid #fecaca;border-radius:12px;padding:18px;text-align:center;color:#991b1b;font-size:.9rem;line-height:1.7;margin-bottom:18px}
    .btn{display:inline-block;padding:11px 30px;background:#4f46e5;color:#fff;border:none;border-radius:10px;font-size:.9rem;font-weight:600;text-decoration:none;cursor:pointer}
    .btn:hover{background:#4338ca}
    .btn-outline{background:transparent;border:2px solid #4f46e5;color:#4f46e5}
    .btn-outline:hover{background:#4f46e5;color:#fff}
    .center{text-align:center}
    .hint{text-align:center;margin-top:14px;font-size:.73rem;color:#94a3b8}
    .warn-text{text-align:center;font-size:.82rem;color:#92400e;background:#fef3c7;border:1px solid #fde68a;border-radius:10px;padding:12px;margin-bottom:16px;line-height:1.6}
</style></head><body><div class="box">

<?php if ($step === 'confirm' && $allPassed): ?>
    <!-- 第二步：确认安装 -->
    <h1>📋 创建默认安装配置</h1>
    <div class="cred">
        <h2>即将创建以下默认管理员账号</h2>
        <p><span>用户名：</span><?= htmlspecialchars(ADMIN_USER) ?></p>
        <p><span>密　码：</span><?= htmlspecialchars(ADMIN_PASS) ?></p>
    </div>
    <div class="warn-text">
        ⚠️ 点击下方按钮后，系统将写入默认凭证并完成初始化。<br>
        请确认已记录上方账号密码，完成后将跳转至登录页。
    </div>
    <form method="POST" action="?step=confirm">
        <div class="center">
            <button type="submit" class="btn">✅ 确认安装并继续</button>
        </div>
    </form>
    <div class="center" style="margin-top:12px">
        <a href="?" class="btn btn-outline">返回重新检测</a>
    </div>

<?php else: ?>
    <!-- 第一步：环境检测 -->
    <h1>🔍 首次访问 · 服务器环境检测</h1>
    <table>
        <?php foreach ($checks as $c): ?>
        <tr><td><?= htmlspecialchars($c['name']) ?></td><td><?= htmlspecialchars($c['label']) ?></td></tr>
        <?php endforeach; ?>
    </table>

    <?php if ($allPassed): ?>
        <div class="center">
            <a href="?step=confirm" class="btn">下一步：查看默认账号密码</a>
        </div>
        <div class="hint">检测通过，请点击下一步完成安装</div>
    <?php else: ?>
        <div class="fail">❌ <strong>环境检测未通过</strong><br>请修复上方标红项目后刷新重试</div>
        <div class="center"><a href="?" class="btn btn-outline">重新检测</a></div>
    <?php endif; ?>
<?php endif; ?>

</div></body></html>