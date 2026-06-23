// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';

// ─── API mock (unused by this component, but required by test infrastructure) ──
const { mockApi } = vi.hoisted(() => ({
  mockApi: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
    download: vi.fn(),
    upload: vi.fn(),
  },
}));
vi.mock('@/lib/api', () => ({ api: mockApi, default: mockApi }));

// ─── Contexts ─────────────────────────────────────────────────────────────────
const mockToast = {
  success: vi.fn(),
  error: vi.fn(),
  info: vi.fn(),
  warning: vi.fn(),
  showToast: vi.fn(),
};

vi.mock('@/contexts', () =>
  createMockContexts({
    useAuth: () => ({
      user: { id: 1, name: 'Test User' },
      isAuthenticated: true,
      login: vi.fn(),
      logout: vi.fn(),
      register: vi.fn(),
      updateUser: vi.fn(),
      refreshUser: vi.fn(),
      status: 'idle' as const,
      error: null,
    }),
    useToast: () => mockToast,
    useTenant: () => ({
      tenant: { id: 2, name: 'Test', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  }),
);

// ─────────────────────────────────────────────────────────────────────────────
describe('BusinessSellerBadge', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('renders nothing when sellerType is not "business"', async () => {
    const { BusinessSellerBadge } = await import('./BusinessSellerBadge');
    const { container } = render(<BusinessSellerBadge sellerType="private" />);
    // ToastProvider always renders a role=status element — ensure NO badge text
    expect(screen.queryByText('Business')).toBeNull();
    expect(screen.queryByText('Verified Business')).toBeNull();
    // The only children should be the ToastProvider scaffolding, not our badge
    expect(container.querySelector('[data-testid]')).toBeNull();
  });

  it('renders nothing when sellerType is empty string', async () => {
    const { BusinessSellerBadge } = await import('./BusinessSellerBadge');
    render(<BusinessSellerBadge sellerType="" />);
    expect(screen.queryByText('Business')).toBeNull();
    expect(screen.queryByText('Verified Business')).toBeNull();
  });

  it('renders unverified Business chip when sellerType is "business" without businessVerified', async () => {
    const { BusinessSellerBadge } = await import('./BusinessSellerBadge');
    render(<BusinessSellerBadge sellerType="business" />);
    expect(screen.getByText('Business')).toBeInTheDocument();
  });

  it('renders unverified chip when businessVerified is explicitly false', async () => {
    const { BusinessSellerBadge } = await import('./BusinessSellerBadge');
    render(<BusinessSellerBadge sellerType="business" businessVerified={false} />);
    expect(screen.getByText('Business')).toBeInTheDocument();
    expect(screen.queryByText('Verified Business')).toBeNull();
  });

  it('renders "Verified Business" chip when businessVerified is true', async () => {
    const { BusinessSellerBadge } = await import('./BusinessSellerBadge');
    render(<BusinessSellerBadge sellerType="business" businessVerified={true} />);
    expect(screen.getByText('Verified Business')).toBeInTheDocument();
  });

  it('does not render "Business" (plain) text when verified', async () => {
    const { BusinessSellerBadge } = await import('./BusinessSellerBadge');
    render(<BusinessSellerBadge sellerType="business" businessVerified={true} />);
    // Only the verified label should appear — not the plain one
    expect(screen.queryByText('Business')).toBeNull();
    expect(screen.getByText('Verified Business')).toBeInTheDocument();
  });

  it('verified badge has an icon element (aria-hidden)', async () => {
    const { BusinessSellerBadge } = await import('./BusinessSellerBadge');
    render(<BusinessSellerBadge sellerType="business" businessVerified={true} />);
    const icon = document.querySelector('[aria-hidden="true"]');
    expect(icon).toBeInTheDocument();
  });

  it('unverified chip has no aria-hidden icon', async () => {
    const { BusinessSellerBadge } = await import('./BusinessSellerBadge');
    render(<BusinessSellerBadge sellerType="business" />);
    // icon is only present in the verified variant
    const icon = document.querySelector('[aria-hidden="true"]');
    expect(icon).toBeNull();
  });

  it('switching from non-business to business sellerType renders the badge', async () => {
    const { BusinessSellerBadge } = await import('./BusinessSellerBadge');
    const { rerender } = render(<BusinessSellerBadge sellerType="private" />);
    expect(screen.queryByText('Business')).toBeNull();

    rerender(<BusinessSellerBadge sellerType="business" />);
    expect(screen.getByText('Business')).toBeInTheDocument();
  });

  it('switching businessVerified from false to true updates chip label', async () => {
    const { BusinessSellerBadge } = await import('./BusinessSellerBadge');
    const { rerender } = render(<BusinessSellerBadge sellerType="business" businessVerified={false} />);
    expect(screen.getByText('Business')).toBeInTheDocument();

    rerender(<BusinessSellerBadge sellerType="business" businessVerified={true} />);
    expect(screen.getByText('Verified Business')).toBeInTheDocument();
    expect(screen.queryByText('Business')).toBeNull();
  });
});
