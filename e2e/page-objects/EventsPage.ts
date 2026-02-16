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

    // HeroUI Select uses button trigger, not <select> or role="combobox"
    this.timeFilterSelect = page.locator('button[aria-haspopup="listbox"]').filter({ hasText: /Upcoming|Past|All Events|Filter/ });

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
    // Click the Select button to open dropdown
    await this.timeFilterSelect.click();
    await this.page.waitForTimeout(200);

    // Click the option from the dropdown
    const filterText = filter === 'upcoming' ? 'Upcoming' : filter === 'past' ? 'Past' : 'All Events';
    const option = this.page.locator(`li[role="option"]:has-text("${filterText}")`).first();
    await option.click();
    await this.page.waitForTimeout(500);
  }

  /**
   * Filter events by category
   */
  async filterByCategory(category: string): Promise<void> {
    const chip = this.page.locator(`button:has-text("${category}")`).first();
    await chip.click();
    await this.page.waitForTimeout(500);
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
    this.titleInput = page.locator('label:has-text("Event Title")').locator('..').locator('input').first();
    this.categorySelect = page.locator('button[aria-haspopup="listbox"]').filter({ hasText: /Select a category|Category|Workshop|Social/ }).first();
    this.descriptionTextarea = page.locator('textarea[placeholder*="Describe"]').first();

    // Date/time inputs
    this.startDateInput = page.locator('input[type="date"]').first();
    this.startTimeInput = page.locator('input[type="time"]').first();
    this.endDateInput = page.locator('input[type="date"]').nth(1);
    this.endTimeInput = page.locator('input[type="time"]').nth(1);

    this.locationInput = page.locator('label:has-text("Location")').locator('..').locator('input').first();
    this.maxAttendeesInput = page.locator('label:has-text("Max Attendees")').locator('..').locator('input').first();

    // Image upload
    this.imageUploadArea = page.locator('text=Click to upload or drag and drop');
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
    await this.goto(`events/${id}/edit`);
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
      await this.categorySelect.selectOption(data.category);
    }
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
  readonly deleteButton: Locator;
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
    this.description = page.locator('text=About this event').locator('..').locator('p');
    this.dateTime = page.locator('time').first();
    this.location = page.locator('text=Location').locator('..').locator('div').filter({ hasText: /\w+/ }).last();
    this.organizer = page.locator('text=Organized by').locator('..').locator('span').filter({ hasText: /\w+ \w+/ });
    this.categoryChip = page.locator('[class*="chip"]').filter({ hasText: /Workshop|Social|Outdoor|Online/ }).first();

    // Attendee counts
    this.attendeeCount = page.locator('text=/\\d+ going/');
    this.interestedCount = page.locator('text=/\\d+ interested/');

    // RSVP buttons
    this.goingButton = page.locator('button:has-text("Going")');
    this.interestedButton = page.locator('button:has-text("Interested")');
    this.notGoingButton = page.locator('button:has-text("Not Going")');
    this.rsvpStatusChip = page.locator('[class*="chip"]:has-text("You\'re Going"), [class*="chip"]:has-text("You\'re Interested")');

    // Tabs
    this.detailsTab = page.locator('button[role="tab"]:has-text("Details")');
    this.attendeesTab = page.locator('button[role="tab"]:has-text("Attendees")');
    this.checkinTab = page.locator('button[role="tab"]:has-text("Check-in")');

    // Actions
    this.editButton = page.locator('a[href*="/edit"], button:has-text("Edit")').first();
    this.deleteButton = page.locator('button:has-text("Delete")');
    this.shareButton = page.locator('button:has-text("Share")');

    // Attendees
    this.attendeesList = page.locator('[key="attendees"]').or(
      page.locator('text=Attendees').locator('..').locator('..')
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
