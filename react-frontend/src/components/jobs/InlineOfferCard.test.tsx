// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

vi.mock('@/contexts', () => createMockContexts());

import { InlineOfferCard } from './InlineOfferCard';
import type { InlineOffer } from './JobDetailTypes';

const pendingOffer: InlineOffer = {
  id: 1,
  application_id: 10,
  salary_offered: '3000',
  salary_currency: 'EUR',
  salary_type: 'monthly',
  start_date: '2025-06-01',
  message: 'Welcome aboard!',
  status: 'pending',
};

const minimalOffer: InlineOffer = {
  id: 2,
  application_id: 11,
  salary_offered: null,
  salary_currency: 'EUR',
  salary_type: 'annual',
  start_date: null,
  message: null,
  status: 'pending',
};

describe('InlineOfferCard — non-pending status returns null', () => {
  beforeEach(() => vi.clearAllMocks());

  it('renders no heading or buttons when status is accepted', () => {
    // The component returns null for non-pending, but the ToastProvider wrapper
    // always renders a toast-container div so container.firstChild is never null.
    // Assert on visible content instead.
    const offer = { ...pendingOffer, status: 'accepted' as const };
    render(
      <InlineOfferCard
        pendingOffer={offer}
        isResponding={false}
        onAccept={vi.fn()}
        onDeclineOpen={vi.fn()}
      />
    );
    expect(screen.queryByRole('heading')).not.toBeInTheDocument();
    expect(screen.queryByRole('button')).not.toBeInTheDocument();
  });

  it('renders no heading or buttons when status is rejected', () => {
    const offer = { ...pendingOffer, status: 'rejected' as const };
    render(
      <InlineOfferCard
        pendingOffer={offer}
        isResponding={false}
        onAccept={vi.fn()}
        onDeclineOpen={vi.fn()}
      />
    );
    expect(screen.queryByRole('heading')).not.toBeInTheDocument();
    expect(screen.queryByRole('button')).not.toBeInTheDocument();
  });
});

describe('InlineOfferCard — pending offer renders correctly', () => {
  beforeEach(() => vi.clearAllMocks());

  it('shows the offer heading', () => {
    render(
      <InlineOfferCard
        pendingOffer={pendingOffer}
        isResponding={false}
        onAccept={vi.fn()}
        onDeclineOpen={vi.fn()}
      />
    );
    // i18n key "inline_response.offer_pending"
    expect(screen.getByRole('heading', { level: 3 })).toBeInTheDocument();
  });

  it('displays salary when salary_offered is set', () => {
    render(
      <InlineOfferCard
        pendingOffer={pendingOffer}
        isResponding={false}
        onAccept={vi.fn()}
        onDeclineOpen={vi.fn()}
      />
    );
    expect(screen.getByText(/3000/)).toBeInTheDocument();
    expect(screen.getByText(/EUR/)).toBeInTheDocument();
  });

  it('does not render salary row when salary_offered is null', () => {
    render(
      <InlineOfferCard
        pendingOffer={minimalOffer}
        isResponding={false}
        onAccept={vi.fn()}
        onDeclineOpen={vi.fn()}
      />
    );
    expect(screen.queryByText(/EUR3000/)).not.toBeInTheDocument();
  });

  it('displays the offer message in italics when present', () => {
    render(
      <InlineOfferCard
        pendingOffer={pendingOffer}
        isResponding={false}
        onAccept={vi.fn()}
        onDeclineOpen={vi.fn()}
      />
    );
    expect(screen.getByText(/Welcome aboard!/)).toBeInTheDocument();
  });

  it('does not render a message paragraph when message is null', () => {
    render(
      <InlineOfferCard
        pendingOffer={minimalOffer}
        isResponding={false}
        onAccept={vi.fn()}
        onDeclineOpen={vi.fn()}
      />
    );
    expect(screen.queryByText(/Welcome aboard!/)).not.toBeInTheDocument();
  });

  it('renders Accept and Decline buttons', () => {
    render(
      <InlineOfferCard
        pendingOffer={pendingOffer}
        isResponding={false}
        onAccept={vi.fn()}
        onDeclineOpen={vi.fn()}
      />
    );
    const buttons = screen.getAllByRole('button');
    // At minimum an accept and a decline button
    expect(buttons.length).toBeGreaterThanOrEqual(2);
  });
});

describe('InlineOfferCard — button interactions', () => {
  beforeEach(() => vi.clearAllMocks());

  it('calls onAccept when the accept button is pressed', () => {
    const onAccept = vi.fn();
    render(
      <InlineOfferCard
        pendingOffer={pendingOffer}
        isResponding={false}
        onAccept={onAccept}
        onDeclineOpen={vi.fn()}
      />
    );
    // i18n key "inline_response.offer_accept"
    const acceptBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.match(/accept/i)
    );
    if (acceptBtn) fireEvent.click(acceptBtn);
    expect(onAccept).toHaveBeenCalled();
  });

  it('calls onDeclineOpen when the decline button is clicked', () => {
    const onDeclineOpen = vi.fn();
    render(
      <InlineOfferCard
        pendingOffer={pendingOffer}
        isResponding={false}
        onAccept={vi.fn()}
        onDeclineOpen={onDeclineOpen}
      />
    );
    const declineBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.match(/decline/i)
    );
    if (declineBtn) fireEvent.click(declineBtn);
    expect(onDeclineOpen).toHaveBeenCalled();
  });

  it('disables the decline button while isResponding is true', () => {
    render(
      <InlineOfferCard
        pendingOffer={pendingOffer}
        isResponding={true}
        onAccept={vi.fn()}
        onDeclineOpen={vi.fn()}
      />
    );
    const declineBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.match(/decline/i)
    );
    // HeroUI v3 Button with isDisabled sets the native `disabled` attribute
    // (aria-disabled is NOT set separately in v3). Check either form.
    const isNativeDisabled = declineBtn?.hasAttribute('disabled');
    const isAriaDisabled = declineBtn?.getAttribute('aria-disabled') === 'true';
    expect(isNativeDisabled || isAriaDisabled).toBe(true);
  });
});

describe('InlineOfferCard — start_date display', () => {
  beforeEach(() => vi.clearAllMocks());

  it('shows a formatted start date when start_date is provided', () => {
    render(
      <InlineOfferCard
        pendingOffer={pendingOffer}
        isResponding={false}
        onAccept={vi.fn()}
        onDeclineOpen={vi.fn()}
      />
    );
    // formatDateValue converts '2025-06-01' to a locale date string — just confirm year appears
    expect(screen.getByText(/2025/)).toBeInTheDocument();
  });

  it('does not render start date row when start_date is null', () => {
    render(
      <InlineOfferCard
        pendingOffer={minimalOffer}
        isResponding={false}
        onAccept={vi.fn()}
        onDeclineOpen={vi.fn()}
      />
    );
    expect(screen.queryByText(/2025/)).not.toBeInTheDocument();
  });
});
