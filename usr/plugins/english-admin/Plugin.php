<?php
/**
 * RyeBlog —— 英文站插件（English Site Plugin）
 *
 * 开启 ⇔ 安装英文库（自动添加 *_en 字段与英文选项）+ 双语模式（/cn + /en 双前缀、前台切换器）。
 * 关闭 ⇔ 备份英文数据后清理英文库，中文站（根目录 /、纯中文数据库）完全不受影响。
 *
 * 隔离模型：
 *   - 新装博客：纯中文源文件 + 中文原始库（无英文列）→ 根目录 URL
 *   - 启用本插件：activate() 幂等安装英文库 → /cn + /en
 *   - 停用本插件：deactivate() 备份英文数据 → DROP 英文列 → 恢复纯中文库
 *
 * 语言文件：usr/plugins/<dir>/lang/<lang>.php（UI 词典，430+ 条）
 *
 * @Title    英文站（English Site）
 * @Desc     开启=安装英文库并启用 /en 双语站；关闭=备份并清理英文数据，不影响中文站
 * @Version  2.0.0
 * @Author   RyeBlog Team
 * @Plugin   1
 */

class Plugin_english_admin
{
    /** 英文库需要的内容列（表 => [列 => 定义]，列存在则跳过，幂等） */
    private static $enCols = [
        'vd_posts' => [
            'title_en'             => "VARCHAR(200) NULL DEFAULT NULL AFTER content",
            'content_en'           => "MEDIUMTEXT NULL AFTER title_en",
            'slug_en'              => "VARCHAR(255) NULL DEFAULT NULL AFTER slug",
            'excerpt_en'           => "TEXT NULL DEFAULT NULL AFTER excerpt",
            'seo_description_en'   => "VARCHAR(300) NULL DEFAULT NULL AFTER seo_description",
            'seo_keywords_en'      => "VARCHAR(300) NULL DEFAULT NULL AFTER seo_keywords",
        ],
        'vd_categories' => [
            'name_en'  => "VARCHAR(80) NULL DEFAULT NULL AFTER name",
            'desc_en'  => "VARCHAR(255) NULL DEFAULT NULL AFTER name_en",
        ],
        'vd_tags' => [
            'name_en'  => "VARCHAR(60) NULL DEFAULT NULL AFTER name",
        ],
        'vd_menus' => [
            'title_en' => "VARCHAR(120) NULL DEFAULT NULL AFTER title",
        ],
        // 导航/友情链接插件（nav-links）的内容双语列——表存在才加（插件未启用时跳过）
        'vd_nav_groups' => [
            'name_en'  => "VARCHAR(120) NULL DEFAULT NULL AFTER name",
        ],
        'vd_nav_links' => [
            'title_en'       => "VARCHAR(200) NULL DEFAULT NULL AFTER title",
            'description_en' => "VARCHAR(500) NULL DEFAULT NULL AFTER description",
        ],
    ];

    private static $enOptions = ['site_title_en', 'site_slogan_en', 'site_seo_description_en', 'site_seo_keywords_en', 'footer_support_en'];

    /** 将 $enCols 表名键按当前数据库前缀（dbPrefix）映射 */
    private static function prefixedEnCols()
    {
        $prefix = dbPrefix();
        $out = [];
        foreach (self::$enCols as $table => $cols) {
            $out[strpos($table, $prefix) === 0 ? $table : $prefix . substr($table, 3)] = $cols;
        }
        return $out;
    }

    private static function colExists($table, $col)
    {
        $st = db()->prepare('SELECT COUNT(*) c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
        $st->execute([$table, $col]);
        return (int)$st->fetchColumn() > 0;
    }

    private static function tableExists($table)
    {
        $st = db()->prepare('SELECT COUNT(*) c FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
        $st->execute([$table]);
        return (int)$st->fetchColumn() > 0;
    }

    /**
     * 启用：安装英文库（幂等，可重复执行）。
     */
    public static function activate()
    {
        if (!db()) return '数据库不可用，无法安装英文库。';
        $enCols = self::prefixedEnCols();
        foreach ($enCols as $table => $cols) {
            if (!self::tableExists($table)) continue; // 表不存在（插件未建表）跳过
            foreach ($cols as $col => $def) {
                if (!self::colExists($table, $col)) {
                    db()->exec("ALTER TABLE `$table` ADD COLUMN `$col` $def");
                }
            }
        }
        foreach (self::$enOptions as $opt) {
            if (getOption($opt, '__MISSING__') === '__MISSING__') {
                setOption($opt, '');
            }
        }
        return true;
    }

    /**
     * 停用：备份英文数据 → 清理英文列与选项 → 恢复纯中文库。
     * 备份失败则中止（返回错误阻止停用），保证数据可恢复。
     */
    public static function deactivate()
    {
        if (!db()) return '数据库不可用，无法执行英文库清理。';

        // 1) 备份英文数据（含恢复说明，可手工恢复）
        $backupFile = self::writeBackup();
        if ($backupFile === false) {
            return '英文数据备份写入失败，已中止停用（中文库未受任何影响）。';
        }

        // 2) 清理英文列（存在才 DROP）
        $enCols = self::prefixedEnCols();
        foreach ($enCols as $table => $cols) {
            foreach (array_keys($cols) as $col) {
                if (self::colExists($table, $col)) {
                    db()->exec("ALTER TABLE `$table` DROP COLUMN `$col`");
                }
            }
        }

        // 3) 删除英文选项
        foreach (self::$enOptions as $opt) {
            if (getOption($opt, '__MISSING__') !== '__MISSING__') {
                dbQuery('DELETE FROM vd_options WHERE name=?', [$opt]);
            }
        }

        return true; // 停用成功，英文库已清理，中文库不受影响
    }

    /** 生成英文数据备份文件，返回文件名；失败返回 false */
    private static function writeBackup()
    {
        $backupDir = RYEBLOG_ROOT . '/usr/uploads/backup';
        if (!is_dir($backupDir)) @mkdir($backupDir, 0777, true);
        $backupFile = $backupDir . '/verda_en_' . date('Ymd_His') . '.sql';

        $lines = [
            '-- ============================================================',
            '-- RyeBlog 英文站数据备份（English Site Data Backup）',
            '-- 生成时间：' . date('Y-m-d H:i:s'),
            '-- 生成方式：停用英文站插件时自动创建（english-admin deactivate）',
            '-- 用途：重新启用英文站插件后，在「插件管理 → 英文站 → 配置」中恢复；',
            '--       或手工恢复：mysql -u root verda < ' . basename($backupFile),
            '-- 注意：恢复前请先重新启用英文站插件（否则英文列不存在，语句会报错）。',
            '-- ============================================================',
            'SET NAMES utf8mb4;',
            '',
        ];
        $tableCount = [];
        $enCols = self::prefixedEnCols();
        foreach ($enCols as $table => $cols) {
            if (!self::tableExists($table)) continue; // 表不存在跳过
            $rows = dbAll("SELECT * FROM `$table`");
            $cnt = 0;
            foreach ($rows as $r) {
                $set = [];
                $hasEn = false;
                foreach (array_keys($cols) as $col) {
                    $v = $r[$col] ?? null;
                    if ($v !== null && $v !== '') $hasEn = true;
                    $set[] = "`$col` = " . ($v === null ? 'NULL' : db()->quote((string)$v));
                }
                if (!$hasEn) continue; // 无英文数据跳过
                $lines[] = "UPDATE `$table` SET " . implode(', ', $set) . " WHERE id = " . (int)$r['id'] . ";";
                $cnt++;
            }
            if ($cnt > 0) { $tableCount[$table] = $cnt; $lines[] = ''; }
        }
        $optionsLines = [];
        $prefix = dbPrefix();
        foreach (self::$enOptions as $opt) {
            $v = getOption($opt, '');
            if ($v !== '') $optionsLines[] = "INSERT INTO {$prefix}options (name, value) VALUES (" . db()->quote($opt) . ", " . db()->quote($v) . ") ON DUPLICATE KEY UPDATE value=VALUES(value);";
        }
        if ($optionsLines) { $lines[] = '-- English options'; $lines = array_merge($lines, $optionsLines, ['']); }

        // 统计摘要（写入备份头部，方便核对备份内容）
        $summary = [];
        foreach ($tableCount as $t => $n) $summary[] = "$t: $n 条";
        if ($optionsLines) $summary[] = 'options: ' . count($optionsLines) . ' 项';
        if ($summary) {
            array_splice($lines, 7, 0, ['-- 备份内容摘要：' . implode(' | ', $summary), '']);
        }

        if (file_put_contents($backupFile, implode("\n", $lines)) === false) {
            return false;
        }
        return $backupFile;
    }

    /** 列出英文数据备份文件（最新在前） */
    public static function backupFiles()
    {
        $dir = RYEBLOG_ROOT . '/usr/uploads/backup';
        if (!is_dir($dir)) return [];
        $files = glob($dir . '/verda_en_*.sql') ?: [];
        $out = [];
        foreach ($files as $f) {
            $out[] = [
                'name' => basename($f),
                'path' => $f,
                'size' => filesize($f),
                'time' => filemtime($f),
            ];
        }
        usort($out, fn($a, $b) => $b['time'] - $a['time']);
        return $out;
    }

    /**
     * 恢复英文数据备份：逐条执行备份 SQL。
     * @param string $file 备份文件名（仅允许 verda_en_*.sql）
     * @return string|true 错误信息或 true
     */
    public static function restoreBackup($file)
    {
        if (!db()) return '数据库不可用。';
        $base = basename($file);
        if (!preg_match('/^verda_en_\d{8}_\d{6}\.sql$/', $base)) {
            return '非法的备份文件名。';
        }
        $path = RYEBLOG_ROOT . '/usr/uploads/backup/' . $base;
        if (!is_file($path)) return '备份文件不存在。';

        // 恢复前要求英文列存在（未启用插件则提示先启用）
        if (!self::colExists(dbPrefix() . 'posts', 'title_en')) {
            return '请先重新启用英文站插件（恢复英文库结构），再执行恢复。';
        }

        $sql = file_get_contents($path);
        if ($sql === false) return '读取备份文件失败。';

        $n = 0;
        foreach (preg_split('/;\s*\n/', $sql) as $stmt) {
            $stmt = trim($stmt);
            if ($stmt === '' || strpos($stmt, '--') === 0) continue;
            $stmt = preg_replace('/^--.*$/m', '', $stmt); // 去掉行内注释
            $stmt = trim($stmt);
            if ($stmt === '') continue;
            try {
                db()->exec(applyDbPrefix($stmt));
                $n++;
            } catch (Throwable $e) {
                return '恢复失败（第 ' . ($n + 1) . ' 条）：' . $e->getMessage();
            }
        }
        return true;
    }

    /** 删除一个备份文件 */
    public static function deleteBackup($file)
    {
        $base = basename($file);
        if (!preg_match('/^verda_en_\d{8}_\d{6}\.sql$/', $base)) {
            return '非法的备份文件名。';
        }
        $path = RYEBLOG_ROOT . '/usr/uploads/backup/' . $base;
        if (is_file($path) && @unlink($path)) return true;
        return '备份文件删除失败。';
    }

    /**
     * 后台菜单注册：本插件启用时，在「设置」组导航注入「翻译管理」入口。
     * 规范：插件实现 admin_menu_<组key>() 静态方法，核心导航渲染时自动注入对应分组；未启用/未实现则不显示（热插拔）。
     */
    public static function admin_menu_settings()
    {
        if (!bilingualEnabled()) return '';
        return '<li><a href="' . esc(baseUrl('admin/translations.php')) . '" class="admin-nav-sub-link">🌐 ' . __('翻译管理') . '</a></li>';
    }

    /**
     * 插件说明页 + 英文数据备份管理（恢复/删除）。
     */
    public static function config()
    {
        // 处理恢复/删除备份动作
        $okMsg = $errMsg = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && checkCsrf()) {
            $act = $_POST['ba'] ?? '';
            $file = trim($_POST['bf'] ?? '');
            if ($act === 'restore' && $file !== '') {
                $r = self::restoreBackup($file);
                if ($r === true) $okMsg = __('英文数据已恢复：') . ' <code>' . esc($file) . '</code>';
                else $errMsg = $r;
            } elseif ($act === 'delete' && $file !== '') {
                $r = self::deleteBackup($file);
                if ($r === true) $okMsg = __('备份已删除：') . ' <code>' . esc($file) . '</code>';
                else $errMsg = $r;
            }
        }

        echo '<div class="panel" style="margin-bottom:16px"><h3>' . __('英文站插件（English Site）') . '</h3>';
        echo '<p>' . __('开启：自动安装英文库（添加中英双字段）并启用 /en 双语站；关闭：备份英文数据后清理英文库，中文站（根目录 / 与纯中文数据库）完全不受影响。') . '</p>';
        echo '<p class="muted" style="font-size:.85rem">' . __('UI 词典位于') . ' <code>usr/plugins/english-admin/lang/en.php</code>' . __('（440+ 条，未译自动回退中文）。') . '</p></div>';

        // 备份管理
        $backups = self::backupFiles();
        echo '<div class="panel"><h3>💾 ' . __('英文数据备份') . '</h3>';
        if ($okMsg) echo '<div class="notice notice-ok">' . $okMsg . '</div>';
        if ($errMsg) echo '<div class="notice notice-err">' . esc($errMsg) . '</div>';
        echo '<p class="muted" style="font-size:.85rem">' . __('停用英文站插件时自动备份英文数据到'); ?> <code>usr/uploads/backup/</code><?php echo __('，恢复前请先重新启用英文站插件。') . '</p>';
        if (!$backups) {
            echo '<p class="muted">' . __('暂无备份（停用插件时自动生成）。') . '</p>';
        } else {
            echo '<table class="data"><tr><th>' . __('文件') . '</th><th>' . __('大小') . '</th><th>' . __('时间') . '</th><th>' . __('操作') . '</th></tr>';
            foreach ($backups as $b) {
                echo '<tr>'
                    . '<td><code>' . esc($b['name']) . '</code></td>'
                    . '<td>' . round($b['size'] / 1024, 1) . ' KB</td>'
                    . '<td>' . formatDate(date('Y-m-d H:i:s', $b['time'])) . '</td>'
                    . '<td style="white-space:nowrap">'
                    . '<form method="post" style="display:inline" onsubmit="return confirm(\'' . __('恢复该备份将覆盖当前英文数据，确定？') . '\')">'
                    . '<input type="hidden" name="_csrf" value="' . csrfToken() . '">'
                    . '<input type="hidden" name="ba" value="restore"><input type="hidden" name="bf" value="' . esc($b['name']) . '">'
                    . '<button class="btn btn-sm" type="submit">' . __('恢复') . '</button></form> '
                    . '<form method="post" style="display:inline" onsubmit="return confirm(\'' . __('删除该备份文件？不可恢复。') . '\')">'
                    . '<input type="hidden" name="_csrf" value="' . csrfToken() . '">'
                    . '<input type="hidden" name="ba" value="delete"><input type="hidden" name="bf" value="' . esc($b['name']) . '">'
                    . '<button class="btn btn-ghost btn-sm" type="submit" style="color:#b3261e">' . __('删除') . '</button></form>'
                    . '</td></tr>';
            }
            echo '</table>';
        }
        echo '</div>';
    }
}
