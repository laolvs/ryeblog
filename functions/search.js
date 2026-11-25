// Search page - /search
import { renderPage, buildSearchContent } from '../lib/render.js';
import { searchArticles } from '../lib/db.js';

export async function onRequestGet(context) {
  const { data, request, env } = context;
  const url = new URL(request.url);
  const q = url.searchParams.get('q') || '';
  const searchResults = q ? await searchArticles(env.DB, q, 'zh') : [];
  const content = buildSearchContent(q, searchResults, data.articles, data.categories, data.tags, data.settings);
  const html = renderPage({ title: '搜索', content }, data);
  return new Response(html, { headers: { 'Content-Type': 'text/html; charset=utf-8' } });
}
