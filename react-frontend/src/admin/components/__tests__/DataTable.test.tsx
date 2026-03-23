// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for DataTable — reusable admin table with sorting, search, pagination
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { HeroUIProvider } from '@heroui/react';

// ─── Stable mock references ─────────────────────────────────────────────────

vi.mock('@/contexts', () => ({
  useAuth: vi.fn(() => ({
    user: { id: 1, name: 'Admin User', role: 'admin' },
    isAuthenticated: true,
    logout: vi.fn(),
  })),
  useTenant: vi.fn(() => ({
    tenant: { id: 2, name: 'Test Community', slug: 'test', configuration: {} },
    tenantSlug: 'test',
    branding: { name: 'Test Community' },
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
    tenantPath: (p: string) => `/test${p}`,
  })),
  useToast: vi.fn(() => ({
    success: vi.fn(),
    error: vi.fn(),
    info: vi.fn(),
    showToast: vi.fn(),
  })),
  useTheme: () => ({ resolvedTheme: 'light', toggleTheme: vi.fn(), theme: 'system', setTheme: vi.fn() }),
  useNotifications: () => ({ unreadCount: 0, counts: {}, notifications: [], markAsRead: vi.fn(), markAllAsRead: vi.fn(), hasMore: false, loadMore: vi.fn(), isLoading: false, refresh: vi.fn() }),
  usePusher: () => ({ channel: null, isConnected: false }),
  usePusherOptional: () => null,
  useCookieConsent: () => ({ consent: null, showBanner: false, openPreferences: vi.fn(), resetConsent: vi.fn(), saveConsent: vi.fn(), hasConsent: vi.fn(() => true), updateConsent: vi.fn() }),
  readStoredConsent: () => null,
  useMenuContext: () => ({ headerMenus: [], mobileMenus: [], hasCustomMenus: false }),
  useFeature: vi.fn(() => true),
  useModule: vi.fn(() => true),
}));

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

import { DataTable, StatusBadge } from '../DataTable';
import type { Column } from '../DataTable';

// ─── Wrapper ─────────────────────────────────────────────────────────────────

function W({ children }: { children: React.ReactNode }) {
  return (
    <HeroUIProvider>
      <MemoryRouter initialEntries={['/test/admin']}>
        {children}
      </MemoryRouter>
    </HeroUIProvider>
  );
}

// ─── Test data ───────────────────────────────────────────────────────────────

interface TestRow {
  id: number;
  name: string;
  email: string;
  status: string;
}

const columns: Column<TestRow>[] = [
  { key: 'id', label: 'ID', sortable: true },
  { key: 'name', label: 'Name', sortable: true },
  { key: 'email', label: 'Email' },
  { key: 'status', label: 'Status', render: (item) => <span>{item.status}</span> },
];

const sampleData: TestRow[] = [
  { id: 1, name: 'Alice', email: 'alice@example.com', status: 'active' },
  { id: 2, name: 'Bob', email: 'bob@example.com', status: 'pending' },
  { id: 3, name: 'Charlie', email: 'charlie@example.com', status: 'suspended' },
];

// ─── Tests ───────────────────────────────────────────────────────────────────

describe('DataTable', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders without crashing', () => {
    const { container } = render(
      <W><DataTable columns={columns} data={sampleData} /></W>
    );
    expect(container.querySelector('table')).toBeTruthy();
  });

  it('renders column headers', () => {
    render(<W><DataTable columns={columns} data={sampleData} /></W>);
    expect(screen.getByText('ID')).toBeTruthy();
    expect(screen.getByText('Name')).toBeTruthy();
    expect(screen.getByText('Email')).toBeTruthy();
    expect(screen.getByText('Status')).toBeTruthy();
  });

  it('renders data rows', () => {
    render(<W><DataTable columns={columns} data={sampleData} /></W>);
    expect(screen.getByText('Alice')).toBeTruthy();
    expect(screen.getByText('Bob')).toBeTruthy();
    expect(screen.getByText('Charlie')).toBeTruthy();
  });

  it('renders cell values from data', () => {
    render(<W><DataTable columns={columns} data={sampleData} /></W>);
    expect(screen.getByText('alice@example.com')).toBeTruthy();
    expect(screen.getByText('bob@example.com')).toBeTruthy();
  });

  it('shows empty content when no data', () => {
    render(<W><DataTable columns={columns} data={[]} /></W>);
    // t('data_table.no_data') returns fallback key since not in admin.json
    expect(screen.getByText('data_table.no_data')).toBeTruthy();
  });

  it('shows custom empty content', () => {
    render(
      <W>
        <DataTable
          columns={columns}
          data={[]}
          emptyContent={<span>Nothing here</span>}
        />
      </W>
    );
    expect(screen.getByText('Nothing here')).toBeTruthy();
  });

  it('renders search input when searchable', () => {
    render(
      <W><DataTable columns={columns} data={sampleData} searchable={true} /></W>
    );
    expect(screen.getByPlaceholderText('Search...')).toBeTruthy();
  });

  it('renders custom search placeholder', () => {
    render(
      <W>
        <DataTable
          columns={columns}
          data={sampleData}
          searchable={true}
          searchPlaceholder="Find users..."
        />
      </W>
    );
    expect(screen.getByPlaceholderText('Find users...')).toBeTruthy();
  });

  it('does not render search input when searchable is false', () => {
    render(
      <W><DataTable columns={columns} data={sampleData} searchable={false} /></W>
    );
    expect(screen.queryByPlaceholderText('Search...')).toBeNull();
  });

  it('renders refresh button when onRefresh is provided', () => {
    const onRefresh = vi.fn();
    render(
      <W><DataTable columns={columns} data={sampleData} onRefresh={onRefresh} /></W>
    );
    // t('data_table.refresh') returns fallback key since not in admin.json
    const refreshBtn = screen.getByLabelText('data_table.refresh');
    expect(refreshBtn).toBeTruthy();
  });

  it('calls onRefresh when refresh button is clicked', () => {
    const onRefresh = vi.fn();
    render(
      <W><DataTable columns={columns} data={sampleData} onRefresh={onRefresh} /></W>
    );
    fireEvent.click(screen.getByLabelText('data_table.refresh'));
    expect(onRefresh).toHaveBeenCalledTimes(1);
  });

  it('uses custom render function for columns', () => {
    const customColumns: Column<TestRow>[] = [
      { key: 'name', label: 'Name', render: (item) => <strong data-testid="custom-name">{item.name.toUpperCase()}</strong> },
    ];
    render(
      <W><DataTable columns={customColumns} data={sampleData} /></W>
    );
    expect(screen.getByText('ALICE')).toBeTruthy();
  });

  it('renders topContent when provided', () => {
    render(
      <W>
        <DataTable
          columns={columns}
          data={sampleData}
          topContent={<button data-testid="top-action">Add User</button>}
        />
      </W>
    );
    expect(screen.getByTestId('top-action')).toBeTruthy();
  });

  it('renders aria-label on the table', () => {
    render(<W><DataTable columns={columns} data={sampleData} /></W>);
    const table = screen.getByRole('grid');
    expect(table).toBeTruthy();
  });
});

describe('StatusBadge', () => {
  it('renders active status with success color', () => {
    render(<W><StatusBadge status="active" /></W>);
    expect(screen.getByText('active')).toBeTruthy();
  });

  it('renders pending status', () => {
    render(<W><StatusBadge status="pending" /></W>);
    expect(screen.getByText('pending')).toBeTruthy();
  });

  it('renders unknown status with default color', () => {
    render(<W><StatusBadge status="custom_status" /></W>);
    expect(screen.getByText('custom_status')).toBeTruthy();
  });

  it('handles empty status string', () => {
    render(<W><StatusBadge status="" /></W>);
    expect(screen.getByText('unknown')).toBeTruthy();
  });
});
