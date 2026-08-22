# RyeBlog 双语架构 · 英文站插件（隔离模型）

> 状态：**定稿 v4（2026-08-19）**——中文站 URL 恒为根目录（插件开关不变），英文站多 `/en`；插件化附加包，开启装英文库、关闭清理干净。
> 关联：`docs/PLUGIN_DEV.md`（插件双语规范）

---

## 1. 现状与问题

### 1.1 隔离模型（v4 定稿，用户 2026-08-19 主张）
> 「开启英文站后，关闭不影响中文站。中文站 URL 就是默认根目录，不加 /cn；英文站多一个 /en。不能一次开启一次关闭路径变 2 次，SEO 全乱。新装默认纯中文。开启=安装英文库；关闭=清理干净。」

| 状态 | 文件 | 数据库 | 中文 URL | 英文 URL |
|------|------|--------|---------|---------|
| 新装（默认） | 纯中文源文件 | 中文原始库（**无英文列**） | 根目录 `/` | — |
| 开启 english-admin | 插件激活（仅 3 个文件） | **activate() 安装英文库** | 根目录 `/`（**不变**） | `/en/...` |
| 关闭 english-admin | 插件停用 | **deactivate() 备份 → DROP 英文列 → 删选项** | 根目录 `/`（**不变**） | 消失（/en 301 回根） |

**URL 稳定性（SEO 关键）**：中文规范 = 根目录（无前缀），插件开关都不变；`/cn` 旧前缀 301 → 无前缀（兼容旧链接）；`/en` 仅双语模式存在。开关切换时中文 URL 零变化，外链/收录不受影响。

### 1.2 翻译范围清单（用户 2026-08-19 确认：全部要英文）
| 内容类型 | 中文字段 | 英文字段 | 状态 |
|---------|---------|---------|------|
| 文章/页面 | title / slug / content / excerpt / seo_description / seo_keywords | title_en / slug_en / content_en / excerpt_en / seo_description_en / seo_keywords_en | ✅ |
| 分类 | name / description | name_en / desc_en | ✅ |
| 标签 | name | name_en | ✅ |
| 菜单 | title | title_en | ✅ |
| 站点 | site_title / site_slogan | site_title_en / site_slogan_en | ✅ |
| 侧边栏模块 | 标题 + 内容 | `__()` 标题 + `L()` 内容（最新文章/分类/标签/归档/热文/热评/评论数） | ✅ |
| 评论 / 用户生成 | — | 不翻译（保持原样） | — |
- **英文 slug**：默认留空（自动用中文别名）；有特殊需求才填 slug_en（/en 下独立英文 URL）
- **英文正文**：与中文一致，支持完整编辑器（Markdown 工具栏 / 上传图片 / 上传附件 / 拖拽粘贴 / 预览）

### 1.3 实现要点
- **英文库 DDL 归属插件**：`usr/plugins/english-admin/Plugin.php` 的 `activate()`（加列：vd_posts 6 列 + categories 2 + tags 1 + menus 1 + options site_title_en/site_slogan_en）与 `deactivate()`（先备份 `usr/uploads/backup/verda_en_*.sql`，备份失败中止停用；再 DROP 列、删选项）
- **核心库纯中文**：`upgrade.php` / `verda.sql` 不再含任何 *_en 列与 active_plugins 默认 → 新装即纯中文库
- **核心查询动态列**：`getPosts/getPost/getPostBySlugAnyType/getPostTags/getPages` 的 *_en SELECT 按 `bilingualEnabled()` 动态拼接（纯中文库无这些列，硬编码会 1054 报错）
- **编辑页防护**：write/categories/tags 的 `$editLang` 仅双语模式可取 en（纯中文恒 zh，英文 pane 不渲染）；translations.php 双语模式才可用（否则提示先启用插件）
- **双语开关**：`bilingualEnabled()` = english-admin 在 active_plugins ⇔ 双语模式；纯中文模式 `langPrefix()` 空、切换器隐藏、detectLang/adminLang 强制 zh、无前缀 URL 不 301

---

## 2. 目标架构（v2 定稿：复制一个站）

> 用户最终主张：「英文插件应该是复制一个站，包括数据库的字段。标题都要 2 个，一中一英。分类、tag 这些都是。」

### 2.1 数据模型：双列全字段（同一行记录，每字段成对）
| 实体 | 中文字段 | 英文字段 |
|------|---------|---------|
| 文章/页面 | title / slug / content / excerpt / seo_description / seo_keywords | title_en / **slug_en** / content_en / **excerpt_en** / **seo_description_en** / **seo_keywords_en** |
| 分类 | name / description | name_en / desc_en |
| 标签 | name | name_en |
| 菜单 | title | title_en |
| 站点 | site_title / site_slogan | site_title_en / site_slogan_en |

- 英文缺失自动回退中文（Drupal 式，`L()`）；中文模式保存不覆盖已有英文，反之亦然
- `slug_en`：/en 下文章 URL 用英文别名（`/en/how-tea-types-classified`），未配则回退中文 slug
- 查询：en 态 `getPost()` 按 slug_en 优先匹配，其次中文 slug（旧链接兼容）

### 2.2 管理 UI：页面内中/英切换（v2）
- 每个编辑页（write/categories/tags）顶部「中文版 | 英文版」分段控件，JS 无刷新切换 + 隐藏 lang 字段
- 中文版表单 = 全部中文字段 + 元数据；英文版表单 = 全部 *_en 字段 + slug_en
- 保存按 tab 只更新对应语言列（互不覆盖）
- `translations.php` 保留为翻译总览（统计 + 跳转入口，进入对应页面英文 tab）
- menus/settings 字段少，直接并排显示中英输入

### 2.3 三层分离（贯穿）
```
① 后台 UI 语言  adminLang()                 中/EN 词典切换（english-admin 插件）
② 前台内容语言  currentLang() / detectLang() /cn /en 前缀 + 回退
③ 内容编辑语言  editLang（?lang=zh|en）      页面内中英切换
```

---

## 3. 插件双语包规范（docs/PLUGIN_DEV.md 新增章节）

### 3.1 插件 UI 双语（词典）
- 目录约定：`usr/plugins/<dir>/lang/en.php`，返回 `['中文key' => 'English']` 数组
- `loadLangDict('en')` 自动合并核心 + 全部启用插件的词典，插件 UI 直接 `__('中文')`
- 未译词条回退中文，不产生空白
- 范例：english-admin（后台 UI 词典，398 条）

### 3.2 插件内容双语（插件自管内容表）
- 约定：插件内容表需英文时，按 `*_en` 双列命名（如 `title_en`），前台渲染用 `L($row,'field')` 回退
- 插件管理页若需英文录入，复用 `?lang=en` 编辑模式约定
- 数据导入（data-import）导入文只填 ZH，`content_en` 留空 → 自动「仅中文」

### 3.3 插件双语检查清单（发布前自检）
```
□ 插件 UI 全部中文经 __() 包裹？
□ usr/plugins/<dir>/lang/en.php 词典齐全？
□ 插件自定义内容表按 *_en 双列 + L() 回退？
□ 停用插件后站点无残留（词典自动卸载）？
```

---

## 4. 实施阶段计划

| 阶段 | 内容 | 涉及文件 | 状态 |
|------|------|---------|------|
| ① | 数据迁移：`site_title_en`/`site_slogan_en` 选项；`install_schema.php`/`verda.sql`/`upgrade.php` 同步 | upgrade.php, verda.sql | ✅ |
| ② | 翻译管理页 `admin/translations.php`（总览 + 状态徽章 + 跳转入口）+ 后台导航项 | admin/translations.php, admin/admin.php | ✅ |
| ③ | 编辑页 `?lang=en` 模式：write.php（移除 en-box，改双模式）、categories.php（+name_en/desc_en）、menus.php（+title_en） | write.php, categories.php, menus.php | ✅ |
| ④ | 新增标签管理页 tags.php（双语：name + name_en + count + 删除） | admin/tags.php, admin/admin.php | ✅ |
| ⑤ | 站点信息英文：settings.php 品牌区 + siteTitle()/siteSlogan() 语言感知 | settings.php, functions.php | ✅ |
| ⑥ | 词典补全（430 条）+ 全链路回归（真机 HTTP） | english-admin/lang/en.php | ✅ |
| ⑦ | 开发文档：PLUGIN_DEV.md 插件双语规范章节 + 本文件修订 | docs/PLUGIN_DEV.md, docs/bilingual-en-design.md | ✅ |

> 落地要点：修复隐藏 bug——`currentLang()` 顶部 `$_CUR_LANG='zh'` 导致生产环境（Apache 仅注入 `?lang=`，无人调用 `setCurrentLang`）下语言探测恒为中文，`/en/` 实际一直渲染中文；已删除该初始化，未显式设定时走 `detectLang()`。

---

## 5. 需要你拍板的点

> ✅ 全部确认（2026-08-19，用户采纳全部推荐项）：
> 1. **数据模型**：双列全字段（同一行记录，每字段成对）——最终定稿
> 2. **摘要/SEO 英文**：补 excerpt_en / seo_description_en / seo_keywords_en——已落地
> 3. **英文 URL**：slug_en 独立英文别名——已落地（/en 用英文 slug，未配回退中文）
> 4. **管理 UI**：页面内「中文版 | 英文版」切换——已落地（v2 重构）
