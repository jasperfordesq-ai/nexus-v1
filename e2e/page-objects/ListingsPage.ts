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

    // React: GlassCard listing cards (article elements)
    this.listingCards = page.locator('article').filter({ has: page.locator('h3') });
    this.searchInput = page.locator('input[placeholder*="Search"]');
    // React: HeroUI Select components (button triggers, not <select> elements)
    this.filterButtons = page.locator('button[aria-haspopup="listbox"]');
    this.categoryFilter = page.locator('button[aria-haspopup="listbox"]').filter({ hasText: /Category|All Categories/ }).first();
    this.typeFilter = page.locator('button[aria-haspopup="listbox"]').filter({ hasText: /All Types|Offer|Request/ }).first();
    this.sortDropdown = page.locator('button[aria-haspopup="listbox"]').filter({ hasText: /Sort|Recent|Popular/ }).first();
    this.createListingButton = page.locator('a[href*="/listings/create"], button:has-text("Create")').first();
    this.loadMoreButton = page.locator('button:has-text("Load More")');
    this.noResultsMessage = page.locator('text=/No listings found|No listings/');
  }

  /**
   * Navigate to listings page
   */
  async navigate(): Promise<void> {
    await this.goto('listings');
  }

  /**
   * Wait for listings page to load
   */
  async waitForLoad(): Promise<void> {
    await this.page.waitForLoadState('domcontentloaded');
    await this.page.locator('[class*="glass"], article, text=No listings').first().waitFor({
      state: 'visible',
      timeout: 15000
    }).catch(() => {});
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
    // Click the Select button to open dropdown
    await this.typeFilter.click();
    await this.page.waitForTimeout(200);

    // Click the option from the dropdown
    const typeText = type === 'all' ? 'All Types' : type === 'offer' ? 'Offers' : 'Requests';
    const option = this.page.locator(`li[role="option"]:has-text("${typeText}")`).first();
    await option.click();
    await this.page.waitForTimeout(500);
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
