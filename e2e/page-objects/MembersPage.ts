import { Page, Locator, expect } from '@playwright/test';
import { BasePage } from './BasePage';

/**
 * Members Directory Page Object
 */
export class MembersPage extends BasePage {
  readonly memberCards: Locator;
  readonly searchInput: Locator;
  readonly filterDropdown: Locator;
  readonly sortDropdown: Locator;
  readonly locationFilter: Locator;
  readonly skillsFilter: Locator;
  readonly noMembersMessage: Locator;
  readonly loadMoreButton: Locator;

  constructor(page: Page, tenant?: string) {
    super(page, tenant);

    this.memberCards = page.locator('#members-grid .glass-member-card, .glass-member-card, .member-card, .user-card, [data-member], article[class*="member"]');
    this.searchInput = page.locator('#member-search, .glass-search-input, input[name="search"], input[name="q"], input[placeholder*="Search"]');
    this.filterDropdown = page.locator('.glass-select, select[name="filter"], .filter-dropdown');
    this.sortDropdown = page.locator('select[name="sort"], .sort-dropdown');
    this.locationFilter = page.locator('select[name="location"], [data-filter="location"], .nexus-smart-btn:has-text("Nearby")');
    this.skillsFilter = page.locator('select[name="skills"], [data-filter="skills"]');
    this.noMembersMessage = page.locator('.glass-empty-state, .no-members, .empty-state, .no-results');
    this.loadMoreButton = page.locator('#infinite-scroll-trigger, .load-more, [data-load-more], button:has-text("Load more")');
  }

  /**
   * Navigate to members directory
   */
  async navigate(): Promise<void> {
    await this.goto('members');
  }

  /**
   * Get number of visible members
   */
  async getMemberCount(): Promise<number> {
    return await this.memberCards.count();
  }

  /**
   * Search for members
   */
  async searchMembers(query: string): Promise<void> {
    await this.searchInput.fill(query);
    await this.searchInput.press('Enter');
    await this.page.waitForLoadState('domcontentloaded');
  }

  /**
   * Click on a member
   */
  async clickMember(index: number = 0): Promise<void> {
    await this.memberCards.nth(index).click();
    await this.page.waitForLoadState('domcontentloaded');
  }

  /**
   * Filter by location
   */
  async filterByLocation(location: string): Promise<void> {
    await this.locationFilter.selectOption(location);
    await this.page.waitForLoadState('domcontentloaded');
  }

  /**
   * Sort members
   */
  async sortBy(option: string): Promise<void> {
    await this.sortDropdown.selectOption(option);
    await this.page.waitForLoadState('domcontentloaded');
  }

  /**
   * Load more members
   */
  async loadMore(): Promise<void> {
    if (await this.loadMoreButton.isVisible()) {
      await this.loadMoreButton.click();
      await this.page.waitForLoadState('domcontentloaded');
    }
  }

  /**
   * Get member names
   */
  async getMemberNames(): Promise<string[]> {
    const names = await this.memberCards.locator('.member-name, .name, h3').allTextContents();
    return names.map(n => n.trim());
  }
}

/**
 * Member Profile Page Object
 */
export class ProfilePage extends BasePage {
  readonly profileName: Locator;
  readonly profileBio: Locator;
  readonly profileAvatar: Locator;
  readonly connectButton: Locator;
  readonly messageButton: Locator;
  readonly skillsList: Locator;
  readonly listingsSection: Locator;
  readonly reviewsSection: Locator;
  readonly activityFeed: Locator;
  readonly badgesSection: Locator;
  readonly editProfileButton: Locator;
  readonly settingsButton: Locator;
  readonly connectionCount: Locator;
  readonly reviewCount: Locator;
  readonly nexusScore: Locator;

  constructor(page: Page, tenant?: string) {
    super(page, tenant);

    this.profileName = page.locator('h1, .profile-name');
    this.profileBio = page.locator('.profile-bio, .bio, .about');
    this.profileAvatar = page.locator('.profile-avatar, .avatar');
    this.connectButton = page.locator('.connect-btn, button:has-text("Connect"), [data-connect]');
    this.messageButton = page.locator('.message-btn, a[href*="messages"], button:has-text("Message")');
    this.skillsList = page.locator('.skills-list, .skills .skill, [data-skills]');
    this.listingsSection = page.locator('.profile-listings, [data-listings]');
    this.reviewsSection = page.locator('.profile-reviews, [data-reviews]');
    this.activityFeed = page.locator('.activity-feed, .recent-activity');
    this.badgesSection = page.locator('.badges-section, [data-badges]');
    this.editProfileButton = page.locator('a[href*="settings"], .edit-profile-btn');
    this.settingsButton = page.locator('a[href*="settings"], .settings-btn');
    this.connectionCount = page.locator('.connection-count, [data-connections]');
    this.reviewCount = page.locator('.review-count, [data-review-count]');
    this.nexusScore = page.locator('.nexus-score, [data-nexus-score]');
  }

  /**
   * Navigate to a member's profile
   */
  async navigateToProfile(id: number | string): Promise<void> {
    await this.goto(`members/${id}`);
  }

  /**
   * Navigate to own profile
   */
  async navigateToOwnProfile(): Promise<void> {
    await this.goto('profile/me');
  }

  /**
   * Get profile name
   */
  async getProfileName(): Promise<string> {
    return (await this.profileName.textContent())?.trim() || '';
  }

  /**
   * Get bio text
   */
  async getBio(): Promise<string> {
    return (await this.profileBio.textContent())?.trim() || '';
  }

  /**
   * Send connection request
   */
  async sendConnectionRequest(): Promise<void> {
    await this.connectButton.click();
    await this.page.waitForLoadState('domcontentloaded');
  }

  /**
   * Click message button
   */
  async clickMessage(): Promise<void> {
    await this.messageButton.click();
    await this.page.waitForLoadState('domcontentloaded');
  }

  /**
   * Get skills list
   */
  async getSkills(): Promise<string[]> {
    const skills = await this.skillsList.locator('.skill-tag, .skill-name').allTextContents();
    return skills.map(s => s.trim());
  }

  /**
   * Check if viewing own profile
   */
  async isOwnProfile(): Promise<boolean> {
    return await this.editProfileButton.isVisible();
  }

  /**
   * Click edit profile
   */
  async clickEditProfile(): Promise<void> {
    await this.editProfileButton.click();
    await this.page.waitForLoadState('domcontentloaded');
  }

  /**
   * Get connection count
   */
  async getConnectionCount(): Promise<number> {
    const text = await this.connectionCount.textContent() || '0';
    return parseInt(text.replace(/\D/g, ''), 10);
  }

  /**
   * Get Nexus score
   */
  async getNexusScore(): Promise<number> {
    const text = await this.nexusScore.textContent() || '0';
    return parseInt(text.replace(/\D/g, ''), 10);
  }
}

/**
 * Settings Page Object
 */
export class SettingsPage extends BasePage {
  readonly profileTab: Locator;
  readonly privacyTab: Locator;
  readonly notificationsTab: Locator;
  readonly passwordTab: Locator;

  // Profile settings
  readonly nameInput: Locator;
  readonly bioInput: Locator;
  readonly locationInput: Locator;
  readonly avatarUpload: Locator;

  // Privacy settings
  readonly profileVisibility: Locator;
  readonly messagePrivacy: Locator;
  readonly searchIndexing: Locator;

  // Notification settings
  readonly emailNotifications: Locator;
  readonly pushNotifications: Locator;
  readonly digestFrequency: Locator;

  readonly saveButton: Locator;
  readonly successMessage: Locator;

  constructor(page: Page, tenant?: string) {
    super(page, tenant);

    this.profileTab = page.locator('[data-tab="profile"], a[href*="settings/profile"]');
    this.privacyTab = page.locator('[data-tab="privacy"], a[href*="settings/privacy"]');
    this.notificationsTab = page.locator('[data-tab="notifications"], a[href*="settings/notifications"]');
    this.passwordTab = page.locator('[data-tab="password"], a[href*="settings/password"]');

    this.nameInput = page.locator('input[name="name"], input[name="display_name"]');
    this.bioInput = page.locator('textarea[name="bio"], textarea[name="about"]');
    this.locationInput = page.locator('input[name="location"]');
    this.avatarUpload = page.locator('input[type="file"][name="avatar"], input[type="file"]');

    this.profileVisibility = page.locator('select[name="privacy_profile"], [name="profile_visibility"]');
    this.messagePrivacy = page.locator('select[name="privacy_messages"], [name="message_privacy"]');
    this.searchIndexing = page.locator('input[name="search_indexed"], [name="allow_indexing"]');

    this.emailNotifications = page.locator('input[name="email_notifications"]');
    this.pushNotifications = page.locator('input[name="push_notifications"]');
    this.digestFrequency = page.locator('select[name="digest_frequency"]');

    this.saveButton = page.locator('button[type="submit"], .save-btn');
    this.successMessage = page.locator('.success, .alert-success');
  }

  /**
   * Navigate to settings page
   */
  async navigate(): Promise<void> {
    await this.goto('settings');
  }

  /**
   * Go to privacy tab
   */
  async goToPrivacyTab(): Promise<void> {
    await this.privacyTab.click();
    await this.page.waitForLoadState('domcontentloaded');
  }

  /**
   * Go to notifications tab
   */
  async goToNotificationsTab(): Promise<void> {
    await this.notificationsTab.click();
    await this.page.waitForLoadState('domcontentloaded');
  }

  /**
   * Update profile
   */
  async updateProfile(data: {
    name?: string;
    bio?: string;
    location?: string;
  }): Promise<void> {
    if (data.name) {
      await this.nameInput.fill(data.name);
    }
    if (data.bio) {
      await this.bioInput.fill(data.bio);
    }
    if (data.location) {
      await this.locationInput.fill(data.location);
    }

    await this.saveButton.click();
    await this.page.waitForLoadState('domcontentloaded');
  }

  /**
   * Change profile visibility
   */
  async setProfileVisibility(visibility: 'public' | 'members' | 'connections'): Promise<void> {
    await this.goToPrivacyTab();
    await this.profileVisibility.selectOption(visibility);
    await this.saveButton.click();
    await this.page.waitForLoadState('domcontentloaded');
  }

  /**
   * Check if save was successful
   */
  async isSaveSuccessful(): Promise<boolean> {
    return await this.successMessage.isVisible();
  }
}
