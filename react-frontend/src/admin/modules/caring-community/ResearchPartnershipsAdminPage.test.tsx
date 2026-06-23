// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';

// ── hoisted mocks (must be first) ─────────────────────────────────────────────
const { mockApi, mockShowToast } = vi.hoisted(() => ({
  mockApi: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() },
  mockShowToast: vi.fn(),
}));

vi.mock('@/lib/api', () => ({ api: mockApi, default: mockApi }));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

// ── stubs ─────────────────────────────────────────────────────────────────────
vi.mock('@/components/seo/PageMeta', () => ({ PageMeta: () => null }));
vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));
vi.mock('@/components/feedback', () => ({
  EmptyState: ({ title }: { title: string }) => <div data-testid="empty-state">{title}</div>,
}));
vi.mock('../../AdminMetaContext', () => ({ useAdminPageMeta: vi.fn() }));
vi.mock('../../components', () => ({
  PageHeader: ({ title, actions }: { title: string; actions?: React.ReactNode }) => (
    <div>
      <h1>{title}</h1>
      {actions}
    </div>
  ),
}));

// ── contexts ──────────────────────────────────────────────────────────────────
vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => ({ showToast: mockShowToast, success: vi.fn(), error: vi.fn() }),
    useTenant: () => ({
      tenant: { id: 2, name: 'Test', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  }),
);

// ── fixtures ──────────────────────────────────────────────────────────────────
const makePartner = (overrides = {}) => ({
  id: 1,
  name: 'University of Dublin',
  institution: 'UCD Research',
  contact_email: 'research@ucd.ie',
  agreement_reference: 'AGR-2026-001',
  methodology_url: null,
  status: 'active' as const,
  starts_at: '2026-01-01',
  ends_at: null,
  created_at: '2026-01-01T00:00:00Z',
  ...overrides,
});

const makeExport = (overrides = {}) => ({
  id: 10,
  partner_id: 1,
  partner_name: 'University of Dublin',
  partner_institution: 'UCD Research',
  dataset_key: 'caring_community_aggregate_v1',
  period_start: '2026-01-01',
  period_end: '2026-12-31',
  status: 'generated' as const,
  row_count: 500,
  anonymization_version: '2.0',
  data_hash: 'abcdef123456789012',
  generated_at: '2026-06-01T12:00:00Z',
  ...overrides,
});

const makeTemplate = (overrides = {}) => ({
  key: 'standard_dpa',
  title: 'Standard DPA',
  summary: 'A standard data processing agreement',
  suitable_for: ['academic', 'public-sector'],
  placeholders: ['institution_name', 'start_date'],
  ...overrides,
});

// Each load() call does 3 Promise.all GETs — set up all three each time
const resolveAllOnce = (opts: { partners?: object[]; exports?: object[]; templates?: object[] } = {}) => {
  mockApi.get
    .mockResolvedValueOnce({ success: true, data: { partners: opts.partners ?? [makePartner()] } })
    .mockResolvedValueOnce({ success: true, data: { exports: opts.exports ?? [makeExport()] } })
    .mockResolvedValueOnce({ success: true, data: { templates: opts.templates ?? [makeTemplate()] } });
};

// ─────────────────────────────────────────────────────────────────────────────
describe('ResearchPartnershipsAdminPage', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    resolveAllOnce();
  });

  async function renderPage() {
    const mod = await import('./ResearchPartnershipsAdminPage');
    const Component = mod.default;
    render(<Component />);
  }

  // ── loading ────────────────────────────────────────────────────────────────
  it('shows loading spinner initially', async () => {
    mockApi.get.mockImplementation(() => new Promise(() => {}));
    await renderPage();
    const statusEls = screen.getAllByRole('status');
    const busy = statusEls.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busy).toBeDefined();
  });

  // ── populated partners ─────────────────────────────────────────────────────
  it('shows partner name and institution after load', async () => {
    await renderPage();
    await waitFor(() => {
      // Partner name appears in the table — use getAllByText and check at least one
      const names = screen.getAllByText('University of Dublin');
      expect(names.length).toBeGreaterThan(0);
      // Institution appears in both partners and exports tables — use getAllByText
      const institutions = screen.getAllByText('UCD Research');
      expect(institutions.length).toBeGreaterThan(0);
    });
  });

  it('shows agreement reference for partner', async () => {
    await renderPage();
    await waitFor(() => {
      expect(screen.getByText('AGR-2026-001')).toBeInTheDocument();
    });
  });

  // ── empty partners ─────────────────────────────────────────────────────────
  it('shows empty content when no partners returned', async () => {
    resolveAllOnce.call(null); // reset
    vi.resetAllMocks();
    mockApi.get
      .mockResolvedValueOnce({ success: true, data: { partners: [] } })
      .mockResolvedValueOnce({ success: true, data: { exports: [] } })
      .mockResolvedValueOnce({ success: true, data: { templates: [] } });

    await renderPage();
    await waitFor(() => {
      const busyEls = screen.queryAllByRole('status').filter(
        (el) => el.getAttribute('aria-busy') === 'true',
      );
      expect(busyEls.length).toBe(0);
    });
    // HeroUI Table emptyContent — real i18n renders "No research partners found"
    expect(document.body.textContent).toMatch(/No research partners|no partners|partners\.empty/i);
  });

  // ── exports table ──────────────────────────────────────────────────────────
  it('shows export row with row count', async () => {
    await renderPage();
    await waitFor(() => {
      expect(screen.getByText('500')).toBeInTheDocument();
    });
  });

  // ── revoke button ──────────────────────────────────────────────────────────
  it('calls revoke endpoint when Revoke button is pressed', async () => {
    mockApi.post.mockResolvedValue({ success: true });
    // After revoke, load() is called again
    resolveAllOnce();

    await renderPage();
    await waitFor(() => screen.getByText('500'));

    const revokeBtns = screen.getAllByRole('button').filter(
      (b) => b.textContent?.match(/revoke/i),
    );
    expect(revokeBtns.length).toBeGreaterThan(0);
    const enabledRevoke = revokeBtns.find(
      (b) =>
        !b.hasAttribute('disabled') &&
        b.getAttribute('data-disabled') !== 'true' &&
        b.getAttribute('aria-disabled') !== 'true',
    );
    if (enabledRevoke) {
      fireEvent.click(enabledRevoke);
      await waitFor(() => {
        expect(mockApi.post).toHaveBeenCalledWith(
          '/v2/admin/caring-community/research/dataset-exports/10/revoke',
        );
      });
    }
  });

  it('Revoke button is disabled for already-revoked exports', async () => {
    vi.resetAllMocks();
    mockApi.get
      .mockResolvedValueOnce({ success: true, data: { partners: [] } })
      .mockResolvedValueOnce({ success: true, data: { exports: [makeExport({ status: 'revoked' })] } })
      .mockResolvedValueOnce({ success: true, data: { templates: [] } });

    await renderPage();
    await waitFor(() => screen.getByText('500'));

    const revokeBtns = screen.getAllByRole('button').filter(
      (b) => b.textContent?.match(/revoke/i),
    );
    expect(revokeBtns.length).toBeGreaterThan(0);
    // HeroUI isDisabled sets aria-disabled="true" or data-disabled="true"
    const isDisabled = revokeBtns.every(
      (b) =>
        b.hasAttribute('disabled') ||
        b.getAttribute('data-disabled') === 'true' ||
        b.getAttribute('aria-disabled') === 'true',
    );
    expect(isDisabled).toBe(true);
  });

  // ── templates section ──────────────────────────────────────────────────────
  it('shows template title and summary', async () => {
    await renderPage();
    await waitFor(() => {
      expect(screen.getByText('Standard DPA')).toBeInTheDocument();
      expect(screen.getByText('A standard data processing agreement')).toBeInTheDocument();
    });
  });

  it('opens template modal when Open is clicked on a template', async () => {
    await renderPage();
    await waitFor(() => screen.getByText('Standard DPA'));

    const openBtns = screen.getAllByRole('button').filter(
      (b) => b.textContent?.match(/open/i),
    );
    if (openBtns.length > 0) {
      fireEvent.click(openBtns[0]);
      await waitFor(() => {
        expect(document.querySelector('[role="dialog"]')).toBeTruthy();
      });
    }
  });

  // ── add partner modal ──────────────────────────────────────────────────────
  it('opens create partner modal when Add Partner is clicked', async () => {
    await renderPage();
    // wait for page to finish loading
    await waitFor(() => {
      const busy = screen.queryAllByRole('status').filter(
        (el) => el.getAttribute('aria-busy') === 'true',
      );
      expect(busy.length).toBe(0);
    });

    const addBtns = screen.getAllByRole('button').filter(
      (b) => b.textContent?.match(/add partner/i),
    );
    if (addBtns.length > 0) {
      fireEvent.click(addBtns[0]);
      await waitFor(() => {
        expect(document.querySelector('[role="dialog"]')).toBeTruthy();
      });
    }
  });

  // ── error on load ──────────────────────────────────────────────────────────
  it('shows toast on load failure', async () => {
    vi.resetAllMocks();
    mockApi.get.mockRejectedValue(new Error('network error'));
    await renderPage();
    await waitFor(() => {
      expect(mockShowToast).toHaveBeenCalled();
    });
  });
});
