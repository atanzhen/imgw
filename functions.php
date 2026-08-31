<?php
require_once __DIR__ . '/config.php';

define('LICENSE_SERVER_URL_ENC', 'VW1Qc31pYExIdj4+eWlrMnZ2Oj51WFdYTU4+az9RaHh+eHRZc3U2XjdTakQ=');
define('LICENSE_APP_KEY',    'dsgsdgshssssgsgsg');
define('LICENSE_SALT',       'sdgdfsgshdyj9f');
define('LICENSE_CONTACT_EMAIL', 'atusu@qq.com');
define('LICENSE_IDENTITY_FILE', __DIR__ . '/data/license_identity.json');
define('LICENSE_ACTIVATED_LOCK', __DIR__ . '/data/.license_activated_lock');


function _safe_get($arr, $key, $default = '') {
    if (is_array($arr) && array_key_exists($key, $arr)) {
        return $arr[$key];
    }
    return $default;
}

function _lic_decrypt_server_url() {
    static $url = null;
    if ($url !== null) return $url;

    $enc = defined('LICENSE_SERVER_URL_ENC') ? LICENSE_SERVER_URL_ENC : '';
    if (empty($enc)) { $url = ''; return $url; }

    try {
        $obfuscated = base64_decode($enc);
        if ($obfuscated === false) { $url = ''; return $url; }

        $b64 = '';
        for ($i = 0; $i < strlen($obfuscated); $i++) {
            $b64 .= chr((ord($obfuscated[$i]) - 7 + 256) % 256);
        }

        $key    = hash('sha256', LICENSE_APP_KEY, true);
        $iv     = substr(hash('sha256', LICENSE_APP_KEY . '_iv', true), 0, 16);
        $cipher = base64_decode($b64);

        if ($cipher === false) { $url = ''; return $url; }

        $decrypted = openssl_decrypt($cipher, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);

        if ($decrypted !== false && filter_var($decrypted, FILTER_VALIDATE_URL)) {
            $url = rtrim($decrypted, '/');
        } else {
            $url = '';
        }
    } catch (Exception $e) {
        $url = '';
    } catch (Throwable $e) {
        $url = '';
    }

    return $url;
}

function _lic_fingerprint() {
    $domain = _safe_get($_SERVER, 'HTTP_HOST', _safe_get($_SERVER, 'SERVER_NAME', 'unknown'));
    $domain = preg_replace('/^www\./i', '', strtolower($domain));
    $ip = _safe_get($_SERVER, 'SERVER_ADDR', '');
    if (empty($ip)) {
        $ip = @gethostbyname($domain);
    }
    if (!$ip || $ip === $domain) $ip = 'unknown';
    return hash('sha256', $domain . '|' . $ip . '|' . LICENSE_SALT);
}

function _lic_identity_read() {
    if (!file_exists(LICENSE_IDENTITY_FILE)) return null;
    $d = json_decode(@file_get_contents(LICENSE_IDENTITY_FILE), true);
    return (is_array($d) && !empty($d['license_key'])) ? $d : null;
}

function _lic_identity_write($key) {
    $dir = dirname(LICENSE_IDENTITY_FILE);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    file_put_contents(LICENSE_IDENTITY_FILE, json_encode(array(
        'license_key' => $key,
        'fingerprint' => _lic_fingerprint(),
        'bound_at'    => time()
    ), JSON_PRETTY_PRINT), LOCK_EX);
    if (function_exists('opcache_invalidate')) {
        @opcache_invalidate(LICENSE_IDENTITY_FILE, true);
    }
    clearstatcache(true, LICENSE_IDENTITY_FILE);
}

function _lic_identity_clear() {
    @unlink(LICENSE_IDENTITY_FILE);
    if (function_exists('opcache_invalidate')) {
        @opcache_invalidate(LICENSE_IDENTITY_FILE, true);
    }
    clearstatcache(true, LICENSE_IDENTITY_FILE);
}

function _lic_has_auto_activated() {
    return file_exists(LICENSE_ACTIVATED_LOCK);
}

function _lic_mark_auto_activated() {
    $dir = dirname(LICENSE_ACTIVATED_LOCK);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    file_put_contents(LICENSE_ACTIVATED_LOCK, _lic_fingerprint() . '|' . time(), LOCK_EX);
    if (function_exists('opcache_invalidate')) {
        @opcache_invalidate(LICENSE_ACTIVATED_LOCK, true);
    }
    clearstatcache(true, LICENSE_ACTIVATED_LOCK);
}

function _lic_api_request($action, $extra = array()) {
    $server_url = _lic_decrypt_server_url();
    if (empty($server_url)) {
        return array('success' => false, 'code' => 'config_error', 'message' => '授权配置异常，无法连接验证服务');
    }

    $params = array_merge(array(
        'action'      => $action,
        'app_key'     => LICENSE_APP_KEY,
        'fingerprint' => _lic_fingerprint(),
        'domain'      => _safe_get($_SERVER, 'HTTP_HOST', ''),
        'server_ip'   => _safe_get($_SERVER, 'SERVER_ADDR', ''),
        'timestamp'   => time(),
    ), $extra);
    ksort($params);
    $params['sign'] = hash('sha256', http_build_query($params) . LICENSE_APP_KEY);

    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_URL            => $server_url,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($params),
        CURLOPT_HTTPHEADER     => array('Content-Type: application/json'),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ));
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($resp === false || $code !== 200) {
        return array('success' => false, 'code' => 'network_error', 'message' => '无法连接授权服务器' . ($err ? "：{$err}" : ''));
    }
    $data = json_decode($resp, true);
    return is_array($data) ? $data : array('success' => false, 'code' => 'parse_error', 'message' => '授权服务器返回异常');
}

function _lic_safe_redirect($msg = '') {
    if ($msg !== '') {
        $_SESSION['_lic_activate_msg'] = $msg;
    }
    $uri = _safe_get($_SERVER, 'REQUEST_URI', '/');
    header('Location: ' . $uri);
    exit;
}

function _lic_verify() {
    static $verified = false;
    if ($verified) return;
    $verified = true;

    $identity = _lic_identity_read();

    if (!$identity) {
        if (!_lic_has_auto_activated()) {
            $result = _lic_api_request('auto_activate');
            if (!empty($result['success'])) {
                $lic_key = isset($result['license_key']) ? $result['license_key'] : '';
                _lic_identity_write($lic_key);
                _lic_mark_auto_activated();
                _lic_safe_redirect('✅ 已自动完成授权激活！');
            }
            _lic_mark_auto_activated();
            $err_code = isset($result['code']) ? $result['code'] : 'error';
            $err_msg  = isset($result['message']) ? $result['message'] : '自动激活失败，请手动输入授权码';
            _lic_render_activation($err_code, $err_msg);
            exit;
        }
        _lic_render_activation('not_found', '授权记录已丢失，请输入授权码重新激活');
        exit;
    }

    $result = _lic_api_request('verify', array('license_key' => $identity['license_key']));

    if (!empty($result['success'])) {
        return;
    }

    $err_code = isset($result['code']) ? $result['code'] : 'error';
    _lic_render_activation($err_code, isset($result['message']) ? $result['message'] : '授权验证失败');
    exit;
}

function _lic_render_activation($error_code = '', $error_msg = '') {
    $content_type = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
    $accept       = isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : '';
    $x_requested  = isset($_SERVER['HTTP_X_REQUESTED_WITH']) ? $_SERVER['HTTP_X_REQUESTED_WITH'] : '';

    $is_api = !empty($x_requested)

        || strpos($content_type, 'application/json') !== false
        || strpos($accept, 'application/json') !== false;

    if ($is_api) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array(
            'success' => false,
            'code' => 'license_invalid',
            'message' => '🔒 ' . $error_msg
        ), JSON_UNESCAPED_UNICODE);
        exit;
    }

    $activate_error = '';
    $show_input = false;
    $icon = '❌';
    $title = '授权状态异常';
    $message = '';
    $box_class = 'msg-err';
    $show_recovery_hint = false;

    $lic_action = isset($_POST['_lic_action']) ? $_POST['_lic_action'] : '';
    $lic_key    = isset($_POST['_lic_key']) ? $_POST['_lic_key'] : '';

    if ($lic_action === 'activate' && !empty($lic_key)) {
        $key = trim($lic_key);
        _lic_identity_clear();

        $result = _lic_api_request('activate', array('license_key' => $key));

        if (!empty($result['success'])) {
            _lic_identity_write($key);
            _lic_mark_auto_activated();
            _lic_safe_redirect('✅ 授权激活成功，已更新为新许可证！');
        }
        $activate_error = isset($result['message']) ? $result['message'] : '激活失败';
        $error_code = isset($result['code']) ? $result['code'] : 'error';
    }

    switch ($error_code) {
        case 'blacklisted':
            $icon = '🚫';
            $title = '设备已被拉黑';
            $message = '您的设备因违规滥用已被永久拉黑，无法自行恢复。请联系管理员处理。';
            $box_class = 'msg-err';
            $show_input = false;
            break;

        case 'disabled':
            $icon = '⛔';
            $title = '授权已被停用';
            $message = '您的授权已被管理员停用，暂不支持使用新许可证激活。请联系管理员恢复授权。';
            $box_class = 'msg-err';
            $show_input = false;
            $show_recovery_hint = true;
            break;

        case 'not_found':
            $icon = '❌';
            $title = '授权记录不存在';
            $message = '授权记录已被删除或丢失。请输入新的授权码重新激活。';
            $box_class = 'msg-err';
            $show_input = true;
            break;

        case 'expired':
            $icon = '⏰';
            $title = '授权已过期';
            $message = '您的授权已过期，请联系管理员续期或使用新的授权码。';
            $box_class = 'msg-warn';
            $show_input = true;
            break;

        case 'network_error':
        case 'parse_error':
        case 'config_error':
            $icon = '⚠️';
            $title = '无法连接授权服务器';
            $message = '无法连接授权服务器进行实时验证，请检查服务器网络连接后刷新重试。';
            $box_class = 'msg-warn';
            $show_input = false;
            break;

        default:
            $icon = '❌';
            $title = '授权状态异常';
            $message = !empty($error_msg) ? $error_msg : '程序需要激活才能使用。';
            $box_class = 'msg-err';
            $show_input = true;
            break;
    }

    http_response_code(403);
    ?>
    <!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?></title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
        .box{background:#fff;border-radius:20px;padding:40px;max-width:480px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,.2)}
        .icon{text-align:center;font-size:3rem;margin-bottom:12px}
        h1{text-align:center;font-size:1.3rem;color:#1e293b;margin-bottom:20px}
        .msg{padding:16px;border-radius:12px;margin-bottom:20px;font-size:.92rem;line-height:1.7;text-align:center}
        .msg-err{background:#fee2e2;color:#991b1b;border:1px solid #fecaca}
        .msg-warn{background:#fef3c7;color:#92400e;border:1px solid #fde68a}
        .contact{text-align:center;padding:16px;background:#f8fafc;border-radius:12px;border:1px solid #e2e8f0;margin-bottom:16px}
        .contact p{font-size:.82rem;color:#64748b;margin-bottom:6px}
        .contact a{color:#4f46e5;font-weight:600;text-decoration:none;font-size:1rem}
        .contact a:hover{text-decoration:underline}
        form{margin-top:16px}
        form label{display:block;font-size:.82rem;font-weight:600;color:#334155;margin-bottom:8px;text-align:center}
        .inp-grp{display:flex;gap:8px}
        .inp-grp input{flex:1;padding:12px 16px;border:2px solid #e2e8f0;border-radius:10px;font-size:.92rem;outline:none}
        .inp-grp input:focus{border-color:#4f46e5}
        .inp-grp button{padding:12px 20px;background:#4f46e5;color:#fff;border:none;border-radius:10px;font-size:.92rem;font-weight:600;cursor:pointer;white-space:nowrap}
        .inp-grp button:hover{background:#4338ca}
        .activate-err{padding:10px 14px;border-radius:8px;margin-bottom:12px;font-size:.85rem;background:#fef2f2;color:#dc2626;border:1px solid #fecaca;text-align:center}
        .hint{text-align:center;margin-top:16px;font-size:.75rem;color:#94a3b8}
        .recovery-hint{text-align:center;margin-top:12px;font-size:.82rem;color:#1e40af;background:#eff6ff;padding:14px;border-radius:10px;border:1px solid #bfdbfe;line-height:1.6}
    </style></head><body><div class="box">
    <div class="icon"><?php echo $icon; ?></div>
    <h1><?php echo htmlspecialchars($title); ?></h1>

    <div class="msg <?php echo $box_class; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>

    <?php if ($activate_error): ?>
        <div class="activate-err">❌ <?php echo htmlspecialchars($activate_error); ?></div>
    <?php endif; ?>

    <?php if ($show_input): ?>
        <form method="post">
            <label>输入授权码激活</label>
            <div class="inp-grp">
                <input type="text" name="_lic_key" placeholder="请粘贴新的授权码" required autocomplete="off">
                <button type="submit" name="_lic_action" value="activate">激活</button>
            </div>
        </form>
    <?php endif; ?>

    <?php if ($show_recovery_hint): ?>
        <div class="recovery-hint">
            💡 管理员在后台恢复授权后，<strong>刷新本页面</strong>即可自动恢复正常使用。<br>
            当前状态无需任何操作，请耐心等待管理员处理。
        </div>
    <?php endif; ?>

    <div class="contact">
        <p>如需帮助，请联系管理员</p>
        <a href="mailto:<?php echo htmlspecialchars(LICENSE_CONTACT_EMAIL); ?>"><?php echo htmlspecialchars(LICENSE_CONTACT_EMAIL); ?></a>
    </div>

    <div class="hint">本程序采用实时联网验证</div>
    </div></body></html>
    <?php
}

_lic_verify();


function get_current_password() {
    if (file_exists(CRED_FILE)) {
        $cred = json_decode(file_get_contents(CRED_FILE), true);
        if (is_array($cred) && isset($cred['password'])) return $cred['password'];
    }
    return ADMIN_PASS;
}

function change_password($new_password) {
    $cred = array('password' => $new_password, 'changed_at' => time());
    return file_put_contents(CRED_FILE, json_encode($cred, JSON_PRETTY_PRINT)) !== false;
}

function is_logged_in() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

function require_login() {
    if (!is_logged_in()) {
        header('Location: index.php');
        exit;
    }
}

function load_meta() {
    if (!file_exists(META_FILE)) return array();
    $data = json_decode(file_get_contents(META_FILE), true);
    return is_array($data) ? $data : array();
}

function save_meta($data) {
    file_put_contents(META_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function add_image_record($record) {
    $meta = load_meta();
    array_unshift($meta, $record);
    save_meta($meta);
}


function delete_image_record($id) {
    $meta = load_meta();
    $found = false;
    $new_meta = array();

    foreach ($meta as $item) {
        if (isset($item['id']) && $item['id'] === $id) {
            $filepath = UPLOAD_DIR . '/' . $item['filename'];
            if (file_exists($filepath)) @unlink($filepath);
            $found = true;
        } else {
            $new_meta[] = $item;
        }
    }

    if ($found) {
        save_meta($new_meta);
    }
    return $found;
}

function server_supports_webp() {
    return function_exists('imagewebp');
}


function compress_image($source_path, $target_path, $mime_type) {
    $info = @getimagesize($source_path);
    if (!$info) return false;

    $orig_w = $info[0];
    $orig_h = $info[1];
    $new_w = $orig_w;
    $new_h = $orig_h;

    if ($orig_w > COMPRESS_MAX_WIDTH || $orig_h > COMPRESS_MAX_HEIGHT) {
        $ratio = min(COMPRESS_MAX_WIDTH / $orig_w, COMPRESS_MAX_HEIGHT / $orig_h);
        $new_w = (int)($orig_w * $ratio);
        $new_h = (int)($orig_h * $ratio);
    }

    $src_img = null;
    switch ($mime_type) {
        case 'image/jpeg':
            $src_img = @imagecreatefromjpeg($source_path);
            break;
        case 'image/png':
            $src_img = @imagecreatefrompng($source_path);
            break;
        case 'image/gif':
            $src_img = @imagecreatefromgif($source_path);
            break;
        case 'image/webp':
            if (function_exists('imagecreatefromwebp')) {
                $src_img = @imagecreatefromwebp($source_path);
            }
            break;
        case 'image/bmp':
            if (function_exists('imagecreatefrombmp')) {
                $src_img = @imagecreatefrombmp($source_path);
            } else {
                return @copy($source_path, $target_path);
            }
            break;
        default:
            return false;
    }


    if (!$src_img) return false;

    $dst_img = imagecreatetruecolor($new_w, $new_h);
    if (!$dst_img) {
        imagedestroy($src_img);
        return false;
    }

    imagealphablending($dst_img, false);
    imagesavealpha($dst_img, true);
    $transparent = imagecolorallocatealpha($dst_img, 0, 0, 0, 127);
    imagefill($dst_img, 0, 0, $transparent);
    imagecopyresampled($dst_img, $src_img, 0, 0, 0, 0, $new_w, $new_h, $orig_w, $orig_h);

    $saved = false;
    if (server_supports_webp()) {
        $saved = @imagewebp($dst_img, $target_path, COMPRESS_QUALITY);
    } else {
        switch ($mime_type) {
            case 'image/jpeg':
                $saved = @imagejpeg($dst_img, $target_path, COMPRESS_QUALITY);
                break;
            case 'image/png':
                $saved = @imagepng($dst_img, $target_path, 7);
                break;
            case 'image/gif':
                $saved = @imagegif($dst_img, $target_path);
                break;
            case 'image/webp':
                // 【修复】WebP 不支持时降级为 JPEG
                $saved = @imagejpeg($dst_img, $target_path, COMPRESS_QUALITY);
                break;
            default:
                $saved = @imagejpeg($dst_img, $target_path, COMPRESS_QUALITY);
                break;
        }
    }

    imagedestroy($src_img);
    imagedestroy($dst_img);
    return $saved;
}

function get_output_extension($original_mime) {
    if (server_supports_webp()) return 'webp';
    $map = array(
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'jpg',
        'image/bmp'  => 'bmp'
    );
    return isset($map[$original_mime]) ? $map[$original_mime] : 'jpg';
}

function generate_filename($ext) {
    return date('Ymd') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
}

function json_response($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}


function site_url() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    $script = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
    $path = str_replace('\\', '/', dirname($script));

    if ($path === '/' || $path === '\\') {
        $path = '';
    }
    return rtrim($protocol . '://' . $host . $path, '/');
}

function format_size($bytes) {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1024 * 1024) return round($bytes / 1024, 1) . ' KB';
    if ($bytes < 1024 * 1024 * 1024) return round($bytes / (1024 * 1024), 2) . ' MB';
    return round($bytes / (1024 * 1024 * 1024), 2) . ' GB';
}

function total_images_size() {
    $meta = load_meta();
    $total = 0;
    foreach ($meta as $item) {
        $total += isset($item['compressed_size']) ? (int)$item['compressed_size'] : 0;
    }
    return $total;
}