// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for AdminSidebar — collapsible sidebar navigation
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';

// ─── Stable mock references ─────────────────────────────────────────────────

const mockTenantPath = (p: string) => `/test${p}`;
const mockOnToggle = vi.fn();
const mockHasFeature = vi.fn((feature: string) => feature === 'caring_community');
const mockUser: Record<string, unknown> = {
  id: 1,
  name: 'Admin User',
  role: 'super_admin',
  is_super_admin: true,
};

vi.mock('@/contexts', () => ({
  useAuth: vi.fn(() => ({
    user: mockUser,
    isAuthenticated: true,
    logout: vi.fn(),
  })),
  useTenant: vi.fn(() => ({
    tenant: { id: 2, name: 'Test Community', slug: 'test', configuration: {} },
    tenantSlug: 'test',
    branding: { name: 'Test Community' },
    hasFeature: mockHasFeature,
    hasModule: vi.fn(() => true),
    tenantPath: mockTenantPath,
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

vi.mock('../../api/adminApi', () => ({
  adminBroker: {
    getUnreviewedCount: vi.fn(() => Promise.resolve({ success: true, data: { count: 0 } })),
  },
}));

import { AdminSidebar } from '../AdminSidebar';

// ─── Wrapper ─────────────────────────────────────────────────────────────────

function W({ children, path = '/test/admin' }: { children: React.ReactNode; path?: string }) {
  return (
    <>
      <MemoryRouter initialEntries={[path]}>
        {children}
      </MemoryRouter>
    </>
  );
}

// ─── Tests ───────────────────────────────────────────────────────────────────

describe('AdminSidebar', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    Object.assign(mockUser, {
      id: 1,
      name: 'Admin User',
      role: 'super_admin',
      is_super_admin: true,
      is_tenant_super_admin: undefined,
      is_god: undefined,
    });
    mockHasFeature.mockImplementation((feature: string) => feature === 'caring_community');
  });

  it('renders without crashing', () => {
    const { container } = render(
      <W><AdminSidebar collapsed={false} onToggle={mockOnToggle} /></W>
    );
    expect(container.querySelector('aside')).toBeTruthy();
  });

  it('renders the Admin link when not collapsed', () => {
    render(
      <W><AdminSidebar collapsed={false} onToggle={mockOnToggle} /></W>
    );
    expect(screen.getByText('Admin')).toBeTruthy();
  });

  it('does not render Admin text when collapsed', () => {
    render(
      <W><AdminSidebar collapsed={true} onToggle={mockOnToggle} /></W>
    );
    expect(screen.queryByText('Admin')).toBeNull();
  });

  it('renders the collapse sidebar button', () => {
    render(
      <W><AdminSidebar collapsed={false} onToggle={mockOnToggle} /></W>
    );
    expect(screen.getByLabelText('Collapse sidebar')).toBeTruthy();
  });

  it('renders expand sidebar button when collapsed', () => {
    render(
      <W><AdminSidebar collapsed={true} onToggle={mockOnToggle} /></W>
    );
    expect(screen.getByLabelText('Expand sidebar')).toBeTruthy();
  });

  it('calls onToggle when toggle button is clicked', () => {
    render(
      <W><AdminSidebar collapsed={false} onToggle={mockOnToggle} /></W>
    );
    fireEvent.click(screen.getByLabelText('Collapse sidebar'));
    expect(mockOnToggle).toHaveBeenCalledTimes(1);
  });

  it('renders nav element for navigation', () => {
    const { container } = render(
      <W><AdminSidebar collapsed={false} onToggle={mockOnToggle} /></W>
    );
    expect(
      container.querySelector('nav') ?? container.querySelector('[role="navigation"]')
    ).toBeTruthy();
  });

  it('renders Dashboard section link', () => {
    render(
      <W><AdminSidebar collapsed={false} onToggle={mockOnToggle} /></W>
    );
    // Dashboard is translated via admin_nav namespace -> "Dashboard"
    expect(screen.getByText('Dashboard')).toBeTruthy();
  });

  it('groups Partner Timebanks into core network and protocol operations when federation is enabled', () => {
    mockHasFeature.mockImplementation((feature: string) =>
      feature === 'federation' || feature === 'partner_api',
    );

    const { container } = render(
      <W path="/test/admin/federation/api-docs"><AdminSidebar collapsed={false} onToggle={mockOnToggle} /></W>
    );

    const partnerSection = Array.from(container.querySelectorAll('li')).find((item) =>
      item.textContent?.includes('Partner Timebanks') &&
      item.textContent.includes('Core network') &&
      item.textContent.includes('Partner protocols') &&
      item.textContent.includes('Monitoring & data'),
    );
    const text = partnerSection?.textContent ?? '';

    expect(partnerSection).toBeTruthy();
    expect(text.indexOf('Federation Settings')).toBeLessThan(text.indexOf('Trust & settlement'));
    expect(text.indexOf('Trust & settlement')).toBeLessThan(text.indexOf('Partner protocols'));
    expect(text.indexOf('Partner protocols')).toBeLessThan(text.indexOf('Monitoring & data'));
    expect(screen.getByText('Partner APIs & integrations')).toBeTruthy();
    fireEvent.click(screen.getByRole('button', { name: 'Partner APIs & integrations' }));
    expect(screen.getByRole('link', { name: 'Inbound API Partners' })).toHaveAttribute('href', '/test/admin/api-partners');
  });

  it('keeps Broker Panel visible as a pinned admin link', () => {
    render(
      <W><AdminSidebar collapsed={false} onToggle={mockOnToggle} /></W>
    );
    expect(screen.getByText('Broker Panel')).toBeTruthy();
  });

  it('shows one Super Admin Panel link instead of the full super-admin navigation tree', () => {
    render(
      <W><AdminSidebar collapsed={false} onToggle={mockOnToggle} /></W>
    );

    const superAdminLinks = screen.getAllByRole('link', { name: 'Super Admin Panel' });
    expect(superAdminLinks).toHaveLength(1);
    expect(superAdminLinks[0]).toHaveAttribute('href', '/test/super-admin');
    expect(screen.queryByText('Super Dashboard')).not.toBeInTheDocument();
  });

  it('keeps Super Admin Panel under Overview after Broker Panel', () => {
    const { container } = render(
      <W><AdminSidebar collapsed={false} onToggle={mockOnToggle} /></W>
    );

    const overview = Array.from(container.querySelectorAll('li')).find((item) =>
      item.textContent?.includes('Overview') &&
      item.textContent.includes('Dashboard') &&
      item.textContent.includes('Broker Panel') &&
      item.textContent.includes('Super Admin Panel'),
    );
    const overviewText = overview?.textContent ?? '';

    expect(overview).toBeTruthy();
    expect(overviewText.indexOf('Dashboard')).toBeLessThan(overviewText.indexOf('Broker Panel'));
    expect(overviewText.indexOf('Broker Panel')).toBeLessThan(overviewText.indexOf('Super Admin Panel'));
  });

  it('pins only the Caring Community link in the footer', () => {
    const { container } = render(
      <W><AdminSidebar collapsed={false} onToggle={mockOnToggle} /></W>
    );

    const caringLinks = screen.getAllByRole('link', { name: /Caring Community/i });
    expect(caringLinks).toHaveLength(1);

    const footer = container.querySelector('aside > div:last-child');
    const footerLinks = Array.from(footer?.querySelectorAll('a') ?? []);
    expect(footerLinks).toHaveLength(1);
    expect(footerLinks[0]).toHaveTextContent('Caring Community');
    expect(footerLinks[0]).toHaveTextContent('Alpha');
    expect(footerLinks[0]).toHaveAttribute('href', '/test/caring');
    expect(footerLinks[0].className).toContain('border-warning/30');
    expect(footerLinks[0].className).toContain('bg-warning/10');
    expect(footer?.textContent).not.toContain('Help Centre');
  });

  it('shows the Super Admin Panel overview link for god users', () => {
    Object.assign(mockUser, {
      role: 'admin',
      is_super_admin: false,
      is_tenant_super_admin: false,
      is_god: true,
    });

    render(
      <W><AdminSidebar collapsed={false} onToggle={mockOnToggle} /></W>
    );

    expect(screen.getByRole('link', { name: 'Super Admin Panel' })).toHaveAttribute('href', '/test/super-admin');
  });

  it('applies w-16 class when collapsed', () => {
    const { container } = render(
      <W><AdminSidebar collapsed={true} onToggle={mockOnToggle} /></W>
    );
    const aside = container.querySelector('aside');
    expect(aside?.className).toContain('w-16');
  });

  it('applies w-64 class when not collapsed', () => {
    const { container } = render(
      <W><AdminSidebar collapsed={false} onToggle={mockOnToggle} /></W>
    );
    const aside = container.querySelector('aside');
    expect(aside?.className).toContain('w-64');
  });

  it('renders section labels when not collapsed', () => {
    render(
      <W><AdminSidebar collapsed={false} onToggle={mockOnToggle} /></W>
    );
    // Section labels translated via admin_nav namespace
    expect(screen.getByText('Users')).toBeTruthy();
    expect(screen.getByText('Content')).toBeTruthy();
    expect(screen.getByText('Platform Operations')).toBeTruthy();
  });
});
