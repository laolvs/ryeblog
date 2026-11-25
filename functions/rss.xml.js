// RSS feed - /rss.xml
import { buildRssXml } from '../lib/render.js';

export function onRequestGet(context) {
  const { data, request } = context;
  const host = new URL(request.url).origin;
  const xml = buildRssXml(data.articles, data.settings, host);
  return new Response(xml, { headers: { 'Content-Type': 'application/rss+xml; charset=utf-8' } });
}
