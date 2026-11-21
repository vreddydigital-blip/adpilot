'use strict';

const http = require('node:http');
const https = require('node:https');

const { SsrfBlockedError, isIpAllowed, resolveAndValidateHost, validateTargetUrl } = require('./ssrf');

function rawRequest(url, { addresses, timeoutMs, maxBytes }) {
  return new Promise((resolve, reject) => {
    const isHttps = url.protocol === 'https:';
    const mod = isHttps ? https : http;
    const primary = addresses[0];

    const req = mod.request(url, {
      method: 'GET',
      headers: {
        'user-agent': 'AdPilotAICrawler/0.1 (+marketing-intelligence-crawler; bounded crawl)',
        accept: 'text/html,application/xhtml+xml,application/json;q=0.9,*/*;q=0.5',
        'accept-encoding': 'identity',
      },
      timeout: timeoutMs,
      lookup: (hostname, options, callback) => {
        if (typeof options === 'function') {
          callback = options;
          options = {};
        }
        if (options && options.all) {
          callback(null, [primary]);
          return;
        }
        callback(null, primary.address, primary.family);
      },
      agent: false,
    });

    let settled = false;
    const finish = (fn, value) => {
      if (settled) return;
      settled = true;
      fn(value);
    };

    req.on('timeout', () => {
      req.destroy(new SsrfBlockedError(`Request timed out after ${timeoutMs}ms.`, 'timeout'));
    });
    req.on('error', (err) => finish(reject, err));

    req.on('response', (res) => {
      const chunks = [];
      let size = 0;

      res.on('data', (chunk) => {
        size += chunk.length;
        if (size > maxBytes) {
          res.destroy();
          finish(reject, new SsrfBlockedError(
            `Response body exceeded the ${maxBytes} byte limit.`,
            'response_too_large',
          ));
          return;
        }
        chunks.push(chunk);
      });

      res.on('end', () => {
        finish(resolve, {
          statusCode: res.statusCode,
          headers: res.headers,
          body: Buffer.concat(chunks),
          timeMs: Date.now() - startedAt,
        });
      });
      res.on('error', (err) => finish(reject, err));
    });

    const startedAt = Date.now();
    req.end();
  });
}

async function fetchWithGuards(rawUrl, options = {}) {
  const maxRedirects = options.maxRedirects ?? 4;
  const timeoutMs = options.timeoutMs ?? 15000;
  const maxBytes = options.maxBytes ?? 5 * 1024 * 1024;
  const transport = options._rawRequest || rawRequest;
  const resolver = options._resolver || resolveAndValidateHost;

  let url = validateTargetUrl(rawUrl);
  const chain = [];

  for (let hop = 0; hop <= maxRedirects; hop += 1) {
    const addresses = await resolver(url.hostname);
    const response = await transport(url, { addresses, timeoutMs, maxBytes });

    const location = response.headers.location;

    if (response.statusCode >= 300 && response.statusCode < 400 && location) {
      let next;
      try {
        next = new URL(location, url);
      } catch {
        throw new SsrfBlockedError('Redirect Location header was not a valid URL.', 'invalid_redirect');
      }
      if (next.protocol !== 'http:' && next.protocol !== 'https:') {
        throw new SsrfBlockedError(`Redirect used a forbidden scheme: ${next.protocol}`, 'invalid_redirect');
      }
      next.hash = '';
      validateTargetUrl(next.toString());
      chain.push({ status: response.statusCode, location: next.toString() });
      url = next;
      continue;
    }

    return {
      statusCode: response.statusCode,
      headers: response.headers,
      body: response.body,
      finalUrl: url.toString(),
      originalUrl: rawUrl,
      redirects: chain,
      timeMs: response.timeMs,
    };
  }

  throw new SsrfBlockedError(`Exceeded ${maxRedirects} redirect hops.`, 'too_many_redirects');
}

module.exports = { fetchWithGuards, rawRequest };
