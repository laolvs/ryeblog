// Article detail page - /:slug
import { renderPage, buildArticleContent } from '../lib/render.js';
import { getArticleBySlug, incrementViews } from '../lib/db.js';

export async function onRequestGet(context) {
  const { data, params, env, next } = context;
  const slug = decodeURIComponent(params.slug);

  // Skip static assets — pass through to Pages static file serving
  if (slug.includes('.') || slug === 'favicon.ico') {
    return context.next();
  }

  const article = await getArticleBySlug(env.DB, slug);

  if (!article) {
    const html = renderPage({ title: '文章未找到', content: '<div class="layout-wrapper"><main class="main-content"><p>文章未找到</p></main></div>' }, data);
    return new Response(html, { headers: { 'Content-Type': 'text/html; charset=utf-8' } });
  }

  // Increment views
  await incrementViews(env.DB, article.id);

  const content = buildArticleContent(article, data.categories, data.tags, data.settings);
  const html = renderPage({ title: article.title.zh || article.title.en, content }, data);
  return new Response(html, { headers: { 'Content-Type': 'text/html; charset=utf-8' } });
}
