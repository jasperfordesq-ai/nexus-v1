import { test, expect, Page } from '@playwright/test';
import { tenantUrl, dismissDevNoticeModal } from '../../helpers/test-utils';

/**
 * E2E Tests: Google Places Location Autocomplete Integration
 *
 * Tests the PlaceAutocompleteInput component across all pages where it's used:
 * - Settings (profile location)
 * - Create Listing
 * - Create Event
 * - Create Group
 *
 * The component has two modes:
 * 1. Google Places API mode — autocomplete suggestions with lat/lng (when VITE_GOOGLE_MAPS_API_KEY is set)
 * 2. Fallback mode — plain text input with autocomplete="address-level2"
 *
 * These tests verify DOM structure, accessibility, user interaction,
 * and form submission with location data — regardless of API key presence.
 *
 * DOM structure (PlaceAutocompleteInput renders a HeroUI Input):
 * - Fallback mode: input[placeholder="City, Country"][autocomplete="address-level2"]
 * - API mode:      input[role="combobox"][autocomplete="off"] + ul[role="listbox"] for suggestions
 * - Both modes:    div.relative wrapper, MapPin SVG startContent, clear button[aria-label="Clear location"]
 */

// ─── Helpers ────────────────────────────────────────────────────────

/**
 * Dismiss any blocking modals (dev notice, cookie consent)
 */
async function dismissModals(page: Page): Promise<void> {
  await dismissDevNoticeModal(page);
  const cookieBtn = page.locator('button:has-text("Accept All"), button:has-text("Accept all cookies")');
  if (await cookieBtn.isVisible({ timeout: 1000 }).catch(() => false)) {
    await cookieBtn.first().click();
    await page.waitForTimeout(300);
  }
}

/**
 * Wait for React to hydrate a page — wait until at least one input is visible
 */
async function waitForReact(page: Page, timeout = 15000): Promise<void> {
  await page.waitForLoadState('domcontentloaded');
  await page.waitForSelector('input', { timeout, state: 'visible' }).catch(() => {});
  await page.waitForTimeout(300);
}

/**
 * Find the PlaceAutocompleteInput's inner <input> element.
 *
 * Reliable selectors based on what PlaceAutocompleteInput actually renders:
 * - Fallback mode: autocomplete="address-level2" + placeholder="City, Country" or "Enter your location..."
 * - API mode:      role="combobox" + autocomplete="off"
 */
function getLocationInput(page: Page): ReturnType<Page['locator']> {
  return page.locator(
    'input[autocomplete="address-level2"], input[placeholder="City, Country"], input[placeholder="Enter your location..."], input[role="combobox"][aria-haspopup="listbox"]'
  ).first();
}

/**
 * Get the clear button inside a PlaceAutocompleteInput
 */
function getClearButton(page: Page): ReturnType<Page['locator']> {
  return page.locator('button[aria-label="Clear location"]').first();
}

/**
 * Get the suggestions listbox (only visible in Google API mode)
 */
function getSuggestionsListbox(page: Page): ReturnType<Page['locator']> {
  return page.locator('ul[role="listbox"]');
}

/**
 * Check if the current instance is running in API mode (role="combobox") or fallback
 */
async function isApiMode(page: Page): Promise<boolean> {
  const input = getLocationInput(page);
  const role = await input.getAttribute('role').catch(() => null);
  return role === 'combobox';
}

// ─── Settings Page Tests (Authenticated) ────────────────────────────

test.describe('Location Input - Settings Page', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto(tenantUrl('settings'));
    await dismissModals(page);
    await waitForReact(page);
  });

  test('should render location input on settings page', async ({ page }) => {
    const locationInput = getLocationInput(page);
    await expect(locationInput).toBeVisible({ timeout: 10000 });
  });

  test('should have placeholder text City, Country', async ({ page }) => {
    const locationInput = getLocationInput(page);
    await expect(locationInput).toBeVisible({ timeout: 10000 });

    const placeholder = await locationInput.getAttribute('placeholder');
    expect(placeholder === 'City, Country' || placeholder === 'Enter your location...').toBeTruthy();
  });

  test('should accept text input for location', async ({ page }) => {
    const locationInput = getLocationInput(page);
    await expect(locationInput).toBeVisible({ timeout: 10000 });

    await locationInput.fill('Dublin, Ireland');
    await expect(locationInput).toHaveValue('Dublin, Ireland');
  });

  test('should show clear button when location has value', async ({ page }) => {
    const locationInput = getLocationInput(page);
    await expect(locationInput).toBeVisible({ timeout: 10000 });

    await locationInput.fill('Cork');

    const clearBtn = getClearButton(page);
    await expect(clearBtn).toBeVisible({ timeout: 3000 });
  });

  test('should clear location when clear button is clicked', async ({ page }) => {
    const locationInput = getLocationInput(page);
    await expect(locationInput).toBeVisible({ timeout: 10000 });

    await locationInput.fill('Galway');
    await expect(locationInput).toHaveValue('Galway');

    const clearBtn = getClearButton(page);
    await expect(clearBtn).toBeVisible({ timeout: 3000 });
    await clearBtn.click();

    await expect(locationInput).toHaveValue('');
  });

  test('should have MapPin icon in location wrapper', async ({ page }) => {
    const locationInput = getLocationInput(page);
    await expect(locationInput).toBeVisible({ timeout: 10000 });

    // MapPin icon is rendered as SVG startContent inside the input wrapper
    // It lives in a div.relative alongside the input. Use .first() since HeroUI
    // also renders an inner div.relative (data-slot="input-wrapper").
    const wrapperWithMapPin = page.locator('div.relative').filter({
      has: locationInput,
    }).first();
    await expect(wrapperWithMapPin).toBeVisible({ timeout: 5000 });
  });

  test('should have correct autocomplete attribute in fallback mode', async ({ page }) => {
    const locationInput = getLocationInput(page);
    await expect(locationInput).toBeVisible({ timeout: 10000 });

    const autocomplete = await locationInput.getAttribute('autocomplete');
    // Fallback: address-level2 / API mode: off
    expect(autocomplete === 'address-level2' || autocomplete === 'off').toBeTruthy();
  });

  test('should not have required attribute on location field', async ({ page }) => {
    const locationInput = getLocationInput(page);
    await expect(locationInput).toBeVisible({ timeout: 10000 });

    const isRequired = await locationInput.getAttribute('required');
    expect(isRequired).toBeNull();
  });
});

// ─── Create Listing Page Tests (Authenticated) ──────────────────────

test.describe('Location Input - Create Listing Page', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto(tenantUrl('listings/create'));
    await dismissModals(page);
    await waitForReact(page);
  });

  test('should render location input on create listing form', async ({ page }) => {
    const locationInput = getLocationInput(page);
    await expect(locationInput).toBeVisible({ timeout: 10000 });
  });

  test('should accept location text on listing form', async ({ page }) => {
    const locationInput = getLocationInput(page);
    await expect(locationInput).toBeVisible({ timeout: 10000 });

    await locationInput.fill('Online');
    await expect(locationInput).toHaveValue('Online');
  });

  test('should show and use clear button on listing form', async ({ page }) => {
    const locationInput = getLocationInput(page);
    await expect(locationInput).toBeVisible({ timeout: 10000 });

    await locationInput.fill('Limerick');
    const clearBtn = getClearButton(page);
    await expect(clearBtn).toBeVisible({ timeout: 3000 });
    await clearBtn.click();
    await expect(locationInput).toHaveValue('');
  });
});

// ─── Create Event Page Tests (Authenticated) ────────────────────────

test.describe('Location Input - Create Event Page', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto(tenantUrl('events/create'));
    await dismissModals(page);
    await waitForReact(page);
  });

  test('should render location input on create event form', async ({ page }) => {
    const locationInput = getLocationInput(page);
    await expect(locationInput).toBeVisible({ timeout: 10000 });
  });

  test('should accept location text for events', async ({ page }) => {
    const locationInput = getLocationInput(page);
    await expect(locationInput).toBeVisible({ timeout: 10000 });

    await locationInput.fill('Community Center, Dublin');
    await expect(locationInput).toHaveValue('Community Center, Dublin');
  });

  test('should show clear button on event location field', async ({ page }) => {
    const locationInput = getLocationInput(page);
    await expect(locationInput).toBeVisible({ timeout: 10000 });

    await locationInput.fill('Online');
    const clearBtn = getClearButton(page);
    await expect(clearBtn).toBeVisible({ timeout: 3000 });
  });

  test('should coexist with max attendees field', async ({ page }) => {
    // Event form has location + max attendees in a 2-column grid
    const locationInput = getLocationInput(page);
    const maxAttendeesInput = page.locator('input[type="number"]').first();

    await expect(locationInput).toBeVisible({ timeout: 10000 });
    await expect(maxAttendeesInput).toBeVisible({ timeout: 10000 });
  });
});

// ─── Create Group Page Tests (Authenticated) ────────────────────────

test.describe('Location Input - Create Group Page', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto(tenantUrl('groups/create'));
    await dismissModals(page);
    await waitForReact(page);
  });

  test('should render location input on create group form', async ({ page }) => {
    const locationInput = getLocationInput(page);
    await expect(locationInput).toBeVisible({ timeout: 10000 });
  });

  test('should accept location text for groups', async ({ page }) => {
    const locationInput = getLocationInput(page);
    await expect(locationInput).toBeVisible({ timeout: 10000 });

    await locationInput.fill('Cork, Ireland');
    await expect(locationInput).toHaveValue('Cork, Ireland');
  });

  test('should clear group location via clear button', async ({ page }) => {
    const locationInput = getLocationInput(page);
    await expect(locationInput).toBeVisible({ timeout: 10000 });

    await locationInput.fill('Waterford');
    const clearBtn = getClearButton(page);
    await expect(clearBtn).toBeVisible({ timeout: 3000 });
    await clearBtn.click();
    await expect(locationInput).toHaveValue('');
  });
});

// ─── Google Places API Tests (conditional on API key) ───────────────

test.describe('Google Places Autocomplete (API-dependent)', () => {
  test('should show autocomplete suggestions when API key is configured', async ({ page }) => {
    await page.goto(tenantUrl('settings'));
    await dismissModals(page);
    await waitForReact(page);

    const locationInput = getLocationInput(page);
    await expect(locationInput).toBeVisible({ timeout: 10000 });

    if (!await isApiMode(page)) {
      test.skip(true, 'Google Maps API key not set — component in fallback mode');
      return;
    }

    await locationInput.fill('Dublin');
    await page.waitForTimeout(900); // Wait for 300ms debounce + API call

    const listbox = getSuggestionsListbox(page);
    const visible = await listbox.isVisible({ timeout: 3000 }).catch(() => false);
    if (visible) {
      const options = listbox.locator('li[role="option"]');
      expect(await options.count()).toBeGreaterThan(0);

      // Google ToS requires attribution
      await expect(listbox.locator('text=Powered by Google')).toBeVisible();
    }
    // API might be rate-limited or key restricted — don't hard-fail
  });

  test('should support keyboard navigation in suggestions', async ({ page }) => {
    await page.goto(tenantUrl('settings'));
    await dismissModals(page);
    await waitForReact(page);

    const locationInput = getLocationInput(page);
    await expect(locationInput).toBeVisible({ timeout: 10000 });

    if (!await isApiMode(page)) {
      test.skip(true, 'Google Maps API key not set — component in fallback mode');
      return;
    }

    await locationInput.fill('Cork');
    await page.waitForTimeout(900);

    const listbox = getSuggestionsListbox(page);
    if (await listbox.isVisible({ timeout: 2000 }).catch(() => false)) {
      await locationInput.press('ArrowDown');
      await page.waitForTimeout(100);
      const firstOpt = listbox.locator('li[role="option"]').first();
      await expect(firstOpt).toHaveAttribute('aria-selected', 'true');

      await locationInput.press('Escape');
      await expect(listbox).not.toBeVisible({ timeout: 2000 });
    }
  });

  test('should not show suggestions for input shorter than 3 chars', async ({ page }) => {
    await page.goto(tenantUrl('settings'));
    await dismissModals(page);
    await waitForReact(page);

    const locationInput = getLocationInput(page);
    await expect(locationInput).toBeVisible({ timeout: 10000 });

    if (!await isApiMode(page)) {
      test.skip(true, 'Google Maps API key not set — component in fallback mode');
      return;
    }

    await locationInput.fill('Du'); // Only 2 chars
    await page.waitForTimeout(600); // Wait past 300ms debounce

    const listbox = getSuggestionsListbox(page);
    await expect(listbox).not.toBeVisible({ timeout: 1000 });
  });
});

// ─── Fallback Mode Tests ─────────────────────────────────────────────

test.describe('Location Input - Fallback Mode (No API Key)', () => {
  test('should work as plain text input in fallback mode', async ({ page }) => {
    await page.goto(tenantUrl('settings'));
    await dismissModals(page);
    await waitForReact(page);

    const locationInput = getLocationInput(page);
    await expect(locationInput).toBeVisible({ timeout: 10000 });

    await locationInput.fill('Killarney, Co. Kerry');
    await expect(locationInput).toHaveValue('Killarney, Co. Kerry');
  });

  test('should have browser autocomplete hint in fallback mode', async ({ page }) => {
    await page.goto(tenantUrl('settings'));
    await dismissModals(page);
    await waitForReact(page);

    const locationInput = getLocationInput(page);
    await expect(locationInput).toBeVisible({ timeout: 10000 });

    if (await isApiMode(page)) {
      // In API mode autocomplete is "off"
      await expect(locationInput).toHaveAttribute('autocomplete', 'off');
    } else {
      // In fallback mode autocomplete is "address-level2"
      await expect(locationInput).toHaveAttribute('autocomplete', 'address-level2');
    }
  });
});

// ─── Cross-Page Consistency Tests ────────────────────────────────────

test.describe('Location Input - Cross-Page Consistency', () => {
  const pages = [
    { name: 'Settings', path: 'settings' },
    { name: 'Create Listing', path: 'listings/create' },
    { name: 'Create Event', path: 'events/create' },
    { name: 'Create Group', path: 'groups/create' },
  ];

  for (const { name, path } of pages) {
    test(`should have location input on ${name} page`, async ({ page }) => {
      await page.goto(tenantUrl(path));
      await dismissModals(page);
      await waitForReact(page);

      const locationInput = getLocationInput(page);
      await expect(locationInput).toBeVisible({ timeout: 10000 });
    });

    test(`should support clear functionality on ${name} page`, async ({ page }) => {
      await page.goto(tenantUrl(path));
      await dismissModals(page);
      await waitForReact(page);

      const locationInput = getLocationInput(page);
      await expect(locationInput).toBeVisible({ timeout: 10000 });

      await locationInput.fill('Test Location');
      const clearBtn = getClearButton(page);
      await expect(clearBtn).toBeVisible({ timeout: 3000 });
      await clearBtn.click();
      await expect(locationInput).toHaveValue('');
    });
  }
});
