<?php
/**
 * RyeBlog —— 核心程序更新检查（客户端）
 *
 * 各站点后台定期（默认 12 小时缓存）拉取官方更新清单：
 *   https://ryeblog.com/cloud/core.json
 * 结构：{version, url, sha256, changelog, published}
 * 对比本地 RYEBLOG_VERSION，发现新版本时后台仪表盘显示更新横幅。
 *
 * 依赖：getOption / setOption（vd_options），RyeBlog 核心。
 */
if (!defined('RYEBLOG_ROOT')) {
    define('RYEBLOG_ROOT', dirname(__DIR__));
}

/** 官方核心更新清单地址（各站统一指向 ryeblog.com；可用环境变量 RYEBLOG_CORE_URL 覆盖，便于测试） */
function coreUpdateUrl()
{
    $env = getenv('RYEBLOG_CORE_URL');
    return ($env !== false && $env !== '') ? $env : 'https://ryeblog.com/cloud/core.json';
}

/**
 * 检查是否有核心更新。
 * @param bool $force 忽略缓存强制拉取
 * @return array {
 *   ok:bool, update:bool, current:string, version:string,
 *   url:string, sha256:string, changelog:string, published:string,
 *   msg?:string(失败原因), _ts:int
 * }
 */
function coreUpdateCheck($force = false)
{
    $cacheKey = 'core_update_check';
    if (!$force) {
        $cached = getOption($cacheKey, '');
        if ($cached !== '' && $cached[0] === '{') {
            $d = json_decode($cached, true);
            if (is_array($d) && isset($d['_ts']) && (time() - (int) $d['_ts']) < 43200) { // 12 小时
                // 缓存命中：version/url/sha256/changelog 来自云端 manifest 仍有效；
                // 但 current 必须实时取 RYEBLOG_VERSION 重算（避免站点刚升完、缓存却仍报旧版）
                $current = defined('RYEBLOG_VERSION') ? RYEBLOG_VERSION : '0.0.0';
                $d['current'] = $current;
                $d['update']  = !empty($d['version']) && version_compare($current, (string) $d['version'], '<');
                return $d;
            }
        }
    }

    $ctx = stream_context_create([
        'http' => ['timeout' => 8, 'user_agent' => 'RyeBlog-Core/1.0'],
        'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);
    $body = @file_get_contents(coreUpdateUrl(), false, $ctx);
    if ($body === false) {
        return ['ok' => false, 'msg' => '无法连接更新服务器（' . coreUpdateUrl() . '）', '_ts' => time()];
    }
    $d = json_decode($body, true);
    if (!is_array($d) || empty($d['version'])) {
        return ['ok' => false, 'msg' => '更新服务器返回数据异常', '_ts' => time()];
    }

    $current = defined('RYEBLOG_VERSION') ? RYEBLOG_VERSION : '0.0.0';
    $d['ok']      = true;
    $d['current'] = $current;
    $d['update']  = version_compare($current, (string) $d['version'], '<');
    $d['_ts']     = time();
    try {
        setOption($cacheKey, json_encode($d, JSON_UNESCAPED_UNICODE));
    } catch (Throwable $e) {
        // 缓存失败不影响本次返回
    }
    return $d;
}
