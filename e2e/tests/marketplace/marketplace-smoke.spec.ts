// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { expect, test } from '@playwright/test';
import {
  dismissBlockingModals,
  pinSpaApiToCandidate,
  tenantUrl,
} from '../../helpers/test-utils';

test.describe('Marketplace end-to-end @marketplace @smoke', () => {
  test.beforeEach(async ({ page }) => {
    await pinSpaApiToCandidate(page);
    await page.addInitScript(() => {
      localStorage.setItem('dev_notice_dismissed', '2.1');
      localStorage.setItem('nexus_cookie_consent', JSON.stringify({
        essential: true,
        analytics: false,
        preferences: true,
        timestamp: new Date().toISOString(),
      }));
    });

    await page.route('**/api/v2/tenant/bootstrap**', async (route) => {
      const response = await route.fetch();
      const payload = await response.json();
      payload.data.features = { ...payload.data.features, marketplace: true };
      await route.fulfill({ response, json: payload });
    });

    const listing = {
      id: 55,
      title: 'Marketplace browser test chair',
      description: 'A real browser-flow fixture for the marketplace detail page.',
      tagline: 'Comfortable and ready for collection',
      price: 50,
      price_currency: 'EUR',
      currency: 'EUR',
      price_type: 'fixed',
      time_credit_price: null,
      condition: 'good',
      quantity: 1,
      category: { id: 5, name: 'Furniture', slug: 'furniture', icon: null },
      location: 'Cork',
      latitude: 51.9,
      longitude: -8.48,
      shipping_available: false,
      local_pickup: true,
      delivery_method: 'pickup',
      seller_type: 'individual',
      images: [],
      image: null,
      video_url: null,
      user: { id: 20, name: 'Browser Test Seller', avatar_url: null, is_verified: true },
      seller: { id: 20, name: 'Browser Test Seller' },
      template_data: null,
      views_count: 12,
      saves_count: 3,
      is_saved: false,
      is_own: false,
      is_promoted: false,
      status: 'active',
      created_at: '2026-07-01T00:00:00Z',
      updated_at: '2026-07-01T00:00:00Z',
    };
    const hybridListing = {
      ...listing,
      id: 56,
      title: 'Marketplace hybrid browser test chair',
      price: 50,
      time_credit_price: 4,
    };

    await page.route('**/api/v2/marketplace/**', async (route) => {
      const url = new URL(route.request().url());
      const path = url.pathname;
      const body = (data: unknown, meta?: Record<string, unknown>) => JSON.stringify({
        success: true,
        data,
        ...(meta ? { meta } : {}),
      });

      if (path.endsWith('/marketplace/categories')) {
        await route.fulfill({ status: 200, contentType: 'application/json', body: body([
          { id: 5, name: 'Furniture', slug: 'furniture', icon: null, listing_count: 1 },
        ]) });
        return;
      }
      if (path.endsWith('/marketplace/listings/featured')) {
        await route.fulfill({ status: 200, contentType: 'application/json', body: body([]) });
        return;
      }
      if (path.endsWith('/marketplace/listings/55')) {
        await route.fulfill({ status: 200, contentType: 'application/json', body: body(listing) });
        return;
      }
      if (path.endsWith('/marketplace/listings/56')) {
        await route.fulfill({ status: 200, contentType: 'application/json', body: body(hybridListing) });
        return;
      }
      if (path.endsWith('/marketplace/sellers/20/listings')
        || path.endsWith('/marketplace/sellers/20/shipping-options')
        || path.endsWith('/marketplace/listings/55/pickup-slots')
        || path.endsWith('/marketplace/listings/56/pickup-slots')) {
        await route.fulfill({ status: 200, contentType: 'application/json', body: body([]) });
        return;
      }
      if (path.endsWith('/marketplace/listings')) {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: body([listing], { has_more: false, cursor: null }),
        });
        return;
      }
      if (path.endsWith('/marketplace/orders') && route.request().method() === 'POST') {
        await route.fulfill({
          status: 201,
          contentType: 'application/json',
          body: body({
            id: 901,
            order_number: 'MKT-BROWSER-901',
            status: 'paid',
            requires_payment: false,
          }),
        });
        return;
      }
      if (path.endsWith('/marketplace/payments/create-intent')) {
        await route.fulfill({
          status: 500,
          contentType: 'application/json',
          body: JSON.stringify({ success: false, error: 'Card payment must not start for time credits.' }),
        });
        return;
      }

      await route.continue();
    });
  });

  test('loads browse data and opens a public listing when one is available', async ({ page }) => {
    const pageErrors: string[] = [];
    page.on('pageerror', (error) => pageErrors.push(error.message));

    const browseResponse = page.waitForResponse(
      (response) => new URL(response.url()).pathname.endsWith('/api/v2/marketplace/listings')
        && response.request().method() === 'GET',
    );

    await page.goto(tenantUrl('marketplace'), { waitUntil: 'domcontentloaded' });
    await dismissBlockingModals(page);

    const response = await browseResponse;
    expect(response.ok()).toBeTruthy();
    await expect(page.getByRole('heading', { name: /marketplace/i }).first()).toBeVisible();
    await expect(page.getByText('Marketplace browser test chair').first()).toBeVisible();

    const listingHref = await page.locator('a[href*="/marketplace/"]').evaluateAll((anchors) => (
      anchors
        .map((anchor) => anchor.getAttribute('href'))
        .find((href) => href !== null && /\/marketplace\/\d+(?:[/?#]|$)/.test(href))
    ));
    if (listingHref) {
      const detailResponse = page.waitForResponse(
        (detail) => new URL(detail.url()).pathname.endsWith('/api/v2/marketplace/listings/55'),
      );
      await page.locator(`a[href="${listingHref}"]`).first().click();
      await expect(page).toHaveURL(/\/marketplace\/\d+/);
      expect((await detailResponse).ok()).toBeTruthy();
      await expect(page.getByRole('heading', { level: 1 }).first()).toBeVisible();
    }

    expect(pageErrors).toEqual([]);
  });

  test('submits a hybrid listing as a time-credit order without starting Stripe', async ({ page }) => {
    const paymentIntentRequests: string[] = [];
    page.on('request', (request) => {
      if (new URL(request.url()).pathname.endsWith('/api/v2/marketplace/payments/create-intent')) {
        paymentIntentRequests.push(request.url());
      }
    });

    await page.goto(tenantUrl('marketplace/56'), { waitUntil: 'domcontentloaded' });
    await dismissBlockingModals(page);

    await expect(page.getByRole('heading', { level: 1, name: 'Marketplace hybrid browser test chair' }))
      .toBeVisible({ timeout: 15_000 });
    await page.getByRole('button', { name: /pay with 4 time credits/i }).click();

    const orderRequestPromise = page.waitForRequest((request) => (
      request.method() === 'POST'
      && new URL(request.url()).pathname.endsWith('/api/v2/marketplace/orders')
    ));
    await page.getByRole('button', { name: /buy now for 4 tc/i }).click();

    const orderRequest = await orderRequestPromise;
    expect(orderRequest.postDataJSON()).toMatchObject({
      listing_id: 56,
      payment_method: 'time_credits',
    });
    expect(orderRequest.postDataJSON().shipping_option_id).toBeUndefined();
    expect(orderRequest.postDataJSON().idempotency_key).toEqual(expect.any(String));
    await expect(page.getByText('Order created!')).toBeVisible();
    expect(paymentIntentRequests).toEqual([]);
  });
});
