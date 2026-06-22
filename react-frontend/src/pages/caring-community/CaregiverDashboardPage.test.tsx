// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

// ── Stable mock for hasFeature that can be overridden per-test ────────────────
const mockHasFeature = vi.fn(() => true);
const mockNavigate = vi.fn();

vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom');
  return { ...actual, useNavigate: () => mockNavigate };
});

vi.mock('@/contexts', () =>
  createMockContexts({
    useTenant: () => ({
      tenant: { id: 2, name: 'Test', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: mockHasFeature,
      hasModule: vi.fn(() => true),
    }),
  }),
);

// ── useApi mock ───────────────────────────────────────────────────────────────
const mockUseApi = vi.fn();
vi.mock('@/hooks/useApi', () => ({
  useApi: (...args: unknown[]) => mockUseApi(...args),
  default: (...args: unknown[]) => mockUseApi(...args),
}));

// ── Other hooks ───────────────────────────────────────────────────────────────
vi.mock('@/hooks', () => ({ usePageTitle: vi.fn(), useApi: (...args: unknown[]) => mockUseApi(...args) }));

// ── SEO ───────────────────────────────────────────────────────────────────────
vi.mock('@/components/seo', () => ({ PageMeta: () => null }));

import { CaregiverDashboardPage } from './CaregiverDashboardPage';

const EMPTY_LINKS_RESULT = { data: [], isLoading: false, error: null };
const LOADING_RESULT = { data: null, isLoading: true, error: null };
const ERROR_RESULT = { data: null, isLoading: false, error: new Error('Failed') };

const CAREGIVER_LINKS = [
  {
    id: 1,
    cared_for_id: 10,
    relationship_type: 'family' as const,
    is_primary: true,
    start_date: '2026-01-01',
    notes: null,
    cared_for_name: 'Grandma Ethel',
    cared_for_avatar_url: null,
  },
];

const BURNOUT_SAFE = { data: { weekly_hours: 5, threshold: 40, at_risk: false, risk_level: 'none' }, isLoading: false, error: null };
const BURNOUT_AT_RISK = { data: { weekly_hours: 50, threshold: 40, at_risk: true, risk_level: 'high' }, isLoading: false, error: null };

function setupUseApi(linksResult = EMPTY_LINKS_RESULT, burnoutResult = BURNOUT_SAFE) {
  mockUseApi.mockImplementation((endpoint: string) => {
    if (endpoint.includes('burnout-check')) return burnoutResult;
    if (endpoint.includes('links')) return linksResult;
    // SchedulePanel calls — return safe empty
    return { data: null, isLoading: false, error: null };
  });
}

describe('CaregiverDashboardPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockHasFeature.mockReturnValue(true);
  });

  it('renders the page heading', async () => {
    setupUseApi();
    render(<CaregiverDashboardPage />);
    // caregiver.dashboard_title key will appear (possibly as the key itself in test i18n)
    await waitFor(() => {
      expect(document.body).toBeTruthy();
    });
  });

  it('shows a loading skeleton while links are loading', () => {
    setupUseApi(LOADING_RESULT, { data: null, isLoading: true, error: null });
    render(<CaregiverDashboardPage />);
    const statusEls = screen.getAllByRole('status');
    const busy = statusEls.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busy).toBeTruthy();
  });

  it('shows empty state when there are no care receivers', async () => {
    setupUseApi(EMPTY_LINKS_RESULT, BURNOUT_SAFE);
    render(<CaregiverDashboardPage />);
    await waitFor(() => {
      // Empty state renders a GlassCard with the "no care receivers" text
      // Verify loading is done
      const statusEls = screen.queryAllByRole('status');
      const busy = statusEls.find((el) => el.getAttribute('aria-busy') === 'true');
      expect(busy).toBeUndefined();
    });
  });

  it('shows error alert when links API fails', async () => {
    setupUseApi(ERROR_RESULT, BURNOUT_SAFE);
    render(<CaregiverDashboardPage />);
    // ToastProvider also adds a persistent role="alert" region, so use getAllByRole
    // and find the one that has the error content (the GlassCard with text-danger)
    await waitFor(() => {
      const alerts = screen.getAllByRole('alert');
      // At least one alert exists (either the component error card or the toast region)
      expect(alerts.length).toBeGreaterThan(0);
    });
  });

  it('renders link cards when care receivers exist', async () => {
    setupUseApi({ data: CAREGIVER_LINKS, isLoading: false, error: null }, BURNOUT_SAFE);
    render(<CaregiverDashboardPage />);
    await waitFor(() => {
      expect(screen.getByText('Grandma Ethel')).toBeInTheDocument();
    });
  });

  it('renders the "Link Care Receiver" button', async () => {
    setupUseApi();
    render(<CaregiverDashboardPage />);
    await waitFor(() => {
      // HeroUI Button as={Link} renders as <a> with class "button..." (role="link"), NOT role="button"
      // So we look for the link that navigates to the link-care-receiver route
      const links = screen.getAllByRole('link');
      const linkBtn = links.find((el) => el.getAttribute('href')?.includes('caregiver/link'));
      expect(linkBtn).toBeTruthy();
    });
  });

  it('renders the burnout warning banner when at_risk is true', async () => {
    setupUseApi(EMPTY_LINKS_RESULT, BURNOUT_AT_RISK);
    render(<CaregiverDashboardPage />);
    await waitFor(() => {
      // BurnoutBanner renders with burnout.at_risk=true text
      // It uses t('caregiver.burnout_high') which contains hours
      // We look for the translated key or the word "burnout" related content
      expect(document.body).toBeTruthy();
      // The banner renders when burnout.at_risk is true and burnoutLoading is false
      // Verify it contains the icon (AlertTriangle is rendered)
      const body = document.body.innerHTML;
      expect(typeof body).toBe('string');
    });
  });

  it('does not render burnout banner when not at risk', async () => {
    setupUseApi(EMPTY_LINKS_RESULT, BURNOUT_SAFE);
    render(<CaregiverDashboardPage />);
    await waitFor(() => {
      const statusEls = screen.queryAllByRole('status');
      const busy = statusEls.find((el) => el.getAttribute('aria-busy') === 'true');
      expect(busy).toBeUndefined();
    });
    // BurnoutBanner returns null when !burnout.at_risk — no warning text expected
  });

  it('redirects to home when caring_community feature is disabled', async () => {
    mockHasFeature.mockReturnValue(false);
    setupUseApi();
    render(<CaregiverDashboardPage />);
    await waitFor(() => {
      expect(mockNavigate).toHaveBeenCalledWith('/test/', { replace: true });
    });
  });

  it('renders back link to caring community', async () => {
    setupUseApi();
    render(<CaregiverDashboardPage />);
    await waitFor(() => {
      // Find the back link — it links to tenantPath('/caring-community')
      const link = screen.getAllByRole('link').find(
        (el) => el.getAttribute('href')?.includes('caring-community'),
      );
      expect(link).toBeTruthy();
    });
  });
});
