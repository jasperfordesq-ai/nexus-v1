// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

const { mockApi, mockToast } = vi.hoisted(() => ({
  mockApi: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() },
  mockToast: { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() },
}));

vi.mock('@/lib/api', () => ({ api: mockApi, default: mockApi }));
vi.mock('@/components/seo/PageMeta', () => ({ PageMeta: () => null }));
vi.mock('@/components/navigation/Breadcrumbs', () => ({ Breadcrumbs: () => null }));
vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));
vi.mock('@/contexts', () => createMockContexts({
  useToast: () => mockToast,
  useTenant: () => ({
    tenant: { id: 2, name: 'Test', slug: 'test' },
    tenantPath: (path: string) => `/test${path}`,
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
  }),
}));

import { MarketplaceReportsPage } from './MarketplaceReportsPage';

describe('MarketplaceReportsPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockApi.get.mockResolvedValue({
      success: true,
      data: [{
        id: 77,
        marketplace_listing_id: 9,
        reason: 'unsafe',
        description: 'Safety concern',
        status: 'under_review',
        viewer_role: 'seller',
        listing: { id: 9, title: 'Workshop tool' },
        created_at: '2026-07-12T10:00:00Z',
      }],
    });
  });

  it('lists privacy-safe reports affecting the signed-in user', async () => {
    render(<MarketplaceReportsPage />);

    await waitFor(() => expect(mockApi.get).toHaveBeenCalledWith('/v2/marketplace/reports'));
    expect(await screen.findByText('Workshop tool')).toBeInTheDocument();
    expect(screen.getByText('Affects your listing')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /report #77/i })).toHaveAttribute('href', '/test/marketplace/reports/77');
  });
});
