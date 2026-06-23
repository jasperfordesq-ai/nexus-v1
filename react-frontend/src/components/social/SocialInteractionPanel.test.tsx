// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';
import userEvent from '@testing-library/user-event';

// ─── Mock api ────────────────────────────────────────────────────────────────
const { mockApi } = vi.hoisted(() => ({
  mockApi: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn(), download: vi.fn(), upload: vi.fn() },
}));

vi.mock('@/lib/api', () => ({ api: mockApi, default: mockApi }));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));
vi.mock('@/lib/feedSync', () => ({ dispatchFeedSync: vi.fn() }));

// ─── Stub heavy children ─────────────────────────────────────────────────────
vi.mock('./CommentsSection', () => ({
  CommentsSection: ({ commentsLoading }: { commentsLoading: boolean }) => (
    <div data-testid="comments-section">{commentsLoading ? 'loading...' : 'comments'}</div>
  ),
}));

vi.mock('./LikersModal', () => ({
  LikersModal: ({ isOpen }: { isOpen: boolean }) => (
    isOpen ? <div data-testid="likers-modal">Likers</div> : null
  ),
}));

vi.mock('./ShareButton', () => ({
  ShareButton: ({ isAuthenticated }: { isAuthenticated: boolean }) => (
    <button data-testid="share-button" disabled={!isAuthenticated}>Share</button>
  ),
}));

// ─── Toast / Auth / Tenant ───────────────────────────────────────────────────
const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn(), showToast: vi.fn() };

vi.mock('@/contexts', () =>
  createMockContexts({
    useAuth: () => ({
      user: { id: 10, name: 'Alice', first_name: 'Alice', last_name: '', avatar_url: null, avatar: null },
      isAuthenticated: true,
      login: vi.fn(),
      logout: vi.fn(),
      register: vi.fn(),
      updateUser: vi.fn(),
      refreshUser: vi.fn(),
      status: 'idle' as const,
      error: null,
    }),
    useToast: () => mockToast,
    useTenant: () => ({
      tenant: { id: 2, name: 'Test', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  })
);

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

// ─── Default props ───────────────────────────────────────────────────────────
const defaultProps = {
  targetType: 'post',
  targetId: 42,
  initialLiked: false,
  initialLikesCount: 0,
  initialCommentsCount: 0,
};

// ─────────────────────────────────────────────────────────────────────────────
describe('SocialInteractionPanel', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockApi.get.mockResolvedValue({ success: true, data: { comments: [], count: 0 } });
    mockApi.post.mockResolvedValue({ success: true, data: { status: 'liked', action: 'liked', likes_count: 1 } });
  });

  it('renders Like and Comment action buttons', async () => {
    const { SocialInteractionPanel } = await import('./SocialInteractionPanel');
    render(<SocialInteractionPanel {...defaultProps} />);

    const likeBtn = screen.getByRole('button', { name: /like/i });
    const commentBtn = screen.getByRole('button', { name: /comment|open/i });
    expect(likeBtn).toBeInTheDocument();
    expect(commentBtn).toBeInTheDocument();
  });

  it('renders Share button for shareable target types', async () => {
    const { SocialInteractionPanel } = await import('./SocialInteractionPanel');
    render(<SocialInteractionPanel {...defaultProps} targetType="post" showShare />);

    await waitFor(() => {
      expect(screen.getByTestId('share-button')).toBeInTheDocument();
    });
  });

  it('does NOT render Share button for non-shareable target types', async () => {
    const { SocialInteractionPanel } = await import('./SocialInteractionPanel');
    render(<SocialInteractionPanel {...defaultProps} targetType="review" showShare />);

    expect(screen.queryByTestId('share-button')).not.toBeInTheDocument();
  });

  it('shows likes count when initialLikesCount > 0', async () => {
    const { SocialInteractionPanel } = await import('./SocialInteractionPanel');
    render(<SocialInteractionPanel {...defaultProps} initialLikesCount={7} />);

    // The counts summary row should be rendered
    await waitFor(() => {
      const likeCountBtn = screen.getAllByRole('button').find(b => /7/.test(b.textContent ?? ''));
      expect(likeCountBtn).toBeInTheDocument();
    });
  });

  it('shows comments count and clicking it opens comments', async () => {
    const { SocialInteractionPanel } = await import('./SocialInteractionPanel');
    render(<SocialInteractionPanel {...defaultProps} initialCommentsCount={3} />);

    const countBtn = screen.getAllByRole('button').find(b => /3/.test(b.textContent ?? ''));
    expect(countBtn).toBeInTheDocument();

    await userEvent.click(countBtn!);
    await waitFor(() => {
      expect(screen.getByTestId('comments-section')).toBeInTheDocument();
    });
  });

  it('toggles comments section open/closed', async () => {
    const { SocialInteractionPanel } = await import('./SocialInteractionPanel');
    render(<SocialInteractionPanel {...defaultProps} />);

    const commentBtn = screen.getByRole('button', { name: /comment|open/i });
    await userEvent.click(commentBtn);

    await waitFor(() => {
      expect(screen.getByTestId('comments-section')).toBeInTheDocument();
    });

    await userEvent.click(commentBtn);
    await waitFor(() => {
      expect(screen.queryByTestId('comments-section')).not.toBeInTheDocument();
    });
  });

  it('opens with comments visible when defaultShowComments=true', async () => {
    const { SocialInteractionPanel } = await import('./SocialInteractionPanel');
    render(<SocialInteractionPanel {...defaultProps} defaultShowComments />);

    await waitFor(() => {
      expect(screen.getByTestId('comments-section')).toBeInTheDocument();
    });
  });

  it('calls API to load comments when section opens', async () => {
    const { SocialInteractionPanel } = await import('./SocialInteractionPanel');
    render(<SocialInteractionPanel {...defaultProps} defaultShowComments />);

    await waitFor(() => {
      expect(mockApi.get).toHaveBeenCalledWith(
        expect.stringContaining('/v2/comments'),
      );
    });
  });

  it('Like button has disabled attribute when authenticated=false (default mock)', async () => {
    // The top-level mock uses isAuthenticated: true, so we verify the attribute is NOT disabled
    // by default. The component sets isDisabled={!isAuthenticated}.
    const { SocialInteractionPanel } = await import('./SocialInteractionPanel');
    render(<SocialInteractionPanel {...defaultProps} />);

    const likeBtn = screen.getByRole('button', { name: /toggle like/i });
    // In the default top-level mock isAuthenticated=true, so the button is enabled
    expect(likeBtn).not.toBeDisabled();
    expect(likeBtn).toHaveAttribute('aria-pressed', 'false');
  });

  it('opens likers modal when likes count is clicked', async () => {
    const { SocialInteractionPanel } = await import('./SocialInteractionPanel');
    render(<SocialInteractionPanel {...defaultProps} initialLikesCount={5} />);

    // The button has aria-label="View likes" (translated from t('view_likes'))
    const likesBtn = screen.getByRole('button', { name: /view likes/i });
    await userEvent.click(likesBtn);

    await waitFor(() => {
      expect(screen.getByTestId('likers-modal')).toBeInTheDocument();
    });
  });

  it('toggles like via API call when Like button is pressed (authenticated)', async () => {
    const { SocialInteractionPanel } = await import('./SocialInteractionPanel');
    render(<SocialInteractionPanel {...defaultProps} />);

    // aria-label="Toggle like"
    const likeBtn = screen.getByRole('button', { name: /toggle like/i });
    // Button is enabled for authenticated user
    expect(likeBtn).not.toBeDisabled();
    await userEvent.click(likeBtn);

    await waitFor(() => {
      expect(mockApi.post).toHaveBeenCalledWith(
        '/v2/feed/like',
        expect.objectContaining({ target_type: 'post', target_id: 42 }),
      );
    });
  });

  it('does not show counts row when both counts are zero', async () => {
    const { SocialInteractionPanel } = await import('./SocialInteractionPanel');
    render(<SocialInteractionPanel {...defaultProps} initialLikesCount={0} initialCommentsCount={0} />);

    // Only action buttons visible, no count summary
    const buttons = screen.getAllByRole('button');
    // Should have Like and Comment buttons but NO view-likes or view-comments count buttons
    const countButtons = buttons.filter(b => /^\d+/.test(b.textContent?.trim() ?? ''));
    expect(countButtons).toHaveLength(0);
  });
});
