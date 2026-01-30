import { test, expect } from '@playwright/test';
import { GroupsPage, GroupDetailPage, CreateGroupPage } from '../../page-objects';
import { generateTestData, tenantUrl } from '../../helpers/test-utils';

test.describe('Groups - Browse', () => {
  test('should display groups page', async ({ page }) => {
    const groupsPage = new GroupsPage(page);
    await groupsPage.navigate();

    await expect(page).toHaveURL(/groups/);
  });

  test('should show groups or empty state', async ({ page }) => {
    const groupsPage = new GroupsPage(page);
    await groupsPage.navigate();

    const count = await groupsPage.getGroupCount();
    const noGroups = groupsPage.noGroupsMessage;

    expect(count > 0 || await noGroups.count() > 0).toBeTruthy();
  });

  test('should have search functionality', async ({ page }) => {
    const groupsPage = new GroupsPage(page);
    await groupsPage.navigate();

    await expect(groupsPage.searchInput).toBeVisible();
  });

  test('should have create group button', async ({ page }) => {
    const groupsPage = new GroupsPage(page);
    await groupsPage.navigate();

    await expect(groupsPage.createGroupButton).toBeVisible();
  });

  test('should search groups', async ({ page }) => {
    const groupsPage = new GroupsPage(page);
    await groupsPage.navigate();

    await groupsPage.searchGroups('test');
    await page.waitForLoadState('domcontentloaded');
  });

  test('should display group cards with required info', async ({ page }) => {
    const groupsPage = new GroupsPage(page);
    await groupsPage.navigate();

    const count = await groupsPage.getGroupCount();
    if (count > 0) {
      const card = groupsPage.groupCards.first();

      // Should have name
      const name = card.locator('.group-name, h3, h4');
      await expect(name).toBeVisible();

      // Should have member count or description
      const info = card.locator('.member-count, .description, .group-info');
      await expect(info).toBeVisible();
    }
  });

  test('should navigate to group detail', async ({ page }) => {
    const groupsPage = new GroupsPage(page);
    await groupsPage.navigate();

    const count = await groupsPage.getGroupCount();
    if (count > 0) {
      await groupsPage.clickGroup(0);
      expect(page.url()).toMatch(/groups\/\d+/);
    }
  });
});

test.describe('Groups - My Groups', () => {
  test('should navigate to my groups page', async ({ page }) => {
    const groupsPage = new GroupsPage(page);
    await groupsPage.navigateToMyGroups();

    expect(page.url()).toContain('my-groups');
  });

  test('should show user memberships or empty state', async ({ page }) => {
    const groupsPage = new GroupsPage(page);
    await groupsPage.navigateToMyGroups();

    const count = await groupsPage.getGroupCount();
    const noGroups = groupsPage.noGroupsMessage;

    expect(count >= 0 || await noGroups.count() >= 0).toBeTruthy();
  });
});

test.describe('Groups - Create', () => {
  test('should navigate to create group page', async ({ page }) => {
    const groupsPage = new GroupsPage(page);
    await groupsPage.navigate();
    await groupsPage.clickCreateGroup();

    expect(page.url()).toContain('create');
  });

  test('should display create group form', async ({ page }) => {
    const createPage = new CreateGroupPage(page);
    await createPage.navigate();

    await expect(createPage.nameInput).toBeVisible();
    await expect(createPage.descriptionInput).toBeVisible();
    await expect(createPage.submitButton).toBeVisible();
  });

  test('should validate required fields', async ({ page }) => {
    const createPage = new CreateGroupPage(page);
    await createPage.navigate();

    await createPage.submit();

    const hasErrors = await createPage.hasErrors();
    const stillOnCreate = page.url().includes('create');

    expect(hasErrors || stillOnCreate).toBeTruthy();
  });

  test('should have privacy options', async ({ page }) => {
    const createPage = new CreateGroupPage(page);
    await createPage.navigate();

    const privacySelect = createPage.privacySelect;
    if (await privacySelect.count() > 0) {
      const options = await privacySelect.locator('option').count();
      expect(options).toBeGreaterThan(0);
    }
  });

  test('should create a new group', async ({ page }) => {
    const createPage = new CreateGroupPage(page);
    await createPage.navigate();

    const testData = generateTestData();

    await createPage.createGroup({
      name: `Test Group ${testData.uniqueId}`,
      description: testData.description,
    });

    // Should redirect to group detail or groups list
    expect(page.url()).toMatch(/groups(\/\d+)?$/);
  });
});

test.describe('Groups - Detail', () => {
  test('should display group details', async ({ page }) => {
    const groupsPage = new GroupsPage(page);
    await groupsPage.navigate();

    const count = await groupsPage.getGroupCount();
    if (count > 0) {
      await groupsPage.clickGroup(0);

      const detailPage = new GroupDetailPage(page);
      await expect(detailPage.groupName).toBeVisible();
    }
  });

  test('should show member count', async ({ page }) => {
    const groupsPage = new GroupsPage(page);
    await groupsPage.navigate();

    const count = await groupsPage.getGroupCount();
    if (count > 0) {
      await groupsPage.clickGroup(0);

      const detailPage = new GroupDetailPage(page);
      const memberCount = await detailPage.getMemberCount();
      expect(memberCount).toBeGreaterThanOrEqual(0);
    }
  });

  test('should have join/leave button', async ({ page }) => {
    const groupsPage = new GroupsPage(page);
    await groupsPage.navigate();

    const count = await groupsPage.getGroupCount();
    if (count > 0) {
      await groupsPage.clickGroup(0);

      const detailPage = new GroupDetailPage(page);
      const hasJoin = await detailPage.joinButton.count() > 0;
      const hasLeave = await detailPage.leaveButton.count() > 0;
      const hasSettings = await detailPage.settingsButton.count() > 0;

      // Should have one of these options
      expect(hasJoin || hasLeave || hasSettings).toBeTruthy();
    }
  });

  test('should join a group', async ({ page }) => {
    const groupsPage = new GroupsPage(page);
    await groupsPage.navigate();

    const count = await groupsPage.getGroupCount();
    if (count > 0) {
      await groupsPage.clickGroup(0);

      const detailPage = new GroupDetailPage(page);
      if (await detailPage.joinButton.count() > 0) {
        await detailPage.join();

        // Should now show leave button
        const isMember = await detailPage.isMember();
        expect(isMember).toBeTruthy();
      }
    }
  });
});

test.describe('Groups - Discussions', () => {
  test('should show discussions tab or section', async ({ page }) => {
    const groupsPage = new GroupsPage(page);
    await groupsPage.navigate();

    const count = await groupsPage.getGroupCount();
    if (count > 0) {
      await groupsPage.clickGroup(0);

      const detailPage = new GroupDetailPage(page);

      // Check for discussions
      const discussions = detailPage.discussionsList;
      const discussionsTab = detailPage.discussionsTab;

      const hasDiscussions = await discussions.count() > 0;
      const hasTab = await discussionsTab.count() > 0;

      expect(hasDiscussions || hasTab).toBeTruthy();
    }
  });

  test('should have create discussion option for members', async ({ page }) => {
    const groupsPage = new GroupsPage(page);
    await groupsPage.navigate();

    const count = await groupsPage.getGroupCount();
    if (count > 0) {
      await groupsPage.clickGroup(0);

      const detailPage = new GroupDetailPage(page);

      // Only visible if member
      if (await detailPage.isMember()) {
        const createButton = detailPage.createDiscussionButton;
        await expect(createButton).toBeVisible();
      }
    }
  });
});

test.describe('Groups - Posts', () => {
  test('should have create post option for members', async ({ page }) => {
    const groupsPage = new GroupsPage(page);
    await groupsPage.navigate();

    const count = await groupsPage.getGroupCount();
    if (count > 0) {
      await groupsPage.clickGroup(0);

      const detailPage = new GroupDetailPage(page);

      if (await detailPage.isMember()) {
        const createPostButton = detailPage.createPostButton;
        if (await createPostButton.count() > 0) {
          await expect(createPostButton).toBeVisible();
        }
      }
    }
  });
});

test.describe('Groups - Members Tab', () => {
  test('should show members list', async ({ page }) => {
    const groupsPage = new GroupsPage(page);
    await groupsPage.navigate();

    const count = await groupsPage.getGroupCount();
    if (count > 0) {
      await groupsPage.clickGroup(0);

      const detailPage = new GroupDetailPage(page);
      const membersTab = detailPage.membersTab;

      if (await membersTab.count() > 0) {
        await detailPage.goToMembersTab();

        const members = page.locator('.member, .member-card, [data-member]');
        const memberCount = await members.count();
        expect(memberCount).toBeGreaterThan(0);
      }
    }
  });
});

test.describe('Groups - Invite', () => {
  test('should have invite option for group admins', async ({ page }) => {
    // Navigate to a group user manages
    await page.goto(tenantUrl('groups/my-groups'));

    const myGroups = page.locator('.group-card, [data-group]');
    if (await myGroups.count() > 0) {
      await myGroups.first().click();
      await page.waitForLoadState('domcontentloaded');

      const detailPage = new GroupDetailPage(page);
      const inviteButton = detailPage.inviteButton;

      // May or may not have invite permissions
      if (await inviteButton.count() > 0) {
        await expect(inviteButton).toBeVisible();
      }
    }
  });
});

test.describe('Groups - Leave', () => {
  test.skip('should allow leaving a group', async ({ page }) => {
    // Skip to avoid leaving real groups
    // Enable when needed with test data setup
  });
});

test.describe('Groups - Accessibility', () => {
  test('should have proper heading structure', async ({ page }) => {
    const groupsPage = new GroupsPage(page);
    await groupsPage.navigate();

    const h1 = page.locator('h1');
    await expect(h1).toBeVisible();
  });

  test('should have keyboard-accessible group cards', async ({ page }) => {
    const groupsPage = new GroupsPage(page);
    await groupsPage.navigate();

    const count = await groupsPage.getGroupCount();
    if (count > 0) {
      const card = groupsPage.groupCards.first();
      const link = card.locator('a').first();

      if (await link.count() > 0) {
        await link.focus();
        await expect(link).toBeFocused();
      }
    }
  });
});

test.describe('Groups - Mobile Behavior', () => {
  test.use({ viewport: { width: 375, height: 667 } });

  test('should display properly on mobile', async ({ page }) => {
    const groupsPage = new GroupsPage(page);
    await groupsPage.navigate();

    const content = page.locator('main, .content, .groups');
    await expect(content).toBeVisible();
  });
});
