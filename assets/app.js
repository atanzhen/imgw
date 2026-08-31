(function() {
    'use strict';

    var dropZone        = document.getElementById('dropZone');
    var fileInput       = document.getElementById('fileInput');
    var uploadQueue     = document.getElementById('uploadQueue');
    var queueList       = document.getElementById('queueList');
    var btnStart        = document.getElementById('btnStartUpload');
    var btnClear        = document.getElementById('btnClearQueue');
    var uploadStats     = document.getElementById('uploadStats');
    var uploadResults   = document.getElementById('uploadResults');
    var resultTextarea  = document.getElementById('resultTextarea');
    var resultCount     = document.getElementById('resultCount');
    var btnChangePass   = document.getElementById('btnChangePass');
    var toastContainer  = document.getElementById('toastContainer');

    var queue = [];
    var results = [];
    var currentFormat = 'url';
    var isUploading = false;

    /* ========== Toast 悬浮提示 ========== */
    function showToast(msg, type) {
        type = type || 'success';
        var icons = { success: '✅', error: '❌', warning: '⚠️', info: '📋' };
        var toast = document.createElement('div');
        toast.className = 'toast-item toast-' + type;
        toast.innerHTML = '<span class="toast-icon">' + (icons[type] || '✅') +
            '</span><span class="toast-msg">' + escapeHtml(msg) + '</span>';
        toastContainer.appendChild(toast);
        requestAnimationFrame(function() { toast.classList.add('show'); });
        setTimeout(function() {
            toast.classList.remove('show');
            toast.classList.add('hide');
            setTimeout(function() { toast.remove(); }, 300);
        }, 1000);
    }

    /* ========== 剪贴板 ========== */
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

    /* ========== 拖拽 / 选择 / 粘贴 ========== */
    dropZone.addEventListener('click', function() { fileInput.click(); });

    dropZone.addEventListener('dragover', function(e) {
        e.preventDefault();
        dropZone.classList.add('dragover');
    });
    dropZone.addEventListener('dragleave', function() {
        dropZone.classList.remove('dragover');
    });
    dropZone.addEventListener('drop', function(e) {
        e.preventDefault();
        dropZone.classList.remove('dragover');
        var files = [];
        for (var i = 0; i < e.dataTransfer.files.length; i++) {
            if (e.dataTransfer.files[i].type.indexOf('image/') === 0) {
                files.push(e.dataTransfer.files[i]);
            }
        }
        addToQueue(files);
    });

    fileInput.addEventListener('change', function() {
        addToQueue(Array.from(fileInput.files));
        fileInput.value = '';
    });

    document.addEventListener('paste', function(e) {
        var items = e.clipboardData && e.clipboardData.items;
        if (!items) return;
        var files = [];
        for (var i = 0; i < items.length; i++) {
            if (items[i].type.indexOf('image/') === 0) {
                var f = items[i].getAsFile();
                if (f) {
                    var ext = f.type.split('/')[1] || 'png';
                    files.push(new File([f], 'pasted_' + Date.now() + '.' + ext, { type: f.type }));
                }
            }
        }
        if (files.length > 0) {
            addToQueue(files);
            showToast('已添加 ' + files.length + ' 张粘贴图片', 'info');
        }
    });

    /* ========== 队列管理 ========== */
    function addToQueue(files) {
        if (!files || files.length === 0) return;

        var hasDone = queue.some(function(i) { return i.status === 'done'; });
        if (hasDone || results.length > 0) {
            queue = [];
            results = [];
            currentFormat = 'url';
            resetFormatTabs();
            renderResults();
        }

        files.forEach(function(file) {
            if (file.size > 20 * 1024 * 1024) {
                showToast(file.name + ' 超过20MB限制', 'error');
                return;
            }
            queue.push({
                id: genId(),
                file: file,
                status: 'pending',
                progress: 0,
                result: null
            });
        });
        renderQueue();
    }

    function renderQueue() {
        if (queue.length === 0) {
            uploadQueue.style.display = 'none';
            return;
        }
        uploadQueue.style.display = 'block';
        queueList.innerHTML = '';

        queue.forEach(function(item) {
            var div = document.createElement('div');
            div.className = 'queue-item';
            div.id = 'q-' + item.id;

            var thumbUrl = URL.createObjectURL(item.file);
            var fillClass = '';
            if (item.status === 'done') fillClass = ' done';
            if (item.status === 'error') fillClass = ' error';

            div.innerHTML =
                '<img class="thumb" src="' + thumbUrl + '" alt="">' +
                '<div class="info">' +
                    '<div class="name">' + escapeHtml(item.file.name) + '</div>' +
                    '<div class="size">' + fmtSize(item.file.size) + '</div>' +
                    '<div class="progress-bar"><div class="fill' + fillClass + '" style="width:' + item.progress + '%"></div></div>' +
                '</div>' +
                '<span class="status" style="color:' + statusColor(item.status) + '">' + statusText(item.status) + '</span>' +
                (item.status === 'pending' ? '<button class="remove-btn" data-qid="' + item.id + '">&times;</button>' : '');

            var rmBtn = div.querySelector('.remove-btn');
            if (rmBtn) {
                rmBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    removeFromQueue(rmBtn.dataset.qid);
                });
            }

            queueList.appendChild(div);
        });

        var pending = 0, done = 0, errors = 0, uploading = 0;
        queue.forEach(function(i) {
            if (i.status === 'pending') pending++;
            else if (i.status === 'uploading') uploading++;
            else if (i.status === 'done') done++;
            else if (i.status === 'error') errors++;
        });

        uploadStats.textContent = '共 ' + queue.length + ' 个 | 待上传 ' + pending + ' | 上传中 ' + uploading + ' | 完成 ' + done + ' | 失败 ' + errors;

        // 按钮状态完全由队列真实状态驱动
        if (uploading > 0) {
            btnStart.disabled = true;
            btnStart.textContent = '上传中... (' + uploading + ')';
        } else if (pending > 0) {
            btnStart.disabled = false;
            btnStart.textContent = '开始上传 (' + pending + ')';
        } else {
            btnStart.disabled = true;
            btnStart.textContent = '上传任务完成';
        }
    }

    /* ========== 上传逻辑 ========== */
    btnStart.addEventListener('click', startUpload);
    btnClear.addEventListener('click', function() {
        if (isUploading) return;
        queue = [];
        results = [];
        currentFormat = 'url';
        resetFormatTabs();
        renderQueue();
        renderResults();
    });

    function startUpload() {
        if (isUploading) return;
        var pending = queue.filter(function(i) { return i.status === 'pending'; });
        if (pending.length === 0) return;

        isUploading = true;

        // 批量标记为 uploading 并立即渲染
        pending.forEach(function(item) {
            item.status = 'uploading';
            item.progress = 0;
        });
        renderQueue();

        var idx = 0;
        var concurrency = 3;

        function worker() {
            return new Promise(function(resolve) {
                function next() {
                    if (idx >= pending.length) { resolve(); return; }
                    var item = pending[idx++];
                    uploadOne(item).then(next).catch(next);
                }
                next();
            });
        }

        var workers = [];
        for (var i = 0; i < Math.min(concurrency, pending.length); i++) {
            workers.push(worker());
        }

        Promise.all(workers).then(function() {
            isUploading = false;
            renderQueue();
            renderResults();
            if (results.length > 0) {
                uploadResults.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }).catch(function() {
            isUploading = false;
            renderQueue();
        });
    }

    function uploadOne(item) {
        return new Promise(function(resolve) {
            var formData = new FormData();
            formData.append('file', item.file);

            var xhr = new XMLHttpRequest();
            var hasScrolled = false; // 【恢复】防止进度回调中频繁触发滚动

            xhr.upload.addEventListener('progress', function(e) {
                if (e.lengthComputable) {
                    item.progress = Math.round((e.loaded / e.total) * 100);
                    updateProgress(item);

                    // 【恢复】当文件开始有实际传输时，确保其在可视区域内
                    if (!hasScrolled && item.progress > 0) {
                        hasScrolled = true;
                        scrollQueueTo(item.id);
                    }
                }
            });

            xhr.addEventListener('load', function() {
                if (xhr.status >= 200 && xhr.status < 300) {
                    try {
                        var resp = JSON.parse(xhr.responseText);
                        if (resp.success === true && resp.data) {
                            item.status = 'done';
                            item.progress = 100;
                            item.result = resp.data;
                            results.push(resp.data);
                            updateResultTextarea();
                            
                            // 【新增】每成功一个文件，就自动将结果区域滚动到底部，方便实时查看
                            scrollResultBottom();
                            // 同时确保结果区域在视野内
                            if (uploadResults.style.display !== 'none') {
                                uploadResults.scrollIntoView({ behavior: 'smooth', block: 'end' });
                            }
                        } else {
                            item.status = 'error';
                            showToast(item.file.name + ': ' + (resp.message || '服务器处理失败'), 'error');
                        }
                    } catch(e) {
                        item.status = 'error';
                        showToast(item.file.name + ': 响应数据解析失败', 'error');
                    }
                } else {
                    item.status = 'error';
                    showToast(item.file.name + ': 服务器错误 (' + xhr.status + ')', 'error');
                }
                renderQueue();
                resolve();
            });

            xhr.addEventListener('error', function() {
                item.status = 'error';
                showToast(item.file.name + ': 网络连接失败', 'error');
                renderQueue();
                resolve();
            });

            xhr.addEventListener('abort', function() {
                item.status = 'error';
                showToast(item.file.name + ': 上传已取消', 'warning');
                renderQueue();
                resolve();
            });

            xhr.timeout = 60000;
            xhr.addEventListener('timeout', function() {
                item.status = 'error';
                showToast(item.file.name + ': 上传超时', 'error');
                renderQueue();
                resolve();
            });

            //xhr.open('POST', SITE_URL + '/api.php?action=upload');//修改兼容带端口的
            xhr.open('POST', 'api.php?action=upload');
            xhr.send(formData);
        });
    }

    // 【恢复·优化】智能滚动：仅在元素不在可视区域时才触发滚动，避免抖动
    function scrollQueueTo(id) {
        var el = document.getElementById('q-' + id);
        if (!el) return;

        var container = queueList;
        var elTop = el.offsetTop;
        var elBottom = elTop + el.offsetHeight;
        var cScroll = container.scrollTop;
        var cHeight = container.clientHeight;

        // 只有当元素超出容器可视范围时才滚动
        if (elTop < cScroll || elBottom > cScroll + cHeight) {
            el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }

    function scrollResultBottom() {
        if (resultTextarea) {
            // 强制滚动到文本框最底部
            resultTextarea.scrollTop = resultTextarea.scrollHeight;
        }
    }

    function updateProgress(item) {
        var el = document.getElementById('q-' + item.id);
        if (!el) return;
        var fill = el.querySelector('.progress-bar .fill');
        var status = el.querySelector('.status');
        if (fill) fill.style.width = item.progress + '%';
        if (status) {
            status.textContent = item.progress + '%';
            status.style.color = statusColor(item.status);
        }
    }

    /* ========== 结果输出 ========== */
    function renderResults() {
        if (results.length === 0) {
            uploadResults.style.display = 'none';
            return;
        }
        uploadResults.style.display = 'block';
        updateResultTextarea();
    }

    function updateResultTextarea() {
        if (!resultTextarea) return;
        var lines = results.map(function(r) {
            switch(currentFormat) {
                case 'markdown': return '![' + r.original_name + '](' + r.url + ')';
                case 'html':     return '<img src="' + r.url + '" alt="' + escapeHtml(r.original_name) + '">';
                case 'bbcode':   return '[img]' + r.url + '[/img]';
                default:         return r.url;
            }
        });
        resultTextarea.value = lines.join('\n');
        if (resultCount) resultCount.textContent = results.length + ' 条链接';
        uploadResults.style.display = 'block';
    }

    function resetFormatTabs() {
        document.querySelectorAll('.fmt-tab').forEach(function(t) {
            t.classList.toggle('active', t.dataset.fmt === 'url');
        });
    }

    /* ========== 全局函数 ========== */
    window.switchFormat = function(fmt, btn) {
        currentFormat = fmt;
        btn.parentElement.querySelectorAll('.fmt-tab').forEach(function(t) { t.classList.remove('active'); });
        btn.classList.add('active');
        updateResultTextarea();
    };

    window.copyAll = function(btn) {
        var text = resultTextarea.value;
        if (!text) return;
        var count = results.length;
        var fmtName = { url: 'URL', markdown: 'Markdown', html: 'HTML', bbcode: 'BBCode' }[currentFormat] || currentFormat;

        copyText(text).then(function() {
            if (count === 1) {
                showToast('已复制: ' + text, 'success');
            } else {
                showToast('已复制 ' + count + ' 条 ' + fmtName + ' 链接', 'success');
            }
        }).catch(function() {
            resultTextarea.select();
            showToast('复制失败，请手动 Ctrl+C', 'error');
        });
    };

    function removeFromQueue(id) {
        if (isUploading) return;
        queue = queue.filter(function(i) { return i.id !== id; });
        renderQueue();
    }

    /* ========== 修改密码 ========== */
    var passModal = document.getElementById('passModal');

    if (btnChangePass && passModal) {
        btnChangePass.addEventListener('click', function(e) {
            e.preventDefault();
            document.getElementById('passOld').value = '';
            document.getElementById('passNew').value = '';
            document.getElementById('passConfirm').value = '';
            passModal.style.display = 'flex';
        });

        document.getElementById('passClose').addEventListener('click', function() {
            passModal.style.display = 'none';
        });
        document.getElementById('passCancel').addEventListener('click', function() {
            passModal.style.display = 'none';
        });
        passModal.addEventListener('click', function(e) {
            if (e.target === passModal) passModal.style.display = 'none';
        });

        document.getElementById('passSubmit').addEventListener('click', function() {
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
                    passModal.style.display = 'none';
                } else {
                    showToast(data.message || '修改失败', 'error');
                }
            })
            .catch(function() { showToast('网络错误', 'error'); });
        });
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && passModal) passModal.style.display = 'none';
    });

    /* ========== 工具函数 ========== */
    function fmtSize(b) {
        if (b < 1024) return b + ' B';
        if (b < 1024 * 1024) return (b / 1024).toFixed(1) + ' KB';
        return (b / (1024 * 1024)).toFixed(2) + ' MB';
    }

    function escapeHtml(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function statusText(s) {
        return { pending: '等待中', uploading: '上传中', done: '✓ 完成', error: '✗ 失败' }[s] || s;
    }

    function statusColor(s) {
        return { pending: '#64748b', uploading: '#4f46e5', done: '#10b981', error: '#ef4444' }[s] || '#64748b';
    }

    function genId() {
        return Math.random().toString(36).substr(2, 12);
    }

})();