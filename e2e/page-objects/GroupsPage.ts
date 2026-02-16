import { Page, Locator, expect } from '@playwright/test';
import { BasePage } from './BasePage';

/**
 * Groups List Page Object (React with GlassCard and HeroUI components)
 *
 * The React groups page uses:
 * - GlassCard for group cards and search bar
 * - HeroUI Select for filter (All Groups, My Groups, Public, Private)
 * - Avatar/AvatarGroup for member previews
 * - Load More pagination
 */
export class GroupsPage extends BasePage {
  readonly pageHeading: Locator;
  readonly createGroupButton: Locator;

  // Search and filters
  readonly searchCard: Locator;
  readonly searchInput: Locator;
  readonly filterSelect: Locator;

  // Group cards
  readonly groupCards: Locator;
  readonly noGroupsMessage: Locator;

  // Load more
  readonly loadMoreButton: Locator;

  constructor(page: Page, tenant?: string) {
    super(page, tenant);

    this.pageHeading = page.locator('h1:has-text("Groups")');
    this.createGroupButton = page.locator('a[href*="/groups/create"], button:has-text("Create Group")').first();

    // Search card
    this.searchCard = page.locator('[class*="glass"]').filter({ has: page.locator('input[placeholder*="Search groups"]') });
    this.searchInput = page.locator('input[placeholder*="Search groups"]');
    this.filterSelect = page.locator('button[aria-haspopup="listbox"]').filter({ hasText: /All Groups|My Groups|Public|Private|Filter/ });

    // Group cards - article or GlassCard with group content
    this.groupCards = page.locator('article').filter({ has: page.locator('h3, .avatar-group') });
    this.noGroupsMessage = page.locator('text=/No groups found|No groups/');

    // Pagination
    this.loadMoreButton = page.locator('button:has-text("Load More")');
  }

  /**
   * Navigate to groups page
   */
  async navigate(): Promise<void> {
    await this.goto('groups');
  }

  /**
   * Wait for groups page to load
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
   * Get number of visible group cards
   */
  async getGroupCount(): Promise<number> {
    return await this.groupCards.count();
  }

  /**
   * Search for groups
   */
  async searchGroups(query: string): Promise<void> {
    await this.searchInput.fill(query);
    await this.page.waitForTimeout(500); // Debounce
  }

  /**
   * Filter groups by type
   */
  async filterByType(filter: 'all' | 'joined' | 'public' | 'private'): Promise<void> {
    // Click the Select button to open dropdown
    await this.filterSelect.click();
    await this.page.waitForTimeout(200);

    // Click the option from the dropdown
    const filterText = filter === 'all' ? 'All Groups' : filter === 'joined' ? 'My Groups' : filter === 'public' ? 'Public' : 'Private';
    const option = this.page.locator(`li[role="option"]:has-text("${filterText}")`).first();
    await option.click();
    await this.page.waitForTimeout(500);
  }

  /**
   * Click on a group card
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
   * Check if no groups are shown
   */
  async hasNoGroups(): Promise<boolean> {
    return await this.noGroupsMessage.isVisible();
  }

  /**
   * Get group names from visible cards
   */
  async getGroupNames(): Promise<string[]> {
    const names = await this.groupCards.locator('h3').allTextContents();
    return names.map(n => n.trim());
  }

  /**
   * Check if search is available
   */
  async hasSearch(): Promise<boolean> {
    return await this.searchInput.count() > 0;
  }

  /**
   * Check if create button is available
   */
  async hasCreateButton(): Promise<boolean> {
    return await this.createGroupButton.count() > 0;
  }

  /**
   * Load more groups
   */
  async loadMore(): Promise<void> {
    await this.loadMoreButton.click();
    await this.page.waitForTimeout(1000);
  }
}

/**
 * Group Detail Page Object (React with Tabs)
 *
 * The React group detail page uses:
 * - GlassCard for main content
 * - HeroUI Tabs for different sections
 * - Join/Leave buttons
 * - Member avatars
 */
export class GroupDetailPage extends BasePage {
  readonly pageHeading: Locator;
  readonly breadcrumbs: Locator;

  // Group info
  readonly groupName: Locator;
  readonly description: Locator;
  readonly memberCount: Locator;
  readonly privacyBadge: Locator;

  // Actions
  readonly joinButton: Locator;
  readonly leaveButton: Locator;
  readonly settingsButton: Locator;

  // Tabs
  readonly discussionsTab: Locator;
  readonly membersTab: Locator;
  readonly eventsTab: Locator;

  // Members
  readonly membersList: Locator;
  readonly memberItems: Locator;

  constructor(page: Page, tenant?: string) {
    super(page, tenant);

    this.pageHeading = page.locator('h1');
    this.breadcrumbs = page.locator('nav[aria-label="Breadcrumb"]');

    // Group info
    this.groupName = page.locator('h1').first();
    this.description = page.locator('text=About').locator('..').locator('p');
    this.memberCount = page.locator('text=/\\d+ members?/');
    this.privacyBadge = page.locator('[class*="chip"]').filter({ hasText: /Public|Private/ });

    // Actions
    this.joinButton = page.locator('button:has-text("Join")');
    this.leaveButton = page.locator('button:has-text("Leave")');
    this.settingsButton = page.locator('button:has-text("Settings"), a[href*="/edit"]');

    // Tabs
    this.discussionsTab = page.locator('button[role="tab"]:has-text("Discussions")');
    this.membersTab = page.locator('button[role="tab"]:has-text("Members")');
    this.eventsTab = page.locator('button[role="tab"]:has-text("Events")');

    // Members
    this.membersList = page.locator('[key="members"]');
    this.memberItems = page.locator('.bg-theme-elevated').filter({ has: page.locator('img[alt], .avatar') });
  }

  /**
   * Navigate to group detail
   */
  async navigateToGroup(id: number | string): Promise<void> {
    await this.goto(`groups/${id}`);
  }

  /**
   * Wait for group detail to load
   */
  async waitForLoad(): Promise<void> {
    await this.page.waitForLoadState('domcontentloaded');
    await this.groupName.waitFor({ state: 'visible', timeout: 15000 }).catch(() => {});
  }

  /**
   * Join the group
   */
  async join(): Promise<void> {
    await this.joinButton.click();
    await this.page.waitForTimeout(500);
  }

  /**
   * Leave the group
   */
  async leave(): Promise<void> {
    await this.leaveButton.click();
    await this.page.waitForTimeout(500);
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
    return (await this.groupName.textContent())?.trim() || '';
  }

  /**
   * Get member count
   */
  async getMemberCount(): Promise<number> {
    const countText = await this.memberCount.textContent() || '0';
    const match = countText.match(/(\d+)/);
    return match ? parseInt(match[1], 10) : 0;
  }

  /**
   * Switch to Members tab
   */
  async switchToMembersTab(): Promise<void> {
    await this.membersTab.click();
    await this.page.waitForTimeout(300);
  }

  /**
   * Get number of visible members in list
   */
  async getVisibleMemberCount(): Promise<number> {
    await this.switchToMembersTab();
    return await this.memberItems.count();
  }

  /**
   * Check if current user can edit
   */
  async canEdit(): Promise<boolean> {
    return await this.settingsButton.isVisible();
  }
}

/**
 * Create Group Page Object (React with HeroUI Form)
 *
 * The React create/edit group page uses:
 * - HeroUI Input/Textarea components
 * - HeroUI Select for privacy setting
 * - Image upload with drag & drop
 */
export class CreateGroupPage extends BasePage {
  readonly pageHeading: Locator;

  // Form fields
  readonly nameInput: Locator;
  readonly descriptionTextarea: Locator;
  readonly privacySelect: Locator;

  // Image upload
  readonly imageUploadArea: Locator;
  readonly imagePreview: Locator;

  // Actions
  readonly submitButton: Locator;
  readonly cancelButton: Locator;

  // Validation
  readonly errorMessages: Locator;

  constructor(page: Page, tenant?: string) {
    super(page, tenant);

    this.pageHeading = page.locator('h1:has-text("Create"), h1:has-text("Edit")');

    // Form fields - use label parent traversal for HeroUI
    this.nameInput = page.locator('label:has-text("Group Name")').locator('..').locator('input').first();
    this.descriptionTextarea = page.locator('textarea[placeholder*="Describe"]').first();
    this.privacySelect = page.locator('button[aria-haspopup="listbox"]').filter({ hasText: /Public|Private|Privacy/ });

    // Image upload
    this.imageUploadArea = page.locator('text=Click to upload or drag and drop');
    this.imagePreview = page.locator('img[alt*="preview"]');

    // Actions
    this.submitButton = page.locator('button[type="submit"]:has-text("Create"), button[type="submit"]:has-text("Update")');
    this.cancelButton = page.locator('button:has-text("Cancel")');

    // Validation
    this.errorMessages = page.locator('[role="alert"], .error, [data-slot="error-message"]');
  }

  /**
   * Navigate to create group page
   */
  async navigate(): Promise<void> {
    await this.goto('groups/create');
  }

  /**
   * Wait for form to load
   */
  async waitForLoad(): Promise<void> {
    await this.page.waitForLoadState('domcontentloaded');
    await this.nameInput.waitFor({ state: 'visible', timeout: 10000 }).catch(() => {});
  }

  /**
   * Fill group form
   */
  async fillForm(data: {
    name: string;
    description: string;
    privacy?: 'public' | 'private';
  }): Promise<void> {
    await this.nameInput.fill(data.name);
    await this.descriptionTextarea.fill(data.description);

    if (data.privacy) {
      await this.privacySelect.selectOption(data.privacy);
    }
  }

  /**
   * Submit group form
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
}
