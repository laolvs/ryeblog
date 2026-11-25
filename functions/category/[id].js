// Category filtered page - /category/:id
import { renderPage, buildCategoryContent } from '../../lib/render.js';

export function onRequestGet(context) {
  const { data, params } = context;
  const catId = params.id;
  const content = buildCategoryContent(catId, data.articles, data.categories, data.tags, data.settings);
  const html = renderPage({ title: '分类', content }, data);
  return new Response(html, { headers: { 'Content-Type': 'text/html; charset=utf-8' } });
}
