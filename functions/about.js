// About / Landing page - /about
import { renderPage, buildLandingContent } from '../lib/render.js';

export function onRequestGet(context) {
  const { data } = context;
  const content = buildLandingContent(data);
  const html = renderPage({ title: '关于 RyeBlog', content }, data);
  return new Response(html, { headers: { 'Content-Type': 'text/html; charset=utf-8' } });
}
