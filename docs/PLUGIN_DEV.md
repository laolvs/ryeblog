# RyeBlog 插件开发规范

本文档面向第三方开发者，详细介绍如何为 RyeBlog 开发插件。

## 插件架构

![插件架构](docs/img/plugin-arch.svg)

插件是一个 `Plugin_{目录名}` 类，通过约定方法（如 `config()`、`saveConfig()`、`contentMenu()`）与后台对接，并可在钩子点挂载行为。后台「插件管理」负责启用 / 停用，「插件内容」负责聚合各插件的内容入口。

## 目录结构

```
usr/plugins/
└── your-plugin/           ← 插件目录名（仅用字母数字下划线）
    ├── Plugin.php         ← 主文件（必需）
    ├── config.php         ← 配置页面逻辑（可选，由 config() 方法实现）
    ├── assets/            ← 静态资源（可选）
    │   ├── css/
    │   └── js/
    └── lib/               ← 第三方库（可选）
```

## Plugin.php 规范

插件主文件必须定义一个类，类名格式为 `Plugin_{目录名}`（目录名中的特殊字符替换为下划线）。

### 元数据注释

在文件头部使用 PHP 注释声明插件元信息，后台通过正则解析：

```php
<?php
/**
 * @Title    插件名称
 * @Desc     插件描述
 * @Version  1.0.0
 * @Author   作者名
 */
```

### 最小示例

```php
<?php
/**
 * @Title    我的插件
 * @Desc     一个简单的示例插件
 * @Version  1.0.0
 * @Author   Your Name
 */

class Plugin_my_plugin
{
    // 钩子方法...
}
```

## 钩子（Hooks）系统

RyeBlog 在前台和后台的关键位置预留了钩子。插件通过定义与钩子同名的方法来注入内容或修改数据。

### Action 钩子（输出 HTML）

`doHook($name, $arg)` / `doAction($name, $arg)` 会遍历所有已启用的插件，调用其同名方法，收集返回的 HTML 字符串并拼接输出。

| 钩子名 | 触发位置 | 参数 | 返回值 |
|--------|---------|------|--------|
| `header` | `<head>` 标签内 | 无 | HTML 字符串 |
| `footer` | `</body>` 标签前 | 无 | HTML 字符串 |
| `sidebarTop` | 侧边栏顶部 | 无 | HTML 字符串 |
| `sidebarBottom` | 侧边栏底部 | 无 | HTML 字符串 |
| `articleFooter` | 文章内容后（评论前） | `$post` 数组 | HTML 字符串 |
| `afterArticleContent` | 正文 HTML 输出后 | `$post` 数组 | HTML 字符串 |

> 注意：钩子方法名使用 **驼峰命名**（如 `sidebarTop`），与 `doHook('sidebar_top')` 的下划线参数对应。RyeBlog 内部会自动匹配驼峰方法名。

### Filter 钩子（修改数据）

`applyFilter($name, $value, $extra)` 遍历所有已启用的插件，调用其同名方法，方法接收一个值并可修改后返回。

| 过滤器名 | 触发位置 | 参数 | 返回值 |
|---------|---------|------|--------|
| `filterContent` | 正文渲染后 | `$html` | 修改后的 HTML |

### 钩子方法示例

```php
class Plugin_my_plugin
{
    /**
     * 在 <head> 中添加内容
     */
    public static function header()
    {
        return '<meta name="my-plugin" content="active">' . "\n";
    }

    /**
     * 在页脚添加 JavaScript
     */
    public static function footer()
    {
        return '<script>console.log("My Plugin is active!");</script>' . "\n";
    }

    /**
     * 在文章末尾追加内容
     * @param array $post 文章数据，包含 id, title, slug, content 等
     */
    public static function articleFooter($post)
    {
        if (!$post) return '';
        return '<p style="margin-top:20px;color:#999">—— 由我的插件提供</p>';
    }

    /**
     * 在侧边栏顶部添加自定义小工具
     */
    public static function sidebarTop()
    {
        return '<div class="widget"><h3>自定义工具</h3><p>这是我的插件添加的内容。</p></div>';
    }
}
```

### 后台菜单注册（重要规范）

**核心导航共 5 个顶级分组**：`dashboard`（仪表盘）、`content`（内容）、`appearance`（外观）、`plugins`（插件）、`settings`（设置）。

插件需要向后台添加菜单时，**实现对应分组的 `admin_menu_<组key>()` 静态方法**，返回一个 `<li>…</li>` 片段。核心渲染导航时会自动把插件菜单注入对应分组；**插件未启用或未实现该方法，菜单不会显示**（热插拔，无需改动核心）。

```php
/**
 * 后台菜单注册：本插件启用时，在「设置」组注入「翻译管理」入口
 */
public static function admin_menu_settings()
{
    // 条件控制：满足条件才显示
    if (!bilingualEnabled()) return '';
    return '<li><a href="' . esc(baseUrl('admin/translations.php')) . '" class="admin-nav-sub-link">🌐 ' . __('翻译管理') . '</a></li>';
}
```

> ⚠️ **原则**：核心导航**只放核心功能**。凡是由插件提供的能力（如双语/翻译管理、论坛管理等），入口必须由插件自己通过 `admin_menu_<组>` 注册，核心不硬编码。插件停用/卸载后菜单自动消失。

可用分组：`admin_menu_dashboard` / `admin_menu_content` / `admin_menu_appearance` / `admin_menu_plugins` / `admin_menu_settings`。另保留全局 `admin_menu` 钩子（追加在导航末尾，向后兼容）。

## 生命周期方法

插件可以定义以下生命周期方法（可选）：

```php
class Plugin_my_plugin
{
    /**
     * 插件激活时执行
     * 可用于创建数据库表、初始化配置
     * @return bool|string 成功返回 true，失败返回错误信息字符串
     */
    public static function activate()
    {
        // 例如：创建自定义表
        // dbQuery("CREATE TABLE IF NOT EXISTS ...");
        return true;
    }

    /**
     * 插件停用时执行
     * 可用于清理数据（可选）
     * @return bool|string
     */
    public static function deactivate()
    {
        return true;
    }
}
```

## 配置页面接口

插件可通过 `config()` 和 `saveConfig()` 方法提供后台配置页面。

### config() 方法

返回配置页面的 HTML（包含 `<form method="post">` 表单）。后台会自动在外层包裹面板和 CSRF 令牌。

```php
class Plugin_my_plugin
{
    /**
     * 返回配置页面 HTML
     * 表单必须 method="post"，后台会自动注入 _csrf 令牌
     */
    public static function config()
    {
        $current = getOption('my_plugin_setting', '默认值');
        return <<<HTML
<form method="post">
    <label>插件设置项</label>
    <input type="text" name="my_plugin_setting" value="{$current}" style="width:100%">
    <p style="margin-top:12px"><button class="btn" type="submit">保存配置</button></p>
</form>
HTML;
    }

    /**
     * 保存配置
     * @param array $post $_POST 数据
     * @return bool|string 成功返回 true，失败返回错误信息
     */
    public static function saveConfig($post)
    {
        if (isset($post['my_plugin_setting'])) {
            setOption('my_plugin_setting', trim($post['my_plugin_setting']));
        }
        return true;
    }
}
```

后台通过 `admin/plugin-config.php?dir=your-plugin` 访问配置页面。如果插件类有 `config()` 方法，插件管理页会显示"配置"按钮。

## 可用的核心函数

插件中可以直接调用 RyeBlog 的核心函数（定义在 `inc/functions.php` 中）：

### 数据库

```php
dbQuery($sql, $params)   // 执行写操作（INSERT/UPDATE/DELETE）
dbAll($sql, $params)     // 查询多行，返回数组
dbOne($sql, $params)     // 查询单行，返回数组或 null
dbInsert($sql, $params)  // 插入并返回 lastInsertId
```

> 所有 SQL 必须使用 PDO 预处理语句（`?` 占位符 + 参数绑定），禁止拼接 SQL。

### 选项 / 配置

```php
getOption($key, $default = '')   // 读取站点配置
setOption($key, $value)          // 写入站点配置
```

### URL 与品牌

```php
siteTitle()        // 站点名称
siteSlogan()       // 站点标语
siteUrl()          // 站点 URL
homeUrl()          // 首页 URL
baseUrl($path)     // 拼接站点基础路径
postUrl($post)     // 文章 URL
pageUrl($pg)       // 独立页面 URL
categoryUrl($cat)  // 分类 URL
tagUrl($tag)       // 标签 URL
```

### 内容渲染

```php
renderContent($content, $format)           // 渲染 Markdown/HTML 内容
renderContentWithToc($content, $format)    // 渲染并提取目录（返回 ['html'=>..., 'toc'=>[...]]）
excerptOf($content, $format, $len)         // 提取摘要
postExcerpt($post, $len)                   // 文章摘要
```

### 文件上传 / 附件

```php
getUploadRelDir()                   // 获取上传相对目录（usr/uploads/YYYYmm/）
ensureUploadDir($rel)               // 确保上传目录存在
makeUniqueFilename($original)       // 生成唯一文件名
sanitizeFilename($name)             // 文件名消毒
scanContentForImages($content, $format)           // 扫描正文中的本地上传图片 URL
scanContentForUsedAttachments($content, $format)   // 扫描正文所有附件引用
scanRemoteImages($content, $format)                // 扫描正文中的远程图片 URL
downloadRemoteFile($url, $destDir, $filename)      // 下载远程文件
registerAttachmentRecord($rel, $filename, $size, $mime, $postId)  // 注册附件记录
cleanupUnusedAttachments($postId, $usedKeys)        // 清理未引用附件
```

### 用户 / 鉴权

```php
isLoggedIn()       // 前台用户是否登录
currentUser()      // 获取当前登录用户信息
isAdmin()          // 是否管理员
currentAdmin()     // 获取当前管理员信息
requireAdmin()     // 要求管理员登录（否则跳转）
requireUser()      // 要求用户登录（否则跳转）
```

### 安全

```php
esc($val)          // HTML 转义输出
csrfToken()        // 获取 CSRF 令牌
checkCsrf()        // 验证 POST 中的 CSRF 令牌
```

### 工具

```php
slugify($text)           // 生成 URL slug
formatDate($datetime, $format)  // 格式化日期
makeExcerpt($html, $len)        // 生成摘要
rrmdir($dir)                    // 递归删除目录
extractZip($tmpFile, $destDir)  // 解压 ZIP 文件
mkDirRecursive($path)           // 递归创建目录
```

## 代码规范

1. **类名**：`Plugin_{目录名}`，目录名仅使用字母、数字、下划线。
2. **方法**：钩子方法必须为 `public static`。
3. **安全**：所有输出到 HTML 的内容必须使用 `esc()` 转义，防止 XSS。
4. **数据库**：所有 SQL 必须使用 PDO 预处理语句（`?` 占位符 + 参数绑定），禁止拼接 SQL。
5. **命名空间**：插件不应声明命名空间，保持全局函数/类可直接调用。
6. **兼容性**：插件应兼容 PHP 7.4+，不依赖非标准扩展。
7. **CSRF**：配置表单提交时，后台已自动注入 `_csrf` 字段，`saveConfig()` 内可通过 `checkCsrf()` 验证。

## ZIP 打包规范

1. 将插件目录打包为 ZIP，ZIP 内应有一个顶层目录（即插件名）。
2. 顶层目录内必须包含 `Plugin.php`。
3. 示例结构：
   ```
   my-plugin.zip
   └── my-plugin/
       ├── Plugin.php
       ├── lang/
       │   └── en.php        （可选：英文 UI 词典）
       └── assets/
   ```
4. 用户在后台「插件管理 → 上传安装」选择 ZIP 文件即可安装。

## 插件双语规范（i18n）

RyeBlog 双语架构（v4）：`english-admin` 插件启用 ⇔ 双语模式——中文站 = **根目录**（无前缀，URL 永不变），英文站 = `/en` 前缀；未启用 = 纯中文博客（无 /en、无切换器、纯中文库）。插件参与双语需遵守：

### 1. 插件 UI 双语（词典）
- 目录约定：`usr/plugins/<dir>/lang/en.php`，返回 `['中文key' => 'English']` 数组。
- `loadLangDict('en')` 会自动合并核心 + **全部已启用插件**的词典；插件后台/前台页面直接 `__('中文')` 即可，未译词条回退中文，不产生空白。
- **前台钩子输出的 UI 文案也必须 `__()`**（如 nav-links 的「友情链接」「更多 →」「搜索网址…」「全部/国内/国外」），否则 /en 下插件 UI 仍是中文。
- 插件停用 → 词典自动卸载，零残留。

### 2. 插件内容双语（插件自管内容表）
- 需英文的字段按 `*_en` 双列命名（如 `title_en`、`description_en`），与内容主字段同表、同 id。
- **英文列统一由 english-admin 管理**：插件在自己的 `activate()` 建表后，把 `[表 => [列 => 定义]]` 告知 english-admin（`Plugin_english_admin::$enCols`），开/关英文站时自动安装/清理该表的英文列（表不存在自动跳过）；停用时英文数据随备份文件一起导出，可恢复。
- 前台渲染用 `L($row, 'field')`（en 态且 `*_en` 非空才用译文，否则回退中文）；`L()` 对纯中文库（无 *_en 列）安全，直接回退中文。
- 插件后台表单：`bilingualEnabled()` 为 true 时才渲染英文输入框，保存时同样判断（纯中文库无列，写 *_en 会 1054 报错）。
- 管理入口约定：后台「插件管理 → 配置」页内提供英文输入（如 nav-links：分组名称/链接标题/描述均并排中英），不另设独立页面。

### 3. 双语发布检查清单
```
□ 插件 UI（含前台钩子输出）全部中文经 __() 包裹？
□ usr/plugins/<dir>/lang/en.php 词典齐全（含前台 UI 词条）？
□ 插件自定义内容表按 *_en 双列 + L() 回退？
□ 插件表英文列已加入 english-admin::$enCols（开/关自动装/清）？
□ 后台表单英文输入仅在 bilingualEnabled() 时渲染与保存？
□ 停用 english-admin 后：英文列清理、无残留、纯中文站零报错？
```
参考实现：
- `usr/plugins/english-admin/` —— 双语开关宿主：activate() 安装全部英文列（含各插件表）、deactivate() 备份（`usr/uploads/backup/verda_en_*.sql`）→ DROP 英文列 → 删选项；配置页提供备份列表/恢复/删除。
- `usr/plugins/nav-links/` —— 内容双语示范（分组 name_en、链接 title_en/description_en、频道标题英文选项，前台 L() + __()）。

## 完整示例

参见 `usr/plugins/example/Plugin.php` —— 文末版权声明插件（演示 `articleFooter` 钩子 + `config()`/`saveConfig()` 配置接口 + 占位符变量 + 颜色配色编辑 + 实时预览）。
参见 `usr/plugins/data-import/Plugin.php` —— 数据导入导出插件 v2.0（支持 WordPress/Typecho XML、SQL 导入；RyeBlog XML/SQL 导出；自动下载远程图片；`set_time_limit(0)` 防超时）。

## 发布插件

1. 将插件目录打包为 ZIP。
2. 用户在后台「插件管理」页面上传 ZIP 安装，或在后台启用。
3. 也可手动解压到 `usr/plugins/` 目录后启用。
