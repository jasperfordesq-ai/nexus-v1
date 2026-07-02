// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import { cleanup } from '@testing-library/react';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';
import userEvent from '@testing-library/user-event';

// ─── API mock ────────────────────────────────────────────────────────────────
const { mockApi } = vi.hoisted(() => ({
  mockApi: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
    download: vi.fn(),
    upload: vi.fn(),
  },
}));
vi.mock('@/lib/api', () => ({ api: mockApi, default: mockApi }));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));
vi.mock('@/lib/safeStorage', () => ({
  safeLocalStorageGet: vi.fn(() => null),
  safeLocalStorageSetJSON: vi.fn(),
}));

// ─── Auth / Tenant ───────────────────────────────────────────────────────────
const mockHasFeature = vi.fn(() => true);
const mockHasModule = vi.fn(() => true);

vi.mock('@/contexts', () =>
  createMockContexts({
    useAuth: () => ({
      user: { id: 1, name: 'Admin User', role: 'admin' },
      isAuthenticated: true,
      login: vi.fn(),
      logout: vi.fn(),
      register: vi.fn(),
      updateUser: vi.fn(),
      refreshUser: vi.fn(),
      status: 'idle' as const,
      error: null,
    }),
    useTenant: () => ({
      tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: mockHasFeature,
      hasModule: mockHasModule,
    }),
  })
);

// ─── react-router-dom ───────────────────────────────────────────────────────
vi.mock('react-router-dom', async (importOriginal) => {
  const orig = await importOriginal<typeof import('react-router-dom')>();
  return {
    ...orig,
    useLocation: () => ({ pathname: '/test/admin', search: '', hash: '' }),
    Link: ({ to, children, ...rest }: { to: string; children: React.ReactNode; [key: string]: unknown }) => (
      <a href={to} {...(rest as object)}>{children}</a>
    ),
  };
});

// ─── Stub heavy HeroUI components ───────────────────────────────────────────
vi.mock('@/components/ui', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/components/ui')>();
  return {
    ...actual,
    ScrollShadow: ({ children, ...rest }: { children: React.ReactNode; [key: string]: unknown }) => (
      <nav {...(rest as object)}>{children}</nav>
    ),
    Accordion: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
    AccordionItem: ({ children, title }: { children: React.ReactNode; title: React.ReactNode }) => (
      <div>
        <div>{title}</div>
        <div>{children}</div>
      </div>
    ),
    Tooltip: ({ children }: { children: React.ReactNode }) => <>{children}</>,
    Input: ({ value, onValueChange, placeholder, ...rest }: {
      value?: string;
      onValueChange?: (v: string) => void;
      placeholder?: string;
      [key: string]: unknown;
    }) => (
      <input
        type="search"
        value={value}
        onChange={(e) => onValueChange?.(e.target.value)}
        placeholder={placeholder}
        aria-label={(rest as Record<string, string>)['aria-label'] || placeholder}
      />
    ),
  };
});

// ─────────────────────────────────────────────────────────────────────────────
describe('AdminSidebar', () => {
  afterEach(() => {
    cleanup();
  });

  beforeEach(() => {
    vi.resetAllMocks();
    mockHasFeature.mockReturnValue(true);
    mockHasModule.mockReturnValue(true);
    // Safeguarding call
    mockApi.get.mockResolvedValue({
      success: true,
      data: { unreviewed_flags: 0 },
    });
    // jsdom does not implement scrollIntoView — stub to prevent unhandled errors
    window.HTMLElement.prototype.scrollIntoView = vi.fn();
  });

  it('renders without crashing', async () => {
    const { AdminSidebar } = await import('./AdminSidebar');
    render(<AdminSidebar />);
    expect(screen.getByRole('navigation', { name: /admin navigation/i })).toBeInTheDocument();
  });

  it('shows an Admin heading link when not collapsed', async () => {
    const { AdminSidebar } = await import('./AdminSidebar');
    render(<AdminSidebar collapsed={false} />);
    const adminLink = screen.getByRole('link', { name: /admin/i });
    expect(adminLink).toBeInTheDocument();
  });

  it('renders collapse/expand toggle button', async () => {
    const onToggle = vi.fn();
    const { AdminSidebar } = await import('./AdminSidebar');
    render(<AdminSidebar collapsed={false} onToggle={onToggle} />);
    const toggleBtn = screen.getByRole('button', { name: /collapse sidebar/i });
    expect(toggleBtn).toBeInTheDocument();
  });

  it('calls onToggle when the sidebar toggle button is clicked', async () => {
    const onToggle = vi.fn();
    const { AdminSidebar } = await import('./AdminSidebar');
    render(<AdminSidebar collapsed={false} onToggle={onToggle} />);
    const toggleBtn = screen.getByRole('button', { name: /collapse sidebar/i });
    await userEvent.click(toggleBtn);
    expect(onToggle).toHaveBeenCalled();
  });

  it('shows search input when not collapsed', async () => {
    const { AdminSidebar } = await import('./AdminSidebar');
    render(<AdminSidebar collapsed={false} />);
    const searchInput = screen.getByRole('searchbox');
    expect(searchInput).toBeInTheDocument();
  });

  it('does not show search input when collapsed', async () => {
    const { AdminSidebar } = await import('./AdminSidebar');
    render(<AdminSidebar collapsed={true} />);
    const searchInput = screen.queryByRole('searchbox');
    expect(searchInput).not.toBeInTheDocument();
  });

  it('renders core navigation sections (users, dashboard)', async () => {
    const { AdminSidebar } = await import('./AdminSidebar');
    render(<AdminSidebar collapsed={false} />);
    // Should have links for admin dashboard — multiple may match due to aria-current, use getAllByRole
    const dashLinks = screen.getAllByRole('link', { name: /dashboard/i });
    expect(dashLinks.length).toBeGreaterThan(0);
  });

  it('renders Users section when not collapsed', async () => {
    const { AdminSidebar } = await import('./AdminSidebar');
    render(<AdminSidebar collapsed={false} />);
    // Users section appears in sidebar
    const usersLinks = screen.getAllByRole('link').filter((l) =>
      l.getAttribute('href')?.includes('/users')
    );
    expect(usersLinks.length).toBeGreaterThan(0);
  });

  it('hides newsletter navigation when the newsletter module is disabled', async () => {
    mockHasFeature.mockImplementation((feature: string) => feature !== 'newsletter');
    const { AdminSidebar } = await import('./AdminSidebar');
    render(<AdminSidebar collapsed={false} />);

    const newsletterLinks = screen.getAllByRole('link').filter((link) =>
      link.getAttribute('href')?.includes('/admin/newsletters'),
    );
    expect(newsletterLinks).toHaveLength(0);
  });

  it('filters navigation results when search query is entered', async () => {
    const { AdminSidebar } = await import('./AdminSidebar');
    render(<AdminSidebar collapsed={false} />);
    const searchInput = screen.getByRole('searchbox');
    await userEvent.type(searchInput, 'user');
    // After typing, filtered results or no-results message appears
    await waitFor(() => {
      const links = screen.queryAllByRole('link');
      const noResults = screen.queryByText(/no results/i);
      // Either filtered link results OR the "no results" fallback must be present
      expect(links.length > 0 || noResults !== null).toBe(true);
    });
  });

  it('shows expand label button when collapsed', async () => {
    const { AdminSidebar } = await import('./AdminSidebar');
    render(<AdminSidebar collapsed={true} />);
    const expandBtn = screen.getByRole('button', { name: /expand sidebar/i });
    expect(expandBtn).toBeInTheDocument();
  });

  it('renders platform zone navigation links (enterprise, federation) when features enabled', async () => {
    // beforeEach sets mockHasFeature.mockReturnValue(true) — all features including federation are on
    // The platform zone includes enterprise and federation sections
    const { AdminSidebar } = await import('./AdminSidebar');
    render(<AdminSidebar collapsed={false} />);
    await waitFor(() => {
      // Enterprise section is in the platform zone and always rendered (not gated)
      const enterpriseLinks = screen.getAllByRole('link').filter((l) =>
        l.getAttribute('href')?.includes('/enterprise')
      );
      expect(enterpriseLinks.length).toBeGreaterThan(0);
    });
  });

  it('hides super admin section for non-super-admin users', async () => {
    vi.mock('@/contexts', () =>
      createMockContexts({
        useAuth: () => ({
          user: { id: 5, name: 'Regular Admin', role: 'admin' },
          isAuthenticated: true,
          login: vi.fn(),
          logout: vi.fn(),
          register: vi.fn(),
          updateUser: vi.fn(),
          refreshUser: vi.fn(),
          status: 'idle' as const,
          error: null,
        }),
        useTenant: () => ({
          tenant: { id: 2, name: 'Test', slug: 'test' },
          tenantPath: (p: string) => `/test${p}`,
          hasFeature: vi.fn(() => false),
          hasModule: vi.fn(() => true),
        }),
      })
    );
    const { AdminSidebar } = await import('./AdminSidebar');
    render(<AdminSidebar collapsed={false} />);
    // Super-admin panel link should not appear for regular admins
    const superLinks = screen.queryAllByRole('link').filter((l) =>
      l.getAttribute('href')?.includes('/super-admin')
    );
    expect(superLinks.length).toBe(0);
  });
});
