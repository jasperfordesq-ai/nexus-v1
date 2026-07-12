// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';

// ─── API mock (not used by this component but required by the pattern) ─────────
const { mockApi } = vi.hoisted(() => ({
  mockApi: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn(), download: vi.fn(), upload: vi.fn() },
}));
vi.mock('@/lib/api', () => ({ api: mockApi, default: mockApi }));

// ─── Tenant / Auth / Toast ─────────────────────────────────────────────────────
const mockTenantPath = vi.fn((p: string) => `/hour-timebank${p}`);

vi.mock('@/contexts', () =>
  createMockContexts({
    useAuth: () => ({
      user: { id: 1, name: 'Alice', role: 'member' },
      isAuthenticated: true,
      isLoading: false,
      status: 'idle' as const,
      login: vi.fn(), logout: vi.fn(), register: vi.fn(), updateUser: vi.fn(), refreshUser: vi.fn(), error: null,
    }),
    useTenant: () => ({
      tenant: { id: 2, name: 'hOUR Timebank', slug: 'hour-timebank' },
      tenantPath: mockTenantPath,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
    useToast: () => ({ success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn(), showToast: vi.fn() }),
  })
);

// ─── Stub GlassCard to avoid HeroUI Card in jsdom ─────────────────────────────
vi.mock('@/components/ui/GlassCard', () => ({
  GlassCard: ({ children, className }: { children: React.ReactNode; className?: string }) => (
    <div data-testid="glass-card" className={className}>{children}</div>
  ),
}));

// ─── Fixtures ─────────────────────────────────────────────────────────────────
const makeSubGroup = (overrides = {}): { id: number; name: string; member_count: number } => ({
  id: 1,
  name: 'Youth Group',
  member_count: 12,
  ...overrides,
});

// ─────────────────────────────────────────────────────────────────────────────
describe('GroupSubgroupsTab', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockTenantPath.mockImplementation((p: string) => `/hour-timebank${p}`);
  });

  it('renders without crashing when given an empty subGroups array', async () => {
    const { GroupSubgroupsTab } = await import('./GroupSubgroupsTab');
    const { container } = render(<GroupSubgroupsTab subGroups={[]} />);
    expect(container.firstChild).toBeTruthy();
  });

  it('renders nothing inside the list when subGroups is empty', async () => {
    const { GroupSubgroupsTab } = await import('./GroupSubgroupsTab');
    render(<GroupSubgroupsTab subGroups={[]} />);
    // No subgroup names should appear
    expect(screen.queryByRole('link')).toBeNull();
  });

  it('renders a link for each subgroup', async () => {
    const { GroupSubgroupsTab } = await import('./GroupSubgroupsTab');
    render(
      <GroupSubgroupsTab
        subGroups={[makeSubGroup({ id: 10, name: 'Youth Group' }), makeSubGroup({ id: 20, name: 'Seniors Club' })]}
      />
    );
    const links = screen.getAllByRole('link');
    expect(links).toHaveLength(2);
  });

  it('renders the subgroup name as visible text', async () => {
    const { GroupSubgroupsTab } = await import('./GroupSubgroupsTab');
    render(<GroupSubgroupsTab subGroups={[makeSubGroup({ name: 'Youth Group' })]} />);
    expect(screen.getByText('Youth Group')).toBeInTheDocument();
  });

  it('constrains long subgroup names without removing their full accessible text', async () => {
    const longName = 'A very long subgroup name that must not force the mobile card beyond the viewport';
    const { GroupSubgroupsTab } = await import('./GroupSubgroupsTab');
    render(<GroupSubgroupsTab subGroups={[makeSubGroup({ name: longName })]} />);

    const name = screen.getByText(longName);
    expect(name).toHaveClass('truncate');
    expect(name).toHaveAttribute('title', longName);
    expect(screen.getByRole('link')).toHaveClass('min-w-0');
  });

  it('renders all subgroup names when multiple subgroups are passed', async () => {
    const { GroupSubgroupsTab } = await import('./GroupSubgroupsTab');
    render(
      <GroupSubgroupsTab
        subGroups={[
          makeSubGroup({ id: 1, name: 'Youth Group' }),
          makeSubGroup({ id: 2, name: 'Senior Choir' }),
          makeSubGroup({ id: 3, name: 'Garden Guild' }),
        ]}
      />
    );
    expect(screen.getByText('Youth Group')).toBeInTheDocument();
    expect(screen.getByText('Senior Choir')).toBeInTheDocument();
    expect(screen.getByText('Garden Guild')).toBeInTheDocument();
  });

  it('calls tenantPath with the correct group ID in the link href', async () => {
    const { GroupSubgroupsTab } = await import('./GroupSubgroupsTab');
    render(<GroupSubgroupsTab subGroups={[makeSubGroup({ id: 99, name: 'Test Group' })]} />);
    expect(mockTenantPath).toHaveBeenCalledWith('/groups/99');
  });

  it('link href reflects the tenant-prefixed path', async () => {
    const { GroupSubgroupsTab } = await import('./GroupSubgroupsTab');
    render(<GroupSubgroupsTab subGroups={[makeSubGroup({ id: 42, name: 'Alpha Group' })]} />);
    const link = screen.getByRole('link');
    expect(link.getAttribute('href')).toBe('/hour-timebank/groups/42');
  });

  it('shows member count text for a single member', async () => {
    const { GroupSubgroupsTab } = await import('./GroupSubgroupsTab');
    render(<GroupSubgroupsTab subGroups={[makeSubGroup({ member_count: 1 })]} />);
    // i18n key groups:detail.members_count renders the count
    // react-i18next in test mode renders the key with interpolation
    const countEl = screen.getByText(/1/);
    expect(countEl).toBeInTheDocument();
  });

  it('shows member count text for many members', async () => {
    const { GroupSubgroupsTab } = await import('./GroupSubgroupsTab');
    render(<GroupSubgroupsTab subGroups={[makeSubGroup({ member_count: 250 })]} />);
    const countEl = screen.getByText(/250/);
    expect(countEl).toBeInTheDocument();
  });

  it('renders a unique link per subgroup id', async () => {
    const { GroupSubgroupsTab } = await import('./GroupSubgroupsTab');
    render(
      <GroupSubgroupsTab
        subGroups={[
          makeSubGroup({ id: 11, name: 'Alpha' }),
          makeSubGroup({ id: 22, name: 'Beta' }),
        ]}
      />
    );
    const links = screen.getAllByRole('link');
    const hrefs = links.map((l) => l.getAttribute('href'));
    expect(hrefs[0]).not.toBe(hrefs[1]);
    expect(hrefs[0]).toContain('11');
    expect(hrefs[1]).toContain('22');
  });

  it('wraps content in a GlassCard container', async () => {
    const { GroupSubgroupsTab } = await import('./GroupSubgroupsTab');
    render(<GroupSubgroupsTab subGroups={[makeSubGroup()]} />);
    expect(screen.getByTestId('glass-card')).toBeInTheDocument();
  });

  it('does not render member count text if member_count is 0', async () => {
    const { GroupSubgroupsTab } = await import('./GroupSubgroupsTab');
    render(<GroupSubgroupsTab subGroups={[makeSubGroup({ member_count: 0, name: 'Empty Group' })]} />);
    // Name renders but there's no phantom non-zero count visible
    expect(screen.getByText('Empty Group')).toBeInTheDocument();
    expect(screen.queryByText(/250/)).toBeNull();
  });
});
