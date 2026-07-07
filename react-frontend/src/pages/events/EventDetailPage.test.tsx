// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for EventDetailPage
 */

import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
  },
}));
import { api } from '@/lib/api';

vi.mock('@/contexts', () => ({
  useAuth: vi.fn(() => ({
    user: { id: 99, first_name: 'Alice', name: 'Alice Test' },
    isAuthenticated: true,
  })),
  useTenant: vi.fn(() => ({
    tenant: { id: 2, slug: 'test' },
    tenantPath: (p: string) => `/test${p}`,
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
  })),
  useToast: vi.fn(() => ({ success: vi.fn(), error: vi.fn(), info: vi.fn() })),
  ToastProvider: ({ children }: { children: React.ReactNode }) => <>{children}</>,

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

vi.mock('@/contexts/ToastContext', () => ({
  useToast: vi.fn(() => ({ success: vi.fn(), error: vi.fn(), info: vi.fn() })),
  ToastProvider: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));
vi.mock('@/lib/helpers', () => ({
  cn: (...classes: Array<string | false | null | undefined>) => classes.filter(Boolean).join(' '),
  resolveAvatarUrl: vi.fn((url) => url || '/default-avatar.png'),
  resolveAssetUrl: vi.fn((url) => url || null),
  resolveThumbnailUrl: vi.fn((url) => url || null),
  formatDateTime: vi.fn((value) => String(value)),
  formatDateValue: vi.fn((value) => String(value)),
  formatMonthShort: vi.fn(() => 'Jun'),
}));

// Mutable route id so a test can simulate navigating from one event to another
// (the stale-data regression needs the :id param to change mid-component-life).
const routeState = vi.hoisted(() => ({ id: '1' }));

vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual('react-router-dom');
  return {
    ...actual,
    useParams: () => ({ id: routeState.id }),
    useNavigate: () => vi.fn(),
  };
});

vi.mock('@/lib/motion', async () => {
  const { framerMotionMock } = await import('@/test/mocks');
  return framerMotionMock;
});

vi.mock('@/components/ui', async () => (await import('@/test/uiMock')).uiMock);

vi.mock('@/components/navigation', () => ({
  Breadcrumbs: ({ items }: { items: { label: string }[] }) => (
    <nav>{items.map((i) => <span key={i.label}>{i.label}</span>)}</nav>
  ),
}));

vi.mock('@/components/feedback', () => ({
  LoadingScreen: ({ message }: { message: string }) => <div data-testid="loading-screen">{message}</div>,
  EmptyState: ({ title, description }: { title: string; description?: string }) => (
    <div data-testid="empty-state">
      <h2>{title}</h2>
      {description && <p>{description}</p>}
    </div>
  ),
}));

vi.mock('@/components/location', () => ({
  LocationMapCard: () => <div data-testid="location-map" />,
}));

import { EventDetailPage } from './EventDetailPage';

const mockEvent = {
  id: 1,
  title: 'Community Garden Day',
  description: 'Join us for a community garden event',
  start_date: '2026-06-01T10:00:00Z',
  end_date: '2026-06-01T14:00:00Z',
  location: 'Dublin Park',
  latitude: 53.3498,
  longitude: -6.2603,
  organizer_id: 5,
  organizer_name: 'Alice',
  status: 'upcoming',
  rsvp_count: 10,
  max_attendees: 50,
  category: 'outdoor',
  image_url: null,
  created_at: '2026-01-01T10:00:00Z',
  attendees: [],
};

describe('EventDetailPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    routeState.id = '1';
    api.get.mockImplementation((url: string) => {
      if (url.includes('/rsvp') || url.includes('/attendees')) {
        return Promise.resolve({ success: true, data: [] });
      }
      return Promise.resolve({ success: true, data: mockEvent });
    });
  });

  it('shows loading screen initially', () => {
    api.get.mockImplementation(() => new Promise(() => {}));
    render(<EventDetailPage />);
    // The loading screen may render more than one role="status" region; assert at
    // least one carries aria-busy="true".
    const statuses = screen.getAllByRole('status');
    expect(statuses.some((el) => el.getAttribute('aria-busy') === 'true')).toBe(true);
  });

  it('renders event title after load', async () => {
    render(<EventDetailPage />);
    await waitFor(() => {
      expect(screen.getAllByText('Community Garden Day').length).toBeGreaterThanOrEqual(1);
    });
  });

  it('renders event description', async () => {
    render(<EventDetailPage />);
    await waitFor(() => {
      expect(screen.getByText('Join us for a community garden event')).toBeInTheDocument();
    });
  });

  it('renders event location', async () => {
    render(<EventDetailPage />);
    await waitFor(() => {
      expect(screen.getByText('Dublin Park')).toBeInTheDocument();
    });
  });

  it('shows error state on API error', async () => {
    api.get.mockRejectedValue(new Error('Network error'));
    render(<EventDetailPage />);
    await waitFor(() => {
      expect(screen.getAllByText(/Unable to Load Event/i).length).toBeGreaterThanOrEqual(1);
    });
  });

  it('shows error state when event not found', async () => {
    api.get.mockResolvedValue({ success: false, data: null });
    render(<EventDetailPage />);
    await waitFor(() => {
      // Component shows error message when success: false
      expect(screen.getByText('The event you are looking for does not exist')).toBeInTheDocument();
    });
  });

  it('renders RSVP buttons for authenticated non-organizer', async () => {
    render(<EventDetailPage />);
    await waitFor(() => {
      expect(screen.getAllByText('Community Garden Day').length).toBeGreaterThanOrEqual(1);
    });
    // RSVP buttons should be present (going/interested/not_going)
    const buttons = screen.getAllByRole('button');
    expect(buttons.length).toBeGreaterThan(0);
  });

  it('clears the previously-loaded event when a reload (id change) fails — no stale data', async () => {
    // Regression: loadEvent set `error` on a failed (re)load but never cleared the
    // already-loaded `event`, so the `error && !event` render guard stayed false and
    // the PRIOR event rendered under the new URL. Live-verified on the running app:
    // navigating from a loaded event to a 500ing event id left the stale event on
    // screen; now it shows the error screen instead.
    const { rerender } = render(<EventDetailPage />);
    await waitFor(() => {
      expect(screen.getAllByText('Community Garden Day').length).toBeGreaterThanOrEqual(1);
    });

    // Navigate to a different event whose fetch fails.
    routeState.id = '2';
    api.get.mockImplementation((url: string) => {
      if (url.includes('/rsvp') || url.includes('/attendees')) {
        return Promise.resolve({ success: true, data: [] });
      }
      return Promise.resolve({ success: false, data: null });
    });
    rerender(<EventDetailPage />);

    await waitFor(() => {
      expect(screen.getByText('The event you are looking for does not exist')).toBeInTheDocument();
    });
    // The stale event-1 title must be gone (pre-fix it remained on screen).
    expect(screen.queryByText('Community Garden Day')).not.toBeInTheDocument();
  });
});
