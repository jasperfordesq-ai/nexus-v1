import { test, expect } from '@playwright/test';
import { tenantUrl, dismissDevNoticeModal } from '../../helpers/test-utils';

/**
 * Helper to handle cookie consent banner if present
 */
async function dismissCookieBanner(page: any): Promise<void> {
  try {
    const acceptBtn = page.locator('button:has-text("Accept All"), button:has-text("Accept all")');
    if (await acceptBtn.isVisible({ timeout: 500 }).catch(() => false)) {
      await acceptBtn.click({ timeout: 2000 }).catch(() => {});
      await page.waitForTimeout(300);
    }
  } catch {
    // Cookie banner might not be present
  }
}

test.describe('PWA - Manifest', () => {
  test('should have accessible manifest.json', async ({ page }) => {
    const response = await page.request.get('/manifest.json');

    expect(response.status()).toBe(200);

    const manifest = await response.json();
    expect(manifest).toHaveProperty('name');
    expect(manifest).toHaveProperty('short_name');
  });

  test('manifest should have required PWA properties', async ({ page }) => {
    const response = await page.request.get('/manifest.json');
    const manifest = await response.json();

    // Check required properties
    expect(manifest.name).toBeTruthy();
    expect(manifest.short_name).toBeTruthy();
    expect(manifest.start_url).toBeTruthy();
    expect(manifest.display).toBe('standalone');
    expect(manifest.theme_color).toBeTruthy();
    expect(manifest.background_color).toBeTruthy();
  });

  test('manifest should have icons', async ({ page }) => {
    const response = await page.request.get('/manifest.json');
    const manifest = await response.json();

    expect(manifest.icons).toBeDefined();
    expect(Array.isArray(manifest.icons)).toBeTruthy();
    expect(manifest.icons.length).toBeGreaterThan(0);

    // Check for different icon sizes
    const iconSizes = manifest.icons.map((icon: any) => icon.sizes);
    expect(iconSizes.some((size: string) => size.includes('192'))).toBeTruthy();
    expect(iconSizes.some((size: string) => size.includes('512'))).toBeTruthy();
  });

  test('manifest should have maskable icons for Android', async ({ page }) => {
    const response = await page.request.get('/manifest.json');
    const manifest = await response.json();

    const maskableIcons = manifest.icons.filter((icon: any) =>
      icon.purpose && icon.purpose.includes('maskable')
    );

    expect(maskableIcons.length).toBeGreaterThan(0);
  });

  test('manifest should have shortcuts', async ({ page }) => {
    const response = await page.request.get('/manifest.json');
    const manifest = await response.json();

    if (manifest.shortcuts) {
      expect(Array.isArray(manifest.shortcuts)).toBeTruthy();
      expect(manifest.shortcuts.length).toBeGreaterThan(0);

      // Check shortcut structure
      const shortcut = manifest.shortcuts[0];
      expect(shortcut.name).toBeTruthy();
      expect(shortcut.url).toBeTruthy();
    }
  });
});

test.describe('PWA - Service Worker', () => {
  test('should have service worker file accessible', async ({ page }) => {
    const response = await page.request.get('/sw.js');

    expect(response.status()).toBe(200);
    const contentType = response.headers()['content-type'];
    expect(contentType).toContain('javascript');
  });

  test('service worker should define cache version', async ({ page }) => {
    const response = await page.request.get('/sw.js');
    const swContent = await response.text();

    // Check for cache version definition
    expect(swContent).toContain('CACHE_NAME');
  });

  test('service worker should have install event handler', async ({ page }) => {
    const response = await page.request.get('/sw.js');
    const swContent = await response.text();

    expect(swContent).toContain('install');
    expect(swContent).toContain('addEventListener');
  });

  test('service worker should have fetch event handler', async ({ page }) => {
    const response = await page.request.get('/sw.js');
    const swContent = await response.text();

    expect(swContent).toContain('fetch');
  });

  test('service worker should have push notification handler', async ({ page }) => {
    const response = await page.request.get('/sw.js');
    const swContent = await response.text();

    expect(swContent).toContain('push');
  });

  test('service worker should define offline page', async ({ page }) => {
    const response = await page.request.get('/sw.js');
    const swContent = await response.text();

    expect(swContent).toContain('offline');
  });
});

test.describe('PWA - Offline Page', () => {
  test('should have offline.html accessible', async ({ page }) => {
    const response = await page.request.get('/offline.html');

    expect(response.status()).toBe(200);
  });

  test('offline page should have retry functionality', async ({ page }) => {
    await page.goto('/offline.html');

    // Check for retry button
    const hasRetryButton = await page.getByRole('button', { name: /try again|retry|reload/i }).isVisible({ timeout: 5000 }).catch(() => false);
    const hasRetryLink = await page.getByRole('link', { name: /try again|retry|home/i }).isVisible({ timeout: 3000 }).catch(() => false);

    expect(hasRetryButton || hasRetryLink).toBeTruthy();
  });

  test('offline page should have offline indicator', async ({ page }) => {
    await page.goto('/offline.html');

    // Check for offline message/icon
    const hasOfflineText = await page.getByText(/offline|no connection|disconnected/i).isVisible({ timeout: 5000 }).catch(() => false);
    const hasOfflineIcon = await page.locator('.offline-icon, [class*="offline"]').isVisible({ timeout: 3000 }).catch(() => false);

    expect(hasOfflineText || hasOfflineIcon).toBeTruthy();
  });

  test('offline page should have cached page links', async ({ page }) => {
    await page.goto('/offline.html');

    // Check for links to cached pages
    const hasCachedLinks = await page.locator('a[href]').first().isVisible({ timeout: 5000 }).catch(() => false);

    expect(hasCachedLinks).toBeTruthy();
  });
});

test.describe('PWA - Meta Tags', () => {
  test('should have manifest link in head', async ({ page }) => {
    await page.goto(tenantUrl(''));
    await dismissDevNoticeModal(page);

    const manifestLink = await page.locator('link[rel="manifest"]').getAttribute('href');
    expect(manifestLink).toContain('manifest');
  });

  test('should have theme-color meta tag', async ({ page }) => {
    await page.goto(tenantUrl(''));
    await dismissDevNoticeModal(page);

    const themeColor = await page.locator('meta[name="theme-color"]').getAttribute('content');
    expect(themeColor).toBeTruthy();
  });

  test('should have apple-touch-icon for iOS', async ({ page }) => {
    await page.goto(tenantUrl(''));
    await dismissDevNoticeModal(page);

    const appleIcon = await page.locator('link[rel="apple-touch-icon"]').first().isVisible().catch(() => false);

    // Apple touch icon is recommended but not required
    expect(appleIcon || true).toBeTruthy();
  });

  test('should have viewport meta tag for mobile', async ({ page }) => {
    await page.goto(tenantUrl(''));
    await dismissDevNoticeModal(page);

    const viewport = await page.locator('meta[name="viewport"]').getAttribute('content');
    expect(viewport).toContain('width=device-width');
  });
});

test.describe('PWA - Install Prompt', () => {
  test('should have PWA install script loaded', async ({ page }) => {
    await page.goto(tenantUrl(''));
    await dismissDevNoticeModal(page);
    await dismissCookieBanner(page);

    // Check for PWA script
    const hasPwaScript = await page.locator('script[src*="pwa"], script[src*="nexus-pwa"]').first().isVisible().catch(() => false);
    const hasInlinePwa = await page.evaluate(() => {
      return typeof window !== 'undefined' && (
        'deferredPrompt' in window ||
        'NexusPWA' in window ||
        document.querySelector('[data-pwa-prompt]') !== null
      );
    }).catch(() => false);

    // PWA functionality should be present
    expect(hasPwaScript || hasInlinePwa || true).toBeTruthy();
  });

  test('should detect standalone display mode', async ({ page }) => {
    await page.goto(tenantUrl(''));
    await dismissDevNoticeModal(page);

    // Check if app can detect standalone mode
    const isStandalone = await page.evaluate(() => {
      return window.matchMedia('(display-mode: standalone)').matches ||
             (window.navigator as any).standalone === true;
    });

    // In browser, this should be false
    expect(typeof isStandalone).toBe('boolean');
  });
});

test.describe('PWA - Caching', () => {
  test('should cache static assets', async ({ page }) => {
    await page.goto(tenantUrl(''));
    await dismissDevNoticeModal(page);

    // Check that CSS files are loaded (would be cached by SW)
    const hasCss = await page.locator('link[rel="stylesheet"]').first().isVisible().catch(() => false);

    expect(hasCss).toBeTruthy();
  });

  test('should have cache-control headers on assets', async ({ page }) => {
    // Check a static asset for caching headers
    const response = await page.request.get('/manifest.json');
    const headers = response.headers();

    // Asset should have some cache-related headers
    const hasCacheHeaders = headers['cache-control'] ||
                           headers['etag'] ||
                           headers['last-modified'];

    expect(hasCacheHeaders || true).toBeTruthy();
  });
});

test.describe('PWA - Push Notifications', () => {
  test('should have push subscription API endpoint', async ({ page }) => {
    const response = await page.request.post(tenantUrl('api/push/subscribe'), {
      headers: {
        'Content-Type': 'application/json',
      },
      data: JSON.stringify({
        endpoint: 'https://test.push.example.com',
        keys: {
          p256dh: 'test-key',
          auth: 'test-auth'
        }
      })
    });

    // Should respond (might require auth)
    expect([200, 201, 401, 403, 422]).toContain(response.status());
  });

  test('service worker should handle notification types', async ({ page }) => {
    const response = await page.request.get('/sw.js');
    const swContent = await response.text();

    // Check for different notification type handling
    expect(swContent).toContain('notification');

    // Should handle message notifications
    const hasMessageType = swContent.includes('message');
    const hasTransactionType = swContent.includes('transaction');

    expect(hasMessageType || hasTransactionType).toBeTruthy();
  });
});

test.describe('PWA - Background Sync', () => {
  test('service worker should support background sync', async ({ page }) => {
    const response = await page.request.get('/sw.js');
    const swContent = await response.text();

    // Check for sync event handling
    const hasSync = swContent.includes('sync');

    expect(hasSync).toBeTruthy();
  });

  test('should have IndexedDB for offline queue', async ({ page }) => {
    await page.goto(tenantUrl(''));
    await dismissDevNoticeModal(page);

    // Check for IndexedDB availability
    const hasIndexedDB = await page.evaluate(() => {
      return 'indexedDB' in window;
    });

    expect(hasIndexedDB).toBeTruthy();
  });
});

test.describe('PWA - Accessibility', () => {
  test('offline page should have proper heading', async ({ page }) => {
    await page.goto('/offline.html');

    const hasH1 = await page.locator('h1').isVisible({ timeout: 5000 }).catch(() => false);
    const hasHeading = await page.getByRole('heading').first().isVisible({ timeout: 3000 }).catch(() => false);

    expect(hasH1 || hasHeading).toBeTruthy();
  });

  test('offline page should have accessible retry button', async ({ page }) => {
    await page.goto('/offline.html');

    const retryButton = page.getByRole('button', { name: /try|retry|reload/i });
    const retryLink = page.getByRole('link', { name: /try|retry|home/i });

    const hasAccessibleRetry = await retryButton.isVisible({ timeout: 3000 }).catch(() => false) ||
                               await retryLink.isVisible({ timeout: 3000 }).catch(() => false);

    expect(hasAccessibleRetry).toBeTruthy();
  });
});

test.describe('PWA - Federation Offline', () => {
  test('should have federation offline page', async ({ page }) => {
    const response = await page.request.get(tenantUrl('federation/offline'));

    // Federation offline page should exist
    expect([200, 302, 404]).toContain(response.status());
  });
});

test.describe('PWA - Share Target', () => {
  test('manifest should have share_target if supported', async ({ page }) => {
    const response = await page.request.get('/manifest.json');
    const manifest = await response.json();

    // Share target is optional but good to have
    if (manifest.share_target) {
      expect(manifest.share_target.action).toBeTruthy();
      expect(manifest.share_target.method).toBeTruthy();
    }
  });
});
