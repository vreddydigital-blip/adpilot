'use strict';

const { fetchWithGuards } = require('./request');
const { discoverSeedUrls, isAllowedByRobots } = require('./discovery');
const { extractPage } = require('./extract');
const { SsrfBlockedError, validateTargetUrl } = require('./ssrf');

function normalizeQueueKey(urlString) {
  try {
    const url = new URL(urlString);
    url.hash = '';
    if (url.pathname !== '/' && url.pathname.endsWith('/')) {
      url.pathname = url.pathname.replace(/\/+$/, '');
    }
    return url.toString();
  } catch {
    return null;
  }
}

class CrawlDeadline extends Error {
  constructor() {
    super('Crawl deadline reached.');
    this.code = 'crawl_deadline';
  }
}

async function crawlPage(fetcher, url, guardOptions, retries) {
  let lastError = null;

  for (let attempt = 0; attempt <= retries; attempt += 1) {
    try {
      return await fetcher(url, guardOptions);
    } catch (err) {
      lastError = err;
      if (err && ['blocked_ip', 'blocked_hostname', 'invalid_scheme', 'invalid_url'].includes(err.code)) {
        throw err;
      }
    }
  }

  throw lastError;
}

async function crawlHttp(targetUrl, options = {}) {
  const maxPages = Math.min(Number(options.maxPages || 20), 20);
  const maxDurationMs = options.maxDurationMs || 240000;
  const pageTimeoutMs = options.pageTimeoutMs || 15000;
  const guardOptions = { timeoutMs: pageTimeoutMs, maxBytes: options.maxBytes || 5 * 1024 * 1024, maxRedirects: 4 };
  const concurrency = Math.min(Number(options.concurrency || 3), 5);
  const retries = options.retries ?? 1;
  const fetcher = options._fetcher || fetchWithGuards;

  const startedAt = Date.now();
  const expired = () => Date.now() - startedAt >= maxDurationMs;

  const errors = [];
  const pages = [];
  const queue = [];
  const queued = new Set();
  const depths = new Map();

  const seed = validateTargetUrl(targetUrl);
  const targetOrigin = seed.origin;
  const startKey = normalizeQueueKey(targetUrl);

  if (!startKey) {
    throw new SsrfBlockedError('Target URL could not be normalized.', 'invalid_url');
  }

  queue.push(startKey);
  queued.add(startKey);
  depths.set(startKey, 0);

  const recordError = (url, errorType, message) => {
    if (errors.length < 100) {
      errors.push({ url, error_type: String(errorType).slice(0, 60), message: String(message).slice(0, 1000) });
    }
  };

  const discovery = await discoverSeedUrls(targetUrl, fetcher, guardOptions)
    .catch(() => ({ rules: null, sitemapUrls: [] }));
  const robotsRules = discovery.rules;

  for (const sitemapUrl of (robotsRules && robotsRules.sitemapPageUrls) || []) {
    const key = normalizeQueueKey(sitemapUrl);
    if (!key || queued.has(key) || !key.startsWith(targetOrigin)) continue;
    if (robotsRules && !isAllowedByRobots(key, robotsRules)) continue;
    queued.add(key);
    depths.set(key, 1);
    queue.push(key);
    if (queue.length >= maxPages) break;
  }

  const processUrl = async (url, depth) => {
    try {
      const res = await crawlPage(fetcher, url, guardOptions, retries);

      const contentType = res.headers['content-type'] || '';
      if (res.statusCode >= 200 && res.statusCode < 300 && /text\/html|application\/xhtml/i.test(contentType)) {
        const finalUrl = res.finalUrl || url;
        const parsed = new URL(finalUrl);
        if (parsed.origin !== targetOrigin) {
          recordError(url, 'cross_origin_redirect', 'Page redirected to a different origin. Skipped.');
          return;
        }
        parsed.hash = '';
        const page = extractPage(res.body.toString('utf8'), parsed.toString(), res.timeMs, res.statusCode);
        page.final_url = finalUrl;
        page.depth = depth;

        if (pages.length < maxPages) {
          pages.push(sanitizePage(page));
          process.stderr.write(`[crawl-http] page ${pages.length}/${maxPages} ${url} links=${page.links.length} queue=${queue.length}\n`);
          for (const link of page.links) {
            const key = normalizeQueueKey(link);
            if (!key || queued.has(key) || !key.startsWith(targetOrigin)) continue;
            if (robotsRules && !isAllowedByRobots(key, robotsRules)) continue;
            if (depths.has(key)) continue;
            queued.add(key);
            depths.set(key, depth + 1);
            queue.push(key);
          }
        }
      } else if (res.statusCode >= 400) {
        recordError(url, `http_${res.statusCode}`, `Page returned HTTP ${res.statusCode}.`);
      }
    } catch (err) {
      recordError(url, err.code || 'crawl_error', err.message);
      process.stderr.write(`[crawl-http] error ${url} ${err.code || 'crawl_error'}: ${String(err.message).slice(0, 200)}\n`);
      if (err instanceof CrawlDeadline) throw err;
    }
  };

  let deadlineHit = false;

  try {
    while (queue.length > 0 && pages.length < maxPages) {
      if (expired()) {
        throw new CrawlDeadline();
      }

      const batchSize = Math.min(concurrency, queue.length);
      const batch = queue.splice(0, batchSize);
      const results = await Promise.allSettled(batch.map((url) => processUrl(url, depths.get(url) || 0)));

      if (results.some((r) => r.status === 'rejected')) {
        throw new CrawlDeadline();
      }
    }
  } catch (err) {
    if (err instanceof CrawlDeadline) {
      deadlineHit = true;
    } else {
      throw err;
    }
  }

  return { pages, errors, deadlineReached: deadlineHit || expired() };
}

function sanitizePage(page) {
  return {
    url: page.url,
    final_url: page.final_url || page.url,
    http_status: page.statusCode ?? null,
    title: page.title,
    meta: page.meta,
    headings: page.headings,
    text_length: page.text_length,
    load_time_ms: page.load_time_ms,
    depth: page.depth || 0,
    features: page.features,
    forms: page.forms,
    contacts: page.contacts,
    trust_signals: page.trust_signals,
    is_responsive: page.is_responsive,
  };
}

module.exports = { crawlHttp, normalizeQueueKey, CrawlDeadline };
