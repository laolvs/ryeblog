// Tags cloud page - /tags
import { renderPage, buildTagsContent } from '../lib/render.js';

export function onRequestGet(context) {
  const { data } = context;
  const content = buildTagsContent(data.articles, data.categories, data.tags, data.settings);
  const html = renderPage({ title: '标签', content }, data);
  return new Response(html, { headers: { 'Content-Type': 'text/html; charset=utf-8' } });
}
