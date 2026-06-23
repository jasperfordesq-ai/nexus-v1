// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';

beforeEach(() => {
  vi.resetAllMocks();
});

describe('MarketplacePartnerBadge', () => {
  it('renders nothing when grantedAt is undefined', async () => {
    const { MarketplacePartnerBadge } = await import('./MarketplacePartnerBadge');
    render(<MarketplacePartnerBadge />);
    expect(screen.queryByText('Marketplace Partner')).not.toBeInTheDocument();
  });

  it('renders nothing when grantedAt is null', async () => {
    const { MarketplacePartnerBadge } = await import('./MarketplacePartnerBadge');
    render(<MarketplacePartnerBadge grantedAt={null} />);
    expect(screen.queryByText('Marketplace Partner')).not.toBeInTheDocument();
  });

  it('renders the "Marketplace Partner" label when grantedAt is a date string', async () => {
    const { MarketplacePartnerBadge } = await import('./MarketplacePartnerBadge');
    render(<MarketplacePartnerBadge grantedAt="2025-01-01T00:00:00Z" />);
    expect(screen.getByText('Marketplace Partner')).toBeInTheDocument();
  });

  it('renders the ShieldCheck SVG icon when grantedAt is provided', async () => {
    const { MarketplacePartnerBadge } = await import('./MarketplacePartnerBadge');
    const { container } = render(<MarketplacePartnerBadge grantedAt="2025-01-01T00:00:00Z" />);
    expect(container.querySelector('svg')).toBeInTheDocument();
  });

  it('icon has aria-hidden="true"', async () => {
    const { MarketplacePartnerBadge } = await import('./MarketplacePartnerBadge');
    const { container } = render(<MarketplacePartnerBadge grantedAt="2025-06-01" />);
    const svg = container.querySelector('svg');
    expect(svg?.getAttribute('aria-hidden')).toBe('true');
  });

  it('renders with size="sm" by default (no size prop)', async () => {
    const { MarketplacePartnerBadge } = await import('./MarketplacePartnerBadge');
    render(<MarketplacePartnerBadge grantedAt="2025-01-01" />);
    // Just verify it renders without error at default size
    expect(screen.getByText('Marketplace Partner')).toBeInTheDocument();
  });

  it('renders with size="md" without error', async () => {
    const { MarketplacePartnerBadge } = await import('./MarketplacePartnerBadge');
    render(<MarketplacePartnerBadge grantedAt="2025-01-01" size="md" />);
    expect(screen.getByText('Marketplace Partner')).toBeInTheDocument();
  });

  it('renders with size="lg" without error', async () => {
    const { MarketplacePartnerBadge } = await import('./MarketplacePartnerBadge');
    render(<MarketplacePartnerBadge grantedAt="2025-01-01" size="lg" />);
    expect(screen.getByText('Marketplace Partner')).toBeInTheDocument();
  });

  it('does not render SVG when grantedAt is null', async () => {
    const { MarketplacePartnerBadge } = await import('./MarketplacePartnerBadge');
    const { container } = render(<MarketplacePartnerBadge grantedAt={null} />);
    expect(container.querySelector('svg')).not.toBeInTheDocument();
  });
});
