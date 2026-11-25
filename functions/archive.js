// Archive page - /archive
import { renderPage, buildArchiveContent } from '../lib/render.js';

export function onRequestGet(context) {
  const { data } = context;
  const content = buildArchiveContent(data.articles, data.categories, data.tags, data.settings);
  const html = renderPage({ title: '归档', content }, data);
  return new Response(html, { headers: { 'Content-Type': 'text/html; charset=utf-8' } });
}
