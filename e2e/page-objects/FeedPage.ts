import { Page, Locator } from '@playwright/test';
import { BasePage } from './BasePage';

/**
 * Feed Page Object (React with GlassCard components)
 *
 * The React feed uses:
 * - GlassCard components for posts
 * - HeroUI Button, Avatar, Input, Textarea, Dropdown components
 * - Modal for create post dialog
 * - Inline comments section that expands/collapses
 * - Filter chips for post types (all, posts, listings, events, polls, goals)
 */
export class FeedPage extends BasePage {
  // Main feed elements
  readonly pageHeading: Locator;
  readonly newPostButton: Locator;
  readonly quickPostBox: Locator;

  // Filter chips
  readonly filterChips: Locator;

  // Feed items (posts in GlassCards)
  readonly feedItems: Locator;

  // Create post modal
  readonly createPostModal: Locator;
  readonly createPostTextarea: Locator;
  readonly postModeTextChip: Locator;
  readonly postModePollChip: Locator;
  readonly submitPostButton: Locator;
  readonly cancelPostButton: Locator;

  // Poll creation (in modal)
  readonly pollQuestionInput: Locator;
  readonly pollOptionsInputs: Locator;
  readonly addPollOptionButton: Locator;

  // Empty state
  readonly emptyState: Locator;

  // Load more
  readonly loadMoreButton: Locator;

  constructor(page: Page, tenant?: string) {
    super(page, tenant);

    // Header elements
    this.pageHeading = page.locator('h1:has-text("Community Feed")');
    this.newPostButton = page.locator('button:has-text("New Post")');
    this.quickPostBox = page.locator('.cursor-pointer:has-text("What\'s on your mind?")');

    // Filter chips (buttons for all, posts, listings, events, polls, goals)
    this.filterChips = page.locator('button:has-text("All"), button:has-text("Posts"), button:has-text("Listings"), button:has-text("Events"), button:has-text("Polls"), button:has-text("Goals")');

    // Feed items - GlassCard components containing posts
    // Look for cards with avatar + content + like/comment buttons
    this.feedItems = page.locator('[class*="glass"]').filter({
      has: page.locator('button:has-text("Like"), button:has-text("Comment")')
    });

    // Create post modal
    this.createPostModal = page.locator('[role="dialog"]:has-text("Create Post")');
    this.createPostTextarea = page.locator('textarea[placeholder*="mind"]');
    this.postModeTextChip = this.createPostModal.locator('[class*="chip"]:has-text("Text")');
    this.postModePollChip = this.createPostModal.locator('[class*="chip"]:has-text("Poll")');
    this.submitPostButton = this.createPostModal.locator('button[type="submit"], button:has-text("Post"), button:has-text("Create Poll")');
    this.cancelPostButton = this.createPostModal.locator('button:has-text("Cancel")');

    // Poll creation
    this.pollQuestionInput = this.createPostModal.locator('input[placeholder*="question"]');
    this.pollOptionsInputs = this.createPostModal.locator('input[placeholder*="Option"]');
    this.addPollOptionButton = this.createPostModal.locator('button:has-text("Add Option")');

    // Empty state
    this.emptyState = page.locator('text=No posts yet, text=Be the first to share');

    // Load more
    this.loadMoreButton = page.locator('button:has-text("Load More")');
  }

  /**
   * Navigate to feed page
   */
  async navigate(): Promise<void> {
    await this.goto('feed');
  }

  /**
   * Wait for feed to load
   */
  async waitForLoad(): Promise<void> {
    await this.page.waitForLoadState('domcontentloaded');
    // Wait for either feed items or empty state
    await this.page.locator('[class*="glass"], text=No posts yet').first().waitFor({
      state: 'visible',
      timeout: 15000
    }).catch(() => {});
  }

  /**
   * Check if feed has posts
   */
  async hasPosts(): Promise<boolean> {
    return await this.feedItems.count() > 0;
  }

  /**
   * Get number of posts in feed
   */
  async getPostCount(): Promise<number> {
    return await this.feedItems.count();
  }

  /**
   * Open create post modal by clicking New Post button
   */
  async openCreatePostModal(): Promise<void> {
    await this.newPostButton.click();
    await this.createPostModal.waitFor({ state: 'visible' });
  }

  /**
   * Open create post modal by clicking quick post box
   */
  async clickQuickPostBox(): Promise<void> {
    await this.quickPostBox.click();
    await this.createPostModal.waitFor({ state: 'visible' });
  }

  /**
   * Create a text post
   */
  async createTextPost(content: string): Promise<void> {
    await this.openCreatePostModal();
    await this.createPostTextarea.fill(content);
    await this.submitPostButton.click();
    await this.page.waitForLoadState('networkidle', { timeout: 10000 }).catch(() => {});
  }

  /**
   * Create a poll
   */
  async createPoll(question: string, options: string[]): Promise<void> {
    await this.openCreatePostModal();

    // Switch to poll mode
    await this.postModePollChip.click();

    // Fill question
    await this.pollQuestionInput.fill(question);

    // Fill options (first 2 are always visible)
    const optionInputs = this.pollOptionsInputs;
    for (let i = 0; i < Math.min(options.length, 2); i++) {
      await optionInputs.nth(i).fill(options[i]);
    }

    // Add more options if needed
    for (let i = 2; i < options.length && i < 6; i++) {
      await this.addPollOptionButton.click();
      await optionInputs.nth(i).fill(options[i]);
    }

    await this.submitPostButton.click();
    await this.page.waitForLoadState('networkidle', { timeout: 10000 }).catch(() => {});
  }

  /**
   * Like a post by index
   */
  async likePost(index: number = 0): Promise<void> {
    const post = this.feedItems.nth(index);
    const likeButton = post.locator('button:has-text("Like")');
    await likeButton.click();
  }

  /**
   * Toggle comments section for a post
   */
  async toggleComments(index: number = 0): Promise<void> {
    const post = this.feedItems.nth(index);
    const commentButton = post.locator('button:has-text("Comment")');
    await commentButton.click();
    await this.page.waitForTimeout(300);
  }

  /**
   * Add comment to a post
   */
  async addComment(postIndex: number, commentText: string): Promise<void> {
    await this.toggleComments(postIndex);

    const post = this.feedItems.nth(postIndex);
    const commentInput = post.locator('input[placeholder*="comment"]');
    await commentInput.fill(commentText);

    // Press Enter or click send button
    await commentInput.press('Enter');
    await this.page.waitForLoadState('networkidle', { timeout: 5000 }).catch(() => {});
  }

  /**
   * Open post options menu (3-dot menu)
   */
  async openPostOptionsMenu(index: number = 0): Promise<void> {
    const post = this.feedItems.nth(index);
    const moreButton = post.locator('button[aria-label*="options"]');
    await moreButton.click();
    await this.page.waitForTimeout(200);
  }

  /**
   * Filter feed by type
   */
  async filterByType(type: 'all' | 'posts' | 'listings' | 'events' | 'polls' | 'goals'): Promise<void> {
    const filterButton = this.page.locator(`button:has-text("${type.charAt(0).toUpperCase() + type.slice(1)}")`);
    await filterButton.click();
    await this.page.waitForLoadState('domcontentloaded');
  }

  /**
   * Load more posts
   */
  async loadMore(): Promise<void> {
    await this.loadMoreButton.click();
    await this.page.waitForLoadState('networkidle', { timeout: 10000 }).catch(() => {});
  }

  /**
   * Vote on a poll option (for first post)
   */
  async voteOnPoll(postIndex: number, optionText: string): Promise<void> {
    const post = this.feedItems.nth(postIndex);
    const optionButton = post.locator(`button:has-text("${optionText}")`);
    await optionButton.click();
    await this.page.waitForTimeout(500);
  }

  /**
   * Check if a post has an author
   */
  async getPostAuthor(index: number): Promise<string | null> {
    const post = this.feedItems.nth(index);
    const author = post.locator('.font-semibold').first();
    return await author.textContent();
  }

  /**
   * Check if likes count is visible for a post
   */
  async getPostLikesCount(index: number): Promise<number> {
    const post = this.feedItems.nth(index);
    const likesText = post.locator('text=/\\d+ likes?/').first();
    const count = await likesText.count();
    if (count === 0) return 0;

    const text = await likesText.textContent();
    const match = text?.match(/(\d+)/);
    return match ? parseInt(match[1], 10) : 0;
  }

  /**
   * Check if comments count is visible for a post
   */
  async getPostCommentsCount(index: number): Promise<number> {
    const post = this.feedItems.nth(index);
    const commentsText = post.locator('text=/\\d+ comments?/').first();
    const count = await commentsText.count();
    if (count === 0) return 0;

    const text = await commentsText.textContent();
    const match = text?.match(/(\d+)/);
    return match ? parseInt(match[1], 10) : 0;
  }
}
