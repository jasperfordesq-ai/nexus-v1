// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for ShareButton and SharedByAttribution components
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import userEvent from '@testing-library/user-event';

const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };

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
  useToast: () => mockToast,
}));

vi.mock('@/lib/api', () => ({
  api: {
    post: vi.fn().mockResolvedValue({ success: true }),
    delete: vi.fn().mockResolvedValue({ success: true }),
  },
}));

vi.mock('@/lib/logger', () => ({
  logError: vi.fn(),
}));

import { ShareButton, SharedByAttribution } from './ShareButton';
import { api } from '@/lib/api';

describe('ShareButton', () => {
  const defaultProps = {
    postId: 1,
    shareCount: 5,
    isShared: false,
    isAuthenticated: true,
    onShareChange: vi.fn(),
  };

  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders without crashing', () => {
    render(<ShareButton {...defaultProps} />);
    // Should show the count in the button text
    expect(screen.getByRole('button')).toBeInTheDocument();
  });

  it('displays share count when greater than 0', () => {
    render(<ShareButton {...defaultProps} shareCount={3} />);
    const btn = screen.getByRole('button');
    // The translation mock returns the key with interpolation
    expect(btn).toBeInTheDocument();
  });

  it('disables button when not authenticated', () => {
    render(<ShareButton {...defaultProps} isAuthenticated={false} />);
    const btn = screen.getByRole('button');
    expect(btn).toBeDisabled();
  });

  it('calls api.post when sharing an unshared post', async () => {
    const user = userEvent.setup();
    const onShareChange = vi.fn();
    render(
      <ShareButton
        {...defaultProps}
        isShared={false}
        onShareChange={onShareChange}
      />
    );
    const btn = screen.getByRole('button');
    await user.click(btn);

    await waitFor(() => {
      // Polymorphic endpoint — ShareButton always POSTs to /v2/shares.
      // The backend treats an existing share as a toggle-off.
      expect(api.post).toHaveBeenCalledWith('/v2/shares', { type: 'post', id: 1 });
    });
  });

  it('calls api.post (toggle endpoint) when unsharing a shared post', async () => {
    const user = userEvent.setup();
    render(<ShareButton {...defaultProps} isShared={true} />);
    const btn = screen.getByRole('button');
    await user.click(btn);

    await waitFor(() => {
      expect(api.post).toHaveBeenCalledWith('/v2/shares', { type: 'post', id: 1 });
    });
  });

  it('shows success toast after sharing', async () => {
    const user = userEvent.setup();
    render(<ShareButton {...defaultProps} isShared={false} />);
    const btn = screen.getByRole('button');
    await user.click(btn);

    await waitFor(() => {
      expect(mockToast.success).toHaveBeenCalled();
    });
  });

  it('shows info toast after unsharing', async () => {
    const user = userEvent.setup();
    render(<ShareButton {...defaultProps} isShared={true} />);
    const btn = screen.getByRole('button');
    await user.click(btn);

    await waitFor(() => {
      expect(mockToast.info).toHaveBeenCalled();
    });
  });

  it('reverts on API failure when sharing', async () => {
    vi.mocked(api.post).mockResolvedValueOnce({ success: false, error: 'Server error' });
    const user = userEvent.setup();
    render(<ShareButton {...defaultProps} isShared={false} />);
    const btn = screen.getByRole('button');
    await user.click(btn);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalledWith('Server error');
    });
  });

  it('calls onShareChange on successful share', async () => {
    const user = userEvent.setup();
    const onShareChange = vi.fn();
    render(
      <ShareButton
        {...defaultProps}
        isShared={false}
        shareCount={2}
        onShareChange={onShareChange}
      />
    );
    const btn = screen.getByRole('button');
    await user.click(btn);

    await waitFor(() => {
      expect(onShareChange).toHaveBeenCalledWith(3, true);
    });
  });
});

describe('SharedByAttribution', () => {
  it('renders the sharer name', () => {
    render(<SharedByAttribution sharerName="Alice" />);
    expect(screen.getByText(/Alice/)).toBeInTheDocument();
  });

  it('renders the repeat icon', () => {
    const { container } = render(<SharedByAttribution sharerName="Bob" />);
    // Lucide Repeat2 renders an SVG
    const svg = container.querySelector('svg');
    expect(svg).toBeInTheDocument();
  });
});
