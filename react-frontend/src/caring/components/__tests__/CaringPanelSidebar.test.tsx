// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const mockTenantPath = (path: string) => `/test${path}`;
const mockUser: Record<string, unknown> = {
  id: 1,
  role: 'admin',
  is_super_admin: true,
};

vi.mock('@/contexts', () => ({
  useAuth: vi.fn(() => ({
    user: mockUser,
    isAuthenticated: true,
  })),
  useTenant: vi.fn(() => ({
    tenant: { id: 2, name: 'Test Community', slug: 'test' },
    tenantPath: mockTenantPath,
  })),
}));

import { CaringPanelSidebar } from '../CaringPanelSidebar';

function Wrapper({ children, path = '/test/caring' }: { children: React.ReactNode; path?: string }) {
  return (
    <MemoryRouter initialEntries={[path]}>
      {children}
    </MemoryRouter>
  );
}

describe('CaringPanelSidebar', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    Object.assign(mockUser, {
      id: 1,
      role: 'admin',
      is_super_admin: true,
      is_tenant_super_admin: undefined,
      is_god: undefined,
    });
  });

  it('keeps partner timebank links in the partnerships section rather than routine operations', () => {
    const { container } = render(
      <Wrapper path="/test/caring/federation-peers">
        <CaringPanelSidebar collapsed={false} onToggle={vi.fn()} />
      </Wrapper>,
    );

    const operationsHeading = screen.getByText('Operations');
    const partnershipsHeading = screen.getByText('Partner Timebanks & integrations');
    const operationsBlock = operationsHeading.closest('div')?.textContent ?? '';
    const partnershipsBlock = partnershipsHeading.closest('div')?.textContent ?? '';

    expect(operationsBlock).not.toContain('Partner Cooperatives');
    expect(partnershipsBlock).toContain('Partner Cooperatives');
    expect(partnershipsBlock).toContain('Partner Integration Tracker');
    expect(partnershipsBlock).toContain('Developer Integration Reference');

    const navText = container.querySelector('nav')?.textContent ?? '';
    expect(navText.indexOf('Partner Timebanks & integrations')).toBeLessThan(navText.indexOf('Reporting'));
  });
});
