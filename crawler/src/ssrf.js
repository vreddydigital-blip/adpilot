'use strict';

const net = require('node:net');
const dns = require('node:dns/promises');

const BLOCKED_HOSTNAME_RE = /(^|\.)localhost$|\.local$|\.internal$|\.test$|(^|\.)metadata\.google\.internal$|(^|\.)metadata\.goog$|(^|\.)metadata$/i;

class SsrfBlockedError extends Error {
  constructor(message, code) {
    super(message);
    this.name = 'SsrfBlockedError';
    this.code = code || 'ssrf_blocked';
  }
}

function isIpAllowed(ip) {
  if (net.isIPv4(ip)) {
    return isIpv4Allowed(ip);
  }
  if (net.isIPv6(ip)) {
    return isIpv6Allowed(ip);
  }
  return false;
}

function isIpv4Allowed(ip) {
  const o = ip.split('.').map(Number);
  if (o.length !== 4 || o.some((n) => Number.isNaN(n) || n < 0 || n > 255)) {
    return false;
  }
  const [a, b] = o;
  if (a === 0 || a === 10 || a === 127) return false;
  if (a === 169 && b === 254) return false;
  if (a === 172 && b >= 16 && b <= 31) return false;
  if (a === 192 && b === 168) return false;
  if (a === 192 && b === 0) return false;
  if (a === 100 && b >= 64 && b <= 127) return false;
  if (a === 198 && (b === 18 || b === 19)) return false;
  if (a === 198 && b === 51 && o[2] === 100) return false;
  if (a === 203 && b === 0 && o[2] === 113) return false;
  if (a === 192 && b === 88 && o[2] === 99) return false;
  if (a >= 224) return false;
  return true;
}

function isIpv6Allowed(ip) {
  const lower = String(ip).toLowerCase().replace(/^\[|\]$/g, '');

  const mappedDotted = lower.match(/^::ffff:(\d+\.\d+\.\d+\.\d+)$/);
  if (mappedDotted) {
    return isIpv4Allowed(mappedDotted[1]);
  }
  const mappedHex = lower.match(/^::ffff:([0-9a-f]{1,4}):([0-9a-f]{1,4})$/);
  if (mappedHex) {
    const hi = parseInt(mappedHex[1], 16);
    const lo = parseInt(mappedHex[2], 16);
    return isIpv4Allowed(`${hi >> 8}.${hi & 0xff}.${lo >> 8}.${lo & 0xff}`);
  }

  if (net.isIPv4(lower)) {
    return isIpv4Allowed(lower);
  }

  if (lower === '::' || lower === '::1') return false;
  if (lower.startsWith('fe80')) return false;
  if (lower.startsWith('fc') || lower.startsWith('fd')) return false;
  if (lower.startsWith('ff')) return false;
  if (lower.startsWith('2001:db8')) return false;

  const firstGroup = lower.split(':')[0];
  if (firstGroup !== '' && parseInt(firstGroup, 16) >= 0xfe80) return false;

  return true;
}

function validateHostname(hostname) {
  const host = String(hostname || '').toLowerCase().replace(/^\[|\]$/g, '');
  if (host === '') {
    throw new SsrfBlockedError('Empty hostname.', 'blocked_hostname');
  }
  if (BLOCKED_HOSTNAME_RE.test(host)) {
    throw new SsrfBlockedError(`Blocked hostname: ${host}`, 'blocked_hostname');
  }
  return host;
}

async function resolveAndValidateHost(hostname) {
  const host = validateHostname(hostname);

  if (net.isIP(host)) {
    if (!isIpAllowed(host)) {
      throw new SsrfBlockedError(`Blocked IP address: ${host}`, 'blocked_ip');
    }
    return [{ address: host, family: net.isIPv4(host) ? 4 : 6 }];
  }

  let records;
  try {
    records = await dns.lookup(host, { all: true, verbatim: true });
  } catch (err) {
    const code = err && err.code === 'ENOTFOUND' ? 'dns_failure' : 'dns_error';
    throw new SsrfBlockedError(`DNS resolution failed for ${host}: ${err.code || err.message}`, code);
  }

  if (!Array.isArray(records) || records.length === 0) {
    throw new SsrfBlockedError(`No DNS records for ${host}`, 'dns_failure');
  }

  const allowed = records.filter((record) => isIpAllowed(record.address));
  if (allowed.length === 0) {
    throw new SsrfBlockedError(`All resolved addresses for ${host} are private or reserved.`, 'blocked_ip');
  }

  return allowed;
}

function validateTargetUrl(urlString) {
  let url;
  try {
    url = new URL(urlString);
  } catch {
    throw new SsrfBlockedError('Invalid URL syntax.', 'invalid_url');
  }

  if (url.protocol !== 'http:' && url.protocol !== 'https:') {
    throw new SsrfBlockedError(`Unsupported URL scheme: ${url.protocol}`, 'invalid_scheme');
  }
  if (url.username !== '' || url.password !== '') {
    throw new SsrfBlockedError('URLs with embedded credentials are not allowed.', 'invalid_url');
  }

  const host = validateHostname(url.hostname);

  if (net.isIP(host) && !isIpAllowed(host)) {
    throw new SsrfBlockedError(`Blocked IP address: ${host}`, 'blocked_ip');
  }

  return url;
}

module.exports = {
  SsrfBlockedError,
  isIpAllowed,
  isIpv4Allowed,
  isIpv6Allowed,
  validateHostname,
  validateTargetUrl,
  resolveAndValidateHost,
};
