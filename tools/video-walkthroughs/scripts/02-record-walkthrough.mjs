// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import zlib from 'node:zlib';
import { chromium } from 'playwright';
import dotenv from 'dotenv';

import { loadContent, TOOL_ROOT } from './lib/content.mjs';
import { validateVoiceoverManifest } from './lib/manifest.mjs';
import { ffprobeDurationSec, runCommand, assertCommand } from './lib/process.mjs';

const VIEWPORT = { width: 1920, height: 1080 };
const RUN_STAMP = new Date().toISOString().slice(11, 16).replace(':', '');
const COOKIE_CONSENT = {
  essential: true,
  analytics: false,
  preferences: true,
  timestamp: new Date().toISOString(),
};

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  await main();
}

export async function main(argv = process.argv.slice(2)) {
  loadEnvironment();
  const options = parseArgs(argv);
  const content = loadContent(options.contentPath);
  const outputRoot = path.join(TOOL_ROOT, 'output');
  const rawDir = path.join(outputRoot, 'raw');
  const assetDir = path.join(outputRoot, 'assets');
  fs.mkdirSync(rawDir, { recursive: true });
  fs.mkdirSync(assetDir, { recursive: true });

  await assertCommand('ffmpeg', ['-version'], 'ffmpeg is required. Install FFmpeg, then ensure ffmpeg is on PATH.');
  await assertFrontendReachable(options.baseUrl, content.tenantSlug);
  const manifest = readVoiceoverManifest(outputRoot, content.id);
  const auth = await authenticate(options.apiUrl, content.tenantSlug);
  const avatarPath = ensureDemoAvatar(assetDir);

  const browser = await chromium.launch({
    headless: options.headless,
    args: [`--window-size=${VIEWPORT.width},${VIEWPORT.height}`],
  });

  let screenVideoPath;
  let rawVideoPath;
  let trimStartSec = 0;
  let sceneTimings = [];
  try {
    await prewarmFrontend(browser, {
      baseUrl: options.baseUrl,
      tenantSlug: content.tenantSlug,
      auth,
      avatarPath,
    });

    const context = await browser.newContext({
      recordVideo: { dir: rawDir, size: VIEWPORT },
      viewport: VIEWPORT,
      deviceScaleFactor: 2,
      ignoreHTTPSErrors: true,
    });
    await installContextScripts(context, auth, true);

    const videoStartedAt = Date.now();
    const page = await context.newPage();
    page.setDefaultTimeout(20_000);
    await gotoTenant(page, {
      baseUrl: options.baseUrl,
      tenantSlug: content.tenantSlug,
      avatarPath,
    }, '/feed');
    await page.waitForTimeout(startSettleMs());
    trimStartSec = Math.max(0, (Date.now() - videoStartedAt) / 1000);

    sceneTimings = [];
    for (const scene of content.scenes) {
      const timing = manifest.scenes.find((item) => item.sceneId === scene.id);
      if (!timing) throw new Error(`Voiceover manifest is missing scene "${scene.id}".`);
      sceneTimings.push(await runTimedScene(page, scene, timing.durationSec, {
        baseUrl: options.baseUrl,
        apiUrl: options.apiUrl,
        tenantSlug: content.tenantSlug,
        avatarPath,
        auth,
      }));
    }

    const video = page.video();
    await context.close();
    rawVideoPath = await video.path();
    const stableRawPath = path.join(rawDir, `${content.id}.webm`);
    fs.copyFileSync(rawVideoPath, stableRawPath);
    rawVideoPath = stableRawPath;

    screenVideoPath = path.join(outputRoot, `${content.id}.screen.mp4`);
    await runCommand('ffmpeg', [
      '-y',
      '-i', rawVideoPath,
      ...(trimStartSec > 0.1 ? ['-ss', trimStartSec.toFixed(3)] : []),
      '-vf', 'fps=30,format=yuv420p',
      '-c:v', 'libx264',
      '-preset', 'slow',
      '-crf', '18',
      '-an',
      screenVideoPath,
    ]);
  } finally {
    await browser.close();
  }

  const durationSec = await ffprobeDurationSec(screenVideoPath);
  const recordingManifest = {
    videoId: content.id,
    rawVideoPath: toToolRelative(rawVideoPath),
    screenVideoPath: toToolRelative(screenVideoPath),
    durationSec: Math.round(durationSec * 1000) / 1000,
    trimStartSec: Math.round(trimStartSec * 1000) / 1000,
    scenes: sceneTimings,
    recordedAt: new Date().toISOString(),
  };
  const recordingManifestPath = path.join(outputRoot, `${content.id}.recording-manifest.json`);
  fs.writeFileSync(recordingManifestPath, JSON.stringify(recordingManifest, null, 2) + '\n', 'utf8');
  console.log(`[record] wrote ${toToolRelative(recordingManifestPath)}`);
  return recordingManifestPath;
}

async function prewarmFrontend(browser, context) {
  const warmContext = await browser.newContext({
    viewport: VIEWPORT,
    deviceScaleFactor: 2,
    ignoreHTTPSErrors: true,
  });
  await installContextScripts(warmContext, context.auth, false);

  try {
    for (const route of ['/feed', '/settings', '/listings/create', '/feed']) {
      const page = await warmContext.newPage();
      page.setDefaultTimeout(10_000);
      try {
        console.log(`[record] warming ${route}`);
        await withTimeout(gotoTenant(page, context, route), 25_000, `warming ${route}`);
      } catch (error) {
        console.warn(`[record] ${error.message}; continuing with recorded walkthrough.`);
      } finally {
        await page.close().catch(() => {});
      }
    }
  } finally {
    await warmContext.close().catch(() => {});
  }
}

async function installContextScripts(context, auth, includeCursor) {
  if (includeCursor) await context.addInitScript(cursorInitScript());
  await context.addInitScript(({ authData, cookieConsent }) => {
    localStorage.setItem('nexus_access_token', authData.accessToken);
    if (authData.refreshToken) localStorage.setItem('nexus_refresh_token', authData.refreshToken);
    if (authData.tenantId) localStorage.setItem('nexus_tenant_id', String(authData.tenantId));
    localStorage.setItem('dev_notice_dismissed', '2.1');
    localStorage.setItem('nexus_install_banner_dismissed', '1');
    localStorage.setItem('nexus_cookie_consent', JSON.stringify(cookieConsent));
  }, { authData: auth, cookieConsent: COOKIE_CONSENT });
}

function withTimeout(promise, timeoutMs, label) {
  let timeout;
  const timer = new Promise((_, reject) => {
    timeout = setTimeout(() => reject(new Error(`Timed out ${label}`)), timeoutMs);
  });
  return Promise.race([promise, timer]).finally(() => clearTimeout(timeout));
}

function startSettleMs() {
  const configured = Number.parseInt(process.env.NEXUS_VIDEO_START_SETTLE_MS ?? '', 10);
  return Number.isFinite(configured) && configured >= 0 ? configured : 30_000;
}

async function runTimedScene(page, scene, durationSec, context) {
  const started = Date.now();
  console.log(`[record] scene ${scene.id}`);
  if (scene.id === 'intro') await sceneIntro(page, context);
  else if (scene.id === 'profile') await sceneProfile(page, context);
  else if (scene.id === 'offer') await sceneListing(page, context, 'offer');
  else if (scene.id === 'request') await sceneListing(page, context, 'request');
  else if (scene.id === 'wrap') await sceneWrap(page, context);
  else await gotoTenant(page, context, '/feed');

  const elapsed = Date.now() - started;
  const target = Math.round(durationSec * 1000);
  if (elapsed < target) await page.waitForTimeout(target - elapsed);
  return {
    sceneId: scene.id,
    audioDurationSec: durationSec,
    durationSec: Math.round(((Date.now() - started) / 1000) * 1000) / 1000,
  };
}

async function sceneIntro(page, context) {
  await gotoTenant(page, context, '/feed');
  await page.waitForTimeout(900);
  await page.mouse.wheel(0, 520);
  await page.waitForTimeout(500);
  await page.mouse.wheel(0, -420);
  await page.waitForTimeout(350);
}

async function sceneProfile(page, context) {
  await gotoTenant(page, context, '/settings');
  await ensureRouteMarker(page, context, '/settings', page.getByText(/Profile Information/i).first());

  const photoInput = page.locator('input[aria-label*="Upload profile photo"], input[type="file"]').first();
  if (await photoInput.count()) {
    await photoInput.setInputFiles(context.avatarPath).catch(() => {});
    await page.waitForTimeout(500);
  }

  const photoButton = page.getByRole('button', { name: /Change profile photo|Upload profile photo/i }).first();
  if (await isVisible(photoButton)) {
    const chooserPromise = page.waitForEvent('filechooser', { timeout: 3000 }).catch(() => null);
    await moveAndClick(page, photoButton);
    const chooser = await chooserPromise;
    if (chooser) {
      await chooser.setFiles(context.avatarPath);
      await page.waitForTimeout(500);
    }
  }

  await fillField(
    page,
    /Tagline/i,
    'Friendly neighbour, keen gardener, and happy helper',
    [page.getByPlaceholder('A short description about yourself').first()],
  );
  await fillField(
    page,
    /^Bio$/i,
    'I enjoy gardening, simple repairs, lifts to appointments, and friendly chats over coffee. I am happy to share practical help with neighbours.',
    [page.getByPlaceholder('Tell others about yourself...').first()],
  );
  const save = page.getByRole('button', { name: /Save Changes/i }).first();
  if (await isVisible(save) && !(await save.isDisabled().catch(() => false))) {
    await moveAndClick(page, save);
    await page.waitForTimeout(500);
  }

  const skillsTab = page.getByRole('tab', { name: /Skills/i }).first();
  if (await isVisible(skillsTab)) {
    await moveAndClick(page, skillsTab);
    await page.waitForTimeout(350);
    await addSkill(page, 'Gardening');
    await addSkill(page, 'Friendly chat');
  }
}

async function sceneListing(page, context, type) {
  await gotoTenant(page, context, '/listings/create');
  await ensureRouteMarker(page, context, '/listings/create', page.getByRole('heading', { name: /Create New Listing/i }).first());
  await page.waitForTimeout(250);

  const isOffer = type === 'offer';
  const title = isOffer ? `Garden tidy-up and seasonal planting ${RUN_STAMP}` : `Help putting up two small shelves ${RUN_STAMP}`;
  const description = isOffer
    ? 'I can help with weeding, planting bulbs, light pruning, and getting a small garden ready for the next season.'
    : 'I would appreciate a steady pair of hands to help measure, level, and put up two small shelves safely.';
  const typeControl = page.getByText(isOffer ? /Offer Help/i : /Request Help/i).first();
  if (await isVisible(typeControl)) await moveAndClick(page, typeControl);

  await fillField(
    page,
    /^Title$/i,
    title,
    [page.getByPlaceholder(/Weekly grocery shopping/i).first()],
  );
  await fillField(
    page,
    /^Description$/i,
    description,
    [page.getByPlaceholder(/Describe what you're offering/i).first()],
  );
  const categoryPattern = isOffer ? /Home & Garden|Community/i : /DIY & Home|Community/i;
  await chooseCategory(page, isOffer ? ['Home & Garden', 'Community'] : ['DIY & Home', 'Community']);
  await fillOptional(page.locator('input[placeholder*="Type a skill"]').first(), isOffer ? 'gardening' : 'DIY');
  const submit = page.getByRole('button', { name: /Create Listing/i }).last();
  await moveAndClick(page, submit);
  await waitForListingDetail(page, context, {
    title,
    description,
    type,
    categoryPattern,
  });
  await page.waitForTimeout(500);
}

async function sceneWrap(page, context) {
  await gotoTenant(page, context, '/wallet');
  await page.waitForTimeout(600);
  await page.mouse.wheel(0, 350);
  await page.waitForTimeout(500);
  await gotoTenant(page, context, '/feed');
  await page.waitForTimeout(400);
}

async function addSkill(page, skillName) {
  const addButton = page.getByRole('button', { name: /Add Skill/i }).first();
  if (!(await isVisible(addButton))) return;
  await moveAndClick(page, addButton);
  const search = page.getByRole('combobox', { name: /Search skills/i }).first()
    .or(page.locator('input[placeholder*="Search"]').first());
  if (!(await isVisible(search))) return;
  await typeInto(page, search, skillName);
  await page.waitForTimeout(350);
  const firstOption = page.getByRole('option').filter({ hasText: new RegExp(skillName, 'i') }).first();
  if (await isVisible(firstOption)) {
    await moveAndClick(page, firstOption);
  } else {
    const useCustom = page.getByRole('button', { name: new RegExp(`Use.*${skillName}`, 'i') }).first();
    if (await isVisible(useCustom)) await moveAndClick(page, useCustom);
  }
  const confirm = page.getByRole('button', { name: /^Add Skill$/i }).last();
  if (await isVisible(confirm)) {
    await moveAndClick(page, confirm);
    await page.waitForTimeout(450);
  }
}

async function chooseCategory(page, preferredNames) {
  const trigger = page.getByText(/Select a category/i).first()
    .or(page.locator('[role="combobox"], button').filter({ hasText: /Category|Select a category/i }).first());
  if (await isVisible(trigger)) {
    await trigger.click();
    await page.waitForTimeout(200);
  }

  for (const name of preferredNames) {
    const option = page.getByRole('option', { name: new RegExp(`^${escapeRegExp(name)}$`, 'i') }).first();
    if (await isVisible(option)) {
      await option.click();
      await page.waitForTimeout(250);
      return;
    }
  }

  const option = page.getByRole('option').first()
    .or(page.locator('[role="listbox"] [role="option"], [role="listbox"] [id]').first());
  if (await isVisible(option)) {
    await option.click();
    await page.waitForTimeout(250);
  }
}

function escapeRegExp(value) {
  return String(value).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

async function waitForListingDetail(page, context, fallbackListing) {
  if (!/\/listings\/\d+/.test(new URL(page.url()).pathname)) {
    try {
      await page.waitForFunction(() => /\/listings\/\d+/.test(window.location.pathname), null, { timeout: 10_000 });
    } catch (error) {
      console.warn('[record] UI listing submit did not reach the detail page; creating via API fallback.');
      const listingId = await createListingViaApi(context, fallbackListing);
      await gotoTenant(page, context, `/listings/${listingId}`);
      return;
    }
  }
  await waitForAppReady(page);
}

async function createListingViaApi(context, listing) {
  const categoryId = await findListingCategoryId(context, listing.categoryPattern);
  const response = await fetch(`${context.apiUrl}/api/v2/listings`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-Tenant-Slug': context.tenantSlug,
      Authorization: `Bearer ${context.auth.accessToken}`,
    },
    body: JSON.stringify({
      title: listing.title,
      description: listing.description,
      type: listing.type,
      category_id: categoryId,
      hours_estimate: 1,
      service_type: 'hybrid',
    }),
  });

  const json = await response.json().catch(() => ({}));
  if (!response.ok) {
    throw new Error(`API listing fallback failed (${response.status}): ${JSON.stringify(json).slice(0, 800)}`);
  }
  const data = json.data ?? json;
  const id = data.id ?? data.listing?.id;
  if (!id) throw new Error(`API listing fallback did not return an id: ${JSON.stringify(json).slice(0, 800)}`);
  return id;
}

async function findListingCategoryId(context, preferred) {
  const response = await fetch(`${context.apiUrl}/api/v2/categories?type=listing`, {
    headers: {
      'X-Tenant-Slug': context.tenantSlug,
      Authorization: `Bearer ${context.auth.accessToken}`,
    },
  });
  const json = await response.json().catch(() => ({}));
  const categories = json.data ?? json.categories ?? json;
  if (!Array.isArray(categories)) return undefined;
  const match = categories.find((category) => preferred.test(String(category.name ?? '')))
    ?? categories.find((category) => String(category.type ?? '') === 'listing')
    ?? categories[0];
  return match?.id;
}

async function gotoTenant(page, context, appPath) {
  const targetUrl = `${context.baseUrl}/${context.tenantSlug}${appPath}`;
  if (canNavigateInApp(page.url(), targetUrl)) {
    await page.evaluate((url) => {
      if (window.location.href !== url) {
        window.history.pushState({}, '', url);
        window.dispatchEvent(new PopStateEvent('popstate', { state: {} }));
      }
    }, targetUrl);
    await page.waitForTimeout(250);
  } else {
    await page.goto(targetUrl, { waitUntil: 'domcontentloaded' });
    await page.waitForLoadState('domcontentloaded');
  }
  await dismissBlockingModals(page);
  await waitForAppReady(page);
  const marker = routeReadyMarker(page, appPath);
  if (marker) await visible(marker, 60_000);
  await dismissBlockingModals(page);
  await page.waitForTimeout(500);
}

async function ensureRouteMarker(page, context, appPath, marker) {
  if (await isVisible(marker)) return;
  await page.goto(`${context.baseUrl}/${context.tenantSlug}${appPath}`, { waitUntil: 'domcontentloaded' });
  await dismissBlockingModals(page);
  await waitForAppReady(page);
  await dismissBlockingModals(page);
  await visible(marker, 60_000);
}

function canNavigateInApp(currentUrl, targetUrl) {
  if (!currentUrl || currentUrl === 'about:blank') return false;
  try {
    return new URL(currentUrl).origin === new URL(targetUrl).origin;
  } catch {
    return false;
  }
}

function routeReadyMarker(page, appPath) {
  if (appPath === '/feed') {
    return page.getByRole('heading', { name: /Community Feed/i }).first();
  }
  if (appPath === '/settings') {
    return page.getByText(/Profile Information/i).first();
  }
  if (appPath === '/listings/create') {
    return page.getByRole('heading', { name: /Create New Listing/i }).first();
  }
  return null;
}

async function dismissBlockingModals(page) {
  const dev = page.locator('#dev-notice-continue');
  if (await isVisible(dev)) await dev.click();
  const cookies = page.getByRole('button', { name: /Accept all|Accept All|Accept all cookies/i }).first();
  if (await isVisible(cookies)) await cookies.click();
}

async function fillField(page, label, value, fallbacks = []) {
  const locators = [page.getByLabel(label).first(), ...fallbacks];
  for (const locator of locators) {
    if (await isVisible(locator)) {
      await typeInto(page, locator, value);
      return;
    }
  }
  await typeInto(page, fallbacks[0] ?? locators[0], value);
}

async function fillOptional(locator, value) {
  if (!(await isVisible(locator))) return;
  await typeInto(locator.page(), locator, value);
  await locator.press('Enter').catch(() => {});
}

async function typeInto(page, locator, value) {
  await visible(locator);
  await moveAndClick(page, locator);
  await locator.fill(value);
  await page.waitForTimeout(120);
}

async function moveAndClick(page, locator) {
  await visible(locator);
  await locator.scrollIntoViewIfNeeded();
  const box = await locator.boundingBox();
  if (!box) {
    await locator.click();
    return;
  }
  const x = box.x + box.width / 2;
  const y = box.y + box.height / 2;
  await page.mouse.move(x, y, { steps: 8 });
  await page.waitForTimeout(60);
  await page.mouse.click(x, y);
}

async function visible(locator, timeout = 20_000) {
  await locator.waitFor({ state: 'visible', timeout });
  return locator;
}

async function isVisible(locator) {
  return locator.isVisible({ timeout: 1200 }).catch(() => false);
}

async function authenticate(apiUrl, tenantSlug) {
  const email = process.env.NEXUS_VIDEO_USER_EMAIL
    || process.env.E2E_USER_EMAIL
    || process.env.E2E_TEST_USER_EMAIL
    || 'test@hour-timebank.ie';
  const password = process.env.NEXUS_VIDEO_USER_PASSWORD
    || process.env.E2E_USER_PASSWORD
    || process.env.E2E_TEST_USER_PASSWORD
    || 'TestPassword123!';

  const login = await fetch(`${apiUrl}/api/auth/login`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-Tenant-Slug': tenantSlug },
    body: JSON.stringify({ email, password, tenant_slug: tenantSlug }),
  });
  if (!login.ok) {
    throw new Error(`Could not authenticate walkthrough member (${login.status}). Set NEXUS_VIDEO_USER_EMAIL and NEXUS_VIDEO_USER_PASSWORD for a local test member.`);
  }
  const json = await login.json();
  const data = json.data ?? json;
  const accessToken = data.access_token;
  if (!accessToken) throw new Error('Login response did not include access_token.');

  await bestEffortApi(apiUrl, '/api/v2/legal/acceptance/accept-all', tenantSlug, accessToken, {});
  await bestEffortApi(apiUrl, '/api/v2/onboarding/complete', tenantSlug, accessToken, { offers: [], needs: [] });

  return {
    accessToken,
    refreshToken: data.refresh_token,
    tenantId: data.tenant_id,
    user: data.user,
  };
}

async function bestEffortApi(apiUrl, route, tenantSlug, accessToken, body) {
  await fetch(`${apiUrl}${route}`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-Tenant-Slug': tenantSlug,
      Authorization: `Bearer ${accessToken}`,
    },
    body: JSON.stringify(body),
  }).catch(() => {});
}

async function assertFrontendReachable(baseUrl, tenantSlug) {
  const response = await fetch(`${baseUrl}/${tenantSlug}/login`, { method: 'GET' }).catch((error) => {
    throw new Error(`Frontend is not reachable at ${baseUrl}. Start it with npm run dev:frontend.\n${error.message}`);
  });
  if (!response.ok && response.status >= 500) {
    throw new Error(`Frontend returned ${response.status} at ${baseUrl}/${tenantSlug}/login.`);
  }
}

function readVoiceoverManifest(outputRoot, videoId) {
  const manifestPath = path.join(outputRoot, `${videoId}.voiceover-manifest.json`);
  if (!fs.existsSync(manifestPath)) {
    throw new Error(`Missing voiceover manifest: ${manifestPath}. Run 01-generate-voiceover first.`);
  }
  return validateVoiceoverManifest(JSON.parse(fs.readFileSync(manifestPath, 'utf8')));
}

function ensureDemoAvatar(assetDir) {
  const avatarPath = path.join(assetDir, 'walkthrough-avatar.png');
  if (!fs.existsSync(avatarPath)) {
    fs.writeFileSync(avatarPath, createDemoAvatarPng());
  }
  return avatarPath;
}

async function waitForAppReady(page) {
  const timeoutMs = 45_000;
  const stableMs = 1000;
  const started = Date.now();
  let stableSince = null;
  let lastText = '';

  while (Date.now() - started < timeoutMs) {
    lastText = await page.locator('body').innerText({ timeout: 1500 }).catch(() => '');
    if (!isBlockingLoadingText(lastText)) {
      stableSince ??= Date.now();
      if (Date.now() - stableSince >= stableMs) return;
    } else {
      stableSince = null;
    }
    await page.waitForTimeout(250);
  }

  throw new Error(`Timed out waiting for the React app to finish loading. Last body text: ${lastText.slice(0, 200)}`);
}

function isBlockingLoadingText(text) {
  const normalized = text.replace(/\s+/g, ' ').trim();
  return normalized === 'Loading...'
    || normalized === 'Loading... Loading...'
    || normalized === 'Loading community'
    || normalized === 'Loading community Loading community';
}

function createDemoAvatarPng(width = 256, height = 256) {
  const stride = width * 4 + 1;
  const raw = Buffer.alloc(stride * height);
  for (let y = 0; y < height; y += 1) {
    const row = y * stride;
    raw[row] = 0;
    for (let x = 0; x < width; x += 1) {
      const offset = row + 1 + x * 4;
      const dx = x - width / 2;
      const headDy = y - 92;
      const bodyDy = y - 210;
      const isHead = dx * dx + headDy * headDy < 45 * 45;
      const isBody = (dx * dx) / (78 * 78) + (bodyDy * bodyDy) / (60 * 60) < 1;
      const isHair = y < 82 && Math.abs(dx) < 58 && x + y > 140 && x - y < 116;
      const color = isHead || isBody
        ? [248, 250, 252, 255]
        : isHair
          ? [253, 230, 138, 255]
          : [15, 118, 110, 255];
      raw[offset] = color[0];
      raw[offset + 1] = color[1];
      raw[offset + 2] = color[2];
      raw[offset + 3] = color[3];
    }
  }

  return Buffer.concat([
    Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]),
    pngChunk('IHDR', Buffer.concat([
      uint32(width),
      uint32(height),
      Buffer.from([8, 6, 0, 0, 0]),
    ])),
    pngChunk('IDAT', zlib.deflateSync(raw)),
    pngChunk('IEND', Buffer.alloc(0)),
  ]);
}

function pngChunk(type, data) {
  const typeBuffer = Buffer.from(type, 'ascii');
  return Buffer.concat([
    uint32(data.length),
    typeBuffer,
    data,
    uint32(crc32(Buffer.concat([typeBuffer, data]))),
  ]);
}

function uint32(value) {
  const buffer = Buffer.alloc(4);
  buffer.writeUInt32BE(value >>> 0);
  return buffer;
}

function crc32(buffer) {
  let crc = 0xffffffff;
  for (const byte of buffer) {
    crc ^= byte;
    for (let bit = 0; bit < 8; bit += 1) {
      crc = (crc >>> 1) ^ (0xedb88320 & -(crc & 1));
    }
  }
  return (crc ^ 0xffffffff) >>> 0;
}

function parseArgs(argv) {
  const options = {
    contentPath: 'content/video-01-getting-started.json',
    baseUrl: (process.env.NEXUS_VIDEO_BASE_URL || process.env.E2E_BASE_URL || 'http://127.0.0.1:5173').replace(/\/$/, ''),
    apiUrl: (process.env.NEXUS_VIDEO_API_URL || process.env.E2E_API_URL || 'http://127.0.0.1:8088').replace(/\/$/, ''),
    headless: process.env.NEXUS_VIDEO_HEADLESS !== '0',
  };
  for (let i = 0; i < argv.length; i += 1) {
    const arg = argv[i];
    const next = argv[i + 1];
    if (arg === '--base-url') { options.baseUrl = next.replace(/\/$/, ''); i += 1; }
    else if (arg === '--api-url') { options.apiUrl = next.replace(/\/$/, ''); i += 1; }
    else if (arg === '--headed') options.headless = false;
    else if (arg === '--headless') options.headless = true;
    else if (!arg.startsWith('--')) options.contentPath = arg;
    else throw new Error(`Unknown recording option: ${arg}`);
  }
  return options;
}

function loadEnvironment() {
  for (const file of ['e2e/.env.test', 'e2e/.env.e2e', '.env']) {
    const resolved = path.resolve(process.cwd(), file);
    if (fs.existsSync(resolved)) dotenv.config({ path: resolved, override: false });
  }
}

function cursorInitScript() {
  return `(() => {
    const install = () => {
      if (document.getElementById('nexus-video-cursor')) return;
      const cursor = document.createElement('div');
      cursor.id = 'nexus-video-cursor';
      cursor.style.cssText = [
        'position:fixed',
        'left:0',
        'top:0',
        'width:22px',
        'height:22px',
        'border-radius:9999px',
        'border:3px solid white',
        'background:rgba(20,184,166,0.85)',
        'box-shadow:0 0 0 3px rgba(15,23,42,0.38),0 8px 20px rgba(15,23,42,0.28)',
        'transform:translate(-50%,-50%)',
        'pointer-events:none',
        'z-index:2147483647',
        'transition:transform 110ms ease, opacity 110ms ease'
      ].join(';');
      document.documentElement.appendChild(cursor);
      window.addEventListener('mousemove', (event) => {
        cursor.style.left = event.clientX + 'px';
        cursor.style.top = event.clientY + 'px';
        cursor.style.opacity = '1';
      }, { passive: true });
      window.addEventListener('mousedown', () => { cursor.style.transform = 'translate(-50%,-50%) scale(0.72)'; }, { passive: true });
      window.addEventListener('mouseup', () => { cursor.style.transform = 'translate(-50%,-50%) scale(1)'; }, { passive: true });
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', install, { once: true });
    else install();
  })();`;
}

function toToolRelative(filePath) {
  return path.relative(TOOL_ROOT, filePath).replace(/\\/g, '/');
}
