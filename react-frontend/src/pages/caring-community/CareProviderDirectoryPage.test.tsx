// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import userEvent from '@testing-library/user-event';
import { createMockContexts } from '@/test/mock-contexts';

// ── Stable hoisted refs ───────────────────────────────────────────────────────
const { mockHasFeature, mockApiGet } = vi.hoisted(() => ({
  mockHasFeature: vi.fn(() => true),
  mockApiGet: vi.fn(),
}));

vi.mock('@/contexts', () =>
  createMockContexts({
    useTenant: () => ({
      tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: mockHasFeature,
      hasModule: vi.fn(() => true),
    }),
  })
);

// useApi is used by CareProviderDirectoryPage — mock at the hook level
vi.mock('@/hooks/useApi', () => ({
  useApi: vi.fn(),
  default: vi.fn(),
}));

vi.mock('@/lib/api', () => {
  const m = { get: mockApiGet, post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() };
  return { default: m, api: m };
});

// SubRegionFilter makes its own API call; stub it out
vi.mock('@/components/caring-community/SubRegionFilter', () => ({
  SubRegionFilter: ({ onChange }: { onChange: (id: number | null) => void }) => (
    <div data-testid="sub-region-filter">
      <button onClick={() => onChange(null)}>All Regions</button>
    </div>
  ),
}));

// PageMeta is purely head-management
vi.mock('@/components/seo', () => ({
  PageMeta: () => null,
}));

import { useApi } from '@/hooks/useApi';
import CareProviderDirectoryPage from './CareProviderDirectoryPage';

// ── Test data ─────────────────────────────────────────────────────────────────
const VERIFIED_PROVIDER = {
  id: 1,
  name: 'Spitex Zürich Nord',
  type: 'spitex' as const,
  description: 'Professional home care services',
  categories: ['nursing', 'physiotherapy'],
  address: 'Musterstrasse 1, 8001 Zürich',
  contact_phone: '+41 44 123 45 67',
  contact_email: 'info@spitex-zh-nord.ch',
  website_url: 'https://spitex-zh-nord.ch',
  opening_hours: { monday: '08:00-18:00' },
  is_verified: true,
};

const UNVERIFIED_PROVIDER = {
  id: 2,
  name: 'Verein Nachbarschaftshilfe',
  type: 'verein' as const,
  description: null,
  categories: null,
  address: null,
  contact_phone: null,
  contact_email: null,
  website_url: null,
  opening_hours: null,
  is_verified: false,
};

type UseApiReturn = ReturnType<typeof useApi>;

function mockUseApiResult(overrides: Partial<UseApiReturn> = {}): UseApiReturn {
  return {
    data: null,
    loading: false,
    isLoading: false,
    error: null,
    meta: null,
    execute: vi.fn().mockResolvedValue({ success: true, data: null }),
    refetch: vi.fn().mockResolvedValue({ success: true, data: null }),
    reset: vi.fn(),
    setData: vi.fn(),
    ...overrides,
  };
}

// ── Tests ─────────────────────────────────────────────────────────────────────
describe('CareProviderDirectoryPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockHasFeature.mockReturnValue(true);
  });

  it('shows unavailable card when caring_community feature is disabled', () => {
    mockHasFeature.mockReturnValue(false);
    vi.mocked(useApi).mockReturnValue(mockUseApiResult());

    render(<CareProviderDirectoryPage />);
    // i18n key providers.unavailable
    expect(screen.getByText(/care provider directory is not available/i)).toBeInTheDocument();
  });

  it('shows loading skeleton grid while fetching', () => {
    vi.mocked(useApi).mockReturnValue(mockUseApiResult({ loading: true, isLoading: true }));

    render(<CareProviderDirectoryPage />);
    // Loading grid has role=status aria-busy=true
    const loadingEl = Array.from(document.body.querySelectorAll('[role="status"]')).find(
      (el) => el.getAttribute('aria-busy') === 'true'
    );
    expect(loadingEl).toBeTruthy();
  });

  it('renders provider cards after successful load', () => {
    vi.mocked(useApi).mockReturnValue(
      mockUseApiResult({
        data: { data: [VERIFIED_PROVIDER, UNVERIFIED_PROVIDER], total: 2, per_page: 20, current_page: 1 },
      })
    );

    render(<CareProviderDirectoryPage />);
    expect(screen.getByText('Spitex Zürich Nord')).toBeInTheDocument();
    expect(screen.getByText('Verein Nachbarschaftshilfe')).toBeInTheDocument();
  });

  it('shows verified badge for verified providers', () => {
    vi.mocked(useApi).mockReturnValue(
      mockUseApiResult({
        data: { data: [VERIFIED_PROVIDER], total: 1, per_page: 20, current_page: 1 },
      })
    );

    render(<CareProviderDirectoryPage />);
    // Verified provider renders a BadgeCheck with aria-label
    const verifiedBadges = screen.getAllByLabelText(/verified/i);
    expect(verifiedBadges.length).toBeGreaterThan(0);
  });

  it('shows contact phone link when available', () => {
    vi.mocked(useApi).mockReturnValue(
      mockUseApiResult({
        data: { data: [VERIFIED_PROVIDER], total: 1, per_page: 20, current_page: 1 },
      })
    );

    render(<CareProviderDirectoryPage />);
    const phoneLink = screen.getByText('+41 44 123 45 67');
    expect(phoneLink).toBeInTheDocument();
  });

  it('shows contact email link when available', () => {
    vi.mocked(useApi).mockReturnValue(
      mockUseApiResult({
        data: { data: [VERIFIED_PROVIDER], total: 1, per_page: 20, current_page: 1 },
      })
    );

    render(<CareProviderDirectoryPage />);
    const emailEl = screen.getByText('info@spitex-zh-nord.ch');
    expect(emailEl).toBeInTheDocument();
  });

  it('shows empty state when no providers are returned', () => {
    vi.mocked(useApi).mockReturnValue(
      mockUseApiResult({
        data: { data: [], total: 0, per_page: 20, current_page: 1 },
      })
    );

    render(<CareProviderDirectoryPage />);
    // i18n key providers.no_providers
    expect(screen.getByText('No care providers found')).toBeInTheDocument();
  });

  it('shows error panel with retry button on fetch error', () => {
    const mockRefetch = vi.fn().mockResolvedValue({ success: false, data: null });
    vi.mocked(useApi).mockReturnValue(
      mockUseApiResult({ error: 'Failed to load', refetch: mockRefetch })
    );

    render(<CareProviderDirectoryPage />);
    // The error div has role="alert"; ToastProvider also adds alerts — find the one with danger class
    const alerts = screen.getAllByRole('alert');
    const errorAlert = alerts.find((el) => el.className.includes('danger'));
    expect(errorAlert).toBeTruthy();
    // Error title text
    expect(screen.getByText(/care providers could not be loaded/i)).toBeInTheDocument();
    // "Try again" button (providers.retry)
    expect(screen.getByRole('button', { name: /try again/i })).toBeInTheDocument();
  });

  it('calls refetch when retry button is clicked', async () => {
    const user = userEvent.setup();
    const mockRefetch = vi.fn().mockResolvedValue({ success: false, data: null });
    vi.mocked(useApi).mockReturnValue(
      mockUseApiResult({ error: 'Failed to load', refetch: mockRefetch })
    );

    render(<CareProviderDirectoryPage />);

    // "Try again" is the translated text for providers.retry
    await user.click(screen.getByRole('button', { name: /try again/i }));
    expect(mockRefetch).toHaveBeenCalled();
  });

  it('renders filter tabs for provider types', () => {
    vi.mocked(useApi).mockReturnValue(
      mockUseApiResult({
        data: { data: [], total: 0, per_page: 20, current_page: 1 },
      })
    );

    render(<CareProviderDirectoryPage />);
    // FILTER_TABS includes all/spitex/tagesstätte/private/verein/volunteer
    const tabs = screen.getAllByRole('tab');
    expect(tabs.length).toBeGreaterThanOrEqual(2);
  });

  it('renders page title', () => {
    vi.mocked(useApi).mockReturnValue(
      mockUseApiResult({
        data: { data: [], total: 0, per_page: 20, current_page: 1 },
      })
    );

    render(<CareProviderDirectoryPage />);
    expect(screen.getByRole('heading', { level: 1 })).toBeInTheDocument();
  });
});
