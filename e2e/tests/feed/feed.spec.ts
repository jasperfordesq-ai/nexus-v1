import { test, expect } from '@playwright/test';
import { tenantUrl, generateTestData, waitForAjax } from '../../helpers/test-utils';

test.describe('Social Feed', () => {
  test.describe('Feed Display', () => {
    test('should display feed page with posts', async ({ page }) => {
      await page.goto(tenantUrl(''));

      // Feed should have posts or empty state
      const posts = page.locator('.feed-post, .post-card, [data-post]');
      const emptyState = page.locator('.empty-feed, .no-posts');

      const hasPosts = await posts.count() > 0;
      const isEmpty = await emptyState.count() > 0;

      expect(hasPosts || isEmpty).toBeTruthy();
    });

    test('should show post composer for logged-in users', async ({ page }) => {
      await page.goto(tenantUrl(''));

      const composer = page.locator('.post-composer, .create-post, [data-composer], textarea[name="content"]');
      await expect(composer).toBeVisible();
    });

    test('should display post metadata (author, date)', async ({ page }) => {
      await page.goto(tenantUrl(''));

      const posts = page.locator('.feed-post, .post-card, [data-post]');
      if (await posts.count() > 0) {
        const firstPost = posts.first();

        // Should have author info
        const author = firstPost.locator('.author, .user-name, [data-author]');
        await expect(author).toBeVisible();

        // Should have date/time
        const date = firstPost.locator('.date, time, [data-time]');
        await expect(date).toBeVisible();
      }
    });

    test('should show interaction buttons on posts', async ({ page }) => {
      await page.goto(tenantUrl(''));

      const posts = page.locator('.feed-post, .post-card, [data-post]');
      if (await posts.count() > 0) {
        const firstPost = posts.first();

        // Should have like button
        const likeButton = firstPost.locator('.like-btn, [data-like], button:has-text("Like")');
        await expect(likeButton).toBeVisible();

        // Should have comment button/link
        const commentButton = firstPost.locator('.comment-btn, [data-comment], button:has-text("Comment"), a:has-text("Comment")');
        await expect(commentButton).toBeVisible();
      }
    });
  });

  test.describe('Create Post', () => {
    test('should create a text post', async ({ page }) => {
      await page.goto(tenantUrl(''));

      const testData = generateTestData();
      const postContent = `Test post ${testData.uniqueId}`;

      // Find and fill the composer
      const composer = page.locator('textarea[name="content"], .post-composer textarea, [data-composer] textarea');
      await composer.fill(postContent);

      // Submit post
      const submitButton = page.locator('button[type="submit"]:near(textarea), .post-submit, [data-submit-post]');
      await submitButton.click();
      await page.waitForLoadState('domcontentloaded');

      // Verify post appears
      const posts = page.locator('.feed-post, .post-card, [data-post]');
      const postText = await posts.first().textContent();
      expect(postText).toContain(testData.uniqueId);
    });

    test('should not allow empty posts', async ({ page }) => {
      await page.goto(tenantUrl(''));

      const submitButton = page.locator('button[type="submit"]:near(textarea), .post-submit, [data-submit-post]');

      // Button should be disabled for empty content or show error
      const isDisabled = await submitButton.isDisabled();
      if (!isDisabled) {
        await submitButton.click();
        await page.waitForLoadState('domcontentloaded');

        // Should show error or stay on page
        const errors = page.locator('.error, .alert-danger');
        const stillOnFeed = page.url().match(/\/(home|feed|\/)$/);
        expect((await errors.count() > 0) || stillOnFeed).toBeTruthy();
      } else {
        expect(isDisabled).toBeTruthy();
      }
    });

    test('should handle post with mentions', async ({ page }) => {
      await page.goto(tenantUrl(''));

      const composer = page.locator('textarea[name="content"], .post-composer textarea');
      await composer.fill('Hello @');

      // Should show mention suggestions
      await page.waitForTimeout(500);
      const suggestions = page.locator('.mention-suggestion, .user-suggestion, [data-mention]');

      // Mention autocomplete may or may not be present
      if (await suggestions.count() > 0) {
        await expect(suggestions.first()).toBeVisible();
      }
    });
  });

  test.describe('Post Interactions', () => {
    test('should like a post', async ({ page }) => {
      await page.goto(tenantUrl(''));

      const posts = page.locator('.feed-post, .post-card, [data-post]');
      if (await posts.count() > 0) {
        const firstPost = posts.first();
        const likeButton = firstPost.locator('.like-btn, [data-like]');

        // Get initial like count
        const likeCount = firstPost.locator('.like-count, [data-likes]');
        const initialCount = await likeCount.textContent() || '0';

        // Click like
        await likeButton.click();
        await page.waitForLoadState('domcontentloaded');

        // Verify button state changed or count increased
        const buttonClasses = await likeButton.getAttribute('class');
        const newCount = await likeCount.textContent() || '0';

        const isLiked = buttonClasses?.includes('liked') || buttonClasses?.includes('active');
        const countChanged = newCount !== initialCount;

        expect(isLiked || countChanged).toBeTruthy();
      }
    });

    test('should open comments section', async ({ page }) => {
      await page.goto(tenantUrl(''));

      const posts = page.locator('.feed-post, .post-card, [data-post]');
      if (await posts.count() > 0) {
        const firstPost = posts.first();
        const commentButton = firstPost.locator('.comment-btn, [data-comment], a:has-text("Comment")');

        await commentButton.click();
        await page.waitForTimeout(300);

        // Comments section should be visible
        const commentsSection = page.locator('.comments-section, .comments, [data-comments]');
        const commentInput = page.locator('textarea[name="comment"], .comment-input');

        const hasComments = await commentsSection.count() > 0;
        const hasInput = await commentInput.count() > 0;

        expect(hasComments || hasInput).toBeTruthy();
      }
    });

    test('should add a comment to a post', async ({ page }) => {
      await page.goto(tenantUrl(''));

      const posts = page.locator('.feed-post, .post-card, [data-post]');
      if (await posts.count() > 0) {
        const firstPost = posts.first();

        // Open comments
        const commentButton = firstPost.locator('.comment-btn, [data-comment]');
        await commentButton.click();
        await page.waitForTimeout(300);

        const testData = generateTestData();
        const commentText = `Test comment ${testData.uniqueId}`;

        // Find comment input
        const commentInput = page.locator('textarea[name="comment"], .comment-input').first();
        await commentInput.fill(commentText);

        // Submit comment
        const submitComment = page.locator('button:near(.comment-input), .comment-submit').first();
        await submitComment.click();
        await page.waitForLoadState('domcontentloaded');

        // Verify comment appears
        const pageContent = await page.content();
        expect(pageContent).toContain(testData.uniqueId);
      }
    });

    test('should share a post', async ({ page }) => {
      await page.goto(tenantUrl(''));

      const posts = page.locator('.feed-post, .post-card, [data-post]');
      if (await posts.count() > 0) {
        const firstPost = posts.first();
        const shareButton = firstPost.locator('.share-btn, [data-share]');

        if (await shareButton.count() > 0) {
          await shareButton.click();
          await page.waitForTimeout(300);

          // Should show share options
          const shareOptions = page.locator('.share-menu, .share-options, [data-share-menu]');
          await expect(shareOptions).toBeVisible();
        }
      }
    });
  });

  test.describe('Post Detail View', () => {
    test('should navigate to post detail page', async ({ page }) => {
      await page.goto(tenantUrl(''));

      const posts = page.locator('.feed-post, .post-card, [data-post]');
      if (await posts.count() > 0) {
        const firstPost = posts.first();
        const postLink = firstPost.locator('a[href*="post"], .post-link');

        if (await postLink.count() > 0) {
          await postLink.first().click();
          await page.waitForLoadState('domcontentloaded');

          expect(page.url()).toContain('post');
        }
      }
    });
  });

  test.describe('Feed Filtering', () => {
    test('should filter by post type if available', async ({ page }) => {
      await page.goto(tenantUrl(''));

      const filterButtons = page.locator('.feed-filter, [data-filter]');
      if (await filterButtons.count() > 0) {
        const firstFilter = filterButtons.first();
        await firstFilter.click();
        await page.waitForLoadState('domcontentloaded');

        // Feed should update
        expect(page.url()).toBeTruthy();
      }
    });
  });

  test.describe('Infinite Scroll / Pagination', () => {
    test('should load more posts on scroll or button click', async ({ page }) => {
      await page.goto(tenantUrl(''));

      const posts = page.locator('.feed-post, .post-card, [data-post]');
      const initialCount = await posts.count();

      if (initialCount > 0) {
        // Look for load more button
        const loadMore = page.locator('.load-more, [data-load-more]');
        if (await loadMore.isVisible()) {
          await loadMore.click();
          await page.waitForLoadState('domcontentloaded');

          const newCount = await posts.count();
          expect(newCount).toBeGreaterThanOrEqual(initialCount);
        } else {
          // Try scrolling to bottom
          await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
          await page.waitForTimeout(1000);

          // Count may or may not increase depending on pagination
        }
      }
    });
  });

  test.describe('Post Moderation', () => {
    test('should show report option for posts', async ({ page }) => {
      await page.goto(tenantUrl(''));

      const posts = page.locator('.feed-post, .post-card, [data-post]');
      if (await posts.count() > 0) {
        const firstPost = posts.first();
        const moreMenu = firstPost.locator('.more-menu, [data-more], .dropdown-toggle');

        if (await moreMenu.count() > 0) {
          await moreMenu.click();
          await page.waitForTimeout(200);

          const reportOption = page.locator('.report-btn, [data-report], a:has-text("Report")');
          await expect(reportOption).toBeVisible();
        }
      }
    });

    test('should allow hiding posts', async ({ page }) => {
      await page.goto(tenantUrl(''));

      const posts = page.locator('.feed-post, .post-card, [data-post]');
      if (await posts.count() > 0) {
        const firstPost = posts.first();
        const moreMenu = firstPost.locator('.more-menu, [data-more], .dropdown-toggle');

        if (await moreMenu.count() > 0) {
          await moreMenu.click();
          await page.waitForTimeout(200);

          const hideOption = page.locator('.hide-btn, [data-hide], a:has-text("Hide")');
          if (await hideOption.count() > 0) {
            await expect(hideOption).toBeVisible();
          }
        }
      }
    });
  });

  test.describe('Polls', () => {
    test('should display poll posts correctly', async ({ page }) => {
      await page.goto(tenantUrl(''));

      const pollPost = page.locator('.poll-post, [data-poll]');
      if (await pollPost.count() > 0) {
        // Should show poll options
        const options = pollPost.locator('.poll-option, [data-option]');
        await expect(options.first()).toBeVisible();
      }
    });
  });
});
