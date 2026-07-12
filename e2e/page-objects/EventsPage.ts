// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Page, Locator, expect } from '@playwright/test';
import { BasePage } from './BasePage';

/**
 * Events List Page Object (React with GlassCard and HeroUI components)
 *
 * The React events page uses:
 * - GlassCard for event cards and search bar
 * - HeroUI Select for time filter (Upcoming, Past, All Events)
 * - Chip components for category filters
 * - EventCard components with date badges
 */
export class EventsPage extends BasePage {
  readonly pageHeading: Locator;
  readonly createEventButton: Locator;

  // Search and filters
  readonly searchCard: Locator;
  readonly searchInput: Locator;
  readonly timeFilterSelect: Locator;
  readonly categoryChips: Locator;
  readonly allCategoryChip: Locator;

  // Event cards
  readonly eventCards: Locator;
  readonly noEventsMessage: Locator;

  // Load more
  readonly loadMoreButton: Locator;

  constructor(page: Page, tenant?: string) {
    super(page, tenant);

    this.pageHeading = page.locator('h1:has-text("Events")');
    this.createEventButton = page.locator('a[href*="/events/create"], button:has-text("Create Event")').first();

    // Search card with GlassCard styling
    this.searchCard = page.locator('[class*="glass"]').filter({ has: page.locator('input[placeholder*="Search events"]') });
    this.searchInput = page.locator('input[placeholder*="Search events"]');

    // HeroUI v3 Select exposes a button trigger and ARIA listbox options.
    this.timeFilterSelect = page.getByRole('button', { name: 'Filter events by time' });

    // Category filter chips - HeroUI Chip components with aria-pressed
    this.categoryChips = page.locator('[aria-pressed]').filter({ hasText: /Workshop|Social|Outdoor|Online|Meeting|Training|Other/ });
    this.allCategoryChip = page.locator('[aria-pressed]:has-text("All")').first();

    // Event cards - GlassCard with event content
    this.eventCards = page.locator('article').filter({ has: page.locator('time') });
    this.noEventsMessage = page.locator('text=/No events found|No upcoming events/');

    // Load more pagination
    this.loadMoreButton = page.locator('button:has-text("Load More")');
  }

  /**
   * Navigate to events page
   */
  async navigate(): Promise<void> {
    await this.goto('events');
  }

  /**
   * Navigate to create event page
   */
  async navigateToCreate(): Promise<void> {
    await this.goto('events/create');
  }

  /**
   * Wait for events page to load
   */
  async waitForLoad(): Promise<void> {
    await this.page.waitForLoadState('domcontentloaded');
    await this.page.waitForLoadState('networkidle').catch(() => {});

    // Wait for React to hydrate - search input should always be present
    await this.searchInput.waitFor({
      state: 'attached',
      timeout: 15000
    }).catch(() => {});

    // Give React time to render
    await this.page.waitForTimeout(500);
  }

  /**
   * Get number of visible event cards
   */
  async getEventCount(): Promise<number> {
    return await this.eventCards.count();
  }

  /**
   * Search for events
   */
  async searchEvents(query: string): Promise<void> {
    await this.searchInput.fill(query);
    await this.page.waitForTimeout(500); // Debounce
  }

  /**
   * Filter events by time (Upcoming, Past, All Events)
   */
  async filterByTime(filter: 'upcoming' | 'past' | 'all'): Promise<void> {
    const filterText = filter === 'upcoming' ? 'Upcoming' : filter === 'past' ? 'Past' : 'All Events';
    await this.selectHeroUiOption(this.timeFilterSelect, filterText);
    await this.page.waitForTimeout(500);
  }

  /**
   * Filter events by category
   */
  async filterByCategory(category: string): Promise<void> {
    const chip = this.page.getByRole('button', { name: category, exact: true });
    await chip.click();
    await this.page.waitForTimeout(500);
  }

  private async selectHeroUiOption(trigger: Locator, optionName: string): Promise<void> {
    await trigger.click();
    const option = this.page.getByRole('option', { name: optionName, exact: true });
    await expect(option).toBeVisible();
    await option.click();
    await expect(trigger).toContainText(optionName);
  }

  /**
   * Click on an event card
   */
  async clickEvent(index: number = 0): Promise<void> {
    await this.eventCards.nth(index).click();
    await this.page.waitForLoadState('domcontentloaded');
  }

  /**
   * Click create event button
   */
  async clickCreateEvent(): Promise<void> {
    await this.createEventButton.click();
    await this.page.waitForLoadState('domcontentloaded');
  }

  /**
   * Check if no events are shown
   */
  async hasNoEvents(): Promise<boolean> {
    return await this.noEventsMessage.isVisible();
  }

  /**
   * Get event titles from visible cards
   */
  async getEventTitles(): Promise<string[]> {
    const titles = await this.eventCards.locator('h3').allTextContents();
    return titles.map(t => t.trim());
  }

  /**
   * Check if search input is available
   */
  async hasSearch(): Promise<boolean> {
    return await this.searchInput.count() > 0;
  }

  /**
   * Check if create button is available
   */
  async hasCreateButton(): Promise<boolean> {
    return await this.createEventButton.count() > 0;
  }

  /**
   * Load more events
   */
  async loadMore(): Promise<void> {
    await this.loadMoreButton.click();
    await this.page.waitForTimeout(1000);
  }
}

/**
 * Create/Edit Event Page Object (React with HeroUI Form Components)
 *
 * The React create/edit event page uses:
 * - HeroUI Input components for form fields
 * - HeroUI Textarea for description
 * - HeroUI Select for category dropdown
 * - Image upload with drag & drop
 */
export class CreateEventPage extends BasePage {
  readonly pageHeading: Locator;

  // Form fields
  readonly titleInput: Locator;
  readonly categorySelect: Locator;
  readonly descriptionTextarea: Locator;
  readonly startDateInput: Locator;
  readonly startTimeInput: Locator;
  readonly endDateInput: Locator;
  readonly endTimeInput: Locator;
  readonly locationInput: Locator;
  readonly maxAttendeesInput: Locator;

  // Image upload
  readonly imageUploadArea: Locator;
  readonly imagePreview: Locator;
  readonly removeImageButton: Locator;

  // Actions
  readonly submitButton: Locator;
  readonly cancelButton: Locator;

  // Validation
  readonly errorMessages: Locator;

  constructor(page: Page, tenant?: string) {
    super(page, tenant);

    this.pageHeading = page.locator('h1:has-text("Create"), h1:has-text("Edit")');

    // Form fields with HeroUI Input components
    this.titleInput = page.getByRole('textbox', { name: 'Event Title' });
    this.categorySelect = page.getByRole('button', { name: 'Event category' });
    this.descriptionTextarea = page.getByRole('textbox', { name: 'Description' });

    // Date/time inputs
    this.startDateInput = page.getByRole('textbox', { name: 'Start Date' });
    this.startTimeInput = page.getByRole('group', { name: 'Start Time' });
    this.endDateInput = page.getByRole('textbox', { name: 'End Date (optional)' });
    this.endTimeInput = page.getByRole('group', { name: 'End Time (optional)' });

    this.locationInput = page.getByRole('textbox', { name: 'Location (optional)' });
    this.maxAttendeesInput = page.getByRole('spinbutton', { name: 'Max Attendees (optional)' });

    // Image upload
    this.imageUploadArea = page.getByRole('button', { name: 'Upload cover image' });
    this.imagePreview = page.locator('img[alt*="cover preview"]');
    this.removeImageButton = page.locator('button[aria-label="Remove image"]');

    // Actions
    this.submitButton = page.locator('button[type="submit"]:has-text("Create"), button[type="submit"]:has-text("Update")');
    this.cancelButton = page.locator('button:has-text("Cancel")');

    // Validation
    this.errorMessages = page.locator('[role="alert"], .error, [data-slot="error-message"]');
  }

  /**
   * Navigate to create event page
   */
  async navigate(): Promise<void> {
    await this.goto('events/create');
  }

  /**
   * Navigate to edit event page
   */
  async navigateToEdit(id: number | string): Promise<void> {
    await this.goto(`events/edit/${id}`);
  }

  /**
   * Wait for form to load
   */
  async waitForLoad(): Promise<void> {
    await this.page.waitForLoadState('domcontentloaded');
    await this.titleInput.waitFor({ state: 'visible', timeout: 10000 }).catch(() => {});
  }

  /**
   * Fill event form
   */
  async fillForm(data: {
    title: string;
    description: string;
    startDate: string;
    startTime?: string;
    endDate?: string;
    endTime?: string;
    location?: string;
    maxAttendees?: string;
    category?: string;
  }): Promise<void> {
    await this.titleInput.fill(data.title);
    await this.descriptionTextarea.fill(data.description);
    await this.startDateInput.fill(data.startDate);

    if (data.startTime) {
      await this.startTimeInput.fill(data.startTime);
    }

    if (data.endDate) {
      await this.endDateInput.fill(data.endDate);
    }

    if (data.endTime) {
      await this.endTimeInput.fill(data.endTime);
    }

    if (data.location) {
      await this.locationInput.fill(data.location);
    }

    if (data.maxAttendees) {
      await this.maxAttendeesInput.fill(data.maxAttendees);
    }

    if (data.category) {
      await this.selectCategory(data.category);
    }
  }

  async selectCategory(category: string): Promise<void> {
    const categoryLabel = category.charAt(0).toUpperCase() + category.slice(1);
    await this.selectHeroUiOption(this.categorySelect, categoryLabel);
  }

  private async selectHeroUiOption(trigger: Locator, optionName: string): Promise<void> {
    await trigger.click();
    const option = this.page.getByRole('option', { name: optionName, exact: true });
    await expect(option).toBeVisible();
    await option.click();
    await expect(trigger).toContainText(optionName);
  }

  /**
   * Upload event image
   */
  async uploadImage(filePath: string): Promise<void> {
    const fileInput = this.page.locator('input[type="file"]');
    await fileInput.setInputFiles(filePath);
    await this.page.waitForTimeout(500);
  }

  /**
   * Submit event form
   */
  async submit(): Promise<void> {
    await this.submitButton.click();
    await this.page.waitForLoadState('networkidle', { timeout: 10000 }).catch(() => {});
  }

  /**
   * Check if form has validation errors
   */
  async hasErrors(): Promise<boolean> {
    return await this.errorMessages.count() > 0;
  }

  /**
   * Get error messages
   */
  async getErrors(): Promise<string[]> {
    return await this.errorMessages.allTextContents();
  }
}

/**
 * Event Detail Page Object (React with Tabs and RSVP buttons)
 *
 * The React event detail page uses:
 * - GlassCard for main content
 * - HeroUI Tabs for Details/Attendees/Check-in sections
 * - RSVP buttons (Going, Interested, Not Going)
 * - Breadcrumbs navigation
 */
export class EventDetailPage extends BasePage {
  readonly pageHeading: Locator;
  readonly breadcrumbs: Locator;

  // Event info
  readonly title: Locator;
  readonly description: Locator;
  readonly dateTime: Locator;
  readonly location: Locator;
  readonly organizer: Locator;
  readonly categoryChip: Locator;

  // Attendee info
  readonly attendeeCount: Locator;
  readonly interestedCount: Locator;

  // RSVP buttons
  readonly goingButton: Locator;
  readonly interestedButton: Locator;
  readonly notGoingButton: Locator;
  readonly rsvpStatusChip: Locator;

  // Tabs
  readonly detailsTab: Locator;
  readonly attendeesTab: Locator;
  readonly checkinTab: Locator;

  // Actions
  readonly editButton: Locator;
  readonly archiveButton: Locator;
  readonly manageButton: Locator;
  readonly shareButton: Locator;

  // Attendees list
  readonly attendeesList: Locator;
  readonly attendeeItems: Locator;

  constructor(page: Page, tenant?: string) {
    super(page, tenant);

    this.pageHeading = page.locator('h1');
    this.breadcrumbs = page.locator('nav[aria-label="Breadcrumb"]');

    // Event info
    this.title = page.locator('h1').first();
    this.description = page.getByRole('heading', { name: 'About this event' }).locator('..');
    this.dateTime = page.locator('time').first();
    this.location = page.getByText('Location', { exact: true }).locator('..');
    this.organizer = page.getByRole('heading', { name: 'Organized by' }).locator('..').locator('span.text-theme-primary');
    this.categoryChip = page.locator('[class*="chip"]').filter({ hasText: /Workshop|Social|Outdoor|Online/ }).first();

    // Attendee counts
    this.attendeeCount = page.getByText('going', { exact: true }).locator('../..').getByText(/^\d+$/);
    this.interestedCount = page.getByText('interested', { exact: true }).locator('../..').getByText(/^\d+$/);

    // RSVP buttons
    this.goingButton = page.locator('button:has-text("Going")');
    this.interestedButton = page.locator('button:has-text("Interested")');
    this.notGoingButton = page.locator('button:has-text("Not Going")');
    this.rsvpStatusChip = page.locator('[class*="chip"]:has-text("You\'re Going"), [class*="chip"]:has-text("You\'re Interested")');

    // Tabs
    this.detailsTab = page.getByRole('tab', { name: 'Details' });
    this.attendeesTab = page.getByRole('tab', { name: /^Attendees/ });
    this.checkinTab = page.getByRole('tab', { name: /^Check-in/ });

    // Actions
    this.editButton = page.locator('a[href*="/edit"], button:has-text("Edit")').first();
    this.archiveButton = page.getByRole('button', { name: /^Archive / });
    this.manageButton = page.getByRole('link', { name: /^Manage / });
    this.shareButton = page.getByRole('button', { name: /^Copy link to / });

    // Attendees
    this.attendeesList = page.getByRole('heading', { name: 'No attendees yet' }).or(
      page.locator('div.grid').filter({ has: page.locator('p.text-theme-primary') }).first()
    );
    this.attendeeItems = this.page.locator('.bg-theme-elevated').filter({ has: page.locator('img[alt], .avatar') });
  }

  /**
   * Navigate to event detail
   */
  async navigateToEvent(id: number | string): Promise<void> {
    await this.goto(`events/${id}`);
  }

  /**
   * Wait for event detail to load
   */
  async waitForLoad(): Promise<void> {
    await this.page.waitForLoadState('domcontentloaded');
    await this.title.waitFor({ state: 'visible', timeout: 15000 }).catch(() => {});
  }

  /**
   * RSVP to event as "Going"
   */
  async rsvpGoing(): Promise<void> {
    await this.goingButton.click();
    await this.page.waitForTimeout(500);
  }

  /**
   * RSVP to event as "Interested"
   */
  async rsvpInterested(): Promise<void> {
    await this.interestedButton.click();
    await this.page.waitForTimeout(500);
  }

  /**
   * Mark as "Not Going"
   */
  async rsvpNotGoing(): Promise<void> {
    await this.notGoingButton.click();
    await this.page.waitForTimeout(500);
  }

  /**
   * Get event title
   */
  async getEventTitle(): Promise<string> {
    return (await this.title.textContent())?.trim() || '';
  }

  /**
   * Get attendee count (going)
   */
  async getAttendeeCount(): Promise<number> {
    const countText = await this.attendeeCount.textContent() || '0';
    const match = countText.match(/(\d+)/);
    return match ? parseInt(match[1], 10) : 0;
  }

  /**
   * Get interested count
   */
  async getInterestedCount(): Promise<number> {
    if (await this.interestedCount.count() === 0) return 0;
    const countText = await this.interestedCount.textContent() || '0';
    const match = countText.match(/(\d+)/);
    return match ? parseInt(match[1], 10) : 0;
  }

  /**
   * Check if user has RSVP'd
   */
  async hasRsvpd(): Promise<boolean> {
    return await this.rsvpStatusChip.count() > 0;
  }

  /**
   * Check if current user can edit
   */
  async canEdit(): Promise<boolean> {
    return await this.editButton.isVisible();
  }

  /**
   * Switch to Attendees tab
   */
  async switchToAttendeesTab(): Promise<void> {
    await this.attendeesTab.click();
    await this.page.waitForTimeout(300);
  }

  /**
   * Get number of visible attendees in list
   */
  async getVisibleAttendeeCount(): Promise<number> {
    await this.switchToAttendeesTab();
    return await this.attendeeItems.count();
  }

  /**
   * Share event (copy link)
   */
  async share(): Promise<void> {
    await this.shareButton.click();
    await this.page.waitForTimeout(300);
  }
}
