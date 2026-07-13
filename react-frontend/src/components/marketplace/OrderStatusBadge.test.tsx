// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

// ─── No API calls in this pure-display component ──────────────────────────────

// ─── Contexts ────────────────────────────────────────────────────────────────
vi.mock('@/contexts', () => createMockContexts());

function getChip() {
  const chip = document.querySelector<HTMLElement>('[data-slot="chip"]');
  expect(chip).not.toBeNull();
  return chip as HTMLElement;
}

// ─────────────────────────────────────────────────────────────────────────────
describe('OrderStatusBadge', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  // ── Label rendering ──────────────────────────────────────────────────────

  it('renders "Pending Payment" label for pending_payment status', async () => {
    const { OrderStatusBadge } = await import('./OrderStatusBadge');
    render(<OrderStatusBadge status="pending_payment" />);
    expect(getChip()).toHaveTextContent('Pending Payment');
  });

  it('renders "Paid" label for paid status', async () => {
    const { OrderStatusBadge } = await import('./OrderStatusBadge');
    render(<OrderStatusBadge status="paid" />);
    expect(getChip()).toHaveTextContent('Paid');
  });

  it('renders "Shipped" label for shipped status', async () => {
    const { OrderStatusBadge } = await import('./OrderStatusBadge');
    render(<OrderStatusBadge status="shipped" />);
    expect(getChip()).toHaveTextContent('Shipped');
  });

  it('renders "Delivered" label for delivered status', async () => {
    const { OrderStatusBadge } = await import('./OrderStatusBadge');
    render(<OrderStatusBadge status="delivered" />);
    expect(getChip()).toHaveTextContent('Delivered');
  });

  it('renders "Completed" label for completed status', async () => {
    const { OrderStatusBadge } = await import('./OrderStatusBadge');
    render(<OrderStatusBadge status="completed" />);
    expect(getChip()).toHaveTextContent('Completed');
  });

  it('renders "Disputed" label for disputed status', async () => {
    const { OrderStatusBadge } = await import('./OrderStatusBadge');
    render(<OrderStatusBadge status="disputed" />);
    expect(getChip()).toHaveTextContent('Disputed');
  });

  it('renders "Refunded" label for refunded status', async () => {
    const { OrderStatusBadge } = await import('./OrderStatusBadge');
    render(<OrderStatusBadge status="refunded" />);
    expect(getChip()).toHaveTextContent('Refunded');
  });

  it('renders "Cancelled" label for cancelled status', async () => {
    const { OrderStatusBadge } = await import('./OrderStatusBadge');
    render(<OrderStatusBadge status="cancelled" />);
    expect(getChip()).toHaveTextContent('Cancelled');
  });

  // ── Color mapping ────────────────────────────────────────────────────────

  it('applies warning color for pending_payment', async () => {
    const { OrderStatusBadge } = await import('./OrderStatusBadge');
    render(<OrderStatusBadge status="pending_payment" />);
    expect(getChip()).toHaveClass('chip--warning');
  });

  it('applies accent color for paid', async () => {
    const { OrderStatusBadge } = await import('./OrderStatusBadge');
    render(<OrderStatusBadge status="paid" />);
    expect(getChip()).toHaveClass('chip--accent');
  });

  it('applies success color for delivered', async () => {
    const { OrderStatusBadge } = await import('./OrderStatusBadge');
    render(<OrderStatusBadge status="delivered" />);
    expect(getChip()).toHaveClass('chip--success');
  });

  it('applies success color for completed', async () => {
    const { OrderStatusBadge } = await import('./OrderStatusBadge');
    render(<OrderStatusBadge status="completed" />);
    expect(getChip()).toHaveClass('chip--success');
  });

  it('applies danger color for disputed', async () => {
    const { OrderStatusBadge } = await import('./OrderStatusBadge');
    render(<OrderStatusBadge status="disputed" />);
    expect(getChip()).toHaveClass('chip--danger');
  });

  it('applies default color for shipped, refunded, cancelled', async () => {
    const { OrderStatusBadge } = await import('./OrderStatusBadge');
    for (const status of ['shipped', 'refunded', 'cancelled']) {
      const { unmount } = render(<OrderStatusBadge status={status} />);
      expect(getChip()).toHaveClass('chip--default');
      unmount();
    }
  });

  // ── Unknown status fallback ──────────────────────────────────────────────

  it('renders an unknown status using a humanized version of the key', async () => {
    const { OrderStatusBadge } = await import('./OrderStatusBadge');
    render(<OrderStatusBadge status="some_new_status" />);
    // Falls back to status.replace(/_/g, ' ') = "some new status"
    expect(getChip()).toHaveTextContent('some new status');
  });

  it('applies default color for unknown statuses', async () => {
    const { OrderStatusBadge } = await import('./OrderStatusBadge');
    render(<OrderStatusBadge status="unknown_status" />);
    expect(getChip()).toHaveClass('chip--default');
  });

  // ── Size prop ────────────────────────────────────────────────────────────

  it('uses sm size by default', async () => {
    const { OrderStatusBadge } = await import('./OrderStatusBadge');
    render(<OrderStatusBadge status="paid" />);
    expect(getChip()).toHaveClass('chip--sm');
  });

  it('passes size prop through to Chip', async () => {
    const { OrderStatusBadge } = await import('./OrderStatusBadge');
    render(<OrderStatusBadge status="paid" size="lg" />);
    expect(getChip()).toHaveClass('chip--lg');
  });

  // ── Variant ──────────────────────────────────────────────────────────────

  it('always renders with tertiary variant', async () => {
    const { OrderStatusBadge } = await import('./OrderStatusBadge');
    render(<OrderStatusBadge status="paid" />);
    expect(getChip()).toHaveClass('chip--tertiary');
  });
});
