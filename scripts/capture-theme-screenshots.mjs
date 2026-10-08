#!/usr/bin/env node

import { spawn, spawnSync } from 'node:child_process';
import { existsSync, mkdirSync, mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { setTimeout as delay } from 'node:timers/promises';
import { get } from 'node:http';
import { get as getHttps } from 'node:https';

const viewport = { width: 1440, height: 1000 };
// Data fixtures differ from one install to another: the product is discovered on
// the storefront. This one is only preferred when it exists and can be bought,
// so that screenshots stay comparable between themes.
const preferredProductSlug = 'comet-pulse-t-shirt';
const maxEvaluatedProducts = 24;
const themeSelectors = {
  canvas: '.canvas-logo',
  prompt_dark: '.prompt-logo',
  prompt_light: '.prompt-logo',
  blush: '.blush-logo',
  volt: '.volt-logo',
  lagoon: '.lagoon-logo',
};

function printUsage() {
  console.log(`Usage: node scripts/capture-theme-screenshots.mjs <theme> [options]

Capture full-page screenshots of the homepage and of a product page.
Screenshots are saved as docs/images/<theme>-homepage.png and
docs/images/<theme>-product.png.

The product is chosen among the products listed on the storefront (homepage,
then category pages): the first one that is in stock, has an add-to-cart button
and a real image is used, the "${preferredProductSlug}" product being preferred
when it qualifies.

Options:
  --include-cart       Add the selected product and capture the cart page
  --product <slug>     Use this product instead of discovering one
  --base-url <url>    Storefront base URL (default: https://app.test/en_US/)
  --output-dir <dir>  Screenshot output directory (default: docs/images)
  --help              Show this help

Requires Node.js 22+ and Google Chrome or Chromium. Set CHROME_BIN to use a
specific browser executable.`);
}

function parseArguments(args) {
  let theme;
  let includeCart = false;
  let productSlug;
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

    if (argument === '--base-url' || argument === '--output-dir' || argument === '--product') {
      const value = args.shift();
      if (!value || value.startsWith('--')) {
        throw new Error(`${argument} requires a value`);
      }
      if (argument === '--base-url') baseUrl = value;
      else if (argument === '--product') productSlug = value;
      else outputDir = value;
      continue;
    }

    if (argument.startsWith('-')) throw new Error(`Unknown option: ${argument}`);
    if (theme) throw new Error(`Unexpected argument: ${argument}`);
    theme = argument;
  }

  if (!theme) throw new Error('A theme name is required');
  if (!/^[a-zA-Z0-9_-]+$/.test(theme)) throw new Error('The theme name may only contain letters, numbers, hyphens, and underscores');
  if (productSlug !== undefined && !/^[a-zA-Z0-9_-]+$/.test(productSlug)) throw new Error('The product slug may only contain letters, numbers, hyphens, and underscores');

  const parsedBaseUrl = new URL(baseUrl);
  if (!['http:', 'https:'].includes(parsedBaseUrl.protocol)) {
    throw new Error('The base URL must use HTTP or HTTPS');
  }
  parsedBaseUrl.pathname = `${parsedBaseUrl.pathname.replace(/\/+$/, '')}/`;

  return { theme, includeCart, productSlug, baseUrl: parsedBaseUrl, outputDir: resolve(outputDir) };
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
  const { theme, includeCart, productSlug, baseUrl, outputDir } = parseArguments(process.argv.slice(2));
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

    const evaluate = async (expression, { awaitPromise = false } = {}) => {
      const result = await send('Runtime.evaluate', { expression, awaitPromise, returnByValue: true });
      if (result.exceptionDetails) {
        throw new Error(result.exceptionDetails.exception?.description ?? result.exceptionDetails.text);
      }
      return result.result.value;
    };

    const navigate = async url => {
      const loaded = waitForEvent(events, 'Page.loadEventFired');
      const navigation = await send('Page.navigate', { url: url.href });
      if (navigation.errorText) throw new Error(`Could not open ${url.href}: ${navigation.errorText}`);
      await loaded;
      await evaluate('document.fonts.ready', { awaitPromise: true });
      await delay(1000);

      if (themeSelectors[theme] && !await evaluate(`document.querySelector(${JSON.stringify(themeSelectors[theme])}) !== null`)) {
        throw new Error(`Expected ${theme} theme marker ${themeSelectors[theme]} was not found at ${url.href}`);
      }
    };

    const outputTheme = theme.replace(/_/g, '-');
    const capture = async (url, name) => {
      const metrics = await send('Page.getLayoutMetrics');
      const size = metrics.cssContentSize;
      const screenshot = await send('Page.captureScreenshot', {
        format: 'png',
        captureBeyondViewport: true,
        fromSurface: true,
        clip: { x: 0, y: 0, width: size.width, height: size.height, scale: 1 },
      });
      const path = join(outputDir, `${outputTheme}-${name}.png`);
      mkdirSync(outputDir, { recursive: true });
      writeFileSync(path, Buffer.from(screenshot.data, 'base64'));
      console.log(`${url.href} -> ${path} (${size.width}x${size.height})`);
    };

    // Product links of the current page, as absolute URLs, without duplicates.
    const productLinksExpression = `[...new Set([...document.querySelectorAll('a[href]')]
      .map(link => new URL(link.getAttribute('href'), document.baseURI))
      .filter(url => url.origin === location.origin && url.pathname.split('/').filter(Boolean).at(-2) === 'products')
      .map(url => url.origin + url.pathname))]`;

    // Fetches a product page and reports whether it makes a good screenshot.
    const evaluateProduct = productUrl => evaluate(`fetch(${JSON.stringify(productUrl)}, { credentials: 'same-origin' })
      .then(async response => {
        if (!response.ok) return { url: ${JSON.stringify(productUrl)}, usable: false, reason: 'HTTP ' + response.status };
        const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
        // Themes may put headings in the header or in off-canvas menus: the product
        // name is the first page heading outside of them.
        const heading = [...doc.querySelectorAll('h1')]
          .find(h1 => !h1.closest('header, nav, .offcanvas, [class*="offcanvas-"]'));
        const name = heading?.textContent.trim() ?? '';
        const button = doc.querySelector('#add-to-cart-button');
        // Real product pictures are served by LiipImagine from /media/, placeholders are not.
        const images = [...doc.querySelectorAll('img')]
          .map(image => image.getAttribute('src') ?? '')
          .filter(src => src.includes('/media/'));
        const reasons = [];
        if (!name) reasons.push('no product name');
        if (!button) reasons.push('no add-to-cart button (out of stock or not purchasable)');
        else if (button.disabled) reasons.push('add-to-cart button disabled');
        if (images.length === 0) reasons.push('no product image');
        return { url: ${JSON.stringify(productUrl)}, name, images: images.length, usable: reasons.length === 0, reason: reasons.join(', ') };
      })`, { awaitPromise: true });

    await send('Page.enable');
    await send('Runtime.enable');

    const homepageUrl = new URL('./', baseUrl);
    await navigate(homepageUrl);
    await capture(homepageUrl, 'homepage');

    let candidates;
    if (productSlug) {
      candidates = [new URL(`./products/${productSlug}`, baseUrl).href];
    } else {
      candidates = await evaluate(productLinksExpression);
      if (candidates.length === 0) {
        // The theme may hide product lists on the homepage: look into the categories.
        const taxonUrls = await evaluate(`[...new Set([...document.querySelectorAll('a[href]')]
          .map(link => new URL(link.getAttribute('href'), document.baseURI))
          .filter(url => url.origin === location.origin && url.pathname.split('/').includes('taxons'))
          .map(url => url.href))].slice(0, 5)`);
        for (const taxonUrl of taxonUrls) {
          const links = await evaluate(`fetch(${JSON.stringify(taxonUrl)}, { credentials: 'same-origin' })
            .then(response => response.text())
            .then(html => { const doc = new DOMParser().parseFromString(html, 'text/html');
              return [...doc.querySelectorAll('a[href]')]
                .map(link => new URL(link.getAttribute('href'), ${JSON.stringify(taxonUrl)}))
                .filter(url => url.pathname.split('/').filter(Boolean).at(-2) === 'products')
                .map(url => url.origin + url.pathname); })`, { awaitPromise: true });
          candidates = [...new Set([...candidates, ...links])];
        }
      }
      candidates.sort((a, b) => Number(b.endsWith(`/products/${preferredProductSlug}`)) - Number(a.endsWith(`/products/${preferredProductSlug}`)));
      candidates = candidates.slice(0, maxEvaluatedProducts);
    }
    if (candidates.length === 0) {
      throw new Error(`No product link was found on ${homepageUrl.href}; load the data fixtures or pass --product <slug>`);
    }

    let product;
    const rejected = [];
    for (const candidate of candidates) {
      const evaluation = await evaluateProduct(candidate);
      if (evaluation.usable) {
        product = evaluation;
        break;
      }
      rejected.push(evaluation);
    }
    if (!product) {
      const details = rejected.map(item => `  - ${item.url}: ${item.reason}`).join('\n');
      throw new Error(`None of the ${candidates.length} evaluated products can be used:\n${details}`);
    }
    for (const item of rejected) console.log(`Skipped ${item.url}: ${item.reason}`);
    console.log(`Selected product: ${product.name} (${product.url})`);

    const productUrl = new URL(product.url);
    await navigate(productUrl);
    await capture(productUrl, 'product');

    if (includeCart) {
      const productName = JSON.stringify(product.name);
      await evaluate(`(() => {
        const button = document.querySelector('#add-to-cart-button');
        if (!button) throw new Error('The add-to-cart button was not found for ' + ${productName});
        button.click();
        return true;
      })()`);

      let cartHasProduct = false;
      for (let attempt = 0; attempt < 30 && !cartHasProduct; attempt++) {
        try {
          cartHasProduct = await evaluate(`fetch(new URL('cart/', ${JSON.stringify(baseUrl.href)}), { credentials: 'same-origin' })
            .then(response => response.text())
            .then(html => new DOMParser().parseFromString(html, 'text/html').body.textContent.includes(${productName}))`, { awaitPromise: true }) === true;
        } catch {
          // Adding to cart redirects to the cart page, which destroys the
          // context being evaluated: retry once the new document is loaded.
          await delay(1000);
          continue;
        }
        if (!cartHasProduct) await delay(500);
      }
      if (!cartHasProduct) throw new Error(`${product.name} did not appear in the cart after adding it`);

      const cartUrl = new URL('cart/', baseUrl);
      await navigate(cartUrl);
      if (!await evaluate(`document.body.innerText.includes(${productName})`)) {
        throw new Error(`The cart page did not render ${product.name}`);
      }
      await capture(cartUrl, 'cart');
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
