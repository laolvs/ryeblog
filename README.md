# RyeBlog 青禾博客系统

> 🍃 零依赖 · 中英双语 · 百万级性能压测通过的开源 PHP 博客系统
> 官方网址：https://ryeblog.com ｜ 演示站：https://teayear.com （170 万篇文章实跑）

[![Version](https://img.shields.io/badge/version-1.4.2-green)](https://ryeblog.com/)
[![License](https://img.shields.io/badge/license-GPL--3.0-blue)](LICENSE.txt)
[![PHP](https://img.shields.io/badge/PHP-%3E%3D8.1-777bb3)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-%3E%3D5.7-f29111)](https://www.mysql.com/)
[![Zero Dependency](https://img.shields.io/badge/dependencies-0-brightgreen)](composer.json)

RyeBlog（青禾博客）是一个用 **PHP + MySQL** 原创实现的轻量级开源博客系统——**零 Composer 依赖、零 npm 依赖、原生 PHP 8.1+ 即装即用**。全站清新绿风格，内置 12+ 主题与 7+ 插件，支持中英双语、前台用户中心、插件热插拔、云端市场与百万级数据性能优化。

---

## ✨ 为什么选 RyeBlog

### 🚀 百万级数据性能（已实站压测验证）

不玩虚的——用 **1,702,561 篇文章（全量中文维基）** 在 teayear.com 实跑压测，全部优化沉淀进正式版：

| 场景 | 优化前 | 优化后 |
|---|---|---|
| 文章页（上一篇/下一篇） | 30s 超时 | **3ms** |
| 首页冷启动 | 13s | **2.6s** |
| 全站搜索 | 卡死超时 | **3ms** |
| 标签云页 | 226MB 内存打爆 | **88KB** |
| 归档页 | 404 / 3.2s GROUP BY | **3ms（O(1) 物化）** |
| 整页缓存命中 | — | **0.003–0.009s** |

技术手段：复合索引 + `FORCE INDEX` 主键导航、正文渲染缓存（content_rev 实时失效）、评论数批量统计、热门侧栏、浏览计数合并写、归档月计数物化、搜索 items 缓存、整页缓存（文件/Redis 双后端）——**全部做成后台开关**，默认保守、随时可回退。

> 🎯 立即体验：https://teayear.com —— 170 万篇文章的演示站，全站 3–13ms 响应。

### 🪶 零依赖

- 无 Composer、无 npm、无构建步骤，**上传即用**
- 自带轻量 Markdown 解析器与全文搜索
- 单文件 `install.php` 安装向导，5 分钟上线

### 🌐 中英双语（i18n）

- 中文根目录、英文 `/en`，URL 结构清晰（`/en/post/slug`）
- 后台界面与站点内容双语境独立切换
- 关闭双语即回纯中文，英文数据自动备份清理，零残留

### 🎨 主题与插件生态

- **12+ 内置主题**：企业站（科技/餐饮/教育/地产/医疗/文旅/法律/制造）、博客（Rye 官方/清新/深林/薄荷）、文档站（vuecho 三栏知识库）
- **7+ 内置插件**：中英双语、云端市场、RYE 社区论坛、数据导入、垃圾评论防护、文末版权、演示主题切换
- **插件热插拔**：后台菜单由插件自己注册（`admin_menu_<组>`），未启用即不显示——核心不硬编码
- **云端市场**：后台一键安装/升级主题与插件，SHA-256 强校验 + 失败自动回滚
- 第三方开发友好：`docs/PLUGIN_DEV.md`、`docs/THEME_DEV.md` 完整文档

### 🛡 安全

- PDO 预处理防 SQL 注入、`esc()` 统一输出转义防 XSS、CSRF 令牌
- 登录限流防爆破、上传扩展名+MIME 双重校验、密码强哈希
- 安全漏洞披露渠道见 [SECURITY.md](SECURITY.md)

### 📦 迁移友好

- 从 WordPress / Typecho / 通用 SQL / Markdown 批量导入
- 保留 slug、评论、附件与分类结构
- 升级脚本 `upgrade.php` 幂等可重复执行，版本化迁移

---

## 功能特性

- **文章系统**：Markdown/HTML 双格式、标签、SEO（摘要/描述/关键词）、封面图、附件、草稿、定时发布、回收站
- **用户中心**：注册/登录（用户名或邮箱）、忘记密码、收藏、划线笔记、纠错、浏览轨迹、个人资料
- **评论系统**：嵌套评论、审核流、垃圾评论防护插件
- **归档与统计**：按月归档（O(1) 物化计数）、分类、标签云（百万级分页安全）
- **伪静态**：多模式 URL（`/post/slug`、`/slug.html`、`/archives/id.html`），后台一键切换并生成 Nginx/Apache 规则
- **SEO**：自动 sitemap.xml、RSS feed、每篇独立 SEO 字段、统计代码位
- **后台管理**：仪表盘、文章/页面、分类、标签、评论、附件、菜单、备份（随机命名+下载/删除）、自动更新、6 组折叠导航
- **性能开关**：整页缓存（文件/Redis + TTL）、DB 持久连接、归档计数重建——全后台可视化
- **响应式**：PC 双栏 / 移动端单栏自适应
- **多站点**：同一套代码部署多站，主题/插件/数据完全隔离

---

## 快速开始

### 环境要求

| 组件 | 版本 |
|---|---|
| PHP | >= 8.1（`pdo_mysql`、`mbstring` 扩展） |
| MySQL | 5.7+ 或 MariaDB 10.3+（utf8mb4） |
| Web 服务器 | Apache（mod_rewrite）或 Nginx |

### 安装

1. 将本仓库解压到网站根目录（目录需可写）。
2. 浏览器访问 `install.php`，按向导填写数据库连接、站点标题、管理员账号。
3. 安装完成自动生成默认分类与示例文章，进入 `admin/` 开始管理。
4. 安全提示：安装完成后建议删除 `install.php`。

### 伪静态

后台「站点设置 → 伪静态」一键开启并自动生成规则。

**Apache**：内置 `.htaccess`，确保 `AllowOverride All`。

**Nginx**：

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

### 升级

覆盖代码后访问 `upgrade.php`（幂等，可重复执行），或直接用云端市场/自动更新。详见 [docs/UPGRADE.md](docs/UPGRADE.md)。

---

## 目录结构

```
ryeblog/
├── index.php         前台首页
├── post.php          文章详情 + 评论
├── category.php      分类页
├── tag.php           标签页
├── archive.php       归档页
├── search.php        搜索页
├── page.php          独立页面
├── sitemap.php       网站地图
├── feed.php          RSS 订阅
├── install.php       安装向导
├── upgrade.php       升级脚本（幂等）
├── inc/              核心函数库（functions/view/markdown/cache…）
├── admin/            后台管理（登录/仪表盘/文章/分类/评论/设置…）
├── user/             前台用户中心
├── usr/
│   ├── theme/        主题目录（12+ 内置）
│   ├── plugins/      插件目录（7+ 内置）
│   └── uploads/      上传文件
├── assets/           样式与脚本
└── docs/             开发文档（插件/主题/升级/双语/云端市场）
```

---

## 生态链接

| 入口 | 地址 |
|---|---|
| 官方网站 | https://ryeblog.com |
| 源码仓库（GitHub） | https://github.com/laolvs/ryeblog |
| 源码镜像（Gitee，国内加速） | https://gitee.com/laolvs/ryeblog |
| 百万级演示站 | https://teayear.com （1,702,561 篇文章） |
| 主题切换演示 | https://demo.ryeblog.com （顶部切换 12+ 主题） |
| 知识库/文档 | https://ryeblog.com/category/knowledge |
| 案例展示 | https://ryeblog.com/category/cases |
| 官方社区论坛 | https://ryeblog.com/bbs/ |
| 云端市场 | 后台「插件/主题市场」一键安装 |

---

## 开发文档

- [插件开发指南](docs/PLUGIN_DEV.md) — 目录结构、生命周期、钩子、后台菜单注册、双语规范、打包发布
- [主题开发指南](docs/THEME_DEV.md) — 模板加载顺序、可用函数、统计代码对接、发布打包
- [升级指南](docs/UPGRADE.md) — 版本化迁移、索引/物化表、常见问题
- [双语设计](docs/bilingual-en-design.md) — i18n 架构与关闭清理
- [云端市场设计](docs/cloud-marketplace-design.md) — SHA-256 强校验与回滚

---

## 开源协议

RyeBlog 采用 **GPL-3.0** 协议开源，详情见 [LICENSE.txt](LICENSE.txt)。

第三方组件（vuecho 主题、RYE 社区论坛插件、jQuery / fuse.js 等）均采用与 GPL-3.0 兼容的自由软件授权，署名保留在原文件处。

## 安全

如发现安全漏洞，请通过 [SECURITY.md](SECURITY.md) 中的渠道**私下**报告（邮箱 ynwuse@qq.com 或 GitHub Security Advisory），勿直接公开 Issue。

---

<p align="center">Made with ❤️ by 老吕 · RyeBlog 青禾博客</p>
