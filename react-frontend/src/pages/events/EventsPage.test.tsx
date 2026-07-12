// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { beforeEach, describe, expect, it, vi } from 'vitest';
import { screen, waitFor } from '@testing-library/react';
import { getFormattingLocale } from '@/lib/helpers';
import { createCanonicalEventFixture, renderEventRoute } from '@/test/events-test-harness';

const { mockApi, mockToast } = vi.hoisted(() => ({
  mockApi: {
    get: vi.fn(),
    post: vi.fn(),
  },
  mockToast: {
    success: vi.fn(),
    error: vi.fn(),
    info: vi.fn(),
    warning: vi.fn(),
  },
}));

vi.mock('@/lib/api', () => ({
  api: mockApi,
  tokenManager: { getTenantId: vi.fn() },
}));

vi.mock('@/contexts/AuthContext', () => ({
  useAuth: () => ({
    user: { id: 1, first_name: 'Test' },
    isAuthenticated: true,
  }),
}));

vi.mock('@/contexts/ToastContext', () => ({
  useToast: () => mockToast,
}));

vi.mock('@/contexts/TenantContext', () => ({
  useTenant: () => ({
    tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
    tenantPath: (path: string) => `/test${path}`,
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
  }),
}));

vi.mock('@/hooks/usePageTitle', () => ({ usePageTitle: vi.fn() }));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));
vi.mock('@/components/proximity/ProximityFilter', () => ({
  ProximityFilter: () => null,
}));
vi.mock('./components/CalendarSubscriptionPanel', () => ({
  CalendarSubscriptionPanel: () => null,
}));

import { EventsPage } from './EventsPage';

async function renderLoadedEventsPage() {
  renderEventRoute(<EventsPage />);

  await waitFor(() => {
    expect(mockApi.get).toHaveBeenCalledWith(
      expect.stringContaining('/v2/events?'),
      expect.objectContaining({ headers: expect.any(Headers), signal: expect.any(AbortSignal) }),
    );
  });
  await screen.findByRole('heading', { name: 'No events found' });
}

describe('EventsPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockApi.get.mockResolvedValue({
      success: true,
      data: [],
      meta: { has_more: false, total_items: 0 },
    });
  });

  it('renders the Events page heading', async () => {
    await renderLoadedEventsPage();

    expect(screen.getByRole('heading', { level: 1, name: 'Events' })).toBeInTheDocument();
  });

  it('shows the page description', async () => {
    await renderLoadedEventsPage();

    expect(screen.getAllByText(/Find local workshops, gatherings, and community events near you/i).length)
      .toBeGreaterThan(0);
  });

  it('exposes an accessible event search field', async () => {
    await renderLoadedEventsPage();

    expect(screen.getByRole('searchbox', { name: 'Search events' })).toBeInTheDocument();
  });

  it('loads the structured step-free venue filter from the URL', async () => {
    renderEventRoute(<EventsPage />, { route: '/test/events?step_free=yes' });

    await waitFor(() => {
      expect(mockApi.get).toHaveBeenCalledWith(
        expect.stringContaining('step_free=yes'),
        expect.objectContaining({ headers: expect.any(Headers), signal: expect.any(AbortSignal) }),
      );
    });

    expect(screen.getByRole('button', { name: /Step-free venue access/ })).toBeInTheDocument();
    expect(screen.getAllByText('Step-free access confirmed').length).toBeGreaterThan(0);
  });

  it('shows tenant-aware Create Event links for authenticated users', async () => {
    await renderLoadedEventsPage();

    const createLinks = screen.getAllByRole('link', { name: 'Create Event' });
    expect(createLinks.length).toBeGreaterThan(0);
    expect(createLinks[0]).toHaveAttribute('href', '/test/events/create');
  });

  it('keeps card day and time aligned to the event timezone', async () => {
    const event = createCanonicalEventFixture({
      schedule: {
        ...createCanonicalEventFixture().schedule,
        start_at: '2026-07-11T00:30:00+00:00',
        end_at: '2026-07-11T01:30:00+00:00',
        timezone: 'America/Los_Angeles',
        all_day: false,
      },
    });
    mockApi.get.mockResolvedValue({
      success: true,
      data: [event],
      meta: { has_more: false, total_items: 1 },
    });

    renderEventRoute(<EventsPage />);
    await screen.findByRole('heading', { name: 'Community Garden Day' });

    const locale = getFormattingLocale();
    const start = new Date('2026-07-11T00:30:00+00:00');
    const expectedTime = start.toLocaleString(locale, {
      hour: '2-digit',
      minute: '2-digit',
      timeZone: 'America/Los_Angeles',
      timeZoneName: 'short',
    });
    const expectedDay = start.toLocaleDateString(locale, {
      day: 'numeric',
      timeZone: 'America/Los_Angeles',
    });

    expect(screen.getByText(expectedTime)).toBeInTheDocument();
    expect(screen.getByText(expectedDay)).toBeInTheDocument();
  });
});
