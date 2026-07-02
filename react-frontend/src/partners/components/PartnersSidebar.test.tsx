// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

// ─── Mutable tenant state (read by the factory each render) ──────────────────
const { mockTenant } = vi.hoisted(() => ({
  mockTenant: {
    tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
    tenantPath: (p: string) => `/test${p}`,
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
  },
}));

vi.mock('@/contexts', () =>
  createMockContexts({
    useAuth: () => ({
      user: { id: 1, role: 'super_admin' },
      isAuthenticated: true,
      login: vi.fn(),
      logout: vi.fn(),
      register: vi.fn(),
      updateUser: vi.fn(),
      refreshUser: vi.fn(),
      status: 'idle' as const,
      error: null,
    }),
    useTenant: () => ({ ...mockTenant }),
  })
);

import { PartnersSidebar } from './PartnersSidebar';

const mockOnToggle = vi.fn();

describe('PartnersSidebar', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockTenant.hasFeature = vi.fn(() => true);
  });

  it('renders the sidebar navigation landmark', () => {
    render(<PartnersSidebar collapsed={false} onToggle={mockOnToggle} />);
    // i18n: partners.sidebar.nav_label → "Partner Timebanks sections"
    expect(screen.getByRole('navigation', { name: /Partner Timebanks sections/i })).toBeInTheDocument();
  });

  it('renders all sections when every feature is enabled', () => {
    render(<PartnersSidebar collapsed={false} onToggle={mockOnToggle} />);
    expect(screen.getByText('Partner network')).toBeInTheDocument();
    expect(screen.getByText('External connections')).toBeInTheDocument();
    expect(screen.getByText('Caring Community')).toBeInTheDocument();
    expect(screen.getByText('Access & security')).toBeInTheDocument();
    expect(screen.getByText('Activity & data')).toBeInTheDocument();
  });

  it('hides the Caring Community section when the module is off', () => {
    mockTenant.hasFeature = vi.fn((f: string) => f !== 'caring_community');
    render(<PartnersSidebar collapsed={false} onToggle={mockOnToggle} />);
    expect(screen.queryByText('Caring Community')).not.toBeInTheDocument();
    expect(screen.queryByRole('link', { name: /Partner cooperatives/i })).not.toBeInTheDocument();
    // The rest of the panel is unaffected
    expect(screen.getByText('Partner network')).toBeInTheDocument();
  });

  it('hides the Inbound API partners item without the partner_api feature', () => {
    mockTenant.hasFeature = vi.fn((f: string) => f !== 'partner_api');
    render(<PartnersSidebar collapsed={false} onToggle={mockOnToggle} />);
    expect(screen.queryByRole('link', { name: /Inbound API partners/i })).not.toBeInTheDocument();
    // Other external-connection items stay
    expect(screen.getByRole('link', { name: /External platforms/i })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /Credit Commons/i })).toBeInTheDocument();
  });

  it('hides all federation sections when only partner_api is enabled', () => {
    mockTenant.hasFeature = vi.fn((f: string) => f === 'partner_api');
    render(<PartnersSidebar collapsed={false} onToggle={mockOnToggle} />);
    expect(screen.queryByText('Partner network')).not.toBeInTheDocument();
    expect(screen.queryByText('Access & security')).not.toBeInTheDocument();
    // External connections survives with just the inbound item
    expect(screen.getByText('External connections')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /Inbound API partners/i })).toBeInTheDocument();
    expect(screen.queryByRole('link', { name: /Credit Commons/i })).not.toBeInTheDocument();
  });

  it('renders the Full Admin Panel footer link', () => {
    render(<PartnersSidebar collapsed={false} onToggle={mockOnToggle} />);
    const link = screen.getByRole('link', { name: /Full Admin Panel/i });
    expect(link).toHaveAttribute('href', '/test/admin');
  });

  it('links nav items through tenantPath', () => {
    render(<PartnersSidebar collapsed={false} onToggle={mockOnToggle} />);
    expect(screen.getByRole('link', { name: /Partnerships/i })).toHaveAttribute(
      'href',
      '/test/partner-timebanks/partnerships'
    );
  });
});
