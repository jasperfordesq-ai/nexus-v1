// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for GroupMembersTab
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

vi.mock('@/components/feedback', () => ({
  EmptyState: ({ title }: { title: string }) => (
    <div data-testid="empty-state"><h2>{title}</h2></div>
  ),
}));

vi.mock('@/lib/helpers', () => ({
  resolveAvatarUrl: vi.fn((url) => url || '/default-avatar.png'),
}));

import { GroupMembersTab } from '../GroupMembersTab';
import type { GroupMember } from '../GroupMembersTab';

const mockMembers: GroupMember[] = [
  { id: 1, name: 'Alice Owner', first_name: 'Alice', last_name: 'Owner', avatar: null, role: 'admin', joined_at: '2026-01-01T10:00:00Z' } as GroupMember,
  { id: 2, name: 'Bob Admin', first_name: 'Bob', last_name: 'Admin', avatar: null, role: 'admin', joined_at: '2026-01-03T10:00:00Z' } as GroupMember,
  { id: 3, name: 'Charlie Member', first_name: 'Charlie', last_name: 'Member', avatar: null, role: 'member', joined_at: '2026-01-05T10:00:00Z' } as GroupMember,
];

const defaultProps = {
  members: mockMembers,
  membersLoading: false,
  userIsAdmin: false,
  currentUserId: 99,
  groupOwnerId: 1,
  groupAdminIds: [1, 2],
  updatingMember: null,
  onUpdateMemberRole: vi.fn(),
  onRemoveMember: vi.fn(),
};

describe('GroupMembersTab', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders member names', () => {
    render(<GroupMembersTab {...defaultProps} />);
    expect(screen.getByText('Alice Owner')).toBeInTheDocument();
    expect(screen.getByText('Bob Admin')).toBeInTheDocument();
    expect(screen.getByText('Charlie Member')).toBeInTheDocument();
  });

  it('shows loading spinner when membersLoading is true', () => {
    render(<GroupMembersTab {...defaultProps} membersLoading={true} />);
    // Spinner should be present
    expect(document.querySelector('svg') || document.querySelector('[class*="spinner"]')).toBeTruthy();
  });

  it('shows empty state when no members', () => {
    render(<GroupMembersTab {...defaultProps} members={[]} />);
    expect(screen.getByTestId('empty-state')).toBeInTheDocument();
  });

  it('shows admin badge for admin members', () => {
    render(<GroupMembersTab {...defaultProps} />);
    // Translation: detail.member_admin -> "Admin"
    expect(screen.getByText('Admin')).toBeInTheDocument();
  });

  it('shows management dropdown for admin user', () => {
    render(<GroupMembersTab {...defaultProps} userIsAdmin={true} />);
    // Management buttons/dropdowns should be visible
    const moreButtons = screen.getAllByRole('button');
    expect(moreButtons.length).toBeGreaterThan(0);
  });

  it('does not show management options for regular user', () => {
    render(<GroupMembersTab {...defaultProps} userIsAdmin={false} />);
    // No management dropdown trigger buttons for non-admin
    const moreButtons = screen.queryAllByRole('button');
    // Regular user sees 0 management buttons since userIsAdmin=false
    expect(moreButtons.length).toBe(0);
  });
});
