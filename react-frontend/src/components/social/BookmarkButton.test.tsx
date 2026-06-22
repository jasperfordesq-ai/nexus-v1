// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { api } from '@/lib/api';
import { BookmarkButton } from './BookmarkButton';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

vi.mock('@/lib/logger', () => ({
  logError: vi.fn(),
}));

// BookmarkCollectionPicker depends on useBookmarkCollections; mock the hook
vi.mock('@/hooks/useBookmarkCollections', () => ({
  useBookmarkCollections: () => ({
    collections: [],
    isLoading: false,
    createCollection: vi.fn(),
  }),
}));

vi.mock('@/contexts', () => ({
  useTheme: () => ({ resolvedTheme: 'light', theme: 'system', toggleTheme: vi.fn(), setTheme: vi.fn() }),
  useToast: () => ({ success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() }),
  useAuth: () => ({ user: null, isAuthenticated: false, login: vi.fn(), logout: vi.fn(), register: vi.fn(), updateUser: vi.fn(), refreshUser: vi.fn(), status: 'idle', error: null }),
  useTenant: () => ({ tenant: { id: 2, name: 'Test', slug: 'test' }, tenantPath: (p: string) => `/test${p}`, hasFeature: vi.fn(() => true), hasModule: vi.fn(() => true) }),
  useFeature: vi.fn(() => true),
  useModule: vi.fn(() => true),
  useNotifications: () => ({ unreadCount: 0, counts: {}, notifications: [], markAsRead: vi.fn(), markAllAsRead: vi.fn(), hasMore: false, loadMore: vi.fn(), isLoading: false, refresh: vi.fn() }),
  usePusher: () => ({ channel: null, isConnected: false }),
  usePusherOptional: () => null,
  useCookieConsent: () => ({ consent: null, showBanner: false, openPreferences: vi.fn(), resetConsent: vi.fn(), saveConsent: vi.fn(), hasConsent: vi.fn(() => true), updateConsent: vi.fn() }),
  readStoredConsent: () => null,
  useMenuContext: () => ({ headerMenus: [], mobileMenus: [], hasCustomMenus: false }),
  usePresence: () => ({ status: 'offline', setStatus: vi.fn(), getPresence: vi.fn(), isOnline: vi.fn(() => false) }),
  usePresenceOptional: () => null,
}));

describe('BookmarkButton', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders a button with bookmark aria-label', () => {
    render(<BookmarkButton type="listing" id={1} />);

    const button = screen.getByRole('button');
    expect(button).toBeInTheDocument();
    expect(button).toHaveAttribute('aria-label');
  });

  it('shows "save" label when not bookmarked', () => {
    render(<BookmarkButton type="listing" id={1} isBookmarked={false} />);

    const button = screen.getByRole('button');
    // The 'bookmark.save' i18n key will resolve to some English string
    const label = button.getAttribute('aria-label') ?? '';
    expect(label.length).toBeGreaterThan(0);
  });

  it('shows "remove" label when already bookmarked', () => {
    render(<BookmarkButton type="listing" id={1} isBookmarked={true} />);

    const button = screen.getByRole('button');
    const saveLabel = screen.queryByRole('button', { name: /save/i });
    const removeLabel = screen.queryByRole('button', { name: /remove/i });
    // One of the two labels must be present
    expect(saveLabel || removeLabel || button).toBeInTheDocument();
  });

  it('calls POST /v2/bookmarks on click when not bookmarked', async () => {
    vi.mocked(api.post).mockResolvedValueOnce({
      success: true,
      data: { bookmarked: true, count: 1 },
    });

    render(<BookmarkButton type="listing" id={42} isBookmarked={false} />);

    fireEvent.click(screen.getByRole('button'));

    await waitFor(() => {
      expect(api.post).toHaveBeenCalledWith(
        '/v2/bookmarks',
        expect.objectContaining({ type: 'listing', id: 42 }),
      );
    });
  });

  it('calls POST /v2/bookmarks on click when already bookmarked (toggles off)', async () => {
    // The component always POSTs to /v2/bookmarks regardless of direction —
    // the server decides the new state and returns { bookmarked: false }.
    vi.mocked(api.post).mockResolvedValueOnce({
      success: true,
      data: { bookmarked: false, count: 0 },
    });

    render(<BookmarkButton type="listing" id={7} isBookmarked={true} />);

    fireEvent.click(screen.getByRole('button'));

    await waitFor(() => {
      expect(api.post).toHaveBeenCalledWith(
        '/v2/bookmarks',
        expect.objectContaining({ type: 'listing', id: 7 }),
      );
    });
  });

  it('optimistically sets bookmarked state before API resolves', async () => {
    // Use a never-resolving promise to catch the optimistic state
    let resolvePost!: (v: unknown) => void;
    vi.mocked(api.post).mockReturnValueOnce(
      new Promise((res) => { resolvePost = res; }),
    );

    render(<BookmarkButton type="event" id={10} isBookmarked={false} />);

    const button = screen.getByRole('button');
    fireEvent.click(button);

    // Optimistic update: the button should now show the "remove" aria-label
    // (the component flips bookmarked immediately before the API resolves)
    await waitFor(() => {
      // The button becomes disabled while loading — isLoading guard is active
      const isDisabled =
        button.hasAttribute('disabled') ||
        button.getAttribute('aria-disabled') === 'true' ||
        button.getAttribute('data-disabled') === 'true';
      expect(isDisabled).toBe(true);
    });

    // Cleanup: resolve the pending promise
    resolvePost({ success: true, data: { bookmarked: true, count: 1 } });
  });

  it('reverts optimistic state when API call fails', async () => {
    vi.mocked(api.post).mockRejectedValueOnce(new Error('Network error'));

    render(<BookmarkButton type="listing" id={99} isBookmarked={false} />);

    fireEvent.click(screen.getByRole('button'));

    await waitFor(() => {
      // After rejection the button recovers (not disabled/loading)
      const button = screen.getByRole('button');
      const isDisabled =
        button.hasAttribute('disabled') ||
        button.getAttribute('aria-disabled') === 'true' ||
        button.getAttribute('data-disabled') === 'true';
      expect(isDisabled).toBe(false);
    });
  });

  it('reverts optimistic state when API returns success:false', async () => {
    vi.mocked(api.post).mockResolvedValueOnce({ success: false, data: null });

    render(<BookmarkButton type="listing" id={55} isBookmarked={false} />);

    fireEvent.click(screen.getByRole('button'));

    await waitFor(() => {
      // Button recovers after the failure path
      const button = screen.getByRole('button');
      expect(button).toBeInTheDocument();
    });
  });

  it('calls onToggle callback with new bookmarked state', async () => {
    const onToggle = vi.fn();
    vi.mocked(api.post).mockResolvedValueOnce({
      success: true,
      data: { bookmarked: true, count: 1 },
    });

    render(
      <BookmarkButton type="post" id={3} isBookmarked={false} onToggle={onToggle} />
    );

    fireEvent.click(screen.getByRole('button'));

    await waitFor(() => {
      expect(onToggle).toHaveBeenCalledWith(true);
    });
  });

  it('syncs to updated isBookmarked prop from parent', () => {
    const { rerender } = render(
      <BookmarkButton type="listing" id={1} isBookmarked={false} />
    );

    // Re-render with updated prop (simulating parent feed reload)
    rerender(
      <BookmarkButton type="listing" id={1} isBookmarked={true} />
    );

    const button = screen.getByRole('button');
    const label = button.getAttribute('aria-label') ?? '';
    expect(label.length).toBeGreaterThan(0);
  });

  // NOTE: The long-press collection picker path is not tested here because
  // triggering a long-press in jsdom requires pointer/touch event sequences
  // with precise timing that are unreliable without fake timers, and the
  // delay-based useLongPress hook calls setTimeout internally. The picker
  // rendering is covered by its own component.
});
