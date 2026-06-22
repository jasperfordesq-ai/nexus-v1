// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

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

vi.mock('@/lib/logger', () => ({
  logError: vi.fn(),
}));

// hasFeature is a vi.fn so individual tests can override its return value
const mockHasFeature = vi.fn(() => true);

vi.mock('@/contexts', () => ({
  useAuth: () => ({
    user: null, isAuthenticated: false, login: vi.fn(), logout: vi.fn(),
    register: vi.fn(), updateUser: vi.fn(), refreshUser: vi.fn(),
    status: 'idle' as const, error: null,
  }),
  useTenant: () => ({
    tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
    branding: { name: 'Test Tenant' },
    tenantSlug: 'test',
    tenantPath: (p: string) => `/test${p}`,
    isLoading: false,
    hasFeature: mockHasFeature,
    hasModule: vi.fn(() => true),
  }),
  useToast: () => ({ success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() }),
  useTheme: () => ({ resolvedTheme: 'light', theme: 'system', toggleTheme: vi.fn(), setTheme: vi.fn() }),
  useNotifications: () => ({
    unreadCount: 0, counts: {}, notifications: [], markAsRead: vi.fn(),
    markAllAsRead: vi.fn(), hasMore: false, loadMore: vi.fn(), isLoading: false, refresh: vi.fn(),
  }),
  usePusher: () => ({ channel: null, isConnected: false }),
  usePusherOptional: () => null,
  useCookieConsent: () => ({
    consent: null, showBanner: false, openPreferences: vi.fn(), resetConsent: vi.fn(),
    saveConsent: vi.fn(), hasConsent: vi.fn(() => true), updateConsent: vi.fn(),
  }),
  readStoredConsent: () => null,
  useMenuContext: () => ({ headerMenus: [], mobileMenus: [], hasCustomMenus: false }),
  useFeature: vi.fn(() => true),
  useModule: vi.fn(() => true),
  usePresence: () => ({ status: 'offline', setStatus: vi.fn(), getPresence: vi.fn(), isOnline: vi.fn(() => false) }),
  usePresenceOptional: () => null,
}));

import MySafeguardingReportsPage from './MySafeguardingReportsPage';

// Real English strings from public/locales/en/common.json
const TITLE = 'My Safeguarding Reports';
const EMPTY_TEXT = "You haven't submitted any reports.";
const ERROR_TEXT = 'Failed to load reports';
const ESCALATED_TEXT = 'Escalated';

const MOCK_REPORTS = [
  {
    id: 1,
    category: 'neglect',
    severity: 'high' as const,
    description_preview: 'Neighbour reported aggressive behaviour.',
    status: 'investigating' as const,
    review_due_at: '2026-07-01T00:00:00Z',
    escalated: false,
    resolved_at: null,
    created_at: '2026-06-01T12:00:00Z',
  },
  {
    id: 2,
    category: 'neglect',
    severity: 'critical' as const,
    description_preview: 'Urgent welfare concern.',
    status: 'resolved' as const,
    review_due_at: null,
    escalated: true,
    resolved_at: '2026-06-10T09:00:00Z',
    created_at: '2026-06-05T08:00:00Z',
  },
];

describe('MySafeguardingReportsPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockHasFeature.mockReturnValue(true);
  });

  it('shows a loading spinner while fetching', () => {
    vi.mocked(api.get).mockReturnValue(new Promise(() => {}));
    render(<MySafeguardingReportsPage />);
    // The page renders a <div role="status" aria-busy="true"> for the loading state.
    // (HeroUI Spinner and ToastProvider also emit role="status" nodes — filter by aria-busy.)
    const statusNodes = screen.getAllByRole('status');
    const loadingNode = statusNodes.find((n) => n.getAttribute('aria-busy') === 'true');
    expect(loadingNode).toBeInTheDocument();
  });

  it('renders the page heading after load', async () => {
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: { items: MOCK_REPORTS },
    });
    render(<MySafeguardingReportsPage />);
    await waitFor(() => {
      expect(screen.getByRole('heading', { level: 1, name: TITLE })).toBeInTheDocument();
    });
  });

  it('renders a list of reports when the API succeeds', async () => {
    vi.mocked(api.get).mockImplementation((url: string) => {
      if (url === '/v2/caring-community/safeguarding/my-reports') {
        return Promise.resolve({ success: true, data: { items: MOCK_REPORTS } });
      }
      return Promise.resolve({ success: true, data: {} });
    });

    render(<MySafeguardingReportsPage />);

    await waitFor(() => {
      expect(screen.getByText('Neighbour reported aggressive behaviour.')).toBeInTheDocument();
    });
    expect(screen.getByText('Urgent welfare concern.')).toBeInTheDocument();
  });

  it('shows the Escalated chip for escalated reports', async () => {
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: { items: MOCK_REPORTS },
    });

    render(<MySafeguardingReportsPage />);

    await waitFor(() => {
      expect(screen.getByText(ESCALATED_TEXT)).toBeInTheDocument();
    });
  });

  it('shows empty state when the API returns an empty list', async () => {
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: { items: [] },
    });

    render(<MySafeguardingReportsPage />);

    await waitFor(() => {
      expect(screen.getByText(EMPTY_TEXT)).toBeInTheDocument();
    });
  });

  it('shows an error alert when the API returns a non-success response', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: false, data: null });

    render(<MySafeguardingReportsPage />);

    await waitFor(() => {
      // The page renders an inline <div role="alert"> with the error text.
      // (ToastProvider may also inject a role="alert" portal — use text query.)
      expect(screen.getByText(ERROR_TEXT)).toBeInTheDocument();
    });
    // The aria-busy loading state should be gone after the error state renders
    const statusNodes = screen.queryAllByRole('status');
    const loadingNodes = statusNodes.filter((n) => n.getAttribute('aria-busy') === 'true');
    expect(loadingNodes).toHaveLength(0);
  });

  it('shows an error alert when the API call throws', async () => {
    vi.mocked(api.get).mockRejectedValue(new Error('Network error'));

    render(<MySafeguardingReportsPage />);

    await waitFor(() => {
      expect(screen.getByText(ERROR_TEXT)).toBeInTheDocument();
    });
  });

  // When caring_community feature is disabled the page renders <Navigate />.
  // BrowserRouter in test-utils is at '/', so the redirect executes silently;
  // the page heading must not appear.
  it('does not render the page heading when caring_community feature is disabled', () => {
    mockHasFeature.mockReturnValue(false);
    // No api mock needed — redirect fires before any fetch
    render(<MySafeguardingReportsPage />);
    expect(screen.queryByRole('heading', { name: TITLE })).not.toBeInTheDocument();
  });
});
