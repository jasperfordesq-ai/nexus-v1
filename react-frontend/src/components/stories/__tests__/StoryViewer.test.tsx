// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for StoryViewer component
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@/test/test-utils';
import { api } from '@/lib/api';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

const mockAuthUser = { id: 1, name: 'Current User' };

vi.mock('@/contexts', () => ({
  useToast: vi.fn(() => ({ success: vi.fn(), error: vi.fn(), info: vi.fn() })),
  useTheme: () => ({ resolvedTheme: 'light', toggleTheme: vi.fn(), theme: 'system', setTheme: vi.fn() }),
  useNotifications: () => ({ unreadCount: 0, counts: {}, notifications: [], markAsRead: vi.fn(), markAllAsRead: vi.fn(), hasMore: false, loadMore: vi.fn(), isLoading: false, refresh: vi.fn() }),
  usePusher: () => ({ channel: null, isConnected: false }),
  usePusherOptional: () => null,
  useCookieConsent: () => ({ consent: null, showBanner: false, openPreferences: vi.fn(), resetConsent: vi.fn(), saveConsent: vi.fn(), hasConsent: vi.fn(() => true), updateConsent: vi.fn() }),
  readStoredConsent: () => null,
  useMenuContext: () => ({ headerMenus: [], mobileMenus: [], hasCustomMenus: false }),
  useFeature: vi.fn(() => true),
  useModule: vi.fn(() => true),
  useAuth: () => ({ user: mockAuthUser, isAuthenticated: true, login: vi.fn(), logout: vi.fn(), register: vi.fn(), updateUser: vi.fn(), refreshUser: vi.fn(), status: 'idle', error: null }),
  useTenant: () => ({ tenant: { id: 2, name: 'Test', slug: 'test', tagline: null }, branding: { name: 'Test', logo_url: null }, tenantSlug: 'test', tenantPath: (p: string) => '/test' + p, isLoading: false, hasFeature: vi.fn(() => true), hasModule: vi.fn(() => true) }),
}));

vi.mock('@/lib/logger', () => ({
  logError: vi.fn(),
}));

vi.mock('@/lib/helpers', () => ({
  resolveAssetUrl: (url: string) => url || '/default.png',
  resolveAvatarUrl: (url: string | null) => url || '/default-avatar.png',
  tenantPath: (p: string) => '/test' + p,
}));

vi.mock('framer-motion', async () => {
  const actual = await vi.importActual<typeof import('framer-motion')>('framer-motion');
  return {
    ...actual,
    motion: {
      ...actual.motion,
      div: ({ children, ...props }: React.PropsWithChildren<Record<string, unknown>>) => {
        const htmlProps: Record<string, unknown> = {};
        for (const [k, v] of Object.entries(props)) {
          if (!['initial', 'animate', 'exit', 'transition', 'variants', 'whileHover', 'whileTap', 'layout'].includes(k)) {
            htmlProps[k] = v;
          }
        }
        return <div {...htmlProps}>{children}</div>;
      },
    },
    AnimatePresence: ({ children }: React.PropsWithChildren) => <>{children}</>,
  };
});

import { StoryViewer } from '../StoryViewer';
import type { StoryUser } from '@/components/feed/StoriesBar';

const makeStoryUser = (overrides?: Partial<StoryUser>): StoryUser => ({
  user_id: 10,
  name: 'Story Author',
  first_name: 'Story',
  avatar_url: null,
  story_count: 2,
  has_unseen: true,
  is_own: false,
  is_connected: true,
  latest_at: '2026-03-23T12:00:00Z',
  ...overrides,
});

const mockTextStory = {
  id: 101,
  user_id: 10,
  media_type: 'text' as const,
  media_url: null,
  text_content: 'Hello from my story!',
  background_gradient: 'from-purple-600 to-blue-500',
  background_color: null,
  duration: 5,
  view_count: 12,
  is_viewed: false,
  expires_at: '2026-03-24T12:00:00Z',
  created_at: '2026-03-23T12:00:00Z',
};

const mockImageStory = {
  id: 102,
  user_id: 10,
  media_type: 'image' as const,
  media_url: 'https://example.com/photo.jpg',
  text_content: null,
  background_gradient: null,
  background_color: null,
  duration: 5,
  view_count: 8,
  is_viewed: true,
  expires_at: '2026-03-24T12:00:00Z',
  created_at: '2026-03-23T11:00:00Z',
};

const mockPollStory = {
  id: 103,
  user_id: 10,
  media_type: 'poll' as const,
  media_url: null,
  text_content: null,
  background_gradient: 'from-blue-600 to-indigo-500',
  background_color: null,
  duration: 10,
  view_count: 20,
  is_viewed: false,
  expires_at: '2026-03-24T12:00:00Z',
  created_at: '2026-03-23T10:00:00Z',
  poll_question: 'Cats or dogs?',
  poll_options: ['Cats', 'Dogs'],
  poll_results: { votes: { 0: 5, 1: 3 }, total_votes: 8 },
};

describe('StoryViewer', () => {
  const defaultProps = {
    storyUsers: [makeStoryUser()],
    initialUserIndex: 0,
    onClose: vi.fn(),
  };

  beforeEach(() => {
    vi.clearAllMocks();
    // Default: return text stories
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: [mockTextStory],
    });
    // Ignore view marking
    vi.mocked(api.post).mockResolvedValue({ success: true });
  });

  it('renders without crashing', async () => {
    render(<StoryViewer {...defaultProps} />);
    await waitFor(() => {
      expect(screen.getByRole('dialog')).toBeInTheDocument();
    });
  });

  it('has accessible dialog label with user name', async () => {
    render(<StoryViewer {...defaultProps} />);
    await waitFor(() => {
      expect(screen.getByRole('dialog')).toHaveAttribute('aria-label', 'Story from Story Author');
    });
  });

  it('renders the close button', async () => {
    render(<StoryViewer {...defaultProps} />);
    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'Close story viewer' })).toBeInTheDocument();
    });
  });

  it('calls onClose when close button is clicked', async () => {
    render(<StoryViewer {...defaultProps} />);
    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'Close story viewer' })).toBeInTheDocument();
    });
    fireEvent.click(screen.getByRole('button', { name: 'Close story viewer' }));
    expect(defaultProps.onClose).toHaveBeenCalled();
  });

  it('calls onClose when Escape key is pressed', async () => {
    render(<StoryViewer {...defaultProps} />);
    await waitFor(() => {
      expect(screen.getByRole('dialog')).toBeInTheDocument();
    });
    fireEvent.keyDown(window, { key: 'Escape' });
    expect(defaultProps.onClose).toHaveBeenCalled();
  });

  it('displays the story author name', async () => {
    render(<StoryViewer {...defaultProps} />);
    await waitFor(() => {
      expect(screen.getByText('Story Author')).toBeInTheDocument();
    });
  });

  it('loads stories from the API on mount', async () => {
    render(<StoryViewer {...defaultProps} />);
    await waitFor(() => {
      expect(api.get).toHaveBeenCalledWith('/v2/stories/user/10');
    });
  });

  it('displays text story content', async () => {
    render(<StoryViewer {...defaultProps} />);
    await waitFor(() => {
      expect(screen.getByText('Hello from my story!')).toBeInTheDocument();
    });
  });

  it('renders image story with img element', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({
      success: true,
      data: [mockImageStory],
    });

    render(<StoryViewer {...defaultProps} />);

    await waitFor(() => {
      const img = screen.getByAltText('Story from Story Author');
      expect(img).toBeInTheDocument();
      expect(img).toHaveAttribute('src', 'https://example.com/photo.jpg');
    });
  });

  it('renders poll story with question and options', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({
      success: true,
      data: [mockPollStory],
    });

    render(<StoryViewer {...defaultProps} />);

    await waitFor(() => {
      expect(screen.getByText('Cats or dogs?')).toBeInTheDocument();
      expect(screen.getByRole('button', { name: 'Vote for Cats' })).toBeInTheDocument();
      expect(screen.getByRole('button', { name: 'Vote for Dogs' })).toBeInTheDocument();
    });
  });

  it('renders tap zones for navigation', async () => {
    render(<StoryViewer {...defaultProps} />);
    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'Previous story' })).toBeInTheDocument();
      expect(screen.getByRole('button', { name: 'Next story' })).toBeInTheDocument();
    });
  });

  it('renders progress bars when stories are loaded', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({
      success: true,
      data: [mockTextStory, mockImageStory],
    });

    render(<StoryViewer {...defaultProps} />);

    await waitFor(() => {
      const progressBars = screen.getAllByRole('progressbar');
      expect(progressBars).toHaveLength(2);
    });
  });

  it('marks story as viewed via API for non-owner stories', async () => {
    render(<StoryViewer {...defaultProps} />);
    await waitFor(() => {
      expect(api.post).toHaveBeenCalledWith('/v2/stories/101/view');
    });
  });

  it('shows reaction buttons for non-owner text stories', async () => {
    render(<StoryViewer {...defaultProps} />);
    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'React with heart' })).toBeInTheDocument();
      expect(screen.getByRole('button', { name: 'React with laugh' })).toBeInTheDocument();
      expect(screen.getByRole('button', { name: 'React with fire' })).toBeInTheDocument();
    });
  });

  it('sends reaction via API when reaction button is clicked', async () => {
    vi.mocked(api.post).mockResolvedValue({ success: true });

    render(<StoryViewer {...defaultProps} />);

    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'React with heart' })).toBeInTheDocument();
    });

    fireEvent.click(screen.getByRole('button', { name: 'React with heart' }));

    await waitFor(() => {
      expect(api.post).toHaveBeenCalledWith('/v2/stories/101/react', { reaction_type: 'heart' });
    });
  });

  it('shows "No stories available" when API returns empty list', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({
      success: true,
      data: [],
    });

    render(<StoryViewer {...defaultProps} />);

    await waitFor(() => {
      expect(screen.getByText('No stories available')).toBeInTheDocument();
    });
  });

  it('shows view count and menu for owner stories', async () => {
    const ownerUser = makeStoryUser({ user_id: 1 }); // matches mockAuthUser.id
    vi.mocked(api.get).mockResolvedValueOnce({
      success: true,
      data: [{ ...mockTextStory, user_id: 1, view_count: 42 }],
    });

    render(
      <StoryViewer
        storyUsers={[ownerUser]}
        initialUserIndex={0}
        onClose={vi.fn()}
      />
    );

    await waitFor(() => {
      // View count label
      expect(screen.getByRole('button', { name: '42 views' })).toBeInTheDocument();
      // More options menu
      expect(screen.getByRole('button', { name: 'Story options' })).toBeInTheDocument();
    });
  });

  it('does not show reactions for owner stories', async () => {
    const ownerUser = makeStoryUser({ user_id: 1 });
    vi.mocked(api.get).mockResolvedValueOnce({
      success: true,
      data: [{ ...mockTextStory, user_id: 1 }],
    });

    render(
      <StoryViewer
        storyUsers={[ownerUser]}
        initialUserIndex={0}
        onClose={vi.fn()}
      />
    );

    await waitFor(() => {
      expect(screen.getByText('Hello from my story!')).toBeInTheDocument();
    });

    expect(screen.queryByRole('button', { name: 'React with heart' })).not.toBeInTheDocument();
  });

  it('renders previous/next user arrows when multiple users exist', async () => {
    const users = [
      makeStoryUser({ user_id: 10, name: 'User A' }),
      makeStoryUser({ user_id: 20, name: 'User B' }),
      makeStoryUser({ user_id: 30, name: 'User C' }),
    ];

    render(
      <StoryViewer
        storyUsers={users}
        initialUserIndex={1}
        onClose={vi.fn()}
      />
    );

    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'Previous user' })).toBeInTheDocument();
      expect(screen.getByRole('button', { name: 'Next user' })).toBeInTheDocument();
    });
  });

  it('returns null when storyUsers is empty', () => {
    render(
      <StoryViewer storyUsers={[]} initialUserIndex={0} onClose={vi.fn()} />
    );
    // Component returns null when currentUserStory is undefined, so no dialog is rendered
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });
});
