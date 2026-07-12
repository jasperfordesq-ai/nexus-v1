// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Page, Locator, expect } from '@playwright/test';
import { BasePage } from './BasePage';

/**
 * Groups List Page Object (React with GlassCard and HeroUI components)
 *
 * The React groups page uses:
 * - GlassCard for group cards and search bar
 * - HeroUI ToggleButtonGroup filters (All Groups, My Groups, Public, Private)
 * - Avatar/AvatarGroup for member previews
 * - Load More pagination
 */
export class GroupsPage extends BasePage {
  readonly pageHeading: Locator;
  readonly createGroupButton: Locator;

  // Search and filters
  readonly searchCard: Locator;
  readonly groupsSearchInput: Locator;
  readonly filterSelect: Locator;

  // Group cards
  readonly groupCards: Locator;
  readonly noGroupsMessage: Locator;

  // Load more
  readonly loadMoreButton: Locator;

  constructor(page: Page, tenant?: string) {
    super(page, tenant);

    this.pageHeading = page.getByRole('heading', { level: 1, name: 'Groups' });
    this.createGroupButton = page.getByRole('link', { name: 'Create Group', exact: true });

    // Search card
    this.searchCard = page.locator('[class*="glass"]').filter({ has: page.locator('input[placeholder*="Search groups"]') });
    this.groupsSearchInput = page.getByRole('searchbox', { name: 'Search groups...' });
    this.filterSelect = page.getByRole('radiogroup', { name: 'Group filters' });

    // Group cards - article or GlassCard with group content
    this.groupCards = page.locator('article').filter({ has: page.locator('h3, .avatar-group') });
    this.noGroupsMessage = page.getByRole('heading', { name: 'No groups found', exact: true });

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
    await expect(this.groupsSearchInput).toBeVisible({ timeout: 45000 });
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
    const response = this.page.waitForResponse((candidate) => {
      const url = new URL(candidate.url());
      return url.pathname.endsWith('/api/v2/groups') && url.searchParams.get('q') === query;
    });
    await this.groupsSearchInput.fill(query);
    await response;
  }

  /**
   * Filter groups by type
   */
  async filterByType(filter: 'all' | 'joined' | 'public' | 'private'): Promise<void> {
    const filterText = filter === 'all' ? 'All Groups' : filter === 'joined' ? 'My Groups' : filter === 'public' ? 'Public' : 'Private';
    const response = this.page.waitForResponse((candidate) => {
      const url = new URL(candidate.url());
      if (!url.pathname.endsWith('/api/v2/groups')) return false;
      if (filter === 'public' || filter === 'private') {
        return url.searchParams.get('visibility') === filter;
      }
      if (filter === 'joined') return url.searchParams.has('user_id');
      return !url.searchParams.has('visibility') && !url.searchParams.has('user_id');
    });
    await this.page.getByRole('radio', { name: filterText, exact: true }).click();
    await response;
  }

  /**
   * Click on a group card
   */
  async clickGroup(index: number = 0): Promise<void> {
    const links = this.page.locator('a[href*="/groups/"]').filter({ has: this.groupCards });
    const linkCount = await links.count();
    if (index < 0 || index >= linkCount) {
      throw new Error(`Group card index ${index} is out of range for ${linkCount} visible group links.`);
    }
    const link = links.nth(index);
    await Promise.all([
      this.page.waitForURL(/\/groups\/\d+/),
      link.click(),
    ]);
  }

  /**
   * Return the card whose visible heading exactly matches a deterministic fixture.
   */
  groupCardNamed(name: string): Locator {
    return this.groupCards.filter({
      has: this.page.getByRole('heading', { level: 3, name, exact: true }),
    });
  }

  /**
   * Return the link that owns the named card. The anchor wraps the article in
   * production, so it is an ancestor rather than a card descendant.
   */
  groupLinkNamed(name: string): Locator {
    return this.page.locator('a[href*="/groups/"]').filter({
      has: this.page.getByRole('heading', { level: 3, name, exact: true }),
    });
  }

  /**
   * Open a deterministic group by its exact visible name.
   */
  async openGroupNamed(name: string): Promise<void> {
    const link = this.groupLinkNamed(name);
    await expect(link).toHaveCount(1);
    await Promise.all([
      this.page.waitForURL(/\/groups\/\d+/),
      link.click(),
    ]);
  }

  /**
   * Click create group button
   */
  async clickCreateGroup(): Promise<void> {
    await Promise.all([
      this.page.waitForURL(/\/groups\/create$/),
      this.createGroupButton.click(),
    ]);
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
    return await this.groupsSearchInput.count() > 0;
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

  /**
   * Navigate to "My Groups" by applying the filter
   * There is no separate /groups/mine URL — it's a filter on the groups page
   */
  async navigateToMyGroups(): Promise<void> {
    await this.navigate();
    await this.waitForLoad();
    await this.filterByType('joined');
  }

  /**
   * Full flow shortcut: navigate to create page, fill form, submit
   */
  async createGroup(data: {
    name: string;
    description: string;
    visibility?: 'public' | 'private' | 'secret';
  }): Promise<void> {
    await this.clickCreateGroup();
    await this.page.waitForLoadState('domcontentloaded');
    const createPage = new CreateGroupPage(this.page, this.tenant);
    await createPage.waitForLoad();
    await createPage.fillForm(data);
    await createPage.submit();
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
  readonly inviteButton: Locator;

  // Tabs
  readonly discussionsTab: Locator;
  readonly membersTab: Locator;
  readonly eventsTab: Locator;

  // Feed and discussions
  readonly discussionsList: Locator;
  readonly createDiscussionButton: Locator;
  readonly createPostButton: Locator;

  // Members
  readonly membersList: Locator;
  readonly memberItems: Locator;

  constructor(page: Page, tenant?: string) {
    super(page, tenant);

    this.pageHeading = page.locator('h1');
    this.breadcrumbs = page.locator('nav[aria-label="Breadcrumb"]');

    // Group info
    this.groupName = page.getByRole('heading', { level: 1 }).first();
    this.description = page.locator('text=About').locator('..').locator('p');
    this.memberCount = page.getByText(/^\d+ members?$/).first();
    this.privacyBadge = page.locator('[class*="chip"]').filter({ hasText: /Public|Private/ });

    // Actions
    this.joinButton = page.getByRole('button', { name: 'Join Group', exact: true }).first();
    this.leaveButton = page.getByRole('button', { name: 'Leave Group', exact: true }).first();
    this.settingsButton = page.getByRole('button', { name: 'Settings', exact: true });
    this.inviteButton = page.getByRole('button', { name: /^Invite Members$/i });

    // Tabs
    this.discussionsTab = page.getByRole('tab', { name: /^Discussions?$/i });
    this.membersTab = page.getByRole('tab', { name: /^Members$/i });
    this.eventsTab = page.getByRole('tab', { name: /^Events$/i });

    // Feed and discussions
    this.discussionsList = page.getByRole('tabpanel', { name: /^Discussion$/i });
    this.createDiscussionButton = page.getByRole('button', {
      name: /^New Discussion$/i,
    });
    this.createPostButton = page.getByRole('button', {
      name: /^Create a group post$/i,
    });

    // Members
    this.membersList = page.getByRole('tabpanel', { name: /^Members$/i });
    this.memberItems = this.membersList.locator('a[href*="/profile/"]');
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
    await expect(this.groupName).toBeVisible({ timeout: 45000 });
  }

  /**
   * Join the group
   */
  async join(): Promise<void> {
    await this.joinButton.click();
    await expect(this.leaveButton).toBeVisible({ timeout: 10000 });
  }

  /**
   * Leave the group
   */
  async leave(): Promise<void> {
    await this.leaveButton.click();
    const dialog = this.page.getByRole('dialog', { name: 'Leave Group' });
    await expect(dialog).toBeVisible();
    await dialog.getByRole('button', { name: 'Leave Group', exact: true }).click();
    await expect(this.joinButton).toBeVisible({ timeout: 10000 });
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
    await this.switchToSection('Members', 'members');
  }

  /**
   * Switch a detail section through the desktop tablist or mobile dropdown.
   */
  async switchToSection(label: string, key: string): Promise<void> {
    const desktopTab = this.page.getByRole('tab', { name: label, exact: true });
    if (await desktopTab.isVisible()) {
      await desktopTab.click();
    } else {
      const mobileTrigger = this.page.getByRole('button', { name: /^Group navigation:/ });
      await mobileTrigger.click();
      await this.page
        .locator('[role="menuitem"], [role="menuitemradio"], [role="option"]')
        .filter({ hasText: label })
        .click();
    }
    await expect.poll(() => new URL(this.page.url()).searchParams.get('tab')).toBe(key);
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
 * - HeroUI Select for the capability-aware visibility setting
 * - Image upload with drag & drop
 */
export class CreateGroupPage extends BasePage {
  readonly pageHeading: Locator;

  // Form fields
  readonly nameInput: Locator;
  readonly descriptionTextarea: Locator;
  readonly visibilitySelect: Locator;

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

    this.pageHeading = page.getByRole('heading', { level: 1, name: /^(Create New|Edit) Group$/ });

    // Form fields — HeroUI Input does not use standard <label for=""> structure.
    // Match by placeholder text which is stable and unique to the field.
    this.nameInput = page.getByRole('textbox', { name: 'Group Name' });
    this.descriptionTextarea = page.getByRole('textbox', { name: 'Description' });
    // React Aria exposes the selected value before the translated field label
    // (for example, "Public Visibility"), so match the stable label portion.
    this.visibilitySelect = page.getByRole('button', { name: /Visibility/ });

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
    await expect(this.nameInput).toBeVisible({ timeout: 45000 });
  }

  /**
   * Fill group form
   */
  async fillForm(data: {
    name: string;
    description: string;
    visibility?: 'public' | 'private' | 'secret';
  }): Promise<void> {
    await this.nameInput.fill(data.name);
    await this.descriptionTextarea.fill(data.description);

    if (data.visibility) {
      await this.selectVisibility(data.visibility);
    }
  }

  async selectVisibility(visibility: 'public' | 'private' | 'secret'): Promise<void> {
    const label = visibility[0].toUpperCase() + visibility.slice(1);

    if (await this.visibilitySelect.textContent() !== label) {
      await this.visibilitySelect.click();
      await this.page.getByRole('option', { name: label, exact: true }).click();
    }

    await expect(this.visibilitySelect).toContainText(label);
  }

  /**
   * Submit group form
   */
  async submit(): Promise<void> {
    await this.submitButton.click();
  }

  /**
   * Full flow shortcut: fill form and submit
   */
  async createGroup(data: {
    name: string;
    description: string;
    visibility?: 'public' | 'private' | 'secret';
  }): Promise<void> {
    await this.fillForm(data);
    await this.submit();
  }

  /**
   * Check if form has validation errors
   */
  async hasErrors(): Promise<boolean> {
    return await this.errorMessages.count() > 0;
  }
}
