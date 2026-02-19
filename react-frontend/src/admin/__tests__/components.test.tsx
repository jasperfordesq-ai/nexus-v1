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

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { HeroUIProvider } from '@heroui/react';
import { Users, Clock } from 'lucide-react';

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
}));

vi.mock('@/hooks', () => ({
  usePageTitle: vi.fn(),
}));

function Wrapper({ children }: { children: React.ReactNode }) {
  return (
    <HeroUIProvider>
      <MemoryRouter initialEntries={['/test/admin']}>
        {children}
      </MemoryRouter>
    </HeroUIProvider>
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
    expect(screen.getByText('Dashboard')).toBeInTheDocument();
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
    expect(screen.getByText('System')).toBeInTheDocument();
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
    expect(screen.getByText('Super Admin')).toBeInTheDocument();
  });

  it('shows federation section when feature enabled', () => {
    render(
      <Wrapper>
        <AdminSidebar collapsed={false} onToggle={vi.fn()} />
      </Wrapper>
    );
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
      <HeroUIProvider>
        <StatCard label="Total Users" value={1234} icon={Users} />
      </HeroUIProvider>
    );
    expect(screen.getByText('Total Users')).toBeInTheDocument();
    expect(screen.getByText('1,234')).toBeInTheDocument();
  });

  it('shows loading state', () => {
    const { container } = render(
      <HeroUIProvider>
        <StatCard label="Total Users" value={0} icon={Users} loading={true} />
      </HeroUIProvider>
    );
    expect(container.querySelector('.animate-pulse')).toBeInTheDocument();
  });

  it('renders string value', () => {
    render(
      <HeroUIProvider>
        <StatCard label="Status" value="Active" icon={Users} />
      </HeroUIProvider>
    );
    expect(screen.getByText('Active')).toBeInTheDocument();
  });

  it('shows positive trend indicator', () => {
    render(
      <HeroUIProvider>
        <StatCard label="Users" value={100} icon={Users} trend={15} trendLabel="vs last month" />
      </HeroUIProvider>
    );
    expect(screen.getByText('+15%')).toBeInTheDocument();
    expect(screen.getByText('vs last month')).toBeInTheDocument();
  });

  it('shows negative trend indicator', () => {
    render(
      <HeroUIProvider>
        <StatCard label="Users" value={100} icon={Users} trend={-5} />
      </HeroUIProvider>
    );
    expect(screen.getByText('-5%')).toBeInTheDocument();
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// EmptyState
// ─────────────────────────────────────────────────────────────────────────────

import { EmptyState } from '../components/EmptyState';

describe('EmptyState', () => {
  it('renders title', () => {
    render(
      <HeroUIProvider>
        <EmptyState title="No users found" />
      </HeroUIProvider>
    );
    expect(screen.getByText('No users found')).toBeInTheDocument();
  });

  it('renders description', () => {
    render(
      <HeroUIProvider>
        <EmptyState title="No users" description="Try adjusting your filters" />
      </HeroUIProvider>
    );
    expect(screen.getByText('Try adjusting your filters')).toBeInTheDocument();
  });

  it('renders action button when provided', () => {
    const onAction = vi.fn();
    render(
      <HeroUIProvider>
        <EmptyState title="No users" actionLabel="Create User" onAction={onAction} />
      </HeroUIProvider>
    );
    const btn = screen.getByText('Create User');
    expect(btn).toBeInTheDocument();
    fireEvent.click(btn);
    expect(onAction).toHaveBeenCalledTimes(1);
  });

  it('does not render action button without onAction', () => {
    render(
      <HeroUIProvider>
        <EmptyState title="No users" actionLabel="Create User" />
      </HeroUIProvider>
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
    render(<PageHeader title="User Management" />);
    expect(screen.getByText('User Management')).toBeInTheDocument();
  });

  it('renders description', () => {
    render(<PageHeader title="Users" description="Manage platform users" />);
    expect(screen.getByText('Manage platform users')).toBeInTheDocument();
  });

  it('renders actions slot', () => {
    render(
      <PageHeader title="Users" actions={<button>Add User</button>} />
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
      <HeroUIProvider>
        <ConfirmModal
          isOpen={true}
          onClose={vi.fn()}
          onConfirm={vi.fn()}
          title="Delete User"
          message="Are you sure you want to delete this user?"
        />
      </HeroUIProvider>
    );
    expect(screen.getByText('Delete User')).toBeInTheDocument();
    expect(screen.getByText('Are you sure you want to delete this user?')).toBeInTheDocument();
  });

  it('shows default confirm label', () => {
    render(
      <HeroUIProvider>
        <ConfirmModal
          isOpen={true}
          onClose={vi.fn()}
          onConfirm={vi.fn()}
          title="Delete"
          message="Confirm?"
        />
      </HeroUIProvider>
    );
    expect(screen.getByText('Confirm')).toBeInTheDocument();
  });

  it('shows custom confirm label', () => {
    render(
      <HeroUIProvider>
        <ConfirmModal
          isOpen={true}
          onClose={vi.fn()}
          onConfirm={vi.fn()}
          title="Ban"
          message="Ban user?"
          confirmLabel="Ban User"
        />
      </HeroUIProvider>
    );
    expect(screen.getByText('Ban User')).toBeInTheDocument();
  });

  it('shows Cancel button', () => {
    render(
      <HeroUIProvider>
        <ConfirmModal
          isOpen={true}
          onClose={vi.fn()}
          onConfirm={vi.fn()}
          title="Delete"
          message="Confirm?"
        />
      </HeroUIProvider>
    );
    expect(screen.getByText('Cancel')).toBeInTheDocument();
  });

  it('renders children content', () => {
    render(
      <HeroUIProvider>
        <ConfirmModal
          isOpen={true}
          onClose={vi.fn()}
          onConfirm={vi.fn()}
          title="Delete"
          message="Confirm?"
        >
          <p>Extra warning text</p>
        </ConfirmModal>
      </HeroUIProvider>
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
      <HeroUIProvider>
        <StatusBadge status="active" />
      </HeroUIProvider>
    );
    expect(screen.getByText('active')).toBeInTheDocument();
  });

  it('handles unknown status', () => {
    render(
      <HeroUIProvider>
        <StatusBadge status="custom_status" />
      </HeroUIProvider>
    );
    expect(screen.getByText('custom_status')).toBeInTheDocument();
  });

  it('handles empty status gracefully', () => {
    render(
      <HeroUIProvider>
        <StatusBadge status="" />
      </HeroUIProvider>
    );
    expect(screen.getByText('unknown')).toBeInTheDocument();
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
      <HeroUIProvider>
        <DataTable columns={columns} data={data} />
      </HeroUIProvider>
    );
    expect(screen.getByText('Name')).toBeInTheDocument();
    expect(screen.getByText('Email')).toBeInTheDocument();
  });

  it('renders data rows', () => {
    render(
      <HeroUIProvider>
        <DataTable columns={columns} data={data} />
      </HeroUIProvider>
    );
    expect(screen.getByText('Alice')).toBeInTheDocument();
    expect(screen.getByText('Bob')).toBeInTheDocument();
  });

  it('shows empty content when no data', () => {
    render(
      <HeroUIProvider>
        <DataTable columns={columns} data={[]} emptyContent="No results" />
      </HeroUIProvider>
    );
    expect(screen.getByText('No results')).toBeInTheDocument();
  });

  it('renders search input when searchable', () => {
    render(
      <HeroUIProvider>
        <DataTable columns={columns} data={data} searchable={true} searchPlaceholder="Search users..." />
      </HeroUIProvider>
    );
    expect(screen.getByPlaceholderText('Search users...')).toBeInTheDocument();
  });

  it('renders refresh button when onRefresh provided', () => {
    const onRefresh = vi.fn();
    render(
      <HeroUIProvider>
        <DataTable columns={columns} data={data} onRefresh={onRefresh} />
      </HeroUIProvider>
    );
    const btn = screen.getByLabelText('Refresh');
    expect(btn).toBeInTheDocument();
    fireEvent.click(btn);
    expect(onRefresh).toHaveBeenCalledTimes(1);
  });
});
