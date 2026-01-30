import { Page, Locator, expect } from '@playwright/test';
import { BasePage } from './BasePage';

/**
 * Groups/Hubs List Page Object
 */
export class GroupsPage extends BasePage {
  readonly groupCards: Locator;
  readonly searchInput: Locator;
  readonly createGroupButton: Locator;
  readonly myGroupsTab: Locator;
  readonly allGroupsTab: Locator;
  readonly categoryFilter: Locator;
  readonly noGroupsMessage: Locator;

  constructor(page: Page, tenant?: string) {
    super(page, tenant);

    this.groupCards = page.locator('.groups-grid .glass-group-card, .glass-group-card, .group-card, .hub-card, [data-group], article[class*="group"]');
    this.searchInput = page.locator('#groups-search, .glass-search-input, input[name="q"], input[name="search"], input[placeholder*="Search"]');
    this.createGroupButton = page.locator('a[href*="groups/create"], a[href*="create-group"], .glass-btn-primary:has-text("Create"), button:has-text("Create"), a:has-text("Create Group"), a:has-text("Create Hub")');
    this.myGroupsTab = page.locator('a[href*="my-groups"], a[href*="my-hubs"], [data-tab="my-groups"]');
    this.allGroupsTab = page.locator('a[href*="groups"]:not([href*="my-groups"]), [data-tab="all"]');
    this.categoryFilter = page.locator('.glass-select, select[name="type"], select[name="category"], select[name="category_id"]');
    this.noGroupsMessage = page.locator('.empty-state, .no-groups, .no-results');
  }

  /**
   * Navigate to groups page
   */
  async navigate(): Promise<void> {
    await this.goto('groups');
  }

  /**
   * Navigate to my groups
   */
  async navigateToMyGroups(): Promise<void> {
    await this.goto('groups/my-groups');
  }

  /**
   * Get number of visible groups
   */
  async getGroupCount(): Promise<number> {
    return await this.groupCards.count();
  }

  /**
   * Search for groups
   */
  async searchGroups(query: string): Promise<void> {
    await this.searchInput.fill(query);
    await this.searchInput.press('Enter');
    await this.page.waitForLoadState('domcontentloaded');
  }

  /**
   * Click on a group
   */
  async clickGroup(index: number = 0): Promise<void> {
    await this.groupCards.nth(index).click();
    await this.page.waitForLoadState('domcontentloaded');
  }

  /**
   * Click create group button
   */
  async clickCreateGroup(): Promise<void> {
    await this.createGroupButton.click();
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
   * Get group names
   */
  async getGroupNames(): Promise<string[]> {
    const names = await this.groupCards.locator('.group-name, h3, h4').allTextContents();
    return names.map(n => n.trim());
  }
}

/**
 * Group Detail Page Object
 */
export class GroupDetailPage extends BasePage {
  readonly groupName: Locator;
  readonly description: Locator;
  readonly memberCount: Locator;
  readonly joinButton: Locator;
  readonly leaveButton: Locator;
  readonly discussionsList: Locator;
  readonly createPostButton: Locator;
  readonly createDiscussionButton: Locator;
  readonly membersTab: Locator;
  readonly discussionsTab: Locator;
  readonly eventsTab: Locator;
  readonly settingsButton: Locator;
  readonly inviteButton: Locator;

  constructor(page: Page, tenant?: string) {
    super(page, tenant);

    this.groupName = page.locator('h1, .group-title');
    this.description = page.locator('.group-description, .description');
    this.memberCount = page.locator('.member-count, [data-member-count]');
    this.joinButton = page.locator('.join-btn, button:has-text("Join"), [data-join]');
    this.leaveButton = page.locator('.leave-btn, button:has-text("Leave"), [data-leave]');
    this.discussionsList = page.locator('.discussion, .discussion-item, [data-discussion]');
    this.createPostButton = page.locator('a[href*="post"], .create-post-btn');
    this.createDiscussionButton = page.locator('a[href*="discussions/create"], .create-discussion-btn');
    this.membersTab = page.locator('[data-tab="members"], a[href*="members"]');
    this.discussionsTab = page.locator('[data-tab="discussions"], a[href*="discussions"]');
    this.eventsTab = page.locator('[data-tab="events"], a[href*="events"]');
    this.settingsButton = page.locator('.settings-btn, a[href*="edit"]');
    this.inviteButton = page.locator('.invite-btn, a[href*="invite"]');
  }

  /**
   * Navigate to group detail
   */
  async navigateToGroup(id: number | string): Promise<void> {
    await this.goto(`groups/${id}`);
  }

  /**
   * Join the group
   */
  async join(): Promise<void> {
    await this.joinButton.click();
    await this.page.waitForLoadState('domcontentloaded');
  }

  /**
   * Leave the group
   */
  async leave(): Promise<void> {
    await this.leaveButton.click();
    // Handle confirmation if present
    const confirmButton = this.page.locator('.confirm-leave, [data-confirm]');
    if (await confirmButton.isVisible()) {
      await confirmButton.click();
    }
    await this.page.waitForLoadState('domcontentloaded');
  }

  /**
   * Check if user is a member
   */
  async isMember(): Promise<boolean> {
    return await this.leaveButton.isVisible();
  }

  /**
   * Get group name
   */
  async getGroupName(): Promise<string> {
    return await this.groupName.textContent() || '';
  }

  /**
   * Get member count
   */
  async getMemberCount(): Promise<number> {
    const countText = await this.memberCount.textContent() || '0';
    return parseInt(countText.replace(/\D/g, ''), 10);
  }

  /**
   * Click create post
   */
  async clickCreatePost(): Promise<void> {
    await this.createPostButton.click();
    await this.page.waitForLoadState('domcontentloaded');
  }

  /**
   * Click create discussion
   */
  async clickCreateDiscussion(): Promise<void> {
    await this.createDiscussionButton.click();
    await this.page.waitForLoadState('domcontentloaded');
  }

  /**
   * Get number of discussions
   */
  async getDiscussionCount(): Promise<number> {
    return await this.discussionsList.count();
  }

  /**
   * Click on discussions tab
   */
  async goToDiscussionsTab(): Promise<void> {
    await this.discussionsTab.click();
    await this.page.waitForLoadState('domcontentloaded');
  }

  /**
   * Click on members tab
   */
  async goToMembersTab(): Promise<void> {
    await this.membersTab.click();
    await this.page.waitForLoadState('domcontentloaded');
  }

  /**
   * Click invite button
   */
  async clickInvite(): Promise<void> {
    await this.inviteButton.click();
    await this.page.waitForLoadState('domcontentloaded');
  }
}

/**
 * Create Group Page Object
 */
export class CreateGroupPage extends BasePage {
  readonly nameInput: Locator;
  readonly descriptionInput: Locator;
  readonly categorySelect: Locator;
  readonly privacySelect: Locator;
  readonly imageUpload: Locator;
  readonly submitButton: Locator;

  constructor(page: Page, tenant?: string) {
    super(page, tenant);

    this.nameInput = page.locator('input[name="name"], input[name="title"]');
    this.descriptionInput = page.locator('textarea[name="description"]');
    this.categorySelect = page.locator('select[name="category_id"]');
    this.privacySelect = page.locator('select[name="privacy"], select[name="visibility"]');
    this.imageUpload = page.locator('input[type="file"]');
    this.submitButton = page.locator('button[type="submit"]');
  }

  /**
   * Navigate to create group page
   */
  async navigate(): Promise<void> {
    await this.goto('create-group');
  }

  /**
   * Fill group creation form
   */
  async fillForm(data: {
    name: string;
    description: string;
    category?: string;
    privacy?: 'public' | 'private' | 'hidden';
  }): Promise<void> {
    await this.nameInput.fill(data.name);
    await this.descriptionInput.fill(data.description);

    if (data.category) {
      await this.categorySelect.selectOption({ label: data.category });
    }

    if (data.privacy) {
      await this.privacySelect.selectOption(data.privacy);
    }
  }

  /**
   * Submit group creation form
   */
  async submit(): Promise<void> {
    await this.submitButton.click();
    await this.page.waitForLoadState('domcontentloaded');
  }

  /**
   * Create a group with full flow
   */
  async createGroup(data: {
    name: string;
    description: string;
    category?: string;
    privacy?: 'public' | 'private' | 'hidden';
  }): Promise<void> {
    await this.fillForm(data);
    await this.submit();
  }
}
