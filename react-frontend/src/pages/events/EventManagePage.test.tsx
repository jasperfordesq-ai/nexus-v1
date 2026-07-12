// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createCanonicalEventFixture, renderEventRoute } from '@/test/events-test-harness';
import { EventManagePage } from './EventManagePage';

const { mockApi, mockToast } = vi.hoisted(() => ({
  mockApi: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
    download: vi.fn(),
  },
  mockToast: {
    success: vi.fn(),
    error: vi.fn(),
    info: vi.fn(),
    warning: vi.fn(),
  },
}));

vi.mock('@/lib/api', () => ({ api: mockApi }));
vi.mock('@/contexts/ToastContext', () => ({ useToast: () => mockToast }));
vi.mock('@/contexts/TenantContext', () => ({
  useTenant: () => ({
    tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
    tenantPath: (path: string) => `/test${path}`,
  }),
}));
vi.mock('@/hooks/usePageTitle', () => ({ usePageTitle: vi.fn() }));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));
vi.mock('./components/EventOfflineCheckinWorkspace', () => ({
  EventOfflineCheckinWorkspace: ({ eventId }: { eventId: number }) => (
    <section>
      <h2>Offline QR check-in</h2>
      <h3>Manual check-in</h3>
      <span>event-{eventId}</span>
    </section>
  ),
}));
vi.mock('./components/EventRegistrationWorkspace', () => ({
  EventRegistrationWorkspace: ({ eventId }: { eventId: number }) => (
    <section aria-label="Registration management workspace">registration-{eventId}</section>
  ),
}));

const manageableEvent = createCanonicalEventFixture({
  permissions: {
    ...createCanonicalEventFixture().permissions,
    edit: true,
    cancel: true,
    manage_people: true,
    check_in: true,
    manage_agenda: true,
    manage_staff: true,
    manage_registration: true,
    manage_finance: true,
    reconcile_tickets: true,
    transfer_ownership: true,
  },
});

function renderManagePage(route = '/test/events/1/manage') {
  return renderEventRoute(<EventManagePage />, {
    route,
    path: '/:tenantSlug/events/:id/manage/:section?',
  });
}

describe('EventManagePage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockApi.get.mockImplementation((url: string) => {
      if (url === '/v2/events/1') return Promise.resolve({ success: true, data: manageableEvent });
      if (url === '/v2/events/1/staff?include_inactive=true') {
        return Promise.resolve({ success: true, data: [] });
      }
      if (url === '/v2/events/1/federation-status') {
        return Promise.resolve({
          success: true,
          data: {
            contract_version: 1,
            event_id: 1,
            federation_version: 2,
            visibility: 'listed',
            configured_partners: 0,
            recipient_partners: 0,
            health: 'not_configured',
            counts: { pending: 0, retry: 0, processing: 0, delivered: 0, dead_letter: 0 },
            partners: [],
            generated_at: '2030-01-01T12:00:00Z',
          },
        });
      }
      if (url === '/v2/event-templates?status=active&per_page=20') {
        return Promise.resolve({
          success: true,
          data: [],
          meta: { per_page: 20, next_cursor: null, has_more: false },
        });
      }
      if (url === '/v2/events/1/analytics') {
        const rate = { numerator: 0, denominator: 0, basis_points: null, suppressed: false };
        const deliveries = { pending: 0, delivered: 0, suppressed: 0, failed: 0, dead_lettered: 0 };
        return Promise.resolve({
          success: true,
          data: {
            contract_version: 1,
            event_id: 1,
            event_title: 'Community Garden Day',
            generated_at: '2030-01-01T12:00:00Z',
            privacy_threshold: 5,
            registration: {
              capacity_limit: 20,
              confirmed: 12,
              pending: 0,
              invited: 0,
              declined: 0,
              cancelled: 0,
              remaining: 8,
              completion_transitions: 12,
              cancellation_transitions: 0,
            },
            invitation: { available: true, issued: 0, accepted: 0, revoked: 0, expired: 0, conversion: rate },
            waitlist: {
              current_waiting: 0,
              current_offered: 0,
              joined: 0,
              offered: 0,
              accepted: 0,
              expired: 0,
              cancelled: 0,
              conversion: rate,
            },
            attendance: { checked_in: 0, checked_out: 0, attended: 0, no_show: 0, attendance_rate: rate },
            tickets: {
              available: true,
              redacted: false,
              confirmed_entitlements: 0,
              confirmed_units: 0,
              cancelled_units: 0,
              confirmed_credit_value: '0.00',
            },
            credits: {
              completed_claims: 0,
              completed_amount: '0.00',
              pending_claims: 0,
              failed_claims: 0,
              reversed_claims: 0,
            },
            communications: { ...deliveries, delivery_rate: rate, by_channel: {} },
            optional_funnel: {
              event_views: { value: null, suppressed: true },
              registration_starts: { value: null, suppressed: true },
              start_to_registration_conversion: { ...rate, suppressed: true },
            },
            safeguarding: { available: true, guardian_consents: { value: null, suppressed: true } },
          },
        });
      }
      if (url === '/v2/events/1/tickets') {
        return Promise.resolve({
          success: true,
          data: {
            contract_version: 1,
            event_id: 1,
            currency: 'time_credit',
            payment_gateway: { free_supported: true, time_credit_supported: false, money_supported: false },
            permissions: { manage: true, reconcile: true, allocate_self: false },
            ticket_types: [],
            own_entitlements: [],
          },
        });
      }
      return Promise.resolve({ success: false });
    });
  });

  it('loads the canonical event before staff and exposes only implemented management tabs', async () => {
    const user = userEvent.setup();
    renderManagePage();

    expect(screen.getByRole('status', { name: 'Loading event management workspace...' })).toBeInTheDocument();
    await screen.findByRole('heading', { level: 1, name: 'Community Garden Day' });

    expect(mockApi.get.mock.calls.map(([url]) => url)).toEqual([
      '/v2/events/1',
      '/v2/events/1/staff?include_inactive=true',
    ]);
    expect(screen.getByRole('tab', { name: 'Overview' })).toBeInTheDocument();
    expect(screen.getByRole('tab', { name: 'People' })).toBeInTheDocument();
    expect(screen.getByRole('tab', { name: 'Registration, invitations and guests' })).toBeInTheDocument();
    expect(screen.getByRole('tab', { name: 'Check-in' })).toBeInTheDocument();
    expect(screen.getByRole('tab', { name: 'Agenda' })).toBeInTheDocument();
    expect(screen.getByRole('tab', { name: 'Team' })).toBeInTheDocument();
    expect(screen.getByRole('tab', { name: 'Templates' })).toBeInTheDocument();
    expect(screen.getByRole('tab', { name: 'Event analytics' })).toBeInTheDocument();
    expect(screen.getByRole('tab', { name: 'Tickets' })).toBeInTheDocument();
    expect(screen.queryByRole('tab', { name: /communications/i })).not.toBeInTheDocument();
    expect(screen.queryByRole('tab', { name: /finance/i })).not.toBeInTheDocument();

    await user.click(screen.getByRole('tab', { name: 'Team' }));
    expect(await screen.findByRole('heading', { name: 'Add a team member' })).toBeInTheDocument();
  });

  it('links the overview only to operations granted by the event contract', async () => {
    renderManagePage();
    await screen.findByRole('heading', { level: 1, name: 'Community Garden Day' });

    expect(screen.getByRole('link', { name: 'Edit event' })).toHaveAttribute('href', '/test/events/1/edit');
    expect(screen.getByRole('link', { name: 'Manage people' })).toHaveAttribute('href', '/test/events/1/manage/people');
    expect(screen.getByRole('link', { name: 'Registration, invitations and guests' })).toHaveAttribute('href', '/test/events/1/manage/registration');
    expect(screen.getByRole('link', { name: 'Open check-in' })).toHaveAttribute('href', '/test/events/1/manage/check-in');
    expect(screen.getByRole('link', { name: 'Manage agenda' })).toHaveAttribute('href', '/test/events/1/manage/agenda');
    expect(screen.getByRole('link', { name: 'Templates' })).toHaveAttribute('href', '/test/events/1/manage/templates');
    expect(screen.getByRole('link', { name: 'Event analytics' })).toHaveAttribute('href', '/test/events/1/manage/analytics');
    expect(screen.getByRole('link', { name: 'Tickets' })).toHaveAttribute('href', '/test/events/1/manage/tickets');
  });

  it('deep-links editors to the privacy-safe template library', async () => {
    renderManagePage('/test/events/1/manage/templates');

    const templatesTab = await screen.findByRole('tab', { name: 'Templates' });
    expect(templatesTab).toHaveAttribute('aria-selected', 'true');
    expect(await screen.findByRole('heading', { name: 'Event templates' })).toBeInTheDocument();
    expect(screen.getByText('Templates copy configuration only')).toBeInTheDocument();
    expect(mockApi.get).toHaveBeenCalledWith('/v2/event-templates?status=active&per_page=20');
  });

  it('deep-links authorized organizers to payload-free federation delivery status', async () => {
    renderManagePage('/test/events/1/manage/federation');

    const federationTab = await screen.findByRole('tab', { name: 'Federation' });
    expect(federationTab).toHaveAttribute('aria-selected', 'true');
    expect(await screen.findByRole('heading', { name: 'Federated event sharing' }))
      .toBeInTheDocument();
    expect(mockApi.get).toHaveBeenCalledWith(
      '/v2/events/1/federation-status',
      expect.objectContaining({ signal: expect.any(AbortSignal) }),
    );
  });

  it('deep-links organizers to privacy-safe analytics and finance-authorized ticket operations', async () => {
    const analytics = renderManagePage('/test/events/1/manage/analytics');

    expect(await screen.findByRole('tab', { name: 'Event analytics' }))
      .toHaveAttribute('aria-selected', 'true');
    expect(await screen.findByRole('heading', { name: 'Event analytics' })).toBeInTheDocument();
    expect(mockApi.get).toHaveBeenCalledWith(
      '/v2/events/1/analytics',
      expect.objectContaining({ signal: expect.any(AbortSignal) }),
    );

    analytics.unmount();
    renderManagePage('/test/events/1/manage/tickets');

    expect(await screen.findByRole('tab', { name: 'Tickets' }))
      .toHaveAttribute('aria-selected', 'true');
    expect(await screen.findByRole('heading', { name: 'Tickets' })).toBeInTheDocument();
    expect(screen.getByText('No ticket types have been configured for this event.')).toBeInTheDocument();
    expect(mockApi.get).toHaveBeenCalledWith(
      '/v2/events/1/tickets',
      expect.objectContaining({ signal: expect.any(AbortSignal) }),
    );
  });

  it('deep-links check-in-only staff without revealing People or Team', async () => {
    const checkInOnlyEvent = createCanonicalEventFixture({
      permissions: {
        ...createCanonicalEventFixture().permissions,
        check_in: true,
      },
    });
    mockApi.get.mockImplementation((url: string) => {
      if (url === '/v2/events/1') return Promise.resolve({ success: true, data: checkInOnlyEvent });
      if (url.startsWith('/v2/events/1/people?')) {
        return Promise.resolve({
          success: true,
          data: [],
          meta: {
            base_url: 'https://api.example.test',
            current_page: 1,
            per_page: 25,
            total: 0,
            total_pages: 0,
            has_more: false,
            search: null,
            registration_state: null,
            waitlist_state: null,
            attendance_state: null,
            engagement_state: null,
            sort: 'name',
            direction: 'asc',
            metrics: { confirmed: 0, checked_in: 0, checked_out: 0, no_show: 0, attended: 0 },
            projection: 'attendance',
            sensitive_fields_redacted: true,
            capabilities: {
              view_roster: true,
              view_waitlist: false,
              manage_registration: false,
              manage_attendance: true,
              export_people: false,
              view_history: true,
            },
          },
        });
      }
      return Promise.resolve({ success: false });
    });

    renderManagePage('/test/events/1/manage/check-in');

    const checkInTab = await screen.findByRole('tab', { name: 'Check-in' });
    expect(checkInTab).toHaveAttribute('aria-selected', 'true');
    expect(await screen.findByRole('heading', { name: 'Offline QR check-in' })).toBeInTheDocument();
    expect(await screen.findByRole('heading', { name: 'Manual check-in' })).toBeInTheDocument();
    expect(screen.queryByRole('tab', { name: 'People' })).not.toBeInTheDocument();
    expect(screen.queryByRole('tab', { name: 'Team' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /export/i })).not.toBeInTheDocument();
  });

  it('deep-links delegated registration managers without exposing unrelated organizer controls', async () => {
    const registrationOnlyEvent = createCanonicalEventFixture({
      permissions: {
        ...createCanonicalEventFixture().permissions,
        manage_registration: true,
      },
    });
    mockApi.get.mockImplementation((url: string) => (
      url === '/v2/events/1'
        ? Promise.resolve({ success: true, data: registrationOnlyEvent })
        : Promise.resolve({ success: false })
    ));

    renderManagePage('/test/events/1/manage/registration');

    const registrationTab = await screen.findByRole('tab', {
      name: 'Registration, invitations and guests',
    });
    expect(registrationTab).toHaveAttribute('aria-selected', 'true');
    expect(screen.getByRole('region', { name: 'Registration management workspace' }))
      .toHaveTextContent('registration-1');
    expect(screen.queryByRole('tab', { name: 'People' })).not.toBeInTheDocument();
    expect(screen.queryByRole('tab', { name: 'Team' })).not.toBeInTheDocument();
  });

  it('deep-links delegated broadcasters without exposing unrelated organizer controls', async () => {
    const broadcastOnlyEvent = createCanonicalEventFixture({
      permissions: {
        ...createCanonicalEventFixture().permissions,
        broadcast: true,
      },
    });
    mockApi.get.mockImplementation((url: string) => {
      if (url === '/v2/events/1') return Promise.resolve({ success: true, data: broadcastOnlyEvent });
      if (url === '/v2/events/1/broadcasts?page=1&per_page=20') {
        return Promise.resolve({
          success: true,
          data: [],
          meta: { current_page: 1, per_page: 20, total: 0, total_pages: 0, has_more: false },
        });
      }
      return Promise.resolve({ success: false });
    });

    renderManagePage('/test/events/1/manage/communications');

    const communicationsTab = await screen.findByRole('tab', { name: 'Organizer communications' });
    expect(communicationsTab).toHaveAttribute('aria-selected', 'true');
    expect(await screen.findByRole('heading', { name: 'Organizer communications' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'New message' })).toBeInTheDocument();
    expect(screen.queryByRole('tab', { name: 'People' })).not.toBeInTheDocument();
    expect(screen.queryByRole('tab', { name: 'Team' })).not.toBeInTheDocument();
    expect(screen.queryByRole('tab', { name: 'Event analytics' })).not.toBeInTheDocument();
  });

  it('normalizes legacy tab queries and permission-gates unknown sections', async () => {
    const { router } = renderManagePage('/test/events/1/manage?tab=team');
    expect(await screen.findByRole('heading', { name: 'Add a team member' })).toBeInTheDocument();
    expect(screen.getByRole('tab', { name: 'Team' })).toHaveAttribute('aria-selected', 'true');

    await router.navigate('/test/events/1/manage/not-a-section');
    expect(await screen.findByRole('heading', { name: 'Operational overview' })).toBeInTheDocument();
    expect(screen.getByRole('tab', { name: 'Overview' })).toHaveAttribute('aria-selected', 'true');
  });

  it('previews the privacy-safe People CSV fields before starting a download', async () => {
    mockApi.download.mockResolvedValue(new Blob(['member_id,member_name']));
    mockApi.get.mockImplementation((url: string) => {
      if (url === '/v2/events/1') return Promise.resolve({ success: true, data: manageableEvent });
      if (url === '/v2/events/1/staff?include_inactive=true') {
        return Promise.resolve({ success: true, data: [] });
      }
      if (url.startsWith('/v2/events/1/people?')) {
        return Promise.resolve({
          success: true,
          data: [],
          meta: {
            base_url: 'https://api.example.test',
            current_page: 1,
            per_page: 25,
            total: 17,
            total_pages: 1,
            has_more: false,
            search: null,
            registration_state: null,
            waitlist_state: null,
            attendance_state: null,
            engagement_state: null,
            sort: 'name',
            direction: 'asc',
            metrics: { confirmed: 12, waitlisted: 2, checked_in: 3, checked_out: 1, no_show: 0, attended: 4 },
            projection: 'full',
            sensitive_fields_redacted: true,
            capabilities: {
              view_roster: true,
              view_waitlist: true,
              manage_registration: true,
              manage_attendance: true,
              export_people: true,
              view_history: true,
            },
          },
        });
      }
      return Promise.resolve({ success: false });
    });
    const user = userEvent.setup();
    renderManagePage('/test/events/1/manage/people');

    const exportButton = await screen.findByRole('button', { name: 'Export CSV' });
    await user.click(exportButton);

    expect(await screen.findByRole('dialog')).toHaveTextContent('Review People CSV export');
    expect(screen.getByText('Registration form answers and custom question responses')).toBeInTheDocument();
    expect(screen.getByText('Incident, safeguarding and case-management records')).toBeInTheDocument();
    expect(screen.getByText('Health, accessibility, accommodation and private support notes')).toBeInTheDocument();
    expect(mockApi.download).not.toHaveBeenCalled();

    await user.click(screen.getByRole('button', { name: 'Download privacy-safe CSV' }));
    await waitFor(() => expect(mockApi.download).toHaveBeenCalledTimes(1));
  });

  it('does not request staff and shows an access boundary without implemented server permissions', async () => {
    const inaccessibleEvent = createCanonicalEventFixture();
    mockApi.get.mockResolvedValue({ success: true, data: inaccessibleEvent });
    renderManagePage();

    expect(await screen.findByRole('alert')).toHaveTextContent('Event management is not available');
    expect(mockApi.get).toHaveBeenCalledTimes(1);
    expect(mockApi.get).toHaveBeenCalledWith('/v2/events/1', expect.anything());
    expect(screen.queryByRole('tab')).not.toBeInTheDocument();
  });

  it('offers a retryable error when the event contract cannot be loaded', async () => {
    mockApi.get.mockResolvedValue({ success: false });
    renderManagePage();

    expect(await screen.findByRole('alert')).toHaveTextContent('Unable to load event management');
    await waitFor(() => expect(screen.getByRole('button', { name: 'Try again' })).toBeEnabled());
  });
});
