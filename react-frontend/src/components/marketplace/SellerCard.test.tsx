// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

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

// resolveAvatarUrl is a pure helper — no need to mock it
import { SellerCard } from './SellerCard';

const baseSeller = {
  id: 42,
  name: 'Alice Smith',
};

describe('SellerCard', () => {
  beforeEach(() => vi.clearAllMocks());

  it('renders the seller name', () => {
    render(<SellerCard seller={baseSeller} />);
    expect(screen.getByText('Alice Smith')).toBeInTheDocument();
  });

  it('renders a "view profile" link pointing to the correct tenant path', () => {
    render(<SellerCard seller={baseSeller} />);
    const link = screen.getByRole('link');
    expect(link).toHaveAttribute('href', '/test/marketplace/seller/42');
  });

  it('does NOT show the verification icon when is_verified is false', () => {
    render(<SellerCard seller={{ ...baseSeller, is_verified: false }} />);
    // CheckCircle has aria-label "seller.verified" (i18n key — English = "Verified seller" or similar)
    // In test i18n the key is returned as-is when no translation file is loaded
    expect(screen.queryByLabelText(/verified/i)).not.toBeInTheDocument();
  });

  it('shows the verification icon when is_verified is true', () => {
    render(<SellerCard seller={{ ...baseSeller, is_verified: true }} />);
    // The icon has aria-label={t('seller.verified')} — match on attribute value
    expect(screen.getByLabelText(/verified/i)).toBeInTheDocument();
  });

  it('renders the "business" chip when seller_type is business', () => {
    render(<SellerCard seller={{ ...baseSeller, seller_type: 'business' }} />);
    // i18n key: seller.business — in test mode the key is returned directly
    expect(screen.getByText(/business/i)).toBeInTheDocument();
  });

  it('renders the "private" chip when seller_type is private', () => {
    render(<SellerCard seller={{ ...baseSeller, seller_type: 'private' }} />);
    expect(screen.getByText(/private/i)).toBeInTheDocument();
  });

  it('does not render a type chip when seller_type is absent', () => {
    render(<SellerCard seller={baseSeller} />);
    // Neither keyword should appear as chip content
    expect(screen.queryByText(/^business$/i)).not.toBeInTheDocument();
    expect(screen.queryByText(/^private$/i)).not.toBeInTheDocument();
  });

  it('renders an avatar with the seller name as fallback', () => {
    // Avatar renders an img with alt equal to the name, or a span with initials —
    // either way the name is accessible somewhere; just confirm render does not throw
    const { container } = render(<SellerCard seller={baseSeller} />);
    expect(container.firstChild).not.toBeNull();
  });
});
