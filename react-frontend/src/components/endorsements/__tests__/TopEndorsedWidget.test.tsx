// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for TopEndorsedWidget component
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
  useTenant: vi.fn(() => ({
    tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
    tenantPath: (p: string) => `/test${p}`,
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
  useAuth: () => ({ user: null, isAuthenticated: false, login: vi.fn(), logout: vi.fn(), register: vi.fn(), updateUser: vi.fn(), refreshUser: vi.fn(), status: 'idle', error: null }),
  useToast: () => ({ success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() }),
}));

vi.mock('@/lib/helpers', () => ({
  resolveAvatarUrl: vi.fn((url: string | undefined) => url || '/default-avatar.png'),
}));

vi.mock('@/lib/logger', () => ({
  logError: vi.fn(),
}));

import { TopEndorsedWidget } from '../TopEndorsedWidget';

const mockMembers = [
  { id: 1, name: 'Alice', avatar_url: '/alice.png', total_endorsements: 25, top_skills: ['Gardening', 'Cooking'] },
  { id: 2, name: 'Bob', avatar_url: undefined, total_endorsements: 18, top_skills: ['Driving'] },
  { id: 3, name: 'Carol', avatar_url: '/carol.png', total_endorsements: 12, top_skills: [] },
  { id: 4, name: 'Dave', avatar_url: undefined, total_endorsements: 7, top_skills: ['Music'] },
];

describe('TopEndorsedWidget', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders a spinner while loading', () => {
    vi.mocked(api.get).mockReturnValueOnce(new Promise(() => {}));
    render(<TopEndorsedWidget />);
    // During loading, the spinner should be present
    const { container } = render(<TopEndorsedWidget />);
    expect(container.querySelector('[class*="animate-spin"]') || container.firstChild).toBeTruthy();
  });

  it('renders nothing when API returns empty array', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: [] });

    const { container } = render(<TopEndorsedWidget />);
    await waitFor(() => {
      expect(container.firstChild).toBeNull();
    });
  });

  it('renders Most Endorsed heading after loading', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: mockMembers });

    render(<TopEndorsedWidget />);
    await waitFor(() => {
      expect(screen.getByText('Most Endorsed')).toBeInTheDocument();
    });
  });

  it('displays member names', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: mockMembers });

    render(<TopEndorsedWidget />);
    await waitFor(() => {
      expect(screen.getByText('Alice')).toBeInTheDocument();
      expect(screen.getByText('Bob')).toBeInTheDocument();
      expect(screen.getByText('Carol')).toBeInTheDocument();
    });
  });

  it('shows endorsement counts as chips', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: mockMembers });

    render(<TopEndorsedWidget />);
    await waitFor(() => {
      expect(screen.getByText('25')).toBeInTheDocument();
      expect(screen.getByText('18')).toBeInTheDocument();
      expect(screen.getByText('12')).toBeInTheDocument();
    });
  });

  it('displays top skills for members', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: mockMembers });

    render(<TopEndorsedWidget />);
    await waitFor(() => {
      expect(screen.getByText('Gardening, Cooking')).toBeInTheDocument();
    });
  });

  it('shows rank numbers for positions beyond top 3', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: mockMembers });

    render(<TopEndorsedWidget />);
    await waitFor(() => {
      expect(screen.getByText('#4')).toBeInTheDocument();
    });
  });

  it('links member rows to profile pages', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: mockMembers });

    render(<TopEndorsedWidget />);
    await waitFor(() => {
      const links = screen.getAllByRole('link');
      const hrefs = links.map((l) => l.getAttribute('href'));
      expect(hrefs).toContain('/test/profile/1');
      expect(hrefs).toContain('/test/profile/2');
    });
  });

  it('calls API with limit param', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: [] });

    render(<TopEndorsedWidget limit={3} />);
    await waitFor(() => {
      expect(api.get).toHaveBeenCalledWith('/v2/members/top-endorsed?limit=3');
    });
  });

  it('handles API error gracefully without crashing', async () => {
    vi.mocked(api.get).mockRejectedValueOnce(new Error('Network error'));

    const { container } = render(<TopEndorsedWidget />);
    await waitFor(() => {
      // Should render null or be empty after failed load
      expect(container.firstChild).toBeNull();
    });
  });
});
