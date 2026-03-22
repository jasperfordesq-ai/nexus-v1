// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for GroupSelector component
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

vi.mock('@/contexts', () => ({
  useAuth: vi.fn(() => ({
    isAuthenticated: true,
    user: { id: 1, first_name: 'Alice', username: 'alice' },
  })),

  useTheme: () => ({ resolvedTheme: 'light', toggleTheme: vi.fn(), theme: 'system', setTheme: vi.fn() }),
  useNotifications: () => ({ unreadCount: 0, counts: {}, notifications: [], markAsRead: vi.fn(), markAllAsRead: vi.fn(), hasMore: false, loadMore: vi.fn(), isLoading: false, refresh: vi.fn() }),
  usePusher: () => ({ channel: null, isConnected: false }),
  usePusherOptional: () => null,
  useCookieConsent: () => ({ consent: null, showBanner: false, openPreferences: vi.fn(), resetConsent: vi.fn(), saveConsent: vi.fn(), hasConsent: vi.fn(() => true), updateConsent: vi.fn() }),
  readStoredConsent: () => null,
  useMenuContext: () => ({ headerMenus: [], mobileMenus: [], hasCustomMenus: false }),
  useFeature: vi.fn(() => true),
  useModule: vi.fn(() => true),
  useToast: () => ({ success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() }),
  useTenant: () => ({ tenant: { id: 2, name: 'Test', slug: 'test', tagline: null }, branding: { name: 'Test', logo_url: null }, tenantSlug: 'test', tenantPath: (p) => '/test' + p, isLoading: false, hasFeature: vi.fn(() => true), hasModule: vi.fn(() => true) }),
}));

vi.mock('@/lib/logger', () => ({
  logError: vi.fn(),
}));

import { GroupSelector } from '../GroupSelector';

const mockGroups = [
  { id: 1, name: 'Gardeners Group', member_count: 30 },
  { id: 2, name: 'Tech Skills', member_count: undefined },
];

describe('GroupSelector', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders nothing when user is null', async () => {
    const { useAuth } = await import('@/contexts');
    vi.mocked(useAuth).mockReturnValueOnce({
      isAuthenticated: false,
      user: null,
    } as ReturnType<typeof useAuth>);

    // No userId → no API call → renders nothing
    const { container } = render(<GroupSelector value={null} onChange={vi.fn()} />);
    await waitFor(() => {
      expect(container.firstChild).toBeNull();
    });
  });

  it('renders nothing when groups are empty after API load', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: [] });

    const { container } = render(<GroupSelector value={null} onChange={vi.fn()} />);
    await waitFor(() => {
      expect(container.firstChild).toBeNull();
    });
  });

  it('fetches groups for the authenticated user', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: mockGroups });

    render(<GroupSelector value={null} onChange={vi.fn()} />);
    await waitFor(() => {
      expect(api.get).toHaveBeenCalledWith('/v2/groups?user_id=1&limit=50');
    });
  });

  it('renders a Select element after groups are loaded', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: mockGroups });

    render(<GroupSelector value={null} onChange={vi.fn()} />);
    await waitFor(() => {
      // The HeroUI Select renders a listbox trigger
      expect(screen.getByRole('button') || screen.getByRole('combobox')).toBeTruthy();
    });
  });

  it('handles API error gracefully and renders nothing', async () => {
    vi.mocked(api.get).mockRejectedValueOnce(new Error('Network error'));

    const { container } = render(<GroupSelector value={null} onChange={vi.fn()} />);
    await waitFor(() => {
      expect(container.firstChild).toBeNull();
    });
  });

  it('handles API response where data is null', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: false, data: null });

    const { container } = render(<GroupSelector value={null} onChange={vi.fn()} />);
    await waitFor(() => {
      expect(container.firstChild).toBeNull();
    });
  });
});
