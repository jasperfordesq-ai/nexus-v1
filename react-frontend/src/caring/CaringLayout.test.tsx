// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

vi.mock('@/contexts', () =>
  createMockContexts({
    useAuth: () => ({
      user: { id: 1, name: 'Care Worker', role: 'admin' },
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
      tenantSlug: 'test',
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  }),
);

// Mock child components that have heavy internal dependencies
vi.mock('./components/CaringPanelSidebar', () => ({
  CaringPanelSidebar: ({ collapsed }: { collapsed: boolean }) => (
    <nav data-testid="caring-sidebar" data-collapsed={String(collapsed)}>
      Sidebar
    </nav>
  ),
}));

vi.mock('./components/CaringPanelHeader', () => ({
  CaringPanelHeader: ({
    onSidebarToggle,
  }: {
    sidebarCollapsed: boolean;
    onSidebarToggle?: () => void;
  }) => (
    <header data-testid="caring-header">
      <button onClick={onSidebarToggle} aria-label="Open navigation">
        Menu
      </button>
    </header>
  ),
}));

vi.mock('./components/CaringPanelBreadcrumbs', () => ({
  CaringPanelBreadcrumbs: () => (
    <nav aria-label="breadcrumb" data-testid="caring-breadcrumbs" />
  ),
}));

// Outlet renders children injected by the router; mock it as a placeholder
vi.mock('react-router-dom', async (importOriginal) => {
  const actual = await importOriginal<typeof import('react-router-dom')>();
  return {
    ...actual,
    Outlet: () => <div data-testid="outlet-content">Page content</div>,
  };
});

import { CaringLayout } from './CaringLayout';

describe('CaringLayout', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders the header', () => {
    render(<CaringLayout />);
    expect(screen.getByTestId('caring-header')).toBeInTheDocument();
  });

  it('renders the sidebar (desktop, not collapsed by default)', () => {
    render(<CaringLayout />);
    // The desktop sidebar is inside a hidden-on-mobile div; check data attribute
    const sidebars = screen.getAllByTestId('caring-sidebar');
    expect(sidebars.length).toBeGreaterThanOrEqual(1);
    expect(sidebars[0]).toHaveAttribute('data-collapsed', 'false');
  });

  it('renders the breadcrumbs', () => {
    render(<CaringLayout />);
    expect(screen.getByTestId('caring-breadcrumbs')).toBeInTheDocument();
  });

  it('renders the Outlet (child route content)', () => {
    render(<CaringLayout />);
    expect(screen.getByTestId('outlet-content')).toBeInTheDocument();
    expect(screen.getByText('Page content')).toBeInTheDocument();
  });

  it('renders a <main> landmark wrapping the page content', () => {
    render(<CaringLayout />);
    expect(screen.getByRole('main')).toBeInTheDocument();
  });

  it('opens the mobile drawer when the menu button is clicked', () => {
    render(<CaringLayout />);

    // No dialog before click
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: /open navigation/i }));

    expect(screen.getByRole('dialog')).toBeInTheDocument();
    expect(screen.getByRole('dialog')).toHaveAttribute('aria-modal', 'true');
  });

  it('closes the mobile drawer when the backdrop overlay is clicked', () => {
    render(<CaringLayout />);

    fireEvent.click(screen.getByRole('button', { name: /open navigation/i }));
    expect(screen.getByRole('dialog')).toBeInTheDocument();

    // Backdrop button closes the drawer
    const backdrop = screen.getByRole('button', { name: /close/i });
    fireEvent.click(backdrop);

    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });

  it('closes the mobile drawer on Escape key', () => {
    render(<CaringLayout />);

    fireEvent.click(screen.getByRole('button', { name: /open navigation/i }));
    expect(screen.getByRole('dialog')).toBeInTheDocument();

    fireEvent.keyDown(window, { key: 'Escape' });

    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });
});
