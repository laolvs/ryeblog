// Sitemap - /sitemap.xml
import { buildSitemapXml } from '../lib/render.js';

export function onRequestGet(context) {
  const { data, request } = context;
  const host = new URL(request.url).origin;
  const xml = buildSitemapXml(data.articles, data.categories, data.tags, host);
  return new Response(xml, { headers: { 'Content-Type': 'application/xml; charset=utf-8' } });
}
