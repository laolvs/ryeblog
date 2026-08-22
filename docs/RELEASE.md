# RyeBlog 发布清单（RELEASE CHECKLIST）

每次发布新版本，按顺序执行以下步骤。**发布包必须同时包含安装包（install.php）与升级文件（upgrade.php）。**

## 一、版本与代码

1. [ ] 更新版本号：`inc/functions.php` 的 `RYEBLOG_VERSION` + `install.php` 顶部常量（两处一致）。
2. [ ] 数据库/默认数据变更 → 在 `upgrade.php` 追加 `version_compare` 迁移块（幂等）。
3. [ ] 涉及插件/主题 → 更新 `@Version` 注释。

## 二、打包

4. [ ] 生成安装包：`php tools/build-release.php <版本>` → `download/ryeblog-<版本>.zip`
   - 包内必须含：`install.php`（安装向导）、`upgrade.php`（升级脚本）、`LICENSE.txt`、`tags.php`
   - 不得含：`config.php`、`usr/uploads/`、`tmp/`、`tools/`、设计文档等
5. [ ] 校验包：`php tools/verify-pack.php`
6. [ ] 插件/主题包：`php tools/cli_pack.php plugin/theme <name>`

## 三、测试（必须跑）

7. [ ] **全新安装**：解压包到空目录 → 浏览器/curl 走 `install.php` 全流程 → 验证：默认文章、未分类、顶部导航四项、动态 URL、文末版权插件、无 hero。
8. [ ] **旧版升级**：用上一版本装库 → 覆盖新版文件 → `php upgrade.php` → 确认版本迁移输出与 `db_version` 更新、数据完好。
9. [ ] 前台路由抽查：首页 / 文章 / 分类 / 标签 / tags / feed / sitemap / 后台登录。

## 四、发布

10. [ ] 安装包上传官方站 `download/` 目录，更新下载页版本号与说明。
11. [ ] 云端仓库 `cloud-release/` 重新生成（`cli_pack manifest`），确保 `repo.json` 指向线上。
12. [ ] 更新 README 快速安装/升级说明（如有变化）。
