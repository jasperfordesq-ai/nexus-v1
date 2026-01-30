import { Page, Locator, expect } from '@playwright/test';
import { BasePage } from './BasePage';

/**
 * Listings Page Object - Service marketplace
 */
export class ListingsPage extends BasePage {
  readonly listingCards: Locator;
  readonly searchInput: Locator;
  readonly filterButtons: Locator;
  readonly categoryFilter: Locator;
  readonly typeFilter: Locator;
  readonly sortDropdown: Locator;
  readonly createListingButton: Locator;
  readonly loadMoreButton: Locator;
  readonly noResultsMessage: Locator;

  constructor(page: Page, tenant?: string) {
    super(page, tenant);

    this.listingCards = page.locator('#listings-grid .glass-listing-card, .glass-listing-card, .listing-card, .service-card, [data-listing], article[class*="listing"]');
    this.searchInput = page.locator('#listing-search, .glass-search-input, input[name="q"], input[name="search"], input[placeholder*="Search"]');
    this.filterButtons = page.locator('.filter-pill, .filter-btn, [data-filter], .tab-btn');
    this.categoryFilter = page.locator('.filter-pill.category, select[name="category"], select[name="category_id"], [data-filter="category"]');
    this.typeFilter = page.locator('.filter-pill.offer, .filter-pill.request, select[name="type"], [data-filter="type"], .type-filter');
    this.sortDropdown = page.locator('select[name="sort"], [data-sort], .sort-dropdown');
    // Create listing - either /compose?type=listing or /listings/create, or the compose prompt link
    this.createListingButton = page.locator('a[href*="compose?type=listing"], a[href*="listings/create"], .compose-prompt-link, .create-listing-btn, a:has-text("Create Listing"), a:has-text("Offer"), a:has-text("Request")');
    this.loadMoreButton = page.locator('.load-more, [data-load-more], button:has-text("Load more")');
    this.noResultsMessage = page.locator('.glass-empty-state, .no-results, .empty-state, .no-listings');
  }

  /**
   * Navigate to listings page
   */
  async navigate(): Promise<void> {
    await this.goto('listings');
  }

  /**
   * Get number of visible listings
   */
  async getListingCount(): Promise<number> {
    return await this.listingCards.count();
  }

  /**
   * Search for listings
   */
  async searchListings(query: string): Promise<void> {
    await this.searchInput.fill(query);
    await this.searchInput.press('Enter');
    await this.page.waitForLoadState('domcontentloaded');
  }

  /**
   * Filter by type (offer/request)
   */
  async filterByType(type: 'offer' | 'request' | 'all'): Promise<void> {
    if (await this.typeFilter.count() > 0) {
      await this.typeFilter.selectOption(type);
    } else {
      await this.filterButtons.filter({ hasText: type }).click();
    }
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
   * Sort listings
   */
  async sortBy(option: string): Promise<void> {
    await this.sortDropdown.selectOption(option);
    await this.page.waitForLoadState('domcontentloaded');
  }

  /**
   * Click on a listing card
   */
  async clickListing(index: number = 0): Promise<void> {
    await this.listingCards.nth(index).click();
    await this.page.waitForLoadState('domcontentloaded');
  }

  /**
   * Click create listing button
   */
  async clickCreateListing(): Promise<void> {
    await this.createListingButton.click();
    await this.page.waitForLoadState('domcontentloaded');
  }

  /**
   * Load more listings
   */
  async loadMore(): Promise<void> {
    if (await this.loadMoreButton.isVisible()) {
      await this.loadMoreButton.click();
      await this.page.waitForLoadState('domcontentloaded');
    }
  }

  /**
   * Check if no results are shown
   */
  async hasNoResults(): Promise<boolean> {
    return await this.noResultsMessage.isVisible();
  }

  /**
   * Get listing titles
   */
  async getListingTitles(): Promise<string[]> {
    const titles = await this.listingCards.locator('.listing-title, h3, h4').allTextContents();
    return titles.map(t => t.trim());
  }
}

/**
 * Create/Edit Listing Page Object
 */
export class CreateListingPage extends BasePage {
  readonly titleInput: Locator;
  readonly descriptionInput: Locator;
  readonly typeSelect: Locator;
  readonly categorySelect: Locator;
  readonly tagsInput: Locator;
  readonly locationInput: Locator;
  readonly durationInput: Locator;
  readonly imageUpload: Locator;
  readonly submitButton: Locator;
  readonly cancelButton: Locator;

  constructor(page: Page, tenant?: string) {
    super(page, tenant);

    this.titleInput = page.locator('input[name="title"]');
    this.descriptionInput = page.locator('textarea[name="description"], [name="description"]');
    this.typeSelect = page.locator('select[name="type"], input[name="type"]');
    this.categorySelect = page.locator('select[name="category_id"], select[name="category"]');
    this.tagsInput = page.locator('input[name="tags"], .tags-input');
    this.locationInput = page.locator('input[name="location"]');
    this.durationInput = page.locator('input[name="estimated_duration"]');
    this.imageUpload = page.locator('input[type="file"]');
    this.submitButton = page.locator('button[type="submit"], input[type="submit"]');
    this.cancelButton = page.locator('a[href*="listings"], .cancel-btn');
  }

  /**
   * Navigate to create listing page
   */
  async navigate(): Promise<void> {
    await this.goto('listings/create');
  }

  /**
   * Fill listing form
   */
  async fillForm(data: {
    title: string;
    description: string;
    type?: 'offer' | 'request';
    category?: string;
    tags?: string[];
    location?: string;
    duration?: string;
  }): Promise<void> {
    await this.titleInput.fill(data.title);
    await this.descriptionInput.fill(data.description);

    if (data.type) {
      const typeRadio = this.page.locator(`input[name="type"][value="${data.type}"]`);
      if (await typeRadio.count() > 0) {
        await typeRadio.check();
      } else {
        await this.typeSelect.selectOption(data.type);
      }
    }

    if (data.category) {
      await this.categorySelect.selectOption({ label: data.category });
    }

    if (data.location) {
      await this.locationInput.fill(data.location);
    }

    if (data.duration) {
      await this.durationInput.fill(data.duration);
    }
  }

  /**
   * Submit listing form
   */
  async submit(): Promise<void> {
    await this.submitButton.click();
    await this.page.waitForLoadState('domcontentloaded');
  }

  /**
   * Create a listing with full flow
   */
  async createListing(data: {
    title: string;
    description: string;
    type?: 'offer' | 'request';
    category?: string;
  }): Promise<void> {
    await this.fillForm(data);
    await this.submit();
  }
}

/**
 * Listing Detail Page Object
 */
export class ListingDetailPage extends BasePage {
  readonly title: Locator;
  readonly description: Locator;
  readonly authorInfo: Locator;
  readonly contactButton: Locator;
  readonly editButton: Locator;
  readonly deleteButton: Locator;
  readonly likeButton: Locator;
  readonly shareButton: Locator;
  readonly comments: Locator;
  readonly commentInput: Locator;

  constructor(page: Page, tenant?: string) {
    super(page, tenant);

    this.title = page.locator('h1, .listing-title');
    this.description = page.locator('.listing-description, .description');
    this.authorInfo = page.locator('.author-info, .user-card, [data-author]');
    this.contactButton = page.locator('.contact-btn, a[href*="messages"]');
    this.editButton = page.locator('a[href*="edit"], .edit-btn');
    this.deleteButton = page.locator('.delete-btn, [data-delete]');
    this.likeButton = page.locator('.like-btn, [data-like]');
    this.shareButton = page.locator('.share-btn, [data-share]');
    this.comments = page.locator('.comment, .comment-item');
    this.commentInput = page.locator('textarea[name="comment"], .comment-input');
  }

  /**
   * Navigate to a listing by ID
   */
  async navigateToListing(id: number | string): Promise<void> {
    await this.goto(`listings/${id}`);
  }

  /**
   * Get listing title
   */
  async getTitle(): Promise<string> {
    return await this.title.textContent() || '';
  }

  /**
   * Click contact/message button
   */
  async clickContact(): Promise<void> {
    await this.contactButton.click();
    await this.page.waitForLoadState('domcontentloaded');
  }

  /**
   * Like the listing
   */
  async like(): Promise<void> {
    await this.likeButton.click();
    await this.page.waitForLoadState('domcontentloaded');
  }

  /**
   * Add a comment
   */
  async addComment(text: string): Promise<void> {
    await this.commentInput.fill(text);
    await this.page.click('button[type="submit"]:near(.comment-input)');
    await this.page.waitForLoadState('domcontentloaded');
  }

  /**
   * Get comment count
   */
  async getCommentCount(): Promise<number> {
    return await this.comments.count();
  }

  /**
   * Check if current user can edit
   */
  async canEdit(): Promise<boolean> {
    return await this.editButton.isVisible();
  }
}
