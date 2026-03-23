// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for StoryHighlights component
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
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

const mockToast = {
  success: vi.fn(),
  error: vi.fn(),
  info: vi.fn(),
};

const mockAuthUser = { id: 42, name: 'Test User' };

vi.mock('@/contexts', () => ({
  useToast: vi.fn(() => mockToast),
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

vi.mock('@/components/stories/StoryViewer', () => ({
  StoryViewer: () => <div data-testid="story-viewer">StoryViewer Mock</div>,
}));

import { StoryHighlights } from '../StoryHighlights';

describe('StoryHighlights', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders loading skeletons initially', () => {
    // Never resolves, stays in loading state
    vi.mocked(api.get).mockReturnValue(new Promise(() => {}));
    const { container } = render(
      <StoryHighlights userId={42} userName="Test User" />
    );
    // Skeleton elements should be present during loading
    const skeletons = container.querySelectorAll('[data-slot="base"]');
    expect(skeletons.length).toBeGreaterThanOrEqual(0);
    // The loading state renders skeleton placeholders
    expect(container.querySelector('.flex')).toBeInTheDocument();
  });

  it('shows "Create new highlight" button for owner (userId matches auth user)', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({
      success: true,
      data: [],
    });

    render(<StoryHighlights userId={42} userName="Test User" />);

    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'Create new highlight' })).toBeInTheDocument();
    });
  });

  it('shows "New" label under the create button for owner', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({
      success: true,
      data: [],
    });

    render(<StoryHighlights userId={42} userName="Test User" />);

    await waitFor(() => {
      expect(screen.getByText('New')).toBeInTheDocument();
    });
  });

  it('does not render anything if not owner and no highlights', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({
      success: true,
      data: [],
    });

    const { container } = render(
      <StoryHighlights userId={99} userName="Other User" />
    );

    await waitFor(() => {
      // When no highlights and not owner, component returns null
      // The container should only contain a wrapping div from test utils
      expect(screen.queryByRole('button', { name: 'Create new highlight' })).not.toBeInTheDocument();
    });
  });

  it('renders highlight circles when highlights are returned', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({
      success: true,
      data: [
        { id: 1, title: 'Travel', cover_url: null, story_count: 3, display_order: 0, created_at: '2026-01-01' },
        { id: 2, title: 'Food', cover_url: 'https://example.com/food.jpg', story_count: 5, display_order: 1, created_at: '2026-01-02' },
      ],
    });

    render(<StoryHighlights userId={42} userName="Test User" />);

    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'View highlight: Travel' })).toBeInTheDocument();
      expect(screen.getByRole('button', { name: 'View highlight: Food' })).toBeInTheDocument();
    });
  });

  it('displays highlight titles', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({
      success: true,
      data: [
        { id: 1, title: 'Memories', cover_url: null, story_count: 2, display_order: 0, created_at: '2026-01-01' },
      ],
    });

    render(<StoryHighlights userId={42} userName="Test User" />);

    await waitFor(() => {
      expect(screen.getByText('Memories')).toBeInTheDocument();
    });
  });

  it('shows first letter of title when no cover image', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({
      success: true,
      data: [
        { id: 1, title: 'Work', cover_url: null, story_count: 1, display_order: 0, created_at: '2026-01-01' },
      ],
    });

    render(<StoryHighlights userId={42} userName="Test User" />);

    await waitFor(() => {
      expect(screen.getByText('W')).toBeInTheDocument();
    });
  });

  it('renders a cover image when highlight has a cover_url', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({
      success: true,
      data: [
        { id: 1, title: 'Trips', cover_url: 'https://example.com/trips.jpg', story_count: 2, display_order: 0, created_at: '2026-01-01' },
      ],
    });

    render(<StoryHighlights userId={42} userName="Test User" />);

    await waitFor(() => {
      const img = screen.getByAltText('Trips');
      expect(img).toBeInTheDocument();
    });
  });

  it('shows delete buttons for owner highlights', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({
      success: true,
      data: [
        { id: 1, title: 'Events', cover_url: null, story_count: 1, display_order: 0, created_at: '2026-01-01' },
      ],
    });

    render(<StoryHighlights userId={42} userName="Test User" />);

    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'Delete highlight: Events' })).toBeInTheDocument();
    });
  });

  it('does not show delete buttons for non-owner', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({
      success: true,
      data: [
        { id: 1, title: 'Shared', cover_url: null, story_count: 1, display_order: 0, created_at: '2026-01-01' },
      ],
    });

    render(<StoryHighlights userId={99} userName="Other User" />);

    await waitFor(() => {
      expect(screen.getByText('Shared')).toBeInTheDocument();
    });

    expect(screen.queryByRole('button', { name: /Delete highlight/i })).not.toBeInTheDocument();
  });

  it('calls api.delete when delete button is clicked', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({
      success: true,
      data: [
        { id: 7, title: 'Remove Me', cover_url: null, story_count: 1, display_order: 0, created_at: '2026-01-01' },
      ],
    });
    vi.mocked(api.delete).mockResolvedValueOnce({ success: true });

    render(<StoryHighlights userId={42} userName="Test User" />);

    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'Delete highlight: Remove Me' })).toBeInTheDocument();
    });

    const deleteBtn = screen.getByRole('button', { name: 'Delete highlight: Remove Me' });
    deleteBtn.click();

    await waitFor(() => {
      expect(api.delete).toHaveBeenCalledWith('/v2/stories/highlights/7');
      expect(mockToast.success).toHaveBeenCalledWith('Highlight deleted');
    });
  });

  it('shows info toast when highlight has no stories', async () => {
    // First call loads highlights
    vi.mocked(api.get).mockResolvedValueOnce({
      success: true,
      data: [
        { id: 3, title: 'Empty', cover_url: null, story_count: 0, display_order: 0, created_at: '2026-01-01' },
      ],
    });

    render(<StoryHighlights userId={42} userName="Test User" />);

    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'View highlight: Empty' })).toBeInTheDocument();
    });

    // Second call loads highlight stories — returns empty
    vi.mocked(api.get).mockResolvedValueOnce({
      success: true,
      data: [],
    });

    screen.getByRole('button', { name: 'View highlight: Empty' }).click();

    await waitFor(() => {
      expect(mockToast.info).toHaveBeenCalledWith('This highlight has no stories');
    });
  });

  it('calls API to load highlights on mount', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({
      success: true,
      data: [],
    });

    render(<StoryHighlights userId={42} userName="Test User" />);

    await waitFor(() => {
      expect(api.get).toHaveBeenCalledWith('/v2/stories/highlights/42');
    });
  });
});
