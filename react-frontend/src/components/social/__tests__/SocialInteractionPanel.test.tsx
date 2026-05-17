// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for the shared SocialInteractionPanel.
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { HeroUIProvider } from '@heroui/react';
import { MemoryRouter } from 'react-router-dom';
import type { ReactElement } from 'react';

const useSocialInteractionsMock = vi.hoisted(() => vi.fn());

vi.mock('@/hooks/useSocialInteractions', () => ({
  useSocialInteractions: useSocialInteractionsMock,
}));

vi.mock('@/contexts', () => ({
  useAuth: vi.fn(() => ({
    isAuthenticated: true,
    user: {
      id: 7,
      name: 'Test User',
      first_name: 'Test',
      last_name: 'User',
      avatar_url: null,
      avatar: null,
    },
  })),
  useTenant: vi.fn(() => ({
    tenantPath: (path: string) => `/test${path}`,
  })),
  useToast: vi.fn(() => ({ success: vi.fn(), error: vi.fn(), info: vi.fn() })),
}));

vi.mock('react-i18next', () => ({
  initReactI18next: {
    type: '3rdParty',
    init: vi.fn(),
  },
  useTranslation: () => ({
    t: (key: string, options?: { count?: number }) => {
      if (key === 'likes_count') return `${options?.count ?? 0} likes`;
      if (key === 'comments_count') return `${options?.count ?? 0} comments`;
      const labels: Record<string, string> = {
        like: 'Like',
        liked: 'Liked',
        comment_action: 'Comment',
        share: 'Share',
        toggle_like: 'Toggle like',
        open_comments: 'Open comments',
        close_comments: 'Close comments',
        view_likes: 'View likes',
        you: 'You',
      };
      return labels[key] ?? key;
    },
  }),
}));

vi.mock('../CommentsSection', () => ({
  CommentsSection: ({ commentsCount }: { commentsCount: number }) => (
    <div data-testid="comments-section" data-count={commentsCount} />
  ),
}));

vi.mock('../LikersModal', () => ({
  LikersModal: ({ isOpen, likesCount }: { isOpen: boolean; likesCount: number }) => (
    <div data-testid="likers-modal" data-open={isOpen} data-count={likesCount} />
  ),
}));

vi.mock('../ShareButton', () => ({
  ShareButton: ({ canShareToFeed = true }: { canShareToFeed?: boolean }) => (
    <button data-testid="share-button" data-can-share-to-feed={String(canShareToFeed)}>Share</button>
  ),
}));

import { SocialInteractionPanel } from '../SocialInteractionPanel';

function renderPanel(ui: ReactElement) {
  return render(
    <HeroUIProvider>
      <MemoryRouter>{ui}</MemoryRouter>
    </HeroUIProvider>,
  );
}

function makeSocial(overrides: Record<string, unknown> = {}) {
  return {
    isLiked: false,
    likesCount: 3,
    isLiking: false,
    toggleLike: vi.fn().mockResolvedValue(undefined),
    comments: [],
    commentsCount: 2,
    commentsLoading: false,
    commentsLoaded: false,
    loadComments: vi.fn().mockResolvedValue(undefined),
    submitComment: vi.fn().mockResolvedValue(true),
    editComment: vi.fn().mockResolvedValue(true),
    deleteComment: vi.fn().mockResolvedValue(true),
    availableReactions: ['like', 'love', 'laugh', 'wow', 'sad', 'celebrate'],
    toggleReaction: vi.fn().mockResolvedValue(undefined),
    shareToFeed: vi.fn().mockResolvedValue(true),
    searchMentions: vi.fn().mockResolvedValue([]),
    loadLikers: vi.fn().mockResolvedValue({ likers: [], total_count: 0, has_more: false }),
    ...overrides,
  };
}

describe('SocialInteractionPanel', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    window.history.replaceState(null, '', '/test/feed');
    useSocialInteractionsMock.mockReturnValue(makeSocial());
  });

  it('renders counts and shared social actions', () => {
    renderPanel(<SocialInteractionPanel targetType="event" targetId={42} title="Event" />);

    expect(screen.getByText('3 likes')).toBeInTheDocument();
    expect(screen.getByText('2 comments')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Toggle like' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Open comments' })).toBeInTheDocument();
    expect(screen.getByTestId('share-button')).toBeInTheDocument();
  });

  it('toggles likes through the shared hook', async () => {
    const user = userEvent.setup();
    const toggleLike = vi.fn().mockResolvedValue(undefined);
    useSocialInteractionsMock.mockReturnValue(makeSocial({ toggleLike }));

    renderPanel(<SocialInteractionPanel targetType="goal" targetId={9} />);
    await user.click(screen.getByRole('button', { name: 'Toggle like' }));

    expect(toggleLike).toHaveBeenCalledTimes(1);
  });

  it('opens comments and loads them on demand', async () => {
    const user = userEvent.setup();
    const loadComments = vi.fn().mockResolvedValue(undefined);
    useSocialInteractionsMock.mockReturnValue(makeSocial({ commentsCount: 0, loadComments }));

    renderPanel(<SocialInteractionPanel targetType="job" targetId={5} />);
    await user.click(screen.getByRole('button', { name: 'Open comments' }));

    await waitFor(() => {
      expect(loadComments).toHaveBeenCalledTimes(1);
    });
    expect(screen.getByTestId('comments-section')).toBeInTheDocument();
  });

  it('opens comments for notification deep links', async () => {
    const loadComments = vi.fn().mockResolvedValue(undefined);
    useSocialInteractionsMock.mockReturnValue(makeSocial({ loadComments }));
    window.history.replaceState(null, '', '/test/events/42#comment-123');

    renderPanel(<SocialInteractionPanel targetType="event" targetId={42} />);

    await waitFor(() => {
      expect(loadComments).toHaveBeenCalledTimes(1);
    });
    expect(screen.getByTestId('comments-section')).toBeInTheDocument();
  });

  it('hides unsupported share targets while keeping comments and likes', () => {
    renderPanel(<SocialInteractionPanel targetType="review" targetId={11} />);

    expect(screen.getByRole('button', { name: 'Toggle like' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Open comments' })).toBeInTheDocument();
    expect(screen.queryByTestId('share-button')).not.toBeInTheDocument();
  });

  it('keeps resource comments interactive while hiding share', () => {
    renderPanel(<SocialInteractionPanel targetType="resource" targetId={18} />);

    expect(screen.getByRole('button', { name: 'Toggle like' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Open comments' })).toBeInTheDocument();
    expect(screen.queryByTestId('share-button')).not.toBeInTheDocument();
  });

  it('honors explicit share suppression for otherwise shareable targets', () => {
    renderPanel(<SocialInteractionPanel targetType="event" targetId={42} showShare={false} />);

    expect(screen.getByRole('button', { name: 'Toggle like' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Open comments' })).toBeInTheDocument();
    expect(screen.queryByTestId('share-button')).not.toBeInTheDocument();
  });

  it('disables feed sharing for content owned by the current user', () => {
    renderPanel(<SocialInteractionPanel targetType="challenge" targetId={42} targetOwnerId={7} />);

    expect(screen.getByTestId('share-button')).toHaveAttribute('data-can-share-to-feed', 'false');
  });
});
