// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for FeedCard component
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import userEvent from '@testing-library/user-event';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn().mockResolvedValue({ success: true, data: { comments: [] } }),
    post: vi.fn().mockResolvedValue({ success: true }),
  },
}));

vi.mock('@/contexts', () => ({
  useTenant: vi.fn(() => ({
    tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
    tenantPath: (p: string) => `/test${p}`,
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
  })),
}));

vi.mock('@/lib/logger', () => ({
  logError: vi.fn(),
}));

vi.mock('@/lib/helpers', () => ({
  resolveAvatarUrl: vi.fn((url) => url || '/default-avatar.png'),
  resolveAssetUrl: vi.fn((url) => url || ''),
  formatRelativeTime: vi.fn(() => '2 hours ago'),
}));

vi.mock('framer-motion', () => ({
  motion: {
    div: ({ children, ...props }: Record<string, unknown>) => {
      const { variants, initial, animate, exit, layout, ...rest } = props;
      return <div {...rest}>{children}</div>;
    },
  },
  AnimatePresence: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

import { FeedCard, CommentItem } from './FeedCard';
import type { FeedItem, FeedComment } from './types';

const baseFeedItem: FeedItem = {
  id: 1,
  content: 'Hello world!',
  title: 'Test Post Title',
  author_name: 'Jane Doe',
  author_avatar: '/jane.png',
  author_id: 10,
  created_at: '2026-02-21T12:00:00Z',
  type: 'post',
  likes_count: 5,
  comments_count: 3,
  is_liked: false,
};

const defaultProps = {
  item: baseFeedItem,
  onToggleLike: vi.fn(),
  onHidePost: vi.fn(),
  onMuteUser: vi.fn(),
  onReportPost: vi.fn(),
  onDeletePost: vi.fn(),
  onVotePoll: vi.fn(),
  isAuthenticated: true,
  currentUserId: 99,
};

describe('FeedCard', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders author name and content', () => {
    render(<FeedCard {...defaultProps} />);
    expect(screen.getByText('Jane Doe')).toBeInTheDocument();
    expect(screen.getByText('Hello world!')).toBeInTheDocument();
  });

  it('renders title when different from content', () => {
    render(<FeedCard {...defaultProps} />);
    expect(screen.getByText('Test Post Title')).toBeInTheDocument();
  });

  it('does not render title when same as content', () => {
    const item = { ...baseFeedItem, title: 'Hello world!' };
    render(<FeedCard {...defaultProps} item={item} />);
    // Content appears once, not duplicated
    const matches = screen.getAllByText('Hello world!');
    expect(matches.length).toBe(1);
  });

  it('shows relative time', () => {
    render(<FeedCard {...defaultProps} />);
    expect(screen.getByText('2 hours ago')).toBeInTheDocument();
  });

  it('shows like count', () => {
    render(<FeedCard {...defaultProps} />);
    expect(screen.getByText(/5 likes/)).toBeInTheDocument();
  });

  it('shows comment count', () => {
    render(<FeedCard {...defaultProps} />);
    expect(screen.getByText(/3 comments/)).toBeInTheDocument();
  });

  it('shows type chip for non-post types', () => {
    const item = { ...baseFeedItem, type: 'event' as const };
    render(<FeedCard {...defaultProps} item={item} />);
    expect(screen.getByText('Event')).toBeInTheDocument();
  });

  it('does not show type chip for posts', () => {
    render(<FeedCard {...defaultProps} />);
    // Post type has typeLabel = null
    expect(screen.queryByText('Post')).not.toBeInTheDocument();
  });

  it('renders image with proper alt text', () => {
    const item = { ...baseFeedItem, image_url: '/test-image.jpg' };
    render(<FeedCard {...defaultProps} item={item} />);
    const img = screen.getByAltText('Post image by Jane Doe');
    expect(img).toBeInTheDocument();
    expect(img).toHaveAttribute('loading', 'lazy');
  });

  it('calls onToggleLike when Like button pressed', async () => {
    const user = userEvent.setup();
    render(<FeedCard {...defaultProps} />);
    const likeBtn = screen.getByText('Like');
    await user.click(likeBtn);
    expect(defaultProps.onToggleLike).toHaveBeenCalledOnce();
  });

  it('disables like button when not authenticated', () => {
    render(<FeedCard {...defaultProps} isAuthenticated={false} />);
    const likeBtn = screen.getByText('Like').closest('button');
    expect(likeBtn).toBeDisabled();
  });

  it('shows moderation menu for authenticated users', () => {
    render(<FeedCard {...defaultProps} />);
    expect(screen.getByLabelText('Post options')).toBeInTheDocument();
  });

  it('does not show moderation menu for unauthenticated users', () => {
    render(<FeedCard {...defaultProps} isAuthenticated={false} />);
    expect(screen.queryByLabelText('Post options')).not.toBeInTheDocument();
  });

  it('shows post options button for own posts', () => {
    // Set currentUserId to match author_id — dropdown items render on open,
    // but the trigger button should always be present
    render(<FeedCard {...defaultProps} currentUserId={10} />);
    expect(screen.getByLabelText('Post options')).toBeInTheDocument();
  });

  it('shows post options button for other users posts', () => {
    render(<FeedCard {...defaultProps} currentUserId={99} />);
    expect(screen.getByLabelText('Post options')).toBeInTheDocument();
  });

  it('renders review with rating stars', () => {
    const item: FeedItem = {
      ...baseFeedItem,
      type: 'review',
      rating: 4,
      receiver: { id: 20, name: 'John Smith' },
    };
    render(<FeedCard {...defaultProps} item={item} />);
    expect(screen.getByText('Reviewed')).toBeInTheDocument();
    expect(screen.getByText('John Smith')).toBeInTheDocument();
  });

  it('renders poll with vote buttons when user has not voted', () => {
    const item: FeedItem = {
      ...baseFeedItem,
      type: 'poll',
      poll_data: {
        id: 1,
        question: 'Favorite color?',
        options: [
          { id: 10, text: 'Red', vote_count: 3, percentage: 60 },
          { id: 11, text: 'Blue', vote_count: 2, percentage: 40 },
        ],
        total_votes: 5,
        user_vote_option_id: null,
        is_active: true,
      },
    };
    render(<FeedCard {...defaultProps} item={item} />);
    expect(screen.getByText('Red')).toBeInTheDocument();
    expect(screen.getByText('Blue')).toBeInTheDocument();
    expect(screen.getByText('5 votes')).toBeInTheDocument();
  });

  it('renders poll results when user has voted', () => {
    const item: FeedItem = {
      ...baseFeedItem,
      type: 'poll',
      poll_data: {
        id: 1,
        question: 'Favorite color?',
        options: [
          { id: 10, text: 'Red', vote_count: 3, percentage: 60 },
          { id: 11, text: 'Blue', vote_count: 2, percentage: 40 },
        ],
        total_votes: 5,
        user_vote_option_id: 10,
        is_active: true,
      },
    };
    render(<FeedCard {...defaultProps} item={item} />);
    expect(screen.getByText('60%')).toBeInTheDocument();
    expect(screen.getByText('40%')).toBeInTheDocument();
  });

  it('shows singular "1 like" and "1 comment"', () => {
    const item = { ...baseFeedItem, likes_count: 1, comments_count: 1 };
    render(<FeedCard {...defaultProps} item={item} />);
    expect(screen.getByText(/1 like$/)).toBeInTheDocument();
    expect(screen.getByText(/1 comment$/)).toBeInTheDocument();
  });

  it('hides stats row when both counts are zero', () => {
    const item = { ...baseFeedItem, likes_count: 0, comments_count: 0 };
    render(<FeedCard {...defaultProps} item={item} />);
    expect(screen.queryByText(/likes?$/)).not.toBeInTheDocument();
    expect(screen.queryByText(/comments?$/)).not.toBeInTheDocument();
  });
});

describe('CommentItem', () => {
  const baseComment: FeedComment = {
    id: 1,
    content: 'Great post!',
    created_at: '2026-02-21T12:00:00Z',
    edited: false,
    is_own: false,
    author: { id: 5, name: 'Alice', avatar: '/alice.png' },
    reactions: {},
    user_reactions: [],
    replies: [],
  };

  it('renders comment content and author', () => {
    render(<CommentItem comment={baseComment} />);
    expect(screen.getByText('Great post!')).toBeInTheDocument();
    expect(screen.getByText('Alice')).toBeInTheDocument();
  });

  it('shows edited indicator', () => {
    const comment = { ...baseComment, edited: true };
    render(<CommentItem comment={comment} />);
    expect(screen.getByText('(edited)')).toBeInTheDocument();
  });

  it('does not show edited indicator when not edited', () => {
    render(<CommentItem comment={baseComment} />);
    expect(screen.queryByText('(edited)')).not.toBeInTheDocument();
  });

  it('shows reply count button when replies exist', () => {
    const comment: FeedComment = {
      ...baseComment,
      replies: [
        {
          id: 2,
          content: 'Thanks!',
          created_at: '2026-02-21T13:00:00Z',
          edited: false,
          is_own: false,
          author: { id: 6, name: 'Bob', avatar: null },
          reactions: {},
          user_reactions: [],
          replies: [],
        },
      ],
    };
    render(<CommentItem comment={comment} />);
    expect(screen.getByText(/1 reply/)).toBeInTheDocument();
  });

  it('uses singular "reply" for 1 reply', () => {
    const comment: FeedComment = {
      ...baseComment,
      replies: [
        {
          id: 2,
          content: 'Thanks!',
          created_at: '2026-02-21T13:00:00Z',
          edited: false,
          is_own: false,
          author: { id: 6, name: 'Bob', avatar: null },
          reactions: {},
          user_reactions: [],
          replies: [],
        },
      ],
    };
    render(<CommentItem comment={comment} />);
    expect(screen.getByText(/1 reply$/)).toBeInTheDocument();
  });

  it('does not show reply button when no replies', () => {
    render(<CommentItem comment={baseComment} />);
    expect(screen.queryByText(/repl/)).not.toBeInTheDocument();
  });
});
