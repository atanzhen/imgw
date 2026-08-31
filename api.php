<?php
require_once __DIR__ . '/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    json_response(array('success' => false, 'message' => '未登录'), 401);
}

$raw_input = file_get_contents('php://input');
$input = null;
if (!empty($raw_input)) {
    $input = json_decode($raw_input, true);
}
if (!is_array($input)) {
    $input = array();
}

$action = '';
if (isset($input['action'])) {
    $action = $input['action'];
} elseif (isset($_REQUEST['action'])) {
    $action = $_REQUEST['action'];
}

switch ($action) {
    case 'upload':
        handle_upload();
        break;
    case 'delete':
        handle_delete($input);
        break;
    case 'batch_delete':
        handle_batch_delete($input);
        break;
    case 'change_password':
        handle_change_password($input);
        break;
    case 'get_stats':
        handle_get_stats();
        break;
    default:
        json_response(array('success' => false, 'message' => '未知操作'), 400);
}

function handle_upload() {

    if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
        json_response(array('success' => false, 'message' => '没有上传文件'), 400);
    }

    $file = $_FILES['file'];
    $error = isset($file['error']) ? (int)$file['error'] : UPLOAD_ERR_NO_FILE;

    if ($error !== UPLOAD_ERR_OK) {
        $errors = array(
            UPLOAD_ERR_INI_SIZE   => '文件超过服务器限制大小',
            UPLOAD_ERR_FORM_SIZE  => '文件超过表单限制大小',
            UPLOAD_ERR_PARTIAL    => '文件只上传了一部分',
            UPLOAD_ERR_NO_FILE    => '没有选择文件',
            UPLOAD_ERR_NO_TMP_DIR => '缺少临时文件夹',
            UPLOAD_ERR_CANT_WRITE => '写入磁盘失败',
        );
        $msg = isset($errors[$error]) ? $errors[$error] : '上传错误代码: ' . $error;
        json_response(array('success' => false, 'message' => $msg), 400);
    }

    $file_size = isset($file['size']) ? (int)$file['size'] : 0;
    if ($file_size > MAX_FILE_SIZE) {
        json_response(array('success' => false, 'message' => '文件大小超过限制 (最大20MB)'), 400);
    }

    $tmp_name = isset($file['tmp_name']) ? $file['tmp_name'] : '';
    if (empty($tmp_name) || !file_exists($tmp_name)) {
        json_response(array('success' => false, 'message' => '临时文件不存在'), 400);
    }


    $mime = '';
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp_name);
    }
    if (empty($mime)) {

        $img_info_tmp = @getimagesize($tmp_name);
        $mime = ($img_info_tmp && isset($img_info_tmp['mime'])) ? $img_info_tmp['mime'] : '';
    }

    if (empty($mime) || !in_array($mime, ALLOWED_TYPES)) {
        json_response(array('success' => false, 'message' => '不支持的图片格式: ' . $mime), 400);
    }

    $img_info = @getimagesize($tmp_name);
    if (!$img_info) {
        json_response(array('success' => false, 'message' => '文件不是有效的图片'), 400);
    }

    $output_ext = get_output_extension($mime);
    $new_filename = generate_filename($output_ext);
    $target_path = UPLOAD_DIR . '/' . $new_filename;

    $compressed = compress_image($tmp_name, $target_path, $mime);

    if (!$compressed || !file_exists($target_path)) {
        json_response(array('success' => false, 'message' => '图片处理失败，请检查服务器 GD 扩展'), 500);
    }

    $final_filename = basename($target_path);
    $compressed_size = filesize($target_path);

    $output_info = @getimagesize($target_path);
    $out_w = $output_info ? $output_info[0] : $img_info[0];
    $out_h = $output_info ? $output_info[1] : $img_info[1];

    $base = site_url();
    $image_url = $base . '/uploads/' . $final_filename;

    $original_name = isset($file['name']) ? $file['name'] : 'unknown';

    $record = array(
        'id'              => bin2hex(random_bytes(16)),
        'filename'        => $final_filename,
        'original_name'   => $original_name,
        'mime_type'       => $mime,
        'original_size'   => $file_size,
        'compressed_size' => $compressed_size,
        'width'           => $out_w,
        'height'          => $out_h,
        'url'             => $image_url,
        'timestamp'       => time(),
    );

    add_image_record($record);

    json_response(array('success' => true, 'data' => $record));
}

function handle_delete($input) {
    $id = isset($input['id']) ? $input['id'] : '';
    if (empty($id)) {
        json_response(array('success' => false, 'message' => '缺少图片ID'), 400);
    }
    if (delete_image_record($id)) {
        json_response(array('success' => true, 'message' => '删除成功'));
    } else {
        json_response(array('success' => false, 'message' => '图片不存在'), 404);
    }
}

function handle_batch_delete($input) {
    $ids = isset($input['ids']) ? $input['ids'] : array();

    if (!is_array($ids) || empty($ids)) {
        json_response(array('success' => false, 'message' => '未选择任何图片'), 400);
    }

    if (count($ids) > 100) {
        json_response(array('success' => false, 'message' => '单次最多删除 100 张'), 400);
    }


    $clean_ids = array();
    foreach ($ids as $id) {
        $trimmed = trim((string)$id);
        if ($trimmed !== '') {
            $clean_ids[$trimmed] = true;
        }
    }
    $ids = array_keys($clean_ids);

    if (empty($ids)) {
        json_response(array('success' => false, 'message' => '无有效的图片ID'), 400);
    }

    $deleted = 0;
    $failed  = 0;

    foreach ($ids as $id) {
        if (delete_image_record($id)) {
            $deleted++;
        } else {
            $failed++;
        }
    }

    if ($failed > 0) {
        $msg = "成功删除 {$deleted} 张，{$failed} 张删除失败（可能已不存在）";
    } else {
        $msg = "成功删除 {$deleted} 张图片";
    }

    json_response(array(
        'success' => true,
        'deleted' => $deleted,
        'failed'  => $failed,
        'message' => $msg
    ));
}

function handle_change_password($input) {
    $old_pass = isset($input['old_password']) ? $input['old_password'] : '';
    $new_pass = isset($input['new_password']) ? $input['new_password'] : '';

    if (empty($old_pass) || empty($new_pass)) {
        json_response(array('success' => false, 'message' => '请填写完整'), 400);
    }
    if (strlen($new_pass) < 6) {
        json_response(array('success' => false, 'message' => '新密码至少6位'), 400);
    }

    $current = get_current_password();
    if ($old_pass !== $current) {
        json_response(array('success' => false, 'message' => '旧密码错误'), 400);
    }

    if (change_password($new_pass)) {
        json_response(array('success' => true, 'message' => '密码修改成功'));
    } else {
        json_response(array('success' => false, 'message' => '保存失败'), 500);
    }
}

function handle_get_stats() {
    $meta = load_meta();
    $total = count($meta);

    $total_size = 0;
    foreach ($meta as $item) {
        $total_size += isset($item['compressed_size']) ? (int)$item['compressed_size'] : 0;
    }

    $doc_root = isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : '/';
    $disk_free  = @disk_free_space($doc_root);
    $disk_total = @disk_total_space($doc_root);

    json_response(array(
        'success' => true,
        'data' => array(
            'total'                => $total,
            'total_size'           => $total_size,
            'total_size_formatted' => format_size($total_size),
            'disk_free'            => ($disk_free !== false) ? $disk_free : 0,
            'disk_total'           => ($disk_total !== false) ? $disk_total : 0,
            'disk_free_formatted'  => ($disk_free !== false) ? format_size($disk_free) : '未知',
        )
    ));
}