// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import userEvent from '@testing-library/user-event';
import { createMockContexts } from '@/test/mock-contexts';

const { mockApi, mockToast } = vi.hoisted(() => ({
  mockApi: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() },
  mockToast: { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() },
}));

vi.mock('@/lib/api', () => ({ api: mockApi, default: mockApi }));
vi.mock('@/components/seo/PageMeta', () => ({ PageMeta: () => null }));
vi.mock('@/components/navigation/Breadcrumbs', () => ({ Breadcrumbs: () => null }));
vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));
vi.mock('react-router-dom', async (importOriginal) => {
  const original = await importOriginal<typeof import('react-router-dom')>();
  return { ...original, useParams: () => ({ id: '42' }) };
});
vi.mock('@/contexts', () => createMockContexts({
  useToast: () => mockToast,
  useTenant: () => ({
    tenant: { id: 2, name: 'Test', slug: 'test' },
    tenantPath: (path: string) => `/test${path}`,
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
  }),
}));

import { MarketplaceReportPage } from './MarketplaceReportPage';

const report = {
  id: 42,
  marketplace_listing_id: 8,
  reason: 'misleading',
  description: 'The listing description omitted important information.',
  status: 'action_taken',
  can_appeal: true,
  action_taken: 'listing_removed',
  resolution_reason: 'The description was materially misleading.',
  listing: { id: 8, title: 'Vintage chair', status: 'removed' },
  created_at: '2026-07-12T10:00:00Z',
};

describe('MarketplaceReportPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockApi.get.mockResolvedValue({ success: true, data: report });
  });

  it('loads the authenticated report and shows its decision and appeal form', async () => {
    render(<MarketplaceReportPage />);

    await waitFor(() => expect(mockApi.get).toHaveBeenCalledWith('/v2/marketplace/reports/42'));
    expect(await screen.findByText('Vintage chair')).toBeInTheDocument();
    expect(screen.getByText('The description was materially misleading.')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /submit appeal/i })).toBeInTheDocument();
  });

  it('submits an eligible appeal using the documented payload', async () => {
    const user = userEvent.setup();
    mockApi.post.mockResolvedValue({ success: true, data: { ...report, status: 'appealed', can_appeal: false } });
    render(<MarketplaceReportPage />);

    const field = await screen.findByLabelText(/appeal explanation/i);
    await user.type(field, 'Please reconsider this decision using the attached evidence.');
    await user.click(screen.getByRole('button', { name: /submit appeal/i }));

    await waitFor(() => expect(mockApi.post).toHaveBeenCalledWith(
      '/v2/marketplace/reports/42/appeal',
      { appeal_text: 'Please reconsider this decision using the attached evidence.' },
    ));
    expect(mockToast.success).toHaveBeenCalled();
  });
});
