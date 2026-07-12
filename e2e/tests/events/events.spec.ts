// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { expect, test, type Page } from '@playwright/test';
import { CreateEventPage, EventDetailPage, EventsPage } from '../../page-objects';

/**
 * Events E2E contract for the authenticated hour-timebank fixture.
 *
 * The Events feature is a required precondition for this targeted suite. A
 * disabled feature or missing authenticated session is a setup failure, so the
 * suite reports it directly instead of silently passing without exercising UI.
 * Detail tests likewise require at least one upcoming event in the fixture.
 */

async function openEvents(page: Page): Promise<EventsPage> {
  const eventsPage = new EventsPage(page);
  await eventsPage.navigate();
  await eventsPage.waitForLoad();

  await expect(page, 'The Events feature must be enabled for the E2E tenant').toHaveURL(/\/events\/?$/);
  await expect(eventsPage.pageHeading).toBeVisible();

  return eventsPage;
}

async function openFirstEvent(page: Page): Promise<EventDetailPage> {
  const eventsPage = await openEvents(page);
  await expect(
    eventsPage.eventCards.first(),
    'Events detail coverage requires at least one upcoming event in the E2E fixture',
  ).toBeVisible();

  await eventsPage.clickEvent(0);
  await expect(page).toHaveURL(/\/events\/\d+\/?$/);

  const detailPage = new EventDetailPage(page);
  await detailPage.waitForLoad();
  await expect(detailPage.title).toBeVisible();

  return detailPage;
}

async function expectCardsOrEmptyState(eventsPage: EventsPage): Promise<void> {
  if (await eventsPage.eventCards.first().isVisible()) {
    await expect(eventsPage.eventCards.first().locator('h3')).toBeVisible();
    await expect(eventsPage.eventCards.first().locator('time')).toBeVisible();
    return;
  }

  await expect(eventsPage.noEventsMessage).toBeVisible();
}

test.describe('Events - Browse', () => {
  test('displays the Events page', async ({ page }) => {
    await openEvents(page);
  });

  test('renders event cards or a concrete empty state', async ({ page }) => {
    const eventsPage = await openEvents(page);
    await expectCardsOrEmptyState(eventsPage);
  });

  test('exposes authenticated create-event navigation', async ({ page }) => {
    const eventsPage = await openEvents(page);

    await expect(eventsPage.createEventButton).toBeVisible();
    await eventsPage.clickCreateEvent();
    await expect(page).toHaveURL(/\/events\/create\/?$/);
  });

  test('exposes the category filter group', async ({ page }) => {
    const eventsPage = await openEvents(page);
    const categoryGroup = page.getByRole('group', { name: 'Filter by category' });

    await expect(categoryGroup).toBeVisible();
    await expect(eventsPage.allCategoryChip).toHaveAttribute('aria-pressed', 'true');
  });
});

test.describe('Events - Search and filters', () => {
  test('applies a debounced search query', async ({ page }) => {
    const eventsPage = await openEvents(page);

    await eventsPage.searchEvents('governance workshop');

    await expect(eventsPage.searchInput).toHaveValue('governance workshop');
    await expect(page.getByText('Search: governance workshop', { exact: true })).toBeVisible();
    await expectCardsOrEmptyState(eventsPage);
  });

  test('selects a category through its toggle button', async ({ page }) => {
    const eventsPage = await openEvents(page);
    const workshopButton = page.getByRole('button', { name: 'Workshop', exact: true });

    await eventsPage.filterByCategory('Workshop');

    await expect(workshopButton).toHaveAttribute('aria-pressed', 'true');
    await expect(eventsPage.allCategoryChip).toHaveAttribute('aria-pressed', 'false');
  });

  test('selects a time filter through the HeroUI listbox', async ({ page }) => {
    const eventsPage = await openEvents(page);

    await expect(eventsPage.timeFilterSelect).toBeVisible();
    await eventsPage.filterByTime('past');

    await expect(eventsPage.timeFilterSelect).toContainText('Past');
    await expectCardsOrEmptyState(eventsPage);
  });
});

test.describe('Events - Create form', () => {
  test('renders the required create-event controls', async ({ page }) => {
    const createPage = new CreateEventPage(page);
    await createPage.navigate();
    await createPage.waitForLoad();

    await expect(page).toHaveURL(/\/events\/create\/?$/);
    await expect(createPage.titleInput).toBeVisible();
    await expect(createPage.descriptionTextarea).toBeVisible();
    await expect(createPage.categorySelect).toBeVisible();
    await expect(createPage.submitButton).toBeVisible();
  });

  test('reports concrete validation errors for an empty submission', async ({ page }) => {
    const createPage = new CreateEventPage(page);
    await createPage.navigate();
    await createPage.waitForLoad();

    await createPage.submit();

    await expect(page.getByText('Title is required', { exact: true })).toBeVisible();
    await expect(page.getByText('Description is required', { exact: true })).toBeVisible();
    await expect(page.getByText('Start date is required', { exact: true })).toBeVisible();
    await expect(page.getByText('Start time is required', { exact: true })).toBeVisible();
    await expect(page).toHaveURL(/\/events\/create\/?$/);
  });

  test('selects a category through the HeroUI listbox', async ({ page }) => {
    const createPage = new CreateEventPage(page);
    await createPage.navigate();
    await createPage.waitForLoad();

    await createPage.selectCategory('workshop');

    await expect(createPage.categorySelect).toContainText('Workshop');
  });

  test('exposes date, time, location and capacity controls', async ({ page }) => {
    const createPage = new CreateEventPage(page);
    await createPage.navigate();
    await createPage.waitForLoad();

    await expect(createPage.startDateInput).toBeVisible();
    await expect(createPage.startTimeInput).toBeVisible();
    await expect(createPage.locationInput).toBeVisible();
    await expect(createPage.maxAttendeesInput).toBeVisible();
  });

  test('exposes a keyboard-operable cover-image upload target', async ({ page }) => {
    const createPage = new CreateEventPage(page);
    await createPage.navigate();
    await createPage.waitForLoad();

    await expect(createPage.imageUploadArea).toBeVisible();
    await expect(createPage.imageUploadArea).toHaveAttribute('role', 'button');
    await expect(createPage.imageUploadArea).toHaveAttribute('tabindex', '0');
  });
});

test.describe('Events - Detail', () => {
  test('renders identity, date and organiser information', async ({ page }) => {
    const detailPage = await openFirstEvent(page);

    await expect(detailPage.title).not.toHaveText('');
    await expect(detailPage.dateTime).toBeVisible();
    await expect(detailPage.organizer).toBeVisible();
  });

  test('exposes RSVP actions or organiser controls', async ({ page }) => {
    const detailPage = await openFirstEvent(page);
    const hasRsvpAction = await detailPage.goingButton.isVisible();
    const hasOrganiserControl = await detailPage.editButton.isVisible();

    expect(hasRsvpAction || hasOrganiserControl).toBe(true);
  });

  test('exposes Details and Attendees tabs', async ({ page }) => {
    const detailPage = await openFirstEvent(page);

    await expect(detailPage.detailsTab).toBeVisible();
    await expect(detailPage.attendeesTab).toBeVisible();
  });

  test('switches to the Attendees panel', async ({ page }) => {
    const detailPage = await openFirstEvent(page);

    await detailPage.switchToAttendeesTab();

    await expect(detailPage.attendeesTab).toHaveAttribute('aria-selected', 'true');
    await expect(detailPage.attendeesList).toBeVisible();
  });

  test('shows a numeric going count', async ({ page }) => {
    const detailPage = await openFirstEvent(page);

    await expect(detailPage.attendeeCount).toHaveText(/^\d+$/);
  });

  test('exposes an accessible share action', async ({ page }) => {
    const detailPage = await openFirstEvent(page);

    await expect(detailPage.shareButton).toBeVisible();
    await expect(detailPage.shareButton).toHaveAccessibleName(/^Copy link to /);
  });

  test('never presents archive as irreversible deletion', async ({ page }) => {
    const detailPage = await openFirstEvent(page);

    await expect(page.getByRole('button', { name: /^Delete / })).toHaveCount(0);
    if (await detailPage.archiveButton.isVisible()) {
      await expect(detailPage.archiveButton).toHaveAccessibleName(/^Archive /);
    }
  });
});

test.describe('Events - Accessibility', () => {
  test('has one visible page-level heading', async ({ page }) => {
    await openEvents(page);
    const heading = page.getByRole('heading', { level: 1 });

    await expect(heading).toHaveCount(1);
    await expect(heading).toBeVisible();
  });

  test('gives the search field an accessible name', async ({ page }) => {
    const eventsPage = await openEvents(page);

    await expect(eventsPage.searchInput).toHaveAccessibleName('Search events');
  });

  test('gives the start date control an accessible name', async ({ page }) => {
    const createPage = new CreateEventPage(page);
    await createPage.navigate();
    await createPage.waitForLoad();

    await expect(createPage.startDateInput).toHaveAccessibleName('Start Date');
  });

  test('makes event cards keyboard reachable', async ({ page }) => {
    const eventsPage = await openEvents(page);
    await expect(
      eventsPage.eventCards.first(),
      'Keyboard coverage requires at least one upcoming event in the E2E fixture',
    ).toBeVisible();
    const eventLink = eventsPage.eventCards.first().locator('xpath=ancestor::a[1]');

    await eventLink.focus();

    await expect(eventLink).toBeFocused();
    await expect(eventLink).toHaveAttribute('href', /\/events\/\d+$/);
  });
});

test.describe('Events - Responsive', () => {
  test.use({ viewport: { width: 375, height: 667 } });

  test('keeps primary controls available at a mobile viewport', async ({ page }) => {
    const eventsPage = await openEvents(page);

    await expect(eventsPage.searchInput).toBeVisible();
    await expect(eventsPage.timeFilterSelect).toBeVisible();
    await expect(page.getByRole('group', { name: 'Filter by category' })).toBeVisible();
  });
});

test.describe('Events - Pagination', () => {
  test('loads another page when the server exposes a continuation', async ({ page }) => {
    const eventsPage = await openEvents(page);
    const initialCount = await eventsPage.getEventCount();

    if (await eventsPage.loadMoreButton.isVisible()) {
      await eventsPage.loadMore();
      await expect.poll(() => eventsPage.getEventCount()).toBeGreaterThan(initialCount);
      return;
    }

    expect(initialCount).toBeLessThanOrEqual(20);
    await expect(eventsPage.loadMoreButton).toBeHidden();
  });
});
