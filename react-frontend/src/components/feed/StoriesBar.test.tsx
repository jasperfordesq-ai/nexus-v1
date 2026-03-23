// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for StoriesBar component
 */

import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@/test/test-utils';

vi.mock('@/contexts', () => ({
  useTenant: vi.fn(() => ({
    tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
    tenantPath: (p: string) => `/test${p}`,
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
  useAuth: () => ({ user: null, isAuthenticated: false, login: vi.fn(), logout: vi.fn(), register: vi.fn(), updateUser: vi.fn(), refreshUser: vi.fn(), status: 'idle', error: null }),
  useToast: () => ({ success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() }),
}));

vi.mock('@/lib/helpers', () => ({
  resolveAvatarUrl: vi.fn((url) => url || '/default-avatar.png'),
  resolveAssetUrl: vi.fn((url) => url || ''),
  formatRelativeTime: vi.fn(() => '2 hours ago'),
}));

import { StoriesBar } from './StoriesBar';

const mockFriends = [
  { id: 1, name: 'Alice Smith', avatar_url: '/alice.png', is_online: true },
  { id: 2, name: 'Bob Jones', avatar_url: '/bob.png', is_online: false },
  { id: 3, name: 'Charlie', avatar_url: undefined, is_online: true },
];

describe('StoriesBar', () => {
  it('returns null when friends array is empty', () => {
    const { container } = render(<StoriesBar friends={[]} />);
    // Component returns null; wrapper providers still render their container elements
    expect(container.querySelector('.overflow-x-auto')).not.toBeInTheDocument();
  });

  it('returns null when friends is undefined-like', () => {
    const { container } = render(<StoriesBar friends={[] as never} />);
    expect(container.querySelector('.overflow-x-auto')).not.toBeInTheDocument();
  });

  it('renders friend avatars', () => {
    render(<StoriesBar friends={mockFriends} />);
    // First names are rendered (truncated to first word if needed)
    expect(screen.getByText('Alice')).toBeInTheDocument();
    expect(screen.getByText('Bob')).toBeInTheDocument();
    expect(screen.getByText('Charlie')).toBeInTheDocument();
  });

  it('renders links to friend profiles', () => {
    render(<StoriesBar friends={mockFriends} />);
    const links = screen.getAllByRole('link');
    expect(links).toHaveLength(3);
    expect(links[0]).toHaveAttribute('href', '/test/profile/1');
    expect(links[1]).toHaveAttribute('href', '/test/profile/2');
    expect(links[2]).toHaveAttribute('href', '/test/profile/3');
  });

  it('truncates long first names', () => {
    const friends = [
      { id: 10, name: 'Alexandria VeryLongLastName', avatar_url: '/alex.png' },
    ];
    render(<StoriesBar friends={friends} />);
    // "Alexandria" is 10 chars, truncated to 8 chars + "..."
    expect(screen.getByText('Alexandr...')).toBeInTheDocument();
  });

  it('shows online indicator for online friends', () => {
    const { container } = render(<StoriesBar friends={mockFriends} />);
    // Online indicators are span elements with green background
    const onlineIndicators = container.querySelectorAll('.bg-green-500');
    // Alice and Charlie are online
    expect(onlineIndicators.length).toBe(2);
  });

  it('does not show online indicator for offline friends', () => {
    const offlineFriends = [{ id: 1, name: 'Bob', is_online: false }];
    const { container } = render(<StoriesBar friends={offlineFriends} />);
    const onlineIndicators = container.querySelectorAll('.bg-green-500');
    expect(onlineIndicators.length).toBe(0);
  });
});
