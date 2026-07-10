// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for admin shared components:
 * - AdminSidebar
 * - AdminHeader
 * - AdminBreadcrumbs
 * - StatCard
 * - EmptyState
 * - PageHeader
 * - ConfirmModal
 * - DataTable / StatusBadge
 */

import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import Users from 'lucide-react/icons/users';

vi.mock('@/contexts', () => ({
  useAuth: vi.fn(() => ({
    user: { id: 1, first_name: 'Admin', last_name: 'User', name: 'Admin User', role: 'admin', is_super_admin: true },
    isAuthenticated: true,
    logout: vi.fn(),
  })),
  useTenant: vi.fn(() => ({
    tenant: { id: 2, name: 'Test Community', slug: 'test' },
    tenantSlug: 'test',
    tenantPath: (p: string) => `/test${p}`,
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
  })),
  useToast: vi.fn(() => ({ success: vi.fn(), error: vi.fn(), info: vi.fn() })),

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

vi.mock('@/hooks', () => ({
  usePageTitle: vi.fn(),
}));

function Wrapper({ children }: { children: React.ReactNode }) {
  return (
    <>
      <MemoryRouter initialEntries={['/test/admin']}>
        {children}
      </MemoryRouter>
    </>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// AdminSidebar
// ─────────────────────────────────────────────────────────────────────────────

import { AdminSidebar } from '../components/AdminSidebar';

describe('AdminSidebar', () => {
  it('renders without crashing', () => {
    render(
      <Wrapper>
        <AdminSidebar collapsed={false} onToggle={vi.fn()} />
      </Wrapper>
    );
    expect(screen.getByText('Admin')).toBeInTheDocument();
  });

  it('renders Dashboard link', () => {
    render(
      <Wrapper>
        <AdminSidebar collapsed={false} onToggle={vi.fn()} />
      </Wrapper>
    );
    expect(screen.getAllByText('Dashboard').length).toBeGreaterThan(0);
  });

  it('renders key navigation sections', () => {
    render(
      <Wrapper>
        <AdminSidebar collapsed={false} onToggle={vi.fn()} />
      </Wrapper>
    );
    expect(screen.getByText('Users')).toBeInTheDocument();
    expect(screen.getByText('Listings')).toBeInTheDocument();
    expect(screen.getByText('Content')).toBeInTheDocument();
    expect(screen.getByText('Platform Operations')).toBeInTheDocument();
  });

  it('hides labels when collapsed', () => {
    render(
      <Wrapper>
        <AdminSidebar collapsed={true} onToggle={vi.fn()} />
      </Wrapper>
    );
    // "Admin" text should not be visible when collapsed
    expect(screen.queryByText('Admin')).not.toBeInTheDocument();
  });

  it('calls onToggle when toggle button is clicked', () => {
    const onToggle = vi.fn();
    render(
      <Wrapper>
        <AdminSidebar collapsed={false} onToggle={onToggle} />
      </Wrapper>
    );
    const btn = screen.getByLabelText(/collapse sidebar/i);
    fireEvent.click(btn);
    expect(onToggle).toHaveBeenCalledTimes(1);
  });

  it('shows Super Admin section for super admins', () => {
    render(
      <Wrapper>
        <AdminSidebar collapsed={false} onToggle={vi.fn()} />
      </Wrapper>
    );
    // Label renders via t('super_admin_panel') from admin_nav.json => "Super Admin Panel"
    // (admin sidebar was i18n-converted; the old literal "Super Admin" no longer matches).
    expect(screen.getByText('Super Admin Panel')).toBeInTheDocument();
  });

  it('shows the Partner Timebanks panel entry for super admins with federation enabled', () => {
    render(
      <Wrapper>
        <AdminSidebar collapsed={false} onToggle={vi.fn()} />
      </Wrapper>
    );
    // Single entry to /partner-timebanks — the old 15-link federation and
    // integrations sections were retired on 2026-07-02.
    expect(screen.getByText('Partner Timebanks')).toBeInTheDocument();
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// AdminHeader
// ─────────────────────────────────────────────────────────────────────────────

import { AdminHeader } from '../components/AdminHeader';

describe('AdminHeader', () => {
  it('renders without crashing', () => {
    render(
      <Wrapper>
        <AdminHeader sidebarCollapsed={false} />
      </Wrapper>
    );
    expect(screen.getByText('Back to site')).toBeInTheDocument();
  });

  it('shows the tenant name', () => {
    render(
      <Wrapper>
        <AdminHeader sidebarCollapsed={false} />
      </Wrapper>
    );
    expect(screen.getByText('Test Community')).toBeInTheDocument();
  });

  it('shows the user name', () => {
    render(
      <Wrapper>
        <AdminHeader sidebarCollapsed={false} />
      </Wrapper>
    );
    expect(screen.getByText('Admin User')).toBeInTheDocument();
  });

  it('has notifications button', () => {
    render(
      <Wrapper>
        <AdminHeader sidebarCollapsed={false} />
      </Wrapper>
    );
    expect(screen.getByLabelText('Notifications')).toBeInTheDocument();
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// AdminBreadcrumbs
// ─────────────────────────────────────────────────────────────────────────────

import { AdminBreadcrumbs } from '../components/AdminBreadcrumbs';

describe('AdminBreadcrumbs', () => {
  it('renders nothing when only one segment', () => {
    const { container } = render(
      <MemoryRouter initialEntries={['/test/admin']}>
        <AdminBreadcrumbs />
      </MemoryRouter>
    );
    // Only 1 breadcrumb segment = should not render
    expect(container.querySelector('nav')).toBeNull();
  });

  it('renders breadcrumbs for deeper paths', () => {
    render(
      <MemoryRouter initialEntries={['/test/admin/users']}>
        <AdminBreadcrumbs />
      </MemoryRouter>
    );
    expect(screen.getByText('Admin')).toBeInTheDocument();
    expect(screen.getByText('Users')).toBeInTheDocument();
  });

  it('renders custom items when provided', () => {
    render(
      <MemoryRouter initialEntries={['/test/admin']}>
        <AdminBreadcrumbs items={[
          { label: 'Admin', href: '/admin' },
          { label: 'Custom Page' },
        ]} />
      </MemoryRouter>
    );
    expect(screen.getByText('Custom Page')).toBeInTheDocument();
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// StatCard
// ─────────────────────────────────────────────────────────────────────────────

import { StatCard } from '../components/StatCard';

describe('StatCard', () => {
  it('renders label and value', () => {
    render(
      <>
        <StatCard label="Total Users" value={1234} icon={Users} />
      </>
    );
    expect(screen.getByText('Total Users')).toBeInTheDocument();
    expect(screen.getByText('1,234')).toBeInTheDocument();
  });

  it('shows loading state', () => {
    const { container } = render(
      <>
        <StatCard label="Total Users" value={0} icon={Users} loading={true} />
      </>
    );
    expect(container.querySelector('[role="status"]')).toBeInTheDocument();
  });

  it('renders string value', () => {
    render(
      <>
        <StatCard label="Status" value="Active" icon={Users} />
      </>
    );
    expect(screen.getByText('Active')).toBeInTheDocument();
  });

  it('shows positive trend indicator', () => {
    render(
      <>
        <StatCard label="Users" value={100} icon={Users} trend={15} trendLabel="vs last month" />
      </>
    );
    expect(screen.getByText('+15%')).toBeInTheDocument();
    expect(screen.getByText('vs last month')).toBeInTheDocument();
  });

  it('shows negative trend indicator', () => {
    render(
      <>
        <StatCard label="Users" value={100} icon={Users} trend={-5} />
      </>
    );
    expect(screen.getByText('-5%')).toBeInTheDocument();
  });

  it('renders Lucide forwardRef icon as SVG, not as a raw object (regression: React #31)', () => {
    const errors: unknown[] = [];
    const orig = console.error;
    console.error = (...args: unknown[]) => { errors.push(args); };
    try {
      const { container } = render(
        <>
          <StatCard label="Total Users" value={1234} icon={Users} />
        </>
      );
      expect(container.querySelector('svg.lucide-users')).toBeInTheDocument();
    } finally {
      console.error = orig;
    }
    const reactErrors = errors.filter((args) =>
      Array.isArray(args) &&
      args.some((a) => typeof a === 'string' && (a.includes('React error #31') || a.includes('Objects are not valid as a React child')))
    );
    expect(reactErrors).toEqual([]);
  });

  it('accepts a pre-rendered JSX icon node', () => {
    const { container } = render(
      <>
        <StatCard label="Custom" value={42} icon={<span data-testid="custom-icon">★</span>} />
      </>
    );
    expect(container.querySelector('[data-testid="custom-icon"]')).toBeInTheDocument();
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// EmptyState
// ─────────────────────────────────────────────────────────────────────────────

import { EmptyState } from '../components/EmptyState';

describe('EmptyState', () => {
  it('renders title', () => {
    render(
      <>
        <EmptyState title="No users found" />
      </>
    );
    expect(screen.getByText('No users found')).toBeInTheDocument();
  });

  it('renders description', () => {
    render(
      <>
        <EmptyState title="No users" description="Try adjusting your filters" />
      </>
    );
    expect(screen.getByText('Try adjusting your filters')).toBeInTheDocument();
  });

  it('renders action button when provided', () => {
    const onAction = vi.fn();
    render(
      <>
        <EmptyState title="No users" actionLabel="Create User" onAction={onAction} />
      </>
    );
    const btn = screen.getByText('Create User');
    expect(btn).toBeInTheDocument();
    fireEvent.click(btn);
    expect(onAction).toHaveBeenCalledTimes(1);
  });

  it('does not render action button without onAction', () => {
    render(
      <>
        <EmptyState title="No users" actionLabel="Create User" />
      </>
    );
    expect(screen.queryByText('Create User')).not.toBeInTheDocument();
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// PageHeader
// ─────────────────────────────────────────────────────────────────────────────

import { PageHeader } from '../components/PageHeader';

describe('PageHeader', () => {
  it('renders title', () => {
    render(<Wrapper><PageHeader title="User Management" /></Wrapper>);
    expect(screen.getByText('User Management')).toBeInTheDocument();
  });

  it('renders description', () => {
    render(<Wrapper><PageHeader title="Users" description="Manage platform users" /></Wrapper>);
    expect(screen.getByText('Manage platform users')).toBeInTheDocument();
  });

  it('renders actions slot', () => {
    render(
      <Wrapper><PageHeader title="Users" actions={<button>Add User</button>} /></Wrapper>
    );
    expect(screen.getByText('Add User')).toBeInTheDocument();
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// ConfirmModal
// ─────────────────────────────────────────────────────────────────────────────

import { ConfirmModal } from '../components/ConfirmModal';

describe('ConfirmModal', () => {
  it('renders when open', () => {
    render(
      <>
        <ConfirmModal
          isOpen={true}
          onClose={vi.fn()}
          onConfirm={vi.fn()}
          title="Delete User"
          message="Are you sure you want to delete this user?"
        />
      </>
    );
    expect(screen.getByText('Delete User')).toBeInTheDocument();
    expect(screen.getByText('Are you sure you want to delete this user?')).toBeInTheDocument();
  });

  it('shows default confirm label', () => {
    render(
      <>
        <ConfirmModal
          isOpen={true}
          onClose={vi.fn()}
          onConfirm={vi.fn()}
          title="Delete"
          message="Confirm?"
        />
      </>
    );
    expect(screen.getByText('Confirm')).toBeInTheDocument();
  });

  it('shows custom confirm label', () => {
    render(
      <>
        <ConfirmModal
          isOpen={true}
          onClose={vi.fn()}
          onConfirm={vi.fn()}
          title="Ban"
          message="Ban user?"
          confirmLabel="Ban User"
        />
      </>
    );
    expect(screen.getByText('Ban User')).toBeInTheDocument();
  });

  it('shows Cancel button', () => {
    render(
      <>
        <ConfirmModal
          isOpen={true}
          onClose={vi.fn()}
          onConfirm={vi.fn()}
          title="Delete"
          message="Confirm?"
        />
      </>
    );
    expect(screen.getByText('Cancel')).toBeInTheDocument();
  });

  it('renders children content', () => {
    render(
      <>
        <ConfirmModal
          isOpen={true}
          onClose={vi.fn()}
          onConfirm={vi.fn()}
          title="Delete"
          message="Confirm?"
        >
          <p>Extra warning text</p>
        </ConfirmModal>
      </>
    );
    expect(screen.getByText('Extra warning text')).toBeInTheDocument();
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// StatusBadge
// ─────────────────────────────────────────────────────────────────────────────

import { StatusBadge } from '../components/DataTable';

describe('StatusBadge', () => {
  it('renders status text', () => {
    render(
      <>
        <StatusBadge status="active" />
      </>
    );
    expect(screen.getByText('Active')).toBeInTheDocument();
  });

  it('handles unknown status', () => {
    render(
      <>
        <StatusBadge status="custom_status" />
      </>
    );
    expect(screen.getByText('Unknown')).toBeInTheDocument();
  });

  it('handles empty status gracefully', () => {
    render(
      <>
        <StatusBadge status="" />
      </>
    );
    expect(screen.getByText('Unknown')).toBeInTheDocument();
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// DataTable
// ─────────────────────────────────────────────────────────────────────────────

import { DataTable } from '../components/DataTable';

describe('DataTable', () => {
  const columns = [
    { key: 'name', label: 'Name' },
    { key: 'email', label: 'Email' },
  ];

  const data = [
    { id: 1, name: 'Alice', email: 'alice@test.com' },
    { id: 2, name: 'Bob', email: 'bob@test.com' },
  ];

  it('renders without crashing', () => {
    render(
      <>
        <DataTable columns={columns} data={data} />
      </>
    );
    expect(screen.getByText('Name')).toBeInTheDocument();
    expect(screen.getByText('Email')).toBeInTheDocument();
  });

  it('renders data rows', () => {
    render(
      <>
        <DataTable columns={columns} data={data} />
      </>
    );
    expect(screen.getByText('Alice')).toBeInTheDocument();
    expect(screen.getByText('Bob')).toBeInTheDocument();
  });

  it('shows empty content when no data', () => {
    render(
      <>
        <DataTable columns={columns} data={[]} emptyContent="No results" />
      </>
    );
    expect(screen.getByText('No results')).toBeInTheDocument();
  });

  it('renders search input when searchable', () => {
    render(
      <>
        <DataTable columns={columns} data={data} searchable={true} searchPlaceholder="Search users..." />
      </>
    );
    expect(screen.getByPlaceholderText('Search users...')).toBeInTheDocument();
  });

  it('renders refresh button when onRefresh provided', () => {
    const onRefresh = vi.fn();
    render(
      <>
        <DataTable columns={columns} data={data} onRefresh={onRefresh} />
      </>
    );
    const btn = screen.getByLabelText('Refresh');
    expect(btn).toBeInTheDocument();
    fireEvent.click(btn);
    expect(onRefresh).toHaveBeenCalledTimes(1);
  });
});
