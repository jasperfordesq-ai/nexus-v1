// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

vi.mock('@/lib/api', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
  },
}));

vi.mock('@/lib/logger', () => ({
  logError: vi.fn(),
}));

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
    useTenant: () => ({
      tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      tenantSlug: 'test',
      hasFeature: vi.fn((f: string) => f === 'caring_community'),
      hasModule: vi.fn(() => true),
    }),
  }),
);

import api from '@/lib/api';
import EmergencyAlertBanner from './EmergencyAlertBanner';

const mockApi = api as { get: ReturnType<typeof vi.fn>; post: ReturnType<typeof vi.fn> };

const ACTIVE_ALERT = {
  id: 42,
  title: 'Gas leak in district 3',
  body: 'Please evacuate immediately.',
  severity: 'danger' as const,
  expires_at: null,
  created_at: '2026-06-21T10:00:00Z',
};

const INFO_ALERT = {
  id: 7,
  title: 'Community notice',
  body: 'The weekly market is postponed.',
  severity: 'info' as const,
  expires_at: null,
  created_at: '2026-06-21T08:00:00Z',
};

describe('EmergencyAlertBanner', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders nothing while alerts list is empty', async () => {
    mockApi.get.mockResolvedValueOnce({ data: { data: [] } });

    render(<EmergencyAlertBanner />);

    await waitFor(() => {
      expect(mockApi.get).toHaveBeenCalledWith(
        '/v2/caring-community/emergency-alerts',
      );
    });

    // Nothing rendered when no active alerts
    expect(screen.queryByRole('region')).not.toBeInTheDocument();
  });

  it('shows an active danger alert with title and body', async () => {
    mockApi.get.mockResolvedValueOnce({ data: { data: [ACTIVE_ALERT] } });

    render(<EmergencyAlertBanner />);

    await waitFor(() => {
      expect(screen.getByText('Gas leak in district 3')).toBeInTheDocument();
    });
    expect(screen.getByText('Please evacuate immediately.')).toBeInTheDocument();
    // The region wrapper is present when there are active alerts
    expect(screen.getByRole('region')).toBeInTheDocument();
  });

  it('shows an info alert', async () => {
    mockApi.get.mockResolvedValueOnce({ data: { data: [INFO_ALERT] } });

    render(<EmergencyAlertBanner />);

    await waitFor(() => {
      expect(screen.getByText('Community notice')).toBeInTheDocument();
    });
    expect(screen.getByText('The weekly market is postponed.')).toBeInTheDocument();
  });

  it('renders multiple alerts when more than one is active', async () => {
    mockApi.get.mockResolvedValueOnce({
      data: { data: [ACTIVE_ALERT, INFO_ALERT] },
    });

    render(<EmergencyAlertBanner />);

    await waitFor(() => {
      expect(screen.getByText('Gas leak in district 3')).toBeInTheDocument();
    });
    expect(screen.getByText('Community notice')).toBeInTheDocument();
    // Both alert titles are visible — each alert div has role="alert"
    // (ToastProvider also renders a role="alert" region, so use queryAllByRole
    // and check at least 2 alert elements inside our region)
    const region = screen.getByRole('region');
    const alertDivs = region.querySelectorAll('[role="alert"]');
    expect(alertDivs).toHaveLength(2);
  });

  it('dismisses an alert optimistically when the dismiss button is clicked', async () => {
    mockApi.get.mockResolvedValueOnce({ data: { data: [ACTIVE_ALERT] } });
    mockApi.post.mockResolvedValueOnce({ success: true });

    render(<EmergencyAlertBanner />);

    await waitFor(() => {
      expect(screen.getByText('Gas leak in district 3')).toBeInTheDocument();
    });

    const dismissBtn = screen.getByRole('button');
    fireEvent.click(dismissBtn);

    // Alert should disappear immediately (optimistic)
    await waitFor(() => {
      expect(screen.queryByText('Gas leak in district 3')).not.toBeInTheDocument();
    });

    // Dismiss API call should have been made
    await waitFor(() => {
      expect(mockApi.post).toHaveBeenCalledWith(
        `/v2/caring-community/emergency-alerts/${ACTIVE_ALERT.id}/dismiss`,
      );
    });
  });

  it('also hides the region wrapper when all alerts are dismissed', async () => {
    mockApi.get.mockResolvedValueOnce({ data: { data: [ACTIVE_ALERT] } });
    mockApi.post.mockResolvedValueOnce({ success: true });

    render(<EmergencyAlertBanner />);

    await waitFor(() => {
      expect(screen.getByText('Gas leak in district 3')).toBeInTheDocument();
    });

    fireEvent.click(screen.getByRole('button'));

    await waitFor(() => {
      expect(screen.queryByRole('region')).not.toBeInTheDocument();
    });
  });

  it('handles api.get returning a top-level array (non-envelope format)', async () => {
    mockApi.get.mockResolvedValueOnce({ data: [ACTIVE_ALERT] });

    render(<EmergencyAlertBanner />);

    await waitFor(() => {
      expect(screen.getByText('Gas leak in district 3')).toBeInTheDocument();
    });
  });

  it('gracefully hides on API error without throwing', async () => {
    mockApi.get.mockRejectedValueOnce(new Error('Network error'));

    render(<EmergencyAlertBanner />);

    // Should not throw; no alerts visible
    await waitFor(() => {
      expect(mockApi.get).toHaveBeenCalled();
    });
    expect(screen.queryByRole('region')).not.toBeInTheDocument();
  });
});
