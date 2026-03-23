// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for SubAccountsManager component
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
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
  warning: vi.fn(),
};

vi.mock('@/contexts', () => ({
  useToast: vi.fn(() => mockToast),
  useAuth: vi.fn(() => ({
    isAuthenticated: true,
    user: { id: 1, name: 'Test User', role: 'user' },
    login: vi.fn(),
    logout: vi.fn(),
    register: vi.fn(),
    updateUser: vi.fn(),
    refreshUser: vi.fn(),
    status: 'idle',
    error: null,
  })),
  useTenant: vi.fn(() => ({
    tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
    branding: { name: 'Test', logo_url: null },
    tenantSlug: 'test',
    tenantPath: (p: string) => '/test' + p,
    isLoading: false,
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
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

vi.mock('@/lib/logger', () => ({
  logError: vi.fn(),
}));

vi.mock('@/lib/helpers', () => ({
  resolveAvatarUrl: vi.fn((url: string | undefined) => url || '/default-avatar.png'),
}));

import { SubAccountsManager } from '../SubAccountsManager';

const mockSubAccounts = [
  {
    id: 1,
    child_user_id: 10,
    child_name: 'Child One',
    child_email: 'child1@example.com',
    child_avatar: undefined,
    status: 'approved' as const,
    permissions: {
      can_post: true,
      can_message: true,
      can_exchange: false,
      can_join_events: true,
      can_join_groups: false,
    },
    created_at: '2026-01-01T00:00:00Z',
  },
  {
    id: 2,
    child_user_id: 11,
    child_name: 'Child Two',
    child_email: 'child2@example.com',
    child_avatar: undefined,
    status: 'pending' as const,
    permissions: {
      can_post: false,
      can_message: false,
      can_exchange: false,
      can_join_events: false,
      can_join_groups: false,
    },
    created_at: '2026-01-02T00:00:00Z',
  },
];

describe('SubAccountsManager', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders loading spinner initially', () => {
    vi.mocked(api.get).mockImplementation(() => new Promise(() => {}));

    render(<SubAccountsManager />);
    expect(screen.getByLabelText('Loading')).toBeInTheDocument();
  });

  it('renders header with title and add button', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: [] });

    render(<SubAccountsManager />);

    await waitFor(() => {
      expect(screen.getByText('Linked Accounts')).toBeInTheDocument();
      expect(screen.getByText('Add Account')).toBeInTheDocument();
    });
  });

  it('renders empty state when no sub-accounts', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: [] });

    render(<SubAccountsManager />);

    await waitFor(() => {
      expect(screen.getByText('No linked accounts')).toBeInTheDocument();
    });
  });

  it('renders sub-accounts list after loading', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: mockSubAccounts });

    render(<SubAccountsManager />);

    await waitFor(() => {
      expect(screen.getByText('Child One')).toBeInTheDocument();
      expect(screen.getByText('Child Two')).toBeInTheDocument();
    });
  });

  it('shows status chips for each account', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: mockSubAccounts });

    render(<SubAccountsManager />);

    await waitFor(() => {
      expect(screen.getByText('Active')).toBeInTheDocument();
      expect(screen.getByText('Pending')).toBeInTheDocument();
    });
  });

  it('shows email for each account', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: mockSubAccounts });

    render(<SubAccountsManager />);

    await waitFor(() => {
      expect(screen.getByText('child1@example.com')).toBeInTheDocument();
      expect(screen.getByText('child2@example.com')).toBeInTheDocument();
    });
  });

  it('shows permissions section for approved accounts', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: mockSubAccounts });

    render(<SubAccountsManager />);

    await waitFor(() => {
      expect(screen.getByText('Permissions')).toBeInTheDocument();
    });
  });

  it('shows approve/decline buttons for pending accounts', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: mockSubAccounts });

    render(<SubAccountsManager />);

    await waitFor(() => {
      expect(screen.getByText('Approve')).toBeInTheDocument();
      expect(screen.getByText('Decline')).toBeInTheDocument();
    });
  });

  it('shows error state on API failure', async () => {
    vi.mocked(api.get).mockRejectedValueOnce(new Error('Network error'));

    render(<SubAccountsManager />);

    await waitFor(() => {
      expect(screen.getByText('Failed to load linked accounts')).toBeInTheDocument();
      expect(screen.getByText('Retry')).toBeInTheDocument();
    });
  });

  it('shows description text', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: [] });

    render(<SubAccountsManager />);

    await waitFor(() => {
      expect(screen.getByText(/Link accounts for family members/)).toBeInTheDocument();
    });
  });
});
