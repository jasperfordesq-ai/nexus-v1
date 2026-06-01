// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';

vi.mock('@/contexts', () => ({
  useTenant: vi.fn(() => ({
    tenantPath: (path: string) => `/test${path}`,
  })),
}));

import { SuperAdminSidebar } from '../components/SuperAdminSidebar';

describe('SuperAdminSidebar', () => {
  it('renders dedicated super-admin navigation with a back link to the main admin panel', () => {
    render(
      <MemoryRouter initialEntries={['/test/super-admin/tenants']}>
        <SuperAdminSidebar collapsed={false} onToggle={vi.fn()} />
      </MemoryRouter>,
    );

    expect(screen.getByRole('link', { name: 'Super Admin' })).toHaveAttribute('href', '/test/super-admin');
    expect(screen.getByRole('link', { name: 'Tenants' })).toHaveAttribute('href', '/test/super-admin/tenants');
    expect(screen.getByRole('link', { name: 'Back to Platform Admin' })).toHaveAttribute('href', '/test/admin');
  });
});
