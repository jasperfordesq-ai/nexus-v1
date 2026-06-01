// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MemoryRouter, Outlet, Route, Routes } from 'react-router-dom';

vi.mock('@/components/feedback', () => ({
  LoadingScreen: () => <div>Loading</div>,
}));

vi.mock('@/contexts', () => ({
  useTenant: vi.fn(() => ({
    tenantPath: (path: string) => `/test${path}`,
  })),
}));

vi.mock('@/admin/modules/super/SuperDashboard', () => ({
  default: () => <div data-testid="super-dashboard">Super Dashboard</div>,
}));

vi.mock('@/admin/modules/super/FederationTenantFeatures', () => ({
  default: () => <div data-testid="tenant-features">Tenant Features</div>,
}));

import { SuperAdminRoutes } from '../SuperAdminRoutes';

function renderRoutes(initialEntry: string) {
  return render(
    <MemoryRouter initialEntries={[initialEntry]}>
      <Routes>
        <Route path="/test/super-admin/*" element={<Outlet />}>
          {SuperAdminRoutes()}
        </Route>
      </Routes>
    </MemoryRouter>,
  );
}

describe('SuperAdminRoutes', () => {
  it('renders the dedicated super-admin dashboard at the canonical root', async () => {
    renderRoutes('/test/super-admin');

    expect(await screen.findByTestId('super-dashboard')).toBeInTheDocument();
  });

  it('keeps tenant feature deep links in the super-admin area', async () => {
    renderRoutes('/test/super-admin/tenants/12/features');

    expect(await screen.findByTestId('tenant-features')).toBeInTheDocument();
  });
});
