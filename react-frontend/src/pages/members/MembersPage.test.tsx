// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for MembersPage
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@/test/test-utils';

const { mockApiGet, mockToastError, mockUseAuth } = vi.hoisted(() => ({
  mockApiGet: vi.fn().mockResolvedValue({ success: true, data: [], meta: {} }),
  mockToastError: vi.fn(),
  mockUseAuth: vi.fn(() => ({ user: null, isAuthenticated: false, login: vi.fn(), logout: vi.fn(), register: vi.fn(), updateUser: vi.fn(), refreshUser: vi.fn(), status: 'idle', error: null })),
}));

vi.mock('@/lib/api', () => ({
  api: {
    get: mockApiGet,
    post: vi.fn().mockResolvedValue({ success: true }),
  },
  tokenManager: { getTenantId: vi.fn() },
}));

vi.mock('@/contexts', () => ({
  useToast: vi.fn(() => ({
    success: vi.fn(),
    error: mockToastError,
    info: vi.fn(),
  })),
  useTenant: vi.fn(() => ({
    tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
    tenantPath: (p: string) => `/test${p}`,
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
  useAuth: mockUseAuth,
}));

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));
vi.mock('@/lib/helpers', () => ({
  resolveAvatarUrl: vi.fn((url) => url || '/default-avatar.png'),
}));
vi.mock('@/lib/map-config', () => ({ MAPS_ENABLED: false }));
vi.mock('@/components/location', () => ({
  EntityMapView: () => <div data-testid="map-view">Map</div>,
}));
vi.mock('@/components/feedback', () => ({
  EmptyState: ({ title }: { title: string }) => <div data-testid="empty-state">{title}</div>,
}));
vi.mock('framer-motion', () => ({
  motion: {
    div: ({ children, ...props }: Record<string, unknown>) => {
      const motionKeys = new Set(["variants", "initial", "animate", "transition", "whileInView", "viewport", "layout", "exit", "whileHover", "whileTap"]);
      const rest: Record<string, unknown> = {};
      for (const [k, v] of Object.entries(props)) { if (!motionKeys.has(k)) rest[k] = v; }
      return <div {...rest}>{children}</div>;
    },
  },
  AnimatePresence: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

import { MembersPage } from './MembersPage';

describe('MembersPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockApiGet.mockResolvedValue({ success: true, data: [], meta: {} });
    mockUseAuth.mockReturnValue({ user: null, isAuthenticated: false, login: vi.fn(), logout: vi.fn(), register: vi.fn(), updateUser: vi.fn(), refreshUser: vi.fn(), status: 'idle', error: null });
  });

  it('renders without crashing', () => {
    render(<MembersPage />);
    expect(screen.getByText('Members')).toBeInTheDocument();
  });

  it('shows search input', () => {
    render(<MembersPage />);
    expect(screen.getByPlaceholderText(/Search members/i)).toBeInTheDocument();
  });

  it('shows view mode buttons', () => {
    render(<MembersPage />);
    expect(screen.getByLabelText('Grid view')).toBeInTheDocument();
    expect(screen.getByLabelText('List view')).toBeInTheDocument();
  });

  it('allows Near me when a user has zero coordinates', async () => {
    mockUseAuth.mockReturnValue({
      user: { id: 7, latitude: 0, longitude: 0 },
      isAuthenticated: true,
      login: vi.fn(),
      logout: vi.fn(),
      register: vi.fn(),
      updateUser: vi.fn(),
      refreshUser: vi.fn(),
      status: 'idle',
      error: null,
    });

    render(<MembersPage />);

    fireEvent.click(screen.getByRole('button', { name: 'Near me' }));

    await waitFor(() =>
      expect(mockApiGet).toHaveBeenLastCalledWith(expect.stringContaining('/v2/members/nearby?'))
    );
    expect(mockApiGet).toHaveBeenLastCalledWith(expect.stringContaining('lat=0'));
    expect(mockApiGet).toHaveBeenLastCalledWith(expect.stringContaining('lon=0'));
    expect(mockToastError).not.toHaveBeenCalled();
  });

  it('uses translation keys without inline fallback text on the page shell', () => {
    render(<MembersPage />);

    expect(screen.getByRole('button', { name: 'Near me' })).toBeInTheDocument();
    expect(screen.queryByText('members.near_me')).not.toBeInTheDocument();
  });
});
