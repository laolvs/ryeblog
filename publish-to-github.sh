#!/usr/bin/env bash
# RyeBlog 发布到 GitHub 一键脚本
# 用法：在项目根目录执行  bash publish-to-github.sh
# 脚本只做「本地」部分（init/add/commit/tag），push 需你先填仓库地址。

set -e

REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$REPO_DIR"

echo "==> 当前目录: $REPO_DIR"

# 1) 初始化仓库（已存在则忽略）
git init -q
echo "==> git 仓库已就绪"

# 2) 若未配置提交身份，则设置仓库级默认值（不影响你全局 git 配置）
if [ -z "$(git config user.email)" ]; then
  git config user.name  "老吕"
  git config user.email "ynwuse@qq.com"
  echo "==> 已设置仓库级提交身份: 老吕 <ynwuse@qq.com>"
fi

# 3) 暂存（.gitignore 已屏蔽 config.php / tmp / uploads 数据 / cloud-release zip）
git add .

# 4) 显示将被提交的文件数，方便你核对
STAGED=$(git diff --cached --name-only | wc -l | tr -d ' ')
echo "==> 已暂存文件数: $STAGED"
echo "==> 若想先看清单，可执行: git status"

# 5) 提交
COMMIT_MSG="RyeBlog v1.3.0: MIT 开源 + 中英双语零依赖博客系统"
if git commit -q -m "$COMMIT_MSG"; then
  echo "==> 已提交: $COMMIT_MSG"
else
  echo "==> 没有新的改动可提交（工作区已是最新）"
fi

# 6) 打版本标签（对齐 inc/functions.php 的 RYEBLOG_VERSION）
if git rev-parse "v1.3.0" >/dev/null 2>&1; then
  echo "==> 标签 v1.3.0 已存在，跳过"
else
  git tag v1.3.0
  echo "==> 已打标签 v1.3.0"
fi

echo
echo "============================================================"
echo " 本地部分已完成。接下来请在 GitHub 新建空仓库，然后："
echo
echo "   git branch -M main"
echo "   git remote add origin <这里填你的仓库 HTTPS/SSH 地址>"
echo "   git push -u origin main --tags"
echo
echo " 仓库描述建议填：零依赖 · 中英双语的 PHP 开源博客系统"
echo "============================================================"
