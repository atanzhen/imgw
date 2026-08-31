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

$page = max(1, intval($_GET['page'] ?? 1));
$meta = load_meta();
$total = count($meta);
$total_pages = max(1, ceil($total / PAGE_SIZE));
$page = min($page, $total_pages);
$offset = ($page - 1) * PAGE_SIZE;
$items = array_slice($meta, $offset, PAGE_SIZE);
$base_url = site_url();
$total_size = total_images_size();


$disk_free  = @disk_free_space($_SERVER['DOCUMENT_ROOT']);
$disk_total = @disk_total_space($_SERVER['DOCUMENT_ROOT']);
$disk_used  = $disk_total - $disk_free;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>图床 - 图片管理</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>

        .batch-toolbar {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            padding: 12px 16px;
            margin-bottom: 16px;
            background: #f0f4ff;
            border: 1px solid #c7d2fe;
            border-radius: 10px;
        }
        .batch-toolbar .batch-info {
            font-size: .88rem;
            color: #4338ca;
            font-weight: 600;
            margin-right: auto;
        }
        .batch-toolbar .btn {
            font-size: .82rem;
            padding: 6px 14px;
        }

        #btnDeselectAll {
            display: none;
        }
        #btnDeselectAll.visible {
            display: inline-flex;
        }

        .image-card {
            position: relative;
        }
        .card-checkbox {
            position: absolute;
            top: 8px;
            left: 8px;
            z-index: 5;
            width: 22px;
            height: 22px;
            cursor: pointer;
            accent-color: #4f46e5;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,.3);
        }
        .image-card.selected {
            outline: 3px solid #c5c1ff;

        }
        .image-card.selected .image-thumb::after {
            content: '';
            position: absolute;
            inset: 0;
            background: rgba(79,70,229,.12);
            pointer-events: none;
        }
        .image-thumb {
            position: relative;
            overflow: hidden;
        }
        .image-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .toast-msg {
            word-break: break-all;
        }
        .toast-link {
            font-size: .78rem;
            opacity: .85;
            display: block;
            margin-top: 2px;
        }

        .page-info {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: .85rem;
            color: #64748b;
            margin-left: 8px;
        }
        .page-first, .page-last {
            font-weight: 600;
        }

        .disk-bar-wrap {
            display: flex;
            align-items: center;
            gap: 10px;
            /*margin-top: 4px;*/
        }
        .disk-bar {
            flex: 1;
            height: 6px;
            background: #e2e8f0;
            border-radius: 3px;
            overflow: hidden;
            max-width: 160px;
        }
        .disk-bar-fill {
            height: 100%;
            border-radius: 3px;
            transition: width .3s;
        }

        .image-card.removing {
            opacity: 0;
            transform: scale(.92);
            pointer-events: none;
            transition: opacity .3s ease, transform .3s ease;
        }

        @media (max-width: 640px) {
            .admin-stats {
                display: grid !important;
                grid-template-columns: repeat(3, 1fr) !important;
                gap: 6px !important;
                flex-direction: row !important;
                align-items: stretch !important;
                width: 100%;
            }
            .stat-item {
                display: flex !important;
                flex-direction: column !important;
                align-items: center !important;
                justify-content: center !important;
                text-align: center !important;
                background: #f8fafc;
                border-radius: 8px;
                padding: 8px 4px !important;
                width: auto !important;
            }
            .stat-icon {
                display: none !important;
            }
            .stat-label {
                font-size: 0.7rem !important;
                line-height: 1.2 !important;
                margin-bottom: 2px !important;
                white-space: nowrap !important;
            }
            .stat-value {
                font-size: 0.75rem !important;
                line-height: 1.2 !important;
                font-weight: 700 !important;
                white-space: nowrap !important;
            }
            .disk-bar-wrap {
                margin-top: 2px;
                gap: 4px;
                justify-content: center;
            }
            .disk-bar {
                max-width: 60px;
                height: 3px;
            }
            #diskPctText {
                font-size: 0.6rem !important;
            }

            .image-grid {
                grid-template-columns: 1fr !important;
                gap: 12px !important;
            }
            .image-card {
                display: grid !important;
                grid-template-columns: 100px 1fr !important;
                grid-template-rows: auto auto !important;
                gap: 0 12px !important;
                padding: 10px !important;
                align-items: center !important;
            }
            .image-thumb {
                width: 100px !important;
                height: 100px !important;
                aspect-ratio: 1 / 1 !important;
                border-radius: 8px !important;
                flex-shrink: 0 !important;
                grid-row: 1 / -1 !important;
            }
            .image-info {
                display: flex !important;
                flex-direction: column !important;
                justify-content: center !important;
                min-width: 0 !important;
            }
            .image-name {
                font-size: .85rem !important;
                margin-bottom: 4px !important;
            }
            .image-meta {
                font-size: .72rem !important;
                margin-bottom: 2px !important;
            }
            .image-date {
                font-size: .7rem !important;
                margin-bottom: 6px !important;
            }
            .image-actions {
                display: flex !important;
                gap: 6px !important;
                margin-top: 0 !important;
            }
            .image-actions .btn {
                padding: 4px 10px !important;
                font-size: .75rem !important;
                white-space: nowrap !important;
                flex-shrink: 0 !important;
            }
            .card-checkbox {
                top: 6px;
                left: 6px;
                width: 18px;
                height: 18px;
            }

            .btn,
            .page-link,
            .batch-toolbar .btn,
            .nav-links a,
            .batch-info {
                white-space: nowrap !important;
                flex-shrink: 0;
            }

            .batch-toolbar {
                padding: 8px 10px;
                gap: 6px;
                overflow-x: auto;
                flex-wrap: nowrap;
            }
            .batch-toolbar .btn {
                padding: 5px 10px;
                font-size: .78rem;
            }

            .page-first,
            .page-last,
            .page-info,
            .page-dots {
                display: none !important;
            }
            .pagination {
                gap: 4px;
            }
            .page-link {
                padding: 6px 10px;
                font-size: .82rem;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <span class="nav-brand">🖼 GULU图床</span>
            <div class="nav-links">
                <a href="upload.php">上传</a>
                <a href="admin.php" class="active">管理</a>
                <a href="#" id="btnChangePass">修改密码</a>
                <a href="logout.php">退出</a>
            </div>
        </div>
    </nav>

    <div class="container container-admin">
        <div class="admin-header">
            <div>
                <h2>图片管理</h2>
                <div class="admin-stats">
                    <span class="stat-item">
                        <span class="stat-icon">🖼️</span>
                        <span class="stat-label">图片总数</span>
                        <span class="stat-value" id="statTotal"><?= $total ?> 张</span>
                    </span>
                    <span class="stat-item">
                        <span class="stat-icon">💾</span>
                        <span class="stat-label">占用空间</span>
                        <span class="stat-value" id="statSize"><?= format_size($total_size) ?></span>
                    </span>
                    <span class="stat-item">
                        <span class="stat-icon">🖥️</span>
                        <span class="stat-label">磁盘剩余</span>
                        <span class="stat-value" id="statDisk">
                            <?= $disk_free !== false ? format_size($disk_free) : '未知' ?>
                        </span>
                        <?php if ($disk_free !== false && $disk_total > 0): ?>
                            <span class="disk-bar-wrap">
                                <span class="disk-bar">
                                    <?php
                                        $used_pct = round(($disk_used / $disk_total) * 100, 1);
                                        $bar_color = $used_pct > 90 ? '#ef4444' : ($used_pct > 70 ? '#f59e0b' : '#22c55e');
                                    ?>
                                    <span class="disk-bar-fill" id="diskBarFill" style="width:<?= $used_pct ?>%;background:<?= $bar_color ?>"></span>
                                </span>
                                <span id="diskPctText" style="/*font-size:.75rem;*/color:#64748b;margin-left: 5px;font-weight: 700;"><?= $used_pct ?>% 已用</span>
                            </span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
        </div>

        <?php if (empty($items)): ?>
            <div class="empty-state" id="emptyState">
                <p>📭 暂无图片，<a href="upload.php">去上传</a></p>
            </div>
        <?php else: ?>

            <div class="batch-toolbar">
                <span class="batch-info" id="batchInfo">已选 0 张</span>
                <button class="btn btn-secondary btn-sm" id="btnSelectAll">✅ 全选本页</button>
                <button class="btn btn-secondary btn-sm" id="btnDeselectAll">❎ 取消全选</button>
                <button class="btn btn-danger btn-sm" id="btnBatchDelete" disabled>🗑️ 批量删除</button>
            </div>

            <div class="image-grid" id="imageGrid">
                <?php foreach ($items as $item): ?>
                    <div class="image-card"
                         data-id="<?= htmlspecialchars($item['id']) ?>"
                         data-url="<?= htmlspecialchars($item['url']) ?>"
                         data-name="<?= htmlspecialchars($item['original_name']) ?>"
                         data-size="<?= intval($item['compressed_size']) ?>">
                        <input type="checkbox" class="card-checkbox" data-id="<?= htmlspecialchars($item['id']) ?>" title="选中">
                        <div class="image-thumb" data-action="preview">
                            <img src="<?= htmlspecialchars($item['url']) ?>"
                                 alt="<?= htmlspecialchars($item['original_name']) ?>"
                                 loading="lazy">
                        </div>
                        <div class="image-info">
                            <p class="image-name" title="<?= htmlspecialchars($item['original_name']) ?>">
                                <?= htmlspecialchars(mb_strimwidth($item['original_name'], 0, 24, '...')) ?>
                            </p>
                            <p class="image-meta">
                                <?= format_size($item['original_size']) ?>
                                → <?= format_size($item['compressed_size']) ?>
                                <span class="compress-ratio">
                                    (省 <?= round((1 - $item['compressed_size'] / max(1, $item['original_size'])) * 100) ?>%)
                                </span>
                            </p>
                            <p class="image-date"><?= date('Y-m-d H:i', $item['timestamp']) ?></p>
                        </div>
                        <div class="image-actions">
                            <button class="btn btn-sm btn-info" data-action="copy">📋 复制链接</button>
                            <button class="btn btn-sm btn-danger" data-action="delete">🗑️ 删除</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=1" class="page-link page-first">首页</a>
                        <a href="?page=<?= $page - 1 ?>" class="page-link">« 上一页</a>
                    <?php endif; ?>

                    <?php
                    $start = max(1, $page - 2);
                    $end = min($total_pages, $page + 2);
                    if ($start > 1): ?>
                        <span class="page-dots">...</span>
                    <?php endif; ?>

                    <?php for ($i = $start; $i <= $end; $i++): ?>
                        <a href="?page=<?= $i ?>" class="page-link <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                    <?php endfor; ?>

                    <?php if ($end < $total_pages): ?>
                        <span class="page-dots">...</span>
                    <?php endif; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $page + 1 ?>" class="page-link">下一页 »</a>
                        <a href="?page=<?= $total_pages ?>" class="page-link page-last">尾页</a>
                    <?php endif; ?>

                    <span class="page-info">共 <?= $total_pages ?> 页</span>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <footer class="footer">
        <p>&copy; <?= date('Y') ?> <a href="https://atusu.cn/" target="_blank">ATUSU</a> · GULU图床系统</p>
    </footer>

    <div id="toastContainer" class="toast-container"></div>

    <div id="previewModal" class="preview-overlay" style="display:none;">
        <div class="preview-box">
            <span class="preview-close" id="previewClose">&times;</span>
            <img id="previewImg" src="" alt="预览">
            <div class="preview-info">
                <span id="previewName" class="preview-filename"></span>
            </div>
        </div>
    </div>

    <div id="deleteModal" class="modal-overlay" style="display:none;">
        <div class="modal-box modal-delete">
            <div class="modal-header">
                <h3>⚠️ 确认删除</h3>
                <span class="modal-x" id="deleteClose">&times;</span>
            </div>
            <div class="modal-body">
                <div class="delete-body">
                    <div class="delete-thumb-wrap">
                        <img id="deleteThumb" src="" alt="" class="delete-thumb">
                    </div>
                    <p class="delete-name" id="deleteName"></p>
                    <p class="delete-warn">此操作不可恢复，确定要删除吗？</p>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" id="deleteCancelBtn">取消</button>
                <button class="btn btn-danger" id="deleteConfirmBtn">🗑️ 确认删除</button>
            </div>
        </div>
    </div>

    <div id="batchModal" class="modal-overlay" style="display:none;">
        <div class="modal-box modal-delete">
            <div class="modal-header">
                <h3>⚠️ 批量删除确认</h3>
                <span class="modal-x" id="batchClose">&times;</span>
            </div>
            <div class="modal-body">
                <div class="delete-body">
                    <p class="delete-name" id="batchCount" style="font-size:1.1rem;font-weight:700;"></p>
                    <p class="delete-warn">此操作不可恢复，确定要批量删除吗？</p>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" id="batchCancelBtn">取消</button>
                <button class="btn btn-danger" id="batchConfirmBtn">🗑️ 确认批量删除</button>
            </div>
        </div>
    </div>

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
    (function() {
        'use strict';

        var toastContainer = document.getElementById('toastContainer');

        function showToast(msg, type, sub) {
            type = type || 'success';
            var icons = { success: '', error: '❌', warning: '⚠️', info: '📋' };
            var toast = document.createElement('div');
            toast.className = 'toast-item toast-' + type;
            var html = '<span class="toast-icon">' + (icons[type] || '') + '</span>';
            html += '<span class="toast-msg">' + escapeHtml(msg);
            if (sub) html += '<span class="toast-link">' + escapeHtml(sub) + '</span>';
            html += '</span>';
            toast.innerHTML = html;
            toastContainer.appendChild(toast);
            requestAnimationFrame(function() { toast.classList.add('show'); });
            setTimeout(function() {
                toast.classList.remove('show');
                toast.classList.add('hide');
                setTimeout(function() { toast.remove(); }, 300);
            }, 2000);
        }

        function escapeHtml(str) {
            var d = document.createElement('div');
            d.textContent = str;
            return d.innerHTML;
        }

        function copyText(text) {
            if (navigator.clipboard && navigator.clipboard.writeText && window.isSecureContext) {
                return navigator.clipboard.writeText(text).catch(function() { return fbCopy(text); });
            }
            return fbCopy(text);
        }

        function fbCopy(text) {
            return new Promise(function(resolve, reject) {
                var ta = document.createElement('textarea');
                ta.value = text;
                ta.style.cssText = 'position:fixed;top:0;left:0;opacity:0;width:1px;height:1px;border:none;';
                document.body.appendChild(ta);
                ta.focus();
                ta.select();
                ta.setSelectionRange(0, ta.value.length);
                try { document.execCommand('copy'); resolve(); } catch(e) { reject(e); }
                document.body.removeChild(ta);
            });
        }

        // =====================================================
        // ✅ 【修复】修改密码模块移至最前，确保不依赖图片DOM
        // 无论是否有图片，密码弹窗都能正常绑定
        // =====================================================
        var passModal = document.getElementById('passModal');
        function openPassModal() {
            document.getElementById('passOld').value = '';
            document.getElementById('passNew').value = '';
            document.getElementById('passConfirm').value = '';
            passModal.style.display = 'flex';
        }
        function closePassModal() { passModal.style.display = 'none'; }

        var btnChangePass = document.getElementById('btnChangePass');
        if (btnChangePass) {
            btnChangePass.addEventListener('click', function(e) {
                e.preventDefault();
                openPassModal();
            });
        }
        var passCloseBtn = document.getElementById('passClose');
        if (passCloseBtn) passCloseBtn.addEventListener('click', closePassModal);

        var passCancelBtn = document.getElementById('passCancel');
        if (passCancelBtn) passCancelBtn.addEventListener('click', closePassModal);

        if (passModal) {
            passModal.addEventListener('click', function(e) {
                if (e.target === passModal) closePassModal();
            });
        }

        var passSubmitBtn = document.getElementById('passSubmit');
        if (passSubmitBtn) {
            passSubmitBtn.addEventListener('click', function() {
                var oldP = document.getElementById('passOld').value.trim();
                var newP = document.getElementById('passNew').value.trim();
                var cfP  = document.getElementById('passConfirm').value.trim();
                if (!oldP || !newP || !cfP) { showToast('请填写所有字段', 'warning'); return; }
                if (newP.length < 6) { showToast('新密码至少6位', 'warning'); return; }
                if (newP !== cfP) { showToast('两次新密码不一致', 'warning'); return; }
                fetch('api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'change_password', old_password: oldP, new_password: newP }),
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        showToast('密码修改成功', 'success');
                        closePassModal();
                    } else {
                        showToast(data.message || '修改失败', 'error');
                    }
                })
                .catch(function() { showToast('网络错误', 'error'); });
            });
        }
        // =====================================================
        // ✅ 修改密码模块结束，以下为原有图片管理逻辑
        // =====================================================

        var previewModal = document.getElementById('previewModal');
        var previewImg   = document.getElementById('previewImg');
        var previewName  = document.getElementById('previewName');

        function openPreview(url, name) {
            previewImg.src = url;
            previewName.textContent = name;
            previewModal.style.display = 'flex';
        }
        function closePreview() {
            previewModal.style.display = 'none';
            previewImg.src = '';
        }
        var previewCloseBtn = document.getElementById('previewClose');
        if (previewCloseBtn) previewCloseBtn.addEventListener('click', closePreview);
        if (previewModal) {
            previewModal.addEventListener('click', function(e) {
                if (e.target === previewModal) closePreview();
            });
        }

        function refreshStatsSilently() {
            fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'get_stats' })
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.success) return;
                var s = data.data;
                var elTotal = document.getElementById('statTotal');
                if (elTotal) elTotal.textContent = s.total + ' 张';
                var elSize = document.getElementById('statSize');
                if (elSize) elSize.textContent = s.total_size_formatted;
                var elDisk = document.getElementById('statDisk');
                if (elDisk) elDisk.textContent = s.disk_free_formatted;
                var barFill = document.getElementById('diskBarFill');
                var pctText = document.getElementById('diskPctText');
                if (barFill && pctText && s.disk_total > 0) {
                    var usedPct = Math.round(((s.disk_total - s.disk_free) / s.disk_total) * 1000) / 10;
                    var color = usedPct > 90 ? '#ef4444' : (usedPct > 70 ? '#f59e0b' : '#22c55e');
                    barFill.style.width = usedPct + '%';
                    barFill.style.background = color;
                    pctText.textContent = usedPct + '% 已用';
                }
            })
            .catch(function() {});
        }

        function silentDeleteCard(card) {
            var id = card.dataset.id;
            card.classList.add('removing');
            fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete', id: id })
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    setTimeout(function() {
                        card.remove();
                        var remaining = document.querySelectorAll('.image-card:not(.removing)');
                        if (remaining.length === 0) {
                            var emptyEl = document.getElementById('emptyState');
                            if (emptyEl) emptyEl.style.display = '';
                            else location.reload();
                        }
                        refreshStatsSilently();
                        updateBatchUI();
                    }, 320);
                } else {
                    card.classList.remove('removing');
                    showToast('删除失败: ' + data.message, 'error');
                }
            })
            .catch(function() {
                card.classList.remove('removing');
                showToast('网络错误', 'error');
            });
        }

        var deleteModal = document.getElementById('deleteModal');
        var pendingDeleteCard = null;

        function openDeleteModal(card) {
            pendingDeleteCard = card;
            document.getElementById('deleteThumb').src = card.dataset.url;
            document.getElementById('deleteName').textContent = card.dataset.name;
            deleteModal.style.display = 'flex';
        }
        function closeDeleteModal() {
            deleteModal.style.display = 'none';
            pendingDeleteCard = null;
        }
        var deleteCloseBtn = document.getElementById('deleteClose');
        if (deleteCloseBtn) deleteCloseBtn.addEventListener('click', closeDeleteModal);
        var deleteCancelBtn = document.getElementById('deleteCancelBtn');
        if (deleteCancelBtn) deleteCancelBtn.addEventListener('click', closeDeleteModal);
        if (deleteModal) {
            deleteModal.addEventListener('click', function(e) {
                if (e.target === deleteModal) closeDeleteModal();
            });
        }
        var deleteConfirmBtn = document.getElementById('deleteConfirmBtn');
        if (deleteConfirmBtn) {
            deleteConfirmBtn.addEventListener('click', function() {
                if (!pendingDeleteCard) return;
                var card = pendingDeleteCard;
                closeDeleteModal();
                silentDeleteCard(card);
            });
        }

        var checkboxes     = document.querySelectorAll('.card-checkbox');
        var batchInfo      = document.getElementById('batchInfo');
        var btnBatchDel    = document.getElementById('btnBatchDelete');
        var btnSelectAll   = document.getElementById('btnSelectAll');
        var btnDeselectAll = document.getElementById('btnDeselectAll');
        var batchModal     = document.getElementById('batchModal');
        var selectedIds    = [];
        var isAllSelected  = false;

        function updateBatchUI() {
            selectedIds = [];
            document.querySelectorAll('.card-checkbox').forEach(function(cb) {
                if (cb.checked) selectedIds.push(cb.dataset.id);
                var card = cb.closest('.image-card');
                if (card) card.classList.toggle('selected', cb.checked);
            });
            var count = selectedIds.length;
            if (batchInfo) batchInfo.textContent = '已选 ' + count + ' 张';
            if (btnBatchDel) {
                btnBatchDel.disabled = count === 0;
                btnBatchDel.textContent = count > 0 ? '🗑️ 批量删除 (' + count + ')' : '🗑️ 批量删除';
            }
            if (btnDeselectAll) {
                if (isAllSelected) btnDeselectAll.classList.add('visible');
                else btnDeselectAll.classList.remove('visible');
            }
        }

        checkboxes.forEach(function(cb) {
            cb.addEventListener('change', function() {
                if (!this.checked && isAllSelected) isAllSelected = false;
                updateBatchUI();
            });
            cb.addEventListener('click', function(e) { e.stopPropagation(); });
        });

        if (btnSelectAll) {
            btnSelectAll.addEventListener('click', function() {
                document.querySelectorAll('.card-checkbox').forEach(function(cb) { cb.checked = true; });
                isAllSelected = true;
                updateBatchUI();
            });
        }

        if (btnDeselectAll) {
            btnDeselectAll.addEventListener('click', function() {
                document.querySelectorAll('.card-checkbox').forEach(function(cb) { cb.checked = false; });
                isAllSelected = false;
                updateBatchUI();
            });
        }

        if (btnBatchDel) {
            btnBatchDel.addEventListener('click', function() {
                if (selectedIds.length === 0) return;
                var batchCountEl = document.getElementById('batchCount');
                if (batchCountEl) batchCountEl.textContent = '即将删除 ' + selectedIds.length + ' 张图片';
                if (batchModal) batchModal.style.display = 'flex';
            });
        }

        function closeBatchModal() { if (batchModal) batchModal.style.display = 'none'; }
        var batchCloseBtn = document.getElementById('batchClose');
        if (batchCloseBtn) batchCloseBtn.addEventListener('click', closeBatchModal);
        var batchCancelBtn = document.getElementById('batchCancelBtn');
        if (batchCancelBtn) batchCancelBtn.addEventListener('click', closeBatchModal);
        if (batchModal) {
            batchModal.addEventListener('click', function(e) {
                if (e.target === batchModal) closeBatchModal();
            });
        }

        var batchConfirmBtn = document.getElementById('batchConfirmBtn');
        if (batchConfirmBtn) {
            batchConfirmBtn.addEventListener('click', function() {
                if (selectedIds.length === 0) return;
                closeBatchModal();
                showToast('正在删除 ' + selectedIds.length + ' 张图片...', 'info');
                fetch('api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'batch_delete', ids: selectedIds }),
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        showToast('成功删除 ' + data.deleted + ' 张图片', 'success');
                        selectedIds.forEach(function(id) {
                            var card = document.querySelector('.image-card[data-id="' + id + '"]');
                            if (card) {
                                card.classList.add('removing');
                                setTimeout(function() { card.remove(); }, 320);
                            }
                        });
                        isAllSelected = false;
                        setTimeout(function() {
                            var remaining = document.querySelectorAll('.image-card:not(.removing)');
                            if (remaining.length === 0) location.reload();
                            else { refreshStatsSilently(); updateBatchUI(); }
                        }, 350);
                    } else {
                        showToast('批量删除失败: ' + data.message, 'error');
                    }
                })
                .catch(function() { showToast('网络错误', 'error'); });
            });
        }

        // ✅ 安全绑定图片卡片事件（无图片时自动跳过，不影响后续代码）
        document.querySelectorAll('.image-card').forEach(function(card) {
            var url  = card.dataset.url;
            var name = card.dataset.name;

            var previewBtn = card.querySelector('[data-action="preview"]');
            if (previewBtn) {
                previewBtn.addEventListener('click', function() { openPreview(url, name); });
            }

            var copyBtn = card.querySelector('[data-action="copy"]');
            if (copyBtn) {
                copyBtn.addEventListener('click', function() {
                    copyText(url).then(function() {
                        showToast('✅ 链接已复制', 'success', url);
                    }).catch(function() {
                        showToast('❌ 复制失败，请手动复制', 'error');
                    });
                });
            }

            var delBtn = card.querySelector('[data-action="delete"]');
            if (delBtn) {
                delBtn.addEventListener('click', function() { openDeleteModal(card); });
            }
        });

        // ESC 关闭所有弹窗
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closePreview();
                closeDeleteModal();
                closeBatchModal();
                closePassModal();
            }
        });

    })();
    </script>
</body>
<?php if (!empty($_SESSION['_lic_activate_msg'])): ?>
 
<!-- <div style="position:fixed;top:20px;left:50%;transform:translateX(-50%);background:#dcfce7;color:#166534;padding:12px 24px;border-radius:10px;font-size:.95rem;font-weight:600;z-index:99999;box-shadow:0 4px 12px rgba(0,0,0,.1);animation:_lic_fadeout 3s forwards">
    <?php echo htmlspecialchars($_SESSION['_lic_activate_msg']); ?> -->
</div>
<style>@keyframes _lic_fadeout{0%,70%{opacity:1}100%{opacity:0;pointer-events:none}}</style>
<?php unset($_SESSION['_lic_activate_msg']); endif; ?>
</html>