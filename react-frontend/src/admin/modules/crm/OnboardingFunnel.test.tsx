// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import React from 'react';
import { render, screen, waitFor } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

// ─── Mock adminApi (named export adminCrm) ──────────────────────────────────
const { mockAdminCrm } = vi.hoisted(() => ({
  mockAdminCrm: { getFunnel: vi.fn() },
}));

vi.mock('@/admin/api/adminApi', () => ({
  adminCrm: mockAdminCrm,
  adminUsers: { list: vi.fn() },
  adminPages: { list: vi.fn() },
  adminMenus: { list: vi.fn() },
}));

// ─── Recharts — stub to avoid DOM measurement errors ────────────────────────
vi.mock('recharts', async (importOriginal) => {
  const orig = await importOriginal<typeof import('recharts')>();
  return {
    ...orig,
    ResponsiveContainer: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
    AreaChart: ({ children }: { children: React.ReactNode }) => <div data-testid="area-chart">{children}</div>,
    Area: () => null,
    CartesianGrid: () => null,
    XAxis: () => null,
    YAxis: () => null,
    Tooltip: () => null,
  };
});

// ─── AdminMetaContext stub ───────────────────────────────────────────────────
vi.mock('@/admin/modules/AdminMetaContext', () => ({
  useAdminPageMeta: vi.fn(),
  AdminMetaProvider: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

vi.mock('../../AdminMetaContext', () => ({
  useAdminPageMeta: vi.fn(),
}));

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
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

// ─── Fixtures ────────────────────────────────────────────────────────────────
const makeFunnelData = (overrides = {}) => ({
  stages: [
    { code: 'registered', name: 'SERVER COPY MUST NOT RENDER', count: 500, color: '#3b82f6' },
    { code: 'profile_complete', count: 350, color: '#10b981' },
    { code: 'first_exchange', count: 200, color: '#f59e0b' },
    { code: 'repeat_user', count: 120, color: '#8b5cf6' },
  ],
  monthly_registrations: [
    { month: '2024-10', count: 45 },
    { month: '2024-11', count: 60 },
    { month: '2024-12', count: 55 },
  ],
  ...overrides,
});

// ─────────────────────────────────────────────────────────────────────────────
describe('OnboardingFunnel', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockAdminCrm.getFunnel.mockResolvedValue({ success: true, data: makeFunnelData() });
  });

  it('shows loading spinner while data is fetching', async () => {
    mockAdminCrm.getFunnel.mockImplementationOnce(() => new Promise(() => {}));
    const { default: OnboardingFunnel } = await import('./OnboardingFunnel');
    render(<OnboardingFunnel />);

    const statuses = screen.getAllByRole('status');
    const busy = statuses.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busy).toBeDefined();
  });

  it('renders stage names after data loads', async () => {
    const { default: OnboardingFunnel } = await import('./OnboardingFunnel');
    render(<OnboardingFunnel />);

    // Stage names appear in multiple places (caption + funnel card)
    await waitFor(() => {
      expect(screen.getAllByText('Registered').length).toBeGreaterThan(0);
    }, { timeout: 3000 });
    expect(screen.getAllByText('Profile Complete').length).toBeGreaterThan(0);
    expect(screen.getAllByText('Repeat User').length).toBeGreaterThan(0);
    expect(screen.queryByText('SERVER COPY MUST NOT RENDER')).not.toBeInTheDocument();
  });

  it('renders member counts from stages', async () => {
    const { default: OnboardingFunnel } = await import('./OnboardingFunnel');
    render(<OnboardingFunnel />);

    await waitFor(() => {
      // Entry stage count shown in metric card and hero stats
      const fiveHundreds = screen.getAllByText('500');
      expect(fiveHundreds.length).toBeGreaterThan(0);
    });
  });

  it('shows error state and error toast when API fails', async () => {
    mockAdminCrm.getFunnel.mockRejectedValueOnce(new Error('network error'));
    const { default: OnboardingFunnel } = await import('./OnboardingFunnel');
    render(<OnboardingFunnel />);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
    // Should show refresh button in error card
    const buttons = screen.getAllByRole('button');
    const refreshBtn = buttons.find((b) => b.textContent?.toLowerCase().includes('refresh'));
    expect(refreshBtn).toBeDefined();
  });

  it('renders refresh button that triggers reload', async () => {
    const { default: OnboardingFunnel } = await import('./OnboardingFunnel');
    render(<OnboardingFunnel />);

    await waitFor(() => expect(screen.getAllByText('Registered').length).toBeGreaterThan(0), { timeout: 3000 });

    const buttons = screen.getAllByRole('button');
    const refreshBtn = buttons.find((b) => b.textContent?.toLowerCase().includes('refresh'));
    expect(refreshBtn).toBeDefined();
  });

  it('renders area chart when monthly registration data present', async () => {
    const { default: OnboardingFunnel } = await import('./OnboardingFunnel');
    render(<OnboardingFunnel />);

    await waitFor(() => {
      expect(screen.getByTestId('area-chart')).toBeInTheDocument();
    });
  });

  it('shows no-stages fallback message when stages array is empty', async () => {
    mockAdminCrm.getFunnel.mockResolvedValueOnce({
      success: true,
      data: makeFunnelData({ stages: [], monthly_registrations: [] }),
    });
    const { default: OnboardingFunnel } = await import('./OnboardingFunnel');
    render(<OnboardingFunnel />);

    await waitFor(() => {
      // "no stages available" text rendered in multiple sections
      const msgs = screen.getAllByText(/no stages available/i);
      expect(msgs.length).toBeGreaterThan(0);
    });
  });

  it('calls adminCrm.getFunnel on mount', async () => {
    const { default: OnboardingFunnel } = await import('./OnboardingFunnel');
    render(<OnboardingFunnel />);

    await waitFor(() => {
      expect(mockAdminCrm.getFunnel).toHaveBeenCalledTimes(1);
    });
  });

  it('renders conversion rate between stages', async () => {
    const { default: OnboardingFunnel } = await import('./OnboardingFunnel');
    render(<OnboardingFunnel />);

    await waitFor(() => expect(screen.getAllByText('Registered').length).toBeGreaterThan(0), { timeout: 3000 });

    // Profile Complete / Registered = 350/500 = 70%; formatPercent strips .0 → "70%"
    const seventyPct = screen.getAllByText(/70%/);
    expect(seventyPct.length).toBeGreaterThan(0);
  });

  it('renders navigation links to CRM and members pages', async () => {
    const { default: OnboardingFunnel } = await import('./OnboardingFunnel');
    render(<OnboardingFunnel />);

    await waitFor(() => expect(screen.getAllByText('Registered').length).toBeGreaterThan(0), { timeout: 3000 });

    // The component renders <Link> elements (role="link") to CRM dashboard / all members
    const links = screen.getAllByRole('link');
    expect(links.length).toBeGreaterThan(0);
    // At least one link should point to a CRM or users admin path
    const hasCrmOrUsersLink = links.some(
      (l) =>
        l.getAttribute('href')?.includes('crm') ||
        l.getAttribute('href')?.includes('users') ||
        l.textContent?.toLowerCase().includes('crm') ||
        l.textContent?.toLowerCase().includes('dashboard') ||
        l.textContent?.toLowerCase().includes('member')
    );
    expect(hasCrmOrUsersLink).toBe(true);
  });
});
