// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for AvailabilityGrid component
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import { api } from '@/lib/api';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

const mockToast = {
  success: vi.fn(),
  error: vi.fn(),
  info: vi.fn(),
  warning: vi.fn(),
};

vi.mock('@/contexts', () => ({
  useToast: vi.fn(() => mockToast),
  useAuth: vi.fn(() => ({
    isAuthenticated: true,
    user: { id: 1, name: 'Test User', role: 'user' },
    login: vi.fn(),
    logout: vi.fn(),
    register: vi.fn(),
    updateUser: vi.fn(),
    refreshUser: vi.fn(),
    status: 'idle',
    error: null,
  })),
  useTenant: vi.fn(() => ({
    tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
    branding: { name: 'Test', logo_url: null },
    tenantSlug: 'test',
    tenantPath: (p: string) => '/test' + p,
    isLoading: false,
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
  })),
  useTheme: () => ({ resolvedTheme: 'light', toggleTheme: vi.fn(), theme: 'system', setTheme: vi.fn() }),
  useNotifications: () => ({ unreadCount: 0, counts: {}, notifications: [], markAsRead: vi.fn(), markAllAsRead: vi.fn(), hasMore: false, loadMore: vi.fn(), isLoading: false, refresh: vi.fn() }),
  usePusher: () => ({ channel: null, isConnected: false }),
  usePusherOptional: () => null,
  useCookieConsent: () => ({ consent: null, showBanner: false, openPreferences: vi.fn(), resetConsent: vi.fn(), saveConsent: vi.fn(), hasConsent: vi.fn(() => true), updateConsent: vi.fn() }),
  readStoredConsent: () => null,
  useMenuContext: () => ({ headerMenus: [], mobileMenus: [], hasCustomMenus: false }),
  useFeature: vi.fn(() => true),
  useModule: vi.fn(() => true),
}));

vi.mock('@/lib/logger', () => ({
  logError: vi.fn(),
}));

import { AvailabilityGrid } from '../AvailabilityGrid';

const mockAvailabilityData = {
  weekly: [
    { day_of_week: 0, start_time: '09:00', end_time: '17:00', is_available: true },
    { day_of_week: 2, start_time: '10:00', end_time: '14:00', is_available: true },
  ],
  timezone: 'Europe/Dublin',
};

describe('AvailabilityGrid', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders loading spinner initially', () => {
    vi.mocked(api.get).mockImplementation(() => new Promise(() => {}));

    render(<AvailabilityGrid editable={true} />);
    expect(screen.getByLabelText('Loading')).toBeInTheDocument();
  });

  it('renders the grid with day headers after loading', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({
      success: true,
      data: mockAvailabilityData,
    });

    render(<AvailabilityGrid editable={true} />);

    await waitFor(() => {
      expect(screen.getByText('Mon')).toBeInTheDocument();
      expect(screen.getByText('Tue')).toBeInTheDocument();
      expect(screen.getByText('Wed')).toBeInTheDocument();
      expect(screen.getByText('Thu')).toBeInTheDocument();
      expect(screen.getByText('Fri')).toBeInTheDocument();
      expect(screen.getByText('Sat')).toBeInTheDocument();
      expect(screen.getByText('Sun')).toBeInTheDocument();
    });
  });

  it('renders legend with Available and Unavailable labels', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({
      success: true,
      data: mockAvailabilityData,
    });

    render(<AvailabilityGrid editable={true} />);

    await waitFor(() => {
      expect(screen.getByText('Available')).toBeInTheDocument();
      expect(screen.getByText('Unavailable')).toBeInTheDocument();
    });
  });

  it('renders the header when editable', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({
      success: true,
      data: mockAvailabilityData,
    });

    render(<AvailabilityGrid editable={true} />);

    await waitFor(() => {
      expect(screen.getByText('Set Your Availability')).toBeInTheDocument();
    });
  });

  it('does not render header when not editable', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({
      success: true,
      data: mockAvailabilityData,
    });

    render(<AvailabilityGrid editable={false} />);

    await waitFor(() => {
      expect(screen.queryByText('Set Your Availability')).not.toBeInTheDocument();
    });
  });

  it('returns null when not editable and no availability data', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({
      success: true,
      data: { weekly: [] },
    });

    render(<AvailabilityGrid editable={false} />);

    await waitFor(() => {
      // Component returns null, so no day headers should appear
      expect(screen.queryByText('Mon')).not.toBeInTheDocument();
      expect(screen.queryByText('Available')).not.toBeInTheDocument();
    });
  });

  it('renders error state on API failure', async () => {
    vi.mocked(api.get).mockRejectedValueOnce(new Error('Network error'));

    render(<AvailabilityGrid editable={true} />);

    await waitFor(() => {
      expect(screen.getByText('Failed to load availability')).toBeInTheDocument();
      expect(screen.getByText('Retry')).toBeInTheDocument();
    });
  });

  it('fetches data from user-specific endpoint when userId provided', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({
      success: true,
      data: mockAvailabilityData,
    });

    render(<AvailabilityGrid userId={42} editable={false} />);

    await waitFor(() => {
      expect(api.get).toHaveBeenCalledWith('/v2/users/42/availability');
    });
  });

  it('fetches data from /me endpoint when no userId provided', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({
      success: true,
      data: mockAvailabilityData,
    });

    render(<AvailabilityGrid editable={true} />);

    await waitFor(() => {
      expect(api.get).toHaveBeenCalledWith('/v2/users/me/availability');
    });
  });

  it('renders time slots in the grid', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({
      success: true,
      data: mockAvailabilityData,
    });

    render(<AvailabilityGrid editable={true} />);

    await waitFor(() => {
      expect(screen.getByText('09:00')).toBeInTheDocument();
      expect(screen.getByText('12:00')).toBeInTheDocument();
    });
  });

  it('renders description text when editable', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({
      success: true,
      data: mockAvailabilityData,
    });

    render(<AvailabilityGrid editable={true} />);

    await waitFor(() => {
      expect(screen.getByText(/Click on time slots/)).toBeInTheDocument();
    });
  });
});
