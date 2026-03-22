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
  const React = await import('react');
  return {
    ...actual,
    Link: ({ children, to, ...rest }: { children: ReactNode; to: string; [k: string]: unknown }) =>
      React.createElement('a', { href: String(to), ...rest }, children),
    useNavigate: () => vi.fn(),
    useParams: () => ({ id: '3' }),
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

import { FederationPartnerDetailPage } from './FederationPartnerDetailPage';

const mockPartner = {
  id: 3,
  name: 'Cork Timebank',
  slug: 'cork',
  tagline: 'A vibrant timebank community in Cork city.',
  logo_url: null,
  member_count: 80,
  federation_level: 2,
  location: 'Cork',
  is_connected: true,
  permissions: ['profiles', 'messaging', 'transactions', 'listings', 'events'],
};

describe('FederationPartnerDetailPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders partner name and description on success', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [mockPartner] });
    render(<FederationPartnerDetailPage />);
    await waitFor(() => {
      expect(screen.getAllByText('Cork Timebank').length).toBeGreaterThanOrEqual(1);
      expect(screen.getByText('A vibrant timebank community in Cork city.')).toBeInTheDocument();
    });
  });

  it('shows not-found error when partner is not in list', async () => {
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: [{ ...mockPartner, id: 99 }],
    });
    render(<FederationPartnerDetailPage />);
    await waitFor(() => {
      expect(screen.getByText('partner_detail.not_found_heading')).toBeInTheDocument();
    });
  });

  it('shows error state when API fails', async () => {
    vi.mocked(api.get).mockRejectedValue(new Error('Network error'));
    render(<FederationPartnerDetailPage />);
    await waitFor(() => {
      expect(screen.getByText('partner_detail.not_found_heading')).toBeInTheDocument();
    });
  });

  it('shows permission chips for enabled permissions', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [mockPartner] });
    render(<FederationPartnerDetailPage />);
    await waitFor(() => {
      expect(screen.getAllByText('Cork Timebank').length).toBeGreaterThanOrEqual(1);
      expect(screen.getByText('partner_detail.permission_profiles')).toBeInTheDocument();
      expect(screen.getByText('partner_detail.permission_messaging')).toBeInTheDocument();
    });
  });

  it('shows member count', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [mockPartner] });
    render(<FederationPartnerDetailPage />);
    await waitFor(() => {
      expect(screen.getByText('partner_detail.member_count')).toBeInTheDocument();
    });
  });

  it('calls the correct API endpoint to load partners list', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [mockPartner] });
    render(<FederationPartnerDetailPage />);
    await waitFor(() => {
      expect(api.get).toHaveBeenCalledWith('/v2/federation/partners');
    });
  });
});
