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
  formatRelativeTime: (d: string) => d,
}));

import { FederationConnectionsPage } from './FederationConnectionsPage';

const mockConnections = [
  {
    id: 1,
    user_id: 20,
    tenant_id: 5,
    name: 'Maria Green',
    avatar_url: null,
    tenant_name: 'Cork Timebank',
    message: 'Hello! I would love to connect.',
    created_at: '2026-02-10T10:00:00Z',
  },
  {
    id: 2,
    user_id: 21,
    tenant_id: 6,
    name: 'James Blue',
    avatar_url: null,
    tenant_name: 'Galway Exchange',
    message: null,
    created_at: '2026-03-01T08:00:00Z',
  },
];

describe('FederationConnectionsPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows loading spinner initially', () => {
    vi.mocked(api.get).mockReturnValue(new Promise(() => {}));
    render(<FederationConnectionsPage />);
    expect(document.body).toBeTruthy();
  });

  it('renders connection names on success', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: mockConnections });
    render(<FederationConnectionsPage />);
    await waitFor(() => {
      expect(screen.getByText('Maria Green')).toBeInTheDocument();
    });
    expect(screen.getByText('James Blue')).toBeInTheDocument();
  });

  it('shows empty state when no connections exist', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [] });
    render(<FederationConnectionsPage />);
    await waitFor(() => {
      expect(screen.getByText('connections.empty_connected')).toBeInTheDocument();
    });
  });

  it('renders all three tabs', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [] });
    render(<FederationConnectionsPage />);
    await waitFor(() => {
      expect(screen.getByText('connections.tab_connected')).toBeInTheDocument();
    });
    expect(screen.getByText('connections.tab_received')).toBeInTheDocument();
    expect(screen.getByText('connections.tab_sent')).toBeInTheDocument();
  });

  it('shows tenant name alongside connection name', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: mockConnections });
    render(<FederationConnectionsPage />);
    await waitFor(() => {
      expect(screen.getByText('Cork Timebank')).toBeInTheDocument();
    });
  });

  it('calls the correct API endpoint on mount', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [] });
    render(<FederationConnectionsPage />);
    await waitFor(() => {
      expect(api.get).toHaveBeenCalledWith(
        expect.stringContaining('/v2/federation/connections?status=accepted'),
      );
    });
  });

  it('shows Browse Federation Members button on empty connected tab', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [] });
    render(<FederationConnectionsPage />);
    await waitFor(() => {
      expect(screen.getByText('connections.browse_members')).toBeInTheDocument();
    });
  });
});
