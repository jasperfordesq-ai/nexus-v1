// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';

// ─── Contexts ────────────────────────────────────────────────────────────────
vi.mock('@/contexts', () =>
  createMockContexts({
    useTenant: () => ({
      tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  }),
);

// ─── Stub Button to avoid HeroUI jsdom quirks ────────────────────────────────
vi.mock('@/components/ui', async (importOriginal) => {
  const orig = await importOriginal<typeof import('@/components/ui')>();
  return {
    ...orig,
    Button: ({ children, ...rest }: { children: React.ReactNode; [key: string]: unknown }) => (
      <button data-testid="cta-button" {...(rest as object)}>{children}</button>
    ),
  };
});

// ─────────────────────────────────────────────────────────────────────────────
describe('MarketplaceEmptyState', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('renders the default no-listings translated message', async () => {
    const { MarketplaceEmptyState } = await import('./MarketplaceEmptyState');
    render(<MarketplaceEmptyState />);
    expect(screen.getByText('No listings yet')).toBeInTheDocument();
  });

  it('renders the translated subtitle text', async () => {
    const { MarketplaceEmptyState } = await import('./MarketplaceEmptyState');
    render(<MarketplaceEmptyState />);
    expect(screen.getByText('Check back later or be the first to list something.')).toBeInTheDocument();
  });

  it('renders a custom message when provided', async () => {
    const { MarketplaceEmptyState } = await import('./MarketplaceEmptyState');
    render(<MarketplaceEmptyState message="Nothing to see here" />);
    expect(screen.getByText('Nothing to see here')).toBeInTheDocument();
    // default message should not appear
    expect(screen.queryByText('No listings yet')).not.toBeInTheDocument();
  });

  it('does NOT render the CTA button by default (showCta=false)', async () => {
    const { MarketplaceEmptyState } = await import('./MarketplaceEmptyState');
    render(<MarketplaceEmptyState />);
    expect(screen.queryByTestId('cta-button')).not.toBeInTheDocument();
  });

  it('renders the CTA button when showCta=true', async () => {
    const { MarketplaceEmptyState } = await import('./MarketplaceEmptyState');
    render(<MarketplaceEmptyState showCta />);
    expect(screen.getByTestId('cta-button')).toBeInTheDocument();
  });

  it('CTA button shows translated "Start Selling" text', async () => {
    const { MarketplaceEmptyState } = await import('./MarketplaceEmptyState');
    render(<MarketplaceEmptyState showCta />);
    expect(screen.getByText('Start Selling')).toBeInTheDocument();
  });

  it('CTA link points to tenantPath(/marketplace/sell)', async () => {
    const { MarketplaceEmptyState } = await import('./MarketplaceEmptyState');
    render(<MarketplaceEmptyState showCta />);
    const btn = screen.getByTestId('cta-button');
    // The Button is rendered as-a Link; `to` prop becomes an attribute in our stub
    expect(btn.getAttribute('to')).toBe('/test/marketplace/sell');
  });

  it('renders the shopping bag icon (aria-hidden)', async () => {
    const { MarketplaceEmptyState } = await import('./MarketplaceEmptyState');
    const { container } = render(<MarketplaceEmptyState />);
    // lucide-react renders an SVG; it should be aria-hidden
    const svg = container.querySelector('svg');
    expect(svg).not.toBeNull();
    expect(svg?.getAttribute('aria-hidden')).toBe('true');
  });

  it('renders both message and subtitle when custom message is supplied', async () => {
    const { MarketplaceEmptyState } = await import('./MarketplaceEmptyState');
    render(<MarketplaceEmptyState message="Custom empty text" />);
    expect(screen.getByText('Custom empty text')).toBeInTheDocument();
    expect(screen.getByText('Check back later or be the first to list something.')).toBeInTheDocument();
  });

  it('container uses flex-col centred layout', async () => {
    const { MarketplaceEmptyState } = await import('./MarketplaceEmptyState');
    const { container } = render(<MarketplaceEmptyState />);
    const root = container.firstElementChild;
    expect(root?.className).toContain('flex');
    expect(root?.className).toContain('flex-col');
    expect(root?.className).toContain('items-center');
  });

  it('renders correctly with both showCta and custom message', async () => {
    const { MarketplaceEmptyState } = await import('./MarketplaceEmptyState');
    render(<MarketplaceEmptyState showCta message="Try selling something!" />);
    expect(screen.getByText('Try selling something!')).toBeInTheDocument();
    expect(screen.getByTestId('cta-button')).toBeInTheDocument();
  });
});
