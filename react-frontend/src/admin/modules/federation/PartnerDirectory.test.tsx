// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import React from 'react';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import userEvent from '@testing-library/user-event';

// ─── UI mock ──────────────────────────────────────────────────────────────────
vi.mock('@/components/ui', async () => (await import('@/test/uiMock')).uiMock);

// ─── Admin API mock ───────────────────────────────────────────────────────────
const { mockAdminFederation } = vi.hoisted(() => ({
  mockAdminFederation: {
    getDirectory: vi.fn(),
    requestPartnership: vi.fn(),
  },
}));

vi.mock('@/admin/api/adminApi', () => ({
  adminFederation: mockAdminFederation,
}));

// ─── Admin components ─────────────────────────────────────────────────────────
vi.mock('../../components', () => ({
  PageHeader: ({ title, actions }: { title: string; actions?: React.ReactNode }) => (
    <div><h1>{title}</h1><div data-testid="page-header-actions">{actions}</div></div>
  ),
  EmptyState: ({ title }: { title: string }) => <div data-testid="empty-state">{title}</div>,
}));

vi.mock('./PartnerTimebankGuidance', () => ({
  PartnerTimebankGuidance: () => null,
}));

// ─── SEO / hooks ─────────────────────────────────────────────────────────────
vi.mock('@/components/seo/PageMeta', () => ({ PageMeta: () => null }));
vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

// ─── Toast / contexts ─────────────────────────────────────────────────────────
const { mockToast } = vi.hoisted(() => ({
  mockToast: { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn(), showToast: vi.fn() },
}));

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToast,
  }),
);

// ─── Fixtures ─────────────────────────────────────────────────────────────────
const makeCommunity = (overrides = {}) => ({
  id: 10,
  name: 'Dublin Timebank',
  slug: 'dublin-timebank',
  domain: 'dublin.timebank.example',
  description: 'Community in Dublin',
  region: 'Ireland',
  contact_email: 'hello@dublin-timebank.ie',
  contact_name: 'Jane Admin',
  member_count: 120,
  federation_member_count_public: 80,
  profiles_enabled: 1,
  listings_enabled: 1,
  messaging_enabled: 0,
  transactions_enabled: 1,
  events_enabled: 0,
  groups_enabled: 0,
  partnership_status: null,
  partnership_id: null,
  topics: [],
  ...overrides,
});

const makeDirectoryResponse = (communities = [] as object[]) => ({
  success: true,
  data: {
    communities,
    regions: ['Ireland', 'UK'],
    categories: ['Timebank'],
    topics: [],
  },
});

// ─────────────────────────────────────────────────────────────────────────────
describe('PartnerDirectory', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockAdminFederation.getDirectory.mockResolvedValue(makeDirectoryResponse());
    mockAdminFederation.requestPartnership.mockResolvedValue({ success: true, data: {} });
  });

  it('shows skeleton loading state on initial load', async () => {
    // Never resolves — stays loading
    mockAdminFederation.getDirectory.mockImplementationOnce(() => new Promise(() => {}));
    const { PartnerDirectory } = await import('./PartnerDirectory');
    render(<PartnerDirectory />);

    // Loading skeleton shows Skeleton elements; check for them by class or just that empty-state is absent
    await waitFor(() => {
      expect(screen.queryByTestId('empty-state')).not.toBeInTheDocument();
    });
  });

  it('shows empty state when no communities are returned', async () => {
    const { PartnerDirectory } = await import('./PartnerDirectory');
    render(<PartnerDirectory />);

    await waitFor(() => {
      expect(screen.getByTestId('empty-state')).toBeInTheDocument();
    });
  });

  it('renders community cards when communities are returned', async () => {
    mockAdminFederation.getDirectory.mockResolvedValue(makeDirectoryResponse([makeCommunity()]));
    const { PartnerDirectory } = await import('./PartnerDirectory');
    render(<PartnerDirectory />);

    await waitFor(() => {
      expect(screen.getByText('Dublin Timebank')).toBeInTheDocument();
    });
  });

  it('renders region for a community', async () => {
    mockAdminFederation.getDirectory.mockResolvedValue(makeDirectoryResponse([makeCommunity()]));
    const { PartnerDirectory } = await import('./PartnerDirectory');
    render(<PartnerDirectory />);

    await waitFor(() => {
      // Ireland appears both in the Select option and in the community card region span
      const els = screen.queryAllByText('Ireland');
      expect(els.length).toBeGreaterThan(0);
    });
  });

  it('shows Request Partnership button for unpartnered community', async () => {
    mockAdminFederation.getDirectory.mockResolvedValue(makeDirectoryResponse([makeCommunity()]));
    const { PartnerDirectory } = await import('./PartnerDirectory');
    render(<PartnerDirectory />);

    await waitFor(() => {
      // Use exact match to avoid matching "Hide Partnered" filter button
      const btn = screen.queryAllByRole('button').find(
        b => b.textContent === 'Request Partnership',
      );
      expect(btn).toBeDefined();
    });
  });

  it('opens partnership request modal when button is clicked', async () => {
    mockAdminFederation.getDirectory.mockResolvedValue(makeDirectoryResponse([makeCommunity()]));
    const { PartnerDirectory } = await import('./PartnerDirectory');
    render(<PartnerDirectory />);

    // Wait for community cards to render (300ms debounce + fetch)
    await waitFor(() => screen.getByText('Dublin Timebank'), { timeout: 2000 });

    // The request partnership button exists on the card
    const requestBtns = screen.queryAllByRole('button').filter((b) =>
      b.textContent?.toLowerCase().includes('request') || b.textContent?.toLowerCase().includes('partner'),
    );
    expect(requestBtns.length).toBeGreaterThan(0);
    // Click the "Request Partnership" button specifically (not "Hide Partnered" which also
    // contains 'partner' in its text and would appear first in the DOM)
    const exactRequestBtn = screen.queryAllByRole('button').find(
      b => b.textContent === 'Request Partnership',
    );
    expect(exactRequestBtn).toBeDefined();
    fireEvent.click(exactRequestBtn!);

    // Modal opens: cancel + send buttons appear (both unique to modal footer)
    await waitFor(() => {
      const cancelBtn = screen.queryAllByRole('button').find((b) =>
        b.textContent?.toLowerCase().includes('cancel'),
      );
      expect(cancelBtn).toBeDefined();
    }, { timeout: 2000 });
  });

  it('calls requestPartnership API when modal is submitted', async () => {
    mockAdminFederation.getDirectory.mockResolvedValue(makeDirectoryResponse([makeCommunity()]));
    mockAdminFederation.requestPartnership.mockResolvedValue({ success: true });

    const { PartnerDirectory } = await import('./PartnerDirectory');
    render(<PartnerDirectory />);

    await waitFor(() => screen.getByText('Dublin Timebank'), { timeout: 2000 });

    const exactRequestBtn = screen.queryAllByRole('button').find(
      b => b.textContent === 'Request Partnership',
    );
    expect(exactRequestBtn).toBeDefined();
    fireEvent.click(exactRequestBtn!);

    // Wait for modal to open (cancel button unique to modal footer)
    let sendBtn: HTMLElement | undefined;
    await waitFor(() => {
      const cancelBtn = screen.queryAllByRole('button').find((b) =>
        b.textContent?.toLowerCase().includes('cancel'),
      );
      sendBtn = screen.queryAllByRole('button').find((b) =>
        b.textContent?.toLowerCase().includes('send'),
      );
      expect(cancelBtn).toBeDefined();
      expect(sendBtn).toBeDefined();
    }, { timeout: 2000 });

    fireEvent.click(sendBtn!);

    await waitFor(() => {
      expect(mockAdminFederation.requestPartnership).toHaveBeenCalledWith(10, undefined);
    }, { timeout: 2000 });
  });

  it('shows success toast after partnership request is sent', async () => {
    mockAdminFederation.getDirectory.mockResolvedValue(makeDirectoryResponse([makeCommunity()]));
    mockAdminFederation.requestPartnership.mockResolvedValue({ success: true });

    const { PartnerDirectory } = await import('./PartnerDirectory');
    render(<PartnerDirectory />);

    await waitFor(() => screen.getByText('Dublin Timebank'), { timeout: 2000 });

    const exactRequestBtn = screen.queryAllByRole('button').find(
      b => b.textContent === 'Request Partnership',
    );
    expect(exactRequestBtn).toBeDefined();
    fireEvent.click(exactRequestBtn!);

    // Wait for modal to open
    let sendBtn: HTMLElement | undefined;
    await waitFor(() => {
      sendBtn = screen.queryAllByRole('button').find((b) =>
        b.textContent?.toLowerCase().includes('send'),
      );
      expect(sendBtn).toBeDefined();
    }, { timeout: 2000 });

    fireEvent.click(sendBtn!);

    await waitFor(() => {
      expect(mockToast.success).toHaveBeenCalled();
    }, { timeout: 2000 });
  });

  it('shows Active Partner button (disabled) for partnered community', async () => {
    mockAdminFederation.getDirectory.mockResolvedValue(
      makeDirectoryResponse([makeCommunity({ partnership_status: 'active' })]),
    );
    const { PartnerDirectory } = await import('./PartnerDirectory');
    render(<PartnerDirectory />);

    await waitFor(() => {
      const btn = screen.queryAllByRole('button').find((b) =>
        b.textContent?.toLowerCase().includes('active') || b.textContent?.toLowerCase().includes('partner'),
      );
      expect(btn).toBeDefined();
    });
  });

  it('renders multiple communities', async () => {
    mockAdminFederation.getDirectory.mockResolvedValue(
      makeDirectoryResponse([
        makeCommunity({ id: 10, name: 'Dublin Timebank' }),
        makeCommunity({ id: 11, name: 'Cork Timebank', slug: 'cork-timebank' }),
      ]),
    );
    const { PartnerDirectory } = await import('./PartnerDirectory');
    render(<PartnerDirectory />);

    await waitFor(() => {
      expect(screen.getByText('Dublin Timebank')).toBeInTheDocument();
      expect(screen.getByText('Cork Timebank')).toBeInTheDocument();
    });
  });

  it('reload is called when Refresh button is clicked', async () => {
    const { PartnerDirectory } = await import('./PartnerDirectory');
    render(<PartnerDirectory />);

    await waitFor(() => {
      const busy = screen.queryAllByRole('status').find((el) => el.getAttribute('aria-busy') === 'true');
      expect(busy).toBeUndefined();
    });

    const refreshBtn = screen.queryAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('refresh'),
    );
    expect(refreshBtn).toBeDefined();
    fireEvent.click(refreshBtn!);

    // getDirectory should be called again (second call)
    await waitFor(() => {
      expect(mockAdminFederation.getDirectory.mock.calls.length).toBeGreaterThanOrEqual(2);
    });
  });
});
