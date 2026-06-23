// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';

// ─── No API calls in this component ─────────────────────────────────────────

// ─── Stub HeroUI Tooltip + Chip to avoid jsdom layout issues ────────────────
vi.mock('@/components/ui', async (importOriginal) => {
  const orig = await importOriginal<typeof import('@/components/ui')>();
  return {
    ...orig,
    // Tooltip: render children + expose content in a data attr for assertions
    Tooltip: ({ children, content }: { children: React.ReactNode; content: string }) => (
      <div data-testid="tooltip-wrapper" data-tooltip={content}>
        {children}
      </div>
    ),
    // Chip: render a span with accessible text
    Chip: ({
      children,
      size,
      color,
      variant,
      startContent,
      className,
    }: {
      children: React.ReactNode;
      size?: string;
      color?: string;
      variant?: string;
      startContent?: React.ReactNode;
      className?: string;
    }) => (
      <span
        data-testid="chip"
        data-size={size}
        data-color={color}
        data-variant={variant}
        className={className}
      >
        {startContent}
        {children}
      </span>
    ),
  };
});

vi.mock('@/contexts', () => createMockContexts());
vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

// ─────────────────────────────────────────────────────────────────────────────
describe('VerifiedMunicipalityBadge', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('renders a chip with the verified municipality label', async () => {
    const { VerifiedMunicipalityBadge } = await import('./VerifiedMunicipalityBadge');
    render(<VerifiedMunicipalityBadge />);

    // The translation key 'verified_municipality.label' returns the key in test env
    const chip = screen.getByTestId('chip');
    expect(chip).toBeInTheDocument();
  });

  it('wraps the chip in a Tooltip', async () => {
    const { VerifiedMunicipalityBadge } = await import('./VerifiedMunicipalityBadge');
    render(<VerifiedMunicipalityBadge />);

    expect(screen.getByTestId('tooltip-wrapper')).toBeInTheDocument();
  });

  it('tooltip content is non-empty', async () => {
    const { VerifiedMunicipalityBadge } = await import('./VerifiedMunicipalityBadge');
    render(<VerifiedMunicipalityBadge />);

    const tooltip = screen.getByTestId('tooltip-wrapper');
    const content = tooltip.getAttribute('data-tooltip') ?? '';
    // The tooltip must have some text (translated or key fallback)
    expect(content.length).toBeGreaterThan(0);
  });

  it('tooltip content is longer when domain prop is provided', async () => {
    const { VerifiedMunicipalityBadge } = await import('./VerifiedMunicipalityBadge');

    const { unmount: unmount1 } = render(<VerifiedMunicipalityBadge />);
    const baseContent = (screen.getByTestId('tooltip-wrapper').getAttribute('data-tooltip') ?? '').length;
    unmount1();

    render(<VerifiedMunicipalityBadge domain="cork.ie" />);
    const withDomainContent = (screen.getByTestId('tooltip-wrapper').getAttribute('data-tooltip') ?? '').length;

    // Adding a domain appends a segment → tooltip is longer
    expect(withDomainContent).toBeGreaterThan(baseContent);
  });

  it('tooltip content is longer when verifiedAt prop is provided', async () => {
    const { VerifiedMunicipalityBadge } = await import('./VerifiedMunicipalityBadge');

    const { unmount } = render(<VerifiedMunicipalityBadge />);
    const baseLen = (screen.getByTestId('tooltip-wrapper').getAttribute('data-tooltip') ?? '').length;
    unmount();

    render(<VerifiedMunicipalityBadge verifiedAt="2025-06-01T00:00:00Z" />);
    const withDateLen = (screen.getByTestId('tooltip-wrapper').getAttribute('data-tooltip') ?? '').length;

    expect(withDateLen).toBeGreaterThan(baseLen);
  });

  it('tooltip concatenates parts with " · " separator', async () => {
    const { VerifiedMunicipalityBadge } = await import('./VerifiedMunicipalityBadge');
    render(<VerifiedMunicipalityBadge domain="galway.ie" verifiedAt="2025-03-15T00:00:00Z" />);

    const tooltip = screen.getByTestId('tooltip-wrapper');
    const content = tooltip.getAttribute('data-tooltip') ?? '';
    expect(content).toContain(' · ');
  });

  it('chip uses success color and flat variant', async () => {
    const { VerifiedMunicipalityBadge } = await import('./VerifiedMunicipalityBadge');
    render(<VerifiedMunicipalityBadge />);

    const chip = screen.getByTestId('chip');
    expect(chip).toHaveAttribute('data-color', 'success');
    expect(chip).toHaveAttribute('data-variant', 'flat');
  });

  it('defaults to sm size when no size prop is given', async () => {
    const { VerifiedMunicipalityBadge } = await import('./VerifiedMunicipalityBadge');
    render(<VerifiedMunicipalityBadge />);

    expect(screen.getByTestId('chip')).toHaveAttribute('data-size', 'sm');
  });

  it('uses md size when size="md" prop is given', async () => {
    const { VerifiedMunicipalityBadge } = await import('./VerifiedMunicipalityBadge');
    render(<VerifiedMunicipalityBadge size="md" />);

    expect(screen.getByTestId('chip')).toHaveAttribute('data-size', 'md');
  });

  it('forwards className to the Chip', async () => {
    const { VerifiedMunicipalityBadge } = await import('./VerifiedMunicipalityBadge');
    render(<VerifiedMunicipalityBadge className="my-custom-class" />);

    expect(screen.getByTestId('chip')).toHaveClass('my-custom-class');
  });

  it('does NOT include domain key when domain is null', async () => {
    const { VerifiedMunicipalityBadge } = await import('./VerifiedMunicipalityBadge');
    render(<VerifiedMunicipalityBadge domain={null} />);

    const tooltip = screen.getByTestId('tooltip-wrapper');
    const content = tooltip.getAttribute('data-tooltip') ?? '';
    expect(content).not.toContain('verified_municipality.domain');
  });

  it('does NOT include verified_on key when verifiedAt is null', async () => {
    const { VerifiedMunicipalityBadge } = await import('./VerifiedMunicipalityBadge');
    render(<VerifiedMunicipalityBadge verifiedAt={null} />);

    const tooltip = screen.getByTestId('tooltip-wrapper');
    const content = tooltip.getAttribute('data-tooltip') ?? '';
    expect(content).not.toContain('verified_municipality.verified_on');
  });

  it('renders a shield icon inside the chip via startContent', async () => {
    const { VerifiedMunicipalityBadge } = await import('./VerifiedMunicipalityBadge');
    const { container } = render(<VerifiedMunicipalityBadge />);

    // The ShieldCheck svg is rendered inside the chip's startContent slot
    const svg = container.querySelector('svg');
    expect(svg).toBeInTheDocument();
  });
});
