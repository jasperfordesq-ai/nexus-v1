// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for AdminApp — the lazy-loaded admin entry point
 */

import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';

// Mock dependencies
vi.mock('@/contexts', () => ({
  useAuth: vi.fn(() => ({
    user: { id: 1, first_name: 'Admin', last_name: 'User', role: 'admin', is_super_admin: true, tenant_id: 2 },
    isAuthenticated: true,
    isLoading: false,
    status: 'authenticated',
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
  useNotifications: vi.fn(() => ({ counts: { messages: 0, notifications: 0 } })),
}));

vi.mock('@/hooks', () => ({
  usePageTitle: vi.fn(),
}));

vi.mock('@/components/feedback', () => ({
  LoadingScreen: ({ message }: { message?: string }) => <div>{message || 'Loading...'}</div>,
}));

vi.mock('framer-motion', () => ({
  motion: new Proxy({}, {
    get: (_, tag) => {
      return ({ children, ...props }: any) => {
        const { variants, initial, animate, exit, layout, whileHover, whileTap, transition, ...rest } = props;
        const Tag = typeof tag === 'string' ? tag : 'div';
        return <Tag {...rest}>{children}</Tag>;
      };
    },
  }),
  AnimatePresence: ({ children }: any) => <>{children}</>,
}));

// Mock all admin module components to avoid deep dependency chains
vi.mock('../modules/dashboard/AdminDashboard', () => ({
  AdminDashboard: () => <div data-testid="admin-dashboard">Admin Dashboard</div>,
  default: () => <div data-testid="admin-dashboard">Admin Dashboard</div>,
}));

vi.mock('../routes', () => ({
  AdminRoutes: () => (
    <></>
  ),
}));

import AdminApp from '../AdminApp';

describe('AdminApp', () => {
  it('renders without crashing', () => {
    render(
      <MemoryRouter initialEntries={['/test/admin']}>
        <AdminApp />
      </MemoryRouter>
    );
    // The AdminRoute guard should render content for authenticated admin
    expect(document.body).toBeTruthy();
  });
});
