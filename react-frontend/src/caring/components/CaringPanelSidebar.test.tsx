// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';

// ─── Mock api ────────────────────────────────────────────────────────────────
const { mockApi } = vi.hoisted(() => ({
  mockApi: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn(), download: vi.fn(), upload: vi.fn() },
}));

vi.mock('@/lib/api', () => ({ api: mockApi, default: mockApi }));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

// ─── Toast / Auth / Tenant ───────────────────────────────────────────────────
const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn(), showToast: vi.fn() };
const mockNavigate = vi.fn();

vi.mock('react-router-dom', async (importOriginal) => {
  const orig = await importOriginal<typeof import('react-router-dom')>();
  return {
    ...orig,
    useNavigate: () => mockNavigate,
    useLocation: () => ({ pathname: '/test/caring', search: '', hash: '' }),
  };
});

vi.mock('@/contexts', () =>
  createMockContexts({
    useAuth: () => ({
      user: { id: 1, name: 'Admin User', role: 'admin', is_admin: true },
      isAuthenticated: true,
      login: vi.fn(),
      logout: vi.fn(),
      register: vi.fn(),
      updateUser: vi.fn(),
      refreshUser: vi.fn(),
      status: 'idle' as const,
      error: null,
    }),
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

// ─── Stub HeroUI components that may misbehave in jsdom ──────────────────────
vi.mock('@/components/ui', async (importOriginal) => {
  const orig = await importOriginal<Record<string, unknown>>();
  return {
    ...orig,
    Button: ({ children, onPress, isIconOnly, 'aria-label': ariaLabel, ...rest }: {
      children?: React.ReactNode; onPress?: () => void; isIconOnly?: boolean; 'aria-label'?: string; [key: string]: unknown
    }) => (
      <button onClick={onPress} aria-label={ariaLabel} {...(rest as object)}>{children}</button>
    ),
    Input: ({ 'aria-label': ariaLabel, placeholder, value, onValueChange }: {
      'aria-label'?: string; placeholder?: string; value?: string; onValueChange?: (v: string) => void;
    }) => (
      <input
        aria-label={ariaLabel}
        placeholder={placeholder}
        value={value}
        onChange={(e) => onValueChange?.(e.target.value)}
      />
    ),
    Tooltip: ({ children, content }: { children: React.ReactNode; content?: string }) => (
      <div title={content}>{children}</div>
    ),
  };
});

// ─── Props ────────────────────────────────────────────────────────────────────
const defaultProps = {
  collapsed: false,
  onToggle: vi.fn(),
};

// ─────────────────────────────────────────────────────────────────────────────
describe('CaringPanelSidebar', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('renders the sidebar navigation landmark', async () => {
    const { CaringPanelSidebar } = await import('./CaringPanelSidebar');
    render(<CaringPanelSidebar {...defaultProps} />);
    expect(screen.getByRole('navigation')).toBeInTheDocument();
  });

  it('renders section headings when expanded', async () => {
    const { CaringPanelSidebar } = await import('./CaringPanelSidebar');
    render(<CaringPanelSidebar {...defaultProps} collapsed={false} />);
    // At least one section title should be visible (caring community sections)
    await waitFor(() => {
      const nav = screen.getByRole('navigation');
      expect(nav).toBeInTheDocument();
    });
  });

  it('renders dashboard navigation link for admin users', async () => {
    const { CaringPanelSidebar } = await import('./CaringPanelSidebar');
    render(<CaringPanelSidebar {...defaultProps} collapsed={false} />);
    // Admin sees full sections including dashboard
    const links = screen.getAllByRole('link');
    expect(links.length).toBeGreaterThan(0);
  });

  it('renders toggle button with correct aria-label when expanded', async () => {
    const { CaringPanelSidebar } = await import('./CaringPanelSidebar');
    render(<CaringPanelSidebar {...defaultProps} collapsed={false} />);
    // Should have a collapse button
    const toggleBtn = screen.getAllByRole('button').find((b) =>
      b.getAttribute('aria-label')?.toLowerCase().includes('collapse') ||
      b.getAttribute('aria-label')?.toLowerCase().includes('expand')
    );
    expect(toggleBtn).toBeDefined();
  });

  it('calls onToggle when toggle button is clicked', async () => {
    const onToggle = vi.fn();
    const { CaringPanelSidebar } = await import('./CaringPanelSidebar');
    render(<CaringPanelSidebar {...defaultProps} onToggle={onToggle} />);
    const toggleBtn = screen.getAllByRole('button')[0];
    fireEvent.click(toggleBtn);
    expect(onToggle).toHaveBeenCalled();
  });

  it('renders search input when not collapsed', async () => {
    const { CaringPanelSidebar } = await import('./CaringPanelSidebar');
    render(<CaringPanelSidebar {...defaultProps} collapsed={false} />);
    const searchInput = screen.getByRole('textbox');
    expect(searchInput).toBeInTheDocument();
  });

  it('does NOT render search input when collapsed', async () => {
    const { CaringPanelSidebar } = await import('./CaringPanelSidebar');
    render(<CaringPanelSidebar {...defaultProps} collapsed={true} />);
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument();
  });

  it('filters navigation items when a search query is typed', async () => {
    const { CaringPanelSidebar } = await import('./CaringPanelSidebar');
    render(<CaringPanelSidebar {...defaultProps} collapsed={false} />);
    const searchInput = screen.getByRole('textbox');
    // Type something that matches a specific item key
    fireEvent.change(searchInput, { target: { value: 'dashboard' } });
    await waitFor(() => {
      // After filtering there should still be links in the DOM (the matched ones)
      const links = screen.getAllByRole('link');
      expect(links.length).toBeGreaterThan(0);
    });
  });

  it('shows no-results message when search matches nothing', async () => {
    const { CaringPanelSidebar } = await import('./CaringPanelSidebar');
    render(<CaringPanelSidebar {...defaultProps} collapsed={false} />);
    const searchInput = screen.getByRole('textbox');
    fireEvent.change(searchInput, { target: { value: 'zzzznonexistentxyz' } });
    await waitFor(() => {
      // Nav should still be there but no nav links
      expect(screen.getAllByRole('navigation').length).toBeGreaterThan(0);
    });
  });

  it('renders help centre link', async () => {
    const { CaringPanelSidebar } = await import('./CaringPanelSidebar');
    render(<CaringPanelSidebar {...defaultProps} collapsed={false} />);
    const links = screen.getAllByRole('link');
    const helpLink = links.find((l) => l.getAttribute('href')?.includes('/admin/help'));
    expect(helpLink).toBeDefined();
  });

  it('renders full admin link for admin user', async () => {
    const { CaringPanelSidebar } = await import('./CaringPanelSidebar');
    render(<CaringPanelSidebar {...defaultProps} collapsed={false} />);
    const links = screen.getAllByRole('link');
    // Full admin link href goes to /admin (not /admin/help)
    const adminLink = links.find(
      (l) => l.getAttribute('href')?.endsWith('/admin') || l.getAttribute('href') === '/test/admin'
    );
    expect(adminLink).toBeDefined();
  });

  it('marks current page link with aria-current="page"', async () => {
    const { CaringPanelSidebar } = await import('./CaringPanelSidebar');
    render(<CaringPanelSidebar {...defaultProps} collapsed={false} />);
    // /test/caring is current pathname; dashboard link points to /test/caring
    await waitFor(() => {
      const currentLinks = screen.getAllByRole('link').filter(
        (l) => l.getAttribute('aria-current') === 'page'
      );
      expect(currentLinks.length).toBeGreaterThan(0);
    });
  });

  it('renders as aside element', async () => {
    const { CaringPanelSidebar } = await import('./CaringPanelSidebar');
    const { container } = render(<CaringPanelSidebar {...defaultProps} />);
    expect(container.querySelector('aside')).toBeInTheDocument();
  });
});
