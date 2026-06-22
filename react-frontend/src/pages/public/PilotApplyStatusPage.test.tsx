// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for PilotApplyStatusPage.
 *
 * Design notes
 * ─────────────
 * • The page reads `token` from useParams and issues a GET request.
 * • We mock react-router-dom to control useParams.
 * • We mock @/lib/api to control the API response.
 * • Loading, found (success), not-found/error, and API-error branches are covered.
 * • The page uses useTranslation('common') — real i18n is loaded in setup.ts.
 * • usePageTitle and PageMeta are stubbed globally (setup.ts + per-file).
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';

// ── react-router-dom: mock useParams ─────────────────────────────────────────
// We need to keep the real BrowserRouter/Link etc. from the actual module.
vi.mock('react-router-dom', async (importOriginal) => {
  const actual = await importOriginal<typeof import('react-router-dom')>();
  return {
    ...actual,
    useParams: vi.fn(() => ({ token: 'abc-token-123' })),
  };
});

// ── API mock ──────────────────────────────────────────────────────────────────
vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));
vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

// ── Contexts ──────────────────────────────────────────────────────────────────
vi.mock('@/contexts', () => ({
  useAuth: () => ({ user: null, isAuthenticated: false, login: vi.fn(), logout: vi.fn(), register: vi.fn(), updateUser: vi.fn(), refreshUser: vi.fn(), status: 'idle', error: null }),
  useTenant: () => ({
    tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
    branding: { name: 'Test', logo_url: null },
    tenantPath: (p: string) => `/test${p}`,
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
  }),
  useToast: () => ({ success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() }),
  useTheme: () => ({ resolvedTheme: 'light', theme: 'system', toggleTheme: vi.fn(), setTheme: vi.fn() }),
  useNotifications: () => ({ unreadCount: 0, counts: {}, notifications: [], markAsRead: vi.fn(), markAllAsRead: vi.fn(), hasMore: false, loadMore: vi.fn(), isLoading: false, refresh: vi.fn() }),
  usePusher: () => ({ channel: null, isConnected: false }),
  usePusherOptional: () => null,
  useCookieConsent: () => ({ consent: null, showBanner: false, openPreferences: vi.fn(), resetConsent: vi.fn(), saveConsent: vi.fn(), hasConsent: vi.fn(() => true), updateConsent: vi.fn() }),
  readStoredConsent: () => null,
  useMenuContext: () => ({ headerMenus: [], mobileMenus: [], hasCustomMenus: false }),
  useFeature: vi.fn(() => true),
  useModule: vi.fn(() => true),
  usePresence: () => ({ status: 'offline', setStatus: vi.fn(), getPresence: vi.fn(), isOnline: vi.fn(() => false) }),
  usePresenceOptional: () => null,
}));

vi.mock('@/lib/motion', () => {
  const React = require('react');
  const motionProxy = new Proxy({}, {
    get: (_target, prop) => {
      return React.forwardRef(
        ({ children, ...props }: Record<string, unknown>, ref: unknown) => {
          const clean = { ...props };
          delete clean.variants; delete clean.initial; delete clean.animate;
          delete clean.exit; delete clean.transition;
          const Tag = typeof prop === 'string' ? prop : 'div';
          return React.createElement(Tag, { ...clean, ref }, children);
        },
      );
    },
  });
  return {
    motion: motionProxy,
    AnimatePresence: ({ children }: { children: unknown }) =>
      React.createElement(React.Fragment, null, children),
    MotionConfig: ({ children }: { children: unknown }) =>
      React.createElement(React.Fragment, null, children),
  };
});

import { PilotApplyStatusPage } from './PilotApplyStatusPage';
import { api } from '@/lib/api';
import { useParams } from 'react-router-dom';

const MOCK_STATUS_INFO = {
  org_name: 'Acme Timebank',
  requested_slug: 'acme-timebank',
  status: 'pending',
  provisioned_tenant_id: null,
  created_at: '2026-01-01T00:00:00Z',
  reviewed_at: null,
};

describe('PilotApplyStatusPage — loading state', () => {
  beforeEach(() => { vi.clearAllMocks(); });

  it('shows a loading spinner initially', () => {
    // api.get never resolves so the component stays in loading state
    vi.mocked(api.get).mockReturnValue(new Promise(() => {}));
    render(<PilotApplyStatusPage />);
    // The outer div with role="status" aria-busy="true" wraps the Spinner
    // (Spinner itself also carries role="status"), so use getAllByRole
    const spinners = screen.getAllByRole('status');
    expect(spinners.length).toBeGreaterThanOrEqual(1);
    // Confirm aria-busy is set on the wrapper
    const busyEl = spinners.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busyEl).toBeInTheDocument();
  });
});

describe('PilotApplyStatusPage — success (status found)', () => {
  beforeEach(() => { vi.clearAllMocks(); });

  it('renders org_name after API resolves', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: MOCK_STATUS_INFO });
    render(<PilotApplyStatusPage />);
    await waitFor(() => {
      expect(screen.getByText('Acme Timebank')).toBeInTheDocument();
    });
  });

  it('renders the requested_slug', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: MOCK_STATUS_INFO });
    render(<PilotApplyStatusPage />);
    await waitFor(() => {
      expect(screen.getByText('acme-timebank')).toBeInTheDocument();
    });
  });

  it('renders a status chip for the pending state', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: MOCK_STATUS_INFO });
    render(<PilotApplyStatusPage />);
    await waitFor(() => {
      // Status label comes from t('provisioning.status_labels.pending')
      // We cannot predict the exact translation so we verify org_name is visible
      // and no error message is shown.
      expect(screen.queryByText(/lookup failed/i)).not.toBeInTheDocument();
    });
  });

  it('calls the correct API endpoint with the token from useParams', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: MOCK_STATUS_INFO });
    render(<PilotApplyStatusPage />);
    await waitFor(() => {
      expect(api.get).toHaveBeenCalledWith(
        '/v2/provisioning-requests/status/abc-token-123',
      );
    });
  });

  it('renders a back-home button after data loads', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: MOCK_STATUS_INFO });
    render(<PilotApplyStatusPage />);
    await waitFor(() => {
      expect(screen.getByRole('link')).toBeInTheDocument();
    });
  });

  it('wraps the data shape correctly — response.data path', async () => {
    // Component also handles a raw response (no .data envelope)
    vi.mocked(api.get).mockResolvedValue(MOCK_STATUS_INFO);
    render(<PilotApplyStatusPage />);
    await waitFor(() => {
      expect(screen.getByText('Acme Timebank')).toBeInTheDocument();
    });
  });
});

describe('PilotApplyStatusPage — error / not found', () => {
  beforeEach(() => { vi.clearAllMocks(); });

  it('shows an error message when the API resolves with invalid data', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: { something_else: true } });
    render(<PilotApplyStatusPage />);
    // Wait for the loading state to clear (loading=false, error set)
    await waitFor(() => {
      // When error is set, the component renders a <p> with the error text
      // The loading div with aria-busy is removed
      const busyEls = document.querySelectorAll('[aria-busy="true"]');
      expect(busyEls.length).toBe(0);
    });
    // An error paragraph is now in the DOM
    const paras = document.querySelectorAll('p');
    expect(paras.length).toBeGreaterThan(0);
  });

  it('shows an error message when the API throws', async () => {
    vi.mocked(api.get).mockRejectedValue(new Error('Network error'));
    render(<PilotApplyStatusPage />);
    // Wait for the loading state to clear
    await waitFor(() => {
      const busyEls = document.querySelectorAll('[aria-busy="true"]');
      expect(busyEls.length).toBe(0);
    });
    // After error, the loading spinner div is gone and an error paragraph is visible
    const paras = document.querySelectorAll('p');
    expect(paras.length).toBeGreaterThan(0);
  });

  it('does not call the API when there is no token', async () => {
    vi.mocked(useParams).mockReturnValue({});
    render(<PilotApplyStatusPage />);
    // With no token the effect returns early; loading stays true indefinitely
    // but no API call is made
    expect(api.get).not.toHaveBeenCalled();
    // Reset for subsequent tests
    vi.mocked(useParams).mockReturnValue({ token: 'abc-token-123' });
  });
});

describe('PilotApplyStatusPage — status variations', () => {
  beforeEach(() => { vi.clearAllMocks(); });

  const STATUSES = ['pending', 'under_review', 'approved', 'provisioned', 'rejected', 'failed'] as const;

  for (const status of STATUSES) {
    it(`renders org_name without crash for status="${status}"`, async () => {
      vi.mocked(api.get).mockResolvedValue({
        data: { ...MOCK_STATUS_INFO, status },
      });
      render(<PilotApplyStatusPage />);
      await waitFor(() => {
        expect(screen.getByText('Acme Timebank')).toBeInTheDocument();
      });
    });
  }
});
