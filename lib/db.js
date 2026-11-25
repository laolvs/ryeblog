// === Inkwell D1 Data Access Layer ===
// Provides async functions to read/write data from Cloudflare D1

export async function getSettings(DB) {
  const rows = await DB.prepare('SELECT key, value FROM settings').all();
  const settings = {};
  for (const row of rows.results) {
    settings[row.key] = row.value;
  }
  return {
    blogTitle: { zh: settings.blogTitle_zh || 'Inkwell', en: settings.blogTitle_en || 'Inkwell' },
    blogDescription: { zh: settings.blogDescription_zh || '', en: settings.blogDescription_en || '' },
    blogAuthor: settings.blogAuthor || 'Anonymous',
  };
}

export async function getPublishedArticles(DB) {
  const rows = await DB.prepare(`
    SELECT a.*, GROUP_CONCAT(at.tag) as tags
    FROM articles a
    LEFT JOIN article_tags at ON a.id = at.article_id
    WHERE a.status = 'published'
    GROUP BY a.id
    ORDER BY a.date DESC
  `).all();
  return rows.results.map(rowToArticle);
}

export async function getAllArticles(DB) {
  const rows = await DB.prepare(`
    SELECT a.*, GROUP_CONCAT(at.tag) as tags
    FROM articles a
    LEFT JOIN article_tags at ON a.id = at.article_id
    GROUP BY a.id
    ORDER BY a.date DESC
  `).all();
  return rows.results.map(rowToArticle);
}

export async function getArticleBySlug(DB, slug) {
  const row = await DB.prepare(`
    SELECT a.*, GROUP_CONCAT(at.tag) as tags
    FROM articles a
    LEFT JOIN article_tags at ON a.id = at.article_id
    WHERE a.slug = ? OR a.id = ?
    GROUP BY a.id
  `).bind(slug, slug).first();
  return row ? rowToArticle(row) : null;
}

export async function getArticlesByTag(DB, tagName) {
  const rows = await DB.prepare(`
    SELECT a.*, GROUP_CONCAT(at.tag) as tags
    FROM articles a
    INNER JOIN article_tags at ON a.id = at.article_id
    WHERE a.status = 'published' AND at.tag = ?
    GROUP BY a.id
    ORDER BY a.date DESC
  `).bind(tagName).all();
  return rows.results.map(rowToArticle);
}

export async function getArticlesByCategory(DB, categoryId) {
  const rows = await DB.prepare(`
    SELECT a.*, GROUP_CONCAT(at.tag) as tags
    FROM articles a
    LEFT JOIN article_tags at ON a.id = at.article_id
    WHERE a.status = 'published' AND a.category = ?
    GROUP BY a.id
    ORDER BY a.date DESC
  `).bind(categoryId).all();
  return rows.results.map(rowToArticle);
}

export async function getCategories(DB) {
  const rows = await DB.prepare('SELECT * FROM categories').all();
  return rows.results.map(r => ({
    id: r.id,
    name: { zh: r.name_zh, en: r.name_en },
  }));
}

export async function getTags(DB) {
  const rows = await DB.prepare(`
    SELECT t.name, COUNT(at.article_id) as count
    FROM tags t
    LEFT JOIN article_tags at ON t.name = at.tag
    GROUP BY t.name
    ORDER BY count DESC
  `).all();
  return rows.results.map(r => ({ name: r.name, count: r.count }));
}

export async function incrementViews(DB, articleId) {
  await DB.prepare('UPDATE articles SET views = views + 1 WHERE id = ?').bind(articleId).run();
}

export async function searchArticles(DB, query, lang = 'zh') {
  const tokens = tokenize(query);
  if (tokens.length === 0) return [];

  const published = await getPublishedArticles(DB);
  const results = [];

  for (const article of published) {
    const title = getLocalized(article.title, lang);
    const excerpt = getLocalized(article.excerpt, lang);
    const content = getLocalized(article.content, lang);
    const haystack = title + ' ' + excerpt + ' ' + content;

    let matchCount = 0;
    for (const token of tokens) {
      if (haystack.toLowerCase().includes(token.toLowerCase())) {
        matchCount++;
      }
    }

    const matchRatio = matchCount / tokens.length;
    if (matchRatio >= 0.4) {
      results.push({ article, score: matchRatio });
    }
  }

  results.sort((a, b) => b.score - a.score);
  return results.map(r => r.article);
}

// === Helpers ===

function rowToArticle(row) {
  return {
    id: row.id,
    slug: row.slug,
    title: { zh: row.title_zh, en: row.title_en },
    excerpt: { zh: row.excerpt_zh, en: row.excerpt_en },
    content: { zh: row.content_zh, en: row.content_en },
    cover: row.cover,
    category: row.category,
    tags: row.tags ? row.tags.split(',') : [],
    author: row.author,
    date: row.date,
    status: row.status,
    views: row.views,
  };
}

export function getLocalized(obj, lang) {
  if (obj && typeof obj === 'object' && (obj[lang] || obj.zh)) return obj[lang] || obj.zh;
  return obj || '';
}

export function countWords(text) {
  if (!text) return 0;
  const stripped = text.replace(/[#*`>\-\[\]()!]/g, '').trim();
  return stripped.replace(/\s/g, '').length;
}

function tokenize(query) {
  if (!query) return [];
  const tokens = [];
  // Chinese bigram tokenization
  const chineseChars = query.match(/[\u4e00-\u9fff]+/g);
  if (chineseChars) {
    for (const segment of chineseChars) {
      if (segment.length >= 2) {
        for (let i = 0; i < segment.length - 1; i++) {
          tokens.push(segment.substring(i, i + 2));
        }
      } else {
        tokens.push(segment);
      }
    }
  }
  // Non-Chinese tokens
  const nonChinese = query.replace(/[\u4e00-\u9fff]+/g, ' ').split(/\s+/).filter(t => t.length > 0);
  tokens.push(...nonChinese);
  return tokens;
}
