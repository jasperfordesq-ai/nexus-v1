// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import { api } from '@/lib/api';
import type { ReactNode } from 'react';

vi.mock('framer-motion', () => ({
  motion: {
    div: ({ children, ...props }: { children?: ReactNode; [k: string]: unknown }) => {
      const { variants: _v, initial: _i, animate: _a, exit: _e, transition: _t, ...rest } = props as Record<string, unknown>;
      return <div {...rest}>{children}</div>;
    },
  },
  AnimatePresence: ({ children }: { children?: ReactNode }) => <>{children}</>,
}));

vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, opts?: Record<string, unknown>) =>
      (opts?.defaultValue as string | undefined) ?? key,
  }),
}));

vi.mock('react-router-dom', async () => {
  const actual = await import('react-router-dom');
  return {
    ...actual,
    useNavigate: () => vi.fn(),
  };
});

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
    user: { id: 1, first_name: 'Test', name: 'Test User', role: 'member' },
    isAuthenticated: true,
  })),
  useTenant: vi.fn(() => ({
    tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
    tenantPath: (p: string) => `/test${p}`,
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
  })),
  useToast: vi.fn(() => ({
    success: vi.fn(),
    error: vi.fn(),
    info: vi.fn(),
    warning: vi.fn(),
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
}));

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));
vi.mock('@/lib/helpers', () => ({
  resolveAvatarUrl: (url: string | null) => url ?? '',
}));

import { FederationMembersPage } from './FederationMembersPage';

const mockMember = {
  id: 20,
  first_name: 'Maria',
  last_name: 'Green',
  name: 'Maria Green',
  avatar_url: null,
  bio: 'Community volunteer.',
  skills: ['Gardening'],
  location: 'Cork',
  service_reach: 'local_only',
  is_remote: false,
  travel_radius_km: 0,
  timebank: { id: 5, name: 'Cork Timebank', slug: 'cork' },
  average_rating: 4.5,
  total_exchanges: 18,
  show_skills_federated: true,
  show_location_federated: true,
  show_reviews_federated: true,
};

const mockPartner = {
  id: 5,
  name: 'Cork Timebank',
  slug: 'cork',
  description: null,
  logo_url: null,
  member_count: 80,
  is_connected: true,
  permissions: { can_view_members: true, can_message: true, can_transact: true },
};

function setupMocks() {
  vi.mocked(api.get).mockImplementation((url: string) => {
    if (url.includes('/v2/federation/partners')) {
      return Promise.resolve({ success: true, data: [mockPartner] });
    }
    if (url.includes('/v2/federation/members')) {
      return Promise.resolve({
        success: true,
        data: [mockMember], meta: { cursor: null, has_more: false },
      });
    }
    return Promise.resolve({ success: true, data: [] });
  });
}

describe('FederationMembersPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders member names on success', async () => {
    setupMocks();
    render(<FederationMembersPage />);
    await waitFor(() => {
      expect(screen.getByText('Maria Green')).toBeInTheDocument();
    });
  });

  it('shows partner timebank name alongside member', async () => {
    setupMocks();
    render(<FederationMembersPage />);
    await waitFor(() => {
      expect(screen.getAllByText('Cork Timebank').length).toBeGreaterThanOrEqual(1);
    });
  });

  it('shows error state when API fails', async () => {
    vi.mocked(api.get).mockImplementation((url: string) => {
      if (url.includes('/v2/federation/members')) {
        return Promise.reject(new Error('Network error'));
      }
      return Promise.resolve({ success: true, data: [] });
    });
    render(<FederationMembersPage />);
    await waitFor(() => {
      expect(screen.getByText('members.load_error')).toBeInTheDocument();
    });
  });

  it('shows empty state when no members exist', async () => {
    vi.mocked(api.get).mockImplementation((url: string) => {
      if (url.includes('/v2/federation/members')) {
        return Promise.resolve({
          success: true,
          data: [], meta: { cursor: null, has_more: false },
        });
      }
      if (url.includes('/v2/federation/partners')) {
        return Promise.resolve({ success: true, data: [mockPartner] });
      }
      return Promise.resolve({ success: true, data: [] });
    });
    render(<FederationMembersPage />);
    await waitFor(() => {
      expect(screen.queryByText('Maria Green')).not.toBeInTheDocument();
      expect(screen.getByText('members.no_members_found')).toBeInTheDocument();
    });
  });

  it('shows Load More when has_more is true', async () => {
    vi.mocked(api.get).mockImplementation((url: string) => {
      if (url.includes('/v2/federation/members')) {
        return Promise.resolve({
          success: true,
          data: [mockMember], meta: { cursor: 'cursor-next', has_more: true },
        });
      }
      return Promise.resolve({ success: true, data: [] });
    });
    render(<FederationMembersPage />);
    await waitFor(() => {
      expect(screen.getByText('members.load_more')).toBeInTheDocument();
    });
  });

  it('calls the correct API endpoints on mount', async () => {
    setupMocks();
    render(<FederationMembersPage />);
    await waitFor(() => {
      expect(api.get).toHaveBeenCalledWith(
        expect.stringContaining('/v2/federation/members'),
      );
    });
    expect(api.get).toHaveBeenCalledWith(
      expect.stringContaining('/v2/federation/partners'),
    );
  });
});
