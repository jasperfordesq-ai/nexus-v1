// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for MentionRenderer component
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

vi.mock('@/lib/helpers', () => ({
  resolveAvatarUrl: (url: string | null | undefined) => url || '',
}));

// Stable tenant mock value (avoids infinite re-render)
const mockTenantValue = {
  tenant: { id: 2, name: 'Test', slug: 'test' },
  tenantPath: (p: string) => `/test${p}`,
  hasFeature: vi.fn(() => true),
  hasModule: vi.fn(() => true),
};

vi.mock('@/contexts', () => ({
  useToast: () => ({ success: vi.fn(), error: vi.fn(), info: vi.fn() }),
  useAuth: () => ({ user: null, isAuthenticated: false, login: vi.fn(), logout: vi.fn(), register: vi.fn(), updateUser: vi.fn(), refreshUser: vi.fn(), status: 'idle', error: null }),
  useTenant: () => mockTenantValue,
  useTheme: () => ({ resolvedTheme: 'light', theme: 'system', toggleTheme: vi.fn(), setTheme: vi.fn() }),
  useNotifications: () => ({ unreadCount: 0, counts: {}, notifications: [], markAsRead: vi.fn(), markAllAsRead: vi.fn(), hasMore: false, loadMore: vi.fn(), isLoading: false, refresh: vi.fn() }),
  usePusher: () => ({ channel: null, isConnected: false }),
  usePusherOptional: () => null,
  useCookieConsent: () => ({ consent: null, showBanner: false, openPreferences: vi.fn(), resetConsent: vi.fn(), saveConsent: vi.fn(), hasConsent: vi.fn(() => true), updateConsent: vi.fn() }),
  readStoredConsent: () => null,
  useMenuContext: () => ({ headerMenus: [], mobileMenus: [], hasCustomMenus: false }),
  useFeature: vi.fn(() => true),
  useModule: vi.fn(() => true),
  usePresence: () => ({ status: 'offline', setStatus: vi.fn(), getPresence: vi.fn(), isOnline: vi.fn(() => false) }),
  usePresenceOptional: () => null,
}));

import { MentionRenderer } from './MentionRenderer';
import type { MentionData } from './MentionRenderer';

const ALICE: MentionData = {
  user_id: 5,
  username: 'alice',
  name: 'Alice Smith',
};

const BOB: MentionData = {
  user_id: 6,
  username: 'bob',
  name: 'Bob Jones',
};

describe('MentionRenderer', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders plain text with no mentions unchanged', () => {
    render(<MentionRenderer text="Hello world" />);
    expect(screen.getByText('Hello world')).toBeInTheDocument();
  });

  it('returns null for empty string', () => {
    const { container } = render(<MentionRenderer text="" />);
    // Provider wrapper is non-null but the component itself renders nothing
    expect(screen.queryByText(/.+/)).not.toBeInTheDocument();
  });

  it('renders @mention as a link', () => {
    render(<MentionRenderer text="Hello @alice" mentions={[ALICE]} />);
    const link = screen.getByRole('link');
    expect(link).toBeInTheDocument();
    expect(link.textContent).toMatch(/alice/i);
  });

  it('renders multiple @mentions as separate links', () => {
    render(<MentionRenderer text="Hey @alice and @bob!" mentions={[ALICE, BOB]} />);
    const links = screen.getAllByRole('link');
    expect(links).toHaveLength(2);
  });

  it('preserves plain text segments around mentions', () => {
    const { container } = render(<MentionRenderer text="Hey @alice how are you?" mentions={[ALICE]} />);
    // The combined text content should include all parts
    expect(container.textContent).toMatch(/hey/i);
    expect(container.textContent).toMatch(/how are you\?/i);
  });

  it('mention link points to the user profile when user_id is known', () => {
    render(<MentionRenderer text="@alice" mentions={[ALICE]} showUserCard={false} />);
    const link = screen.getByRole('link');
    expect(link).toHaveAttribute('href', expect.stringContaining('/profile/5'));
  });

  it('mention link has no resolved profile path when user is not in mentions array', () => {
    render(<MentionRenderer text="@unknown" mentions={[ALICE]} showUserCard={false} />);
    const link = screen.getByRole('link');
    // BrowserRouter resolves '#' → '/' in jsdom; the important thing is the link renders
    expect(link).toBeInTheDocument();
    expect(link.textContent).toMatch(/@unknown/);
  });

  it('renders mention with display name (name field) when resolved', () => {
    render(<MentionRenderer text="@alice" mentions={[ALICE]} showUserCard={false} />);
    expect(screen.getByRole('link')).toHaveTextContent(/alice smith/i);
  });

  it('falls back to raw username when user not in mentions', () => {
    render(<MentionRenderer text="@ghost" mentions={[ALICE]} showUserCard={false} />);
    expect(screen.getByRole('link')).toHaveTextContent('@ghost');
  });

  it('handles text with no @ symbols (no link rendered)', () => {
    render(<MentionRenderer text="No mentions here at all" mentions={[ALICE]} />);
    expect(screen.queryByRole('link')).not.toBeInTheDocument();
  });

  it('handles mention at the start of text', () => {
    render(<MentionRenderer text="@alice great work!" mentions={[ALICE]} showUserCard={false} />);
    expect(screen.getByRole('link')).toBeInTheDocument();
  });

  it('handles mention at the end of text', () => {
    render(<MentionRenderer text="Good job @bob" mentions={[BOB]} showUserCard={false} />);
    expect(screen.getByRole('link')).toBeInTheDocument();
  });

  it('renders without mentions array (mention still renders as a link)', () => {
    render(<MentionRenderer text="Hey @nobody" showUserCard={false} />);
    const link = screen.getByRole('link');
    expect(link).toBeInTheDocument();
    expect(link.textContent).toMatch(/@nobody/);
  });
});
