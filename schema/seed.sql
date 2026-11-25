-- RyeBlog Seed Data
-- Comprehensive documentation and promotional content for RyeBlog

-- Articles
INSERT OR IGNORE INTO articles (id, slug, title_zh, title_en, excerpt_zh, excerpt_en, content_zh, content_en, cover, category, author, date, status, views) VALUES

-- === 快速开始 ===
('a1', 'what-is-ryeblog', 'RyeBlog 是什么？', 'What is RyeBlog?', '一个为 Cloudflare 而生的开源博客系统。基于 Pages + D1，一键部署，零成本运行，SSR 直出，SEO 友好。', 'An open-source blog system built for Cloudflare. Based on Pages + D1, one-click deploy, zero cost, SSR first, SEO friendly.', '# RyeBlog 是什么？

RyeBlog 是一个**为 Cloudflare 而生的开源博客系统**。

它不依赖任何服务器，不需要数据库配置，不需要 Docker，不需要 Nginx——你只需要一个 Cloudflare 账号，就能在 30 秒内拥有一个专业博客。

## 核心特性

- **一键部署**：Fork 仓库 → 连接 Cloudflare Pages → 创建 D1 数据库，三步上线
- **SSR 直出**：所有页面服务器端渲染为完整 HTML，不依赖 JavaScript，SEO 天然友好
- **D1 数据存储**：基于 Cloudflare D1（SQLite），数据完全自主，可随时导出迁移
- **Markdown 写作**：用 Markdown 写作，服务器端直接渲染，支持代码高亮、引用、表格
- **全球 CDN 加速**：Cloudflare 全球边缘网络加持，读者在哪里都快
- **零成本运行**：Pages 免费额度 + D1 免费 5GB，个人博客不花一分钱
- **主题可定制**：清晰的模板结构，前端开发者可以轻松定制主题
- **伪静态路由**：`/:slug` 而非 `/article/:slug`，URL 干净优雅

## 为什么做 RyeBlog？

市面上有 WordPress、Hugo、Hexo、Ghost……但它们要么太重，要么太静态，要么需要自己搭服务器。

RyeBlog 的定位很明确：**在 Cloudflare 上开箱即用的博客系统**。

- 比 WordPress 轻——没有 PHP，没有插件地狱
- 比 Hugo/Hexo 动态——内容存数据库，后台直接编辑，不需要重新构建
- 比 Ghost 简单——不需要自己租服务器，不需要 Docker

## 技术栈

| 层 | 技术 |
|---|---|
| 运行时 | Cloudflare Workers（Pages Functions） |
| 数据库 | Cloudflare D1（SQLite） |
| 渲染 | SSR，服务器端直接输出 HTML |
| 前端 | 原生 JS，零框架依赖 |
| 写作 | Markdown，服务器端解析渲染 |
| 部署 | Cloudflare Pages，Git 连接自动部署 |

## 谁适合用 RyeBlog？

- **个人博主**：想要一个简单、快速、免费的博客
- **技术写作者**：需要代码高亮、Markdown 支持、SEO 友好
- **独立开发者**：不想花时间运维，只想专注写作
- **Cloudflare 用户**：已经在用 CF 的其他服务，想要一个博客系统整合进来

## 下一步

- [5 分钟一键部署](/5-min-deploy) — 从零到博客上线
- [技术架构详解](/architecture) — 了解 RyeBlog 的设计哲学
- [GitHub 源码](https://github.com/ryeblog/ryeblog) — Fork 并开始使用', '# What is RyeBlog?

RyeBlog is an **open-source blog system built for Cloudflare**.

It does not depend on any server, does not need database configuration, does not need Docker, does not need Nginx—you only need a Cloudflare account to have a professional blog in 30 seconds.

## Core Features

- **One-click deploy**: Fork repo → Connect Cloudflare Pages → Create D1 database, three steps to go live
- **SSR first**: All pages server-side rendered as complete HTML, no JavaScript dependency, naturally SEO friendly
- **D1 data storage**: Based on Cloudflare D1 (SQLite), data fully self-owned, exportable anytime
- **Markdown writing**: Write in Markdown, server-side rendering, supports code highlighting, quotes, tables
- **Global CDN**: Cloudflare global edge network, fast everywhere
- **Zero cost**: Pages free tier + D1 free 5GB, personal blog costs nothing
- **Customizable themes**: Clear template structure, frontend developers can easily customize
- **Clean URLs**: `/:slug` instead of `/article/:slug`', 'https://picsum.photos/seed/ryeblog-intro/800/450', 'quickstart', 'RyeBlog', '2026-11-25', 'published', 0),

('a2', '5-min-deploy', '5 分钟一键部署', '5-Minute One-Click Deploy', '从零到博客上线，只需要三步。Fork 仓库、连接 Cloudflare Pages、创建 D1 数据库——30 秒部署，5 分钟完整配置。', 'From zero to blog live in three steps. Fork repo, connect Cloudflare Pages, create D1 database—30 seconds to deploy, 5 minutes to fully configure.', '# 5 分钟一键部署

这篇指南带你从零开始，在 5 分钟内完成 RyeBlog 的完整部署。

## 前置条件

- 一个 **GitHub** 账号
- 一个 **Cloudflare** 账号（免费即可）
- 不需要服务器，不需要域名（Cloudflare 会提供 `.pages.dev` 子域名）

## 第一步：Fork 仓库（30 秒）

1. 打开 [RyeBlog GitHub 仓库](https://github.com/ryeblog/ryeblog)
2. 点击右上角 **Fork** 按钮
3. 确认 Fork 到你自己的账号下

完成后，你的 GitHub 下会多一个 `ryeblog` 仓库。

## 第二步：连接 Cloudflare Pages（2 分钟）

1. 登录 [Cloudflare Dashboard](https://dash.cloudflare.com)
2. 左侧菜单选择 **Workers & Pages**
3. 点击 **Create application** → **Pages** → **Connect to Git**
4. 选择你刚 Fork 的 `ryeblog` 仓库
5. 构建配置：
   - **Framework preset**: None
   - **Build command**: 留空（不需要构建）
   - **Build output directory**: `.`
   - **Root directory**: `/`
6. 点击 **Save and Deploy**

Cloudflare 会自动部署，完成后你会得到一个 `xxx.pages.dev` 的地址，此时博客已经可以访问了！

## 第三步：创建 D1 数据库（2 分钟）

1. 在 Cloudflare Dashboard 左侧选择 **D1**
2. 点击 **Create database**
3. 数据库名称填 `ryeblog-db`
4. 创建完成后，记下 **Database ID**

## 第四步：绑定 D1 到 Pages（30 秒）

1. 回到你的 Pages 项目 → **Settings** → **Functions**
2. 找到 **D1 database bindings**
3. 添加绑定：
   - **Variable name**: `DB`
   - **D1 database**: 选择刚创建的 `ryeblog-db`
4. 保存

## 第五步：初始化数据库（30 秒）

在本地安装 Wrangler CLI，然后执行初始化：

```bash
npx wrangler d1 execute ryeblog-db --remote --file=schema/init.sql
npx wrangler d1 execute ryeblog-db --remote --file=schema/seed.sql
```

如果你没有本地环境，也可以在 Cloudflare Dashboard 的 D1 控制台中手动执行 SQL。

## 完成！

现在访问你的 `.pages.dev` 地址，你应该能看到 RyeBlog 的首页，带有示例文章。

### 可选：绑定自定义域名

1. Pages 项目 → **Custom domains** → **Set up a custom domain**
2. 输入你的域名（如 `myblog.com`）
3. Cloudflare 会自动添加 CNAME 记录并签发 HTTPS 证书

### 下一步

- [自定义域名配置](/custom-domain) — 绑定你自己的域名
- [本地开发环境](/local-dev) — 在本地开发和调试
- [主题开发入门](/theme-intro) — 自定义博客外观', 'https://picsum.photos/seed/ryeblog-deploy/800/450', 'quickstart', 'RyeBlog', '2026-11-24', 'published', 0),

('a3', 'migrate-to-ryeblog', '从 WordPress/Hugo 迁移到 RyeBlog', 'Migrating from WordPress/Hugo to RyeBlog', '完整的迁移指南：从 WordPress 导出 XML、从 Hugo 导出 Markdown，转换并导入到 RyeBlog 的 D1 数据库。', 'Complete migration guide: export XML from WordPress, export Markdown from Hugo, convert and import into RyeBlog D1 database.', '# 从 WordPress/Hugo 迁移到 RyeBlog

如果你已经在用 WordPress 或 Hugo，这篇指南帮你平滑迁移到 RyeBlog。

## 从 WordPress 迁移

### 1. 导出 WordPress 内容

在 WordPress 后台：**Tools** → **Export** → 选择 **All content** → 下载 XML 文件。

### 2. 转换为 RyeBlog 格式

RyeBlog 提供了一个迁移脚本，可以将 WordPress XML 转换为 D1 SQL：

```bash
npx ryeblog-migrate wordpress --input=wordpress-export.xml --output=migrate.sql
```

脚本会：
- 提取所有已发布文章的标题、内容、日期、分类、标签
- 将 HTML 内容转换为 Markdown
- 生成 `INSERT INTO articles` SQL 语句
- 生成对应的分类和标签关联

### 3. 导入到 D1

```bash
npx wrangler d1 execute ryeblog-db --remote --file=migrate.sql
```

### 4. 处理图片

WordPress 的图片通常在 `wp-content/uploads/` 目录。迁移时需要：

1. 将图片上传到 Cloudflare R2 或其他图床
2. 在文章内容中替换图片 URL

RyeBlog 迁移脚本支持自动替换：

```bash
npx ryeblog-migrate wordpress --input=wordpress-export.xml --output=migrate.sql --image-base=https://your-r2-bucket.r2.dev/
```

## 从 Hugo 迁移

Hugo 的迁移更简单，因为 Hugo 的内容本身就是 Markdown。

### 1. 批量转换

```bash
npx ryeblog-migrate hugo --input=content/ --output=migrate.sql
```

脚本会：
- 读取 `content/` 目录下所有 `.md` 文件
- 解析 Hugo 的 Front Matter（title、date、tags、categories）
- 生成 `INSERT INTO articles` SQL 语句
- 自动映射 Hugo categories → RyeBlog categories

### 2. 导入到 D1

```bash
npx wrangler d1 execute ryeblog-db --remote --file=migrate.sql
```

## 从 Hexo 迁移

Hexo 的内容格式与 Hugo 类似，同样使用 Markdown + Front Matter：

```bash
npx ryeblog-migrate hexo --input=source/_posts/ --output=migrate.sql
```

## 迁移注意事项

| 项目 | 说明 |
|------|------|
| **URL 结构** | RyeBlog 使用 `/:slug`，你可以在迁移时指定 slug 映射规则 |
| **分类映射** | 迁移脚本会自动创建新分类，你也可以手动映射到已有分类 |
| **评论数据** | RyeBlog 目前不支持导入评论（评论系统在路线图中） |
| **草稿** | 迁移脚本默认只迁移已发布内容，可用 `--include-drafts` 包含草稿 |
| **图片** | 建议迁移到 Cloudflare R2，享受与 Pages 同样的全球加速 |

## 迁移后检查清单

- [ ] 所有文章标题和内容正确显示
- [ ] 分类和标签数量与原博客一致
- [ ] 图片正常加载
- [ ] 文章 URL 可正常访问
- [ ] RSS 订阅正常工作
- [ ] 搜索功能正常', 'https://picsum.photos/seed/ryeblog-migrate/800/450', 'quickstart', 'RyeBlog', '2026-11-23', 'published', 0),

-- === 技术详解 ===
('a4', 'architecture', '为什么选择 Cloudflare Pages + D1？', 'Why Cloudflare Pages + D1?', 'RyeBlog 架构设计哲学：为什么不用 Express + MongoDB，为什么不用 Next.js + Vercel，为什么 Cloudflare 是博客系统的最佳选择。', 'RyeBlog architecture philosophy: why not Express + MongoDB, why not Next.js + Vercel, why Cloudflare is the best choice for a blog system.', '# 为什么选择 Cloudflare Pages + D1？

RyeBlog 的技术选型不是随意的。每一个选择背后都有明确的理由。

## 设计原则

在开始选型之前，先明确 RyeBlog 的设计原则：

1. **零运维**：用户不应该需要管理服务器
2. **零成本**：个人博客不应该有持续开销
3. **SSR 优先**：搜索引擎和读者都应该看到完整 HTML
4. **数据自主**：内容存储在用户自己的数据库中，不依赖第三方
5. **全球快速**：无论读者在哪里，页面都应该秒开

## 为什么不用 Express + MongoDB？

这是最传统的全栈博客方案。但问题是：

- **需要服务器**：你要租 VPS，配置 Nginx，管理 SSL 证书，处理安全更新
- **持续成本**：VPS 每月至少 $5，MongoDB Atlas 免费额度有限
- **运维负担**：服务器宕机、数据库备份、安全补丁……这些都是你的事
- **全球延迟**：服务器在一个地方，其他地区的读者体验差

## 为什么不用 Next.js + Vercel？

这是最流行的现代方案。但问题是：

- **Next.js 太重**：一个博客不需要 React 的组件模型和客户端水合
- **Vercel 限制**：免费版函数执行时间有限，Hobby 计划不支持商业用途
- **数据库选择**：Vercel 没有内置数据库，需要外接 Supabase/PlanetScale 等
- **构建复杂**：每次内容更新都需要重新构建和部署

## 为什么选择 Cloudflare？

### Pages：零运维的托管平台

- **Git 连接自动部署**：推送代码即部署，不需要 CI/CD 配置
- **全球边缘网络**：300+ 城市的 CDN 节点，读者在哪里都快
- **免费额度充足**：每月 500 次构建，无限请求，对个人博客绰绰有余
- **自定义域名 + HTTPS**：自动签发和续期 SSL 证书

### D1：边缘 SQLite 数据库

- **与 Pages 原生集成**：绑定即可用，不需要连接字符串
- **SQLite 语法**：开发者都熟悉，不需要学习新的查询语言
- **免费 5GB**：个人博客的内容存储绰绰有余
- **读写分离**：自动将读请求路由到最近的副本

### Workers Runtime：边缘计算

- **Pages Functions 基于 Workers**：你的代码在全球边缘节点运行
- **冷启动极快**：Workers 的冷启动在 5ms 以内
- **标准 Web API**：使用 `Request`/`Response` 等 Web 标准，学习成本低

## 架构图

```
读者请求 → Cloudflare CDN (全球边缘)
                ↓
         Pages Functions (Workers Runtime)
                ↓
         D1 数据库 (SQLite, 边缘副本)
                ↓
         SSR 渲染 → 完整 HTML 响应
```

整个链路都在 Cloudflare 的边缘网络中完成，没有跨网络调用，延迟极低。

## SSR 铁律

RyeBlog 有一个核心设计原则：**所有公开页面必须服务器端直接输出完整 HTML**。

这意味着：
- 读者不需要等待 JavaScript 加载就能看到内容
- 搜索引擎爬虫可以直接读取完整页面
- 即使浏览器禁用 JavaScript，页面依然正常显示
- 首次内容绘制（FCP）时间极短

这不是"SSR + 客户端水合"的混合方案，而是**纯 SSR**——服务器直接输出 HTML，浏览器直接渲染，没有中间步骤。

## 伪静态路由

RyeBlog 使用 `/:slug` 而非 `/article/:slug`，这不是为了好看，而是有工程考量：

- **更短的 URL**：对读者和搜索引擎都更友好
- **更灵活的路由**：未来可以轻松添加 `/category/:id`、`/tag/:name` 等路由
- **更自然的分享**：`ryeblog.com/my-first-post` 比 `ryeblog.com/article/my-first-post` 更简洁

实现上，通过 Pages Functions 的文件路由系统，`functions/[slug].js` 拦截所有 `/:slug` 请求，在函数内部判断是文章、分类还是其他类型。', 'https://picsum.photos/seed/ryeblog-arch/800/450', 'tech', 'RyeBlog', '2026-11-22', 'published', 0),

('a5', 'ssr-first', 'SSR 优先：零 JS 也能完美渲染', 'SSR First: Perfect Rendering Without JS', 'RyeBlog 的核心设计原则：所有公开页面服务器端直接输出完整 HTML，不依赖 JavaScript 渲染，SEO 天然友好。', 'RyeBlog core design principle: all public pages server-side render complete HTML, no JavaScript dependency, naturally SEO friendly.', '# SSR 优先：零 JS 也能完美渲染

RyeBlog 有一个铁律：**所有公开页面必须服务器端直接输出完整 HTML**。

这不是 Next.js 式的"SSR + 客户端水合"，而是**纯 SSR**——服务器输出 HTML，浏览器直接渲染，没有中间步骤。

## 为什么 SSR 优先？

### 1. SEO 天然友好

搜索引擎爬虫不需要执行 JavaScript 就能读到完整内容。Google 虽然能执行 JS，但执行成本高、延迟大、索引慢。纯 SSR 让你的文章在搜索结果中更快出现。

### 2. 首屏极快

读者请求页面 → 服务器查询数据库 → 渲染 HTML → 返回完整页面。没有"加载骨架屏 → 等待 JS → 请求数据 → 渲染内容"的瀑布流。首次内容绘制（FCP）在毫秒级。

### 3. 不依赖 JavaScript

- 浏览器禁用 JS？页面正常显示
- 网络慢 JS 加载失败？页面正常显示
- 老旧浏览器不支持现代 JS？页面正常显示

### 4. RSS 和爬虫友好

RSS 阅读器、搜索引擎爬虫、社交媒体预览（Open Graph）都能直接读取 HTML，不需要模拟浏览器执行。

## 实现方式

RyeBlog 的 SSR 通过 Pages Functions 实现：

```javascript
// functions/[slug].js
export async function onRequestGet(context) {
  const { env, params } = context;
  const slug = params.slug;
  
  // 从 D1 查询文章
  const article = await env.DB.prepare(
    'SELECT * FROM articles WHERE slug = ? AND status = ?'
  ).bind(slug, 'published').first();
  
  if (!article) {
    return new Response('Not Found', { status: 404 });
  }
  
  // 服务器端渲染完整 HTML
  const html = renderArticlePage(article);
  
  return new Response(html, {
    headers: { 'Content-Type': 'text/html; charset=utf-8' }
  });
}
```

关键点：
- **`onRequestGet`**：Pages Functions 的标准入口，每次请求都在边缘执行
- **`env.DB`**：D1 绑定，直接可用，不需要连接字符串
- **`renderArticlePage`**：纯函数，将文章数据 + 模板 → 完整 HTML 字符串
- **`Response`**：标准 Web API，返回 HTML

## Markdown 服务器端解析

文章内容以 Markdown 存储，在服务器端直接解析为 HTML：

```javascript
function parseMarkdown(text) {
  // 标题、引用、代码块、列表、链接、图片、表格
  // 全部在服务器端转换为 HTML
  return htmlString;
}
```

读者拿到的 HTML 已经包含渲染好的内容，不需要客户端 Markdown 解析器。

## 哪些地方用了 JavaScript？

RyeBlog 的 JavaScript 用量极少，仅用于：
- **主题切换**（亮色/暗色）
- **搜索交互**（搜索页面输入框）
- **文章点击导航**（SPA 式无刷新跳转，可选）

这些功能都是**渐进增强**——即使 JS 完全不可用，页面依然正常工作。

## 与其他方案对比

| 方案 | 首屏速度 | SEO | JS 依赖 | 复杂度 |
|------|---------|-----|---------|--------|
| **RyeBlog (纯 SSR)** | 极快 | 完美 | 无 | 低 |
| Next.js SSR + 水合 | 快 | 好 | 中 | 高 |
| SPA (React/Vue) | 慢 | 差 | 重 | 高 |
| Hugo/Hexo (静态生成) | 极快 | 完美 | 无 | 中 |

RyeBlog 与静态生成器（Hugo/Hexo）的区别在于：RyeBlog 是**动态 SSR**——每次请求都从数据库实时查询和渲染，内容更新不需要重新构建和部署。', 'https://picsum.photos/seed/ryeblog-ssr/800/450', 'tech', 'RyeBlog', '2026-11-21', 'published', 0),

('a6', 'd1-database', 'D1 数据库详解', 'D1 Database Deep Dive', 'Cloudflare D1 是边缘 SQLite 数据库。了解 RyeBlog 如何使用 D1 存储文章、分类、标签，以及 D1 的读写分离和性能特性。', 'Cloudflare D1 is edge SQLite. Learn how RyeBlog uses D1 to store articles, categories, tags, and D1 read-write separation and performance.', '# D1 数据库详解

Cloudflare D1 是基于 SQLite 的边缘数据库。RyeBlog 的所有数据——文章、分类、标签、设置——都存储在 D1 中。

## D1 是什么？

D1 是 Cloudflare 的 Serverless SQL 数据库，基于 SQLite：

- **SQLite 语法**：如果你会用 SQLite，就会用 D1
- **边缘部署**：数据库副本分布在全球 Cloudflare 节点
- **读写分离**：写操作路由到主库，读操作路由到最近的副本
- **与 Pages 原生集成**：通过绑定（Binding）直接使用，不需要连接字符串

## RyeBlog 的数据库 Schema

```sql
-- 文章表
CREATE TABLE articles (
  id TEXT PRIMARY KEY,
  slug TEXT UNIQUE NOT NULL,
  title_zh TEXT NOT NULL,
  title_en TEXT,
  excerpt_zh TEXT,
  excerpt_en TEXT,
  content_zh TEXT,
  content_en TEXT,
  cover TEXT,
  category TEXT,
  author TEXT DEFAULT 'Anonymous',
  date TEXT NOT NULL,
  status TEXT DEFAULT 'draft',
  views INTEGER DEFAULT 0
);

-- 分类表
CREATE TABLE categories (
  id TEXT PRIMARY KEY,
  name_zh TEXT NOT NULL,
  name_en TEXT
);

-- 标签表
CREATE TABLE tags (
  id TEXT PRIMARY KEY,
  name TEXT UNIQUE NOT NULL
);

-- 文章-标签关联表
CREATE TABLE article_tags (
  article_id TEXT NOT NULL,
  tag TEXT NOT NULL,
  PRIMARY KEY (article_id, tag)
);

-- 设置表
CREATE TABLE settings (
  key TEXT PRIMARY KEY,
  value TEXT
);
```

## 读写模式

RyeBlog 的读写模式非常适合 D1：

- **读多写少**：每次页面访问都是读操作，写操作只有发布/编辑文章时
- **读操作**：从最近的边缘副本读取，延迟极低
- **写操作**：路由到主库，有最终一致性延迟（通常 < 1 秒）

这意味着：
- 读者访问文章 → 从最近的 D1 副本读取 → 毫秒级响应
- 你发布新文章 → 写入主库 → 几秒内全球副本同步

## 在 Pages Functions 中使用 D1

```javascript
// 查询所有已发布文章
const articles = await env.DB.prepare(
  'SELECT * FROM articles WHERE status = ? ORDER BY date DESC'
).bind('published').all();

// 查询单篇文章
const article = await env.DB.prepare(
  'SELECT * FROM articles WHERE slug = ? AND status = ?'
).bind(slug, 'published').first();

// 查询文章的标签
const tags = await env.DB.prepare(
  'SELECT tag FROM article_tags WHERE article_id = ?'
).bind(article.id).all();
```

关键 API：
- **`env.DB.prepare(sql)`**：准备 SQL 语句
- **`.bind(...params)`**：绑定参数（防止 SQL 注入）
- **`.first()`**：获取第一行结果
- **`.all()`**：获取所有结果
- **`.run()`**：执行写入操作（INSERT/UPDATE/DELETE）

## D1 的限制

| 限制 | 免费计划 | 付费计划 |
|------|---------|---------|
| 存储 | 5 GB | 10 GB+ |
| 每日读 | 500 万 | 无限 |
| 每日写 | 10 万 | 无限 |
| 数据库数量 | 10 | 50 |
| 单次查询行数 | 5000 | 5000 |

对于个人博客，免费计划完全够用。即使你有 1000 篇文章，每篇 5000 字，总数据量也不到 50 MB。

## 备份和导出

D1 支持导出为 SQL 文件：

```bash
npx wrangler d1 export ryeblog-db --output=backup.sql
```

你也可以在 Cloudflare Dashboard 中手动导出。建议定期备份，虽然 D1 本身有冗余存储。', 'https://picsum.photos/seed/ryeblog-d1/800/450', 'tech', 'RyeBlog', '2026-11-20', 'published', 0),

('a7', 'clean-urls', '伪静态路由设计', 'Clean URL Routing Design', '/:slug 而非 /article/:slug——RyeBlog 的伪静态路由设计背后的工程考量，以及如何避免动态路由拦截静态资源。', '/:slug instead of /article/:slug—the engineering considerations behind RyeBlog clean URL routing, and how to avoid dynamic routes intercepting static assets.', '# 伪静态路由设计

RyeBlog 使用 `/:slug` 而非 `/article/:slug`。URL 更短、更优雅，但背后有重要的工程考量。

## 为什么用伪静态路由？

### 用户体验

- `ryeblog.com/my-first-post` — 干净、简洁、好记
- `ryeblog.com/article/my-first-post` — 冗余、啰嗦

### SEO

搜索引擎更喜欢短 URL。Google 官方文档建议："URL 应该尽量简短，只包含必要的路径段。"

### 分享

在社交媒体、邮件、聊天中粘贴短 URL，更不容易被截断，视觉上也更清爽。

## 实现方式

Cloudflare Pages Functions 使用**文件系统路由**：

```
functions/
  index.js        →  /
  [slug].js       →  /:slug
  category/
    [id].js       →  /category/:id
  tag/
    [name].js     →  /tag/:name
  archive.js      →  /archive
  tags.js         →  /tags
  search.js       →  /search
  about.js        →  /about
  rss.xml.js      →  /rss.xml
  sitemap.xml.js  →  /sitemap.xml
```

`[slug].js` 是动态路由，会拦截所有 `/:slug` 请求。在函数内部，我们查询数据库判断 slug 对应的是文章、分类还是其他类型。

## 静态资源拦截问题

伪静态路由有一个陷阱：`[slug].js` 会拦截**所有** `/:slug` 请求，包括 `/style.css`、`/favicon.ico` 等静态资源。

### 问题复现

当浏览器请求 `/style.css` 时：
1. Pages Functions 路由匹配 `[slug].js`，slug = `style.css`
2. 函数查询数据库，找不到 slug 为 `style.css` 的文章
3. 返回 404，CSS 加载失败
4. 页面样式全部丢失

### 解决方案

在 `[slug].js` 中排除带文件扩展名的路径：

```javascript
export async function onRequestGet(context) {
  const { params } = context;
  const slug = params.slug;
  
  // 排除静态资源（带扩展名的路径）
  if (slug.includes('.')) {
    return context.next();
  }
  
  // 正常的文章查询逻辑
  const article = await context.env.DB.prepare(
    'SELECT * FROM articles WHERE slug = ? AND status = ?'
  ).bind(slug, 'published').first();
  
  if (!article) {
    return new Response('Not Found', { status: 404 });
  }
  
  const html = renderArticlePage(article, context.env);
  return new Response(html, {
    headers: { 'Content-Type': 'text/html; charset=utf-8' }
  });
}
```

`context.next()` 将请求传递给下一个处理器（即 Pages 的静态资源服务），让 Cloudflare 正常返回 CSS、JS、图片等静态文件。

## CDN 缓存陷阱

另一个陷阱：Cloudflare CDN 会缓存响应。如果你部署了错误的 CSS，即使修复后重新部署，CDN 可能仍然返回旧的缓存版本。

### 解决方案

在 HTML 中引用 CSS 时加版本号查询参数：

```html
<link rel="stylesheet" href="/style.css?v=2">
```

每次更新 CSS 后递增版本号，浏览器和 CDN 都会视为新资源，不会返回缓存。

## 路由优先级

Pages Functions 的路由匹配有优先级：

1. **精确匹配**优先：`/archive` 会优先匹配 `archive.js`，而非 `[slug].js`
2. **静态资源**优先：`/style.css` 如果在 `public/` 目录或项目根目录存在，会优先作为静态资源返回
3. **动态路由**最后：`[slug].js` 是最后的兜底路由

这就是为什么我们需要在 `[slug].js` 中手动排除带扩展名的路径——因为某些情况下静态资源匹配可能不生效（取决于 `pages_build_output_dir` 配置）。', 'https://picsum.photos/seed/ryeblog-routing/800/450', 'tech', 'RyeBlog', '2026-11-19', 'published', 0),

-- === 部署指南 ===
('a8', 'cf-pages-deploy', 'Cloudflare Pages 部署全流程', 'Cloudflare Pages Full Deployment Guide', '从创建 Pages 项目到绑定 D1 数据库，从初始化 schema 到部署上线的完整流程，包含每一步的截图说明和常见问题排查。', 'From creating a Pages project to binding D1 database, from initializing schema to going live—complete walkthrough with troubleshooting.', '# Cloudflare Pages 部署全流程

这是 RyeBlog 最详细的部署指南，覆盖从零到上线的每一个步骤。

## 准备工作

### 需要的账号

- **GitHub** 账号（用于 Fork 仓库和 Git 自动部署）
- **Cloudflare** 账号（免费即可，不需要付费计划）

### 需要的工具

- **Wrangler CLI**（可选，用于本地开发和数据库初始化）

```bash
npm install -g wrangler
wrangler login
```

## 步骤 1：Fork 仓库

1. 访问 [github.com/ryeblog/ryeblog](https://github.com/ryeblog/ryeblog)
2. 点击 **Fork** → 选择你的账号 → 确认

## 步骤 2：创建 Pages 项目

1. 登录 [Cloudflare Dashboard](https://dash.cloudflare.com)
2. 左侧菜单 → **Workers & Pages**
3. **Create** → **Pages** → **Connect to Git**
4. 授权 Cloudflare 访问你的 GitHub
5. 选择你 Fork 的 `ryeblog` 仓库
6. 配置构建设置：

| 配置项 | 值 |
|--------|-----|
| Project name | `ryeblog`（或任意名称） |
| Production branch | `main` |
| Framework preset | None |
| Build command | （留空） |
| Build output directory | `.` |
| Root directory | `/` |

7. **Save and Deploy**

部署完成后，你会得到一个 `https://ryeblog-xxx.pages.dev` 地址。

## 步骤 3：创建 D1 数据库

1. Dashboard → **D1** → **Create database**
2. 名称：`ryeblog-db`
3. 创建后，进入数据库详情页，记下 **Database ID**

## 步骤 4：绑定 D1 到 Pages

1. Pages 项目 → **Settings** → **Functions**
2. 找到 **D1 database bindings**
3. **Add binding**：

| 配置项 | 值 |
|--------|-----|
| Variable name | `DB` |
| D1 database | `ryeblog-db` |

4. **Save**

> **重要**：Variable name 必须是 `DB`，代码中通过 `env.DB` 访问数据库。

## 步骤 5：初始化数据库

### 方式 A：使用 Wrangler CLI（推荐）

```bash
# 设置环境变量
$env:CLOUDFLARE_API_TOKEN="your-api-token"

# 执行初始化 SQL
npx wrangler d1 execute ryeblog-db --remote --file=schema/init.sql
npx wrangler d1 execute ryeblog-db --remote --file=schema/seed.sql
```

### 方式 B：在 Dashboard 中手动执行

1. D1 → `ryeblog-db` → **Query**
2. 复制 `schema/init.sql` 的内容粘贴执行
3. 复制 `schema/seed.sql` 的内容粘贴执行

## 步骤 6：重新部署

绑定 D1 后需要重新部署才能生效：

1. Pages 项目 → **Deployments**
2. 找到最新的部署 → **Retry deployment**

或者推送一个新 commit 到 GitHub，自动触发部署。

## 步骤 7：验证

访问你的 `.pages.dev` 地址，检查：

- [ ] 首页正常显示文章列表
- [ ] 点击文章可以正常打开
- [ ] 分类页面正常工作
- [ ] 标签页面正常工作
- [ ] 搜索功能正常
- [ ] RSS 订阅 `/rss.xml` 正常
- [ ] 站点地图 `/sitemap.xml` 正常

## 常见问题排查

### 页面显示但样式丢失

**原因**：`[slug].js` 拦截了 `/style.css` 请求。

**解决**：确保 `[slug].js` 中有静态资源排除逻辑（`slug.includes('.')` → `context.next()`）。

### 页面报 500 错误

**原因**：D1 绑定未生效或数据库未初始化。

**解决**：
1. 检查 Pages Settings → Functions 中 D1 绑定是否存在
2. 检查 Variable name 是否为 `DB`
3. 重新部署
4. 确认 `init.sql` 已执行

### CSS 更新后样式不变

**原因**：CDN 缓存了旧的 CSS。

**解决**：在 HTML 中 CSS 引用加版本号 `?v=2`，每次更新递增。

### 自定义域名访问 404

**原因**：DNS 记录未正确配置。

**解决**：
1. 确认域名在 Cloudflare 上有 Zone
2. 确认 CNAME 记录指向 `xxx.pages.dev`
3. 确认 DNS 记录状态为 Proxied（橙色云朵）', 'https://picsum.photos/seed/ryeblog-cf-deploy/800/450', 'deploy', 'RyeBlog', '2026-11-18', 'published', 0),

('a9', 'custom-domain', '自定义域名配置与 HTTPS', 'Custom Domain Configuration & HTTPS', '绑定你自己的域名到 RyeBlog，Cloudflare 自动签发和续期 SSL 证书，全程 HTTPS。', 'Bind your own domain to RyeBlog, Cloudflare auto-issues and renews SSL certificates, full HTTPS.', '# 自定义域名配置与 HTTPS

RyeBlog 默认使用 `.pages.dev` 子域名，但你可以在 5 分钟内绑定自己的域名。

## 前提条件

- 你拥有一个域名
- 域名的 DNS 托管在 Cloudflare（即域名在 Cloudflare 上有 Zone）

如果你的域名不在 Cloudflare，需要先添加：

1. Dashboard → **Add a site** → 输入你的域名
2. Cloudflare 会给你两个 NS 记录
3. 在你的域名注册商处将 NS 记录改为 Cloudflare 的
4. 等待 DNS 生效（通常几分钟到几小时）

## 绑定自定义域名

1. Pages 项目 → **Custom domains** → **Set up a custom domain**
2. 输入你的域名（如 `myblog.com`）
3. Cloudflare 自动添加 CNAME 记录：`myblog.com` → `ryeblog-xxx.pages.dev`
4. 状态变为 **Proxied**（橙色云朵）

Cloudflare 会自动：
- 签发 SSL 证书（通常几分钟内）
- 配置 HTTPS 重定向
- 续期证书（自动，无需操作）

## 绑定子域名

如果你想用子域名（如 `blog.example.com`）：

1. 同样在 Custom domains 中添加 `blog.example.com`
2. Cloudflare 自动添加 CNAME 记录

## 绑定多个域名

一个 Pages 项目可以绑定最多 100 个自定义域名。所有域名都会指向同一个项目。

例如：
- `ryeblog.com` → 主站
- `maomao.ryeblog.com` → 演示站（通过代码中检测 host 返回不同内容）

## HTTPS 配置

Cloudflare 自动处理 HTTPS，但你可以在 **SSL/TLS** 设置中调整：

| 模式 | 说明 | 推荐度 |
|------|------|--------|
| Off | 不加密 | 不推荐 |
| Flexible | 浏览器→CF 加密，CF→源站不加密 | 不推荐 |
| Full | 全程加密，但源站证书可以自签 | 可用 |
| Full (strict) | 全程加密，源站证书必须有效 | 推荐 |

对于 Pages，默认就是 **Full (strict)**，不需要额外配置。

## 强制 HTTPS 重定向

在 Cloudflare Dashboard：

1. **SSL/TLS** → **Edge Certificates**
2. 开启 **Always Use HTTPS**

这样所有 HTTP 请求都会自动 301 重定向到 HTTPS。

## www 重定向

如果你想 `www.myblog.com` 重定向到 `myblog.com`：

1. 添加 `www.myblog.com` 作为自定义域名
2. 在 **Rules** → **Redirect Rules** 中添加规则：
   - 条件：`hostname equals www.myblog.com`
   - 动作：`redirect to myblog.com with 301`

或者更简单的方式：在 Pages 的 Custom domains 中，将 `myblog.com` 设为 Primary domain，`www.myblog.com` 设为 Alternate，Cloudflare 会自动处理重定向。', 'https://picsum.photos/seed/ryeblog-domain/800/450', 'deploy', 'RyeBlog', '2026-11-17', 'published', 0),

('a10', 'local-dev', '本地开发环境搭建', 'Local Development Environment Setup', '使用 Wrangler + D1 模拟在本地开发 RyeBlog，包括本地数据库初始化、热重载和调试技巧。', 'Set up local development for RyeBlog with Wrangler + D1 simulation, including local database init, hot reload, and debugging tips.', '# 本地开发环境搭建

你可以在本地开发和调试 RyeBlog，不需要每次都部署到 Cloudflare。

## 安装 Wrangler

```bash
npm install -g wrangler
wrangler login
```

`wrangler login` 会打开浏览器让你授权 Cloudflare 账号。

## 项目结构

```
inkwell-cf/
  functions/          ← Pages Functions（路由）
    index.js          ← 首页
    [slug].js         ← 文章页
    archive.js        ← 归档页
    tags.js           ← 标签页
    search.js         ← 搜索页
    about.js          ← 关于页
    category/
      [id].js         ← 分类页
    tag/
      [name].js       ← 标签筛选页
    rss.xml.js        ← RSS
    sitemap.xml.js    ← 站点地图
  lib/                ← 共享库
    db.js             ← 数据库查询
    render.js         ← SSR 渲染
  schema/             ← 数据库 Schema
    init.sql          ← 建表语句
    seed.sql          ← 种子数据
  public/             ← 静态资源
    style.css         ← 博客样式
  wrangler.toml       ← Wrangler 配置
  package.json
```

## wrangler.toml 配置

```toml
name = "ryeblog"
compatibility_date = "2024-01-01"
pages_build_output_dir = "."

[[d1_databases]]
binding = "DB"
database_name = "ryeblog-db"
database_id = "your-database-id"
```

## 启动本地开发服务器

```bash
npx wrangler pages dev .
```

这会启动一个本地服务器（默认 `http://localhost:8788`），模拟 Cloudflare Pages 的运行环境，包括 D1 数据库。

## 初始化本地数据库

本地开发使用的是本地 D1 模拟（SQLite 文件），需要单独初始化：

```bash
npx wrangler d1 execute ryeblog-db --local --file=schema/init.sql
npx wrangler d1 execute ryeblog-db --local --file=schema/seed.sql
```

注意 `--local` 参数，表示操作本地数据库而非远程。

## 开发流程

1. **修改代码**：编辑 `functions/`、`lib/`、`public/` 中的文件
2. **本地预览**：`wrangler pages dev .` 会自动热重载
3. **测试功能**：在浏览器中访问 `localhost:8788`
4. **推送部署**：`git push` 触发 Cloudflare 自动部署

## 调试技巧

### 查看日志

```bash
npx wrangler pages dev . --log-level=debug
```

### 在代码中输出日志

```javascript
export async function onRequestGet(context) {
  console.log('Request:', context.request.url);
  console.log('Params:', context.params);
  
  const article = await context.env.DB.prepare(
    'SELECT * FROM articles WHERE slug = ?'
  ).bind(context.params.slug).first();
  
  console.log('Article:', article);
  
  // ...
}
```

日志会输出到运行 `wrangler pages dev` 的终端。

### 查询本地数据库

```bash
npx wrangler d1 execute ryeblog-db --local --command="SELECT * FROM articles"
```

### 重置本地数据库

```bash
npx wrangler d1 execute ryeblog-db --local --command="DELETE FROM articles"
npx wrangler d1 execute ryeblog-db --local --file=schema/seed.sql
```

## 常见问题

### `pages_build_output_dir` 配置错误

如果本地开发服务器返回 404，检查 `wrangler.toml` 中 `pages_build_output_dir` 是否设置为 `.`（项目根目录）。

### D1 绑定未生效

如果代码中 `context.env.DB` 为 `undefined`，检查：
1. `wrangler.toml` 中 `[[d1_databases]]` 配置是否正确
2. `binding` 是否为 `DB`
3. 本地数据库是否已初始化（`--local` 参数）

### 端口冲突

如果 8788 端口被占用：

```bash
npx wrangler pages dev . --port=3000
```', 'https://picsum.photos/seed/ryeblog-local-dev/800/450', 'deploy', 'RyeBlog', '2026-11-16', 'published', 0),

-- === 主题开发 ===
('a11', 'theme-intro', '主题系统入门', 'Theme System Introduction', '理解 RyeBlog 的模板结构：SSR 渲染函数、CSS 变量系统、布局组件，以及如何开始定制你的博客外观。', 'Understand RyeBlog template structure: SSR render functions, CSS variable system, layout components, and how to start customizing.', '# 主题系统入门

RyeBlog 的主题系统基于 SSR 渲染函数和 CSS 变量。没有模板引擎，没有 JSX，只有纯 JavaScript 函数和 CSS。

## 核心理念

RyeBlog 的主题系统遵循一个原则：**简单到任何人都能改**。

- 没有 Handlebars/EJS/Pug 等模板引擎
- 没有 React/Vue 等组件框架
- 没有 Webpack/Vite 等构建工具
- 只有 **JavaScript 函数** 生成 HTML 字符串 + **CSS 文件** 控制样式

## 模板结构

### 渲染函数（lib/render.js）

所有页面渲染都在 `lib/render.js` 中：

```javascript
// 页面外壳（所有页面共用）
export function renderPage(options, data) {
  const { title, content } = options;
  return `<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <title>${title}</title>
  <link rel="stylesheet" href="/style.css">
</head>
<body>
  <header>...</header>
  <div id="app">${content}</div>
  <footer>...</footer>
</body>
</html>`;
}

// 首页内容
export function buildHomepageContent(articles, categories, tags, settings) {
  return `<div class="layout-wrapper">
    <main class="main-content">
      ${articles.map(a => renderArticleItem(a, categories)).join('')}
    </main>
    <aside class="sidebar-right">...</aside>
  </div>`;
}

// 文章详情页内容
export function buildArticleContent(article, categories, tags, settings) {
  return `<article class="article-detail">
    <h1>${article.title_zh}</h1>
    <div class="article-content">${parseMarkdown(article.content_zh)}</div>
  </article>`;
}
```

### CSS（public/style.css）

所有样式在一个 CSS 文件中，使用 CSS 变量定义主题色：

```css
:root {
  --bg: #faf8f3;
  --text: #2c2c2c;
  --accent: #c4554d;
  --accent-light: #f5e6e3;
  --border: #e8e4dc;
  --font-serif: 'Noto Serif SC', Georgia, serif;
  --font-sans: -apple-system, 'Segoe UI', sans-serif;
}
```

## 如何定制主题

### 1. 改配色

修改 CSS 变量即可改变全站配色：

```css
:root {
  --bg: #1a1a2e;        /* 深色背景 */
  --text: #e0e0e0;      /* 浅色文字 */
  --accent: #e94560;    /* 亮色强调 */
  --accent-light: #16213e;
  --border: #0f3460;
}
```

### 2. 改字体

```css
:root {
  --font-serif: 'Lora', Georgia, serif;
  --font-sans: 'Inter', -apple-system, sans-serif;
}
```

在 HTML 中引入 Google Fonts：

```html
<link href="https://fonts.googleapis.com/css2?family=Lora&family=Inter&display=swap" rel="stylesheet">
```

### 3. 改布局

修改 `renderPage` 函数中的 HTML 结构，或修改 CSS 中的布局属性：

```css
.layout-wrapper {
  display: grid;
  grid-template-columns: 1fr 300px;  /* 改为单列：1fr */
  gap: 2rem;
}
```

### 4. 添加新页面

1. 在 `functions/` 中创建新路由文件
2. 在 `lib/render.js` 中添加对应的渲染函数
3. 在 CSS 中添加对应样式

## CSS 变量系统

RyeBlog 的所有视觉属性都通过 CSS 变量控制，修改变量即可改变全站风格：

| 变量 | 作用 | 默认值 |
|------|------|--------|
| `--bg` | 页面背景 | `#faf8f3` |
| `--text` | 正文文字 | `#2c2c2c` |
| `--accent` | 强调色 | `#c4554d` |
| `--accent-light` | 强调色浅色 | `#f5e6e3` |
| `--border` | 边框色 | `#e8e4dc` |
| `--font-serif` | 衬线字体 | `'Noto Serif SC', Georgia, serif` |
| `--font-sans` | 无衬线字体 | `-apple-system, 'Segoe UI', sans-serif` |
| `--content-width` | 内容区宽度 | `720px` |
| `--sidebar-width` | 侧边栏宽度 | `300px` |

## 深色模式

RyeBlog 内置深色模式，通过 `data-theme` 属性切换：

```css
:root[data-theme="dark"] {
  --bg: #1a1a2e;
  --text: #e0e0e0;
  --accent: #e94560;
  --accent-light: #16213e;
  --border: #0f3460;
}
```

切换按钮在 `renderPage` 中已经内置，用户点击即可切换，选择保存在 `localStorage`。', 'https://picsum.photos/seed/ryeblog-theme-intro/800/450', 'theme', 'RyeBlog', '2026-11-15', 'published', 0),

('a12', 'theme-from-scratch', '从零开发一个 RyeBlog 主题', 'Develop a RyeBlog Theme from Scratch', '完整的主题开发教程：从设计构思到 CSS 实现，从渲染函数修改到新页面添加，手把手教你打造独一无二的博客主题。', 'Complete theme development tutorial: from design concept to CSS implementation, from render function modification to new page addition.', '# 从零开发一个 RyeBlog 主题

这篇教程带你从零开始，为 RyeBlog 开发一个全新的主题。

## 设计构思

我们以"科技未来感"主题为例：
- 深色底（`#0a0e27`）
- 渐变与微光效果
- 等宽字体点缀
- 几何线条装饰

## 第一步：定义 CSS 变量

在 `public/style.css` 中替换 `:root` 变量：

```css
:root {
  /* 科技未来感配色 */
  --bg: #0a0e27;
  --bg-secondary: #111538;
  --text: #c8d0e8;
  --text-secondary: #6b7299;
  --accent: #00d4ff;
  --accent-light: rgba(0, 212, 255, 0.1);
  --accent-glow: rgba(0, 212, 255, 0.3);
  --border: rgba(100, 120, 200, 0.2);
  
  /* 字体 */
  --font-serif: 'JetBrains Mono', 'Courier New', monospace;
  --font-sans: 'Inter', -apple-system, sans-serif;
  --font-mono: 'JetBrains Mono', monospace;
  
  /* 布局 */
  --content-width: 760px;
  --sidebar-width: 280px;
  --radius: 4px;
}
```

## 第二步：深色模式

由于主题本身就是深色，亮色模式作为备选：

```css
:root[data-theme="light"] {
  --bg: #f0f4ff;
  --bg-secondary: #ffffff;
  --text: #1a1a2e;
  --text-secondary: #555;
  --accent: #0066cc;
  --accent-light: rgba(0, 102, 204, 0.1);
  --border: rgba(0, 102, 204, 0.2);
}
```

## 第三步：渐变和微光效果

```css
.site-header {
  background: linear-gradient(135deg, var(--bg) 0%, var(--bg-secondary) 100%);
  border-bottom: 1px solid var(--border);
  box-shadow: 0 0 20px var(--accent-glow);
}

.article-item-title {
  color: var(--text);
  transition: color 0.2s, text-shadow 0.2s;
}

.article-item:hover .article-item-title {
  color: var(--accent);
  text-shadow: 0 0 10px var(--accent-glow);
}
```

## 第四步：几何线条装饰

```css
.article-item {
  position: relative;
  border-left: 2px solid transparent;
  transition: border-color 0.2s;
}

.article-item:hover {
  border-left-color: var(--accent);
}

.article-detail-title {
  position: relative;
  padding-bottom: 1rem;
}

.article-detail-title::after {
  content: '';
  position: absolute;
  bottom: 0;
  left: 0;
  width: 60px;
  height: 2px;
  background: linear-gradient(90deg, var(--accent), transparent);
}
```

## 第五步：修改渲染函数

如果需要修改 HTML 结构，编辑 `lib/render.js`：

```javascript
// 在 header 中添加装饰元素
export function renderPage(options, data) {
  return `<!DOCTYPE html>
<html lang="zh-CN" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <title>${title}</title>
  <link rel="stylesheet" href="/style.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter&family=JetBrains+Mono&display=swap" rel="stylesheet">
</head>
<body>
  <div class="grid-overlay"></div>
  <header class="site-header">
    <div class="site-brand">
      <span class="site-brand-name">${siteName}</span>
      <span class="site-brand-tag">// blog</span>
    </div>
    <nav class="site-nav">...</nav>
  </header>
  <div id="app">${content}</div>
  <footer>...</footer>
</body>
</html>`;
}
```

## 第六步：添加装饰元素

```css
.grid-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-image: 
    linear-gradient(rgba(100, 120, 200, 0.03) 1px, transparent 1px),
    linear-gradient(90deg, rgba(100, 120, 200, 0.03) 1px, transparent 1px);
  background-size: 50px 50px;
  pointer-events: none;
  z-index: 0;
}
```

## 第七步：代码块样式

科技主题需要漂亮的代码块：

```css
pre {
  background: var(--bg-secondary);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 1rem;
  overflow-x: auto;
  position: relative;
}

pre::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  width: 3px;
  height: 100%;
  background: var(--accent);
}

code {
  font-family: var(--font-mono);
  color: var(--accent);
  font-size: 0.9em;
}
```

## 测试和迭代

1. 启动本地开发服务器：`npx wrangler pages dev .`
2. 在浏览器中查看效果
3. 反复调整 CSS 变量和样式
4. 满意后推送到 GitHub 自动部署

## 主题开发清单

- [ ] CSS 变量定义（配色、字体、布局）
- [ ] 深色/亮色模式
- [ ] Header 样式
- [ ] 文章列表样式
- [ ] 文章详情页样式
- [ ] 侧边栏样式
- [ ] 代码块样式
- [ ] 引用块样式
- [ ] 表格样式
- [ ] 移动端响应式
- [ ] Footer 样式
- [ ] 搜索页面样式
- [ ] 归档页面样式', 'https://picsum.photos/seed/ryeblog-theme-dev/800/450', 'theme', 'RyeBlog', '2026-11-14', 'published', 0),

('a13', 'default-theme', '默认主题「安静阅读」的设计理念', 'Default Theme "Quiet Reading" Design Philosophy', 'RyeBlog 默认主题的设计理念：暖色纸感背景、衬线正文、克制的强调色，每个像素都为阅读体验而设计。', 'RyeBlog default theme design philosophy: warm paper background, serif body text, restrained accent color, every pixel designed for reading.', '# 默认主题「安静阅读」的设计理念

RyeBlog 的默认主题叫"安静阅读"。这篇文档解释每个设计决策背后的理由。

## 设计目标

一个好的博客主题应该**让读者忘记主题的存在**。读者的注意力应该在文字上，不在装饰上。

"安静阅读"主题的设计目标：
1. **减少视觉噪音** — 没有花哨的动画，没有刺眼的颜色
2. **优化阅读体验** — 字体、行距、宽度都为长时间阅读调校
3. **克制使用强调色** — 强调色只在必要的地方出现
4. **纸感背景** — 不是纯白，而是微暖的纸色，减少屏幕疲劳

## 配色选择

### 背景：`#faf8f3`

不是纯白（`#ffffff`），而是带有微暖色调的纸色。纯白在长时间阅读时会造成眼睛疲劳，微暖的纸色更接近实体书的阅读体验。

### 正文：`#2c2c2c`

不是纯黑（`#000000`），而是深灰。纯黑与纯白的对比度过高，在屏幕上阅读时刺眼。`#2c2c2c` 提供足够的对比度，同时更加柔和。

### 强调色：`#c4554d`

柿子红，一种温暖但不张扬的颜色。用于：
- 分类标签
- 链接 hover 状态
- 标题装饰线

强调色在整个页面中**出现频率不超过 5%**——克制是关键。

### 边框：`#e8e4dc`

接近背景色的暖灰，用于分隔线、卡片边框。不抢眼，但提供清晰的视觉边界。

## 字体选择

### 正文：衬线体

```css
--font-serif: 'Noto Serif SC', Georgia, serif;
```

衬线体在中文阅读中优势明显：
- 笔画更清晰，小字号下辨识度更高
- 视觉节奏感更强，适合长文阅读
- 与纸感背景搭配，营造"读书"而非"看屏幕"的感觉

### UI 元素：无衬线体

```css
--font-sans: -apple-system, 'Segoe UI', sans-serif;
```

导航、标签、按钮等 UI 元素使用无衬线体，与正文形成层次区分。

## 排版参数

### 行高：`1.8`

中文阅读的最佳行高在 1.7-1.9 之间。`1.8` 是经过反复测试的选择——足够宽松让眼睛舒适，又不会太松散导致行间失去关联感。

### 内容宽度：`720px`

中文阅读的最佳行宽在 30-40 个字符之间。720px 容纳约 35 个中文字符，正好在最佳范围内。太宽会导致阅读时视线跳跃距离过大，太窄则频繁换行打断节奏。

### 段落间距：`1.5rem`

段落之间有充足的空白，让读者在心理上"喘口气"。密集的段落会让长文阅读变得压抑。

## 布局设计

### 双栏布局

```
┌─────────────────────┬──────────┐
│                     │          │
│   文章列表/内容      │  侧边栏   │
│                     │          │
└─────────────────────┴──────────┘
```

- 左侧主内容区：文章列表或文章详情
- 右侧侧边栏：作者信息、最近文章、分类

在移动端，侧边栏移到内容下方，保持单栏阅读体验。

### 移动端优先

所有样式以移动端为基础，通过 `@media` 查询逐步增强桌面端体验。确保在手机上阅读体验同样优秀。

## 深色模式

深色模式不是简单反转颜色，而是重新调校的配色：

```css
:root[data-theme="dark"] {
  --bg: #1a1a2e;
  --text: #d4d4d4;
  --accent: #e94560;
  --border: #2a2a4a;
}
```

- 背景不是纯黑，而是深蓝灰，减少 OLED 屏幕的刺眼感
- 文字不是纯白，而是浅灰，降低对比度
- 强调色从柿子红变为亮红，在深色背景上更醒目

## 动效原则

"安静阅读"主题的动效遵循**少即是多**的原则：

- 页面切换：无动画（直接跳转，最快）
- Hover 效果：仅颜色变化，无位移、无缩放
- 主题切换：无过渡动画（即时切换，避免闪烁）

唯一的"动效"是文章列表项的 hover 颜色变化，用 `transition: color 0.2s` 实现。', 'https://picsum.photos/seed/ryeblog-default-theme/800/450', 'theme', 'RyeBlog', '2026-11-13', 'published', 0),

-- === 使用手册 ===
('a14', 'writing-guide', '写作与发布指南', 'Writing and Publishing Guide', 'RyeBlog 的 Markdown 编辑器全功能指南：支持的语法、代码高亮、图片插入、草稿管理、发布流程。', 'RyeBlog Markdown editor full guide: supported syntax, code highlighting, image insertion, draft management, publishing workflow.', '# 写作与发布指南

RyeBlog 使用 Markdown 作为写作格式。这篇指南覆盖所有写作和发布功能。

## Markdown 语法支持

RyeBlog 的 Markdown 解析器支持以下语法：

### 标题

```markdown
# 一级标题
## 二级标题
### 三级标题
```

### 强调

```markdown
**粗体文本**
`行内代码`
```

### 引用

```markdown
> 这是一段引用文字
```

### 列表

```markdown
- 无序列表项
- 另一项

1. 有序列表项
2. 另一项
```

### 代码块

```markdown
```javascript
function hello() {
  console.log('Hello, RyeBlog!');
}
```
```

### 链接和图片

```markdown
[链接文字](https://example.com)
![图片描述](https://example.com/image.jpg)
```

### 表格

```markdown
| 列1 | 列2 | 列3 |
|-----|-----|-----|
| A   | B   | C   |
```

## 发布流程

### 1. 创建文章

在 D1 数据库中插入新文章记录：

```sql
INSERT INTO articles (id, slug, title_zh, excerpt_zh, content_zh, category, author, date, status) 
VALUES (
  'a7',
  'my-new-post',
  '我的新文章',
  '这是文章摘要',
  '# 我的新文章\n\n正文内容...',
  'tech',
  'RyeBlog',
  '2026-11-25',
  'draft'
);
```

### 2. 草稿状态

`status = 'draft'` 的文章不会在公开页面显示。你可以反复修改，直到满意。

### 3. 发布

将 `status` 改为 `published`：

```sql
UPDATE articles SET status = 'published' WHERE id = 'a7';
```

文章立即在首页和 RSS 中可见。

### 4. 修改

直接 UPDATE 文章内容：

```sql
UPDATE articles SET content_zh = '修改后的内容...' WHERE id = 'a7';
```

修改即时生效，不需要重新构建或部署。

## Slug 规则

Slug 是文章 URL 的唯一标识（`ryeblog.com/:slug`）：

- 使用英文、数字、连字符
- 保持简短有意义：`my-first-post` 而非 `post-001`
- 不可重复（数据库有 UNIQUE 约束）
- 一旦发布尽量不要修改（会影响 SEO 和外链）

## 封面图片

每篇文章可以设置封面图片：

```sql
UPDATE articles SET cover = 'https://example.com/cover.jpg' WHERE id = 'a7';
```

封面图片显示在：
- 首页文章列表（缩略图）
- 文章详情页顶部（大图）
- RSS 订阅中

建议封面尺寸：1200×500px 或 800×450px。

## 分类和标签

### 分类

每篇文章属于一个分类。分类在 `categories` 表中定义：

```sql
INSERT INTO categories (id, name_zh, name_en) VALUES ('tech', '技术详解', 'Technical Deep Dive');
```

文章通过 `category` 字段关联分类。

### 标签

文章可以有多个标签。标签在 `tags` 表中定义，通过 `article_tags` 关联：

```sql
INSERT INTO tags (id, name) VALUES ('cloudflare', 'cloudflare');
INSERT INTO article_tags (article_id, tag) VALUES ('a7', 'cloudflare');
```

## 双语支持

RyeBlog 支持中英双语内容。每篇文章有 `title_zh/title_en`、`excerpt_zh/excerpt_en`、`content_zh/content_en` 字段。

如果只写中文，英文字段留空即可，系统会自动回退到中文内容。

## 自动统计

RyeBlog 自动统计：
- **字数**：从内容中计算（去除 Markdown 语法标记）
- **阅读时长**：按每分钟 300 字估算
- **浏览量**：每次访问文章详情页时 `views + 1`', 'https://picsum.photos/seed/ryeblog-writing/800/450', 'manual', 'RyeBlog', '2026-11-12', 'published', 0),

('a15', 'content-organization', '标签、分类与归档', 'Tags, Categories, and Archive', '内容组织最佳实践：如何设计分类体系、如何有效使用标签、归档页面如何帮助读者发现内容。', 'Content organization best practices: how to design category systems, use tags effectively, and how archive pages help readers discover content.', '# 标签、分类与归档

好的内容组织让读者更容易发现你的文章。这篇指南帮你设计清晰的内容结构。

## 分类 vs 标签

| 维度 | 分类 | 标签 |
|------|------|------|
| 数量 | 少（3-6 个） | 多（不限） |
| 关系 | 互斥（一篇文章一个分类） | 包容（一篇文章多个标签） |
| 定位 | 内容的大方向 | 内容的具体主题 |
| URL | `/category/:id` | `/tag/:name` |
| 显示 | 导航栏、侧边栏 | 标签云、文章底部 |

## 分类设计原则

### 1. 少即是多

3-6 个分类足够。太多分类会让读者困惑，太少则失去导航价值。

### 2. 互斥性

一篇文章只属于一个分类。如果一篇文章可以归入多个分类，说明分类设计有重叠。

### 3. 稳定性

分类一旦确定，尽量不要频繁变动。分类是博客的骨架，频繁变动会破坏读者的导航习惯。

### 4. 有意义

分类名称应该让读者一眼看出内容方向。避免使用"杂记""其他"这类无信息量的分类。

## 标签使用技巧

### 1. 具体而非宽泛

- 好：`cloudflare-pages`、`d1-database`、`ssr`
- 差：`web`、`tech`、`programming`

### 2. 一致性

同一主题使用同一个标签。不要 `cloudflare` 和 `cf` 混用。

### 3. 适度数量

每篇文章 3-5 个标签最佳。太少不够描述，太多变成噪音。

### 4. 标签云

RyeBlog 的标签云页面（`/tags`）根据文章数量自动调整标签大小：
- **大标签**：5 篇以上文章
- **中标签**：2-5 篇文章
- **小标签**：1 篇文章

## 归档页面

归档页面（`/archive`）按年份分组显示所有文章，帮助读者：

1. **浏览历史内容** — 快速看到你写过哪些文章
2. **发现时间线索** — 了解你的写作轨迹和兴趣变化
3. **查找特定文章** — 按时间范围缩小搜索

## 内容组织最佳实践

### 新博客的初始分类

建议从 3-4 个分类开始，随着内容增加再扩展：

```
技术详解 — 技术深度文章
部署指南 — 操作性教程
使用手册 — 功能说明
案例展示 — 实际应用
```

### 标签命名规范

- 使用英文（与 URL 兼容）
- 使用连字符分隔单词：`cloudflare-pages` 而非 `cloudflarepages`
- 保持简短：3-15 个字符
- 避免重复：`deploy` 和 `deployment` 选一个

### 定期整理

随着文章增多，定期检查：
- 是否有标签只有 1 篇文章（考虑合并或删除）
- 是否有分类文章数量严重不均（考虑拆分或合并）
- 是否有标签名称不一致（统一命名）', 'https://picsum.photos/seed/ryeblog-organization/800/450', 'manual', 'RyeBlog', '2026-11-11', 'published', 0),

('a16', 'seo-guide', 'SEO 优化指南', 'SEO Optimization Guide', 'RyeBlog 内置的搜索引擎友好设计：SSR 直出、语义化 HTML、自动站点地图、RSS 订阅、Open Graph 标签。', 'RyeBlog built-in SEO friendly design: SSR direct output, semantic HTML, auto sitemap, RSS, Open Graph tags.', '# SEO 优化指南

RyeBlog 在架构层面就为 SEO 做好了准备。这篇指南解释 RyeBlog 的 SEO 优势和你能做的额外优化。

## RyeBlog 的 SEO 内置优势

### 1. SSR 直出完整 HTML

搜索引擎爬虫不需要执行 JavaScript 就能读到完整内容。这是 RyeBlog 最重要的 SEO 优势。

对比 SPA（单页应用）：
- SPA：爬虫看到空的 `<div id="app"></div>`，需要执行 JS 才能获取内容
- RyeBlog：爬虫直接看到完整的文章标题、正文、链接

### 2. 语义化 HTML

RyeBlog 使用 HTML5 语义化标签：

```html
<header> — 页面头部
<nav> — 导航
<main> — 主内容区
<article> — 文章内容
<aside> — 侧边栏
<footer> — 页面底部
<h1> - <h3> — 标题层级
```

这些标签帮助搜索引擎理解页面结构和内容层次。

### 3. 自动站点地图

RyeBlog 自动生成 `/sitemap.xml`，包含：
- 首页
- 所有已发布文章
- 所有分类页面
- 所有标签页面
- 归档、搜索等固定页面

搜索引擎可以通过站点地图快速发现你的所有内容。

### 4. RSS 订阅

`/rss.xml` 自动生成标准 RSS 2.0 feed，包含所有已发布文章的标题、链接、摘要和发布日期。RSS 阅读器和内容聚合平台可以通过它订阅你的更新。

### 5. 干净的 URL

`/:slug` 伪静态路由，URL 简短且包含关键词，对搜索引擎友好。

## 你能做的额外优化

### 1. 写好文章摘要

每篇文章的 `excerpt_zh` 字段会显示在：
- 首页文章列表
- 搜索结果
- RSS feed
- 搜索引擎结果页（作为 meta description）

好的摘要应该：
- 50-100 字
- 包含核心关键词
- 吸引点击

### 2. 选择好的 Slug

Slug 是 URL 的一部分，也是搜索引擎判断内容相关性的依据：

- 好：`/cloudflare-d1-tutorial`
- 差：`/post-001`

### 3. 合理使用标题层级

```markdown
# 文章标题（h1，每篇只有一个）
## 大段落标题（h2）
### 小段落标题（h3）
```

搜索引擎通过标题层级理解内容结构。不要跳级（h1 → h3），不要用标题做装饰。

### 4. 内部链接

在文章中链接到你自己的其他文章：

```markdown
如果你还不了解 D1，可以先阅读 [D1 数据库详解](/d1-database)。
```

内部链接帮助搜索引擎发现更多页面，也帮助读者深入阅读。

### 5. 图片 Alt 文本

```markdown
![RyeBlog 部署流程图](/images/deploy-flow.png)
```

Alt 文本是搜索引擎理解图片内容的唯一途径，也是无障碍访问的必要信息。

## 提交到搜索引擎

### Google Search Console

1. 在 [Google Search Console](https://search.google.com/search-console) 添加你的域名
2. 验证域名所有权（Cloudflare DNS 验证最简单）
3. 提交站点地图：`https://yourblog.com/sitemap.xml`

### Bing Webmaster Tools

同样添加域名、验证、提交站点地图。

## Open Graph 标签

RyeBlog 的文章页面包含 Open Graph 标签，确保在社交媒体分享时显示预览卡片：

```html
<meta property="og:title" content="文章标题">
<meta property="og:description" content="文章摘要">
<meta property="og:image" content="封面图片URL">
<meta property="og:url" content="文章URL">
```

这意味着在 Twitter、微信、Telegram 等平台分享你的文章链接时，会自动显示标题、摘要和封面图片的预览卡片。', 'https://picsum.photos/seed/ryeblog-seo/800/450', 'manual', 'RyeBlog', '2026-11-10', 'published', 0),

-- === 案例展示 ===
('a17', 'portfolio-site', '用 RyeBlog 搭建个人作品集', 'Build a Portfolio Site with RyeBlog', 'RyeBlog 不只是博客——通过自定义主题和分类，你可以用它搭建个人作品集、项目展示页、在线简历。', 'RyeBlog is not just a blog—through custom themes and categories, you can build a portfolio, project showcase, or online resume.', '# 用 RyeBlog 搭建个人作品集

RyeBlog 的灵活性让它不只是一个博客系统。通过自定义主题和内容结构，你可以用它搭建个人作品集。

## 思路

作品集和博客的核心需求是一样的：
- 展示内容（项目 vs 文章）
- 分类组织（项目类型 vs 文章分类）
- 详情页面（项目介绍 vs 文章正文）
- SEO 友好（被搜索到）

所以，只需要调整分类和主题，RyeBlog 就能变成作品集。

## 分类设计

```sql
-- 作品集分类
INSERT INTO categories (id, name_zh, name_en) VALUES 
('web', 'Web 项目', 'Web Projects'),
('mobile', '移动应用', 'Mobile Apps'),
('design', '设计作品', 'Design Works'),
('writing', '写作项目', 'Writing Projects');
```

## 内容结构

每个"项目"作为一篇文章存储：

```sql
INSERT INTO articles (id, slug, title_zh, excerpt_zh, content_zh, cover, category, author, date, status) 
VALUES (
  'p1',
  'my-web-app',
  '我的 Web 应用',
  '一个帮助人们管理日常任务的应用，使用 React + Cloudflare Workers 构建。',
  '# 项目概述\n\n## 技术栈\n- React\n- Cloudflare Workers\n- D1 Database\n\n## 功能\n- 任务管理\n- 数据同步\n- ...',
  'https://example.com/project-cover.jpg',
  'web',
  'Your Name',
  '2026-11-25',
  'published'
);
```

## 主题定制

作品集需要与博客不同的视觉风格：

### 1. 首页改为项目网格

```css
.article-list {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 2rem;
}

.article-item {
  border: 1px solid var(--border);
  border-radius: 8px;
  overflow: hidden;
  cursor: pointer;
}

.article-item-cover {
  width: 100%;
  height: 200px;
  object-fit: cover;
}
```

### 2. 项目详情页

```css
.article-detail {
  max-width: 900px;
  margin: 0 auto;
}

.article-detail-cover {
  width: 100%;
  height: 400px;
  object-fit: cover;
  border-radius: 8px;
}
```

### 3. 添加"在线演示"按钮

在 `render.js` 的 `buildArticleContent` 中添加：

```javascript
const demoLink = article.cover ? `
  <a class="btn btn-primary" href="${demoUrl}" target="_blank">
    在线演示 →
  </a>
` : '';
```

## 在线简历

同样的思路，你可以用 RyeBlog 搭建在线简历：

- 分类：工作经历、教育背景、技能、项目
- 每段经历作为一篇文章
- 自定义主题：简洁、专业、单页风格

## 关键优势

用 RyeBlog 搭建作品集/简历的优势：

1. **SEO 友好** — SSR 直出，搜索引擎能完整索引你的作品
2. **全球快速** — Cloudflare CDN，招聘方在哪里都快
3. **零成本** — 不花一分钱就有专业作品集
4. **Markdown 写作** — 项目描述用 Markdown，简单高效
5. **自定义域名** — `yourname.com` 比第三方平台更专业
6. **数据自主** — 内容在你自己的 D1 数据库中，不依赖第三方平台', 'https://picsum.photos/seed/ryeblog-portfolio/800/450', 'showcase', 'RyeBlog', '2026-11-09', 'published', 0),

('a18', 'demo-site', 'RyeBlog 在线演示站点', 'RyeBlog Live Demo Site', 'maomao.ryeblog.com 是 RyeBlog 的官方演示站点，展示 RyeBlog 的主题定制能力和内容组织灵活性。', 'maomao.ryeblog.com is RyeBlog official demo site, showcasing theme customization and content organization flexibility.', '# RyeBlog 在线演示站点

RyeBlog 的官方演示站点：**maomao.ryeblog.com**

## 演示站点的作用

演示站点展示 RyeBlog 的两个核心能力：

1. **主题定制能力** — 同一个 RyeBlog 系统，完全不同的视觉风格
2. **内容组织灵活性** — 不只是技术博客，可以承载任何内容类型

## 演示站点主题

演示站点采用"温暖治愈民国风"主题，与主站（ryeblog.com）的"安静阅读"主题形成鲜明对比：

| 维度 | 主站（安静阅读） | 演示站（温暖治愈） |
|------|-----------------|-------------------|
| 配色 | 暖米色 + 柿子红 | 暖米色 + 柿子红 + 墨绿 |
| 字体 | 衬线正文 | 衬线标题 + 无衬线正文 |
| 装饰 | 极简 | 祥云纹、福字纹、窗棂线条 |
| 气质 | 安静、克制 | 温暖、有烟火气 |

## 同一系统，不同域名

演示站点和主站使用**同一个 Pages 项目**和**同一个 D1 数据库**，通过域名区分：

```javascript
// 在 Functions 中检测域名
const host = request.headers.get('host');

if (host === 'maomao.ryeblog.com') {
  // 返回演示站点主题和内容
  return renderDemoSite(data);
} else {
  // 返回主站主题和内容
  return renderMainSite(data);
}
```

这展示了 RyeBlog 的一个独特能力：**一个部署，多个站点**。

## 演示站点内容

演示站点以"卯卯小福星"为主题——2026 年现象级 AI 漫剧 IP，全网播放量超 69 亿。内容包括：

- **卯卯故事** — 角色设定、IP诞生、人物关系
- **现象解读** — 为什么能火、赛道分析、方言现象
- **短剧追踪** — 追剧指南、新季解析、同类对比
- **治愈日常** — 名场面、烟火气、爽感机制

## 如何实现多域名

1. 在 Cloudflare Pages 项目中绑定多个自定义域名
2. 在 Functions 代码中通过 `request.headers.get('host')` 区分
3. 根据域名返回不同的主题 CSS 和内容查询

一个 Pages 项目最多支持 100 个自定义域名，你可以为不同的内容主题创建不同的子域名。

## 启示

演示站点证明了一点：**RyeBlog 不只是一个技术博客系统**。

通过主题定制和内容组织，RyeBlog 可以承载：
- 技术博客（主站模式）
- IP/主题粉丝站（演示站模式）
- 个人作品集
- 在线简历
- 项目展示页
- 知识库/文档站

核心系统不变，只是换皮 + 换内容。这就是 RyeBlog 的灵活性。', 'https://picsum.photos/seed/ryeblog-demo/800/450', 'showcase', 'RyeBlog', '2026-11-08', 'published', 0);

-- Tags
INSERT OR IGNORE INTO tags (id, name) VALUES
('ryeblog', 'ryeblog'),
('cloudflare', 'cloudflare'),
('pages', 'pages'),
('d1', 'd1'),
('ssr', 'ssr'),
('deploy', 'deploy'),
('markdown', 'markdown'),
('theme', 'theme'),
('seo', 'seo'),
('sqlite', 'sqlite'),
('workers', 'workers'),
('routing', 'routing'),
('cdn', 'cdn'),
('migration', 'migration'),
('wordpress', 'wordpress'),
('hugo', 'hugo'),
('hexo', 'hexo'),
('local-dev', 'local-dev'),
('wrangler', 'wrangler'),
('custom-domain', 'custom-domain'),
('https', 'https'),
('css', 'css'),
('design', 'design'),
('writing', 'writing'),
('tags', 'tags'),
('archive', 'archive'),
('portfolio', 'portfolio'),
('demo', 'demo'),
('open-source', 'open-source'),
('zero-cost', 'zero-cost');

-- Article-Tag relationships
INSERT OR IGNORE INTO article_tags (article_id, tag) VALUES
('a1', 'ryeblog'), ('a1', 'cloudflare'), ('a1', 'pages'), ('a1', 'd1'), ('a1', 'ssr'), ('a1', 'open-source'), ('a1', 'zero-cost'),
('a2', 'deploy'), ('a2', 'cloudflare'), ('a2', 'pages'), ('a2', 'd1'), ('a2', 'wrangler'),
('a3', 'migration'), ('a3', 'wordpress'), ('a3', 'hugo'), ('a3', 'hexo'),
('a4', 'cloudflare'), ('a4', 'pages'), ('a4', 'd1'), ('a4', 'ssr'), ('a4', 'workers'),
('a5', 'ssr'), ('a5', 'seo'), ('a5', 'pages'),
('a6', 'd1'), ('a6', 'sqlite'), ('a6', 'cloudflare'),
('a7', 'routing'), ('a7', 'pages'), ('a7', 'cdn'),
('a8', 'deploy'), ('a8', 'cloudflare'), ('a8', 'pages'), ('a8', 'd1'), ('a8', 'wrangler'),
('a9', 'custom-domain'), ('a9', 'https'), ('a9', 'cloudflare'),
('a10', 'local-dev'), ('a10', 'wrangler'), ('a10', 'd1'),
('a11', 'theme'), ('a11', 'css'), ('a11', 'design'),
('a12', 'theme'), ('a12', 'css'), ('a12', 'design'),
('a13', 'theme'), ('a13', 'css'), ('a13', 'design'),
('a14', 'writing'), ('a14', 'markdown'),
('a15', 'tags'), ('a15', 'archive'),
('a16', 'seo'), ('a16', 'ssr'),
('a17', 'portfolio'), ('a17', 'theme'),
('a18', 'demo'), ('a18', 'cloudflare'), ('a18', 'pages');