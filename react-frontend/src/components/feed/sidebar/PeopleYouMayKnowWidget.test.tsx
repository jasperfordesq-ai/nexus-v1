// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import React from 'react';
import { render, screen } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

// ─── API mock (not used directly by this component, but required by the module graph) ──
const { mockApi } = vi.hoisted(() => ({
  mockApi: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
    download: vi.fn(),
    upload: vi.fn(),
  },
}));

vi.mock('@/lib/api', () => ({ api: mockApi, default: mockApi }));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

// ─── Contexts ────────────────────────────────────────────────────────────────
const mockTenantPath = vi.fn((p: string) => `/test${p}`);

vi.mock('@/contexts', () =>
  createMockContexts({
    useAuth: () => ({
      user: { id: 1, name: 'Alice' },
      isAuthenticated: true,
      login: vi.fn(),
      logout: vi.fn(),
      register: vi.fn(),
      updateUser: vi.fn(),
      refreshUser: vi.fn(),
      status: 'idle' as const,
      error: null,
    }),
    useTenant: () => ({
      tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
      tenantPath: mockTenantPath,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  })
);

// ─── Stub heavy UI children ──────────────────────────────────────────────────
vi.mock('@/components/ui', async (importOriginal) => {
  const orig = await importOriginal<typeof import('@/components/ui')>();
  return {
    ...orig,
    GlassCard: ({ children, className }: { children: React.ReactNode; className?: string }) => (
      <div data-testid="glass-card" className={className}>{children}</div>
    ),
    Avatar: ({ name, src }: { name: string; src?: string }) => (
      <div data-testid="avatar" aria-label={name}>{src ? null : name[0]}</div>
    ),
    Button: ({ children, to, as: _as, ...rest }: { children: React.ReactNode; to?: string; as?: React.ElementType; [key: string]: unknown }) => (
      <a href={to as string | undefined} role="link" {...rest as object}>{children}</a>
    ),
  };
});

// ─── Fixtures ────────────────────────────────────────────────────────────────
const makeMember = (overrides = {}) => ({
  id: 10,
  name: 'Bob Builder',
  avatar_url: undefined,
  location: 'Dublin',
  is_online: false,
  ...overrides,
});

// ─────────────────────────────────────────────────────────────────────────────
describe('PeopleYouMayKnowWidget', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('renders nothing when members array is empty', async () => {
    const { PeopleYouMayKnowWidget } = await import('./PeopleYouMayKnowWidget');
    render(<PeopleYouMayKnowWidget members={[]} />);
    // The component returns null — no GlassCard is mounted
    expect(screen.queryByTestId('glass-card')).not.toBeInTheDocument();
    expect(screen.queryByText('People You May Know')).not.toBeInTheDocument();
  });

  it('renders the widget heading when members are provided', async () => {
    const { PeopleYouMayKnowWidget } = await import('./PeopleYouMayKnowWidget');
    render(<PeopleYouMayKnowWidget members={[makeMember()]} />);
    expect(screen.getByText('People You May Know')).toBeInTheDocument();
  });

  it('renders a "See All" link pointing to the members page', async () => {
    const { PeopleYouMayKnowWidget } = await import('./PeopleYouMayKnowWidget');
    render(<PeopleYouMayKnowWidget members={[makeMember()]} />);
    const seeAll = screen.getByText('See All');
    expect(seeAll.closest('a')).toHaveAttribute('href', '/test/members');
  });

  it('renders a row for each member', async () => {
    const { PeopleYouMayKnowWidget } = await import('./PeopleYouMayKnowWidget');
    const members = [
      makeMember({ id: 1, name: 'Alice' }),
      makeMember({ id: 2, name: 'Charlie' }),
    ];
    render(<PeopleYouMayKnowWidget members={members} />);
    expect(screen.getByText('Alice')).toBeInTheDocument();
    expect(screen.getByText('Charlie')).toBeInTheDocument();
  });

  it('renders an avatar for each member', async () => {
    const { PeopleYouMayKnowWidget } = await import('./PeopleYouMayKnowWidget');
    render(<PeopleYouMayKnowWidget members={[makeMember({ name: 'Dana' })]} />);
    const avatars = screen.getAllByTestId('avatar');
    expect(avatars.length).toBeGreaterThanOrEqual(1);
    expect(avatars[0]).toHaveAttribute('aria-label', 'Dana');
  });

  it('shows the member location when provided', async () => {
    const { PeopleYouMayKnowWidget } = await import('./PeopleYouMayKnowWidget');
    render(<PeopleYouMayKnowWidget members={[makeMember({ location: 'Cork' })]} />);
    expect(screen.getByText('Cork')).toBeInTheDocument();
  });

  it('does not show a location paragraph when location is absent', async () => {
    const { PeopleYouMayKnowWidget } = await import('./PeopleYouMayKnowWidget');
    render(<PeopleYouMayKnowWidget members={[makeMember({ location: undefined })]} />);
    // Only the "People You May Know" heading + "See All" + "View" text — no location text
    expect(screen.queryByText('Dublin')).not.toBeInTheDocument();
  });

  it('renders an online indicator for online members', async () => {
    const { PeopleYouMayKnowWidget } = await import('./PeopleYouMayKnowWidget');
    render(<PeopleYouMayKnowWidget members={[makeMember({ is_online: true })]} />);
    // The online dot has aria-label "Online now"
    expect(screen.getByLabelText('Online now')).toBeInTheDocument();
  });

  it('does not render online indicator for offline members', async () => {
    const { PeopleYouMayKnowWidget } = await import('./PeopleYouMayKnowWidget');
    render(<PeopleYouMayKnowWidget members={[makeMember({ is_online: false })]} />);
    expect(screen.queryByLabelText('Online now')).not.toBeInTheDocument();
  });

  it('renders a View link pointing to the member profile', async () => {
    const { PeopleYouMayKnowWidget } = await import('./PeopleYouMayKnowWidget');
    render(<PeopleYouMayKnowWidget members={[makeMember({ id: 42 })]} />);
    // Multiple links point to /profile/42 (avatar link + name link + View button)
    const links = screen.getAllByRole('link');
    const profileLinks = links.filter((el) =>
      el.getAttribute('href')?.includes('/profile/42') ||
      el.getAttribute('to')?.includes('/profile/42')
    );
    expect(profileLinks.length).toBeGreaterThanOrEqual(1);
    expect(screen.getByText('View')).toBeInTheDocument();
  });

  it('renders multiple members from a list of three', async () => {
    const { PeopleYouMayKnowWidget } = await import('./PeopleYouMayKnowWidget');
    const members = [
      makeMember({ id: 1, name: 'Alice' }),
      makeMember({ id: 2, name: 'Bob' }),
      makeMember({ id: 3, name: 'Carol' }),
    ];
    render(<PeopleYouMayKnowWidget members={members} />);
    expect(screen.getByText('Alice')).toBeInTheDocument();
    expect(screen.getByText('Bob')).toBeInTheDocument();
    expect(screen.getByText('Carol')).toBeInTheDocument();
  });

  it('wraps content in the GlassCard', async () => {
    const { PeopleYouMayKnowWidget } = await import('./PeopleYouMayKnowWidget');
    render(<PeopleYouMayKnowWidget members={[makeMember()]} />);
    expect(screen.getByTestId('glass-card')).toBeInTheDocument();
  });
});
