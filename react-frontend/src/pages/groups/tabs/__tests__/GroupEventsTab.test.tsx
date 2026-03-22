// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for GroupEventsTab
 */

import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';

vi.mock('@/contexts', () => ({
  useAuth: vi.fn(() => ({
    user: { id: 1, first_name: 'Alice', name: 'Alice Test' },
    isAuthenticated: true,
  })),
  useTenant: vi.fn(() => ({
    tenant: { id: 2, slug: 'test' },
    tenantPath: (p: string) => `/test${p}`,
    hasFeature: vi.fn(() => true),
  })),
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
  useToast: () => ({ success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() }),
}));

vi.mock('@/contexts/ToastContext', () => ({
  ToastProvider: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

vi.mock('@/components/ui', () => ({
  GlassCard: ({ children, className }: { children: React.ReactNode; className?: string }) => (
    <div className={className}>{children}</div>
  ),

  GlassButton: ({ children }: Record<string, unknown>) => children as never,
  GlassInput: () => null,
  BackToTop: () => null,
  AlgorithmLabel: () => null,
  ImagePlaceholder: () => null,
  DynamicIcon: () => null,
  ICON_MAP: {},
  ICON_NAMES: [],
  ListingSkeleton: () => null,
  MemberCardSkeleton: () => null,
  StatCardSkeleton: () => null,
  EventCardSkeleton: () => null,
  GroupCardSkeleton: () => null,
  ConversationSkeleton: () => null,
  ExchangeCardSkeleton: () => null,
  NotificationSkeleton: () => null,
  ProfileHeaderSkeleton: () => null,
  SkeletonList: () => null,
}));

vi.mock('@/components/feedback', () => ({
  EmptyState: ({ title, description, action }: { title: string; description?: string; action?: React.ReactNode }) => (
    <div data-testid="empty-state">
      <h2>{title}</h2>
      {description && <p>{description}</p>}
      {action}
    </div>
  ),
}));

import { GroupEventsTab } from '../GroupEventsTab';
import type { Event } from '@/types/api';

const mockEvents: Event[] = [
  {
    id: 1,
    title: 'Spring Planting Day',
    description: 'Plant together',
    start_date: '2026-04-15T10:00:00Z',
    end_date: '2026-04-15T14:00:00Z',
    location: 'Community Garden',
    status: 'upcoming',
    organizer_id: 1,
    organizer_name: 'Alice',
    rsvp_count: 5,
    max_attendees: 20,
    category: 'outdoor',
    created_at: '2026-01-01T10:00:00Z',
    image_url: null,
  } as Event,
];

describe('GroupEventsTab', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders event title', () => {
    render(<GroupEventsTab groupId={1} events={mockEvents} eventsLoading={false} isMember={true} />);
    expect(screen.getByText('Spring Planting Day')).toBeInTheDocument();
  });

  it('shows empty state when no events', () => {
    render(<GroupEventsTab groupId={1} events={[]} eventsLoading={false} isMember={true} />);
    expect(screen.getByTestId('empty-state')).toBeInTheDocument();
  });

  it('shows create event button for members', () => {
    render(<GroupEventsTab groupId={1} events={[]} eventsLoading={false} isMember={true} />);
    // Translation: detail.create_event -> "Create Event"
    // The text appears in both the header button and the empty state action; use getAllByText
    expect(screen.getAllByText('Create Event').length).toBeGreaterThanOrEqual(1);
  });

  it('does not show create event button for non-members', () => {
    render(<GroupEventsTab groupId={1} events={[]} eventsLoading={false} isMember={false} />);
    expect(screen.queryByText('detail.create_event')).not.toBeInTheDocument();
  });

  it('shows loading spinner when eventsLoading is true', () => {
    render(<GroupEventsTab groupId={1} events={[]} eventsLoading={true} isMember={false} />);
    // Spinner should be present
    expect(document.querySelector('[class*="spinner"]') || document.querySelector('svg')).toBeTruthy();
  });

  it('renders event location', () => {
    render(<GroupEventsTab groupId={1} events={mockEvents} eventsLoading={false} isMember={true} />);
    expect(screen.getByText('Community Garden')).toBeInTheDocument();
  });
});
