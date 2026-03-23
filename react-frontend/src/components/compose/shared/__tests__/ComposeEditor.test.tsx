// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for ComposeEditor — Lexical rich text editor component
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';

vi.mock('@/contexts', () => ({
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
  useTenant: () => ({ tenant: { id: 2, name: 'Test', slug: 'test', tagline: null }, branding: { name: 'Test', logo_url: null }, tenantSlug: 'test', tenantPath: (p: string) => '/test' + p, isLoading: false, hasFeature: vi.fn(() => true), hasModule: vi.fn(() => true) }),
  useToast: vi.fn(() => ({ success: vi.fn(), error: vi.fn(), info: vi.fn() })),
}));

import { ComposeEditor } from '../ComposeEditor';

describe('ComposeEditor', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders without crashing', () => {
    render(<ComposeEditor value="" onChange={vi.fn()} />);
    expect(document.body).toBeTruthy();
  });

  it('renders the content editable area with aria-label', () => {
    render(<ComposeEditor value="" onChange={vi.fn()} />);
    expect(screen.getByLabelText('Post content editor')).toBeInTheDocument();
  });

  it('renders toolbar buttons (Bold, Italic, Underline)', () => {
    render(<ComposeEditor value="" onChange={vi.fn()} />);
    expect(screen.getByLabelText('Bold')).toBeInTheDocument();
    expect(screen.getByLabelText('Italic')).toBeInTheDocument();
    expect(screen.getByLabelText('Underline')).toBeInTheDocument();
  });

  it('renders list formatting buttons', () => {
    render(<ComposeEditor value="" onChange={vi.fn()} />);
    expect(screen.getByLabelText('Bullet List')).toBeInTheDocument();
    expect(screen.getByLabelText('Numbered List')).toBeInTheDocument();
  });

  it('renders link button', () => {
    render(<ComposeEditor value="" onChange={vi.fn()} />);
    expect(screen.getByLabelText('Insert Link')).toBeInTheDocument();
  });

  it('renders placeholder text', () => {
    render(
      <ComposeEditor
        value=""
        onChange={vi.fn()}
        placeholder="Write something here..."
      />,
    );
    expect(screen.getByText('Write something here...')).toBeInTheDocument();
  });

  it('renders default placeholder when none provided', () => {
    render(<ComposeEditor value="" onChange={vi.fn()} />);
    expect(screen.getByText('What would you like to share?')).toBeInTheDocument();
  });

  it('renders max length indicator when maxLength is provided', () => {
    render(
      <ComposeEditor value="" onChange={vi.fn()} maxLength={500} />,
    );
    expect(screen.getByText('max 500 characters')).toBeInTheDocument();
  });

  it('does not render max length indicator when maxLength is not provided', () => {
    render(<ComposeEditor value="" onChange={vi.fn()} />);
    expect(screen.queryByText(/max.*characters/)).not.toBeInTheDocument();
  });

  it('applies disabled styling when isDisabled is true', () => {
    const { container } = render(
      <ComposeEditor value="" onChange={vi.fn()} isDisabled />,
    );
    // The outer wrapper should have the disabled class
    const wrapper = container.querySelector('.opacity-50');
    expect(wrapper).toBeInTheDocument();
  });
});
