// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';
import React from 'react';

// ─── No api calls; no context consumption → no api/context mocks needed ───────

// ─── Stub @/components/ui Chip (avoids HeroUI jsdom issues) ─────────────────
vi.mock('@/components/ui', async (importOriginal) => {
  const orig = await importOriginal<typeof import('@/components/ui')>();
  return {
    ...orig,
    Chip: ({
      children,
      color,
      size,
      variant,
      startContent,
    }: {
      children?: React.ReactNode;
      color?: string;
      size?: string;
      variant?: string;
      startContent?: React.ReactNode;
    }) => (
      <span
        data-testid="chip"
        data-color={color}
        data-size={size}
        data-variant={variant}
      >
        {startContent}
        {children}
      </span>
    ),
  };
});

describe('TrustTierBadge', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('renders without crashing for tier 0', async () => {
    const { TrustTierBadge } = await import('./TrustTierBadge');
    render(<TrustTierBadge tier={0} />);
    expect(screen.getByTestId('chip')).toBeInTheDocument();
  });

  it('shows "Newcomer" label for tier 0', async () => {
    const { TrustTierBadge } = await import('./TrustTierBadge');
    render(<TrustTierBadge tier={0} />);
    // Real i18n → English translation from caring_community namespace
    expect(screen.getByTestId('chip').textContent?.toLowerCase()).toContain('newcomer');
  });

  it('shows "Member" label for tier 1', async () => {
    const { TrustTierBadge } = await import('./TrustTierBadge');
    render(<TrustTierBadge tier={1} />);
    expect(screen.getByTestId('chip').textContent?.toLowerCase()).toContain('member');
  });

  it('shows "Trusted" label for tier 2', async () => {
    const { TrustTierBadge } = await import('./TrustTierBadge');
    render(<TrustTierBadge tier={2} />);
    expect(screen.getByTestId('chip').textContent?.toLowerCase()).toContain('trusted');
  });

  it('shows "Verified" label for tier 3', async () => {
    const { TrustTierBadge } = await import('./TrustTierBadge');
    render(<TrustTierBadge tier={3} />);
    expect(screen.getByTestId('chip').textContent?.toLowerCase()).toContain('verified');
  });

  it('shows "Coordinator" label for tier 4', async () => {
    const { TrustTierBadge } = await import('./TrustTierBadge');
    render(<TrustTierBadge tier={4} />);
    expect(screen.getByTestId('chip').textContent?.toLowerCase()).toContain('coordinator');
  });

  it('hides label text when showLabel=false', async () => {
    const { TrustTierBadge } = await import('./TrustTierBadge');
    render(<TrustTierBadge tier={2} showLabel={false} />);
    const chip = screen.getByTestId('chip');
    // Icon SVG is still there, but no translated text child
    expect(chip.textContent).not.toMatch(/trusted/i);
  });

  it('applies "sm" size by default', async () => {
    const { TrustTierBadge } = await import('./TrustTierBadge');
    render(<TrustTierBadge tier={1} />);
    expect(screen.getByTestId('chip').getAttribute('data-size')).toBe('sm');
  });

  it('applies "md" size when size="md"', async () => {
    const { TrustTierBadge } = await import('./TrustTierBadge');
    render(<TrustTierBadge tier={1} size="md" />);
    expect(screen.getByTestId('chip').getAttribute('data-size')).toBe('md');
  });

  it('tier 0 uses "default" color', async () => {
    const { TrustTierBadge } = await import('./TrustTierBadge');
    render(<TrustTierBadge tier={0} />);
    expect(screen.getByTestId('chip').getAttribute('data-color')).toBe('default');
  });

  it('tier 1 uses "primary" color', async () => {
    const { TrustTierBadge } = await import('./TrustTierBadge');
    render(<TrustTierBadge tier={1} />);
    expect(screen.getByTestId('chip').getAttribute('data-color')).toBe('primary');
  });

  it('tier 2 uses "success" color', async () => {
    const { TrustTierBadge } = await import('./TrustTierBadge');
    render(<TrustTierBadge tier={2} />);
    expect(screen.getByTestId('chip').getAttribute('data-color')).toBe('success');
  });

  it('tier 4 uses "warning" color', async () => {
    const { TrustTierBadge } = await import('./TrustTierBadge');
    render(<TrustTierBadge tier={4} />);
    expect(screen.getByTestId('chip').getAttribute('data-color')).toBe('warning');
  });

  it('always renders with flat variant', async () => {
    const { TrustTierBadge } = await import('./TrustTierBadge');
    render(<TrustTierBadge tier={3} />);
    expect(screen.getByTestId('chip').getAttribute('data-variant')).toBe('flat');
  });

  it('renders an icon (SVG) as startContent', async () => {
    const { TrustTierBadge } = await import('./TrustTierBadge');
    render(<TrustTierBadge tier={2} />);
    // Our Chip stub renders startContent; Lucide outputs an svg
    const chip = screen.getByTestId('chip');
    expect(chip.querySelector('svg')).toBeInTheDocument();
  });
});
