// === RyeBlog SSR Rendering Library ===
// Server-side HTML rendering for Cloudflare Pages Functions

import { getLocalized, countWords } from './db.js';

export function esc(str) {
  if (!str) return '';
  return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

export function renderArticleItem(a, categories) {
  const cat = categories.find(c => c.id === a.category);
  const wordCount = countWords(getLocalized(a.content, 'zh'));
  const wordStr = wordCount >= 1000 ? (wordCount / 1000).toFixed(1) + ' 千字' : wordCount + ' 字';
  const cover = a.cover ? `<img class="article-item-cover" src="${esc(a.cover)}" alt="${esc(getLocalized(a.title, 'zh'))}" loading="lazy" onerror="this.style.display='none'">` : '';
  return `<article class="article-item" onclick="location.href='/${esc(a.slug || a.id)}'">
      <div class="article-item-body">
        <div class="article-item-meta">
          <span class="article-item-category">${cat ? esc(getLocalized(cat.name, 'zh')) : ''}</span>
          <span class="article-item-meta-dot">·</span>
          <span>${esc(a.date)}</span>
          <span class="article-item-meta-dot">·</span>
          <span>约 ${wordStr}</span>
        </div>
        <h3 class="article-item-title">${esc(getLocalized(a.title, 'zh'))}</h3>
        <p class="article-item-excerpt">${esc(getLocalized(a.excerpt, 'zh'))}</p>
      </div>
      ${cover}
    </article>`;
}

export function renderRightSidebar(articles, categories, tags, settings) {
  const published = articles.filter(a => a.status === 'published');
  const totalWords = published.reduce((sum, a) => {
    const content = getLocalized(a.content, 'zh') || '';
    return sum + content.replace(/\s/g, '').length;
  }, 0);
  const wordStr = totalWords > 10000 ? (totalWords / 10000).toFixed(1) + ' 万字' : totalWords + ' 字';
  const author = settings.blogAuthor || 'Anonymous';
  const desc = getLocalized(settings.blogDescription, 'zh') || '';

  let html = '';

  // Author card
  html += `<div class="widget-author-card">
    <div class="widget-author-header">
      <div class="widget-author-avatar">${esc(author.charAt(0))}</div>
      <div>
        <div class="widget-author-name">${esc(author)}</div>
        <div class="widget-author-desc">${esc(desc)}</div>
      </div>
    </div>
    <div class="widget-author-stats">
      <div class="widget-stat-item"><span class="widget-stat-num">${published.length}</span><span class="widget-stat-label">文章</span></div>
      <div class="widget-stat-item"><span class="widget-stat-num">${categories.length}</span><span class="widget-stat-label">分类</span></div>
      <div class="widget-stat-item"><span class="widget-stat-num">${tags.length}</span><span class="widget-stat-label">标签</span></div>
      <div class="widget-stat-item"><span class="widget-stat-num">${wordStr}</span><span class="widget-stat-label">字</span></div>
    </div>
    <div class="widget-author-actions">
      <a class="widget-rss-btn" href="/rss.xml">订阅 RSS</a>
      <a class="widget-link-btn" href="https://github.com/ryeblog/ryeblog" target="_blank">GitHub →</a>
    </div>
  </div>`;

  // Recent articles
  const recent = published.slice(0, 5);
  html += `<div class="widget">
    <div class="widget-title">最近文章</div>
    <div class="widget-recent-list">
      ${recent.map(a => `<div class="widget-recent-item" onclick="location.href='/${esc(a.slug || a.id)}'">
        <div class="widget-recent-title">${esc(getLocalized(a.title, 'zh'))}</div>
        <div class="widget-recent-date">${esc(a.date)}</div>
      </div>`).join('')}
    </div>
  </div>`;

  // Categories
  html += `<div class="widget">
    <div class="widget-title">分类</div>
    <div class="widget-category-list">
      ${categories.map(c => `<div class="widget-category-item" onclick="location.href='/category/${esc(c.id)}'">${esc(getLocalized(c.name, 'zh'))}<span class="widget-category-count">${published.filter(a => a.category === c.id).length}</span></div>`).join('')}
    </div>
  </div>`;

  return html;
}

export function renderPage(options, data) {
  const { title, content } = options;
  const { settings, categories, tags, articles } = data;
  const siteName = getLocalized(settings.blogTitle, 'zh') || 'RyeBlog';

  return `<!DOCTYPE html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>${esc(title || siteName)} — ${esc(siteName)}</title>
<link rel="stylesheet" href="/style.css?v=2">
</head>
<body>

<header class="site-header">
  <div class="site-header-inner">
    <div class="site-brand" onclick="location.href='/'">
      <span class="site-brand-name">${esc(siteName)}</span>
    </div>
    <nav class="site-nav">
      <a class="site-nav-link" href="/">首页</a>
      <a class="site-nav-link" href="/archive">归档</a>
      <a class="site-nav-link" href="/tags">标签</a>
      <a class="site-nav-link" href="/search">搜索</a>
      <a class="site-nav-link" href="/about">关于</a>
    </nav>
    <div class="site-header-actions">
      <button class="nav-btn" onclick="toggleTheme()" title="切换主题">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
        </svg>
      </button>
    </div>
  </div>
</header>

<div id="app">
${content}
</div>

<footer class="site-footer">
  <div class="site-footer-inner">
    <div class="site-footer-brand">${esc(siteName)}</div>
    <div class="site-footer-text">${esc(getLocalized(settings.blogDescription, 'zh'))}</div>
    <div class="site-footer-links">
      <a class="site-footer-link" href="/">首页</a>
      <a class="site-footer-link" href="/rss.xml">RSS</a>
    </div>
    <div class="site-footer-copy">© 2026 ${esc(siteName)}. All rights reserved.</div>
  </div>
</footer>

<div class="toast-container" id="toastContainer"></div>

<script>
function toggleTheme() {
  const cur = document.documentElement.getAttribute('data-theme');
  const next = cur === 'light' ? 'dark' : 'light';
  document.documentElement.setAttribute('data-theme', next);
  localStorage.setItem('inkwell_theme', next);
}
(function() {
  const saved = localStorage.getItem('inkwell_theme');
  if (saved) document.documentElement.setAttribute('data-theme', saved);
})();
</script>
</body>
</html>`;
}

// === Simple Markdown Parser (Server-side) ===
export function parseMarkdownServer(text) {
  if (!text) return '';
  let html = text;
  html = html.replace(/```(\w*)\n([\s\S]*?)```/g, (m, lang, code) => `<pre><code>${esc(code.trim())}</code></pre>`);
  html = html.replace(/^### (.+)$/gm, '<h3>$1</h3>');
  html = html.replace(/^## (.+)$/gm, '<h2>$1</h2>');
  html = html.replace(/^# (.+)$/gm, '<h1>$1</h1>');
  html = html.replace(/^> (.+)$/gm, '<blockquote>$1</blockquote>');
  html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
  html = html.replace(/`([^`]+)`/g, '<code>$1</code>');
  html = html.replace(/!\[([^\]]*)\]\(([^)]+)\)/g, '<img src="$2" alt="$1">');
  html = html.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2">$1</a>');
  html = html.replace(/^\|(.+)\|$/gm, (m, content) => {
    const cells = content.split('|').map(c => c.trim()).filter(c => c);
    if (cells.every(c => /^[-:]+$/.test(c))) return '';
    return `<tr>${cells.map(c => `<td>${c}</td>`).join('')}</tr>`;
  });
  html = html.replace(/^\d+\. (.+)$/gm, '<li>$1</li>');
  html = html.replace(/^- (.+)$/gm, '<li>$1</li>');
  html = html.replace(/(<li>[\s\S]*?<\/li>)(?!\s*<li>)/g, '<ul>$1</ul>');
  html = html.split(/\n\n+/).map(block => {
    if (block.startsWith('<')) return block;
    return `<p>${block.replace(/\n/g, '<br>')}</p>`;
  }).join('\n');
  html = html.replace(/(<tr>[\s\S]*?<\/tr>)/g, '<table>$1</table>');
  return html;
}

// === Page Content Builders ===

export function buildHomepageContent(articles, categories, tags, settings) {
  const published = articles.filter(a => a.status === 'published');
  return `
    <div class="layout-wrapper">
      <main class="main-content">
        <div id="articleList" class="article-list">
          ${published.map(a => renderArticleItem(a, categories)).join('')}
        </div>
      </main>
      <aside class="sidebar-right">${renderRightSidebar(articles, categories, tags, settings)}</aside>
    </div>`;
}

export function buildArchiveContent(articles, categories, tags, settings) {
  const published = articles.filter(a => a.status === 'published');
  const grouped = {};
  published.forEach(a => {
    const year = a.date ? a.date.substring(0, 4) : '未知';
    if (!grouped[year]) grouped[year] = [];
    grouped[year].push(a);
  });
  const years = Object.keys(grouped).sort((a, b) => b.localeCompare(a));

  let archiveHtml = '';
  years.forEach(year => {
    archiveHtml += `<div class="archive-year-group">
      <h2 class="archive-year">${esc(year)}</h2>
      <div class="archive-year-count">${grouped[year].length} 篇文章</div>
      <div class="archive-items">`;
    grouped[year].forEach(a => {
      const cat = categories.find(c => c.id === a.category);
      archiveHtml += `<div class="archive-item" onclick="location.href='/${esc(a.slug || a.id)}'">
        <span class="archive-item-date">${esc(a.date)}</span>
        <span class="archive-item-title">${esc(getLocalized(a.title, 'zh'))}</span>
        <span class="archive-item-category">${cat ? esc(getLocalized(cat.name, 'zh')) : ''}</span>
      </div>`;
    });
    archiveHtml += `</div></div>`;
  });

  return `
    <div class="layout-wrapper">
      <main class="main-content">
        <h1 class="page-title">归档</h1>
        <div class="archive-list">${archiveHtml}</div>
      </main>
      <aside class="sidebar-right">${renderRightSidebar(articles, categories, tags, settings)}</aside>
    </div>`;
}

export function buildTagsContent(articles, categories, tags, settings) {
  const published = articles.filter(a => a.status === 'published');

  let tagsHtml = '';
  tags.forEach(tag => {
    const count = published.filter(a => a.tags && a.tags.includes(tag.name)).length;
    if (count === 0) return;
    const sizeClass = count > 5 ? 'tag-lg' : count > 2 ? 'tag-md' : 'tag-sm';
    tagsHtml += `<a class="tag-cloud-item ${sizeClass}" href="/tag/${encodeURIComponent(tag.name)}">${esc(tag.name)}<span class="tag-cloud-count">${count}</span></a>`;
  });

  return `
    <div class="layout-wrapper">
      <main class="main-content">
        <h1 class="page-title">标签</h1>
        <div class="tags-cloud-page">${tagsHtml}</div>
      </main>
      <aside class="sidebar-right">${renderRightSidebar(articles, categories, tags, settings)}</aside>
    </div>`;
}

export function buildTagContent(tagName, articles, categories, tags, settings) {
  const published = articles.filter(a => a.status === 'published');
  const taggedArticles = published.filter(a => a.tags && a.tags.includes(tagName));

  let tagsHtml = '';
  tags.forEach(tag => {
    const count = published.filter(a => a.tags && a.tags.includes(tag.name)).length;
    if (count === 0) return;
    const sizeClass = count > 5 ? 'tag-lg' : count > 2 ? 'tag-md' : 'tag-sm';
    const isActive = tag.name === tagName ? ' style="border-color:var(--accent);color:var(--accent);background:var(--accent-light)"' : '';
    tagsHtml += `<a class="tag-cloud-item ${sizeClass}" href="/tag/${encodeURIComponent(tag.name)}"${isActive}>${esc(tag.name)}<span class="tag-cloud-count">${count}</span></a>`;
  });

  return `
    <div class="layout-wrapper">
      <main class="main-content">
        <h1 class="page-title">标签</h1>
        <div class="tags-cloud-page">${tagsHtml}</div>
        <div class="tag-filter-info">标签: <strong>${esc(tagName)}</strong> (${taggedArticles.length} 篇文章) <button class="btn btn-ghost btn-sm" onclick="location.href='/tags'">清除筛选</button></div>
        <div class="article-list">
          ${taggedArticles.map(a => renderArticleItem(a, categories)).join('')}
        </div>
      </main>
      <aside class="sidebar-right">${renderRightSidebar(articles, categories, tags, settings)}</aside>
    </div>`;
}

export function buildCategoryContent(catId, articles, categories, tags, settings) {
  const cat = categories.find(c => c.id === catId);
  const published = articles.filter(a => a.status === 'published' && a.category === catId);

  return `
    <div class="layout-wrapper">
      <main class="main-content">
        <h1 class="page-title">${cat ? esc(getLocalized(cat.name, 'zh')) : '分类'}</h1>
        <div class="article-list">
          ${published.map(a => renderArticleItem(a, categories)).join('')}
        </div>
      </main>
      <aside class="sidebar-right">${renderRightSidebar(articles, categories, tags, settings)}</aside>
    </div>`;
}

export function buildArticleContent(article, categories, tags, settings) {
  const cat = categories.find(c => c.id === article.category);
  const contentHtml = parseMarkdownServer(getLocalized(article.content, 'zh'));
  const wordCount = countWords(getLocalized(article.content, 'zh'));
  const wordStr = wordCount >= 1000 ? (wordCount / 1000).toFixed(1) + ' 千字' : wordCount + ' 字';

  return `
    <div class="layout-wrapper">
      <main class="main-content">
        <article class="article-detail">
          <button class="btn btn-ghost btn-sm" onclick="location.href='/'"><svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>返回文章列表</button>
          <div class="article-detail-header">
            <div class="article-detail-category">${cat ? esc(getLocalized(cat.name, 'zh')) : ''}</div>
            <h1 class="article-detail-title">${esc(getLocalized(article.title, 'zh'))}</h1>
            <div class="article-detail-meta">
              <span>${esc(article.author)}</span>
              <span>发布于 ${esc(article.date)}</span>
              <span>约 ${wordStr}</span>
            </div>
          </div>
          <img class="article-detail-cover" src="${esc(article.cover)}" alt="${esc(getLocalized(article.title, 'zh'))}" onerror="this.src='https://picsum.photos/seed/${esc(article.id)}/1200/500'">
          <div class="article-content">${contentHtml}</div>
          <div class="article-detail-tags">
            ${(article.tags || []).map(tag => `<span class="article-tag" onclick="location.href='/tag/${encodeURIComponent(tag)}'">${esc(tag)}</span>`).join('')}
          </div>
        </article>
      </main>
      <aside class="sidebar-right">${renderRightSidebar([article], categories, tags, settings)}</aside>
    </div>`;
}

export function buildSearchContent(q, searchResults, articles, categories, tags, settings) {
  let resultsHtml = '';
  let resultsInfo = '';

  if (q) {
    resultsInfo = `<div class="search-results-info">找到 ${searchResults.length} 篇相关文章</div>`;
    if (searchResults.length > 0) {
      resultsHtml = `<div class="article-list">${searchResults.map(a => renderArticleItem(a, categories)).join('')}</div>`;
    } else {
      resultsHtml = `<div class="empty-state"><div class="empty-state-title">没有找到匹配的文章</div><div class="empty-state-desc">尝试调整搜索关键词</div></div>`;
    }
  } else {
    resultsHtml = `<div class="search-hint">输入关键词搜索文章标题、摘要和内容</div>`;
  }

  return `
    <div class="layout-wrapper">
      <main class="main-content">
        <h1 class="page-title">搜索</h1>
        <form class="search-page-form" method="get" action="/search">
          <div class="search-page-box">
            <svg class="search-icon" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input class="search-page-input" type="text" name="q" placeholder="搜索文章..." value="${esc(q)}" autofocus>
          </div>
        </form>
        ${resultsInfo}
        <div id="searchResults">${resultsHtml}</div>
      </main>
      <aside class="sidebar-right">${renderRightSidebar(articles, categories, tags, settings)}</aside>
    </div>`;
}

export function buildRssXml(articles, settings, host) {
  const published = articles.filter(a => a.status === 'published');
  const siteName = getLocalized(settings.blogTitle, 'zh') || 'RyeBlog';
  const siteDesc = getLocalized(settings.blogDescription, 'zh') || '';
  const siteAuthor = settings.blogAuthor || 'Anonymous';

  let xml = `<?xml version="1.0" encoding="UTF-8"?>\n`;
  xml += `<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">\n`;
  xml += `  <channel>\n`;
  xml += `    <title>${escapeXml(siteName)}</title>\n`;
  xml += `    <link>${host}</link>\n`;
  xml += `    <description>${escapeXml(siteDesc)}</description>\n`;
  xml += `    <language>zh-CN</language>\n`;
  xml += `    <lastBuildDate>${new Date().toUTCString()}</lastBuildDate>\n`;
  xml += `    <atom:link href="${host}/rss.xml" rel="self" type="application/rss+xml"/>\n`;

  published.forEach(a => {
    const title = getLocalized(a.title, 'zh');
    const excerpt = getLocalized(a.excerpt, 'zh');
    const slug = a.slug || a.id;
    xml += `    <item>\n`;
    xml += `      <title>${escapeXml(title)}</title>\n`;
    xml += `      <link>${host}/${slug}</link>\n`;
    xml += `      <guid isPermaLink="true">${host}/${slug}</guid>\n`;
    xml += `      <description>${escapeXml(excerpt)}</description>\n`;
    xml += `      <author>${escapeXml(siteAuthor)}</author>\n`;
    xml += `      <pubDate>${new Date(a.date).toUTCString()}</pubDate>\n`;
    if (a.tags) {
      a.tags.forEach(tag => {
        xml += `      <category>${escapeXml(tag)}</category>\n`;
      });
    }
    xml += `    </item>\n`;
  });

  xml += `  </channel>\n`;
  xml += `</rss>`;
  return xml;
}

export function buildSitemapXml(articles, categories, tags, host) {
  const published = articles.filter(a => a.status === 'published');

  let xml = `<?xml version="1.0" encoding="UTF-8"?>\n`;
  xml += `<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n`;

  xml += `  <url>\n    <loc>${host}/</loc>\n    <changefreq>daily</changefreq>\n    <priority>1.0</priority>\n  </url>\n`;
  xml += `  <url>\n    <loc>${host}/tags</loc>\n    <changefreq>weekly</changefreq>\n    <priority>0.6</priority>\n  </url>\n`;
  xml += `  <url>\n    <loc>${host}/search</loc>\n    <changefreq>weekly</changefreq>\n    <priority>0.6</priority>\n  </url>\n`;
  xml += `  <url>\n    <loc>${host}/archive</loc>\n    <changefreq>weekly</changefreq>\n    <priority>0.6</priority>\n  </url>\n`;

  published.forEach(a => {
    const slug = a.slug || a.id;
    xml += `  <url>\n    <loc>${host}/${slug}</loc>\n    <lastmod>${a.date}</lastmod>\n    <changefreq>monthly</changefreq>\n    <priority>0.8</priority>\n  </url>\n`;
  });

  categories.forEach(c => {
    xml += `  <url>\n    <loc>${host}/category/${c.id}</loc>\n    <changefreq>weekly</changefreq>\n    <priority>0.5</priority>\n  </url>\n`;
  });

  tags.forEach(tag => {
    xml += `  <url>\n    <loc>${host}/tag/${encodeURIComponent(tag.name)}</loc>\n    <changefreq>weekly</changefreq>\n    <priority>0.5</priority>\n  </url>\n`;
  });

  xml += `</urlset>`;
  return xml;
}

function escapeXml(str) {
  if (!str) return '';
  return str.replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&apos;');
}

// === Landing Page (for /about or promotional page) ===
export function buildLandingContent(data) {
  const { settings, categories, tags, articles } = data;
  const published = articles.filter(a => a.status === 'published');

  return `
    <div class="landing-page">
      <section class="landing-hero">
        <h1 class="landing-hero-title">RyeBlog</h1>
        <p class="landing-hero-tagline">为 Cloudflare 而生的开源博客系统</p>
        <p class="landing-hero-desc">基于 Pages + D1，一键部署，零成本运行。<br>SSR 直出，SEO 友好，Markdown 写作——不追踪，不广告，不喧嚣。</p>
        <div class="landing-hero-actions">
          <a class="btn btn-primary" href="https://github.com/ryeblog/ryeblog" target="_blank">GitHub 源码</a>
          <a class="btn btn-ghost" href="/">阅读文档 →</a>
        </div>
      </section>

      <section class="landing-features">
        <div class="landing-feature">
          <div class="landing-feature-icon">⚡</div>
          <h3 class="landing-feature-title">一键部署</h3>
          <p class="landing-feature-desc">Fork 仓库，连接 Cloudflare Pages，创建 D1 数据库——三步上线，30 秒部署。无需服务器，无需 Docker，无需 Nginx。</p>
        </div>
        <div class="landing-feature">
          <div class="landing-feature-icon">📖</div>
          <h3 class="landing-feature-title">SSR 直出</h3>
          <p class="landing-feature-desc">所有页面服务器端直接输出完整 HTML，不依赖 JavaScript 渲染。搜索引擎天然友好，首屏毫秒级，禁用 JS 也能正常阅读。</p>
        </div>
        <div class="landing-feature">
          <div class="landing-feature-icon">🔒</div>
          <h3 class="landing-feature-title">数据自主</h3>
          <p class="landing-feature-desc">内容存储在你自己的 D1 数据库中，不依赖第三方服务。SQLite 语法，随时导出迁移，备份完全由你掌控。</p>
        </div>
        <div class="landing-feature">
          <div class="landing-feature-icon">🌐</div>
          <h3 class="landing-feature-title">全球加速</h3>
          <p class="landing-feature-desc">Cloudflare 300+ 城市边缘节点，无论读者在哪里都能秒开。D1 读写分离，读请求自动路由到最近副本。</p>
        </div>
        <div class="landing-feature">
          <div class="landing-feature-icon">✏️</div>
          <h3 class="landing-feature-title">Markdown 写作</h3>
          <p class="landing-feature-desc">用 Markdown 写作，服务器端直接渲染为 HTML。支持代码高亮、引用、表格、图片——写作就该这么简单。</p>
        </div>
        <div class="landing-feature">
          <div class="landing-feature-icon">🎨</div>
          <h3 class="landing-feature-title">主题可定制</h3>
          <p class="landing-feature-desc">CSS 变量系统，改一个变量就能换全站配色。清晰的模板结构，前端开发者可以轻松打造独一无二的主题。</p>
        </div>
        <div class="landing-feature">
          <div class="landing-feature-icon">🆓</div>
          <h3 class="landing-feature-title">零成本</h3>
          <p class="landing-feature-desc">Pages 免费额度 + D1 免费 5GB，个人博客不花一分钱。没有隐藏费用，没有付费墙，没有增值套路。</p>
        </div>
        <div class="landing-feature">
          <div class="landing-feature-icon">🔗</div>
          <h3 class="landing-feature-title">干净 URL</h3>
          <p class="landing-feature-desc">伪静态路由 /:slug，URL 简短优雅，搜索引擎更友好。自动生成 sitemap.xml 和 RSS feed。</p>
        </div>
      </section>

      <section class="landing-quote">
        <blockquote>
          "好的博客系统不应该让你花更多时间配置工具，而应该让你花更多时间写作。"
        </blockquote>
      </section>

      <section class="landing-cta">
        <h2 class="landing-cta-title">开始使用 RyeBlog</h2>
        <p class="landing-cta-desc">三步部署，即刻上线：</p>
        <div class="landing-steps">
          <div class="landing-step">
            <span class="landing-step-num">1</span>
            <div class="landing-step-text">Fork RyeBlog 仓库到你的 GitHub</div>
          </div>
          <div class="landing-step">
            <span class="landing-step-num">2</span>
            <div class="landing-step-text">在 Cloudflare Pages 连接仓库</div>
          </div>
          <div class="landing-step">
            <span class="landing-step-num">3</span>
            <div class="landing-step-text">创建 D1 数据库，绑定域名</div>
          </div>
        </div>
        <div class="landing-cta-actions">
          <a class="btn btn-primary" href="https://github.com/ryeblog/ryeblog" target="_blank">Fork on GitHub</a>
          <a class="btn btn-ghost" href="/5-min-deploy">查看部署指南</a>
          <a class="btn btn-ghost" href="/">浏览文档</a>
        </div>
      </section>

      <section class="landing-tech">
        <h2 class="landing-tech-title">技术栈</h2>
        <div class="landing-tech-tags">
          <span class="landing-tech-tag">Cloudflare Pages</span>
          <span class="landing-tech-tag">Cloudflare D1</span>
          <span class="landing-tech-tag">Workers Runtime</span>
          <span class="landing-tech-tag">SSR</span>
          <span class="landing-tech-tag">Markdown</span>
          <span class="landing-tech-tag">SQLite</span>
          <span class="landing-tech-tag">零框架前端</span>
          <span class="landing-tech-tag">CSS Variables</span>
        </div>
      </section>
    </div>`;
}
