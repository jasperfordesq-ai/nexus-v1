// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for HoursReviewTab
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';

import React from "react";
vi.mock('framer-motion', () => ({
  motion: new Proxy({}, {
    get: (_: object, prop: string | symbol) => {
      return React.forwardRef(({ children, ...props }: Record<string, unknown>, ref: React.Ref<HTMLElement>) => {
        const motionProps = ['variants','initial','animate','exit','transition','whileHover','whileTap','layout','layoutId','viewport'];
        const clean: Record<string, unknown> = {};
        for (const [k, v] of Object.entries(props)) {
          if (!motionProps.includes(k)) clean[k] = v;
        }
        return React.createElement(typeof prop === 'string' ? prop : 'div', { ...clean, ref }, children);
      });
    },
  }),
  AnimatePresence: ({ children }: { children: React.ReactNode }) => children,
}));

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn().mockResolvedValue({ success: true, data: { items: [], cursor: null, has_more: false } }),
    put: vi.fn().mockResolvedValue({ success: true }),
  },
}));

vi.mock('@/contexts', () => ({
  useToast: vi.fn(() => ({
    success: vi.fn(),
    error: vi.fn(),
    info: vi.fn(),
    warning: vi.fn(),
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
  useTenant: () => ({ tenant: { id: 2, name: 'Test', slug: 'test', tagline: null }, branding: { name: 'Test', logo_url: null }, tenantSlug: 'test', tenantPath: (p) => '/test' + p, isLoading: false, hasFeature: vi.fn(() => true), hasModule: vi.fn(() => true) }),
}));

vi.mock('@/components/ui', () => ({
  GlassCard: ({ children, className }: { children: React.ReactNode; className?: string }) => (
    <div data-testid="glass-card" className={className}>{children}</div>
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

vi.mock('@/lib/logger', () => ({
  logError: vi.fn(),
}));

import { HoursReviewTab } from './HoursReviewTab';
import { api } from '@/lib/api';

const makeEntry = (id = 1, status: 'pending' | 'approved' | 'declined' = 'pending') => ({
  id,
  hours: 3,
  date: '2026-02-15',
  description: 'Helped at the food bank.',
  status,
  created_at: '2026-02-15T14:00:00Z',
  user: { id: 10, name: 'Jane Doe', avatar_url: null },
  organization: { id: 5, name: 'Green Help', logo_url: null },
  opportunity: { id: 20, title: 'Food Bank Volunteer' },
});

describe('HoursReviewTab', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows empty state when no pending entries', async () => {
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: { items: [], cursor: null, has_more: false },
    });
    render(<HoursReviewTab />);
    await waitFor(() => {
      expect(screen.getByText('No hours pending review.')).toBeInTheDocument();
    });
  });

  it('renders a pending entry with approve and decline buttons', async () => {
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: { items: [makeEntry(1, 'pending')], cursor: null, has_more: false },
    });
    render(<HoursReviewTab />);
    await waitFor(() => {
      expect(screen.getByText('Jane Doe')).toBeInTheDocument();
    });
    expect(screen.getByText('Green Help')).toBeInTheDocument();
    expect(screen.getByText(/Food Bank Volunteer/)).toBeInTheDocument();
    expect(screen.getByText(/3 hours/)).toBeInTheDocument();
    expect(screen.getByText('Helped at the food bank.')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /Approve hours for Jane Doe/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /Decline hours for Jane Doe/i })).toBeInTheDocument();
  });

  it('calls PUT with approve action when Approve is clicked', async () => {
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: { items: [makeEntry(1, 'pending')], cursor: null, has_more: false },
    });
    vi.mocked(api.put).mockResolvedValue({ success: true });
    render(<HoursReviewTab />);
    await waitFor(() => screen.getByRole('button', { name: /Approve hours for Jane Doe/i }));

    fireEvent.click(screen.getByRole('button', { name: /Approve hours for Jane Doe/i }));
    await waitFor(() => {
      expect(api.put).toHaveBeenCalledWith('/v2/volunteering/hours/1/verify', { action: 'approve' });
    });
  });

  it('calls PUT with decline action when Decline is clicked', async () => {
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: { items: [makeEntry(1, 'pending')], cursor: null, has_more: false },
    });
    vi.mocked(api.put).mockResolvedValue({ success: true });
    render(<HoursReviewTab />);
    await waitFor(() => screen.getByRole('button', { name: /Decline hours for Jane Doe/i }));

    fireEvent.click(screen.getByRole('button', { name: /Decline hours for Jane Doe/i }));
    await waitFor(() => {
      expect(api.put).toHaveBeenCalledWith('/v2/volunteering/hours/1/verify', { action: 'decline' });
    });
  });

  it('shows Load more button when has_more is true', async () => {
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: { items: [makeEntry(1, 'pending')], cursor: 'cursor-abc', has_more: true },
    });
    render(<HoursReviewTab />);
    await waitFor(() => {
      expect(screen.getByRole('button', { name: /Load more/i })).toBeInTheDocument();
    });
  });

  it('does not show Load more button when has_more is false', async () => {
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: { items: [makeEntry(1, 'pending')], cursor: null, has_more: false },
    });
    render(<HoursReviewTab />);
    await waitFor(() => {
      expect(screen.getByText('Jane Doe')).toBeInTheDocument();
    });
    expect(screen.queryByRole('button', { name: /Load more/i })).not.toBeInTheDocument();
  });

  it('renders correctly for a single hour (no plural)', async () => {
    const singleHourEntry = { ...makeEntry(1, 'pending'), hours: 1 };
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: { items: [singleHourEntry], cursor: null, has_more: false },
    });
    render(<HoursReviewTab />);
    await waitFor(() => {
      expect(screen.getByText('1 hour')).toBeInTheDocument();
    });
  });
});
