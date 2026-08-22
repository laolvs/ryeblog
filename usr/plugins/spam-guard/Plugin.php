<?php
/**
 * RyeBlog 防垃圾评论插件（spam-guard）
 * -----------------------------------------------------------------------------
 * 多层无感防护，全部可配置（后台「插件配置」）：
 *   1. 蜜罐字段（Honeypot）：表单注入人类不可见的隐藏输入，机器人填了就拒绝；
 *   2. 时间陷阱：表单渲染时写入时间戳，提交过快（默认 < 3 秒）拒绝——机器人秒发、真人至少要打字；
 *   3. 链接数量限制：评论中 URL 超过阈值（默认 2 个）拒绝——垃圾评论常见多个外链；
 *   4. 频率限制：同 IP 在 N 分钟（默认 10）内评论超过 M 条（默认 3）拒绝；
 *   5. 关键词黑名单：可配置（一行一个），命中即拒绝；
 *   6. 全英文内容检测（默认关闭）：内容几乎全英文且较长 → 疑似垃圾（如需接收英文评论请关闭）；
 *   7. 拦截日志：拒绝记录写入 vd_spam_log 表，后台可查看最近拦截。
 * 集成点（核心已预留钩子）：
 *   - 评论表单：doHook('comment_form_extra') 注入蜜罐 + 时间戳；
 *   - 提交检查：post.php 在 commentSpamCheck 之后调用 doHook('comment_check', $_POST)。
 *
 * @Title    防垃圾评论
 * @Desc     多层无感防垃圾评论：蜜罐、时间陷阱、链接数限制、频率限制、关键词黑名单、全英文检测，后台可配置并查看拦截日志。
 * @Version  1.0.0
 * @Author   RyeBlog Team
 */

class Plugin_spam_guard
{
    const TBL_LOG    = 'vd_spam_log';
    const OPT_CFG    = 'spamguard_cfg';

    private static function defaults()
    {
        return [
            'enabled'      => '1',   // 总开关
            'honeypot'     => '1',   // 蜜罐
            'time_min'     => '3',   // 秒（时间陷阱）
            'max_links'    => '2',   // 评论中最多链接数
            'freq_min'     => '10',  // 分钟
            'freq_max'     => '3',   // 条数
            'eng_only'     => '0',   // 全英文检测
            'keywords'     => implode("\n", [
                'transfer to you', 'bitcoin', 'crypto', 'viagra', 'casino', 'porn',
                'buy now', 'click here', 'free money', 'make money', 'earn money', 'lottery',
                'watch this', 'seo service', 'backlink', '加v', '加微信', '加我微信',
                '代开', '发票', '兼职', '刷单', '博彩', '彩票',
            ]),
        ];
    }

    private static function cfg()
    {
        $cur = getOption(self::OPT_CFG, '');
        $cur = $cur !== '' ? json_decode($cur, true) : [];
        return array_merge(self::defaults(), is_array($cur) ? $cur : []);
    }

    private static function setCfg($arr)
    {
        setOption(self::OPT_CFG, json_encode($arr, JSON_UNESCAPED_UNICODE));
    }

    // ==================== 插件生命周期 ====================

    public static function activate()
    {
        dbQuery('CREATE TABLE IF NOT EXISTS ' . self::TBL_LOG . ' (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip VARCHAR(45) NOT NULL,
            reason VARCHAR(120) NOT NULL,
            content TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_sl_ip (ip, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        self::setCfg(self::defaults());
        return true;
    }

    public static function deactivate()
    {
        dbQuery('DROP TABLE IF EXISTS ' . self::TBL_LOG);
        setOption(self::OPT_CFG, '');
        return true;
    }

    // ==================== 评论表单注入 ====================

    public static function comment_form_extra($arg = null)
    {
        $cfg = self::cfg();
        if (($cfg['enabled'] ?? '1') !== '1') return '';

        $ts = time();
        $hpStyle = 'position:absolute;left:-9999px;top:-9999px;width:1px;height:1px;overflow:hidden;opacity:0;pointer-events:none;';
        $html  = '<input type="text" name="comment_hp" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="' . $hpStyle . '">';
        $html .= '<input type="hidden" name="comment_ts" value="' . $ts . '">';
        $html .= '<input type="hidden" name="comment_hp_check" value="1">';
        return $html;
    }

    // ==================== 提交检查 ====================

    public static function comment_check($arg = null)
    {
        $cfg = self::cfg();
        if (($cfg['enabled'] ?? '1') !== '1') return '';

        $data = is_array($arg) ? $arg : $_POST;
        $content = (string)($data['content'] ?? '');
        $ip = clientIp();

        // 1. 蜜罐：人类不可见的字段被填写 → 机器人
        if (($cfg['honeypot'] ?? '1') === '1' && trim((string)($data['comment_hp'] ?? '')) !== '') {
            self::log($ip, 'honeypot', $content);
            return __('提交过于频繁，请稍后再试。');
        }

        // 2. 时间陷阱：提交时间距表单渲染 < 阈值秒 → 机器人秒发
        $minSec = max(0, (int)($cfg['time_min'] ?? 3));
        if ($minSec > 0) {
            $ts = (int)($data['comment_ts'] ?? 0);
            if ($ts > 0 && (time() - $ts) < $minSec) {
                self::log($ip, 'time-trap (' . (time() - $ts) . 's)', $content);
                return __('提交过于频繁，请稍后再试。');
            }
        }

        // 3. 链接数量限制
        $maxLinks = max(0, (int)($cfg['max_links'] ?? 2));
        if ($maxLinks >= 0) {
            $plain = html_entity_decode($content, ENT_QUOTES | ENT_HTML5);
            preg_match_all('#https?://|www\.#i', $plain, $lm);
            if (count($lm[0]) > $maxLinks) {
                self::log($ip, 'links=' . count($lm[0]), $content);
                return __('评论包含过多链接，疑似垃圾信息，请修改后重试。');
            }
        }

        // 4. 频率限制：同 IP 在 freq_min 分钟内评论 >= freq_max 条 → 拒绝
        $freqMin = max(1, (int)($cfg['freq_min'] ?? 10));
        $freqMax = max(1, (int)($cfg['freq_max'] ?? 3));
        $cnt = (int)dbOne('SELECT COUNT(*) c FROM vd_comments WHERE author_ip=? AND created_at > (NOW() - INTERVAL ' . $freqMin . ' MINUTE)', [$ip])['c'];
        if ($cnt >= $freqMax) {
            self::log($ip, 'freq ' . $cnt . '/' . $freqMin . 'm', $content);
            return __('评论过于频繁，请稍后再试。');
        }

        // 5. 关键词黑名单（中英文，一行一个）
        $kw = array_filter(array_map('trim', explode("\n", (string)($cfg['keywords'] ?? ''))));
        if ($kw) {
            $text = mb_strtolower(html_entity_decode($content, ENT_QUOTES | ENT_HTML5) . ' ' . (string)($data['website'] ?? ''));
            foreach ($kw as $w) {
                if ($w !== '' && mb_strpos($text, mb_strtolower($w)) !== false) {
                    self::log($ip, 'keyword: ' . mb_substr($w, 0, 20), $content);
                    return __('评论内容包含不被允许的内容，请修改后重试。');
                }
            }
        }

        // 6. 全英文检测（默认关）：去链接/标点/数字后几乎全为 ASCII 字母且较长 → 疑似垃圾
        if (($cfg['eng_only'] ?? '0') === '1') {
            $plain = preg_replace('#https?://\S+|www\.\S+#i', '', $content);
            $plain = preg_replace('/[^A-Za-z\s]/', '', $plain);
            $plain = trim(preg_replace('/\s+/', ' ', $plain));
            $hasCn = preg_match('/[\x{4e00}-\x{9fff}]/u', $content);
            if (!$hasCn && mb_strlen($plain) > 60) {
                self::log($ip, 'eng-only', $content);
                return __('评论内容疑似垃圾信息，请修改后重试。');
            }
        }

        return '';
    }

    // ==================== 拦截日志 ====================

    private static function log($ip, $reason, $content)
    {
        try {
            dbQuery('INSERT INTO ' . self::TBL_LOG . ' (ip, reason, content) VALUES (?, ?, ?)',
                [$ip, $reason, mb_substr((string)$content, 0, 500)]);
        } catch (\Throwable $e) {
            // 日志失败不影响拦截
        }
    }

    // ==================== 后台配置页 ====================

    public static function config()
    {
        $cfg = self::cfg();
        $h = '';
        $h .= '<style>
.sg-box{max-width:760px}
.sg-row{display:grid;grid-template-columns:150px 1fr;gap:10px;align-items:center;margin-bottom:12px}
.sg-row label{font-size:13.5px;color:var(--ink,#2c3e50);font-weight:600}
.sg-row input[type=text],.sg-row input[type=number],.sg-row textarea{width:100%;padding:8px 10px;border:1px solid var(--line,#e2e8f0);border-radius:8px;font-size:13.5px;background:#fff}
.sg-row textarea{font-family:ui-monospace,Consolas,monospace;font-size:12.5px;min-height:120px}
.sg-hint{color:var(--muted,#7f8c8d);font-size:12px;margin:-6px 0 10px 160px;max-width:560px}
.sg-log{width:100%;border-collapse:collapse;font-size:12.5px;margin-top:8px}
.sg-log th,.sg-log td{border:1px solid var(--line,#eaecef);padding:6px 8px;text-align:left}
.sg-log th{background:#f6faf8}
.sg-num{width:90px}
</style>';
        $h .= '<div class="sg-box"><h1>🛡 防垃圾评论</h1>';
        $h .= '<form method="post" style="margin:16px 0">';
        $h .= '<input type="hidden" name="_csrf" value="' . csrfToken() . '">';

        $h .= '<div class="sg-row"><label>启用防护</label>'
            . '<select name="enabled"><option value="1"' . ($cfg['enabled'] === '1' ? ' selected' : '') . '>开启</option>'
            . '<option value="0"' . ($cfg['enabled'] !== '1' ? ' selected' : '') . '>关闭</option></select></div>';

        $h .= '<div class="sg-row"><label>蜜罐字段</label>'
            . '<select name="honeypot"><option value="1"' . ($cfg['honeypot'] === '1' ? ' selected' : '') . '>开启</option>'
            . '<option value="0"' . ($cfg['honeypot'] !== '1' ? ' selected' : '') . '>关闭</option></select></div>'
            . '<div class="sg-hint">评论表单注入人类不可见的隐藏输入框，机器人自动填写即被拦截。</div>';

        $h .= '<div class="sg-row"><label>时间陷阱（秒）</label>'
            . '<input type="number" class="sg-num" name="time_min" min="0" max="60" value="' . (int)$cfg['time_min'] . '"></div>'
            . '<div class="sg-hint">提交时间距表单渲染小于该秒数则拒绝（机器人秒发）。0 = 关闭。</div>';

        $h .= '<div class="sg-row"><label>最大链接数</label>'
            . '<input type="number" class="sg-num" name="max_links" min="0" max="10" value="' . (int)$cfg['max_links'] . '"></div>'
            . '<div class="sg-hint">评论中 URL 数量超过该值拒绝。0 = 不允许任何链接。</div>';

        $h .= '<div class="sg-row"><label>频率限制</label>'
            . '<div style="display:flex;gap:8px;align-items:center">'
            . '<input type="number" class="sg-num" name="freq_min" min="1" max="1440" value="' . (int)$cfg['freq_min'] . '"> 分钟内最多 '
            . '<input type="number" class="sg-num" name="freq_max" min="1" max="20" value="' . (int)$cfg['freq_max'] . '"> 条</div></div>';

        $h .= '<div class="sg-row"><label>全英文检测</label>'
            . '<select name="eng_only"><option value="1"' . ($cfg['eng_only'] === '1' ? ' selected' : '') . '>开启</option>'
            . '<option value="0"' . ($cfg['eng_only'] !== '1' ? ' selected' : '') . '>关闭</option></select></div>'
            . '<div class="sg-hint">内容几乎全为英文且较长时拒绝（如接收英文评论请关闭）。</div>';

        $h .= '<div class="sg-row" style="align-items:flex-start"><label>关键词黑名单</label>'
            . '<textarea name="keywords" placeholder="一行一个关键词">' . esc($cfg['keywords']) . '</textarea></div>'
            . '<div class="sg-hint">命中即拒绝，自动忽略大小写。</div>';

        $h .= '<p style="margin-top:8px"><button class="btn" type="submit">保存配置</button></p></form>';

        // 拦截日志
        $rows = dbAll('SELECT * FROM ' . self::TBL_LOG . ' ORDER BY id DESC LIMIT 20');
        $h .= '<h3 style="margin:20px 0 6px;color:var(--g-700)">最近拦截记录（' . count($rows) . ' / 最近20条）</h3>';
        if ($rows) {
            $h .= '<table class="sg-log"><tr><th>#</th><th>时间</th><th>IP</th><th>原因</th><th>内容</th></tr>';
            foreach ($rows as $r) {
                $h .= '<tr><td>' . (int)$r['id'] . '</td>'
                    . '<td>' . esc(substr((string)$r['created_at'], 5, 11)) . '</td>'
                    . '<td>' . esc($r['ip']) . '</td>'
                    . '<td>' . esc($r['reason']) . '</td>'
                    . '<td style="max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' . esc($r['content']) . '</td></tr>';
            }
            $h .= '</table>';
        } else {
            $h .= '<p class="muted" style="font-size:.85rem">暂无拦截记录。</p>';
        }

        $h .= '</div>';
        return $h;
    }

    public static function saveConfig($post)
    {
        $cfg = self::defaults();
        foreach ($cfg as $k => $v) {
            if (isset($post[$k])) {
                $cfg[$k] = ($k === 'keywords') ? trim((string)$post[$k]) : trim((string)$post[$k]);
            }
        }
        $cfg['enabled']  = ($post['enabled'] ?? '') === '1' ? '1' : '0';
        $cfg['honeypot'] = ($post['honeypot'] ?? '') === '1' ? '1' : '0';
        $cfg['eng_only'] = ($post['eng_only'] ?? '') === '1' ? '1' : '0';
        self::setCfg($cfg);
        return true;
    }
}
