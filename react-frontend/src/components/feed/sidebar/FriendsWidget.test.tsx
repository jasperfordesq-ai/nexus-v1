// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';

// ─── API mock ─────────────────────────────────────────────────────────────────
const { mockApi } = vi.hoisted(() => ({
  mockApi: {
    get: vi.fn(), post: vi.fn(), put: vi.fn(),
    patch: vi.fn(), delete: vi.fn(), download: vi.fn(), upload: vi.fn(),
  },
}));
vi.mock('@/lib/api', () => ({ api: mockApi, default: mockApi }));

// ─── Helpers mock (resolveAvatarUrl) ─────────────────────────────────────────
vi.mock('@/lib/helpers', async (importOriginal) => {
  const orig = await importOriginal<typeof import('@/lib/helpers')>();
  return {
    ...orig,
    resolveAvatarUrl: (url: string | undefined) => url || '',
  };
});

// ─── Contexts ─────────────────────────────────────────────────────────────────
vi.mock('@/contexts', () =>
  createMockContexts({
    useTenant: () => ({
      tenant: { id: 2, name: 'Test', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  })
);

// ─── Stub GlassCard + Avatar ─────────────────────────────────────────────────
vi.mock('@/components/ui', async (importOriginal) => {
  const orig = await importOriginal<typeof import('@/components/ui')>();
  return {
    ...orig,
    GlassCard: ({ children }: { children: React.ReactNode }) => (
      <div data-testid="glass-card">{children}</div>
    ),
    Avatar: ({ name, src }: { name: string; src?: string }) => (
      <div data-testid="avatar" data-name={name} data-src={src}>{name}</div>
    ),
  };
});

// ─── Fixtures ─────────────────────────────────────────────────────────────────
import type { Friend } from './FriendsWidget';

const makeFriend = (overrides: Partial<Friend> = {}): Friend => ({
  id: 1,
  name: 'Carol Brennan',
  avatar_url: undefined,
  location: undefined,
  is_online: false,
  is_recent: false,
  ...overrides,
});

// ─────────────────────────────────────────────────────────────────────────────
describe('FriendsWidget', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('renders nothing when friends array is empty', async () => {
    const { FriendsWidget } = await import('./FriendsWidget');
    const { container } = render(<FriendsWidget friends={[]} />);
    expect(container.querySelector('[data-testid="glass-card"]')).toBeNull();
  });

  it('renders the widget heading when friends are provided', async () => {
    const { FriendsWidget } = await import('./FriendsWidget');
    render(<FriendsWidget friends={[makeFriend()]} />);
    expect(screen.getByText('Friends')).toBeInTheDocument();
  });

  it('renders a "See All" link pointing to the tenant connections path', async () => {
    const { FriendsWidget } = await import('./FriendsWidget');
    render(<FriendsWidget friends={[makeFriend()]} />);
    const link = screen.getByRole('link', { name: /see all/i });
    expect(link).toHaveAttribute('href', '/test/connections');
  });

  it('renders the friend name', async () => {
    const { FriendsWidget } = await import('./FriendsWidget');
    render(<FriendsWidget friends={[makeFriend({ name: 'Dave Murphy' })]} />);
    // Name appears in both the link text (from Avatar stub) and the p tag
    expect(screen.getAllByText('Dave Murphy').length).toBeGreaterThan(0);
  });

  it('links each friend to their profile page', async () => {
    const { FriendsWidget } = await import('./FriendsWidget');
    render(<FriendsWidget friends={[makeFriend({ id: 7 }), makeFriend({ id: 12, name: 'Eve' })]} />);
    const links = screen.getAllByRole('link');
    const hrefs = links.map((l) => l.getAttribute('href'));
    expect(hrefs).toContain('/test/profile/7');
    expect(hrefs).toContain('/test/profile/12');
  });

  it('renders friend location when provided', async () => {
    const { FriendsWidget } = await import('./FriendsWidget');
    render(<FriendsWidget friends={[makeFriend({ location: 'Cork, Ireland' })]} />);
    expect(screen.getByText('Cork, Ireland')).toBeInTheDocument();
  });

  it('does not render location paragraph when location is absent', async () => {
    const { FriendsWidget } = await import('./FriendsWidget');
    render(<FriendsWidget friends={[makeFriend({ location: undefined })]} />);
    // No location text rendered — just assert friend name is there
    expect(screen.getAllByText('Carol Brennan').length).toBeGreaterThan(0);
    expect(screen.queryByText('Cork, Ireland')).toBeNull();
  });

  it('renders an online indicator with aria-label for online friends', async () => {
    const { FriendsWidget } = await import('./FriendsWidget');
    render(<FriendsWidget friends={[makeFriend({ is_online: true })]} />);
    const indicator = screen.getByLabelText(/online now/i);
    expect(indicator).toBeInTheDocument();
  });

  it('renders an active-today indicator for recently-active friends', async () => {
    const { FriendsWidget } = await import('./FriendsWidget');
    render(<FriendsWidget friends={[makeFriend({ is_recent: true, is_online: false })]} />);
    const indicator = screen.getByLabelText(/active today/i);
    expect(indicator).toBeInTheDocument();
  });

  it('does not render any status indicator for offline, non-recent friends', async () => {
    const { FriendsWidget } = await import('./FriendsWidget');
    render(<FriendsWidget friends={[makeFriend({ is_online: false, is_recent: false })]} />);
    expect(screen.queryByLabelText(/online now/i)).toBeNull();
    expect(screen.queryByLabelText(/active today/i)).toBeNull();
  });

  it('renders multiple friends', async () => {
    const friends = [
      makeFriend({ id: 1, name: 'Alice' }),
      makeFriend({ id: 2, name: 'Bob' }),
      makeFriend({ id: 3, name: 'Carol' }),
    ];
    const { FriendsWidget } = await import('./FriendsWidget');
    render(<FriendsWidget friends={friends} />);
    // Each name appears in Avatar stub + p tag, so check at least one occurrence
    expect(screen.getAllByText('Alice').length).toBeGreaterThan(0);
    expect(screen.getAllByText('Bob').length).toBeGreaterThan(0);
    expect(screen.getAllByText('Carol').length).toBeGreaterThan(0);
  });

  it('passes avatar src to the Avatar component', async () => {
    const { FriendsWidget } = await import('./FriendsWidget');
    render(<FriendsWidget friends={[makeFriend({ avatar_url: 'https://example.com/avatar.jpg' })]} />);
    const avatar = screen.getByTestId('avatar');
    expect(avatar).toHaveAttribute('data-src', 'https://example.com/avatar.jpg');
  });
});
