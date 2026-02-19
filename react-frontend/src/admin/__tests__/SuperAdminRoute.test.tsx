// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for SuperAdminRoute — route guard for super admin access
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MemoryRouter, Routes, Route } from 'react-router-dom';

vi.mock('@/components/feedback', () => ({
  LoadingScreen: ({ message }: { message?: string }) => <div data-testid="loading">{message}</div>,
}));

const mockUseAuth = vi.fn();
const mockUseTenant = vi.fn();

vi.mock('@/contexts', () => ({
  useAuth: (...args: any[]) => mockUseAuth(...args),
  useTenant: (...args: any[]) => mockUseTenant(...args),
}));

import { SuperAdminRoute } from '../SuperAdminRoute';

function renderWithRouter(initialEntry = '/super') {
  return render(
    <MemoryRouter initialEntries={[initialEntry]}>
      <Routes>
        <Route element={<SuperAdminRoute />}>
          <Route path="/super" element={<div data-testid="super-content">Super Content</div>} />
        </Route>
        <Route path="/test/admin" element={<div data-testid="admin-redirect">Admin</div>} />
      </Routes>
    </MemoryRouter>
  );
}

describe('SuperAdminRoute', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockUseTenant.mockReturnValue({
      tenantPath: (p: string) => `/test${p}`,
    });
  });

  it('shows loading screen when auth is loading', () => {
    mockUseAuth.mockReturnValue({
      user: null,
      isLoading: true,
      status: 'loading',
    });

    renderWithRouter();
    expect(screen.getByText('Checking permissions...')).toBeInTheDocument();
  });

  it('redirects non-super-admin to admin dashboard', () => {
    mockUseAuth.mockReturnValue({
      user: { id: 1, role: 'admin' },
      isLoading: false,
      status: 'authenticated',
    });

    renderWithRouter();
    expect(screen.queryByTestId('super-content')).not.toBeInTheDocument();
  });

  it('allows super_admin role through', () => {
    mockUseAuth.mockReturnValue({
      user: { id: 1, role: 'super_admin' },
      isLoading: false,
      status: 'authenticated',
    });

    renderWithRouter();
    expect(screen.getByTestId('super-content')).toBeInTheDocument();
  });

  it('allows users with is_super_admin flag through', () => {
    mockUseAuth.mockReturnValue({
      user: { id: 1, role: 'admin', is_super_admin: true },
      isLoading: false,
      status: 'authenticated',
    });

    renderWithRouter();
    expect(screen.getByTestId('super-content')).toBeInTheDocument();
  });

  it('blocks regular admin users', () => {
    mockUseAuth.mockReturnValue({
      user: { id: 1, role: 'admin', is_super_admin: false },
      isLoading: false,
      status: 'authenticated',
    });

    renderWithRouter();
    expect(screen.queryByTestId('super-content')).not.toBeInTheDocument();
  });
});
