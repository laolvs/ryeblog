# RyeBlog 云端市场方案 · 插件/主题云端化

> 状态：**已落地（2026-08-19）**——方案经用户评审全部采纳推荐项（官方仓库 ryeblog.com / 发布端一起做 / 核心内置），①-⑤ 已实现并 E2E 回归通过。
> 关联：`inc/cloud.php`（客户端核心）、`tools/cli_pack.php`（发布端）、`admin/plugins.php` + `admin/themes.php`（云端区块）、设置项 `cloud_repo_url`/`cloud_enabled`/`cloud_cache`。

## 1. 整体架构

```
┌─────────────────────────┐        HTTPS         ┌──────────────────────────┐
│  博客站点（任意 RyeBlog）│  ←────────────────  │  云端仓库（官方/自建）    │
│  ├ 插件管理页云端区块    │   manifest.json     │  ├ repo.json（目录索引）  │
│  ├ 主题管理页云端区块    │   plugin-xxx.zip    │  ├ plugins/*.zip         │
│  └ 设置：仓库地址可配    │   theme-xxx.zip     │  └ themes/*.zip          │
└─────────────────────────┘                     └──────────────────────────┘
```

- **云端 = 静态文件**：一个 `repo.json`（索引）+ 若干 ZIP 包，任意静态托管（GitHub Pages / 云存储 / 自建 Nginx）即可，无需后端。
- **客户端 = 后台集成**：插件/主题管理页新增「云端仓库」区块，一键安装/更新；本地版本与云端版本比较；更新前自动备份可回滚。

## 2. 云端仓库规范（manifest.json）

```json
{
  "repo": "ryeblog-official",
  "homepage": "https://ryeblog.com/",
  "updated": "2026-08-19T10:00:00+08:00",
  "plugins": [
    {
      "name": "english-admin",
      "title": "英文站（English Site）",
      "version": "2.1.0",
      "desc": "开启=安装英文库并启用 /en 双语站；关闭=备份并清理英文数据",
      "download": "https://ryeblog.com/cloud/plugins/english-admin-2.1.0.zip",
      "sha256": "c4a1…（ZIP 的 SHA-256，防篡改校验）",
      "min_core": "1.0.0",
      "changelog": "v2.1.0：新增英文数据备份/恢复面板"
    }
  ],
  "themes": [
    {
      "name": "dark",
      "title": "暗夜绿",
      "version": "1.0.0",
      "desc": "深色主题",
      "download": "https://ryeblog.com/cloud/themes/dark-1.0.0.zip",
      "sha256": "…",
      "min_core": "1.0.0"
    }
  ]
}
```

约定：
- ZIP 内需含一个顶层目录（插件名/主题名），插件含 `Plugin.php`、主题含 `theme.json`（或 style.css）。
- 版本号语义化 `主.次.修`，本地与云端比较用 `version_compare`。
- manifest 与 ZIP 均放同一静态目录；`repo_url` 后台可配置（默认官方地址，可指向自建仓库）。

## 3. 客户端实现

### 3.1 核心函数（inc/functions.php 或独立 inc/cloud.php）

| 函数 | 职责 |
|---|---|
| `cloudFetchManifest($type)` | 拉取并缓存 manifest（缓存 1 小时，失败返回可读错误） |
| `cloudLocalVersion($type, $name)` | 本地版本：插件读 `@Version` 注释；主题读 `theme.json` 的 version |
| `cloudStatus($type, $pkg)` | 比较 → `installed-up-to-date / update-available / not-installed` |
| `cloudDownload($url, $sha256)` | 下载 ZIP 到临时目录 → 校验 SHA-256（不符即拒） → 返回路径 |
| `cloudInstall($type, $pkg)` | 解压（复用 extractZip，路径穿越防护）→ 校验合法性 → 装入 `usr/plugins` / `usr/theme` |
| `cloudUpdate($type, $pkg)` | 更新前备份旧目录到 `usr/uploads/backup/cloud-{name}-{ver}/` → 覆盖安装 → 失败自动回滚 |
| `cloudBackupList / cloudRollback` | 备份清单 + 一键回滚 |

### 3.2 后台 UI

- **插件管理页**新增「☁️ 云端仓库」区块：表格列出云端插件（名称 / 云端版本 / 本地版本 / 状态徽章 / 操作按钮：`安装` `更新` `已是最新`），顶部刷新按钮。
- **主题管理页**新增「☁️ 云端主题」区块：卡片式（预览 + 版本 + 描述 + `安装`/`更新`/`激活`）。
- 操作流程：安装 → 下载+校验+解压 → 提示成功并（插件）引导启用 /（主题）引导激活；更新 → 自动备份旧版 → 覆盖 → 提示可回滚。

### 3.3 设置项

- `cloud_repo_url`：云端仓库 manifest 地址（默认 `https://ryeblog.com/cloud/repo.json`）
- `cloud_enabled`：云端功能开关（默认开）
- `cloud_cache`：manifest 缓存时长（默认 3600s，后台写死后端用）

## 4. 发布端（作者/官方用）CLI 工具

`cli_pack.php`（开发工具，发布包排除）：

```bash
php usr/plugins/cloud/cli_pack.php plugin english-admin --version 2.1.0
php usr/plugins/cloud/cli_pack.php theme dark --version 1.0.0
php usr/plugins/cloud/cli_pack.php manifest          # 重新生成 repo.json
```

功能：
- 把 `usr/plugins/<name>` / `usr/theme/<name>` 打包为规范 ZIP（自动读取 @Version / theme.json 版本）
- 计算 SHA-256 写入 manifest
- 输出到 `cloud-release/` 目录（plugins/ themes/ repo.json），作者上传到任意静态托管
- 支持 `--changelog "…"` 追加更新日志

## 5. 安全设计

| 风险 | 防护 |
|---|---|
| ZIP 篡改 / 中间人 | **SHA-256 强校验**，不符拒绝安装 |
| 解压路径穿越（../） | 复用 extractZip 的路径白名单校验（仅允许目标目录内） |
| 伪插件 / 伪主题 | 插件必须有 Plugin.php；主题必须有 theme.json/style.css，否则拒装 |
| 更新失败破坏站点 | 更新前备份旧目录，失败自动回滚；备份列表可手动回滚 |
| 仓库地址被改成恶意源 | 仓库地址属站长可控配置（风险自担），安装前显示来源域名 |

## 6. 实施阶段

| 阶段 | 内容 | 涉及文件 |
|---|---|---|
| ① | 云端规范 + 发布端 cli_pack（打包/校验和/manifest 生成） | cloud/cli_pack.php |
| ② | 客户端核心：manifest 拉取缓存、版本比较、下载校验、安装、更新备份回滚 | inc/functions.php（或 inc/cloud.php） |
| ③ | 插件管理页云端区块 | admin/plugins.php |
| ④ | 主题管理页云端区块 | admin/themes.php |
| ⑤ | 设置项 + 词典 + 全量回归（含失败场景：断网/校验和不符/伪包） | settings.php, english-admin/lang |

## 7. 待拍板

1. **默认云端地址**：官方 `https://ryeblog.com/cloud/repo.json`，还是先指向你的自建仓库（laolv.org / atsvg.com 所在服务器 31.97.213.106）？
2. **发布端是否一起做**：建议一起做（否则云端无包可更），CLI 工具归入开发工具（发布包排除）。
3. **是否支持第三方仓库多源**：v1 单仓库地址（可改）够用，多源后续再说。
