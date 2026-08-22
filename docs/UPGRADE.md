# RyeBlog 版本升级指南

升级前请**备份数据库与站点目录**。升级不删除任何数据。

## 升级步骤

1. **备份**：导出数据库（phpMyAdmin 或 `mysqldump`），并备份站点目录。
2. **覆盖文件**：把新版安装包内的 `ryeblog/` 文件覆盖到站点目录（**不要删除** `config.php` 与 `usr/uploads/`）。
3. **执行升级**：命令行运行 `php upgrade.php`（或浏览器访问 `upgrade.php`）。
   - 脚本幂等，可重复执行；会显示「当前数据库版本 → 目标版本」。
4. **验证**：访问前台与后台，确认功能正常。

## 升级脚本说明（upgrade.php）

- 自动读取数据库 `db_version`（旧站按 1.0.0 处理），按发布顺序执行迁移块；
- 迁移内容全部幂等（`CREATE TABLE IF NOT EXISTS` / 补列补索引 / 默认值仅在缺失时写入）；
- 完成后将 `db_version` 更新为当前版本 `RYEBLOG_VERSION`（定义于 `inc/functions.php`）。

## 发版约定（给维护者）

每次发布新版本：

1. 更新 `inc/functions.php` 的 `RYEBLOG_VERSION` 与 `install.php` 顶部版本常量；
2. 若涉及数据库结构/默认数据变更，在 `upgrade.php` 末尾追加 `version_compare($dbVersion, 'X.Y.Z', '<')` 迁移块；
3. 用 `tools/build-release.php <版本>` 重新生成 `download/ryeblog-<版本>.zip`（内含 install.php 安装向导）；
4. 涉及插件/主题时，用 `tools/cli_pack.php` 重新打包云端市场包；
5. 本地全新安装 + 旧版本升级各跑一遍验证（见 docs/RELEASE.md）。
