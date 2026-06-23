// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';

// ─── API mock (required by test-utils chain; widget doesn't call API directly) ─
const { mockApi } = vi.hoisted(() => ({
  mockApi: {
    get: vi.fn(), post: vi.fn(), put: vi.fn(),
    patch: vi.fn(), delete: vi.fn(), download: vi.fn(), upload: vi.fn(),
  },
}));
vi.mock('@/lib/api', () => ({ api: mockApi, default: mockApi }));

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

// ─── Stub GlassCard + Chip from @/components/ui (avoid HeroUI jsdom issues) ──
vi.mock('@/components/ui', async (importOriginal) => {
  const orig = await importOriginal<typeof import('@/components/ui')>();
  return {
    ...orig,
    GlassCard: ({ children, className }: { children: React.ReactNode; className?: string }) => (
      <div data-testid="glass-card" className={className}>{children}</div>
    ),
    Chip: ({ children, className }: { children: React.ReactNode; className?: string }) => (
      <span data-testid="chip" className={className}>{children}</span>
    ),
  };
});

// ─── Fixtures ─────────────────────────────────────────────────────────────────
import type { SuggestedListing } from './SuggestedListingsWidget';

const makeOffer = (overrides: Partial<SuggestedListing> = {}): SuggestedListing => ({
  id: 1,
  title: 'Guitar Lessons',
  type: 'offer',
  owner_name: 'Alice Smith',
  ...overrides,
});

const makeRequest = (overrides: Partial<SuggestedListing> = {}): SuggestedListing => ({
  id: 2,
  title: 'Need a Plumber',
  type: 'request',
  owner_name: 'Bob Jones',
  ...overrides,
});

// ─────────────────────────────────────────────────────────────────────────────
describe('SuggestedListingsWidget', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('renders nothing when listings array is empty', async () => {
    const { SuggestedListingsWidget } = await import('./SuggestedListingsWidget');
    const { container } = render(<SuggestedListingsWidget listings={[]} />);
    // GlassCard stub not rendered → only the ToastProvider wrapper div present
    expect(container.querySelector('[data-testid="glass-card"]')).toBeNull();
  });

  it('renders the widget heading when listings are provided', async () => {
    const { SuggestedListingsWidget } = await import('./SuggestedListingsWidget');
    render(<SuggestedListingsWidget listings={[makeOffer()]} />);
    expect(screen.getByText('Suggested For You')).toBeInTheDocument();
  });

  it('renders a "See All" link pointing to the tenant listings path', async () => {
    const { SuggestedListingsWidget } = await import('./SuggestedListingsWidget');
    render(<SuggestedListingsWidget listings={[makeOffer()]} />);
    const link = screen.getByRole('link', { name: /see all/i });
    expect(link).toHaveAttribute('href', '/test/listings');
  });

  it('renders each listing title', async () => {
    const { SuggestedListingsWidget } = await import('./SuggestedListingsWidget');
    render(<SuggestedListingsWidget listings={[makeOffer(), makeRequest()]} />);
    expect(screen.getByText('Guitar Lessons')).toBeInTheDocument();
    expect(screen.getByText('Need a Plumber')).toBeInTheDocument();
  });

  it('renders owner name via by_owner translation', async () => {
    const { SuggestedListingsWidget } = await import('./SuggestedListingsWidget');
    render(<SuggestedListingsWidget listings={[makeOffer()]} />);
    expect(screen.getByText(/Alice Smith/)).toBeInTheDocument();
  });

  it('links each listing to its tenant-scoped detail page', async () => {
    const { SuggestedListingsWidget } = await import('./SuggestedListingsWidget');
    render(<SuggestedListingsWidget listings={[makeOffer({ id: 42 }), makeRequest({ id: 99 })]} />);
    const links = screen.getAllByRole('link');
    const hrefs = links.map((l) => l.getAttribute('href'));
    expect(hrefs).toContain('/test/listings/42');
    expect(hrefs).toContain('/test/listings/99');
  });

  it('shows an Offer chip for offer-type listings', async () => {
    const { SuggestedListingsWidget } = await import('./SuggestedListingsWidget');
    render(<SuggestedListingsWidget listings={[makeOffer()]} />);
    const chips = screen.getAllByTestId('chip');
    expect(chips.some((c) => c.textContent === 'Offer')).toBe(true);
  });

  it('shows a Request chip for request-type listings', async () => {
    const { SuggestedListingsWidget } = await import('./SuggestedListingsWidget');
    render(<SuggestedListingsWidget listings={[makeRequest()]} />);
    const chips = screen.getAllByTestId('chip');
    expect(chips.some((c) => c.textContent === 'Request')).toBe(true);
  });

  it('renders multiple listings correctly', async () => {
    const listings = [makeOffer({ id: 1 }), makeRequest({ id: 2 }), makeOffer({ id: 3, title: 'Yoga Classes' })];
    const { SuggestedListingsWidget } = await import('./SuggestedListingsWidget');
    render(<SuggestedListingsWidget listings={listings} />);
    expect(screen.getByText('Guitar Lessons')).toBeInTheDocument();
    expect(screen.getByText('Need a Plumber')).toBeInTheDocument();
    expect(screen.getByText('Yoga Classes')).toBeInTheDocument();
  });

  it('renders inside a GlassCard container', async () => {
    const { SuggestedListingsWidget } = await import('./SuggestedListingsWidget');
    render(<SuggestedListingsWidget listings={[makeOffer()]} />);
    expect(screen.getByTestId('glass-card')).toBeInTheDocument();
  });

  it('does not render See All link when listings are empty', async () => {
    const { SuggestedListingsWidget } = await import('./SuggestedListingsWidget');
    render(<SuggestedListingsWidget listings={[]} />);
    expect(screen.queryByRole('link', { name: /see all/i })).toBeNull();
  });
});
