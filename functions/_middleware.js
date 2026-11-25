// === Pages Functions Middleware ===
// Loads common data (settings, categories, tags, articles) for all pages

import { getSettings, getCategories, getTags, getAllArticles } from '../lib/db.js';

export async function onRequest(context) {
  const { env } = context;
  // Attach common data to context for downstream handlers
  context.data = {
    settings: await getSettings(env.DB),
    categories: await getCategories(env.DB),
    tags: await getTags(env.DB),
    articles: await getAllArticles(env.DB),
  };
  return context.next();
}
