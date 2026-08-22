<?php
/**
 * RYE社区 —— 用户桥接层
 *
 * 论坛用户直接复用 RyeBlog 的 vd_users，论坛专属字段（昵称/签名/金币/关注等）
 * 存放在 ryebbs_user_ext（user_id = vd_users.id）。本文件提供：
 *   - ryebbs_user()       合并 vd_users + ryebbs_user_ext（首次访问自动建档）
 *   - ryebbs_name()       显示名（昵称 > display_name > username）
 *   - ryebbs_avatar_src() 头像（论坛自定义 > 站点头像/ Gravatar）
 *   - ryebbs_add_coins()  金币增减（写 coin_logs）
 *   - ryebbs_signin()     每日签到（连续天数 + 奖励）
 *   - ryebbs_follow()     关注 / 取消关注
 *   - ryebbs_recount_user() 重算主题/回复数
 *
 * 所有函数依赖 bootstrap.php 提供的 db_row/db_all/db_val/e/current_user 等别名，
 * 因此本文件必须在 bootstrap.php 之后被 require。
 */

/* ---------- 取用户（合并 vd_users + ryebbs_user_ext，惰性建档） ---------- */
if (!function_exists('ryebbs_provision_ext')) {
    function ryebbs_provision_ext($id)
    {
        dbQuery('INSERT IGNORE INTO ' . prefix() . 'user_ext (user_id, created_at) VALUES (?, NOW())', [(int) $id]);
    }
}

if (!function_exists('ryebbs_user')) {
    function ryebbs_user($id)
    {
        $id = (int) $id;
        if ($id <= 0) return null;
        $u = db_row('SELECT * FROM vd_users WHERE id=? AND status=1', [$id]);
        if (!$u) return null;

        $ext = db_row('SELECT * FROM ' . prefix() . 'user_ext WHERE user_id=?', [$id]);
        if (!$ext) {
            ryebbs_provision_ext($id);
            $ext = [
                'user_id' => $id, 'nickname' => '', 'avatar' => '', 'signature' => '',
                'gender' => '', 'bio' => '', 'is_moderator' => 0, 'mute_until' => null,
                'coins' => 0, 'reply_count' => 0, 'thread_count' => 0, 'online_seconds' => 0,
                'email_verified' => 0, 'last_active' => null, 'created_at' => date('Y-m-d H:i:s'),
            ];
        }

        foreach (['nickname', 'avatar', 'signature', 'gender', 'bio', 'is_moderator',
                     'mute_until', 'coins', 'reply_count', 'thread_count', 'online_seconds',
                     'email_verified', 'last_active'] as $k) {
            $u[$k] = $ext[$k] ?? 0;
        }
        $u['coins']        = (int) $u['coins'];
        $u['thread_count'] = (int) $u['thread_count'];
        $u['reply_count']  = (int) $u['reply_count'];
        $u['online_seconds'] = (int) $u['online_seconds'];
        return $u;
    }
}

if (!function_exists('ryebbs_current_user')) {
    function ryebbs_current_user()
    {
        $c = currentUser();
        return $c ? ryebbs_user($c['id']) : null;
    }
}

/* ---------- 显示名 / 头像 ---------- */
if (!function_exists('ryebbs_name')) {
    function ryebbs_name($u)
    {
        if (!$u) return '已注销';
        $nick = $u['nickname'] ?? ($u['ext_nickname'] ?? '');
        if ($nick !== '' && $nick !== null) return $nick;
        if (!empty($u['display_name'])) return $u['display_name'];
        return $u['username'] ?? '已注销';
    }
}

if (!function_exists('ryebbs_default_avatar')) {
    function ryebbs_default_avatar($size = 48)
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '">'
             . '<rect width="100%" height="100%" rx="' . ($size * 0.18) . '" fill="#2e6b35"/>'
             . '<text x="50%" y="50%" dy=".35em" text-anchor="middle" font-size="' . ($size * 0.5)
             . '" fill="#fff" font-family="sans-serif">U</text></svg>';
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}

if (!function_exists('ryebbs_avatar_src')) {
    /**
     * 兼容两种来源：
     *  - 完整 ryebbs_user 数组（含 avatar_url/avatar_source/email）
     *  - forum 列表 JOIN 行（含 avatar_url/avatar_source/email/ext_avatar）
     */
    function ryebbs_avatar_src($u, $size = 48)
    {
        if (!$u) return ryebbs_default_avatar($size);
        if (!empty($u['ext_avatar'])) return $u['ext_avatar'];
        if (!empty($u['avatar'])) return $u['avatar'];
        $core = [
            'avatar_url'    => $u['avatar_url'] ?? '',
            'email'         => $u['email'] ?? '',
            'username'      => $u['username'] ?? 'U',
            'avatar_source' => $u['avatar_source'] ?? 'gravatar',
        ];
        return userAvatar($core, $size) ?: ryebbs_default_avatar($size);
    }
}

/* ---------- 资料保存（白名单） ---------- */
if (!function_exists('ryebbs_save_ext')) {
    function ryebbs_save_ext($id, $data)
    {
        $allowed = [
            'nickname'       => 32,
            'signature'      => 255,
            'bio'            => 255,
            'gender'         => 10,
            'avatar'         => 255,
            'notify_enabled' => 1,
        ];
        $sets = [];
        $params = [];
        foreach ($allowed as $k => $max) {
            if (array_key_exists($k, $data)) {
                $sets[] = "$k=?";
                $params[] = mb_substr((string) $data[$k], 0, $max, 'UTF-8');
            }
        }
        if (empty($sets)) return;
        ryebbs_provision_ext($id);
        dbQuery('UPDATE ' . prefix() . 'user_ext SET ' . implode(', ', $sets) . ' WHERE user_id=?',
            array_merge($params, [(int) $id]));
    }
}

/* ---------- 金币 ---------- */
if (!function_exists('ryebbs_add_coins')) {
    function ryebbs_add_coins($user_id, $amount, $type = '', $desc = '')
    {
        $amount = (int) $amount;
        if ($amount === 0) return;
        dbQuery('INSERT INTO ' . prefix() . 'coin_logs (user_id, amount, type, description, created_at) VALUES (?, ?, ?, ?, NOW())',
            [(int) $user_id, $amount, $type, $desc]);
        ryebbs_provision_ext($user_id);
        dbQuery('UPDATE ' . prefix() . 'user_ext SET coins = coins + ? WHERE user_id=?', [$amount, (int) $user_id]);
    }
}

/* ---------- 每日签到 ---------- */
if (!function_exists('ryebbs_signin')) {
    function ryebbs_signin($user_id)
    {
        $user_id = (int) $user_id;
        $today = date('Y-m-d');
        if (db_row('SELECT id FROM ' . prefix() . 'signins WHERE user_id=? AND signin_date=?', [$user_id, $today])) {
            return ['ok' => false, 'msg' => '今天已经签到啦～'];
        }
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $prev = db_row('SELECT continuous_days FROM ' . prefix() . 'signins WHERE user_id=? AND signin_date=?', [$user_id, $yesterday]);
        $continuous = $prev ? (int) $prev['continuous_days'] + 1 : 1;
        $reward = 5 + min(max($continuous - 1, 0), 20) * 2; // 5,7,9,... 上限 +45
        dbQuery('INSERT INTO ' . prefix() . 'signins (user_id, signin_date, continuous_days, reward_coins, created_at) VALUES (?, ?, ?, ?, NOW())',
            [$user_id, $today, $continuous, $reward]);
        ryebbs_add_coins($user_id, $reward, 'signin', '每日签到 连续 ' . $continuous . ' 天');
        return ['ok' => true, 'msg' => '签到成功！连续 ' . $continuous . ' 天，+' . $reward . ' 金币',
                'continuous' => $continuous, 'reward' => $reward];
    }
}

/* ---------- 关注 ---------- */
if (!function_exists('ryebbs_follow')) {
    function ryebbs_follow($follower_id, $following_id)
    {
        $follower_id = (int) $follower_id;
        $following_id = (int) $following_id;
        if ($follower_id <= 0 || $follower_id === $following_id) return false;
        if (db_row('SELECT id FROM ' . prefix() . 'follows WHERE follower_id=? AND following_id=?', [$follower_id, $following_id])) {
            dbQuery('DELETE FROM ' . prefix() . 'follows WHERE follower_id=? AND following_id=?', [$follower_id, $following_id]);
            return false; // 已取消关注
        }
        dbQuery('INSERT INTO ' . prefix() . 'follows (follower_id, following_id, created_at) VALUES (?, ?, NOW())', [$follower_id, $following_id]);
        return true; // 已关注
    }
}

if (!function_exists('ryebbs_is_following')) {
    function ryebbs_is_following($follower_id, $following_id)
    {
        return (bool) db_row('SELECT id FROM ' . prefix() . 'follows WHERE follower_id=? AND following_id=?',
            [(int) $follower_id, (int) $following_id]);
    }
}

if (!function_exists('ryebbs_follower_count')) {
    function ryebbs_follower_count($user_id)
    {
        return (int) db_val('SELECT COUNT(*) FROM ' . prefix() . 'follows WHERE following_id=?', [(int) $user_id]);
    }
}

if (!function_exists('ryebbs_following_count')) {
    function ryebbs_following_count($user_id)
    {
        return (int) db_val('SELECT COUNT(*) FROM ' . prefix() . 'follows WHERE follower_id=?', [(int) $user_id]);
    }
}

/* ---------- 重算用户统计 ---------- */
if (!function_exists('ryebbs_recount_user')) {
    function ryebbs_recount_user($user_id)
    {
        $user_id = (int) $user_id;
        $tc = (int) db_val('SELECT COUNT(*) FROM ' . prefix() . 'threads WHERE user_id=? AND is_deleted=0', [$user_id]);
        $rc = (int) db_val('SELECT COUNT(*) FROM ' . prefix() . 'posts WHERE user_id=? AND is_deleted=0', [$user_id]);
        ryebbs_provision_ext($user_id);
        dbQuery('UPDATE ' . prefix() . 'user_ext SET thread_count=?, reply_count=? WHERE user_id=?', [$tc, $rc, $user_id]);
    }
}
