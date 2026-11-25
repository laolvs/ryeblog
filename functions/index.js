// Homepage - /
import { renderPage, buildHomepageContent } from '../lib/render.js';

export function onRequestGet(context) {
  const { data } = context;
  const content = buildHomepageContent(data.articles, data.categories, data.tags, data.settings);
  const html = renderPage({ title: '首页', content }, data);
  return new Response(html, { headers: { 'Content-Type': 'text/html; charset=utf-8' } });
}
