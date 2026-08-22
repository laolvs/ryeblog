<?php
/**
 * RyeBlog 后台 —— 即时上传端点（AJAX）
 * 用途：写文章时无需先保存，即可上传图片/附件，返回 URL 直接插入正文。
 * 鉴权：必须先登录 admin
 * 协议：
 *    POST multipart/form-data; 字段: file + _csrf
 *    返回 JSON {success:1, id, url, filepath, filename, size, mime, type}
 */
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/admin.php';  // 触发 requireAdmin()

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => '仅支持 POST']);
    exit;
}

if (!checkCsrf()) {
    http_response_code(403);
    echo json_encode(['error' => '表单已失效，请刷新页面']);
    exit;
}

if (empty($_FILES['file']['name'])) {
    http_response_code(400);
    echo json_encode(['error' => '未收到文件']);
    exit;
}

$f = $_FILES['file'];
$valid = validateUploadFile($f);
if ($valid !== true) {
    http_response_code(400);
    echo json_encode(['error' => $valid]);
    exit;
}

// 图片按真实格式修正扩展名（如 webp 内容存成 .jpg 时，保存为 .webp）
$realImgMime = function_exists('detectRealImageMime') ? detectRealImageMime($f['tmp_name']) : '';
if ($realImgMime !== '' && strpos($realImgMime, 'image/') === 0) {
    $mimeToExt = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    $correctExt = $mimeToExt[$realImgMime] ?? null;
    if ($correctExt) {
        $curExt = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        if ($correctExt !== $curExt) {
            $f['name'] = pathinfo($f['name'], PATHINFO_FILENAME) . '.' . $correctExt;
        }
    }
}

$rel = getUploadRelDir();
$abs = ensureUploadDir($rel);
$basename = makeUniqueFilename($f['name']);
$dest = $abs . $basename;

if (!@move_uploaded_file($f['tmp_name'], $dest)) {
    http_response_code(500);
    echo json_encode(['error' => '保存文件失败']);
    exit;
}
@chmod($dest, 0644);

$mime = function_exists('mime_content_type')
    ? (mime_content_type($dest) ?: 'application/octet-stream')
    : 'application/octet-stream';

// 防止频繁调用导致孤儿累积：每次上传入口顺便清掉 24 小时前的孤儿（瞬时、低开销）
if (random_int(0, 4) === 0) {
    cleanupOldTempAttachments(24);
}

$rec = registerAttachmentRecord($rel . $basename, $f['name'], filesize($dest), $mime, null);

echo json_encode($rec + ['success' => 1]);
exit;
