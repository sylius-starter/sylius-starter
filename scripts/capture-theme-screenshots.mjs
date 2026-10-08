#!/usr/bin/env node

import { spawn, spawnSync } from 'node:child_process';
import { existsSync, mkdirSync, mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { setTimeout as delay } from 'node:timers/promises';
import { get } from 'node:http';
import { get as getHttps } from 'node:https';

const viewport = { width: 1440, height: 1000 };
const pages = [
  { name: 'homepage', route: './' },
  { name: 'comet-pulse-product', route: './products/comet-pulse-t-shirt' },
];
const themeSelectors = {
  canvas: '.canvas-logo',
  prompt_dark: '.prompt-logo',
  prompt_light: '.prompt-logo',
  blush: '.blush-logo',
  volt: '.volt-logo',
};

function printUsage() {
  console.log(`Usage: node scripts/capture-theme-screenshots.mjs <theme> [options]

Capture full-page screenshots of the homepage and Comet Pulse T-Shirt product page.
Screenshots are saved as docs/images/<theme>-homepage.png and
docs/images/<theme>-comet-pulse-product.png.

Options:
  --include-cart       Add the Comet Pulse T-Shirt and capture the cart page
  --base-url <url>    Storefront base URL (default: https://app.test/en_US/)
  --output-dir <dir>  Screenshot output directory (default: docs/images)
  --help              Show this help

Requires Node.js 22+ and Google Chrome or Chromium. Set CHROME_BIN to use a
specific browser executable.`);
}

function parseArguments(args) {
  let theme;
  let includeCart = false;
  let baseUrl = 'https://app.test/en_US/';
  let outputDir = 'docs/images';

  while (args.length > 0) {
    const argument = args.shift();
    if (argument === '--help' || argument === '-h') {
      printUsage();
      process.exit(0);
    }

    if (argument === '--include-cart') {
      includeCart = true;
      continue;
    }

    if (argument === '--base-url' || argument === '--output-dir') {
      const value = args.shift();
      if (!value || value.startsWith('--')) {
        throw new Error(`${argument} requires a value`);
      }
      if (argument === '--base-url') baseUrl = value;
      else outputDir = value;
      continue;
    }

    if (argument.startsWith('-')) throw new Error(`Unknown option: ${argument}`);
    if (theme) throw new Error(`Unexpected argument: ${argument}`);
    theme = argument;
  }

  if (!theme) throw new Error('A theme name is required');
  if (!/^[a-zA-Z0-9_-]+$/.test(theme)) throw new Error('The theme name may only contain letters, numbers, hyphens, and underscores');

  const parsedBaseUrl = new URL(baseUrl);
  if (!['http:', 'https:'].includes(parsedBaseUrl.protocol)) {
    throw new Error('The base URL must use HTTP or HTTPS');
  }
  parsedBaseUrl.pathname = `${parsedBaseUrl.pathname.replace(/\/+$/, '')}/`;

  return { theme, includeCart, baseUrl: parsedBaseUrl, outputDir: resolve(outputDir) };
}

function findChrome() {
  if (process.env.CHROME_BIN) return process.env.CHROME_BIN;

  const candidates = process.platform === 'darwin'
    ? [
        '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
        '/Applications/Chromium.app/Contents/MacOS/Chromium',
      ]
    : process.platform === 'win32'
      ? [
          'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
          'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
        ]
      : ['google-chrome', 'chromium', 'chromium-browser'];

  if (process.platform !== 'win32' && process.platform !== 'darwin') {
    return candidates.find(candidate => {
      try {
        const result = spawnSync(candidate, ['--version'], { stdio: 'ignore' });
        return result.status === 0;
      } catch {
        return false;
      }
    });
  }

  return candidates.find(candidate => existsSync(candidate));
}

function getJson(url) {
  return new Promise((resolvePromise, reject) => {
    const request = (url.startsWith('https:') ? getHttps : get)(url, response => {
      let body = '';
      response.setEncoding('utf8');
      response.on('data', chunk => body += chunk);
      response.on('end', () => {
        if (response.statusCode < 200 || response.statusCode >= 300) {
          reject(new Error(`Chrome DevTools returned HTTP ${response.statusCode}`));
          return;
        }
        try {
          resolvePromise(JSON.parse(body));
        } catch (error) {
          reject(error);
        }
      });
    });
    request.setTimeout(5000, () => request.destroy(new Error('Timed out connecting to Chrome DevTools')));
    request.on('error', reject);
  });
}

async function waitForEvent(events, method) {
  return new Promise((resolvePromise, reject) => {
    const timeout = setTimeout(() => {
      reject(new Error(`Timed out waiting for Chrome DevTools event ${method}`));
    }, 45_000);
    if (!events.has(method)) events.set(method, []);
    events.get(method).push(params => {
      clearTimeout(timeout);
      resolvePromise(params);
    });
  });
}

async function main() {
  const { theme, includeCart, baseUrl, outputDir } = parseArguments(process.argv.slice(2));
  const chrome = findChrome();
  if (!chrome) throw new Error('Could not find Chrome or Chromium; set CHROME_BIN to its executable');

  const profileDir = mkdtempSync(join(tmpdir(), 'theme-screenshots-'));
  const child = spawn(chrome, [
    '--headless=new',
    '--no-sandbox',
    '--disable-gpu',
    '--hide-scrollbars',
    '--ignore-certificate-errors',
    `--window-size=${viewport.width},${viewport.height}`,
    '--remote-debugging-port=0',
    `--user-data-dir=${profileDir}`,
    'about:blank',
  ], { stdio: ['ignore', 'ignore', 'pipe'] });
  let stderr = '';
  let launchError;
  child.on('error', error => launchError = error);
  child.stderr.setEncoding('utf8');
  child.stderr.on('data', chunk => stderr += chunk);

  let ws;
  try {
    let port;
    for (let attempt = 0; attempt < 150 && !port; attempt++) {
      if (launchError) throw new Error(`Could not start Chrome: ${launchError.message}`);
      if (child.exitCode !== null) throw new Error(`Chrome exited before starting: ${stderr}`);
      port = stderr.match(/DevTools listening on ws:\/\/127\.0\.0\.1:(\d+)\//)?.[1];
      if (!port) await delay(100);
    }
    if (!port) throw new Error(`Chrome DevTools did not start: ${stderr}`);

    const targets = await getJson(`http://127.0.0.1:${port}/json`);
    const target = targets.find(item => item.type === 'page');
    if (!target) throw new Error('Chrome did not expose a page target');

    ws = new WebSocket(target.webSocketDebuggerUrl);
    await new Promise((resolvePromise, reject) => {
      const timeout = setTimeout(() => {
        reject(new Error('Timed out connecting to the Chrome DevTools page'));
      }, 10_000);
      ws.addEventListener('open', () => {
        clearTimeout(timeout);
        resolvePromise();
      }, { once: true });
      ws.addEventListener('error', error => {
        clearTimeout(timeout);
        reject(error);
      }, { once: true });
    });

    let nextId = 0;
    const pending = new Map();
    const events = new Map();
    ws.addEventListener('message', event => {
      const message = JSON.parse(event.data);
      if (message.id && pending.has(message.id)) {
        const callbacks = pending.get(message.id);
        pending.delete(message.id);
        message.error ? callbacks.reject(new Error(message.error.message)) : callbacks.resolve(message.result);
      }
      if (message.method) {
        for (const resolvePromise of events.get(message.method) || []) resolvePromise(message.params);
        events.delete(message.method);
      }
    });
    ws.addEventListener('close', () => {
      for (const callbacks of pending.values()) callbacks.reject(new Error('Chrome DevTools connection closed'));
      pending.clear();
    });

    const send = (method, params = {}) => new Promise((resolvePromise, reject) => {
      const id = ++nextId;
      pending.set(id, { resolve: resolvePromise, reject });
      ws.send(JSON.stringify({ id, method, params }));
    });

    await send('Page.enable');
    await send('Runtime.enable');
    for (const page of pages) {
      const url = new URL(page.route, baseUrl);
      const loaded = waitForEvent(events, 'Page.loadEventFired');
      const navigation = await send('Page.navigate', { url: url.href });
      if (navigation.errorText) throw new Error(`Could not open ${url.href}: ${navigation.errorText}`);
      await loaded;
      await send('Runtime.evaluate', {
        expression: 'document.fonts.ready',
        awaitPromise: true,
        returnByValue: true,
      });
      await delay(1000);

      if (themeSelectors[theme]) {
        const result = await send('Runtime.evaluate', {
          expression: `document.querySelector(${JSON.stringify(themeSelectors[theme])}) !== null`,
          returnByValue: true,
        });
        if (!result.result.value) {
          throw new Error(`Expected ${theme} theme marker ${themeSelectors[theme]} was not found at ${url.href}`);
        }
      }

      const metrics = await send('Page.getLayoutMetrics');
      const size = metrics.cssContentSize;
      const capture = await send('Page.captureScreenshot', {
        format: 'png',
        captureBeyondViewport: true,
        fromSurface: true,
        clip: { x: 0, y: 0, width: size.width, height: size.height, scale: 1 },
      });
      const outputTheme = theme.replace(/_/g, '-');
      const path = join(outputDir, `${outputTheme}-${page.name}.png`);
      mkdirSync(outputDir, { recursive: true });
      writeFileSync(path, Buffer.from(capture.data, 'base64'));
      console.log(`${url.href} -> ${path} (${size.width}x${size.height})`);
    }

    if (includeCart) {
      const addToCart = await send('Runtime.evaluate', {
        expression: `(() => {
          const button = document.querySelector('#add-to-cart-button');
          if (!button) throw new Error('The Comet Pulse T-Shirt add-to-cart button was not found');
          button.click();
          return true;
        })()`,
        returnByValue: true,
      });
      if (addToCart.exceptionDetails) {
        throw new Error(addToCart.exceptionDetails.exception?.description ?? 'Could not add the Comet Pulse T-Shirt to the cart');
      }

      let cartHasProduct = false;
      for (let attempt = 0; attempt < 30 && !cartHasProduct; attempt++) {
        try {
          const result = await send('Runtime.evaluate', {
            expression: `fetch(new URL('cart/', document.baseURI), { credentials: 'same-origin' })
              .then(response => response.text())
              .then(html => html.includes('Comet Pulse T-Shirt'))`,
            awaitPromise: true,
            returnByValue: true,
          });
          cartHasProduct = result.result.value === true;
        } catch {
          // Adding to cart redirects to the cart page, which destroys the
          // context being evaluated: retry once the new document is loaded.
          await delay(1000);
          continue;
        }
        if (!cartHasProduct) await delay(500);
      }
      if (!cartHasProduct) throw new Error('The Comet Pulse T-Shirt did not appear in the cart after adding it');

      const cartUrl = new URL('cart/', baseUrl);
      const loaded = waitForEvent(events, 'Page.loadEventFired');
      const navigation = await send('Page.navigate', { url: cartUrl.href });
      if (navigation.errorText) throw new Error(`Could not open ${cartUrl.href}: ${navigation.errorText}`);
      await loaded;
      await send('Runtime.evaluate', {
        expression: 'document.fonts.ready',
        awaitPromise: true,
        returnByValue: true,
      });
      await delay(500);

      const cartPageHasProduct = await send('Runtime.evaluate', {
        expression: "document.body.innerText.includes('Comet Pulse T-Shirt')",
        returnByValue: true,
      });
      if (!cartPageHasProduct.result.value) throw new Error('The cart page did not render the Comet Pulse T-Shirt');

      const metrics = await send('Page.getLayoutMetrics');
      const size = metrics.cssContentSize;
      const capture = await send('Page.captureScreenshot', {
        format: 'png',
        captureBeyondViewport: true,
        fromSurface: true,
        clip: { x: 0, y: 0, width: size.width, height: size.height, scale: 1 },
      });
      const outputTheme = theme.replace(/_/g, '-');
      const path = join(outputDir, `${outputTheme}-cart.png`);
      writeFileSync(path, Buffer.from(capture.data, 'base64'));
      console.log(`${cartUrl.href} -> ${path} (${size.width}x${size.height})`);
    }
  } finally {
    ws?.close();
    if (child.pid && child.exitCode === null) {
      const exited = new Promise(resolvePromise => child.once('exit', resolvePromise));
      child.kill('SIGTERM');
      await exited;
    }
    rmSync(profileDir, { recursive: true, force: true });
  }
}

main().catch(error => {
  console.error(`Screenshot capture failed: ${error.message}`);
  process.exitCode = 1;
});
