// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for HashtagRenderer component
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@/test/test-utils';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

vi.mock('@/contexts', () => ({
  useTenant: vi.fn(() => ({
    tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
    tenantPath: (p: string) => `/test${p}`,
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

import { HashtagRenderer } from '../HashtagRenderer';

describe('HashtagRenderer', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders plain text without hashtags unchanged', () => {
    render(<HashtagRenderer content="Hello world, no hashtags here" />);
    expect(screen.getByText('Hello world, no hashtags here')).toBeInTheDocument();
  });

  it('renders a single hashtag as a clickable link', () => {
    render(<HashtagRenderer content="Check out #timebanking today!" />);
    const link = screen.getByRole('link', { name: '#timebanking' });
    expect(link).toBeInTheDocument();
    expect(link).toHaveAttribute('href', '/test/feed/hashtag/timebanking');
  });

  it('renders multiple hashtags as links', () => {
    render(<HashtagRenderer content="Loving #community and #timebanking" />);
    expect(screen.getByRole('link', { name: '#community' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: '#timebanking' })).toBeInTheDocument();
  });

  it('preserves surrounding text when rendering hashtags', () => {
    render(<HashtagRenderer content="Hello #world how are you?" />);
    expect(screen.getByText(/Hello/)).toBeInTheDocument();
    expect(screen.getByText(/how are you\?/)).toBeInTheDocument();
  });

  it('does not match short hashtags (1 character)', () => {
    render(<HashtagRenderer content="Category #a is too short" />);
    expect(screen.queryByRole('link')).not.toBeInTheDocument();
  });

  it('matches hashtags with 2 or more characters', () => {
    render(<HashtagRenderer content="Tag #ab is valid" />);
    expect(screen.getByRole('link', { name: '#ab' })).toBeInTheDocument();
  });

  it('applies custom className to the outer span', () => {
    const { container } = render(
      <HashtagRenderer content="Plain text" className="custom-class" />
    );
    const span = container.querySelector('span');
    expect(span).toHaveClass('custom-class');
  });

  it('stops click event propagation on hashtag links', () => {
    const parentClick = vi.fn();
    render(
      <div onClick={parentClick}>
        <HashtagRenderer content="Click #thishashtag please" />
      </div>
    );

    const link = screen.getByRole('link', { name: '#thishashtag' });
    fireEvent.click(link);
    expect(parentClick).not.toHaveBeenCalled();
  });

  it('renders content with no hashtags as a simple span', () => {
    const { container } = render(<HashtagRenderer content="Just text" />);
    expect(container.querySelector('span')).toBeInTheDocument();
    expect(container.querySelector('a')).not.toBeInTheDocument();
  });

  it('handles hashtags with underscores', () => {
    render(<HashtagRenderer content="Tag #good_health is valid" />);
    expect(screen.getByRole('link', { name: '#good_health' })).toBeInTheDocument();
  });
});
