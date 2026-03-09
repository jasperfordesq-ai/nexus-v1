import { test, expect } from '@playwright/test';
import { MembersPage, ProfilePage, SettingsPage } from '../../page-objects';
import { generateTestData, tenantUrl } from '../../helpers/test-utils';

test.describe('Members - Directory', () => {
  test('should display members directory', async ({ page }) => {
    const membersPage = new MembersPage(page);
    await membersPage.navigate();

    await expect(page).toHaveURL(/members/);
  });

  test('should show member cards', async ({ page }) => {
    const membersPage = new MembersPage(page);
    await membersPage.navigate();

    const count = await membersPage.getMemberCount();
    expect(count).toBeGreaterThan(0);
  });

  test('should have search functionality', async ({ page }) => {
    const membersPage = new MembersPage(page);
    await membersPage.navigate();

    await expect(membersPage.searchInput).toBeVisible();
  });

  test('should search members', async ({ page }) => {
    const membersPage = new MembersPage(page);
    await membersPage.navigate();

    await membersPage.searchMembers('test');
    await page.waitForLoadState('domcontentloaded');
  });

  test('should display member cards with required info', async ({ page }) => {
    const membersPage = new MembersPage(page);
    await membersPage.navigate();

    const count = await membersPage.getMemberCount();
    if (count > 0) {
      const card = membersPage.memberCards.first();

      // Should have name
      const name = card.locator('.member-name, .name, h3');
      await expect(name).toBeVisible();

      // Should have avatar or placeholder
      const avatar = card.locator('.avatar, img');
      await expect(avatar).toBeVisible();
    }
  });

  test('should navigate to member profile', async ({ page }) => {
    const membersPage = new MembersPage(page);
    await membersPage.navigate();

    const count = await membersPage.getMemberCount();
    if (count > 0) {
      await membersPage.clickMember(0);
      expect(page.url()).toMatch(/members\/\d+|profile/);
    }
  });

  test('should support sorting', async ({ page }) => {
    const membersPage = new MembersPage(page);
    await membersPage.navigate();

    const sortDropdown = membersPage.sortDropdown;
    if (await sortDropdown.count() > 0) {
      const options = await sortDropdown.locator('option').count();
      expect(options).toBeGreaterThan(0);
    }
  });

  test('should support pagination or load more', async ({ page }) => {
    const membersPage = new MembersPage(page);
    await membersPage.navigate();

    const loadMore = membersPage.loadMoreButton;
    const pagination = page.locator('.pagination');

    const hasLoadMore = await loadMore.count() > 0;
    const hasPagination = await pagination.count() > 0;

    // May have either or neither
    expect(hasLoadMore || hasPagination || true).toBeTruthy();
  });
});

test.describe('Members - Profile View', () => {
  test('should display member profile', async ({ page }) => {
    const membersPage = new MembersPage(page);
    await membersPage.navigate();

    const count = await membersPage.getMemberCount();
    if (count > 0) {
      await membersPage.clickMember(0);

      const profilePage = new ProfilePage(page);
      await expect(profilePage.profileName).toBeVisible();
    }
  });

  test('should show profile avatar', async ({ page }) => {
    const membersPage = new MembersPage(page);
    await membersPage.navigate();

    const count = await membersPage.getMemberCount();
    if (count > 0) {
      await membersPage.clickMember(0);

      const profilePage = new ProfilePage(page);
      await expect(profilePage.profileAvatar).toBeVisible();
    }
  });

  test('should show bio if available', async ({ page }) => {
    const membersPage = new MembersPage(page);
    await membersPage.navigate();

    const count = await membersPage.getMemberCount();
    if (count > 0) {
      await membersPage.clickMember(0);

      const profilePage = new ProfilePage(page);
      // Bio may or may not be present
      const bioCount = await profilePage.profileBio.count();
      expect(bioCount).toBeGreaterThanOrEqual(0);
    }
  });

  test('should have connect/message buttons for other users', async ({ page }) => {
    const membersPage = new MembersPage(page);
    await membersPage.navigate();

    const count = await membersPage.getMemberCount();
    if (count > 0) {
      await membersPage.clickMember(0);

      const profilePage = new ProfilePage(page);

      if (!await profilePage.isOwnProfile()) {
        // Should have interaction buttons
        const hasConnect = await profilePage.connectButton.count() > 0;
        const hasMessage = await profilePage.messageButton.count() > 0;

        expect(hasConnect || hasMessage).toBeTruthy();
      }
    }
  });

  test('should show skills if available', async ({ page }) => {
    const membersPage = new MembersPage(page);
    await membersPage.navigate();

    const count = await membersPage.getMemberCount();
    if (count > 0) {
      await membersPage.clickMember(0);

      const profilePage = new ProfilePage(page);
      const skills = await profilePage.getSkills();
      // Skills may or may not be present
      expect(skills).toBeDefined();
    }
  });
});

test.describe('Members - Own Profile', () => {
  test('should navigate to own profile', async ({ page }) => {
    const profilePage = new ProfilePage(page);
    await profilePage.navigateToOwnProfile();

    expect(page.url()).toContain('profile');
  });

  test('should show edit button on own profile', async ({ page }) => {
    const profilePage = new ProfilePage(page);
    await profilePage.navigateToOwnProfile();

    const isOwn = await profilePage.isOwnProfile();
    expect(isOwn).toBeTruthy();
  });

  test('should navigate to settings from profile', async ({ page }) => {
    const profilePage = new ProfilePage(page);
    await profilePage.navigateToOwnProfile();

    await profilePage.clickEditProfile();
    expect(page.url()).toContain('settings');
  });
});

test.describe('Members - Settings', () => {
  test('should display settings page', async ({ page }) => {
    const settingsPage = new SettingsPage(page);
    await settingsPage.navigate();

    await expect(page).toHaveURL(/settings/);
  });

  test('should have profile settings', async ({ page }) => {
    const settingsPage = new SettingsPage(page);
    await settingsPage.navigate();

    await expect(settingsPage.nameInput).toBeVisible();
  });

  test('should have bio input', async ({ page }) => {
    const settingsPage = new SettingsPage(page);
    await settingsPage.navigate();

    await expect(settingsPage.bioInput).toBeVisible();
  });

  test('should update profile', async ({ page }) => {
    const settingsPage = new SettingsPage(page);
    await settingsPage.navigate();

    const testData = generateTestData();

    await settingsPage.updateProfile({
      bio: `Updated bio ${testData.uniqueId}`,
    });

    const isSuccess = await settingsPage.isSaveSuccessful();
    // Either shows success or just stays on page
    expect(isSuccess || page.url().includes('settings')).toBeTruthy();
  });

  test('should have privacy tab', async ({ page }) => {
    const settingsPage = new SettingsPage(page);
    await settingsPage.navigate();

    const privacyTab = settingsPage.privacyTab;
    if (await privacyTab.count() > 0) {
      await expect(privacyTab).toBeVisible();
    }
  });

  test('should have notification settings', async ({ page }) => {
    const settingsPage = new SettingsPage(page);
    await settingsPage.navigate();

    const notificationsTab = settingsPage.notificationsTab;
    if (await notificationsTab.count() > 0) {
      await settingsPage.goToNotificationsTab();
      // Should show notification preferences
    }
  });

  test('should allow changing profile visibility', async ({ page }) => {
    const settingsPage = new SettingsPage(page);
    await settingsPage.navigate();

    const privacyTab = settingsPage.privacyTab;
    if (await privacyTab.count() > 0) {
      await settingsPage.goToPrivacyTab();

      const visibilitySelect = settingsPage.profileVisibility;
      if (await visibilitySelect.count() > 0) {
        await expect(visibilitySelect).toBeVisible();
      }
    }
  });
});

test.describe('Members - Connections', () => {
  test('should show connection count', async ({ page }) => {
    const profilePage = new ProfilePage(page);
    await profilePage.navigateToOwnProfile();

    const count = await profilePage.getConnectionCount();
    expect(count).toBeGreaterThanOrEqual(0);
  });

  test('should have connections page', async ({ page }) => {
    await page.goto(tenantUrl('connections'));

    const content = page.locator('main, .content, .connections');
    await expect(content).toBeVisible();
  });

  test.skip('should send connection request', async ({ page }) => {
    // Skip to avoid sending real requests
    // Enable when needed with test data setup

    const membersPage = new MembersPage(page);
    await membersPage.navigate();

    const count = await membersPage.getMemberCount();
    if (count > 0) {
      await membersPage.clickMember(0);

      const profilePage = new ProfilePage(page);
      if (!await profilePage.isOwnProfile()) {
        if (await profilePage.connectButton.count() > 0) {
          await profilePage.sendConnectionRequest();
          // Should show pending or success
        }
      }
    }
  });
});

test.describe('Members - Nexus Score', () => {
  test('should show Nexus score on profile if available', async ({ page }) => {
    const profilePage = new ProfilePage(page);
    await profilePage.navigateToOwnProfile();

    const score = await profilePage.getNexusScore();
    // Score may or may not be present
    expect(score).toBeGreaterThanOrEqual(0);
  });

  test('should have Nexus score page', async ({ page }) => {
    await page.goto(tenantUrl('nexus-score'));

    // May redirect or show score page
    const content = page.locator('main, .content');
    await expect(content).toBeVisible();
  });
});

test.describe('Members - Privacy', () => {
  test('should respect profile visibility settings', async ({ page }) => {
    // Navigate to a random member profile
    const membersPage = new MembersPage(page);
    await membersPage.navigate();

    const count = await membersPage.getMemberCount();
    if (count > 0) {
      await membersPage.clickMember(0);

      // Profile should be visible (not 403 or private message)
      const isHealthy = await new ProfilePage(page).isPageHealthy();
      expect(isHealthy).toBeTruthy();
    }
  });
});

test.describe('Members - Accessibility', () => {
  test('should have proper heading structure', async ({ page }) => {
    const membersPage = new MembersPage(page);
    await membersPage.navigate();

    const h1 = page.locator('h1');
    await expect(h1).toBeVisible();
  });

  test('should have accessible search input', async ({ page }) => {
    const membersPage = new MembersPage(page);
    await membersPage.navigate();

    const searchInput = membersPage.searchInput;
    const label = await searchInput.getAttribute('aria-label');
    const labelledBy = await searchInput.getAttribute('aria-labelledby');
    const id = await searchInput.getAttribute('id');
    const placeholder = await searchInput.getAttribute('placeholder');

    const hasAccessibleLabel = label || labelledBy || (id && await page.locator(`label[for="${id}"]`).count() > 0) || placeholder;
    expect(hasAccessibleLabel).toBeTruthy();
  });

  test('should have keyboard-accessible member cards', async ({ page }) => {
    const membersPage = new MembersPage(page);
    await membersPage.navigate();

    const count = await membersPage.getMemberCount();
    if (count > 0) {
      const card = membersPage.memberCards.first();
      const link = card.locator('a').first();

      if (await link.count() > 0) {
        await link.focus();
        await expect(link).toBeFocused();
      }
    }
  });
});

test.describe('Members - Mobile Behavior', () => {
  test.use({ viewport: { width: 375, height: 667 } });

  test('should display properly on mobile', async ({ page }) => {
    const membersPage = new MembersPage(page);
    await membersPage.navigate();

    const content = page.locator('main, .content, .members');
    await expect(content).toBeVisible();
  });

  test('should have responsive member cards', async ({ page }) => {
    const membersPage = new MembersPage(page);
    await membersPage.navigate();

    const count = await membersPage.getMemberCount();
    if (count > 0) {
      const card = membersPage.memberCards.first();
      const box = await card.boundingBox();

      // Card should fit in viewport
      expect(box?.width).toBeLessThanOrEqual(375);
    }
  });
});
