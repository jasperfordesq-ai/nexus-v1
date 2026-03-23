// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for MobileFAB component
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';
import userEvent from '@testing-library/user-event';

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

vi.mock('framer-motion', () => ({
  motion: {
    div: ({ children, ...props }: Record<string, unknown>) => {
      const { variants, initial, animate, exit, layout, transition, whileHover, whileTap, ...rest } = props;
      void variants; void initial; void animate; void exit; void layout; void transition; void whileHover; void whileTap;
      return <div {...rest}>{children as React.ReactNode}</div>;
    },
  },
  AnimatePresence: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

import { MobileFAB } from './MobileFAB';

describe('MobileFAB', () => {
  const mockOnPress = vi.fn();

  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders without crashing', () => {
    render(<MobileFAB onPress={mockOnPress} />);
    expect(screen.getByLabelText('Create post')).toBeInTheDocument();
  });

  it('renders with correct aria-label', () => {
    render(<MobileFAB onPress={mockOnPress} />);
    const btn = screen.getByLabelText('Create post');
    expect(btn.tagName).toBe('BUTTON');
  });

  it('calls onPress when clicked', async () => {
    const user = userEvent.setup();
    render(<MobileFAB onPress={mockOnPress} />);
    const btn = screen.getByLabelText('Create post');
    await user.click(btn);
    expect(mockOnPress).toHaveBeenCalledOnce();
  });

  it('renders the button as icon-only (no visible text)', () => {
    render(<MobileFAB onPress={mockOnPress} />);
    const btn = screen.getByLabelText('Create post');
    // Icon-only button should not have visible text content beyond the SVG
    expect(btn.textContent).toBe('');
  });
});
