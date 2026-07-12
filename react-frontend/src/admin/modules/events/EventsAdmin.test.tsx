// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { act, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createMockContexts } from '@/test/mock-contexts';
import { getFormattingLocale } from '@/lib/helpers';
import { renderEventRoute } from '@/test/events-test-harness';

const { mockApi, mockToast } = vi.hoisted(() => ({
  mockApi: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
  mockToast: {
    success: vi.fn(),
    error: vi.fn(),
    info: vi.fn(),
    warning: vi.fn(),
    showToast: vi.fn(),
  },
}));

vi.mock('@/lib/api', () => ({ api: mockApi }));

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToast,
    useTenant: () => ({
      tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
      tenantPath: (path: string) => `/test${path}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  }),
);

vi.mock('../../AdminMetaContext', () => ({ useAdminPageMeta: vi.fn() }));

import { EventsAdmin } from './EventsAdmin';

const MOCK_EVENT = {
  id: 1,
  title: 'Community Swap Meet',
  start_date: '2030-07-01T10:00:00Z',
  location: 'Town Hall',
  organizer_name: 'Alice Admin',
  status: 'published',
  publication_state: 'published' as const,
  operational_state: 'scheduled' as const,
  lifecycle_version: 2,
  attendees_count: 20,
  max_attendees: 50,
  created_at: '2026-06-01T00:00:00Z',
};

const MOCK_EVENT_2 = {
  id: 2,
  title: 'Gardening Club',
  start_date: '2030-08-01T09:00:00Z',
  location: null,
  organizer_name: null,
  status: 'cancelled',
  publication_state: 'published' as const,
  operational_state: 'cancelled' as const,
  lifecycle_version: 4,
  attendees_count: 5,
  max_attendees: null,
  created_at: '2026-06-02T00:00:00Z',
};

function eventListResponse(events = [MOCK_EVENT]) {
  return {
    success: true,
    data: events,
    meta: { total: events.length },
  };
}

async function renderLoadedAdmin(events = [MOCK_EVENT]) {
  mockApi.get.mockResolvedValue(eventListResponse(events));
  renderEventRoute(<EventsAdmin />, { route: '/admin/events', path: '/admin/events' });

  if (events.length > 0) {
    await screen.findByText(events[0].title);
  } else {
    await screen.findByRole('heading', { name: 'No events found' });
  }
}

describe('EventsAdmin', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows an accessible loading status while fetching', async () => {
    let resolveRequest!: (value: ReturnType<typeof eventListResponse>) => void;
    const request = new Promise<ReturnType<typeof eventListResponse>>((resolve) => {
      resolveRequest = resolve;
    });
    mockApi.get.mockReturnValue(request);

    renderEventRoute(<EventsAdmin />, { route: '/admin/events', path: '/admin/events' });

    expect(screen.getByRole('status', { name: 'Loading' })).toHaveAttribute('aria-busy', 'true');

    await act(async () => {
      resolveRequest(eventListResponse([]));
    });
    await screen.findByRole('heading', { name: 'No events found' });
  });

  it('renders event rows after data loads', async () => {
    await renderLoadedAdmin([MOCK_EVENT, MOCK_EVENT_2]);

    const firstRowHeader = screen.getByRole('rowheader', { name: 'Community Swap Meet' });
    expect(firstRowHeader).toBeInTheDocument();
    expect(screen.getByRole('rowheader', { name: 'Gardening Club' })).toBeInTheDocument();
    const firstRow = firstRowHeader.closest('[role="row"]');
    expect(firstRow).not.toBeNull();
    expect(within(firstRow!).getByRole('link', { name: 'View Event' })).toHaveAttribute(
      'href',
      '/test/events/1',
    );
  });

  it('renders an all-day event on the date defined by its event timezone', async () => {
    const start = '2030-07-01T00:30:00Z';
    await renderLoadedAdmin([{
      ...MOCK_EVENT,
      start_date: start,
      timezone: 'America/Los_Angeles',
      all_day: true,
    }]);

    const expectedDate = new Date(start).toLocaleDateString(getFormattingLocale(), {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      timeZone: 'America/Los_Angeles',
    });
    expect(screen.getByText(expectedDate)).toBeInTheDocument();
    expect(screen.getByText('All day')).toBeInTheDocument();
  });

  it('shows an accessible empty state when no events are returned', async () => {
    await renderLoadedAdmin([]);

    expect(screen.getByRole('heading', { name: 'No events found' })).toBeInTheDocument();
    expect(screen.getByText('No events match your current filters')).toBeInTheDocument();
  });

  it('shows a toast error when the fetch rejects', async () => {
    mockApi.get.mockRejectedValue(new Error('Network error'));
    renderEventRoute(<EventsAdmin />, { route: '/admin/events', path: '/admin/events' });

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalledWith('Failed to load events');
    });
    await screen.findByRole('heading', { name: 'No events found' });
  });

  it('archives an event instead of presenting destructive deletion', async () => {
    const user = userEvent.setup();
    mockApi.post.mockResolvedValue({ success: true });
    await renderLoadedAdmin();

    await user.click(screen.getByRole('button', { name: 'Actions for Community Swap Meet' }));
    await user.click(await screen.findByRole('menuitem', { name: 'Archive' }));
    const dialog = await screen.findByRole('dialog', { name: 'Archive: Community Swap Meet' });

    await user.click(within(dialog).getByRole('button', { name: 'Archive' }));

    await waitFor(() => {
      expect(mockApi.post).toHaveBeenCalledWith('/v2/admin/events/1/archive', {});
    });
    expect(mockToast.success).toHaveBeenCalledWith('Archive completed successfully');
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
  });

  it('requires and submits a cancellation reason', async () => {
    const user = userEvent.setup();
    mockApi.post.mockResolvedValue({ success: true });
    await renderLoadedAdmin();

    await user.click(screen.getByRole('button', { name: 'Actions for Community Swap Meet' }));
    await user.click(await screen.findByRole('menuitem', { name: 'Cancel event' }));
    const dialog = await screen.findByRole('dialog', { name: 'Cancel event: Community Swap Meet' });
    const confirm = within(dialog).getByRole('button', { name: 'Cancel event' });

    expect(confirm).toBeDisabled();
    await user.type(within(dialog).getByRole('textbox', { name: 'Reason' }), 'Venue unavailable');

    await user.click(confirm);

    await waitFor(() => {
      expect(mockApi.post).toHaveBeenCalledWith('/v2/admin/events/1/cancel', {
        reason: 'Venue unavailable',
      });
    });
    expect(mockToast.success).toHaveBeenCalledWith('Cancel event completed successfully');
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
  });

  it('finishes loading and shows the empty state for success:false responses', async () => {
    mockApi.get.mockResolvedValue({ success: false, data: null });
    renderEventRoute(<EventsAdmin />, { route: '/admin/events', path: '/admin/events' });

    expect(await screen.findByRole('heading', { name: 'No events found' })).toBeInTheDocument();
    expect(screen.queryByRole('status', { name: 'Loading' })).not.toBeInTheDocument();
  });
});
