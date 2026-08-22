<?php
/**
 * RyeBlog 插件 —— RYE社区（论坛）
 *
 * @Title RYE社区（论坛）
 * @Version 1.0.0
 *
 * 深度集成方案：论坛作为 RyeBlog 插件，用户复用 RyeBlog 账号（vd_users），
 * 论坛专属字段存放在 ryebbs_user_ext；前台位于 /bbs/，后台在"论坛管理"分组。
 */
class Plugin_rye
{
    /** 论坛表前缀 */
    public static function prefix()
    {
        return 'ryebbs_';
    }

    /** 激活：建立论坛数据表 + 默认数据 */
    public static function activate()
    {
        require_once __DIR__ . '/install/schema.php';
        $P = self::prefix();
        rye_install_schema($P);
        rye_install_default_data($P);
        return true;
    }

    /**
     * 停用：开发期暂不自动清表（避免误删论坛数据）。
     * 后续可在此加"备份 + 确认"逻辑（参照 english-admin 插件）。
     */
    public static function deactivate()
    {
        return true;
    }

    /** 配置页（后台插件管理里显示） */
    public static function config()
    {
        echo '<p>RYE社区论坛插件。启用后：</p>';
        echo '<ul>';
        echo '<li>前台访问地址：<code>' . esc(baseUrl('bbs/')) . '</code></li>';
        echo '<li>用户体系：直接复用 RyeBlog 账号（无需单独注册）。</li>';
        echo '<li>后台管理：后台侧边栏「RYE社区」分组。</li>';
        echo '</ul>';
    }

    /** 后台侧边栏菜单（挂在 adminHead 的 nav 之后） */
    public static function adminMenu()
    {
        $base = baseUrl('admin/plugin.php?p=rye&page=');
        $items = [
            'forums'        => '版块管理',
            'content'       => '主题管理',
            'users'         => '用户管理',
            'medals'        => '勋章管理',
            'reports'       => '举报管理',
            'sensitive_words' => '敏感词',
            'ip_bans'       => 'IP 封禁',
            'invite_codes'  => '邀请码',
            'stats'         => '访问统计',
            'settings'      => '论坛设置',
        ];
        $html = '<div class="plugin-admin-group">';
        $html .= '<div class="plugin-admin-group-title">RYE社区</div>';
        foreach ($items as $pg => $label) {
            $html .= '<a href="' . esc($base . $pg) . '">' . esc($label) . '</a>';
        }
        $html .= '</div>';
        return $html;
    }

    /** 前台导航：注入"社区"链接 */
    public static function nav_top()
    {
        return '<a href="' . esc(baseUrl('bbs/')) . '">RYE社区</a>';
    }
}
