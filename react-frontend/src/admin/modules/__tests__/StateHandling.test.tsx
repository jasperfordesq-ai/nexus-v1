// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { beforeEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen, waitFor } from '@/test/test-utils';

const apiMocks = vi.hoisted(() => ({
  getPermissions: vi.fn(),
  getApprovals: vi.fn(),
  getMatchingStats: vi.fn(),
  getSubscriptions: vi.fn(),
}));

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));
vi.mock('@/components/ui', async () => (await import('@/test/uiMock')).uiMock);

vi.mock('../../api/adminApi', () => ({
  adminEnterprise: { getPermissions: apiMocks.getPermissions },
  adminMatching: {
    getApprovals: apiMocks.getApprovals,
    getMatchingStats: apiMocks.getMatchingStats,
  },
  adminPlans: { getSubscriptions: apiMocks.getSubscriptions },
}));

vi.mock('../../components/PageHeader', () => ({
  PageHeader: ({ title, description, actions }: { title: string; description?: string; actions?: React.ReactNode }) => (
    <header>
      <h1>{title}</h1>
      {description && <p>{description}</p>}
      {actions}
    </header>
  ),
}));

vi.mock('../../components/EmptyState', () => ({
  EmptyState: ({
    title,
    description,
    actionLabel,
    onAction,
  }: {
    title: string;
    description?: string;
    actionLabel?: string;
    onAction?: () => void;
  }) => (
    <section>
      <h2>{title}</h2>
      {description && <p>{description}</p>}
      {actionLabel && onAction && <button type="button" onClick={onAction}>{actionLabel}</button>}
    </section>
  ),
}));

vi.mock('../../components/DataTable', () => ({
  DataTable: ({ data, onRefresh }: { data: Array<Record<string, unknown>>; onRefresh?: () => void }) => (
    <section data-testid="data-table">
      <pre>{JSON.stringify(data)}</pre>
      {onRefresh && <button type="button" onClick={onRefresh}>Table refresh</button>}
    </section>
  ),
  StatusBadge: ({ status }: { status: string }) => <span>{status}</span>,
}));

vi.mock('../../components/StatCard', () => ({
  StatCard: ({ label, value }: { label: string; value: string | number }) => (
    <div><span>{label}</span><strong>{value}</strong></div>
  ),
}));

import { PermissionBrowser } from '../enterprise/PermissionBrowser';
import { SmartMatchMonitoring } from '../community/SmartMatchMonitoring';
import { SmartMatchUsers } from '../community/SmartMatchUsers';
import { Subscriptions } from '../content/Subscriptions';

const backendDetail = 'SQLSTATE secret backend detail';

const zeroStats = {
  overview: {
    total_matches_today: 0,
    total_matches_week: 0,
    total_matches_month: 0,
    hot_matches_count: 0,
    mutual_matches_count: 0,
    avg_match_score: 0,
    avg_distance_km: 0,
    cache_entries: 0,
    cache_hit_rate: 0,
    active_users_matching: 0,
  },
  score_distribution: {},
  distance_distribution: {},
  broker_approval_enabled: false,
  pending_approvals: 0,
  approved_count: 0,
  rejected_count: 0,
  approval_rate: 0,
};

beforeEach(() => {
  vi.clearAllMocks();
  apiMocks.getPermissions.mockResolvedValue({ success: true, data: {} });
  apiMocks.getApprovals.mockResolvedValue({ success: true, data: [] });
  apiMocks.getMatchingStats.mockResolvedValue({ success: true, data: zeroStats });
  apiMocks.getSubscriptions.mockResolvedValue({ success: true, data: [] });
});

describe('PermissionBrowser load states', () => {
  it.each([
    ['resolved failure', () => Promise.resolve({ success: false, error: backendDetail })],
    ['rejected request', () => Promise.reject(new Error(backendDetail))],
  ])('renders %s as an error, never as an empty permission set', async (_label, response) => {
    apiMocks.getPermissions.mockImplementationOnce(response);
    render(<PermissionBrowser />);

    expect(await screen.findByRole('alert')).toHaveTextContent('Failed to load data');
    expect(screen.queryByText('No data available')).not.toBeInTheDocument();
    expect(screen.queryByText(backendDetail)).not.toBeInTheDocument();
  });

  it('retries an initial failure and renders the confirmed permission map', async () => {
    apiMocks.getPermissions
      .mockResolvedValueOnce({ success: false, error: backendDetail })
      .mockResolvedValueOnce({ success: true, data: { security: ['users.view'] } });
    render(<PermissionBrowser />);

    fireEvent.click(await screen.findByRole('button', { name: 'Retry' }));
    expect(await screen.findByText('users.view')).toBeInTheDocument();
    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
  });

  it('retains confirmed permissions when a refresh fails', async () => {
    apiMocks.getPermissions
      .mockResolvedValueOnce({ success: true, data: { security: ['users.view'] } })
      .mockResolvedValueOnce({ success: false, error: backendDetail });
    render(<PermissionBrowser />);

    expect(await screen.findByText('users.view')).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Refresh' }));
    expect(await screen.findByRole('alert')).toHaveTextContent('Failed to load data');
    expect(screen.getByText('users.view')).toBeInTheDocument();
  });

  it('renders a genuine success-empty permission map as empty', async () => {
    render(<PermissionBrowser />);
    expect(await screen.findByText('No data available')).toBeInTheDocument();
    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
  });
});

describe('SmartMatchUsers load states', () => {
  it('treats success:false as an error instead of no match results', async () => {
    apiMocks.getApprovals.mockResolvedValueOnce({ success: false, error: backendDetail });
    render(<SmartMatchUsers />);

    expect(await screen.findByRole('alert')).toHaveTextContent('Failed to load match results');
    expect(screen.queryByText('No match results')).not.toBeInTheDocument();
    expect(screen.queryByText(backendDetail)).not.toBeInTheDocument();
  });

  it('retains confirmed matches when refresh rejects', async () => {
    apiMocks.getApprovals
      .mockResolvedValueOnce({ success: true, data: [{ id: 7, user_1_name: 'Ada', user_2_name: 'Grace' }] })
      .mockRejectedValueOnce(new Error(backendDetail));
    render(<SmartMatchUsers />);

    expect(await screen.findByText(/Ada/)).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Refresh' }));
    expect(await screen.findByRole('alert')).toHaveTextContent('Failed to load match results');
    expect(screen.getByText(/Ada/)).toBeInTheDocument();
  });

  it('renders a confirmed empty result only for success:true', async () => {
    render(<SmartMatchUsers />);
    expect(await screen.findByText('No match results')).toBeInTheDocument();
    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
  });
});

describe('Subscriptions load states', () => {
  it('treats success:false as an error instead of no subscriptions', async () => {
    apiMocks.getSubscriptions.mockResolvedValueOnce({ success: false, error: backendDetail });
    render(<Subscriptions />);

    expect(await screen.findByRole('alert')).toHaveTextContent('Failed to load subscriptions');
    expect(screen.queryByText('No data available')).not.toBeInTheDocument();
    expect(screen.queryByText(backendDetail)).not.toBeInTheDocument();
  });

  it('retries a rejection and keeps confirmed subscription data across a later failed refresh', async () => {
    apiMocks.getSubscriptions
      .mockRejectedValueOnce(new Error(backendDetail))
      .mockResolvedValueOnce({ success: true, data: [{ id: 4, tenant_name: 'Hour Timebank', plan_name: 'Community' }] })
      .mockResolvedValueOnce({ success: false, error: backendDetail });
    render(<Subscriptions />);

    fireEvent.click(await screen.findByRole('button', { name: 'Retry' }));
    expect(await screen.findByText(/Hour Timebank/)).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Refresh' }));
    expect(await screen.findByRole('alert')).toHaveTextContent('Failed to load subscriptions');
    expect(screen.getByText(/Hour Timebank/)).toBeInTheDocument();
  });

  it('renders a genuine success-empty subscription list as empty', async () => {
    render(<Subscriptions />);
    expect(await screen.findByText('No data available')).toBeInTheDocument();
    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
  });
});

describe('SmartMatchMonitoring load states', () => {
  it('does not turn a failed envelope into zero monitoring metrics', async () => {
    apiMocks.getMatchingStats.mockResolvedValueOnce({ success: false, error: backendDetail });
    render(<SmartMatchMonitoring />);

    expect(await screen.findByRole('alert')).toHaveTextContent('Failed to load matching stats');
    expect(screen.queryByText('Matches Generated')).not.toBeInTheDocument();
    expect(screen.queryByText(backendDetail)).not.toBeInTheDocument();
  });

  it('renders confirmed numeric zeroes and a genuine empty distribution', async () => {
    render(<SmartMatchMonitoring />);

    expect(await screen.findByText('Matches Generated')).toBeInTheDocument();
    expect(screen.getAllByText('0').length).toBeGreaterThan(0);
    expect(screen.getByText('No score distribution')).toBeInTheDocument();
    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
  });

  it('retains confirmed zero metrics when refresh resolves success:false', async () => {
    apiMocks.getMatchingStats
      .mockResolvedValueOnce({ success: true, data: zeroStats })
      .mockResolvedValueOnce({ success: false, error: backendDetail });
    render(<SmartMatchMonitoring />);

    expect(await screen.findByText('Matches Generated')).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Refresh' }));
    expect(await screen.findByRole('alert')).toHaveTextContent('Failed to load matching stats');
    expect(screen.getByText('Matches Generated')).toBeInTheDocument();
  });
});
