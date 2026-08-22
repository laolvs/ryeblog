<?php
/**
 * RYE社区（RyeBlog 插件）—— 图片 / 附件上传端点（AJAX）
 * 路由：/bbs/upload （POST multipart/form-data; 字段: file + _csrf）
 * 鉴权：论坛登录用户（require_login）
 * 受后台「论坛设置 → 上传设置」控制：开关 / 大小上限 / 允许扩展名
 * 返回：JSON {success:1, url, filename, size, mime, type:image|file}
 */
require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');

// AJAX 场景：未登录返回 JSON 而非 302（前端 JSON.parse 才能给出正确提示）
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => '请先登录后再上传。']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => '仅支持 POST']);
    exit;
}

verify_csrf();

// 上传开关（后台可关）
if (setting('upload_enabled', '1') !== '1') {
    http_response_code(403);
    echo json_encode(['error' => '管理员已关闭上传功能。']);
    exit;
}

$f = $_FILES['file'] ?? null;
if (!$f || empty($f['name'])) {
    http_response_code(400);
    echo json_encode(['error' => '未收到文件']);
    exit;
}

// 大小上限（默认 5MB）
$maxSizeMb = max(1, (int) setting('upload_max_size_mb', '5'));
$maxSize   = $maxSizeMb * 1048576;

// 类型白名单（默认图片 + 常用文档/压缩包）
$imgExts  = array_values(array_filter(array_map('trim', explode(',', setting('upload_ext_images', 'jpg,jpeg,png,gif,webp')))));
$fileExts = array_values(array_filter(array_map('trim', explode(',', setting('upload_ext_files', 'doc,docx,xls,xlsx,pdf,zip,rar,7z,txt,md')))));
$allowed  = array_values(array_unique(array_merge($imgExts, $fileExts)));
if (empty($allowed)) {
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
}

$valid = validateUploadFile($f, $allowed, $maxSize);
if ($valid !== true) {
    http_response_code(400);
    echo json_encode(['error' => $valid]);
    exit;
}

// 图片按真实格式修正扩展名（webp 内容存 .jpg 时纠正为 .webp）
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

$rel      = getUploadRelDir();
$abs      = ensureUploadDir($rel);
$basename = makeUniqueFilename($f['name']);
$dest     = $abs . $basename;

if (!@move_uploaded_file($f['tmp_name'], $dest)) {
    http_response_code(500);
    echo json_encode(['error' => '保存文件失败']);
    exit;
}
@chmod($dest, 0644);

$size = filesize($dest);
$mime = function_exists('mime_content_type') ? (mime_content_type($dest) ?: 'application/octet-stream') : 'application/octet-stream';
$isImage = strpos($mime, 'image/') === 0 || in_array(strtolower(pathinfo($basename, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
$url = baseUrl($rel . $basename);

// 登记核心附件表（post_id 留空，仅用于统一回收/清理孤儿）
if (function_exists('registerAttachmentRecord')) {
    registerAttachmentRecord($rel . $basename, $f['name'], $size, $mime, null);
}

echo json_encode([
    'success'  => 1,
    'url'      => $url,
    'filename' => $f['name'],
    'size'     => $size,
    'mime'     => $mime,
    'type'     => $isImage ? 'image' : 'file',
]);
exit;
