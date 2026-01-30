import { Page, Locator, expect } from '@playwright/test';
import { BasePage } from './BasePage';

/**
 * Events List Page Object
 */
export class EventsPage extends BasePage {
  readonly eventCards: Locator;
  readonly calendarView: Locator;
  readonly listView: Locator;
  readonly createEventButton: Locator;
  readonly filterButtons: Locator;
  readonly searchInput: Locator;
  readonly categoryFilter: Locator;
  readonly dateFilter: Locator;
  readonly noEventsMessage: Locator;

  constructor(page: Page, tenant?: string) {
    super(page, tenant);

    this.eventCards = page.locator('#eventsGrid .glass-event-card, .events-grid .glass-event-card, .glass-event-card, .event-card, [data-event]');
    this.calendarView = page.locator('.calendar-view, .fc, [data-view="calendar"], .fullcalendar, .fc-daygrid');
    this.listView = page.locator('.list-view, [data-view="list"], .events-list, #eventsGrid');
    // Create event button - can be /compose?type=event or /events/create or "Host Event" text
    this.createEventButton = page.locator('a[href*="compose?type=event"], a[href*="events/create"], .create-event-btn, a:has-text("Host Event"), a:has-text("Create Event"), .nexus-smart-btn:has-text("Host")');
    this.filterButtons = page.locator('.filter-btn, [data-filter], .tab-btn, .nexus-smart-btn');
    this.searchInput = page.locator('.glass-search-input, input[type="search"], input[name="search"], input[name="q"], input[placeholder*="Search"]');
    this.categoryFilter = page.locator('#event-category-filter, select[name="category"], select[name="category_id"], .glass-select');
    this.dateFilter = page.locator('#event-date-filter, select[name="date"], input[name="date"], input[type="date"], [data-date-filter]');
    this.noEventsMessage = page.locator('.glass-empty-state, .no-events, .empty-state, .no-results');
  }

  /**
   * Navigate to events page
   */
  async navigate(): Promise<void> {
    await this.goto('events');
  }

  /**
   * Navigate to calendar view
   */
  async navigateToCalendar(): Promise<void> {
    await this.goto('events/calendar');
  }

  /**
   * Get number of visible events
   */
  async getEventCount(): Promise<number> {
    return await this.eventCards.count();
  }

  /**
   * Search for events
   */
  async searchEvents(query: string): Promise<void> {
    await this.searchInput.fill(query);
    await this.searchInput.press('Enter');
    await this.page.waitForLoadState('domcontentloaded');
  }

  /**
   * Filter by category
   */
  async filterByCategory(category: string): Promise<void> {
    await this.categoryFilter.selectOption(category);
    await this.page.waitForLoadState('domcontentloaded');
  }

  /**
   * Click on an event
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
   * Get event titles
   */
  async getEventTitles(): Promise<string[]> {
    const titles = await this.eventCards.locator('.event-title, h3, h4').allTextContents();
    return titles.map(t => t.trim());
  }
}

/**
 * Create/Edit Event Page Object
 */
export class CreateEventPage extends BasePage {
  readonly titleInput: Locator;
  readonly descriptionInput: Locator;
  readonly startDateInput: Locator;
  readonly startTimeInput: Locator;
  readonly endDateInput: Locator;
  readonly endTimeInput: Locator;
  readonly locationInput: Locator;
  readonly categorySelect: Locator;
  readonly capacityInput: Locator;
  readonly isVirtualCheckbox: Locator;
  readonly virtualLinkInput: Locator;
  readonly imageUpload: Locator;
  readonly submitButton: Locator;

  constructor(page: Page, tenant?: string) {
    super(page, tenant);

    this.titleInput = page.locator('input[name="title"]');
    this.descriptionInput = page.locator('textarea[name="description"]');
    this.startDateInput = page.locator('input[name="start_date"], input[name="event_date"]');
    this.startTimeInput = page.locator('input[name="start_time"]');
    this.endDateInput = page.locator('input[name="end_date"]');
    this.endTimeInput = page.locator('input[name="end_time"]');
    this.locationInput = page.locator('input[name="location"], input[name="venue"]');
    this.categorySelect = page.locator('select[name="category_id"]');
    this.capacityInput = page.locator('input[name="capacity"], input[name="max_attendees"]');
    this.isVirtualCheckbox = page.locator('input[name="is_virtual"], input[name="online"]');
    this.virtualLinkInput = page.locator('input[name="virtual_link"], input[name="meeting_url"]');
    this.imageUpload = page.locator('input[type="file"]');
    this.submitButton = page.locator('button[type="submit"]');
  }

  /**
   * Navigate to create event page
   */
  async navigate(): Promise<void> {
    await this.goto('events/create');
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
    capacity?: string;
    isVirtual?: boolean;
    virtualLink?: string;
  }): Promise<void> {
    await this.titleInput.fill(data.title);
    await this.descriptionInput.fill(data.description);
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

    if (data.capacity) {
      await this.capacityInput.fill(data.capacity);
    }

    if (data.isVirtual) {
      await this.isVirtualCheckbox.check();
      if (data.virtualLink) {
        await this.virtualLinkInput.fill(data.virtualLink);
      }
    }
  }

  /**
   * Submit event form
   */
  async submit(): Promise<void> {
    await this.submitButton.click();
    await this.page.waitForLoadState('domcontentloaded');
  }
}

/**
 * Event Detail Page Object
 */
export class EventDetailPage extends BasePage {
  readonly title: Locator;
  readonly description: Locator;
  readonly dateTime: Locator;
  readonly location: Locator;
  readonly organizer: Locator;
  readonly rsvpButton: Locator;
  readonly attendeesList: Locator;
  readonly attendeeCount: Locator;
  readonly shareButton: Locator;
  readonly editButton: Locator;
  readonly deleteButton: Locator;
  readonly comments: Locator;

  constructor(page: Page, tenant?: string) {
    super(page, tenant);

    this.title = page.locator('h1, .event-title');
    this.description = page.locator('.event-description, .description');
    this.dateTime = page.locator('.event-datetime, .date-time');
    this.location = page.locator('.event-location, .location');
    this.organizer = page.locator('.organizer, [data-organizer]');
    this.rsvpButton = page.locator('.rsvp-btn, [data-rsvp], button:has-text("RSVP")');
    this.attendeesList = page.locator('.attendees-list, .attendee');
    this.attendeeCount = page.locator('.attendee-count, [data-attendee-count]');
    this.shareButton = page.locator('.share-btn, [data-share]');
    this.editButton = page.locator('a[href*="edit"], .edit-btn');
    this.deleteButton = page.locator('.delete-btn, [data-delete]');
    this.comments = page.locator('.comment, .comment-item');
  }

  /**
   * Navigate to event detail
   */
  async navigateToEvent(id: number | string): Promise<void> {
    await this.goto(`events/${id}`);
  }

  /**
   * RSVP to event
   */
  async rsvp(): Promise<void> {
    await this.rsvpButton.click();
    await this.page.waitForLoadState('domcontentloaded');
  }

  /**
   * Get event title
   */
  async getEventTitle(): Promise<string> {
    return await this.title.textContent() || '';
  }

  /**
   * Get attendee count
   */
  async getAttendeeCount(): Promise<number> {
    const countText = await this.attendeeCount.textContent() || '0';
    return parseInt(countText.replace(/\D/g, ''), 10);
  }

  /**
   * Check if user has RSVP'd
   */
  async hasRsvpd(): Promise<boolean> {
    const buttonText = await this.rsvpButton.textContent() || '';
    return buttonText.toLowerCase().includes('going') ||
           buttonText.toLowerCase().includes('cancel') ||
           buttonText.toLowerCase().includes('attending');
  }

  /**
   * Check if current user can edit
   */
  async canEdit(): Promise<boolean> {
    return await this.editButton.isVisible();
  }
}
