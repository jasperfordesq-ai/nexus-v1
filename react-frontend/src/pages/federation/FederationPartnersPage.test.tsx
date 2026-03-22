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

import { FederationPartnersPage } from './FederationPartnersPage';

const mockPartners = [
  {
    id: 3,
    name: 'Cork Timebank',
    slug: 'cork',
    description: 'A community timebank in Cork city.',
    logo_url: null,
    member_count: 80,
    federation_level: 2,
    location: 'Cork',
    is_connected: true,
    permissions: { can_view_members: true, can_message: true, can_transact: true },
  },
  {
    id: 4,
    name: 'Galway Exchange',
    slug: 'galway',
    description: null,
    logo_url: null,
    member_count: 45,
    federation_level: 1,
    location: 'Galway',
    is_connected: false,
    permissions: { can_view_members: true, can_message: false, can_transact: false },
  },
];

describe('FederationPartnersPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders partner community names on success', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: mockPartners });
    render(<FederationPartnersPage />);
    await waitFor(() => {
      expect(screen.getByText('Cork Timebank')).toBeInTheDocument();
    });
    expect(screen.getByText('Galway Exchange')).toBeInTheDocument();
  });

  it('shows error state when API fails', async () => {
    vi.mocked(api.get).mockRejectedValue(new Error('Network error'));
    render(<FederationPartnersPage />);
    await waitFor(() => {
      expect(screen.getByText('partners.load_error')).toBeInTheDocument();
    });
  });

  it('shows empty state when no partners exist', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [] });
    render(<FederationPartnersPage />);
    await waitFor(() => {
      expect(screen.queryByText('Cork Timebank')).not.toBeInTheDocument();
    });
    expect(screen.getByText('partners.empty_title')).toBeInTheDocument();
  });

  it('shows partner member count', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: mockPartners });
    render(<FederationPartnersPage />);
    await waitFor(() => {
      expect(screen.getAllByText('partners.member_count').length).toBeGreaterThanOrEqual(1);
    });
  });

  it('shows federation level badges', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: mockPartners });
    render(<FederationPartnersPage />);
    await waitFor(() => {
      expect(screen.getByText('Social')).toBeInTheDocument();
    });
    expect(screen.getByText('Discovery')).toBeInTheDocument();
  });

  it('calls the correct API endpoint on mount', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [] });
    render(<FederationPartnersPage />);
    await waitFor(() => {
      expect(api.get).toHaveBeenCalledWith(
        expect.stringContaining('/v2/federation/partners'),
      );
    });
  });
});
