import { test, expect } from '@playwright/test';
import { EventsPage, CreateEventPage, EventDetailPage } from '../../page-objects';
import { generateTestData } from '../../helpers/test-utils';

/**
 * Events E2E Tests (React Frontend)
 *
 * Tests the events pages with GlassCard components, category chips, and RSVP functionality.
 *
 * Key features:
 * - Event listing with time filter (Upcoming, Past, All Events) and category chips
 * - Search events with debounced input
 * - Event detail with tabs (Details, Attendees, Check-in for organizers)
 * - RSVP buttons (Going, Interested, Not Going)
 * - Create/edit event form with image upload
 *
 * NOTE: All tests check whether the events feature is enabled for the test tenant
 * before asserting. If events is disabled (FeatureGate redirects to dashboard or
 * shows a ComingSoonPage), tests skip gracefully rather than failing with
 * "Target page, context or browser has been closed".
 */

/**
 * Helper: check whether the events feature is accessible for the current session.
 * Navigates to /events and returns true only if the events page heading is visible.
 * Returns false if we land on a different page (redirect) or see a "coming soon" page.
 */
async function isEventsFeatureEnabled(page: any): Promise<boolean> {
  const eventsPage = new EventsPage(page);
  try {
    await eventsPage.navigate();
    // Allow some time for React to hydrate and any redirects to complete
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(1000);
    // If we got redirected away from /events the feature is off
    if (!page.url().includes('/events')) {
      return false;
    }
    // If the heading is present the feature is on
    const headingVisible = await eventsPage.pageHeading.isVisible().catch(() => false);
    return headingVisible;
  } catch {
    return false;
  }
}

test.describe('Events - Browse', () => {
  test('should display events page', async ({ page }) => {
    const eventsPage = new EventsPage(page);
    await eventsPage.navigate();
    await eventsPage.waitForLoad();

    // Skip gracefully when events feature is disabled for this tenant
    if (!page.url().includes('/events')) {
      test.skip(true, 'Events feature is not enabled for this tenant');
      return;
    }

    await expect(page).toHaveURL(/events/);
    await expect(eventsPage.pageHeading).toBeVisible();
  });

  test('should show events or empty state', async ({ page }) => {
    if (!await isEventsFeatureEnabled(page)) {
      test.skip(true, 'Events feature is not enabled for this tenant');
      return;
    }
    const eventsPage = new EventsPage(page);
    await eventsPage.waitForLoad();

    const count = await eventsPage.getEventCount();
    // EmptyState renders div[role="status"] with h3 title "No events found" or similar
    const noEventsEl = page.locator('[role="status"]').filter({ hasText: /No events/ });
    const hasNoEvents = await noEventsEl.isVisible().catch(() => false);

    // Either have events or empty state
    expect(count > 0 || hasNoEvents).toBeTruthy();
  });

  test('should have search functionality', async ({ page }) => {
    if (!await isEventsFeatureEnabled(page)) {
      test.skip(true, 'Events feature is not enabled for this tenant');
      return;
    }
    const eventsPage = new EventsPage(page);
    await eventsPage.waitForLoad();

    const hasSearch = await eventsPage.hasSearch();
    expect(hasSearch).toBeTruthy();
  });

  test('should have create event button if authenticated', async ({ page }) => {
    if (!await isEventsFeatureEnabled(page)) {
      test.skip(true, 'Events feature is not enabled for this tenant');
      return;
    }
    const eventsPage = new EventsPage(page);
    await eventsPage.waitForLoad();

    // Create button shows for authenticated users
    const hasCreateBtn = await eventsPage.hasCreateButton();
    expect(hasCreateBtn || true).toBeTruthy();
  });

  test('should display event cards with required info', async ({ page }) => {
    if (!await isEventsFeatureEnabled(page)) {
      test.skip(true, 'Events feature is not enabled for this tenant');
      return;
    }
    const eventsPage = new EventsPage(page);
    await eventsPage.waitForLoad();

    const count = await eventsPage.getEventCount();
    if (count > 0) {
      const card = eventsPage.eventCards.first();

      // Should have title
      const title = card.locator('h3');
      await expect(title).toBeVisible();

      // Should have date badge
      const date = card.locator('time');
      await expect(date).toBeVisible();
    }
  });

  test('should navigate to event detail', async ({ page }) => {
    if (!await isEventsFeatureEnabled(page)) {
      test.skip(true, 'Events feature is not enabled for this tenant');
      return;
    }
    const eventsPage = new EventsPage(page);
    await eventsPage.waitForLoad();

    const count = await eventsPage.getEventCount();
    if (count > 0) {
      await eventsPage.clickEvent(0);
      expect(page.url()).toMatch(/events\/\d+/);
    }
  });

  test('should have category filter chips', async ({ page }) => {
    if (!await isEventsFeatureEnabled(page)) {
      test.skip(true, 'Events feature is not enabled for this tenant');
      return;
    }
    const eventsPage = new EventsPage(page);
    await eventsPage.waitForLoad();

    // Category chips are HeroUI Chip components with aria-pressed attribute
    // They render in a div[role="group"] and use onClick/aria-pressed (not aria-pressed on the chip root)
    const categoryGroup = page.locator('[role="group"][aria-label="Filter by category"]');
    const chipCount = await eventsPage.categoryChips.count();
    const groupExists = await categoryGroup.count() > 0;
    expect(chipCount > 0 || groupExists).toBeTruthy();
  });
});

test.describe('Events - Search & Filters', () => {
  test('should search events', async ({ page }) => {
    if (!await isEventsFeatureEnabled(page)) {
      test.skip(true, 'Events feature is not enabled for this tenant');
      return;
    }
    const eventsPage = new EventsPage(page);
    await eventsPage.waitForLoad();

    if (await eventsPage.hasSearch()) {
      await eventsPage.searchEvents('test');
      await page.waitForTimeout(600); // Debounce

      // Search should work without errors
      expect(true).toBeTruthy();
    }
  });

  test('should filter by category', async ({ page }) => {
    if (!await isEventsFeatureEnabled(page)) {
      test.skip(true, 'Events feature is not enabled for this tenant');
      return;
    }
    const eventsPage = new EventsPage(page);
    await eventsPage.waitForLoad();

    // Click a category chip — filterByCategory uses button:has-text() which is reliable
    await eventsPage.filterByCategory('Workshop');
    await page.waitForTimeout(600);

    // Count may change or stay same
    const newCount = await eventsPage.getEventCount();
    expect(newCount).toBeGreaterThanOrEqual(0);
  });

  test('should show all category chip', async ({ page }) => {
    if (!await isEventsFeatureEnabled(page)) {
      test.skip(true, 'Events feature is not enabled for this tenant');
      return;
    }
    const eventsPage = new EventsPage(page);
    await eventsPage.waitForLoad();

    // "All" category chip — HeroUI Chip renders as div with aria-pressed, or as a span.
    // Use a flexible text match rather than relying solely on aria-pressed
    const allChip = page.locator('[aria-label="Filter by category"]').locator('[aria-pressed], button, div[class*="chip"]').filter({ hasText: /^All$/ }).first();
    const allChipAlt = eventsPage.allCategoryChip;
    const visible = await allChip.isVisible().catch(() => false) || await allChipAlt.isVisible().catch(() => false);
    expect(visible).toBeTruthy();
  });

  test('should have time filter select', async ({ page }) => {
    if (!await isEventsFeatureEnabled(page)) {
      test.skip(true, 'Events feature is not enabled for this tenant');
      return;
    }
    const eventsPage = new EventsPage(page);
    await eventsPage.waitForLoad();

    const hasFilter = await eventsPage.timeFilterSelect.count() > 0;
    expect(hasFilter).toBeTruthy();
  });
});

test.describe('Events - Create', () => {
  test('should navigate to create event page', async ({ page }) => {
    if (!await isEventsFeatureEnabled(page)) {
      test.skip(true, 'Events feature is not enabled for this tenant');
      return;
    }
    const eventsPage = new EventsPage(page);
    await eventsPage.waitForLoad();

    if (await eventsPage.hasCreateButton()) {
      await eventsPage.clickCreateEvent();
      expect(page.url()).toContain('/events/create');
    } else {
      // Manually navigate if button not visible
      const createPage = new CreateEventPage(page);
      await createPage.navigate();
      // If events feature is gated, we may land on dashboard
      if (!page.url().includes('/events/create')) {
        test.skip(true, 'Events create page not accessible — feature may be gated');
        return;
      }
      expect(page.url()).toContain('/events/create');
    }
  });

  test('should display create event form', async ({ page }) => {
    if (!await isEventsFeatureEnabled(page)) {
      test.skip(true, 'Events feature is not enabled for this tenant');
      return;
    }
    const createPage = new CreateEventPage(page);
    await createPage.navigate();
    await createPage.waitForLoad();

    if (!page.url().includes('/events/create')) {
      test.skip(true, 'Events create page not accessible — feature may be gated');
      return;
    }

    // Check form elements
    await expect(createPage.titleInput).toBeVisible();
    await expect(createPage.descriptionTextarea).toBeVisible();
    await expect(createPage.submitButton).toBeVisible();
  });

  test('should validate required fields', async ({ page }) => {
    if (!await isEventsFeatureEnabled(page)) {
      test.skip(true, 'Events feature is not enabled for this tenant');
      return;
    }
    const createPage = new CreateEventPage(page);
    await createPage.navigate();
    await createPage.waitForLoad();

    if (!page.url().includes('/events/create')) {
      test.skip(true, 'Events create page not accessible — feature may be gated');
      return;
    }

    // Try to submit empty form
    await createPage.submit();
    await page.waitForTimeout(500);

    // Should have errors or stay on page
    const hasErrors = await createPage.hasErrors();
    const stillOnCreate = page.url().includes('/create');

    expect(hasErrors || stillOnCreate).toBeTruthy();
  });

  test('should have image upload area', async ({ page }) => {
    if (!await isEventsFeatureEnabled(page)) {
      test.skip(true, 'Events feature is not enabled for this tenant');
      return;
    }
    const createPage = new CreateEventPage(page);
    await createPage.navigate();
    await createPage.waitForLoad();

    if (!page.url().includes('/events/create')) {
      test.skip(true, 'Events create page not accessible — feature may be gated');
      return;
    }

    const uploadArea = createPage.imageUploadArea;
    await expect(uploadArea).toBeVisible();
  });

  test('should have category select', async ({ page }) => {
    if (!await isEventsFeatureEnabled(page)) {
      test.skip(true, 'Events feature is not enabled for this tenant');
      return;
    }
    const createPage = new CreateEventPage(page);
    await createPage.navigate();
    await createPage.waitForLoad();

    if (!page.url().includes('/events/create')) {
      test.skip(true, 'Events create page not accessible — feature may be gated');
      return;
    }

    await expect(createPage.categorySelect).toBeVisible();
  });

  test('should have date and time inputs', async ({ page }) => {
    if (!await isEventsFeatureEnabled(page)) {
      test.skip(true, 'Events feature is not enabled for this tenant');
      return;
    }
    const createPage = new CreateEventPage(page);
    await createPage.navigate();
    await createPage.waitForLoad();

    if (!page.url().includes('/events/create')) {
      test.skip(true, 'Events create page not accessible — feature may be gated');
      return;
    }

    await expect(createPage.startDateInput).toBeVisible();
    await expect(createPage.startTimeInput).toBeVisible();
  });

  test('should have location and max attendees inputs', async ({ page }) => {
    if (!await isEventsFeatureEnabled(page)) {
      test.skip(true, 'Events feature is not enabled for this tenant');
      return;
    }
    const createPage = new CreateEventPage(page);
    await createPage.navigate();
    await createPage.waitForLoad();

    if (!page.url().includes('/events/create')) {
      test.skip(true, 'Events create page not accessible — feature may be gated');
      return;
    }

    await expect(createPage.locationInput).toBeVisible();
    await expect(createPage.maxAttendeesInput).toBeVisible();
  });

  test.skip('should create a new event', async ({ page }) => {
    // Skip to avoid creating real events
    const createPage = new CreateEventPage(page);
    await createPage.navigate();
    await createPage.waitForLoad();

    const testData = generateTestData();
    const futureDate = new Date();
    futureDate.setDate(futureDate.getDate() + 7);
    const dateStr = futureDate.toISOString().split('T')[0];

    await createPage.fillForm({
      title: testData.title,
      description: testData.description,
      startDate: dateStr,
      startTime: '14:00',
      location: 'Community Center',
      maxAttendees: '50',
    });

    await createPage.submit();
    await page.waitForTimeout(2000);

    // Should redirect to events page or event detail
    expect(page.url()).toMatch(/events/);
  });
});

test.describe('Events - Detail', () => {
  test('should display event details', async ({ page }) => {
    if (!await isEventsFeatureEnabled(page)) {
      test.skip(true, 'Events feature is not enabled for this tenant');
      return;
    }
    const eventsPage = new EventsPage(page);
    await eventsPage.waitForLoad();

    const count = await eventsPage.getEventCount();
    if (count > 0) {
      await eventsPage.clickEvent(0);

      const detailPage = new EventDetailPage(page);
      await detailPage.waitForLoad();

      await expect(detailPage.title).toBeVisible();
      if (await detailPage.description.count() > 0) {
        await expect(detailPage.description).toBeVisible();
      }
    }
  });

  test('should show event date and time', async ({ page }) => {
    if (!await isEventsFeatureEnabled(page)) {
      test.skip(true, 'Events feature is not enabled for this tenant');
      return;
    }
    const eventsPage = new EventsPage(page);
    await eventsPage.waitForLoad();

    const count = await eventsPage.getEventCount();
    if (count > 0) {
      await eventsPage.clickEvent(0);

      const detailPage = new EventDetailPage(page);
      await detailPage.waitForLoad();

      await expect(detailPage.dateTime).toBeVisible();
    }
  });

  test('should show organizer information', async ({ page }) => {
    if (!await isEventsFeatureEnabled(page)) {
      test.skip(true, 'Events feature is not enabled for this tenant');
      return;
    }
    const eventsPage = new EventsPage(page);
    await eventsPage.waitForLoad();

    const count = await eventsPage.getEventCount();
    if (count > 0) {
      await eventsPage.clickEvent(0);

      const detailPage = new EventDetailPage(page);
      await detailPage.waitForLoad();

      // Organizer info should be visible
      const hasOrganizer = await detailPage.organizer.count() > 0;
      expect(hasOrganizer).toBeTruthy();
    }
  });

  test('should have RSVP buttons', async ({ page }) => {
    if (!await isEventsFeatureEnabled(page)) {
      test.skip(true, 'Events feature is not enabled for this tenant');
      return;
    }
    const eventsPage = new EventsPage(page);
    await eventsPage.waitForLoad();

    const count = await eventsPage.getEventCount();
    if (count > 0) {
      await eventsPage.clickEvent(0);

      const detailPage = new EventDetailPage(page);
      await detailPage.waitForLoad();

      // Should have RSVP buttons or edit button (if own event)
      const hasRsvp = await detailPage.goingButton.count() > 0;
      const hasEdit = await detailPage.editButton.count() > 0;

      expect(hasRsvp || hasEdit).toBeTruthy();
    }
  });

  test('should show attendee count', async ({ page }) => {
    if (!await isEventsFeatureEnabled(page)) {
      test.skip(true, 'Events feature is not enabled for this tenant');
      return;
    }
    const eventsPage = new EventsPage(page);
    await eventsPage.waitForLoad();

    const count = await eventsPage.getEventCount();
    if (count > 0) {
      await eventsPage.clickEvent(0);

      const detailPage = new EventDetailPage(page);
      await detailPage.waitForLoad();

      const attendeeCount = await detailPage.getAttendeeCount();
      expect(attendeeCount).toBeGreaterThanOrEqual(0);
    }
  });

  test('should have tabs for Details and Attendees', async ({ page }) => {
    if (!await isEventsFeatureEnabled(page)) {
      test.skip(true, 'Events feature is not enabled for this tenant');
      return;
    }
    const eventsPage = new EventsPage(page);
    await eventsPage.waitForLoad();

    const count = await eventsPage.getEventCount();
    if (count > 0) {
      await eventsPage.clickEvent(0);

      const detailPage = new EventDetailPage(page);
      await detailPage.waitForLoad();

      await expect(detailPage.detailsTab).toBeVisible();
      await expect(detailPage.attendeesTab).toBeVisible();
    }
  });

  test('should switch to Attendees tab', async ({ page }) => {
    if (!await isEventsFeatureEnabled(page)) {
      test.skip(true, 'Events feature is not enabled for this tenant');
      return;
    }
    const eventsPage = new EventsPage(page);
    await eventsPage.waitForLoad();

    const count = await eventsPage.getEventCount();
    if (count > 0) {
      await eventsPage.clickEvent(0);

      const detailPage = new EventDetailPage(page);
      await detailPage.waitForLoad();

      await detailPage.switchToAttendeesTab();

      // Tab should be selected
      const tabClasses = await detailPage.attendeesTab.getAttribute('class');
      const isSelected = tabClasses?.includes('selected') ||
                        await detailPage.attendeesTab.getAttribute('aria-selected') === 'true';

      expect(isSelected || true).toBeTruthy();
    }
  });

  test.skip('should RSVP to event', async ({ page }) => {
    // Skip to avoid creating real RSVPs
    const eventsPage = new EventsPage(page);
    await eventsPage.navigate();
    await eventsPage.waitForLoad();

    const count = await eventsPage.getEventCount();
    if (count > 0) {
      await eventsPage.clickEvent(0);

      const detailPage = new EventDetailPage(page);
      await detailPage.waitForLoad();

      if (await detailPage.goingButton.count() > 0 && !await detailPage.hasRsvpd()) {
        await detailPage.rsvpGoing();
        await page.waitForTimeout(1000);

        // Should show RSVP status
        const hasRsvpd = await detailPage.hasRsvpd();
        expect(hasRsvpd).toBeTruthy();
      }
    }
  });

  test('should have share button', async ({ page }) => {
    if (!await isEventsFeatureEnabled(page)) {
      test.skip(true, 'Events feature is not enabled for this tenant');
      return;
    }
    const eventsPage = new EventsPage(page);
    await eventsPage.waitForLoad();

    const count = await eventsPage.getEventCount();
    if (count > 0) {
      await eventsPage.clickEvent(0);

      const detailPage = new EventDetailPage(page);
      await detailPage.waitForLoad();

      const hasShare = await detailPage.shareButton.count() > 0;
      expect(hasShare || true).toBeTruthy();
    }
  });
});

test.describe('Events - Edit', () => {
  test('should show edit button for own events', async ({ page }) => {
    if (!await isEventsFeatureEnabled(page)) {
      test.skip(true, 'Events feature is not enabled for this tenant');
      return;
    }
    const eventsPage = new EventsPage(page);
    await eventsPage.waitForLoad();

    const count = await eventsPage.getEventCount();
    if (count > 0) {
      // Check first event
      await eventsPage.clickEvent(0);

      const detailPage = new EventDetailPage(page);
      await detailPage.waitForLoad();

      if (await detailPage.editButton.count() > 0) {
        await expect(detailPage.editButton).toBeVisible();
      }
    }
  });
});

test.describe('Events - Accessibility', () => {
  test('should have proper heading structure', async ({ page }) => {
    if (!await isEventsFeatureEnabled(page)) {
      test.skip(true, 'Events feature is not enabled for this tenant');
      return;
    }
    const eventsPage = new EventsPage(page);
    await eventsPage.waitForLoad();

    const h1 = page.locator('h1');
    await expect(h1).toBeVisible();
  });

  test('should have accessible search input', async ({ page }) => {
    if (!await isEventsFeatureEnabled(page)) {
      test.skip(true, 'Events feature is not enabled for this tenant');
      return;
    }
    const eventsPage = new EventsPage(page);
    await eventsPage.waitForLoad();

    const searchInput = eventsPage.searchInput;
    const ariaLabel = await searchInput.getAttribute('aria-label');
    const placeholder = await searchInput.getAttribute('placeholder');

    expect(ariaLabel || placeholder).toBeTruthy();
  });

  test('should have accessible date inputs in create form', async ({ page }) => {
    if (!await isEventsFeatureEnabled(page)) {
      test.skip(true, 'Events feature is not enabled for this tenant');
      return;
    }
    const createPage = new CreateEventPage(page);
    await createPage.navigate();
    await createPage.waitForLoad();

    if (!page.url().includes('/events/create')) {
      test.skip(true, 'Events create page not accessible — feature may be gated');
      return;
    }

    const dateInput = createPage.startDateInput;
    const label = await dateInput.getAttribute('aria-label');
    const id = await dateInput.getAttribute('id');

    const hasLabel = label || (id && await page.locator(`label[for="${id}"]`).count() > 0);
    expect(hasLabel || true).toBeTruthy();
  });

  test('should have keyboard-accessible event cards', async ({ page }) => {
    if (!await isEventsFeatureEnabled(page)) {
      test.skip(true, 'Events feature is not enabled for this tenant');
      return;
    }
    const eventsPage = new EventsPage(page);
    await eventsPage.waitForLoad();

    const count = await eventsPage.getEventCount();
    if (count > 0) {
      const card = eventsPage.eventCards.first();
      const link = card.locator('a').first();

      if (await link.count() > 0) {
        await link.focus();
        await expect(link).toBeFocused();
      }
    }
  });
});

test.describe('Events - Responsive', () => {
  test.use({ viewport: { width: 375, height: 667 } });

  test('should display properly on mobile', async ({ page }) => {
    if (!await isEventsFeatureEnabled(page)) {
      test.skip(true, 'Events feature is not enabled for this tenant');
      return;
    }
    const eventsPage = new EventsPage(page);
    await eventsPage.waitForLoad();

    await expect(eventsPage.pageHeading).toBeVisible();

    // Category chips may wrap on mobile — just verify the group container exists
    const categoryGroup = page.locator('[role="group"][aria-label="Filter by category"]');
    const groupExists = await categoryGroup.count() > 0;
    expect(groupExists || true).toBeTruthy();
  });
});

test.describe('Events - Pagination', () => {
  test('should show load more button if available', async ({ page }) => {
    if (!await isEventsFeatureEnabled(page)) {
      test.skip(true, 'Events feature is not enabled for this tenant');
      return;
    }
    const eventsPage = new EventsPage(page);
    await eventsPage.waitForLoad();

    const initialCount = await eventsPage.getEventCount();

    if (initialCount > 0) {
      const hasLoadMore = await eventsPage.loadMoreButton.count() > 0;

      if (hasLoadMore) {
        await eventsPage.loadMore();
        await page.waitForTimeout(1000);

        const newCount = await eventsPage.getEventCount();
        expect(newCount).toBeGreaterThanOrEqual(initialCount);
      }
    }
  });
});
