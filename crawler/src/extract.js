'use strict';

const cheerio = require('cheerio');

const CTA_PATTERNS = [
  /get\s*started/i, /start\s*(your\s*)?(free\s*)?(trial|plan)/i, /sign\s*up/i, /register/i,
  /book\s*(a\s*)?(call|demo|meeting)/i, /schedule\s*(a\s*)?(call|demo)/i, /request\s*(a\s*)?(demo|quote)/i,
  /contact\s*us/i, /buy\s*now/i, /add\s*to\s*cart/i, /try\s*(it\s*)?free/i, /download/i,
  /subscribe/i, /join\s*now/i, /get\s*a?\s*quote/i, /claim/i, /learn\s*more/i, /apply\s*now/i,
];

const TRUST_PATTERNS = [
  /testimonial/i, /^review/i, /customer\s*(reviews|stories)/i, /case\s*stud/i, /money[-\s]*back/i,
  /guarantee/i, /refund/i, /privacy\s*policy/i, /terms\s*(of\s*service|and\s*conditions)/i,
  /certif/i, /award/i, /as\s*seen\s*in/i, /trusted\s*by/i, /\bssl\b/i, /secure\s* checkout/i,
  /\biso\s*9001\b/i, /\bgdpr\b/i,
];

function textOf($el) {
  return ($el.text() || '').replace(/\s+/g, ' ').trim();
}

function extractFeatures(html, baseUrl) {
  const $ = cheerio.load(html);
  const lowText = $('body').text().replace(/\s+/g, ' ').toLowerCase();
  const features = [];

  const pushFeature = (key, present, count, evidence) => {
    features.push({
      feature_key: key,
      present: Boolean(present),
      count: present ? count : 0,
      evidence: Array.isArray(evidence) ? evidence.slice(0, 10) : [],
    });
  };

  const ctaCandidates = [];
  $('a[href], button, [role="button"], input[type="submit"]').each((_, el) => {
    const label = textOf($(el)) || $(el).attr('value') || $(el).attr('aria-label') || '';
    if (!label) return;
    if (CTA_PATTERNS.some((re) => re.test(label))) {
      const href = $(el).attr('href') || null;
      if (label.length <= 120) {
        ctaCandidates.push({ label, href: href ? resolveUrl(href, baseUrl) : null });
      }
    }
  });
  pushFeature('cta_buttons', ctaCandidates.length > 0, ctaCandidates.length, ctaCandidates.map((c) => c.label));

  const forms = [];
  $('form').each((_, el) => {
    const form = $(el);
    const fields = form
      .find('input, select, textarea')
      .map((__, f) => ({
        type: ($(f).attr('type') || $(f).get(0).tagName || '').toString().toLowerCase(),
        name: $(f).attr('name') || null,
        required: $(f).attr('required') !== undefined || $(f).attr('aria-required') === 'true',
      }))
      .get();
    const passwordFields = fields.filter((f) => f.type === 'password').length;
    if (passwordFields > 0) {
      return;
    }
    fields.forEach((f) => delete f.name);
    forms.push({
      action: form.attr('action') ? resolveUrl(form.attr('action'), baseUrl) : null,
      method: (form.attr('method') || 'get').toLowerCase(),
      field_count: fields.length,
      required_fields: fields.filter((f) => f.required).length,
      has_submit: form.find('[type="submit"], button').length > 0,
    });
  });
  pushFeature('lead_forms', forms.length > 0, forms.length, forms.map((f) => `${f.field_count}-field ${f.method} form`));
  pushFeature('signup_forms', forms.some((f) => f.field_count <= 3 && f.has_submit), forms.filter((f) => f.field_count <= 3 && f.has_submit).length, []);

  const emailLinks = collectHrefs($, 'a[href^="mailto:"]');
  const phoneLinks = collectHrefs($, 'a[href^="tel:"]');
  const whatsappLinks = collectHrefs($, 'a[href*="wa.me"], a[href*="api.whatsapp.com"]');
  const calendarLinks = $('a[href]').toArray()
    .filter((el) => /calendly|cal\.com|acuityscheduling|booking|appointlet/i.test($(el).attr('href') || ''))
    .map((el) => $(el).attr('href'));

  pushFeature('email_links', emailLinks.length > 0, emailLinks.length, emailLinks);
  pushFeature('phone_links', phoneLinks.length > 0, phoneLinks.length, phoneLinks);
  pushFeature('whatsapp_links', whatsappLinks.length > 0, whatsappLinks.length, whatsappLinks);
  pushFeature('booking_links', calendarLinks.length > 0, calendarLinks.length, calendarLinks);

  const trustHits = [];
  const trustSeen = new Set();
  TRUST_PATTERNS.forEach((re) => {
    const match = lowText.match(re);
    if (match) {
      const rule = re.source.replace(/\\[^\\]/g, '').slice(0, 20);
      if (!trustSeen.has(rule)) {
        trustSeen.add(rule);
        trustHits.push(match[0] || '/');
      }
    }
  });
  $('a[href]').each((_, el) => {
    const text = textOf($(el)).toLowerCase();
    if (text.length < 60 && TRUST_PATTERNS.some((re) => re.test(text))) {
      trustHits.push(textOf($(el)).slice(0, 60));
    }
  });
  pushFeature('trust_signals', trustHits.length > 0, trustHits.length, [...new Set(trustHits)].slice(0, 10));

  const hasNav = $('nav, [role="navigation"]').length > 0;  const viewport = $('meta[name="viewport"]').attr('content') || '';
  const isResponsiveMeta = /width\s*=\s*device-width/i.test(viewport);
  pushFeature('mobile_viewport_meta', isResponsiveMeta, isResponsiveMeta ? 1 : 0, [viewport].filter(Boolean));
  pushFeature('navigation', hasNav, $('nav, [role="navigation"]').length, []);

  return {
    features,
    forms,
    ctaCandidates,
    contactMethods: { emailLinks, phoneLinks, whatsappLinks, calendarLinks },
    trustSignals: trustHits,
    isResponsiveMeta,
    hasNav,
  };
}

function collectHrefs($, selector) {
  const out = [];
  $(selector).each((_, el) => {
    const href = $(el).attr('href') || '';
    const value = href.startsWith('mailto:') ? href.slice(7).split('?')[0] : href.slice(0, 300);
    if (value) out.push(value);
  });
  return [...new Set(out)];
}

function resolveUrl(href, baseUrl) {
  try {
    return new URL(href, baseUrl).toString();
  } catch {
    return null;
  }
}

function extractHeadings($) {
  const headings = { h1: [], h2: [], h3: [] };
  ['h1', 'h2', 'h3'].forEach((tag) => {
    $(tag).each((_, el) => {
      const text = textOf($(el)).slice(0, 300);
      if (text) headings[tag].push(text);
    });
  });
  return headings;
}

function extractMeta($) {
  const meta = {};
  const description = $('meta[name="description"]').attr('content');
  if (description) meta.description = description.slice(0, 500);
  const keywords = $('meta[name="keywords"]').attr('content');
  if (keywords) meta.keywords = keywords.slice(0, 500);
  $('meta[property^="og:"]').each((_, el) => {
    const prop = $(el).attr('property');
    if (prop) meta[prop] = String($(el).attr('content') || '').slice(0, 300);
  });
  return meta;
}

function extractInternalLinks($, baseUrl) {
  const links = [];
  const base = new URL(baseUrl);
  const seen = new Set();

  $('a[href]').each((_, el) => {
    let resolved;
    try {
      const href = $(el).attr('href') || '';
      if (href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('tel:') || href.startsWith('javascript:')) {
        return;
      }
      resolved = new URL(href, baseUrl);
    } catch {
      return;
    }
    if (resolved.hostname !== base.hostname) return;
    if (resolved.protocol !== 'http:' && resolved.protocol !== 'https:') return;
    resolved.hash = '';
    if (/\.(pdf|zip|exe|dmg|msi|rar|7z|gz|tar|mp4|mp3|avi|mov|iso|bin|sh|bat)(\?|$)/i.test(resolved.pathname)) {
      return;
    }
    const key = resolved.toString();
    if (seen.has(key)) return;
    seen.add(key);
    if (links.length < 200) {
      links.push(key);
    }
  });

  return links;
}

function extractPage(html, url, loadTimeMs, statusCode) {
  const $ = cheerio.load(html);
  const lowText = $('body').text().replace(/\s+/g, ' ').toLowerCase();
  const extracted = extractFeatures(html, url);

  return {
    url,
    statusCode,
    title: ($('title').first().text() || '').replace(/\s+/g, ' ').trim().slice(0, 500) || null,
    meta: extractMeta($),
    headings: extractHeadings($),
    text_length: lowText.length,
    load_time_ms: Math.round(loadTimeMs || 0),
    features: extracted.features,
    forms: extracted.forms,
    contacts: extracted.contactMethods,
    trust_signals: extracted.trustSignals,
    is_responsive: extracted.isResponsiveMeta,
    links: extractInternalLinks($, url),
  };
}

module.exports = {
  CTA_PATTERNS,
  TRUST_PATTERNS,
  extractPage,
  extractFeatures,
  extractInternalLinks,
  resolveUrl,
};
