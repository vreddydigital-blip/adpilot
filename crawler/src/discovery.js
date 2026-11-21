'use strict';

const { fetchWithGuards } = require('./request');

const MAX_SITEMAP_URLS = 50;

function parseRobots(body, origin) {
  const rules = { disallow: [], allow: [], sitemaps: [] };
  let appliesToUs = true;

  for (const rawLine of body.split(/\r?\n/)) {
    const line = rawLine.replace(/#.*$/, '').trim();
    if (line === '') continue;

    const m = line.match(/^([a-zA-Z-]+):\s*(.*)$/);
    if (!m) continue;
    const field = m[1].toLowerCase();
    const value = m[2].trim();
    if (value === '' && field !== 'sitemap') continue;

    if (field === 'user-agent') {
      appliesToUs = value === '*' || /adpilot/i.test(value);
      continue;
    }
    if (!appliesToUs) continue;

    if (field === 'disallow' && value !== '') {
      rules.disallow.push(value);
    } else if (field === 'allow' && value !== '') {
      rules.allow.push(value);
    } else if (field === 'sitemap' && value.startsWith('http')) {
      rules.sitemaps.push(value);
    }
  }

  if (rules.sitemaps.length === 0) {
    rules.sitemaps.push(new URL('/sitemap.xml', origin).toString());
  }

  return rules;
}

function isAllowedByRobots(url, rules) {
  if (!rules) return true;
  let path = new URL(url).pathname + (new URL(url).search || '');
  path = path === '' ? '/' : path;

  const matches = (rule) => {
    const clean = rule.replace(/\*$/, '');
    return path.startsWith(clean) || (rule.includes('*') && new RegExp('^' + rule.replace(/[.+?^${}()|[\]\\]/g, '\\$&').replace(/\*/g, '.*')).test(path));
  };

  if (rules.disallow.some(matches) && !rules.allow.some(matches)) {
    return false;
  }
  return true;
}

async function parseSitemap(sitemapUrl, fetchPage, guardOptions) {
  const urls = [];

  const readSitemap = async (target) => {
    try {
      const res = await fetchPage(target, guardOptions);
      if (res.statusCode !== 200) return;
      const body = res.body.toString('utf8');
      const locs = [...body.matchAll(/<loc>\s*([^<\s]+)\s*<\/loc>/gi)].map((m) => m[1]);
      for (const loc of locs) {
        if (urls.length >= MAX_SITEMAP_URLS) return;
        if (/<sitemapindex/i.test(body) && urls.length === 0 && locs.length > 0) {
          continue;
        }
        urls.push(loc);
      }
      if (/<sitemapindex/i.test(body)) {
        for (const loc of locs.slice(0, 3)) {
          if (urls.length >= MAX_SITEMAP_URLS) break;
          await readSitemap(loc);
        }
      }
    } catch {
      return;
    }
  };

  await readSitemap(sitemapUrl);
  return [...new Set(urls)].slice(0, MAX_SITEMAP_URLS);
}

async function discoverSeedUrls(targetUrl, fetchPage, guardOptions) {
  const origin = new URL(targetUrl).origin;
  let rules = null;
  const sitemapUrls = [];

  const robotsUrl = new URL('/robots.txt', origin).toString();
  try {
    const res = await fetchPage(robotsUrl, guardOptions);
    if (res.statusCode === 200) {
      rules = parseRobots(res.body.toString('utf8'), origin);
      sitemapUrls.push(...rules.sitemaps);
    }
  } catch {
    return { rules: null, sitemapUrls: [] };
  }

  for (const sitemapUrl of sitemapUrls.slice(0, 2)) {
    const urls = await parseSitemap(sitemapUrl, fetchPage, guardOptions);
    rules.sitemapPageUrls = urls;
  }

  return { rules, sitemapUrls };
}

module.exports = { parseRobots, isAllowedByRobots, parseSitemap, discoverSeedUrls, MAX_SITEMAP_URLS };
