// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import { HybridPriceDisplay } from './HybridPriceDisplay';

vi.mock('@/contexts', () =>
  createMockContexts({
    useTenant: () => ({
      tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  })
);

describe('HybridPriceDisplay', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders the cash price in the correct currency format', () => {
    render(
      <HybridPriceDisplay price={25} currency="EUR" timeCreditPrice={3} />
    );
    // Intl.NumberFormat will render something like "€25" or "25 €" depending on locale.
    // We match the numeric part to be locale-neutral.
    expect(screen.getByText(/25/)).toBeInTheDocument();
  });

  it('renders the time credit amount with "TC" suffix', () => {
    render(
      <HybridPriceDisplay price={10} currency="USD" timeCreditPrice={5} />
    );
    expect(screen.getByText(/5 TC/)).toBeInTheDocument();
  });

  it('renders the "+" separator between cash and time credit', () => {
    render(
      <HybridPriceDisplay price={10} currency="USD" timeCreditPrice={2} />
    );
    expect(screen.getByText('+')).toBeInTheDocument();
  });

  it('renders the hybrid pricing label from i18n', () => {
    render(
      <HybridPriceDisplay price={10} currency="USD" timeCreditPrice={2} />
    );
    // i18n key: marketplace:hybrid_pricing.label = "Pay with cash + time credits"
    expect(screen.getByText(/pay with cash/i)).toBeInTheDocument();
  });

  it('renders the "learn more" link pointing to tenant help page', () => {
    render(
      <HybridPriceDisplay price={10} currency="USD" timeCreditPrice={2} />
    );
    // i18n key: marketplace:hybrid_pricing.learn_more = "What are time credits?"
    const link = screen.getByRole('link', { name: /what are time credits\?/i });
    expect(link).toBeInTheDocument();
    expect(link).toHaveAttribute('href', '/test/help');
  });

  it('does NOT show the "Negotiable" chip when priceType is not "negotiable"', () => {
    render(
      <HybridPriceDisplay price={10} currency="USD" timeCreditPrice={2} />
    );
    expect(screen.queryByText(/negotiable/i)).not.toBeInTheDocument();
  });

  it('shows the "Negotiable" chip when priceType="negotiable"', () => {
    render(
      <HybridPriceDisplay
        price={10}
        currency="USD"
        timeCreditPrice={2}
        priceType="negotiable"
      />
    );
    // i18n key: marketplace:price.negotiable = "Negotiable"
    expect(screen.getByText(/negotiable/i)).toBeInTheDocument();
  });

  it('renders with a zero cash price', () => {
    render(
      <HybridPriceDisplay price={0} currency="GBP" timeCreditPrice={1} />
    );
    // Should not throw and should show 1 TC
    expect(screen.getByText(/1 TC/)).toBeInTheDocument();
  });

  it('renders with a zero time credit price', () => {
    render(
      <HybridPriceDisplay price={50} currency="EUR" timeCreditPrice={0} />
    );
    expect(screen.getByText(/0 TC/)).toBeInTheDocument();
  });

  it('falls back to "CURRENCY amount" when given an invalid currency code', () => {
    // Intl.NumberFormat will throw for unknown currencies — formatCurrency catches
    // the error and returns a fallback string.
    render(
      <HybridPriceDisplay price={15} currency="INVALID" timeCreditPrice={2} />
    );
    // The fallback is "INVALID 15"
    expect(screen.getByText(/INVALID 15/)).toBeInTheDocument();
  });

  // Size variants — smoke test each to confirm they render without crashing
  it.each(['sm', 'md', 'lg'] as const)(
    'renders without crashing at size="%s"',
    (size) => {
      const { container } = render(
        <HybridPriceDisplay
          price={20}
          currency="USD"
          timeCreditPrice={4}
          size={size}
        />
      );
      expect(container.firstChild).not.toBeNull();
    }
  );
});
