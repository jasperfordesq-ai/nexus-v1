// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for GroupSubgroupsTab
 */

import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';

vi.mock('@/contexts', () => ({
  useTenant: vi.fn(() => ({
    tenant: { id: 2, slug: 'test' },
    tenantPath: (p: string) => `/test${p}`,
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
  useAuth: () => ({ user: null, isAuthenticated: false, login: vi.fn(), logout: vi.fn(), register: vi.fn(), updateUser: vi.fn(), refreshUser: vi.fn(), status: 'idle', error: null }),
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

import { GroupSubgroupsTab } from '../GroupSubgroupsTab';

const mockSubGroups = [
  { id: 10, name: 'Beginner Gardeners', member_count: 5 },
  { id: 11, name: 'Advanced Growers', member_count: 12 },
];

describe('GroupSubgroupsTab', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders subgroup names', () => {
    render(<GroupSubgroupsTab subGroups={mockSubGroups} />);
    expect(screen.getByText('Beginner Gardeners')).toBeInTheDocument();
    expect(screen.getByText('Advanced Growers')).toBeInTheDocument();
  });

  it('renders member counts for each subgroup', () => {
    render(<GroupSubgroupsTab subGroups={mockSubGroups} />);
    expect(screen.getByText('detail.members_count')).toBeInTheDocument();
  });

  it('renders navigation links for each subgroup', () => {
    render(<GroupSubgroupsTab subGroups={mockSubGroups} />);
    const links = screen.getAllByRole('link');
    expect(links).toHaveLength(2);
    expect(links[0]).toHaveAttribute('href', '/test/groups/10');
    expect(links[1]).toHaveAttribute('href', '/test/groups/11');
  });

  it('renders empty container when no subgroups', () => {
    render(<GroupSubgroupsTab subGroups={[]} />);
    // Empty space container — just verify no crash
    expect(document.body).toBeInTheDocument();
  });

  it('renders chevron icon for each subgroup row', () => {
    render(<GroupSubgroupsTab subGroups={mockSubGroups} />);
    // Each subgroup row has a ChevronRight icon (aria-hidden)
    const ariaHiddenIcons = document.querySelectorAll('[aria-hidden="true"]');
    expect(ariaHiddenIcons.length).toBeGreaterThan(0);
  });
});
