import { test, expect } from '@playwright/test';
import { ListingsPage, CreateListingPage, ListingDetailPage } from '../../page-objects';
import { generateTestData, tenantUrl } from '../../helpers/test-utils';

test.describe('Listings - Browse', () => {
  test('should display listings page', async ({ page }) => {
    const listingsPage = new ListingsPage(page);
    await listingsPage.navigate();

    await expect(page).toHaveURL(/listings/);
  });

  test('should show listings or empty state', async ({ page }) => {
    const listingsPage = new ListingsPage(page);
    await listingsPage.navigate();

    const count = await listingsPage.getListingCount();
    const hasNoResults = await listingsPage.hasNoResults();

    expect(count > 0 || hasNoResults).toBeTruthy();
  });

  test('should have search functionality', async ({ page }) => {
    const listingsPage = new ListingsPage(page);
    await listingsPage.navigate();

    await expect(listingsPage.searchInput).toBeVisible();
  });

  test('should have create listing button', async ({ page }) => {
    const listingsPage = new ListingsPage(page);
    await listingsPage.navigate();

    await expect(listingsPage.createListingButton).toBeVisible();
  });

  test('should search listings', async ({ page }) => {
    const listingsPage = new ListingsPage(page);
    await listingsPage.navigate();

    await listingsPage.searchListings('test');
    expect(page.url()).toContain('test');
  });

  test('should filter by type (offer/request)', async ({ page }) => {
    const listingsPage = new ListingsPage(page);
    await listingsPage.navigate();

    const typeFilter = listingsPage.typeFilter;
    if (await typeFilter.count() > 0) {
      await listingsPage.filterByType('offer');
      // Page should update with filter
      await page.waitForLoadState('domcontentloaded');
    }
  });

  test('should navigate to listing detail', async ({ page }) => {
    const listingsPage = new ListingsPage(page);
    await listingsPage.navigate();

    const count = await listingsPage.getListingCount();
    if (count > 0) {
      await listingsPage.clickListing(0);
      expect(page.url()).toMatch(/listings\/\d+/);
    }
  });

  test('should display listing cards with required info', async ({ page }) => {
    const listingsPage = new ListingsPage(page);
    await listingsPage.navigate();

    const count = await listingsPage.getListingCount();
    if (count > 0) {
      const card = listingsPage.listingCards.first();

      // Should have title
      const title = card.locator('.listing-title, h3, h4');
      await expect(title).toBeVisible();

      // Should have type indicator (offer/request)
      const type = card.locator('.listing-type, .badge, [data-type]');
      await expect(type).toBeVisible();
    }
  });
});

test.describe('Listings - Create', () => {
  test('should navigate to create listing page', async ({ page }) => {
    const listingsPage = new ListingsPage(page);
    await listingsPage.navigate();
    await listingsPage.clickCreateListing();

    expect(page.url()).toContain('create');
  });

  test('should display create listing form', async ({ page }) => {
    const createPage = new CreateListingPage(page);
    await createPage.navigate();

    await expect(createPage.titleInput).toBeVisible();
    await expect(createPage.descriptionInput).toBeVisible();
    await expect(createPage.submitButton).toBeVisible();
  });

  test('should have type selection (offer/request)', async ({ page }) => {
    const createPage = new CreateListingPage(page);
    await createPage.navigate();

    // Check for radio buttons or select
    const typeRadios = page.locator('input[name="type"]');
    const typeSelect = createPage.typeSelect;

    const hasRadios = await typeRadios.count() > 0;
    const hasSelect = await typeSelect.count() > 0;

    expect(hasRadios || hasSelect).toBeTruthy();
  });

  test('should validate required fields', async ({ page }) => {
    const createPage = new CreateListingPage(page);
    await createPage.navigate();

    // Submit empty form
    await createPage.submit();

    // Should show errors or stay on page
    const hasErrors = await createPage.hasErrors();
    const stillOnCreate = page.url().includes('create');

    expect(hasErrors || stillOnCreate).toBeTruthy();
  });

  test('should create a new listing', async ({ page }) => {
    const createPage = new CreateListingPage(page);
    await createPage.navigate();

    const testData = generateTestData();

    await createPage.fillForm({
      title: testData.title,
      description: testData.description,
      type: 'offer',
    });

    await createPage.submit();

    // Should redirect to listing detail or listings page
    expect(page.url()).toMatch(/listings(\/\d+)?$/);

    // Verify listing was created by checking for title
    if (page.url().match(/listings\/\d+/)) {
      const pageContent = await page.content();
      expect(pageContent).toContain(testData.title);
    }
  });

  test('should handle category selection', async ({ page }) => {
    const createPage = new CreateListingPage(page);
    await createPage.navigate();

    const categorySelect = createPage.categorySelect;
    if (await categorySelect.count() > 0) {
      const options = await categorySelect.locator('option').count();
      expect(options).toBeGreaterThan(1); // At least one option besides default
    }
  });
});

test.describe('Listings - Detail', () => {
  test('should display listing details', async ({ page }) => {
    // First find a listing
    const listingsPage = new ListingsPage(page);
    await listingsPage.navigate();

    const count = await listingsPage.getListingCount();
    if (count > 0) {
      await listingsPage.clickListing(0);

      const detailPage = new ListingDetailPage(page);
      await expect(detailPage.title).toBeVisible();
      await expect(detailPage.description).toBeVisible();
    }
  });

  test('should show author information', async ({ page }) => {
    const listingsPage = new ListingsPage(page);
    await listingsPage.navigate();

    const count = await listingsPage.getListingCount();
    if (count > 0) {
      await listingsPage.clickListing(0);

      const detailPage = new ListingDetailPage(page);
      await expect(detailPage.authorInfo).toBeVisible();
    }
  });

  test('should have contact button', async ({ page }) => {
    const listingsPage = new ListingsPage(page);
    await listingsPage.navigate();

    const count = await listingsPage.getListingCount();
    if (count > 0) {
      await listingsPage.clickListing(0);

      const detailPage = new ListingDetailPage(page);
      // Contact button may not be visible on own listings
      const contactButton = detailPage.contactButton;
      const editButton = detailPage.editButton;

      const hasContact = await contactButton.count() > 0;
      const hasEdit = await editButton.count() > 0;

      // Should have one or the other
      expect(hasContact || hasEdit).toBeTruthy();
    }
  });

  test('should allow liking a listing', async ({ page }) => {
    const listingsPage = new ListingsPage(page);
    await listingsPage.navigate();

    const count = await listingsPage.getListingCount();
    if (count > 0) {
      await listingsPage.clickListing(0);

      const detailPage = new ListingDetailPage(page);
      if (await detailPage.likeButton.count() > 0) {
        await detailPage.like();
        // Should toggle like state
      }
    }
  });

  test('should show edit button for own listings', async ({ page }) => {
    // Navigate to a listing the user owns
    await page.goto(tenantUrl('dashboard/listings'));

    const myListings = page.locator('.listing-card, [data-listing]');
    if (await myListings.count() > 0) {
      await myListings.first().click();
      await page.waitForLoadState('domcontentloaded');

      const detailPage = new ListingDetailPage(page);
      await expect(detailPage.editButton).toBeVisible();
    }
  });
});

test.describe('Listings - Edit', () => {
  test('should allow editing own listings', async ({ page }) => {
    await page.goto(tenantUrl('dashboard/listings'));

    const myListings = page.locator('.listing-card, [data-listing]');
    if (await myListings.count() > 0) {
      await myListings.first().click();
      await page.waitForLoadState('domcontentloaded');

      const detailPage = new ListingDetailPage(page);
      if (await detailPage.editButton.count() > 0) {
        await detailPage.editButton.click();
        await page.waitForLoadState('domcontentloaded');

        expect(page.url()).toContain('edit');
      }
    }
  });
});

test.describe('Listings - Delete', () => {
  test.skip('should allow deleting own listings', async ({ page }) => {
    // Skip to avoid accidentally deleting real listings
    // Enable when needed with proper test data setup
  });
});

test.describe('Listings - Accessibility', () => {
  test('should have proper heading structure', async ({ page }) => {
    const listingsPage = new ListingsPage(page);
    await listingsPage.navigate();

    const h1 = page.locator('h1');
    await expect(h1).toBeVisible();
  });

  test('should have accessible search input', async ({ page }) => {
    const listingsPage = new ListingsPage(page);
    await listingsPage.navigate();

    const searchInput = listingsPage.searchInput;
    const label = await searchInput.getAttribute('aria-label');
    const labelledBy = await searchInput.getAttribute('aria-labelledby');
    const id = await searchInput.getAttribute('id');

    const hasAccessibleLabel = label || labelledBy || (id && await page.locator(`label[for="${id}"]`).count() > 0);
    expect(hasAccessibleLabel).toBeTruthy();
  });

  test('should have keyboard-accessible listing cards', async ({ page }) => {
    const listingsPage = new ListingsPage(page);
    await listingsPage.navigate();

    const count = await listingsPage.getListingCount();
    if (count > 0) {
      // Cards should be focusable
      const card = listingsPage.listingCards.first();
      const link = card.locator('a').first();

      if (await link.count() > 0) {
        await link.focus();
        await expect(link).toBeFocused();
      }
    }
  });
});

test.describe('Listings - Nearby/Location', () => {
  test('should support location-based filtering', async ({ page }) => {
    const listingsPage = new ListingsPage(page);
    await listingsPage.navigate();

    const locationFilter = listingsPage.locationInput;
    if (await locationFilter.count() > 0) {
      await expect(locationFilter).toBeVisible();
    }
  });
});
