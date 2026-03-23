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
import { HeroUIProvider } from '@heroui/react';

// ─── Stable mock references ─────────────────────────────────────────────────

const mockTenantPath = (p: string) => `/test${p}`;
const mockOnToggle = vi.fn();

vi.mock('@/contexts', () => ({
  useAuth: vi.fn(() => ({
    user: { id: 1, name: 'Admin User', role: 'admin', is_super_admin: false },
    isAuthenticated: true,
    logout: vi.fn(),
  })),
  useTenant: vi.fn(() => ({
    tenant: { id: 2, name: 'Test Community', slug: 'test', configuration: {} },
    tenantSlug: 'test',
    branding: { name: 'Test Community' },
    hasFeature: vi.fn(() => false),
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
    <HeroUIProvider>
      <MemoryRouter initialEntries={[path]}>
        {children}
      </MemoryRouter>
    </HeroUIProvider>
  );
}

// ─── Tests ───────────────────────────────────────────────────────────────────

describe('AdminSidebar', () => {
  beforeEach(() => {
    vi.clearAllMocks();
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
    expect(container.querySelector('nav')).toBeTruthy();
  });

  it('renders Dashboard section link', () => {
    render(
      <W><AdminSidebar collapsed={false} onToggle={mockOnToggle} /></W>
    );
    // Dashboard is translated via admin_nav namespace -> "Dashboard"
    expect(screen.getByText('Dashboard')).toBeTruthy();
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
    expect(screen.getByText('System')).toBeTruthy();
  });
});
