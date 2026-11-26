'use strict';

const { extractPage } = require('./extract');
const { discoverSeedUrls, isAllowedByRobots } = require('./discovery');
const { fetchWithGuards } = require('./request');
const { normalizeQueueKey, CrawlDeadline } = require('./engine-http');
const { SsrfBlockedError, validateTargetUrl } = require('./ssrf');

function loadPlaywright() {
  try {
    return require('playwright');
  } catch (err) {
    throw new SsrfBlockedError(
      'The playwright engine requires the playwright package: npm install playwright && npx playwright install chromium',
      'playwright_missing',
    );
  }
}

async function crawlPlaywright(targetUrl, options = {}) {
  const { chromium } = loadPlaywright();

  const maxPages = Math.min(Number(options.maxPages || 20), 20);
  const maxDurationMs = options.maxDurationMs || 240000;
  const navigationTimeoutMs = options.pageTimeoutMs || 25000;
  const startedAt = Date.now();
  const expired = () => Date.now() - startedAt >= maxDurationMs;

  const errors = [];
  const pages = [];
  const queue = [normalizeQueueKey(targetUrl)];
  const queued = new Set(queue);
  const depths = new Map([[queue[0], 0]]);

  const seed = validateTargetUrl(targetUrl);
  const targetOrigin = seed.origin;

  const guardOptions = { timeoutMs: 15000, maxBytes: 1024 * 1024 };
  const discovery = await discoverSeedUrls(targetUrl, (u) => fetchWithGuards(u, guardOptions), guardOptions)
    .catch(() => ({ rules: null, sitemapUrls: [] }));
  const robotsRules = discovery.rules;
  for (const url of (robotsRules && robotsRules.sitemapPageUrls) || []) {
    const key = normalizeQueueKey(url);
    if (!key || queued.has(key) || !key.startsWith(targetOrigin)) continue;
    if (robotsRules && !isAllowedByRobots(key, robotsRules)) continue;
    queued.add(key);
    depths.set(key, 1);
    queue.push(key);
    if (queue.length >= maxPages) break;
  }

  const recordError = (url, errorType, message) => {
    if (errors.length < 100) {
      errors.push({ url, error_type: String(errorType).slice(0, 60), message: String(message).slice(0, 1000) });
    }
  };

  const browser = await chromium.launch({
    headless: true,
    args: ['--disable-dev-shm-usage', '--disable-gpu', '--no-sandbox', '--disable-extensions', '--disable-plugins'],
  });

  try {
    while (queue.length > 0 && pages.length < maxPages) {
      if (expired()) throw new CrawlDeadline();

      const url = queue.shift();
      const depth = depths.get(url) || 0;

      let page;
      try {
        page = await browser.newPage();
        const t0 = Date.now();
        const response = await page.goto(url, {
          waitUntil: 'domcontentloaded',
          timeout: navigationTimeoutMs,
        });
        const loadTime = Date.now() - t0;

        const finalUrl = page.url();
        if (new URL(finalUrl).origin !== targetOrigin) {
          recordError(url, 'cross_origin_redirect', 'Page redirected to a different origin. Skipped.');
          continue;
        }

        if (options.screenshotDir) {
          await page.screenshot({
            path: `${options.screenshotDir}/page-${pages.length + 1}-desktop.png`,
            fullPage: false,
          });
        }

        const html = await page.content();
        const parsed = extractPage(html, finalUrl, loadTime, response ? response.status() : null);
        parsed.final_url = finalUrl;
        parsed.depth = depth;

        if (pages.length < maxPages) {
          pages.push(parsed);
          process.stderr.write(`[crawl-playwright] page ${pages.length}/${maxPages} ${url} links=${parsed.links.length} queue=${queue.length}\n`);
          for (const link of parsed.links) {
            const key = normalizeQueueKey(link);
            if (!key || queued.has(key) || !key.startsWith(targetOrigin)) continue;
            if (depths.get(key) !== undefined) continue;
            if (robotsRules && !isAllowedByRobots(key, robotsRules)) continue;
            queued.add(key);
            depths.set(key, depth + 1);
            if (queue.length >= maxPages * 5) break;
          }
        }
      } catch (err) {
        recordError(url, err.code || 'playwright_error', err.message);
        process.stderr.write(`[crawl-playwright] error ${url} ${err.code || 'playwright_error'}: ${String(err.message).slice(0, 200)}\n`);
      } finally {
        if (page) {
          await page.close().catch(() => {});
        }
      }
    }
  } finally {
    await browser.close().catch(() => {});
  }

  return { pages, errors, deadlineReached: expired() };
}

module.exports = { crawlPlaywright };
