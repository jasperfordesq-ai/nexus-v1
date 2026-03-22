// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for AdminLayout — admin shell with sidebar + header + content area
 */

import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { MemoryRouter, Routes, Route } from 'react-router-dom';

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

vi.mock('framer-motion');

import { AdminLayout } from '../AdminLayout';

function renderLayout() {
  return render(
    <MemoryRouter initialEntries={['/test/admin']}>
      <Routes>
        <Route element={<AdminLayout />}>
          <Route path="/test/admin" element={<div data-testid="outlet-content">Outlet Content</div>} />
        </Route>
      </Routes>
    </MemoryRouter>
  );
}

describe('AdminLayout', () => {
  it('renders without crashing', () => {
    renderLayout();
    expect(document.body).toBeTruthy();
  });

  it('renders the sidebar with Admin text', () => {
    renderLayout();
    expect(screen.getByText('Admin')).toBeInTheDocument();
  });

  it('renders the header with Back to site button', () => {
    renderLayout();
    expect(screen.getByText('Back to site')).toBeInTheDocument();
  });

  it('renders the outlet content', () => {
    renderLayout();
    expect(screen.getByTestId('outlet-content')).toBeInTheDocument();
  });

  it('shows collapse/expand sidebar button', () => {
    renderLayout();
    const btn = screen.getByLabelText(/collapse sidebar|expand sidebar/i);
    expect(btn).toBeInTheDocument();
  });

  it('toggles sidebar on button click', () => {
    renderLayout();
    const btn = screen.getByLabelText(/collapse sidebar/i);
    fireEvent.click(btn);
    // After clicking, label should change to "Expand sidebar"
    expect(screen.getByLabelText(/expand sidebar/i)).toBeInTheDocument();
  });

  it('renders tenant name in header', () => {
    renderLayout();
    expect(screen.getByText('Test Community')).toBeInTheDocument();
  });
});
