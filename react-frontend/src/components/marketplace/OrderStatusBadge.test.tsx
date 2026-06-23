// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';

// ─── No API calls in this pure-display component ──────────────────────────────

// ─── Contexts ────────────────────────────────────────────────────────────────
vi.mock('@/contexts', () => createMockContexts());

// ─── Stub @/components/ui — render Chip as a <span> with color/variant attrs ──
vi.mock('@/components/ui', async (importOriginal) => {
  const orig = await importOriginal<typeof import('@/components/ui')>();
  return {
    ...orig,
    Chip: ({
      children,
      color,
      variant,
      size,
      ...rest
    }: React.HTMLAttributes<HTMLSpanElement> & {
      color?: string;
      variant?: string;
      size?: string;
    }) => (
      <span
        data-testid="chip"
        data-color={color}
        data-variant={variant}
        data-size={size}
        {...rest}
      >
        {children}
      </span>
    ),
  };
});

// ─────────────────────────────────────────────────────────────────────────────
describe('OrderStatusBadge', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  // ── Label rendering ──────────────────────────────────────────────────────

  it('renders "Pending Payment" label for pending_payment status', async () => {
    const { OrderStatusBadge } = await import('./OrderStatusBadge');
    render(<OrderStatusBadge status="pending_payment" />);
    expect(screen.getByTestId('chip')).toHaveTextContent('Pending Payment');
  });

  it('renders "Paid" label for paid status', async () => {
    const { OrderStatusBadge } = await import('./OrderStatusBadge');
    render(<OrderStatusBadge status="paid" />);
    expect(screen.getByTestId('chip')).toHaveTextContent('Paid');
  });

  it('renders "Shipped" label for shipped status', async () => {
    const { OrderStatusBadge } = await import('./OrderStatusBadge');
    render(<OrderStatusBadge status="shipped" />);
    expect(screen.getByTestId('chip')).toHaveTextContent('Shipped');
  });

  it('renders "Delivered" label for delivered status', async () => {
    const { OrderStatusBadge } = await import('./OrderStatusBadge');
    render(<OrderStatusBadge status="delivered" />);
    expect(screen.getByTestId('chip')).toHaveTextContent('Delivered');
  });

  it('renders "Completed" label for completed status', async () => {
    const { OrderStatusBadge } = await import('./OrderStatusBadge');
    render(<OrderStatusBadge status="completed" />);
    expect(screen.getByTestId('chip')).toHaveTextContent('Completed');
  });

  it('renders "Disputed" label for disputed status', async () => {
    const { OrderStatusBadge } = await import('./OrderStatusBadge');
    render(<OrderStatusBadge status="disputed" />);
    expect(screen.getByTestId('chip')).toHaveTextContent('Disputed');
  });

  it('renders "Refunded" label for refunded status', async () => {
    const { OrderStatusBadge } = await import('./OrderStatusBadge');
    render(<OrderStatusBadge status="refunded" />);
    expect(screen.getByTestId('chip')).toHaveTextContent('Refunded');
  });

  it('renders "Cancelled" label for cancelled status', async () => {
    const { OrderStatusBadge } = await import('./OrderStatusBadge');
    render(<OrderStatusBadge status="cancelled" />);
    expect(screen.getByTestId('chip')).toHaveTextContent('Cancelled');
  });

  // ── Color mapping ────────────────────────────────────────────────────────

  it('applies warning color for pending_payment', async () => {
    const { OrderStatusBadge } = await import('./OrderStatusBadge');
    render(<OrderStatusBadge status="pending_payment" />);
    expect(screen.getByTestId('chip')).toHaveAttribute('data-color', 'warning');
  });

  it('applies accent color for paid', async () => {
    const { OrderStatusBadge } = await import('./OrderStatusBadge');
    render(<OrderStatusBadge status="paid" />);
    expect(screen.getByTestId('chip')).toHaveAttribute('data-color', 'accent');
  });

  it('applies success color for delivered', async () => {
    const { OrderStatusBadge } = await import('./OrderStatusBadge');
    render(<OrderStatusBadge status="delivered" />);
    expect(screen.getByTestId('chip')).toHaveAttribute('data-color', 'success');
  });

  it('applies success color for completed', async () => {
    const { OrderStatusBadge } = await import('./OrderStatusBadge');
    render(<OrderStatusBadge status="completed" />);
    expect(screen.getByTestId('chip')).toHaveAttribute('data-color', 'success');
  });

  it('applies danger color for disputed', async () => {
    const { OrderStatusBadge } = await import('./OrderStatusBadge');
    render(<OrderStatusBadge status="disputed" />);
    expect(screen.getByTestId('chip')).toHaveAttribute('data-color', 'danger');
  });

  it('applies default color for shipped, refunded, cancelled', async () => {
    const { OrderStatusBadge } = await import('./OrderStatusBadge');
    for (const status of ['shipped', 'refunded', 'cancelled']) {
      const { unmount } = render(<OrderStatusBadge status={status} />);
      expect(screen.getByTestId('chip')).toHaveAttribute('data-color', 'default');
      unmount();
    }
  });

  // ── Unknown status fallback ──────────────────────────────────────────────

  it('renders an unknown status using a humanized version of the key', async () => {
    const { OrderStatusBadge } = await import('./OrderStatusBadge');
    render(<OrderStatusBadge status="some_new_status" />);
    // Falls back to status.replace(/_/g, ' ') = "some new status"
    expect(screen.getByTestId('chip')).toHaveTextContent('some new status');
  });

  it('applies default color for unknown statuses', async () => {
    const { OrderStatusBadge } = await import('./OrderStatusBadge');
    render(<OrderStatusBadge status="unknown_status" />);
    expect(screen.getByTestId('chip')).toHaveAttribute('data-color', 'default');
  });

  // ── Size prop ────────────────────────────────────────────────────────────

  it('uses sm size by default', async () => {
    const { OrderStatusBadge } = await import('./OrderStatusBadge');
    render(<OrderStatusBadge status="paid" />);
    expect(screen.getByTestId('chip')).toHaveAttribute('data-size', 'sm');
  });

  it('passes size prop through to Chip', async () => {
    const { OrderStatusBadge } = await import('./OrderStatusBadge');
    render(<OrderStatusBadge status="paid" size="lg" />);
    expect(screen.getByTestId('chip')).toHaveAttribute('data-size', 'lg');
  });

  // ── Variant ──────────────────────────────────────────────────────────────

  it('always renders with tertiary variant', async () => {
    const { OrderStatusBadge } = await import('./OrderStatusBadge');
    render(<OrderStatusBadge status="paid" />);
    expect(screen.getByTestId('chip')).toHaveAttribute('data-variant', 'tertiary');
  });
});
