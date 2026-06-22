// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
  },
}));

vi.mock('@/contexts', () =>
  createMockContexts({
    useTenant: () => ({
      tenant: { id: 2, name: 'hOUR Timebank', slug: 'hour-timebank' },
      tenantPath: (p: string) => `/hour-timebank${p}`,
      tenantSlug: 'hour-timebank',
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  }),
);

// Mock react-router-dom — keep BrowserRouter working but override useParams
vi.mock('react-router-dom', async (importOriginal) => {
  const actual = await importOriginal<typeof import('react-router-dom')>();
  return {
    ...actual,
    useParams: vi.fn(() => ({ code: 'VALID-TOKEN-123' })),
    useNavigate: vi.fn(() => vi.fn()),
  };
});

vi.mock('@/hooks', async (importOriginal) => {
  const actual = await importOriginal<Record<string, unknown>>();
  return { ...actual, usePageTitle: vi.fn() };
});

import { api } from '@/lib/api';
import { useParams } from 'react-router-dom';
import InviteRedemptionPage from './InviteRedemptionPage';

const mockGet = vi.mocked(api.get);
const mockUseParams = vi.mocked(useParams);

const VALID_RESPONSE = {
  valid: true,
  expired: false,
  already_used: false,
  tenant_name: 'hOUR Timebank',
  caring_community_enabled: true,
};

const EXPIRED_RESPONSE = {
  valid: false,
  expired: true,
  already_used: false,
  tenant_name: 'hOUR Timebank',
  caring_community_enabled: true,
};

const USED_RESPONSE = {
  valid: false,
  expired: false,
  already_used: true,
  tenant_name: 'hOUR Timebank',
  caring_community_enabled: true,
};

const INVALID_RESPONSE = {
  valid: false,
  expired: false,
  already_used: false,
  tenant_name: '',
  caring_community_enabled: false,
};

describe('InviteRedemptionPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockUseParams.mockReturnValue({ code: 'VALID-TOKEN-123' });
  });

  it('shows a loading spinner on initial render', () => {
    // Hang the promise so loading stays visible
    mockGet.mockReturnValueOnce(new Promise(() => {}));

    render(<InviteRedemptionPage />);

    // The page shows role="status" aria-busy="true" while loading.
    // ToastProvider also emits role="status" so match the one with aria-busy.
    const statusEls = screen.getAllByRole('status');
    const busyEl = statusEls.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busyEl).toBeInTheDocument();
  });

  it('renders the valid invite state with CTA button', async () => {
    mockGet.mockResolvedValueOnce({ data: VALID_RESPONSE });

    render(<InviteRedemptionPage />);

    await waitFor(() => {
      // i18n key invite.valid.title resolves to a heading in test mode
      expect(screen.getByRole('heading', { level: 1 })).toBeInTheDocument();
    });

    // CTA button should be present
    expect(screen.getByRole('button')).toBeInTheDocument();

    // API should be called with the code from params
    expect(mockGet).toHaveBeenCalledWith(
      '/v2/caring-community/invite/VALID-TOKEN-123',
    );
  });

  it('renders the expired invite error card', async () => {
    mockGet.mockResolvedValueOnce({ data: EXPIRED_RESPONSE });

    render(<InviteRedemptionPage />);

    await waitFor(() => {
      expect(screen.getByRole('heading', { level: 1 })).toBeInTheDocument();
    });

    // Should NOT show the join CTA button
    expect(screen.queryByRole('button')).not.toBeInTheDocument();
  });

  it('renders the already-used invite error card', async () => {
    mockGet.mockResolvedValueOnce({ data: USED_RESPONSE });

    render(<InviteRedemptionPage />);

    await waitFor(() => {
      expect(screen.getByRole('heading', { level: 1 })).toBeInTheDocument();
    });

    expect(screen.queryByRole('button')).not.toBeInTheDocument();
  });

  it('renders the invalid invite error card', async () => {
    mockGet.mockResolvedValueOnce({ data: INVALID_RESPONSE });

    render(<InviteRedemptionPage />);

    await waitFor(() => {
      expect(screen.getByRole('heading', { level: 1 })).toBeInTheDocument();
    });

    expect(screen.queryByRole('button')).not.toBeInTheDocument();
  });

  it('renders the network error card with a retry button on API failure', async () => {
    mockGet.mockRejectedValueOnce(new Error('Network error'));

    render(<InviteRedemptionPage />);

    await waitFor(() => {
      expect(screen.getByRole('heading', { level: 1 })).toBeInTheDocument();
    });

    // Error state shows a retry button
    expect(screen.getByRole('button')).toBeInTheDocument();
  });

  it('handles missing invite code in params gracefully', async () => {
    mockUseParams.mockReturnValue({ code: undefined });
    // Should immediately set status to invalid without making an API call
    render(<InviteRedemptionPage />);

    await waitFor(() => {
      expect(screen.getByRole('heading', { level: 1 })).toBeInTheDocument();
    });

    // No API call should be made for a missing code
    expect(mockGet).not.toHaveBeenCalled();
  });

  it('encodes special characters in the invite code URL segment', async () => {
    const specialCode = 'abc+def/ghi=';
    mockUseParams.mockReturnValue({ code: specialCode });
    mockGet.mockResolvedValueOnce({ data: VALID_RESPONSE });

    render(<InviteRedemptionPage />);

    await waitFor(() => {
      expect(mockGet).toHaveBeenCalledWith(
        `/v2/caring-community/invite/${encodeURIComponent(specialCode)}`,
      );
    });
  });
});
