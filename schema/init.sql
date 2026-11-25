-- RyeBlog D1 Schema
-- Cloudflare D1 (SQLite) database schema

CREATE TABLE IF NOT EXISTS articles (
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

CREATE TABLE IF NOT EXISTS article_tags (
  article_id TEXT NOT NULL,
  tag TEXT NOT NULL,
  PRIMARY KEY (article_id, tag),
  FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS categories (
  id TEXT PRIMARY KEY,
  name_zh TEXT NOT NULL,
  name_en TEXT
);

CREATE TABLE IF NOT EXISTS tags (
  id TEXT PRIMARY KEY,
  name TEXT UNIQUE NOT NULL
);

CREATE TABLE IF NOT EXISTS comments (
  id TEXT PRIMARY KEY,
  article_id TEXT NOT NULL,
  author TEXT,
  content TEXT,
  date TEXT NOT NULL,
  FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS settings (
  key TEXT PRIMARY KEY,
  value TEXT
);

-- Insert default settings
INSERT OR IGNORE INTO settings (key, value) VALUES ('blogTitle_zh', 'RyeBlog');
INSERT OR IGNORE INTO settings (key, value) VALUES ('blogTitle_en', 'RyeBlog');
INSERT OR IGNORE INTO settings (key, value) VALUES ('blogDescription_zh', '为 Cloudflare 而生的开源博客系统');
INSERT OR IGNORE INTO settings (key, value) VALUES ('blogDescription_en', 'An open-source blog system built for Cloudflare');
INSERT OR IGNORE INTO settings (key, value) VALUES ('blogAuthor', 'RyeBlog');

-- Insert default categories
INSERT OR IGNORE INTO categories (id, name_zh, name_en) VALUES ('quickstart', '快速开始', 'Quick Start');
INSERT OR IGNORE INTO categories (id, name_zh, name_en) VALUES ('tech', '技术详解', 'Technical Deep Dive');
INSERT OR IGNORE INTO categories (id, name_zh, name_en) VALUES ('deploy', '部署指南', 'Deployment Guide');
INSERT OR IGNORE INTO categories (id, name_zh, name_en) VALUES ('theme', '主题开发', 'Theme Development');
INSERT OR IGNORE INTO categories (id, name_zh, name_en) VALUES ('manual', '使用手册', 'User Manual');
INSERT OR IGNORE INTO categories (id, name_zh, name_en) VALUES ('showcase', '案例展示', 'Showcase');
