// Tag filtered page - /tag/:name
import { renderPage, buildTagContent } from '../../lib/render.js';

export function onRequestGet(context) {
  const { data, params } = context;
  const tagName = decodeURIComponent(params.name);
  const content = buildTagContent(tagName, data.articles, data.categories, data.tags, data.settings);
  const html = renderPage({ title: `标签: ${tagName}`, content }, data);
  return new Response(html, { headers: { 'Content-Type': 'text/html; charset=utf-8' } });
}
