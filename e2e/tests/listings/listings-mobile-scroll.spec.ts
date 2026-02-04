import { test, expect } from '@playwright/test';
import { tenantUrl } from '../../helpers/test-utils';

/**
 * Mobile Scroll Tests for /listings
 *
 * These tests diagnose scroll issues on mobile devices.
 * Run with: npx playwright test listings-mobile-scroll --project=mobile-chrome
 */

test.describe('Listings - Mobile Scroll', () => {
  test.beforeEach(async ({ page }) => {
    // Navigate to listings page
    await page.goto(tenantUrl('listings'));
    await page.waitForLoadState('networkidle');
  });

  test('page should be scrollable', async ({ page }) => {
    // Get initial scroll position
    const initialScrollY = await page.evaluate(() => window.scrollY);

    // Get page height
    const pageHeight = await page.evaluate(() => document.documentElement.scrollHeight);
    const viewportHeight = await page.evaluate(() => window.innerHeight);

    console.log(`Page height: ${pageHeight}px, Viewport: ${viewportHeight}px`);

    // Page should be taller than viewport (scrollable)
    expect(pageHeight).toBeGreaterThan(viewportHeight);

    // Try to scroll down
    await page.evaluate(() => window.scrollTo(0, 500));
    await page.waitForTimeout(100);

    const newScrollY = await page.evaluate(() => window.scrollY);
    console.log(`Scrolled from ${initialScrollY} to ${newScrollY}`);

    // Scroll should have changed
    expect(newScrollY).toBeGreaterThan(initialScrollY);
  });

  test('body should not have overflow:hidden', async ({ page }) => {
    const bodyOverflow = await page.evaluate(() => {
      const style = getComputedStyle(document.body);
      return {
        overflowX: style.overflowX,
        overflowY: style.overflowY,
        overflow: style.overflow,
        position: style.position,
        height: style.height,
      };
    });

    console.log('Body styles:', bodyOverflow);

    // Body should not have overflow hidden (blocks scroll)
    expect(bodyOverflow.overflowY).not.toBe('hidden');
    expect(bodyOverflow.overflow).not.toBe('hidden');
  });

  test('html should not have overflow:hidden', async ({ page }) => {
    const htmlOverflow = await page.evaluate(() => {
      const style = getComputedStyle(document.documentElement);
      return {
        overflowX: style.overflowX,
        overflowY: style.overflowY,
        overflow: style.overflow,
        position: style.position,
        height: style.height,
      };
    });

    console.log('HTML styles:', htmlOverflow);

    // HTML should not have overflow hidden
    expect(htmlOverflow.overflowY).not.toBe('hidden');
    expect(htmlOverflow.overflow).not.toBe('hidden');
  });

  test('body should not have scroll-blocking classes', async ({ page }) => {
    const bodyClasses = await page.evaluate(() => {
      return Array.from(document.body.classList);
    });

    console.log('Body classes:', bodyClasses);

    // These classes typically block scroll
    const blockingClasses = [
      'js-overflow-hidden',
      'mobile-menu-open',
      'modal-open',
      'no-scroll',
      'overflow-hidden',
    ];

    for (const cls of blockingClasses) {
      expect(bodyClasses).not.toContain(cls);
    }
  });

  test('touch scroll should work', async ({ page }) => {
    // Get initial scroll position
    const initialScrollY = await page.evaluate(() => window.scrollY);

    // Simulate touch scroll (swipe up)
    await page.touchscreen.tap(200, 400);
    await page.mouse.move(200, 400);

    // Perform touch scroll gesture
    await page.evaluate(async () => {
      const target = document.elementFromPoint(200, 400) || document.body;

      // Create touch start
      const touchStart = new TouchEvent('touchstart', {
        bubbles: true,
        cancelable: true,
        touches: [new Touch({
          identifier: 0,
          target: target,
          clientX: 200,
          clientY: 400,
        })],
      });
      target.dispatchEvent(touchStart);

      // Wait a bit
      await new Promise(r => setTimeout(r, 50));

      // Create touch move (swipe up)
      const touchMove = new TouchEvent('touchmove', {
        bubbles: true,
        cancelable: true,
        touches: [new Touch({
          identifier: 0,
          target: target,
          clientX: 200,
          clientY: 200, // Moved up by 200px
        })],
      });
      target.dispatchEvent(touchMove);

      // Wait a bit
      await new Promise(r => setTimeout(r, 50));

      // Create touch end
      const touchEnd = new TouchEvent('touchend', {
        bubbles: true,
        cancelable: true,
        touches: [],
      });
      target.dispatchEvent(touchEnd);
    });

    await page.waitForTimeout(200);

    // Check if scroll happened (may not work in emulation, but worth checking)
    const newScrollY = await page.evaluate(() => window.scrollY);
    console.log(`Touch scroll: ${initialScrollY} -> ${newScrollY}`);
  });

  test('no horizontal overflow', async ({ page }) => {
    const overflow = await page.evaluate(() => {
      const html = document.documentElement;
      const body = document.body;

      return {
        htmlScrollWidth: html.scrollWidth,
        htmlClientWidth: html.clientWidth,
        htmlOverflow: html.scrollWidth - html.clientWidth,
        bodyScrollWidth: body.scrollWidth,
        bodyClientWidth: body.clientWidth,
        bodyOverflow: body.scrollWidth - body.clientWidth,
      };
    });

    console.log('Horizontal overflow check:', overflow);

    // Should have no horizontal overflow (or minimal due to scrollbar)
    expect(overflow.htmlOverflow).toBeLessThanOrEqual(20);
    expect(overflow.bodyOverflow).toBeLessThanOrEqual(20);
  });

  test('listing cards should be within viewport width', async ({ page }) => {
    const viewportWidth = await page.evaluate(() => window.innerWidth);

    const cards = page.locator('.glass-listing-card');
    const cardCount = await cards.count();

    if (cardCount > 0) {
      for (let i = 0; i < Math.min(cardCount, 5); i++) {
        const card = cards.nth(i);
        const box = await card.boundingBox();

        if (box) {
          console.log(`Card ${i}: left=${box.x}, right=${box.x + box.width}, viewport=${viewportWidth}`);

          // Card should not extend past viewport
          expect(box.x).toBeGreaterThanOrEqual(0);
          expect(box.x + box.width).toBeLessThanOrEqual(viewportWidth + 5); // 5px tolerance
        }
      }
    }
  });

  test('PTR should be disabled on listings', async ({ page }) => {
    // Check if PTR is disabled on this route
    const ptrEnabled = await page.evaluate(() => {
      // @ts-ignore
      return window.PullToRefresh?.isRefreshing !== undefined;
    });

    // Check console for PTR disabled message
    const consoleMessages: string[] = [];
    page.on('console', msg => {
      if (msg.text().includes('PTR')) {
        consoleMessages.push(msg.text());
      }
    });

    // Reload to trigger PTR init
    await page.reload();
    await page.waitForLoadState('networkidle');

    console.log('PTR console messages:', consoleMessages);

    // PTR should log that it's disabled on this page
    const hasDisabledMessage = consoleMessages.some(msg =>
      msg.includes('Disabled') || msg.includes('disabled')
    );

    // Either PTR is not loaded or it logged that it's disabled
    expect(ptrEnabled === false || hasDisabledMessage || consoleMessages.length === 0).toBeTruthy();
  });

  test('scroll container detection', async ({ page }) => {
    // Find which element is actually scrollable
    const scrollInfo = await page.evaluate(() => {
      const candidates = [
        { name: 'window', el: null },
        { name: 'html', el: document.documentElement },
        { name: 'body', el: document.body },
        { name: 'main', el: document.querySelector('main') },
        { name: '#main-content', el: document.querySelector('#main-content') },
        { name: '.htb-container-full', el: document.querySelector('.htb-container-full') },
        { name: '#listings-index-glass-wrapper', el: document.querySelector('#listings-index-glass-wrapper') },
      ];

      const results: Record<string, any> = {};

      for (const { name, el } of candidates) {
        if (name === 'window') {
          results[name] = {
            scrollY: window.scrollY,
            scrollHeight: document.documentElement.scrollHeight,
            innerHeight: window.innerHeight,
            canScroll: document.documentElement.scrollHeight > window.innerHeight,
          };
        } else if (el) {
          const style = getComputedStyle(el);
          results[name] = {
            scrollTop: el.scrollTop,
            scrollHeight: el.scrollHeight,
            clientHeight: el.clientHeight,
            overflowY: style.overflowY,
            position: style.position,
            height: style.height,
            canScroll: el.scrollHeight > el.clientHeight &&
                       ['auto', 'scroll', 'overlay'].includes(style.overflowY),
          };
        }
      }

      return results;
    });

    console.log('Scroll container analysis:');
    for (const [name, info] of Object.entries(scrollInfo)) {
      console.log(`  ${name}:`, info);
    }

    // Window should be the scroll container (not an inner div)
    expect(scrollInfo['window'].canScroll).toBeTruthy();
  });

  test('diagnose all scroll-blocking styles', async ({ page }) => {
    const diagnosis = await page.evaluate(() => {
      const issues: string[] = [];

      // Check html
      const htmlStyle = getComputedStyle(document.documentElement);
      if (htmlStyle.overflow === 'hidden' || htmlStyle.overflowY === 'hidden') {
        issues.push(`html has overflow:hidden (${htmlStyle.overflow}/${htmlStyle.overflowY})`);
      }
      if (htmlStyle.position === 'fixed') {
        issues.push(`html has position:fixed`);
      }
      if (htmlStyle.height === '100%' && htmlStyle.overflow !== 'visible') {
        issues.push(`html has height:100% with non-visible overflow`);
      }

      // Check body
      const bodyStyle = getComputedStyle(document.body);
      if (bodyStyle.overflow === 'hidden' || bodyStyle.overflowY === 'hidden') {
        issues.push(`body has overflow:hidden (${bodyStyle.overflow}/${bodyStyle.overflowY})`);
      }
      if (bodyStyle.position === 'fixed') {
        issues.push(`body has position:fixed`);
      }
      if (bodyStyle.height === '100%' && bodyStyle.overflow !== 'visible') {
        issues.push(`body has height:100% with non-visible overflow`);
      }

      // Check for blocking classes
      const blockingClasses = ['js-overflow-hidden', 'mobile-menu-open', 'modal-open', 'no-scroll'];
      for (const cls of blockingClasses) {
        if (document.body.classList.contains(cls)) {
          issues.push(`body has class: ${cls}`);
        }
        if (document.documentElement.classList.contains(cls)) {
          issues.push(`html has class: ${cls}`);
        }
      }

      // Check wrapper elements
      const wrappers = [
        '#listings-index-glass-wrapper',
        '.htb-container-full',
        '#main-content',
        'main',
      ];

      for (const selector of wrappers) {
        const el = document.querySelector(selector);
        if (el) {
          const style = getComputedStyle(el);
          if (style.overflow === 'hidden' && el.scrollHeight > el.clientHeight) {
            issues.push(`${selector} has overflow:hidden but content overflows`);
          }
          if (style.height === '100vh' || style.maxHeight === '100vh') {
            if (style.overflowY === 'hidden') {
              issues.push(`${selector} has height:100vh with overflow:hidden`);
            }
          }
        }
      }

      return {
        issues,
        hasIssues: issues.length > 0,
      };
    });

    console.log('Scroll-blocking diagnosis:');
    if (diagnosis.issues.length > 0) {
      for (const issue of diagnosis.issues) {
        console.log(`  ❌ ${issue}`);
      }
    } else {
      console.log('  ✅ No obvious scroll-blocking styles found');
    }

    // This test reports issues but doesn't fail - it's diagnostic
    expect(diagnosis).toBeDefined();
  });
});
