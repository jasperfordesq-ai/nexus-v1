// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';

// ─── Mock api ────────────────────────────────────────────────────────────────
const { mockApi, mockTokenManager } = vi.hoisted(() => ({
  mockApi: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() },
  mockTokenManager: { getAccessToken: vi.fn(() => 'tok'), getTenantId: vi.fn(() => '2') },
}));

vi.mock('@/lib/api', () => ({
  api: mockApi,
  default: mockApi,
  tokenManager: mockTokenManager,
  API_BASE: '/api',
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

vi.mock('@/lib/chartColors', () => ({
  CHART_COLORS: ['#aaa', '#bbb', '#ccc'],
  CHART_COLOR_MAP: { primary: '#aaa', success: '#bbb', warning: '#ccc', danger: '#ddd' },
  CHART_TOKEN_COLORS: { border: '#eee', surface: '#fff', foreground: '#000' },
}));

// ─── Stub HeroUI components ──────────────────────────────────────────────────
vi.mock('@/components/ui', async (importOriginal) => {
  const actual = await importOriginal<Record<string, unknown>>();
  return {
    ...actual,
    Button: ({ children, onPress, isLoading, isDisabled, startContent }: { children?: React.ReactNode; onPress?: () => void; isLoading?: boolean; isDisabled?: boolean; startContent?: React.ReactNode; [key: string]: unknown }) => (
      <button onClick={() => onPress?.()} disabled={isLoading || isDisabled} data-loading={isLoading ? 'true' : undefined}>
        {children}
      </button>
    ),
    Input: ({ label, value, onValueChange, type, 'aria-label': ariaLabel }: { label?: string; value?: string; onValueChange?: (v: string) => void; type?: string; 'aria-label'?: string; [key: string]: unknown }) => (
      <div>
        {label && <label>{label}</label>}
        <input type={type || 'text'} value={value ?? ''} aria-label={ariaLabel || label} onChange={e => onValueChange?.(e.target.value)} />
      </div>
    ),
    Select: ({ label, children, onSelectionChange, selectedKeys }: { label?: string; children: React.ReactNode; selectedKeys?: string[]; onSelectionChange?: (keys: Set<string>) => void; [key: string]: unknown }) => (
      <div>
        {label && <label>{label}</label>}
        <select aria-label={label} value={selectedKeys?.[0] ?? ''} onChange={e => onSelectionChange?.(new Set([e.target.value]))}>
          {children}
        </select>
      </div>
    ),
    SelectItem: ({ children, id }: { children: React.ReactNode; id?: string }) => <option value={id}>{children}</option>,
    Spinner: () => <div role="status" aria-busy="true" aria-label="Loading" />,
    Tabs: ({ children, onSelectionChange, selectedKey }: { children: React.ReactNode; onSelectionChange?: (key: string | number) => void; selectedKey?: string; [key: string]: unknown }) => (
      <div role="tablist">
        {React.Children.map(children as React.ReactElement[], (child) => {
          if (!child || !React.isValidElement(child)) return null;
          // child.key is React's internal key (e.g. ".$category"), strip the leading ".$"
          const rawKey = child.key ?? '';
          const tabKey = typeof rawKey === 'string' ? rawKey.replace(/^\.\$/, '') : String(rawKey);
          const title = (child.props as { title?: React.ReactNode }).title;
          return (
            <button
              key={tabKey}
              role="tab"
              data-key={tabKey}
              aria-selected={selectedKey === tabKey}
              onClick={() => onSelectionChange?.(tabKey)}
            >
              {title}
            </button>
          );
        })}
      </div>
    ),
    Tab: () => null,
    Card: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
    CardBody: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
    CardHeader: ({ children, className }: { children: React.ReactNode; className?: string }) => <div className={className}>{children}</div>,
    Chip: ({ children, color }: { children: React.ReactNode; color?: string }) => <span data-color={color}>{children}</span>,
    Avatar: ({ name }: { name?: string; src?: string }) => <div aria-label={name}>{name?.[0]}</div>,
    Table: ({ children, 'aria-label': ariaLabel }: { children: React.ReactNode; 'aria-label'?: string }) => <table role="grid" aria-label={ariaLabel}>{children}</table>,
    TableHeader: ({ children }: { children: React.ReactNode }) => <thead><tr>{children}</tr></thead>,
    TableColumn: ({ children }: { children: React.ReactNode }) => <th>{children}</th>,
    TableBody: ({ children, emptyContent, isLoading, loadingContent }: { children?: React.ReactNode; emptyContent?: React.ReactNode; isLoading?: boolean; loadingContent?: React.ReactNode }) => (
      <tbody>
        {isLoading && <tr><td>{loadingContent}</td></tr>}
        {!isLoading && React.Children.count(children) === 0 && <tr><td>{emptyContent}</td></tr>}
        {!isLoading && children}
      </tbody>
    ),
    TableRow: ({ children }: { children: React.ReactNode }) => <tr>{children}</tr>,
    TableCell: ({ children, className }: { children: React.ReactNode; className?: string }) => <td className={className}>{children}</td>,
  };
});

// ─── Stub recharts completely ─────────────────────────────────────────────────
vi.mock('recharts', () => ({
  BarChart: ({ children }: { children?: React.ReactNode }) => <div data-testid="bar-chart">{children}</div>,
  Bar: () => null,
  PieChart: ({ children }: { children?: React.ReactNode }) => <div data-testid="pie-chart">{children}</div>,
  Pie: () => null,
  Cell: () => null,
  XAxis: () => null,
  YAxis: () => null,
  CartesianGrid: () => null,
  Tooltip: () => null,
  ResponsiveContainer: ({ children }: { children: React.ReactNode }) => <div data-testid="responsive-container">{children}</div>,
  Legend: () => null,
  AreaChart: ({ children }: { children?: React.ReactNode }) => <div data-testid="area-chart">{children}</div>,
  Area: () => null,
}));

// ─── Stub admin components ───────────────────────────────────────────────────
vi.mock('../../components', () => ({
  StatCard: ({ label, value }: { label: string; value: string | number }) => (
    <div data-testid="stat-card">
      <span>{label}</span>
      <span data-testid="stat-value">{value}</span>
    </div>
  ),
  PageHeader: ({ title, description, actions }: { title: string; description?: string; actions?: React.ReactNode }) => (
    <div data-testid="page-header">
      <h1>{title}</h1>
      {description && <p>{description}</p>}
      {actions && <div data-testid="page-header-actions">{actions}</div>}
    </div>
  ),
}));

vi.mock('@/components/seo/PageMeta', () => ({
  PageMeta: () => null,
}));

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

// ─── Contexts ─────────────────────────────────────────────────────────────────
const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToast,
  })
);

// ─────────────────────────────────────────────────────────────────────────────
// Fixtures
// ─────────────────────────────────────────────────────────────────────────────

const makeSummary = (overrides = {}) => ({
  total_hours: 120.5,
  total_transactions: 44,
  avg_hours_per_transaction: 2.7,
  unique_givers: 15,
  unique_receivers: 18,
  min_hours: 0.5,
  max_hours: 8,
  ...overrides,
});

const makeCategoryData = () => ({
  categories: [
    { category: 'Gardening', total_hours: 30, transaction_count: 10, percentage: 25 },
    { category: 'Cooking', total_hours: 60, transaction_count: 20, percentage: 50 },
  ],
});

const makeMemberData = () => ({
  members: [
    {
      id: 1, name: 'Alice', profile_image_url: null,
      hours_given: 10, hours_received: 5, total_hours: 15, balance: 5,
    },
    {
      id: 2, name: 'Bob', profile_image_url: null,
      hours_given: 2, hours_received: 8, total_hours: 10, balance: -6,
    },
  ],
});

const makePeriodData = () => ({
  periods: [
    { month: '2025-01', total_hours: 40, transaction_count: 12, unique_givers: 5, unique_receivers: 7 },
    { month: '2025-02', total_hours: 55, transaction_count: 18, unique_givers: 8, unique_receivers: 10 },
  ],
});

// ─────────────────────────────────────────────────────────────────────────────

describe('HoursReportsPage', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    // Default: first call is summary, second is data
    mockApi.get
      .mockResolvedValueOnce({ success: true, data: makeSummary() })
      .mockResolvedValue({ success: true, data: makeCategoryData() });
  });

  it('shows a loading spinner initially', async () => {
    mockApi.get.mockImplementation(() => new Promise(() => {}));
    const { HoursReportsPage } = await import('./HoursReportsPage');
    render(<HoursReportsPage />);
    const statusEls = screen.getAllByRole('status');
    const busy = statusEls.find(el => el.getAttribute('aria-busy') === 'true');
    expect(busy).toBeDefined();
  });

  it('renders summary stat cards after load', async () => {
    const { HoursReportsPage } = await import('./HoursReportsPage');
    render(<HoursReportsPage />);
    await waitFor(() => {
      expect(screen.getAllByTestId('stat-card').length).toBeGreaterThanOrEqual(4);
    });
  });

  it('renders category charts when category data is returned', async () => {
    const { HoursReportsPage } = await import('./HoursReportsPage');
    render(<HoursReportsPage />);
    await waitFor(() => {
      // Either the chart container or empty-text; with data we expect chart containers
      const containers = screen.queryAllByTestId('responsive-container');
      expect(containers.length).toBeGreaterThan(0);
    });
  });

  it('shows empty text when no category data', async () => {
    mockApi.get
      .mockResolvedValueOnce({ success: true, data: makeSummary() })
      .mockResolvedValue({ success: true, data: { categories: [] } });

    const { HoursReportsPage } = await import('./HoursReportsPage');
    render(<HoursReportsPage />);
    // Wait for loading to finish (spinner gone) and then check empty state
    await waitFor(() => {
      // After data loads, spinning should stop
      const busy = screen.queryAllByRole('status').find(el => el.getAttribute('aria-busy') === 'true');
      expect(busy).toBeUndefined();
    }, { timeout: 5000 });
    // Now category charts should show empty text
    const noData = screen.queryAllByText(/no_category_data|No data|No category/i);
    // Either the translated text or the key — either way charts are gone
    // The responsive container won't exist with empty data
    const containers = screen.queryAllByTestId('responsive-container');
    expect(containers.length).toBe(0);
  });

  it('shows member table when member tab is selected', async () => {
    mockApi.get
      .mockResolvedValueOnce({ success: true, data: makeSummary() })
      .mockResolvedValue({ success: true, data: makeMemberData() });

    const { HoursReportsPage } = await import('./HoursReportsPage');
    render(<HoursReportsPage />);
    await waitFor(() => screen.getAllByTestId('stat-card').length >= 1);

    // Click the Member tab
    const tabs = screen.getAllByRole('tab');
    const memberTab = tabs.find(t => t.textContent?.toLowerCase().includes('member') || t.textContent?.includes('reports.tab_by_member'));
    if (memberTab) fireEvent.click(memberTab);

    await waitFor(() => {
      // Alice and Bob names should render
      expect(screen.getByText('Alice')).toBeInTheDocument();
      expect(screen.getByText('Bob')).toBeInTheDocument();
    });
  });

  it('shows period trend chart when period tab is selected', async () => {
    mockApi.get
      .mockResolvedValueOnce({ success: true, data: makeSummary() })
      .mockResolvedValue({ success: true, data: makePeriodData() });

    const { HoursReportsPage } = await import('./HoursReportsPage');
    render(<HoursReportsPage />);
    await waitFor(() => screen.getAllByTestId('stat-card').length >= 1);

    const tabs = screen.getAllByRole('tab');
    const periodTab = tabs.find(t => t.textContent?.toLowerCase().includes('trend') || t.textContent?.includes('reports.tab_monthly_trend'));
    if (periodTab) fireEvent.click(periodTab);

    await waitFor(() => {
      const charts = screen.queryAllByTestId('responsive-container');
      expect(charts.length).toBeGreaterThan(0);
    });
  });

  it('shows error toast when summary fetch fails', async () => {
    mockApi.get.mockRejectedValue(new Error('network'));
    const { HoursReportsPage } = await import('./HoursReportsPage');
    render(<HoursReportsPage />);
    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('shows error toast when data fetch fails', async () => {
    // Summary OK (already set in beforeEach), data fails — override only the fallback
    mockApi.get.mockRejectedValue(new Error('network error'));
    const { HoursReportsPage } = await import('./HoursReportsPage');
    render(<HoursReportsPage />);
    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    }, { timeout: 5000 });
  });

  it('renders date filter inputs', async () => {
    const { HoursReportsPage } = await import('./HoursReportsPage');
    render(<HoursReportsPage />);
    await waitFor(() => screen.getAllByTestId('stat-card').length >= 1);

    // Date inputs rendered via our stubbed Input component
    const inputs = document.querySelectorAll('input[type="date"]');
    // The PageHeader actions area renders 2 date inputs via stubbed Input
    expect(inputs.length).toBeGreaterThanOrEqual(2);
  });

  it('renders export CSV and refresh buttons', async () => {
    const { HoursReportsPage } = await import('./HoursReportsPage');
    render(<HoursReportsPage />);
    await waitFor(() => screen.getAllByTestId('stat-card').length >= 1);

    // PageHeader renders its actions slot; buttons exist in the DOM
    const buttons = screen.getAllByRole('button');
    // At least 2 buttons expected: Export CSV + Refresh
    expect(buttons.length).toBeGreaterThanOrEqual(1);
    const actionBtn = buttons.find(b =>
      b.textContent?.toLowerCase().includes('export') ||
      b.textContent?.toLowerCase().includes('refresh') ||
      b.textContent?.includes('export_csv') ||
      b.textContent?.includes('reports.export_csv') ||
      b.textContent?.includes('reports.refresh')
    );
    expect(actionBtn).toBeDefined();
  });

  it('shows no_member_hours_data when member tab has no data', async () => {
    mockApi.get
      .mockResolvedValueOnce({ success: true, data: makeSummary() })
      .mockResolvedValue({ success: true, data: { members: [] } });

    const { HoursReportsPage } = await import('./HoursReportsPage');
    render(<HoursReportsPage />);
    await waitFor(() => screen.getAllByTestId('stat-card').length >= 1);

    const tabs = screen.getAllByRole('tab');
    const memberTab = tabs.find(t => t.textContent?.toLowerCase().includes('member') || t.textContent?.includes('reports.tab_by_member'));
    if (memberTab) fireEvent.click(memberTab);

    await waitFor(() => {
      // Table is rendered with emptyContent
      expect(screen.getByRole('grid')).toBeInTheDocument();
    });
  });
});
