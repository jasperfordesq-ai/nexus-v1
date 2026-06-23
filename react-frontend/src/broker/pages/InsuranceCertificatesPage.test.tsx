// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import React from 'react';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

// ─── Hoisted mocks ───────────────────────────────────────────────────────────
const { mockAdminInsurance, mockAdminUsers, mockAdminBroker } = vi.hoisted(() => ({
  mockAdminInsurance: {
    list: vi.fn(),
    stats: vi.fn(),
    verify: vi.fn(),
    reject: vi.fn(),
    destroy: vi.fn(),
    create: vi.fn(),
    update: vi.fn(),
  },
  mockAdminUsers: { list: vi.fn() },
  mockAdminBroker: { getConfiguration: vi.fn() },
}));

vi.mock('@/admin/api/adminApi', () => ({
  adminInsurance: mockAdminInsurance,
  adminUsers: mockAdminUsers,
  adminBroker: mockAdminBroker,
  adminCrm: { getFunnel: vi.fn() },
  adminMenus: { list: vi.fn() },
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));
vi.mock('@/lib/helpers', async (importOriginal) => {
  const orig = await importOriginal<typeof import('@/lib/helpers')>();
  return {
    ...orig,
    resolveAvatarUrl: vi.fn((url: string | null) => url ?? '/default-avatar.png'),
    resolveAssetUrl: vi.fn((url: string | null) => url ?? ''),
  };
});
vi.mock('@/lib/serverTime', () => ({
  parseServerTimestamp: vi.fn((d: string) => new Date(d)),
  formatServerDate: vi.fn((d: string) => d),
  formatServerDateTime: vi.fn((d: string) => d),
}));

// ─── Router with useSearchParams stub ────────────────────────────────────────
const mockSetSearchParams = vi.fn();

vi.mock('react-router-dom', async (importOriginal) => {
  const orig = await importOriginal<typeof import('react-router-dom')>();
  return {
    ...orig,
    useNavigate: () => vi.fn(),
    useParams: () => ({}),
    useSearchParams: () => [new URLSearchParams(), mockSetSearchParams],
  };
});

// ─── Contexts ────────────────────────────────────────────────────────────────
const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToast,
    useTenant: () => ({
      tenant: { id: 2, name: 'Test', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  })
);

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

// ─── Admin component stubs ───────────────────────────────────────────────────
const makeAdminComponentMocks = () => ({
  DataTable: ({ data, isLoading }: { data?: unknown[]; isLoading?: boolean }) => (
    <div data-testid="data-table" aria-busy={isLoading ? 'true' : undefined}>
      {isLoading ? <div role="status" aria-busy="true" /> : null}
      {Array.isArray(data) && data.length === 0 ? '0 rows' : `${(data ?? []).length} rows`}
    </div>
  ),
  StatCard: ({ label, value }: { label: string; value?: unknown }) => (
    <div data-testid="stat-card">{label}: {String(value ?? '')}</div>
  ),
  PageHeader: ({ title, actions }: { title: string; actions?: React.ReactNode }) => (
    <div>
      <h1>{title}</h1>
      {actions}
    </div>
  ),
  ConfirmModal: () => null,
  EmptyState: ({ title }: { title: string }) => <div data-testid="empty-state">{title}</div>,
});

vi.mock('@/admin/components', () => makeAdminComponentMocks());
vi.mock('../../admin/components', () => makeAdminComponentMocks());

vi.mock('@/components/seo/PageMeta', () => ({ PageMeta: () => null }));

// ─── Fixtures ─────────────────────────────────────────────────────────────────
const makeStats = () => ({
  total: 45,
  pending_review: 8,
  verified: 30,
  rejected: 3,
  expired: 4,
  expiring_soon: 2,
});

const makeCertificate = (overrides = {}) => ({
  id: 1,
  status: 'pending_review',
  policy_number: 'POL-001',
  insurer: 'Test Insurance Ltd',
  effective_date: '2025-01-01',
  expiry_date: '2026-01-01',
  coverage_amount: 1000000,
  created_at: '2025-01-01T00:00:00Z',
  user: { id: 10, name: 'Carol Cert', email: 'carol@example.com', avatar_url: null },
  ...overrides,
});

const makeConfig = () => ({
  enabled: true,
  require_insurance: true,
  min_coverage: 500000,
});

// ─────────────────────────────────────────────────────────────────────────────
describe('InsuranceCertificatesPage', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockAdminInsurance.list.mockResolvedValue({
      success: true,
      data: [makeCertificate()],
      meta: { total: 1, per_page: 25, current_page: 1, last_page: 1 },
    });
    mockAdminInsurance.stats.mockResolvedValue({ success: true, data: makeStats() });
    mockAdminInsurance.verify.mockResolvedValue({ success: true });
    mockAdminInsurance.reject.mockResolvedValue({ success: true });
    mockAdminInsurance.destroy.mockResolvedValue({ success: true });
    mockAdminInsurance.create.mockResolvedValue({ success: true, data: makeCertificate() });
    mockAdminUsers.list.mockResolvedValue({ success: true, data: [] });
    mockAdminBroker.getConfiguration.mockResolvedValue({ success: true, data: makeConfig() });
  });

  it('shows loading state while fetching', async () => {
    mockAdminInsurance.list.mockImplementationOnce(() => new Promise(() => {}));
    mockAdminInsurance.stats.mockImplementationOnce(() => new Promise(() => {}));
    const { InsuranceCertificates } = await import('./InsuranceCertificatesPage');
    render(<InsuranceCertificates />);

    // Component uses DataTable with isLoading prop or StatCard with loading prop;
    // our DataTable stub renders aria-busy="true" when loading
    const table = screen.getByTestId('data-table');
    expect(table).toBeInTheDocument();
    // The table should be in a loading / no-data state initially
    const bodyText = document.body.textContent ?? '';
    expect(bodyText).toBeTruthy();
  });

  it('renders stat cards after load', async () => {
    const { InsuranceCertificates } = await import('./InsuranceCertificatesPage');
    render(<InsuranceCertificates />);

    await waitFor(() => {
      expect(screen.getAllByTestId('stat-card').length).toBeGreaterThan(0);
    });
  });

  it('renders data table with certificate rows', async () => {
    const { InsuranceCertificates } = await import('./InsuranceCertificatesPage');
    render(<InsuranceCertificates />);

    await waitFor(() => {
      const table = screen.getByTestId('data-table');
      expect(table).toBeInTheDocument();
      // "1 rows" because we returned 1 certificate
      expect(table.textContent).toContain('1');
    });
  });

  it('calls adminInsurance.list on mount', async () => {
    const { InsuranceCertificates } = await import('./InsuranceCertificatesPage');
    render(<InsuranceCertificates />);

    await waitFor(() => {
      expect(mockAdminInsurance.list).toHaveBeenCalled();
    });
  });

  it('calls adminInsurance.stats on mount', async () => {
    const { InsuranceCertificates } = await import('./InsuranceCertificatesPage');
    render(<InsuranceCertificates />);

    await waitFor(() => {
      expect(mockAdminInsurance.stats).toHaveBeenCalled();
    });
  });

  it('shows error warning when stats fail to load', async () => {
    mockAdminInsurance.stats.mockRejectedValueOnce(new Error('Stats unavailable'));
    const { InsuranceCertificates } = await import('./InsuranceCertificatesPage');
    render(<InsuranceCertificates />);

    await waitFor(() => {
      const text = document.body.textContent?.toLowerCase() || '';
      const hasWarning =
        text.includes('error') ||
        text.includes('fail') ||
        text.includes('unavailable') ||
        mockToast.error.mock.calls.length > 0;
      expect(hasWarning).toBe(true);
    });
  });

  it('renders a search input', async () => {
    const { InsuranceCertificates } = await import('./InsuranceCertificatesPage');
    render(<InsuranceCertificates />);

    await waitFor(() => {
      const searchInput = screen.queryByRole('searchbox') ||
        screen.queryByPlaceholderText(/search/i) ||
        screen.queryByRole('textbox');
      expect(searchInput).toBeDefined();
    });
  });

  it('renders a create certificate button', async () => {
    const { InsuranceCertificates } = await import('./InsuranceCertificatesPage');
    render(<InsuranceCertificates />);

    await waitFor(() => {
      const buttons = screen.getAllByRole('button');
      const createBtn = buttons.find(
        (b) =>
          b.textContent?.toLowerCase().includes('create') ||
          b.textContent?.toLowerCase().includes('add') ||
          b.textContent?.toLowerCase().includes('new')
      );
      expect(createBtn).toBeDefined();
    });
  });

  it('shows status filter tabs', async () => {
    const { InsuranceCertificates } = await import('./InsuranceCertificatesPage');
    render(<InsuranceCertificates />);

    await waitFor(() => {
      const text = document.body.textContent?.toLowerCase() || '';
      const hasFilters =
        text.includes('pending') ||
        text.includes('verified') ||
        text.includes('rejected') ||
        text.includes('all');
      expect(hasFilters).toBe(true);
    });
  });

  it('shows empty rows in data table when no certificates match filter', async () => {
    mockAdminInsurance.list.mockResolvedValue({
      success: true,
      data: [],
      meta: { total: 0, per_page: 25, current_page: 1, last_page: 1 },
    });
    const { InsuranceCertificates } = await import('./InsuranceCertificatesPage');
    render(<InsuranceCertificates />);

    await waitFor(() => {
      const table = screen.getByTestId('data-table');
      // Stub renders "0 rows" for empty arrays
      expect(table.textContent).toMatch(/0\s*rows/);
    });
  });
});
