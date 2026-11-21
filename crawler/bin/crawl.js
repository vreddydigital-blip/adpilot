#!/usr/bin/env node
'use strict';

const fs = require('node:fs');
const crypto = require('node:crypto');
const netmod = require('node:http');
const netsec = require('node:https');

const { crawlHttp } = require('../src/engine-http');
const { crawlPlaywright } = require('../src/engine-playwright');

const USAGE = `
Usage: node bin/crawl.js --url <url> [options]

Options:
  --url <url>             Target website URL (required)
  --job-id <uuid>         Analysis job UUID (embedded in the payload)
  --engine <name>         "http" (default) or "playwright"
  --max-pages <n>         Max pages to crawl (default 20, hard cap 20)
  --max-duration-ms <n>   Overall crawl deadline (default 240000)
  --page-timeout-ms <n>   Per-page timeout (default 15000)
  --output <file>         Write the JSON payload to a file instead of stdout
  --callback-url <url>    POST the HMAC-signed payload to this URL instead of writing it out
                          (requires CRAWLER_CALLBACK_SECRET in the environment)
  --screenshot-dir <dir>  Playwright only: save desktop screenshots here (optional)
  --help                  Show this help
`;

function parseArgs(argv) {
  const args = {};
  for (let i = 0; i < argv.length; i += 1) {
    const arg = argv[i];
    if (!arg.startsWith('--')) continue;
    const key = arg.slice(2);
    if (key === 'help') {
      return { help: true };
    }
    args[key] = argv[i + 1];
    i += 1;
  }
  return args;
}

function buildCompletedPayload(targetUrl, jobId, engine, result, durationMs) {
  return {
    job_id: jobId,
    engine,
    target_url: targetUrl,
    status: 'completed',
    summary: {
      pages_crawled: result.pages.length,
      errors: result.errors.length,
      deadline_reached: Boolean(result.deadlineReached),
      duration_ms: Math.round(durationMs),
    },
    pages: result.pages,
    errors: result.errors,
  };
}

function buildFailedPayload(targetUrl, jobId, engine, reason, message) {
  return {
    job_id: jobId,
    engine,
    target_url: targetUrl,
    status: 'failed',
    failure_reason: reason,
    error_message: String(message).slice(0, 2000),
  };
}

function signPayload(payload, secret) {
  const timestamp = Math.floor(Date.now() / 1000).toString();
  const rawBody = JSON.stringify(payload);
  const signature = crypto.createHmac('sha256', secret).update(`${timestamp}.${rawBody}`).digest('hex');
  return {
    rawBody,
    headers: {
      'content-type': 'application/json',
      'x-adpilot-timestamp': timestamp,
      'x-adpilot-signature': signature,
    },
  };
}

function postCallback(callbackUrl, rawBody, headers, timeoutMs = 20000) {
  return new Promise((resolve, reject) => {
    const url = new URL(callbackUrl);
    if (url.protocol !== 'http:' && url.protocol !== 'https:') {
      reject(new Error('Callback URL must use http or https.'));
      return;
    }
    const req = (url.protocol === 'https:' ? netsec : netmod).request(url, {
      method: 'POST',
      headers: { ...headers, 'content-length': Buffer.byteLength(rawBody) },
      timeout: timeoutMs,
    });
    req.on('response', (res) => {
      res.resume();
      res.on('end', () => resolve(res.statusCode));
    });
    req.on('timeout', () => req.destroy(new Error('Callback request timed out.')));
    req.on('error', reject);
    req.end(rawBody);
  });
}

function emit(payload, args) {
  if (args.output) {
    fs.writeFileSync(args.output, JSON.stringify(payload, null, 2));
  } else {
    process.stdout.write(JSON.stringify(payload, null, 2));
  }
}

async function main() {
  const args = parseArgs(process.argv.slice(2));

  if (args.help || !args.url) {
    process.stderr.write(USAGE);
    process.exit(args.help ? 0 : 1);
  }

  const engine = args.engine || 'http';
  const jobId = args['job-id'] || null;

  const options = {
    maxPages: Number(args['max-pages'] || 20),
    maxDurationMs: Number(args['max-duration-ms'] || 240000),
    pageTimeoutMs: Number(args['page-timeout-ms'] || 15000),
  };

  let outcome;
  const startedAt = Date.now();

  try {
    const result = engine === 'playwright'
      ? await crawlPlaywright(args.url, { ...options, screenshotDir: args['screenshot-dir'] })
      : await crawlHttp(args.url, options);

    if (result.pages.length === 0) {
      const firstError = result.errors[0] || {};
      outcome = {
        ok: false,
        payload: buildFailedPayload(args.url, jobId, engine, firstError.error_type || 'no_pages_crawled', firstError.message || 'No pages could be crawled.'),
      };
    } else {
      outcome = { ok: true, payload: buildCompletedPayload(args.url, jobId, engine, result, Date.now() - startedAt) };
    }
  } catch (err) {
    outcome = {
      ok: false,
      payload: buildFailedPayload(args.url, jobId, engine, err.code || 'crawl_error', err.message),
    };
  }

  if (args['callback-url']) {
    const secret = process.env.CRAWLER_CALLBACK_SECRET || '';
    if (secret === '') {
      process.stderr.write('CRAWLER_CALLBACK_SECRET is not set; cannot sign the callback.\n');
      process.exit(1);
    }
    const signed = signPayload(outcome.payload, secret);
    const statusCode = await postCallback(args['callback-url'], signed.rawBody, signed.headers);
    process.stderr.write(`Callback responded with HTTP ${statusCode}.\n`);
    process.exit(statusCode >= 200 && statusCode < 300 && outcome.ok ? 0 : 1);
  }

  emit(outcome.payload, args);
  process.exit(outcome.ok ? 0 : 1);
}

main().catch((err) => {
  process.stderr.write(`Fatal: ${err.message}\n`);
  process.exit(1);
});
