'use strict';

const test = require('node:test');
const assert = require('node:assert');

const {
  SsrfBlockedError,
  isIpAllowed,
  isIpv4Allowed,
  isIpv6Allowed,
  validateTargetUrl,
} = require('../src/ssrf');
const { normalizeQueueKey, crawlHttp } = require('../src/engine-http');
const { parseRobots, isAllowedByRobots } = require('../src/discovery');
const { extractPage, extractFeatures } = require('../src/extract');

test('isIpv4Allowed rejects loopback, private, link-local and reserved ranges', () => {
  const blocked = [
    '127.0.0.1', '127.8.8.8', '10.0.0.1', '10.255.255.255',
    '172.16.0.1', '172.31.255.255', '192.168.1.1',
    '169.254.169.254', '0.0.0.0', '0.1.2.3',
    '100.64.0.1', '198.18.0.1', '198.19.255.255',
    '192.0.2.1', '198.51.100.7', '203.0.113.9',
    '224.0.0.1', '239.1.2.3', '240.0.0.1', '255.255.255.255',
    '192.0.0.1', '192.88.99.1',
  ];
  for (const ip of blocked) {
    assert.equal(isIpv4Allowed(ip), false, `${ip} must be blocked`);
  }
});

test('isIpv4Allowed accepts public addresses', () => {
  const allowed = ['93.184.216.34', '142.250.74.46', '1.1.1.1', '8.8.8.8', '172.32.0.1', '172.15.255.255', '192.162.1.1', '100.63.0.1', '100.128.0.1'];
  for (const ip of allowed) {
    assert.equal(isIpv4Allowed(ip), true, `${ip} must be allowed`);
  }
});

test('isIpv6Allowed rejects loopback, unique-local, link-local, multicast and v4-mapped private', () => {
  const blocked = ['::1', '::', 'fe80::1', 'fc00::1', 'fd12:3456::1', 'ff02::1', '2001:db8::1', '::ffff:127.0.0.1', '::ffff:10.0.0.5', '::ffff:192.168.0.9'];
  for (const ip of blocked) {
    assert.equal(isIpv6Allowed(ip), false, `${ip} must be blocked`);
  }
});

test('isIpAllowed rejects non-IP strings', () => {
  assert.equal(isIpAllowed('not-an-ip'), false);
  assert.equal(isIpAllowed(''), false);
});

test('validateTargetUrl rejects bad schemes, credentials and blocked hostnames', () => {
  const cases = [
    'ftp://example.com/x',
    'javascript:alert(1)',
    'file:///etc/passwd',
    'http://user:pass@example.com/',
    'http://localhost/admin',
    'http://app.localhost/',
    'http://my-service.internal/x',
    'http://demo.test/',
    'http://metadata.google.internal/computeMetadata/v1/',
    'http://metadata.goog/',
    'http://127.0.0.1/',
    'http://[::1]/',
    'http://192.168.0.10/',
  ];
  for (const url of cases) {
    assert.throws(() => validateTargetUrl(url), SsrfBlockedError, `${url} must be rejected`);
  }
});

test('validateTargetUrl accepts public http(s) URLs', () => {
  assert.doesNotThrow(() => validateTargetUrl('https://example.com'));
  assert.doesNotThrow(() => validateTargetUrl('http://93.184.216.34/page'));
});

test('normalizeQueueKey strips fragments, trailing slashes and keeps query', () => {
  assert.equal(normalizeQueueKey('https://example.com/a/'), 'https://example.com/a');
  assert.equal(normalizeQueueKey('https://example.com/#section'), 'https://example.com/');
  assert.equal(normalizeQueueKey('https://example.com/?page=2'), 'https://example.com/?page=2');
  assert.equal(normalizeQueueKey('not a url'), null);
});

test('parseRobots extracts rules and sitemaps', () => {
  const rules = parseRobots([
    'User-agent: *',
    'Disallow: /private/',
    'Allow: /private/ok',
    'Sitemap: https://example.com/sm.xml',
    'User-agent: EvilBot',
    'Disallow: /',
  ].join('\n'), 'https://example.com');

  assert.deepEqual(rules.disallow, ['/private/']);
  assert.deepEqual(rules.allow, ['/private/ok']);
  assert.deepEqual(rules.sitemaps, ['https://example.com/sm.xml']);
});

test('isAllowedByRobots honours disallow with allow exceptions', () => {
  const rules = { disallow: ['/private/'], allow: ['/private/ok'] };
  assert.equal(isAllowedByRobots('https://example.com/private/x', rules), false);
  assert.equal(isAllowedByRobots('https://example.com/private/ok/x', rules), true);
  assert.equal(isAllowedByRobots('https://example.com/public/x', rules), true);
  assert.equal(isAllowedByRobots('https://example.com/anything', null), true);
});

function htmlSample() {
  return `
  <html><head>
    <title>Test Shop — Homepage</title>
    <meta name="description" content="Buy quality widgets today.">
    <meta property="og:title" content="Test Shop">
    <meta name="viewport" content="width=device-width, initial-scale=1">
  </head><body>
    <nav><a href="/products">Products</a></nav>
    <h1>Quality Widgets</h1>
    <h2>Why choose us</h2>
    <p>We have testimonials from hundreds of customers and a money-back guarantee.</p>
    <a href="/signup" class="btn">Get started free</a>
    <a href="mailto:hello@example.com">Email us</a>
    <a href="tel:+1234567890">Call us</a>
    <a href="https://wa.me/1234567890">WhatsApp</a>
    <a href="https://calendly.com/testshop/demo">Book a demo</a>
    <form action="/lead" method="post">
      <input type="text" name="name" required>
      <input type="email" name="email" required>
      <input type="text" name="phone">
      <button type="submit">Request a quote</button>
    </form>
    <form action="/login" method="post"><input type="password" name="pw"></form>
    <a href="https://external.example.net/other">External link, must be ignored</a>
    <a href="/download/brochure.pdf">Brochure</a>
  </body></html>`;
}

test('extractPage pulls title, meta, headings and text length', () => {
  const page = extractPage(htmlSample(), 'https://example.com/', 42, 200);
  assert.equal(page.title, 'Test Shop — Homepage');
  assert.equal(page.meta.description, 'Buy quality widgets today.');
  assert.equal(page.meta['og:title'], 'Test Shop');
  assert.deepEqual(page.headings.h1, ['Quality Widgets']);
  assert.deepEqual(page.headings.h2, ['Why choose us']);
  assert.ok(page.text_length > 50);
  assert.equal(page.load_time_ms, 42);
});

test('extractPage detects CTAs, forms, contacts and trust signals', () => {
  const page = extractPage(htmlSample(), 'https://example.com/', 0, 200);
  const byKey = Object.fromEntries(page.features.map((f) => [f.feature_key, f]));

  assert.equal(byKey.cta_buttons.present, true);
  assert.ok(byKey.cta_buttons.evidence.includes('Get started free'));

  assert.equal(byKey.lead_forms.present, true);
  assert.equal(byKey.lead_forms.count, 1, 'password form must be excluded');

  assert.equal(byKey.email_links.present, true);
  assert.equal(byKey.phone_links.present, true);
  assert.equal(byKey.whatsapp_links.present, true);
  assert.equal(byKey.booking_links.present, true);
  assert.equal(byKey.trust_signals.present, true);
  assert.equal(byKey.mobile_viewport_meta.present, true);
  assert.equal(byKey.navigation.present, true);
});

test('extractFeatures ignores executable and external links', () => {
  const result = extractFeatures(htmlSample(), 'https://example.com/');
  assert.ok(!JSON.stringify(result).includes('external.example.net'));
  assert.ok(!JSON.stringify(result).includes('brochure.pdf'));
});

const fakeResolver = async () => [{ address: '93.184.216.34', family: 4 }];

function fakeResponse({ status = 200, contentType = 'text/html; charset=utf-8', body = '<html><title>t</title><body>x</body></html>', location = null, finalUrl = null, timeMs = 5 }) {
  return { statusCode: status, headers: contentType === null ? {} : { 'content-type': contentType, location }, body: Buffer.from(body), finalUrl: finalUrl, originalUrl: null, redirects: [], timeMs };
}

function makeFakeFetcher(routes) {
  return async (url) => {
    for (const route of routes) {
      const hit = typeof route.match === 'string' ? url === route.match : url.startsWith(route.match);
      if (hit) {
        if (route.throw) {
          throw route.error;
        }
        return fakeResponse(route);
      }
    }
    return fakeResponse({ status: 404, contentType: null, body: '' });
  };
}

test('crawlHttp captures a bounded multi-page crawl with depth ordering', async () => {
  const pageFor = (name, links) => fakeResponse({
    body: `<html><head><title>${name}</title></head><body>${links.map((l) => `<a href="${l}">l</a>`).join('')}</body></html>`,
  });

  const fetcher = makeFakeFetcher([
    { match: 'https://shop.example.com/', body: `<html><head><title>Home</title></head><body><a href="/products">p</a><a href="/about">a</a></body></html>` },
    { match: 'https://shop.example.com/products', body: `<html><title>Products</title></head><body><a href="/products/widget-1">w</a><a href="https://other.example.net/skip">x</a></body></html>` },
    { match: 'https://shop.example.com/about', body: `<html><title>About</title></head><body>about us</body></html>` },
    { match: 'https://shop.example.com/products/widget-1', body: `<html><title>Widget</title></head><body>widget page</body></html>` },
  ]);

  const result = await crawlHttp('https://shop.example.com/', { _fetcher: fetcher, maxPages: 10 });

  assert.equal(result.pages.length, 4);
  assert.equal(result.pages[0].title, 'Home');
  assert.equal(result.pages[0].depth, 0);
  assert.deepEqual(result.pages.map((p) => p.depth), [0, 1, 1, 2]);
  assert.ok(!result.pages.some((p) => String(p.url).includes('other.example.net')));
});

test('fetchWithGuards rejects redirect chains pointing at private addresses', async () => {
  const { fetchWithGuards } = require('../src/request');

  const fakeRaw = async (url) => {
    if (url.toString() === 'https://evil.example.com/') {
      return { statusCode: 302, headers: { location: 'http://169.254.169.254/latest/meta-data/' }, body: Buffer.from(''), timeMs: 1 };
    }
    throw new Error('the private target must never be contacted');
  };

  await assert.rejects(
    () => fetchWithGuards('https://evil.example.com/', { maxRedirects: 3, _rawRequest: fakeRaw, _resolver: fakeResolver }),
    SsrfBlockedError,
  );
});

test('fetchWithGuards follows public redirects and revalidates each hop', async () => {
  const { fetchWithGuards } = require('../src/request');
  const hops = [];

  const fakeRaw = async (url) => {
    hops.push(url.toString());
    if (url.toString() === 'https://start.example.com/one') {
      return { statusCode: 301, headers: { location: '/two' }, body: Buffer.from(''), timeMs: 1 };
    }
    return { statusCode: 200, headers: { 'content-type': 'text/html' }, body: Buffer.from('<html><title>final</title></head><body>x</body></html>'), timeMs: 2 };
  };

  const res = await fetchWithGuards('https://start.example.com/one', { maxRedirects: 3, _rawRequest: fakeRaw, _resolver: fakeResolver });

  assert.equal(res.statusCode, 200);
  assert.equal(res.finalUrl, 'https://start.example.com/two');
  assert.equal(hops.length, 2);
});

test('fetchWithGuards caps redirect hops', async () => {
  const { fetchWithGuards } = require('../src/request');
  const infinite = async (url) => ({
    statusCode: 302,
    headers: { location: `/hop-${url.pathname.length}` },
    body: Buffer.from(''),
    timeMs: 1,
  });

  await assert.rejects(
    () => fetchWithGuards('https://loop.example.com/a', { maxRedirects: 3, _rawRequest: infinite, _resolver: fakeResolver }),
    SsrfBlockedError,
  );
});

test('crawlHttp rejects direct private-IP targets', async () => {
  await assert.rejects(
    () => crawlHttp('http://192.168.1.50/', { maxPages: 5 }),
    SsrfBlockedError,
  );
  await assert.rejects(
    () => crawlHttp('http://127.0.0.1:8080/', { maxPages: 5 }),
    SsrfBlockedError,
  );
});

test('crawlHttp records blocked redirects as errors instead of following them', async () => {
  const blockedHosts = [];
  const fetcher = async (url) => {
    if (url === 'https://shop.example.com/start') {
      const { fetchWithGuards } = require('../src/request');
      return fetchWithGuards('https://shop.example.com/start', {
        maxRedirects: 3,
        _rawRequest: async () => ({
          statusCode: 302,
          headers: { location: 'http://10.0.0.9/admin' },
          body: Buffer.from(''),
          timeMs: 1,
        }),
        _resolver: fakeResolver,
      }).catch((err) => {
        blockedHosts.push(String(url));
        throw err;
      });
    }
    return fakeResponse({ body: '<html><title>ok</title></head><body>x</body></html>' });
  };

  const result = await crawlHttp('https://shop.example.com/start', { _fetcher: fetcher, maxPages: 5 });

  assert.equal(result.pages.length, 0);
  assert.ok(result.errors.some((e) => ['blocked_ip', 'invalid_redirect'].includes(e.error_type)));
});

test('crawlHttp stops at the page cap', async () => {
  const fetcher = (url) => fakeResponse({
    body: `<html><title>${url}</title></head><body>${Array.from({ length: 10 }, (_, i) => `<a href="/page-${i}">${i}</a>`).join('')}</body></html>`,
  });

  const result = await crawlHttp('https://grid.example.com/', { _fetcher: fetcher, maxPages: 5 });
  assert.equal(result.pages.length, 5);
});

test('crawlHttp handles DNS failure target as a structured error', async () => {
  const result = await crawlHttp('https://no-such-host-abcxyz.example/foo', {
    _fetcher: (url, opts) => require('../src/request').fetchWithGuards(url, { ...opts, timeoutMs: 5000 }),
    maxPages: 3,
  });

  assert.equal(result.pages.length, 0);
  assert.ok(result.errors.some((e) => ['dns_failure', 'dns_error', 'blocked_hostname'].includes(e.error_type)));
});

test('crawlHttp records per-page HTTP errors', async () => {
  const fetcher = (url) => {
    if (url === 'https://err.example.com/missing') {
      return fakeResponse({ status: 404, contentType: null, body: '' });
    }
    return fakeResponse({ body: '<html><title>ok</title></head><body>x</body></html>' });
  };

  const result = await crawlHttp('https://err.example.com/missing', { _fetcher: fetcher, maxPages: 3 });
  assert.equal(result.pages.length, 0);
  assert.ok(result.errors.some((e) => e.error_type === 'http_404'));
});
