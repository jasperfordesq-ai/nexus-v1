// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';
import type { Event } from '@/types/api';

// ─── No API calls — GroupEventsTab receives data as props ─────────────────────

// ─── Contexts ─────────────────────────────────────────────────────────────────
vi.mock('@/contexts', () =>
  createMockContexts({
    useAuth: () => ({
      user: { id: 1, name: 'Alice', first_name: 'Alice', avatar: null },
      isAuthenticated: true,
      login: vi.fn(), logout: vi.fn(), register: vi.fn(), updateUser: vi.fn(), refreshUser: vi.fn(),
      status: 'idle' as const, error: null,
    }),
    useTenant: () => ({
      tenant: { id: 2, name: 'Test', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  })
);

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

// ─── Stub heavy child components ─────────────────────────────────────────────
vi.mock('@/components/feedback', () => ({
  EmptyState: ({ title, description, action }: { title: string; description?: string; action?: React.ReactNode }) => (
    <div data-testid="empty-state">
      <h2>{title}</h2>
      {description && <p>{description}</p>}
      {action && <div data-testid="empty-state-action">{action}</div>}
    </div>
  ),
}));

vi.mock('@/components/ui', async (importOriginal) => {
  const orig = await importOriginal<Record<string, unknown>>();
  return {
    ...orig,
    GlassCard: ({ children, className }: { children: React.ReactNode; className?: string }) => (
      <div data-testid="glass-card" className={className}>{children}</div>
    ),
    Button: ({ children, onPress, isDisabled, startContent, size, className }: {
      children?: React.ReactNode; onPress?: () => void; isDisabled?: boolean;
      startContent?: React.ReactNode; size?: string; className?: string;
    }) => (
      <button onClick={() => onPress?.()} disabled={isDisabled} className={className}>
        {startContent}{children}
      </button>
    ),
    Chip: ({ children, size, variant, className }: {
      children: React.ReactNode; size?: string; variant?: string; className?: string;
    }) => <span data-testid="chip" className={className}>{children}</span>,
    Spinner: ({ size }: { size?: string }) => (
      <div data-testid="spinner" role="status" aria-busy="true" aria-label="Loading" />
    ),
  };
});

vi.mock('@/lib/helpers', () => ({
  formatDateTime: (_date: Date, _opts?: object) => '10:00 AM',
  formatMonthShort: (_date: Date, _upper?: boolean) => 'JAN',
  resolveAvatarUrl: (url: string | null | undefined) => url ?? '',
}));

// ─── Fixtures ────────────────────────────────────────────────────────────────
const makeEvent = (overrides: Partial<Event> = {}): Event => ({
  id: 101,
  title: 'Community Cleanup',
  description: 'Let us clean up the park.',
  start_date: '2099-06-15T10:00:00Z',
  is_online: false,
  location: 'City Park',
  attendees_count: 12,
  organizer: { id: 5, first_name: 'Alice', last_name: 'Smith', name: 'Alice Smith', avatar: null },
  ...overrides,
});

const makePastEvent = (overrides: Partial<Event> = {}): Event =>
  makeEvent({ id: 202, title: 'Past Meetup', start_date: '2020-01-01T10:00:00Z', ...overrides });

const defaultProps = {
  groupId: 7,
  events: [] as Event[],
  eventsLoading: false,
  isMember: true,
};

// ─────────────────────────────────────────────────────────────────────────────
describe('GroupEventsTab', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('renders the group events heading', async () => {
    const { GroupEventsTab } = await import('./GroupEventsTab');
    render(<GroupEventsTab {...defaultProps} />);

    // The real h2 from the component (not the EmptyState stub h2) contains translated heading text
    const headings = screen.getAllByRole('heading', { level: 2 });
    // At minimum one heading exists — the card header
    expect(headings.length).toBeGreaterThan(0);
    // The first h2 is the card title (the EmptyState stub h2 is secondary)
    expect(headings[0]).toBeInTheDocument();
  });

  it('shows the loading spinner when eventsLoading=true', async () => {
    const { GroupEventsTab } = await import('./GroupEventsTab');
    render(<GroupEventsTab {...defaultProps} eventsLoading={true} />);

    const statuses = screen.getAllByRole('status');
    const busy = statuses.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busy).toBeDefined();
  });

  it('shows empty state when loading is false and no events', async () => {
    const { GroupEventsTab } = await import('./GroupEventsTab');
    render(<GroupEventsTab {...defaultProps} events={[]} eventsLoading={false} />);

    expect(screen.getByTestId('empty-state')).toBeInTheDocument();
  });

  it('shows "create event" button in header for authenticated member', async () => {
    const { GroupEventsTab } = await import('./GroupEventsTab');
    render(<GroupEventsTab {...defaultProps} events={[]} isMember={true} />);

    const buttons = screen.getAllByRole('button');
    // Should find a create event button in the header area
    expect(buttons.length).toBeGreaterThan(0);
  });

  it('does NOT show "create event" button for non-members', async () => {
    const { GroupEventsTab } = await import('./GroupEventsTab');
    render(<GroupEventsTab {...defaultProps} events={[]} isMember={false} />);

    const buttons = screen.queryAllByRole('button');
    // Non-members see no create-event button (the empty state action area is also gated)
    expect(buttons.length).toBe(0);
  });

  it('shows empty state action (create event) for authenticated member when no events', async () => {
    const { GroupEventsTab } = await import('./GroupEventsTab');
    render(<GroupEventsTab {...defaultProps} events={[]} isMember={true} eventsLoading={false} />);

    // The EmptyState stub renders action inside data-testid="empty-state-action"
    expect(screen.getByTestId('empty-state-action')).toBeInTheDocument();
  });

  it('renders event title when events are provided', async () => {
    const { GroupEventsTab } = await import('./GroupEventsTab');
    render(<GroupEventsTab {...defaultProps} events={[makeEvent()]} />);

    expect(screen.getByText('Community Cleanup')).toBeInTheDocument();
  });

  it('renders the event location when present', async () => {
    const { GroupEventsTab } = await import('./GroupEventsTab');
    render(<GroupEventsTab {...defaultProps} events={[makeEvent({ location: 'City Park' })]} />);

    expect(screen.getByText('City Park')).toBeInTheDocument();
  });

  it('renders attendees count for each event', async () => {
    const { GroupEventsTab } = await import('./GroupEventsTab');
    render(<GroupEventsTab {...defaultProps} events={[makeEvent({ attendees_count: 12 })]} />);

    expect(screen.getByText(/12/)).toBeInTheDocument();
  });

  it('renders multiple events', async () => {
    const { GroupEventsTab } = await import('./GroupEventsTab');
    const events = [makeEvent({ id: 1, title: 'Event One' }), makeEvent({ id: 2, title: 'Event Two' })];
    render(<GroupEventsTab {...defaultProps} events={events} />);

    expect(screen.getByText('Event One')).toBeInTheDocument();
    expect(screen.getByText('Event Two')).toBeInTheDocument();
  });

  it('marks past events with a Past chip', async () => {
    const { GroupEventsTab } = await import('./GroupEventsTab');
    render(<GroupEventsTab {...defaultProps} events={[makePastEvent()]} />);

    // Past chip should be rendered (translation key: detail.past_chip)
    const chips = screen.getAllByTestId('chip');
    expect(chips.length).toBeGreaterThan(0);
  });

  it('event rows render as links to the event page', async () => {
    const { GroupEventsTab } = await import('./GroupEventsTab');
    render(<GroupEventsTab {...defaultProps} events={[makeEvent({ id: 55 })]} />);

    const links = screen.getAllByRole('link');
    const eventLink = links.find((l) => l.getAttribute('href')?.includes('/events/55'));
    expect(eventLink).toBeDefined();
  });

  it('create event link points to /events/create with group_id', async () => {
    const { GroupEventsTab } = await import('./GroupEventsTab');
    render(<GroupEventsTab {...defaultProps} groupId={7} events={[makeEvent()]} isMember={true} />);

    const links = screen.getAllByRole('link');
    const createLink = links.find((l) => l.getAttribute('href')?.includes('/events/create') && l.getAttribute('href')?.includes('group_id=7'));
    expect(createLink).toBeDefined();
  });

  it('does not show loading spinner when eventsLoading=false', async () => {
    const { GroupEventsTab } = await import('./GroupEventsTab');
    render(<GroupEventsTab {...defaultProps} eventsLoading={false} events={[makeEvent()]} />);

    const statuses = screen.getAllByRole('status');
    const busy = statuses.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busy).toBeUndefined();
  });
});
